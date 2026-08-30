<?php

require_once __DIR__.'/html_compat.func.php';
require_once dirname(__DIR__).'/xiunophp/plugin_identifier.func.php';

 // 本地插件
//$plugin_srcfiles = array();
$plugin_paths = array();
$plugins = array(); // 跟官方插件合并

// 官方插件列表
$official_plugins = array();

// Enabled-package state and the actual Hook/overwrite file index share one request-local
// generation. Lifecycle/cache invalidation must advance it so the same request cannot keep
// compiling against a stale enabled set or stale file list.
$g_plugin_file_index_generation = 1;
$g_plugin_enabled_paths_generation = 0;
$g_plugin_enabled_paths = array();
$g_plugin_file_index_built_generation = 0;
$g_plugin_file_index = array();
$g_plugin_state_write_lock = NULL;
$g_plugin_state_read_lock = NULL;
$g_plugin_include_reader_locks = array();
$g_plugin_include_state_lock = NULL;

define('PLUGIN_OFFICIAL_URL', DEBUG == 4 ? 'http://plugin.x.com/' : 'http://plugin.xiuno.com/');

function plugin_cache_stage_write($file, $s) {
	$tmpfile = $file.'.'.getmypid().'.'.str_replace('.', '', uniqid('', TRUE)).'.tmp';
	$length = strlen($s);
	$written = file_put_contents_try($tmpfile, $s);
	if($written !== $length) {
		xn_unlink($tmpfile);
		return FALSE;
	}
	return $tmpfile;
}

// Parse generated cache bytes with the same PHP engine before publication. TOKEN_PARSE understands
// mixed PHP/HTML templates and catches syntax errors without assuming that exec() or a separate CLI
// binary is available in php-fpm deployments.
function plugin_cache_php_syntax_valid($s, &$detail = '') {
	$detail = '';
	if(!is_string($s) || !defined('TOKEN_PARSE')) {
		$detail = 'PHP parser is unavailable for generated cache validation.';
		return FALSE;
	}
	try {
		token_get_all($s, TOKEN_PARSE);
		return TRUE;
	} catch(Throwable $e) {
		$detail = $e->getMessage();
		return FALSE;
	}
}

function plugin_cache_write_atomic($file, $s, $source = '') {
	$tmpfile = plugin_cache_stage_write($file, $s);
	if($tmpfile === FALSE) return FALSE;
	$written = strlen($s);

	$lock = fopen($file.'.lock', 'c');
	if(!$lock || !flock($lock, LOCK_EX)) {
		$lock AND fclose($lock);
		xn_unlink($tmpfile);
		return FALSE;
	}

	try {
		$current = is_file($file) ? file_get_contents_try($file) : FALSE;
		$syntax_detail = '';
		if(!plugin_cache_php_syntax_valid($s, $syntax_detail)) {
			xn_unlink($tmpfile);
			// A pre-existing cache with the same malformed bytes predates this guard. It is not a
			// last-known-good generation; remove it while the exclusive publish lease proves that no
			// compliant reader can still be waiting to open the path.
			if($current === $s && is_file($file)) xn_unlink($file);
			$source = $source === '' ? $file : $source;
			$syntax_detail = str_replace(array("\r", "\n"), ' ', $syntax_detail);
			error_log('Generated PHP cache syntax preflight failed target='.$file.' source='.$source.' detail='.$syntax_detail);
			return FALSE;
		}
		if($current === $s) {
			xn_unlink($tmpfile);
			return $written;
		}
		if(@rename($tmpfile, $file)) {
			clearstatcache(TRUE, $file);
			return $written;
		}

		// Never unlink an already published cache as a rename fallback. _include() returns the path
		// before PHP opens it; removing that path creates a reader race on filesystems that cannot
		// replace an existing target atomically. Keep the last complete generation and fail closed.
		$valid_target = is_file($file) && file_get_contents_try($file) === $s;
		xn_unlink($tmpfile);
		return $valid_target ? $written : FALSE;
	} finally {
		flock($lock, LOCK_UN);
		fclose($lock);
	}
}

// Lifecycle writes keep one exclusive visibility lock from the first state mutation through
// lifecycle completion/restore. Request readers take a shared lock while snapshotting enabled
// packages, so they observe either the old generation or the final generation, never half-install.
function plugin_state_visibility_lock_path() {
	global $conf;
	$tmp_path = is_array($conf) && isset($conf['tmp_path']) ? $conf['tmp_path'] : '';
	if(!is_string($tmp_path) || $tmp_path === '' || !is_dir($tmp_path)) return FALSE;
	return rtrim($tmp_path, '/\\').DIRECTORY_SEPARATOR.'plugin_state_visibility.lock';
}

function plugin_state_visibility_lock_acquire($operation, $timeout_ms = NULL) {
	$lockfile = plugin_state_visibility_lock_path();
	if($lockfile === FALSE) return FALSE;
	$lock = @fopen($lockfile, 'c+b');
	if(!$lock) return FALSE;
	if($timeout_ms === NULL) {
		if(flock($lock, $operation)) return $lock;
		fclose($lock);
		return FALSE;
	}
	$deadline = microtime(TRUE) + max(0, intval($timeout_ms)) / 1000;
	do {
		if(flock($lock, $operation | LOCK_NB)) return $lock;
		if(microtime(TRUE) >= $deadline) break;
		usleep(10000);
	} while(TRUE);
	fclose($lock);
	return FALSE;
}

function plugin_state_visibility_read_lock_start() {
	global $g_plugin_state_write_lock, $g_plugin_state_read_lock;
	// The lifecycle request already owns the exclusive lock and must not self-deadlock if a legacy
	// script compiles a Hook or reloads plugin metadata before returning.
	if(is_resource($g_plugin_state_write_lock)) return TRUE;
	// Nested state readers in one synchronous request share the outer resource. The nested TRUE
	// token is intentionally a no-op on release; the outer owner closes the actual handle.
	if(is_resource($g_plugin_state_read_lock)) return TRUE;
	$g_plugin_state_read_lock = plugin_state_visibility_lock_acquire(LOCK_SH);
	return is_resource($g_plugin_state_read_lock) ? $g_plugin_state_read_lock : FALSE;
}

function plugin_state_visibility_read_lock_end($lock) {
	global $g_plugin_state_read_lock;
	if($lock === TRUE) return TRUE;
	if(!is_resource($lock)) return FALSE;
	if($g_plugin_state_read_lock === $lock) $g_plugin_state_read_lock = NULL;
	$r = flock($lock, LOCK_UN);
	fclose($lock);
	return $r;
}

// Keep the default below the common 30-second Web execution limit so callers still have time to
// release the task lock and render an actionable response after a stalled reader.
function plugin_state_visibility_write_lock_start($timeout_ms = 20000) {
	global $g_plugin_state_write_lock;
	if(is_resource($g_plugin_state_write_lock)) return TRUE;
	$g_plugin_state_write_lock = plugin_state_visibility_lock_acquire(LOCK_EX, $timeout_ms);
	return is_resource($g_plugin_state_write_lock);
}

function plugin_state_visibility_write_lock_end() {
	global $g_plugin_state_write_lock;
	if(!is_resource($g_plugin_state_write_lock)) return TRUE;
	$lock = $g_plugin_state_write_lock;
	$g_plugin_state_write_lock = NULL;
	$r = flock($lock, LOCK_UN);
	fclose($lock);
	return $r;
}

// Public lifecycle helpers are also used outside the admin route by legacy callers. The admin flow
// already owns the exclusive visibility lock; a standalone caller must release its request snapshot,
// acquire the same writer boundary, and reload final state before changing conf.json.
function plugin_state_mutation_lock_start(&$owned) {
	global $g_plugin_state_write_lock;
	$owned = FALSE;
	if(is_resource($g_plugin_state_write_lock)) return TRUE;
	plugin_include_cache_reader_release_all();
	if(!plugin_state_visibility_write_lock_start()) return FALSE;
	$owned = TRUE;
	if(plugin_init() === TRUE) return TRUE;
	plugin_state_visibility_write_lock_end();
	$owned = FALSE;
	return FALSE;
}

function plugin_state_mutation_lock_end($owned) {
	return $owned ? plugin_state_visibility_write_lock_end() : TRUE;
}

// Hold the cache target's shared publish lock after _include() returns. PHP opens the returned path
// only in the caller's next operation; keeping this request lease prevents cache cleanup from
// deleting the path in that interval (and keeps later includes in the same request stable).
function plugin_include_cache_reader_hold($file) {
	global $g_plugin_include_reader_locks;
	if(isset($g_plugin_include_reader_locks[$file]) && is_resource($g_plugin_include_reader_locks[$file])) return TRUE;
	$lock = @fopen($file.'.lock', 'c+b');
	if(!$lock || !flock($lock, LOCK_SH)) {
		$lock AND fclose($lock);
		return FALSE;
	}
	clearstatcache(TRUE, $file);
	if(!is_file($file)) {
		flock($lock, LOCK_UN);
		fclose($lock);
		return FALSE;
	}
	$g_plugin_include_reader_locks[$file] = $lock;
	return TRUE;
}

function plugin_include_cache_reader_release($file) {
	global $g_plugin_include_reader_locks;
	if(!isset($g_plugin_include_reader_locks[$file]) || !is_resource($g_plugin_include_reader_locks[$file])) return TRUE;
	$lock = $g_plugin_include_reader_locks[$file];
	unset($g_plugin_include_reader_locks[$file]);
	$r = flock($lock, LOCK_UN);
	fclose($lock);
	return $r;
}

function plugin_include_cache_reader_release_paths() {
	global $g_plugin_include_reader_locks;
	foreach(array_keys($g_plugin_include_reader_locks) as $file) plugin_include_cache_reader_release($file);
	return TRUE;
}

function plugin_include_cache_reader_release_all() {
	global $g_plugin_include_state_lock;
	plugin_include_cache_reader_release_paths();
	if($g_plugin_include_state_lock !== NULL) {
		plugin_state_visibility_read_lock_end($g_plugin_include_state_lock);
		$g_plugin_include_state_lock = NULL;
	}
	return TRUE;
}

// todo: 对路径进行处理 include _include(APP_PATH.'view/htm/header.inc.htm');
$g_include_slot_kv = array();
function _include($srcfile) {
	global $conf, $g_plugin_include_state_lock;
	static $compiler_mtime;
	// The previous caller has already opened (and may currently be executing) its returned cache by
	// the time PHP evaluates the next _include(). Release only that target lease so each request keeps
	// at most one pre-open handle. The shared plugin-state snapshot lock intentionally stays held for
	// the whole request: a lifecycle writer must never switch generations between two includes in an
	// already-running request and let its request-local file index publish stale Hook bytes afterward.
	plugin_include_cache_reader_release_paths();
	$state_lock = plugin_state_visibility_read_lock_start();
	if($state_lock === FALSE) throw new RuntimeException('Failed to acquire plugin state snapshot lock.');
	$state_lock_owned = is_resource($state_lock);
	$state_lock_transferred = FALSE;
	try {
	$compiler_mtime === NULL AND $compiler_mtime = filemtime(__FILE__);
	// 合并插件，存入 tmp_path
	$len = strlen(APP_PATH);
	$cache_suffix = empty($conf['disabled_plugin']) ? '' : '.safe_mode';
	$tmpfile = $conf['tmp_path'].substr(str_replace('/', '_', $srcfile), $len).$cache_suffix;
	for($attempt = 0; $attempt < 3; $attempt++) {
		$compile_srcfile = empty($conf['disabled_plugin']) ? plugin_find_overwrite($srcfile) : $srcfile;
		$tmp_isfile = is_file($tmpfile);
		$legacy_unlocked_cache = $tmp_isfile && !is_file($tmpfile.'.lock');
		if($legacy_unlocked_cache) {
			// Caches published before the stable reader/writer lock protocol have no generation proof.
			// Mark them stale before the first compliant writer creates the sibling lock. If compilation
			// then fails, the next request must retry instead of promoting the legacy bytes to trusted.
			if(!@touch($tmpfile, 1)) throw new RuntimeException('Failed to mark legacy plugin cache stale: '.$tmpfile);
			clearstatcache(TRUE, $tmpfile);
		}
		$src_mtime = plugin_include_src_mtime($compile_srcfile);
		$src_mtime = max($src_mtime, $compiler_mtime);
		$tmp_mtime = $tmp_isfile ? filemtime($tmpfile) : 0;
		if(!$tmp_isfile || $legacy_unlocked_cache || ($src_mtime && $src_mtime > $tmp_mtime) || DEBUG > 1) {
			// This request may have leased an older generation on an earlier _include() call. Release
			// that one lease before taking the same target's exclusive publish lock.
			plugin_include_cache_reader_release($tmpfile);
			// 开始编译
			$s = plugin_compile_srcfile($srcfile);

			// 支持 <template> <slot>
			$g_include_slot_kv = array();
			for($i = 0; $i < 10; $i++) {
				$s = preg_replace_callback('#<template\sinclude="(.*?)">(.*?)</template>#is', '_include_callback_1', $s);
				if(strpos($s, '<template') === FALSE) break;
			}
			// The first compiler pass is request-private. Publishing it under the final cache path would
			// let another request execute a half-compiled template before the second pass completes.
			$compile_stage = plugin_cache_stage_write($tmpfile, $s);
			if($compile_stage === FALSE) throw new RuntimeException('Failed to write private plugin compile staging: '.$tmpfile);
			try {
				$s = plugin_compile_srcfile($compile_stage);
			} finally {
				xn_unlink($compile_stage);
			}
			if(plugin_cache_write_atomic($tmpfile, $s, $srcfile) === FALSE) throw new RuntimeException('Failed to write plugin cache: '.$tmpfile);
		}
		if(plugin_include_cache_reader_hold($tmpfile)) {
			// Nested readers receive TRUE. Never overwrite the real request resource with that no-op
			// token, or lifecycle lock upgrade would leak the shared lock and self-deadlock.
			if($state_lock_owned) $g_plugin_include_state_lock = $state_lock;
			$state_lock_transferred = TRUE;
			return $tmpfile;
		}
		clearstatcache(TRUE, $tmpfile);
	}
	throw new RuntimeException('Failed to lease plugin cache for include: '.$tmpfile);
	return $tmpfile;
	} finally {
		if($state_lock_owned && !$state_lock_transferred) plugin_state_visibility_read_lock_end($state_lock);
	}
}

function plugin_include_src_mtime($srcfile) {
	global $conf;
	if(!is_file($srcfile)) return 0;
	$mtime = filemtime($srcfile);
	if(!empty($conf['disabled_plugin'])) return $mtime;
	$s = file_get_contents($srcfile);
	if(strpos($s, 'hook') === FALSE) return $mtime;
	preg_match_all('#(?:<!--\{hook\s+(.*?)}-->|//\s*hook\s+(\S+))#is', $s, $m);
	$hooknames = array();
	foreach(array_merge($m[1], $m[2]) as $hookname) {
		$hookname = trim($hookname);
		if($hookname !== '') $hooknames[$hookname] = TRUE;
	}
	if(empty($hooknames)) return $mtime;
	$file_index = plugin_file_index();
	foreach($hooknames as $hookname=>$unused) {
		$hookkey = plugin_file_index_hook_key($hookname);
		if(isset($file_index['hook_mtimes'][$hookkey])) {
			$mtime = max($mtime, $file_index['hook_mtimes'][$hookkey]);
		}
	}
	return $mtime;
}

function _include_callback_1($m) {
	global $g_include_slot_kv;
	$r = file_get_contents($m[1]);
	preg_match_all('#<slot\sname="(.*?)">(.*?)</slot>#is', $m[2], $m2);
	if(!empty($m2[1])) {
		$kv = array_combine($m2[1], $m2[2]);
		$g_include_slot_kv += $kv;
		foreach($g_include_slot_kv as $slot=>$content) {
			$r = preg_replace('#<slot\sname="'.$slot.'"\s*/>#is', $content, $r);
		}
	}
	return $r;
}

// Legacy Kumquat-style setting schemas are captured only while the package itself is already
// running in an authorized lifecycle/settings entry. Never scan or execute arbitrary conf.php
// files from setting_get(): that would add request-time side effects and make package code trusted.
$g_plugin_setting_schema_defaults = array();
$g_plugin_setting_schema_registry = array();
$g_plugin_setting_schema_keys_by_dir = array();
$g_plugin_setting_schema_candidates_by_dir = array();
$g_plugin_setting_schema_conflict_logged = array();
$g_plugin_setting_capture_stack = array();
$g_plugin_setting_capture_serial = 0;
$g_plugin_setting_admin_request = NULL;

function plugin_setting_schema_defaults($schema) {
	$defaults = array();
	if(!is_array($schema) || empty($schema['panels']) || !is_array($schema['panels'])) return $defaults;
	foreach($schema['panels'] as $panel=>$panel_conf) {
		if(!is_array($panel_conf) || empty($panel_conf['sections']) || !is_array($panel_conf['sections'])) continue;
		foreach($panel_conf['sections'] as $section=>$section_conf) {
			if(!is_array($section_conf) || empty($section_conf['options']) || !is_array($section_conf['options'])) continue;
			foreach($section_conf['options'] as $option=>$control) {
				if(!is_array($control)) continue;
				$defaults[$panel][$section][$option] = array_key_exists('default', $control) ? $control['default'] : 0;
			}
		}
	}
	if(isset($schema['kumquat_flag']) && is_array($schema['kumquat_flag'])) $defaults['kumquat_flag'] = $schema['kumquat_flag'];
	return $defaults;
}

function plugin_setting_array_is_list($value) {
	if(!is_array($value)) return FALSE;
	$index = 0;
	foreach($value as $key=>$unused) {
		if($key !== $index++) return FALSE;
	}
	return TRUE;
}

function plugin_setting_merge_defaults($defaults, $saved) {
	if(!is_array($defaults)) return $saved;
	if(!is_array($saved)) return $saved === NULL ? $defaults : $saved;
	// Lists are option values (for example multi-select choices), not schema containers.
	// A saved list replaces its list default instead of retaining choices the administrator removed.
	// PHP cannot distinguish an empty list from an empty associative map, so an empty saved array
	// only means "all fields missing" when the schema default itself is an associative container.
	if(plugin_setting_array_is_list($defaults)) return $saved;
	if(plugin_setting_array_is_list($saved)) return empty($saved) ? $defaults : $saved;
	$result = $defaults;
	foreach($saved as $key=>$value) {
		if(array_key_exists($key, $defaults) && is_array($defaults[$key]) && is_array($value)) {
			$result[$key] = plugin_setting_merge_defaults($defaults[$key], $value);
		} else {
			$result[$key] = $value;
		}
	}
	return $result;
}

// Public package identifiers must be portable as directory names while remaining lossless through
// every Xiuno rewrite mode. Routes carry the identifier in a named query argument; legacy
// positional routes remain readable for existing 32-character underscore-only packages.
function plugin_dir_is_valid($dir) {
	return xn_plugin_dir_is_valid($dir);
}

// Resolve a package directory only after proving that its public identifier, filesystem entry and
// canonical location all describe the same local package. A package-root symlink must be rejected
// before any lower-level within(root) check: otherwise both the candidate and its comparison root
// resolve through the link and can make an external tree look self-contained.
function plugin_package_root_path($dir, &$error = '') {
	$error = '';
	if(!plugin_dir_is_valid($dir)) {
		$error = 'invalid package identifier';
		return FALSE;
	}
	$container = rtrim(str_replace('\\', '/', APP_PATH), '/').'/plugin';
	$real_container = realpath($container);
	if($real_container === FALSE || !is_dir($real_container) || !is_readable($real_container)) {
		$error = 'plugin container is unavailable or unreadable';
		return FALSE;
	}
	$candidate = $container.'/'.$dir;
	if(is_link($candidate)) {
		$error = 'package root is a symbolic link';
		return FALSE;
	}
	$real_package = plugin_realpath_within($candidate, $real_container);
	if($real_package === FALSE || !is_dir($real_package) || !is_readable($real_package)) {
		$error = 'package root is unavailable, unreadable, or outside the plugin container';
		return FALSE;
	}
	return rtrim(str_replace('\\', '/', $real_package), '/').'/';
}

function plugin_package_root_diagnostic($dir, $error) {
	static $reported = array();
	$dir = plugin_dir_is_valid($dir) ? $dir : '[invalid-identifier]';
	$error = preg_replace('~[^a-z0-9 ,._-]+~i', ' ', (string)$error);
	$key = $dir."\0".$error;
	if(isset($reported[$key])) return TRUE;
	$reported[$key] = TRUE;
	@error_log('xiuno: ignored unsafe plugin package root; dir='.rawurlencode($dir).' reason='.trim($error));
	return TRUE;
}

// Return canonical package roots once, in the same deterministic order as the historical glob.
// Non-directory files are not packages; directory-shaped entries that fail the boundary are
// ignored with a package-agnostic diagnostic and are never allowed to contribute metadata/code.
function plugin_package_roots() {
	$candidates = glob(rtrim(str_replace('\\', '/', APP_PATH), '/').'/plugin/*');
	$candidates = is_array($candidates) ? $candidates : array();
	$roots = array();
	foreach($candidates as $candidate) {
		if(!is_dir($candidate) && !is_link($candidate)) continue;
		$dir = basename(str_replace('\\', '/', $candidate));
		$error = '';
		$root = plugin_package_root_path($dir, $error);
		if($root === FALSE) {
			plugin_package_root_diagnostic($dir, $error);
			continue;
		}
		$roots[$dir] = $root;
	}
	return $roots;
}

function plugin_package_conf_path($dir, &$error = '') {
	$root = plugin_package_root_path($dir, $error);
	if($root === FALSE) return FALSE;
	$candidate = $root.'conf.json';
	if(is_link($candidate)) {
		$error = 'package conf.json is a symbolic link';
		return FALSE;
	}
	$real_conf = plugin_realpath_within($candidate, $root);
	if($real_conf === FALSE || !is_file($real_conf) || !is_readable($real_conf)) {
		$error = 'package conf.json is unavailable, unreadable, or outside the package root';
		return FALSE;
	}
	return str_replace('\\', '/', $real_conf);
}

function plugin_url($action, $dir, $extra = array()) {
	if(!is_string($action) || !preg_match('~^[a-z_]+$~D', $action) || !plugin_dir_is_valid($dir)) return url('plugin-local');
	if(!is_array($extra)) $extra = array();
	return url('plugin-'.$action, array('dir'=>$dir) + $extra);
}

function plugin_setting_dir_is_valid($dir) {
	return plugin_dir_is_valid($dir);
}

function plugin_setting_key_is_valid($key) {
	return is_string($key) && $key !== '' && $key !== plugin_setting_schema_setting_key()
		&& strlen($key) <= 128 && !preg_match('~[\x00-\x1F\x7F]~', $key);
}

// setting_get()/setting_get_raw()/successful setting_set() call this only while a compatibility
// wrapper is active. Outside that narrow context it is a no-op and changes no setting behavior.
function plugin_setting_capture_key($key, $operation = 'read') {
	global $g_plugin_setting_capture_stack;
	if(!plugin_setting_key_is_valid($key) || empty($g_plugin_setting_capture_stack)) return FALSE;
	$tokens = array_keys($g_plugin_setting_capture_stack);
	$token = end($tokens);
	$bucket = $operation === 'write' ? 'writes' : 'reads';
	$g_plugin_setting_capture_stack[$token][$bucket][$key] = TRUE;
	return TRUE;
}

function plugin_setting_capture_begin($dir) {
	global $g_plugin_setting_capture_stack, $g_plugin_setting_capture_serial;
	if(!plugin_setting_dir_is_valid($dir)) return FALSE;
	$token = ++$g_plugin_setting_capture_serial;
	$g_plugin_setting_capture_stack[$token] = array(
		'dir'=>$dir,
		'reads'=>array(),
		'writes'=>array(),
	);
	return $token;
}

function plugin_setting_capture_end($token) {
	global $g_plugin_setting_capture_stack;
	if($token === FALSE || !isset($g_plugin_setting_capture_stack[$token])) {
		return array('reads'=>array(), 'writes'=>array());
	}
	$capture = $g_plugin_setting_capture_stack[$token];
	unset($g_plugin_setting_capture_stack[$token]);
	return array(
		'reads'=>array_keys($capture['reads']),
		'writes'=>array_keys($capture['writes']),
	);
}

function plugin_setting_schema_declared_key($schema) {
	if(!is_array($schema) || !array_key_exists('setting_key', $schema)) return NULL;
	return plugin_setting_key_is_valid($schema['setting_key']) ? $schema['setting_key'] : FALSE;
}

// Prefer an explicit schema key, then a key actually read and successfully written by the wrapped
// package. A single observed read/write is also usable. Ambiguous or completely unobserved schemas
// fail closed: a directory-derived key is only a convention, not evidence of the package's real KV key.
function plugin_setting_schema_resolve_key($dir, $schema, $capture = array()) {
	if(!plugin_setting_dir_is_valid($dir)) return FALSE;
	$declared = plugin_setting_schema_declared_key($schema);
	if($declared !== NULL) return $declared;

	$reads = isset($capture['reads']) && is_array($capture['reads']) ? array_values(array_unique($capture['reads'])) : array();
	$writes = isset($capture['writes']) && is_array($capture['writes']) ? array_values(array_unique($capture['writes'])) : array();
	$reads = array_values(array_filter($reads, 'plugin_setting_key_is_valid'));
	$writes = array_values(array_filter($writes, 'plugin_setting_key_is_valid'));
	$both = array_values(array_intersect($reads, $writes));

	if(count($both) === 1) return $both[0];
	if(count($both) > 1) return FALSE;
	$observed = array_values(array_unique(array_merge($reads, $writes)));
	if(count($observed) === 1) return $observed[0];
	return FALSE;
}

function plugin_setting_json_value_supported($value) {
	if($value === NULL || is_bool($value) || is_int($value) || is_string($value)) return TRUE;
	if(is_float($value)) return is_finite($value);
	if(!is_array($value)) return FALSE;
	foreach($value as $item) {
		if(!plugin_setting_json_value_supported($item)) return FALSE;
	}
	return TRUE;
}

function plugin_setting_schema_defaults_fingerprint($defaults) {
	try {
		$json = xn_json_encode($defaults);
	} catch(Throwable $e) {
		return FALSE;
	}
	// json_encode rejects recursion and invalid UTF-8. The explicit type walk also rejects
	// resources/objects that JSON might otherwise coerce and lose during persistence.
	if(!is_string($json) || $json === '' || json_last_error() !== JSON_ERROR_NONE) return FALSE;
	if(!plugin_setting_json_value_supported($defaults)) return FALSE;
	return hash('sha256', $json);
}

function plugin_setting_schema_sidecar_kv_key() {
	// Legacy 4.5.1 development builds used a second KV row. Keep the name only as a read-only
	// migration source; new writes embed metadata in the same `setting` row as package values.
	return 'plugin_setting_schema_v1';
}

function plugin_setting_schema_setting_key() {
	return '__xn_plugin_schema_registry_v1';
}

function plugin_setting_schema_fingerprint_is_valid($fingerprint) {
	return is_string($fingerprint) && preg_match('~^[a-f0-9]{64}$~D', $fingerprint);
}

function plugin_setting_schema_registry_entry_empty() {
	return array(
		'fingerprint'=>'',
		'owners'=>array(),
		'fingerprints'=>array(),
		'conflict'=>FALSE,
	);
}

function plugin_setting_schema_registry_entry_rebuild(&$entry, $sticky_conflict = NULL) {
	$sticky_conflict === NULL AND $sticky_conflict = !empty($entry['conflict']);
	$owners = isset($entry['owners']) && is_array($entry['owners']) ? $entry['owners'] : array();
	$fingerprints = array();
	foreach($owners as $owner_dir=>$owner_fingerprints) {
		if(!plugin_setting_dir_is_valid($owner_dir) || !is_array($owner_fingerprints) || empty($owner_fingerprints)) return FALSE;
		foreach($owner_fingerprints as $fingerprint=>$enabled) {
			if(!$enabled) continue;
			if(!plugin_setting_schema_fingerprint_is_valid($fingerprint)) return FALSE;
			$fingerprints[$fingerprint] = TRUE;
		}
		if(empty(array_filter($owner_fingerprints))) return FALSE;
	}
	$fingerprint_keys = array_keys($fingerprints);
	sort($fingerprint_keys, SORT_STRING);
	$entry['owners'] = $owners;
	$entry['fingerprints'] = $fingerprints;
	$entry['fingerprint'] = empty($fingerprint_keys) ? '' : $fingerprint_keys[0];
	$entry['conflict'] = $sticky_conflict || count($fingerprint_keys) > 1;
	return TRUE;
}

function plugin_setting_schema_registry_entry_add(&$entry, $owner_dir, $fingerprint) {
	if(!plugin_setting_dir_is_valid($owner_dir) || !plugin_setting_schema_fingerprint_is_valid($fingerprint)) return FALSE;
	if(!isset($entry['owners']) || !is_array($entry['owners'])) $entry['owners'] = array();
	if(!isset($entry['owners'][$owner_dir]) || !is_array($entry['owners'][$owner_dir])) $entry['owners'][$owner_dir] = array();
	$entry['owners'][$owner_dir][$fingerprint] = TRUE;
	return plugin_setting_schema_registry_entry_rebuild($entry);
}

function plugin_setting_schema_registry_entry_replace_owner(&$entry, $owner_dir, $fingerprints) {
	if(!plugin_setting_dir_is_valid($owner_dir) || !is_array($fingerprints) || empty($fingerprints)) return FALSE;
	if(!isset($entry['owners']) || !is_array($entry['owners'])) $entry['owners'] = array();
	$other_owners = $entry['owners'];
	unset($other_owners[$owner_dir]);
	// A conflict involving another owner is sticky. A legacy/same-owner multi-fingerprint record
	// may be repaired by replacing that owner's candidate at a verified success boundary.
	$sticky_conflict = !empty($entry['conflict']) && !empty($other_owners);
	$entry['owners'][$owner_dir] = array();
	foreach($fingerprints as $fingerprint) {
		if(!plugin_setting_schema_fingerprint_is_valid($fingerprint)) return FALSE;
		$entry['owners'][$owner_dir][$fingerprint] = TRUE;
	}
	return plugin_setting_schema_registry_entry_rebuild($entry, $sticky_conflict);
}

function plugin_setting_schema_registry_entry_merge(&$target, $source) {
	if(!is_array($source)) return FALSE;
	if(!empty($source['conflict'])) $target['conflict'] = TRUE;
	$owners = isset($source['owners']) && is_array($source['owners']) ? $source['owners'] : array();
	foreach($owners as $owner_dir=>$fingerprints) {
		if(!is_array($fingerprints)) return FALSE;
		foreach($fingerprints as $fingerprint=>$enabled) {
			if(!$enabled) continue;
			if(!plugin_setting_schema_registry_entry_add($target, $owner_dir, $fingerprint)) return FALSE;
		}
	}
	return TRUE;
}

function plugin_setting_schema_sidecar_normalize($raw) {
	if($raw === NULL) return array('version'=>1, 'keys'=>array());
	if(!is_array($raw) || !isset($raw['version']) || intval($raw['version']) !== 1 || !isset($raw['keys']) || !is_array($raw['keys'])) return FALSE;
	$normalized = array('version'=>1, 'keys'=>array());
	foreach($raw['keys'] as $setting_key=>$stored_entry) {
		is_int($setting_key) AND $setting_key = (string)$setting_key;
		if(!plugin_setting_key_is_valid($setting_key) || !is_array($stored_entry)) return FALSE;
		$entry = plugin_setting_schema_registry_entry_empty();
		if(!plugin_setting_schema_registry_entry_merge($entry, $stored_entry) || empty($entry['fingerprints'])) return FALSE;
		$normalized['keys'][$setting_key] = $entry;
	}
	return $normalized;
}

function plugin_setting_schema_sidecar_read() {
	if(!function_exists('kv__get')) return FALSE;
	$setting = kv__get('setting', TRUE);
	if($setting !== NULL && !is_array($setting)) return FALSE;
	!is_array($setting) AND $setting = array();
	if(array_key_exists(plugin_setting_schema_setting_key(), $setting)) {
		return plugin_setting_schema_sidecar_normalize($setting[plugin_setting_schema_setting_key()]);
	}
	// One-way compatibility read. The next verified persistence/unbind embeds this legacy metadata
	// into `setting`; the old row can remain harmlessly for downgrade diagnostics.
	return plugin_setting_schema_sidecar_normalize(kv__get(plugin_setting_schema_sidecar_kv_key(), TRUE));
}

function plugin_setting_schema_sidecar_from_setting($setting, $legacy_sidecar = NULL) {
	if(!is_array($setting)) return FALSE;
	$raw = array_key_exists(plugin_setting_schema_setting_key(), $setting)
		? $setting[plugin_setting_schema_setting_key()]
		: $legacy_sidecar;
	return plugin_setting_schema_sidecar_normalize($raw);
}

function plugin_setting_schema_runtime_entry($setting_key, $sidecar) {
	global $g_plugin_setting_schema_candidates_by_dir;
	$entry = isset($sidecar['keys'][$setting_key]) ? $sidecar['keys'][$setting_key] : plugin_setting_schema_registry_entry_empty();
	foreach($g_plugin_setting_schema_candidates_by_dir as $owner_dir=>$keys) {
		if(empty($keys[$setting_key]['fingerprints']) || !is_array($keys[$setting_key]['fingerprints'])) continue;
		if(!plugin_setting_schema_registry_entry_replace_owner($entry, $owner_dir, array_keys($keys[$setting_key]['fingerprints']))) {
			$entry['conflict'] = TRUE;
		}
	}
	return $entry;
}

function plugin_setting_schema_write_lock_start() {
	global $conf;
	$tmp_path = is_array($conf) && isset($conf['tmp_path']) ? $conf['tmp_path'] : '';
	if(!is_string($tmp_path) || $tmp_path === '' || !is_dir($tmp_path)) return FALSE;
	$lockfile = rtrim($tmp_path, '/\\').DIRECTORY_SEPARATOR.'lock_plugin_setting_schema.lock';
	$fp = @fopen($lockfile, 'c');
	if(!$fp) return FALSE;
	if(!flock($fp, LOCK_EX)) {
		fclose($fp);
		return FALSE;
	}
	return $fp;
}

function plugin_setting_schema_write_lock_end($fp) {
	if(!is_resource($fp)) return FALSE;
	$r = flock($fp, LOCK_UN);
	fclose($fp);
	return $r;
}

function plugin_setting_schema_conflict_log_once($setting_key, $entry, $reason = '') {
	global $g_plugin_setting_schema_conflict_logged;
	if(isset($g_plugin_setting_schema_conflict_logged[$setting_key])) return;
	$g_plugin_setting_schema_conflict_logged[$setting_key] = TRUE;
	$owners = isset($entry['owners']) && is_array($entry['owners']) ? array_keys($entry['owners']) : array();
	$fingerprints = isset($entry['fingerprints']) && is_array($entry['fingerprints']) ? array_keys($entry['fingerprints']) : array();
	sort($owners, SORT_STRING);
	sort($fingerprints, SORT_STRING);
	plugin_setting_compat_log(
		'plugin setting schema conflict key='.$setting_key
		.' owners='.implode(',', $owners)
		.' fingerprints='.implode(',', $fingerprints)
		.($reason === '' ? '' : ' reason='.$reason)
	);
}

function plugin_setting_schema_key_is_conflicted($setting_key) {
	global $g_plugin_setting_schema_registry;
	return !empty($g_plugin_setting_schema_registry[$setting_key]['conflict']);
}

// A setting key is global. Different fingerprints across owners are a sticky conflict. One owner
// may legitimately produce environment-derived defaults (host, language, year), so read-only
// registration compares with its current candidate virtually and a verified success boundary
// replaces that owner's old fingerprint under plugin_setting_schema_write_lock_start().
function plugin_setting_schema_register_key($setting_key, $schema, $owner_dir) {
	global $g_plugin_setting_schema_defaults, $g_plugin_setting_schema_registry, $g_plugin_setting_schema_candidates_by_dir;
	if(!plugin_setting_key_is_valid($setting_key) || !plugin_setting_dir_is_valid($owner_dir)) return FALSE;
	$defaults = plugin_setting_schema_defaults($schema);
	if(empty($defaults)) return FALSE;
	$fingerprint = plugin_setting_schema_defaults_fingerprint($defaults);
	if($fingerprint === FALSE) return FALSE;
	if(!isset($g_plugin_setting_schema_candidates_by_dir[$owner_dir])) $g_plugin_setting_schema_candidates_by_dir[$owner_dir] = array();
	if(!isset($g_plugin_setting_schema_candidates_by_dir[$owner_dir][$setting_key])) {
		$g_plugin_setting_schema_candidates_by_dir[$owner_dir][$setting_key] = array('fingerprints'=>array());
	}
	$g_plugin_setting_schema_candidates_by_dir[$owner_dir][$setting_key]['fingerprints'][$fingerprint] = $defaults;

	$sidecar = plugin_setting_schema_sidecar_read();
	if($sidecar === FALSE) {
		$entry = plugin_setting_schema_registry_entry_empty();
		$entry['conflict'] = TRUE;
		plugin_setting_schema_registry_entry_add($entry, $owner_dir, $fingerprint);
		$g_plugin_setting_schema_registry[$setting_key] = $entry;
		unset($g_plugin_setting_schema_defaults[$setting_key]);
		plugin_setting_schema_conflict_log_once($setting_key, $entry, 'sidecar-invalid');
		return FALSE;
	}
	$entry = plugin_setting_schema_runtime_entry($setting_key, $sidecar);
	$g_plugin_setting_schema_registry[$setting_key] = $entry;
	if(!empty($entry['conflict'])) {
		unset($g_plugin_setting_schema_defaults[$setting_key]);
		plugin_setting_schema_conflict_log_once($setting_key, $entry);
		return FALSE;
	}
	$g_plugin_setting_schema_defaults[$setting_key] = $defaults;
	return TRUE;
}

function plugin_setting_schema_bind_plugin($dir, $schema, $capture = array()) {
	global $g_plugin_setting_schema_keys_by_dir;
	$key = plugin_setting_schema_resolve_key($dir, $schema, $capture);
	if($key === FALSE) return FALSE;
	if(!plugin_setting_schema_register_key($key, $schema, $dir)) {
		// Keep every conflicting owner bound to the inert key so later persistence reports failure
		// instead of appearing successful merely because registration order omitted its mapping.
		if(plugin_setting_schema_key_is_conflicted($key)) {
			if(!isset($g_plugin_setting_schema_keys_by_dir[$dir])) $g_plugin_setting_schema_keys_by_dir[$dir] = array();
			$g_plugin_setting_schema_keys_by_dir[$dir][$key] = TRUE;
		}
		return FALSE;
	}
	if(!isset($g_plugin_setting_schema_keys_by_dir[$dir])) $g_plugin_setting_schema_keys_by_dir[$dir] = array();
	$g_plugin_setting_schema_keys_by_dir[$dir][$key] = TRUE;
	return $key;
}

function plugin_setting_apply_registered_defaults($key, $value) {
	global $g_plugin_setting_schema_defaults, $g_plugin_setting_schema_registry;
	if(empty($g_plugin_setting_schema_registry[$key]) || !empty($g_plugin_setting_schema_registry[$key]['conflict'])) return $value;
	if(empty($g_plugin_setting_schema_defaults[$key])) return $value;
	return plugin_setting_merge_defaults($g_plugin_setting_schema_defaults[$key], $value);
}

function plugin_setting_schema_persist_key($key, $owner_dir) {
	if(!function_exists('setting_row_update_atomic')) return FALSE;
	global $g_plugin_setting_schema_defaults, $g_plugin_setting_schema_registry, $g_plugin_setting_schema_candidates_by_dir;
	if(!plugin_setting_key_is_valid($key) || !plugin_setting_dir_is_valid($owner_dir)) return FALSE;
	$candidate = isset($g_plugin_setting_schema_candidates_by_dir[$owner_dir][$key]) ? $g_plugin_setting_schema_candidates_by_dir[$owner_dir][$key] : NULL;
	if(empty($candidate['fingerprints']) || !is_array($candidate['fingerprints'])) return FALSE;

	$lock = plugin_setting_schema_write_lock_start();
	if($lock === FALSE) return FALSE;
	try {
		// Lock order is schema registry -> whole setting row. Both the normalized package value and
		// its ownership metadata are derived from the latest primary row and published by one
		// db_replace(), so MyISAM cannot expose a value/sidecar half-commit.
		$legacy_sidecar = plugin_setting_schema_sidecar_read();
		if($legacy_sidecar === FALSE) return FALSE;
		$operation_ok = FALSE;
		$runtime_entry = NULL;
		$defaults = NULL;
		$row_ok = setting_row_update_atomic(function($setting) use (
			$key, $owner_dir, $candidate, $legacy_sidecar,
			&$operation_ok, &$runtime_entry, &$defaults
		) {
			$sidecar = plugin_setting_schema_sidecar_from_setting($setting, $legacy_sidecar);
			if($sidecar === FALSE) return FALSE;
			$entry = isset($sidecar['keys'][$key]) ? $sidecar['keys'][$key] : plugin_setting_schema_registry_entry_empty();
			if(!plugin_setting_schema_registry_entry_replace_owner($entry, $owner_dir, array_keys($candidate['fingerprints']))) return FALSE;
			$sidecar['keys'][$key] = $entry;
			$runtime_entry = plugin_setting_schema_runtime_entry($key, $sidecar);
			$setting[plugin_setting_schema_setting_key()] = $sidecar;

			if(!empty($entry['conflict']) || !empty($runtime_entry['conflict']) || count($candidate['fingerprints']) !== 1) {
				$operation_ok = FALSE;
				return $setting;
			}

			$defaults = reset($candidate['fingerprints']);
			$raw = array_key_exists($key, $setting) ? $setting[$key] : NULL;
			$setting[$key] = plugin_setting_merge_defaults($defaults, $raw);
			$operation_ok = TRUE;
			return $setting;
		});
		if($row_ok === FALSE || !is_array($runtime_entry)) return FALSE;
		$g_plugin_setting_schema_registry[$key] = $runtime_entry;
		if(!$operation_ok) {
			unset($g_plugin_setting_schema_defaults[$key]);
			plugin_setting_schema_conflict_log_once($key, $runtime_entry);
			return FALSE;
		}
		$g_plugin_setting_schema_defaults[$key] = $defaults;
		return TRUE;
	} finally {
		plugin_setting_schema_write_lock_end($lock);
	}
}

function plugin_setting_schema_persist_plugin($dir, $only_keys = NULL) {
	global $g_plugin_setting_schema_keys_by_dir;
	if(!plugin_setting_dir_is_valid($dir)) return FALSE;
	if(empty($g_plugin_setting_schema_keys_by_dir[$dir])) return TRUE;
	if($only_keys !== NULL && !is_array($only_keys)) return FALSE;
	$filter = $only_keys === NULL ? NULL : array_fill_keys(array_values($only_keys), TRUE);
	$ok = TRUE;
	foreach($g_plugin_setting_schema_keys_by_dir[$dir] as $key=>$unused) {
		if($filter !== NULL && !isset($filter[$key])) continue;
		if(!plugin_setting_schema_persist_key($key, $dir)) $ok = FALSE;
	}
	return $ok;
}

// Successful uninstall detaches only compatibility-owned schema metadata. The setting value itself
// remains package-owned: an unstall.php that wants destructive cleanup must call setting_delete()
// explicitly. Removing an owner also recomputes conflicts, so uninstalling the conflicting package
// can make the remaining unambiguous owner usable again.
function plugin_setting_schema_unbind_plugin($dir) {
	global $g_plugin_setting_schema_defaults, $g_plugin_setting_schema_registry;
	global $g_plugin_setting_schema_keys_by_dir, $g_plugin_setting_schema_candidates_by_dir;
	if(!plugin_setting_dir_is_valid($dir)) return FALSE;
	$lock = plugin_setting_schema_write_lock_start();
	if($lock === FALSE) return FALSE;
	try {
		$legacy_sidecar = plugin_setting_schema_sidecar_read();
		if($legacy_sidecar === FALSE) return FALSE;
		$changed_keys = array();
		$final_sidecar = NULL;
		$row_ok = setting_row_update_atomic(function($setting) use ($dir, $legacy_sidecar, &$changed_keys, &$final_sidecar) {
			$sidecar = plugin_setting_schema_sidecar_from_setting($setting, $legacy_sidecar);
			if($sidecar === FALSE) return FALSE;
			foreach($sidecar['keys'] as $setting_key=>$entry) {
				if(empty($entry['owners'][$dir])) continue;
				unset($entry['owners'][$dir]);
				$changed_keys[$setting_key] = TRUE;
				if(empty($entry['owners'])) {
					unset($sidecar['keys'][$setting_key]);
					continue;
				}
				if(!plugin_setting_schema_registry_entry_rebuild($entry, FALSE)) return FALSE;
				$sidecar['keys'][$setting_key] = $entry;
			}
			$setting[plugin_setting_schema_setting_key()] = $sidecar;
			$final_sidecar = $sidecar;
			return $setting;
		});
		if($row_ok === FALSE || !is_array($final_sidecar)) return FALSE;

		unset($g_plugin_setting_schema_keys_by_dir[$dir], $g_plugin_setting_schema_candidates_by_dir[$dir]);
		foreach($changed_keys as $setting_key=>$unused) {
			unset($g_plugin_setting_schema_defaults[$setting_key]);
			if(isset($final_sidecar['keys'][$setting_key])) {
				$g_plugin_setting_schema_registry[$setting_key] = $final_sidecar['keys'][$setting_key];
			} else {
				unset($g_plugin_setting_schema_registry[$setting_key]);
			}
		}
		return TRUE;
	} finally {
		plugin_setting_schema_write_lock_end($lock);
	}
}

final class PluginSettingMessage extends Error {
	public $response_code;
	public $response_message;
	public $response_extra;

	public function __construct($code, $message, $extra = array()) {
		parent::__construct('Plugin setting message');
		$this->response_code = $code;
		$this->response_message = $message;
		$this->response_extra = is_array($extra) ? $extra : array();
	}
}

// plugin_url() deliberately keeps package identifiers in a named query argument: the generic
// Xiuno route parser splits positional path segments on `-`, which would corrupt valid hyphenated
// package names. Older setting.php pages, however, often still read param(2) or _GET(2). Expose the
// already validated canonical directory only for the duration of the package include, then restore
// the request arrays exactly so nested wrappers and long-running test processes cannot leak state.
function plugin_compat_setting_route_context_start($dir) {
	if(!plugin_setting_dir_is_valid($dir)) return FALSE;
	$context = array(
		'get_exists'=>array_key_exists(2, $_GET),
		'get_value'=>array_key_exists(2, $_GET) ? $_GET[2] : NULL,
		'request_exists'=>array_key_exists(2, $_REQUEST),
		'request_value'=>array_key_exists(2, $_REQUEST) ? $_REQUEST[2] : NULL,
	);
	$_GET[2] = $dir;
	$_REQUEST[2] = $dir;
	return $context;
}

function plugin_compat_setting_route_context_end($context) {
	if(!is_array($context)
		|| !array_key_exists('get_exists', $context)
		|| !array_key_exists('request_exists', $context)) return FALSE;
	if($context['get_exists']) {
		$_GET[2] = $context['get_value'];
	} else {
		unset($_GET[2]);
	}
	if($context['request_exists']) {
		$_REQUEST[2] = $context['request_value'];
	} else {
		unset($_REQUEST[2]);
	}
	return TRUE;
}

function plugin_setting_admin_request_start($dir) {
	global $g_plugin_setting_admin_request, $method;
	$g_plugin_setting_admin_request = array(
		'dir'=>$dir,
		'is_post'=>isset($method) && strtoupper((string)$method) === 'POST',
		'message_seen'=>FALSE,
		'message_success'=>FALSE,
	);
}

function plugin_setting_admin_request_capture_message($code, $message = NULL, $extra = array()) {
	global $g_plugin_setting_admin_request;
	if(empty($g_plugin_setting_admin_request) || !is_array($g_plugin_setting_admin_request)) return;
	$g_plugin_setting_admin_request['message_seen'] = TRUE;
	$g_plugin_setting_admin_request['message_success'] = ($code === 0 || $code === '0');
	// message() normally exits immediately. When the full response is available, defer it until the
	// compatibility-owned atomic setting commit succeeds or fails with an honest diagnostic.
	if(func_num_args() > 1) throw new PluginSettingMessage($code, $message, $extra);
}

function plugin_setting_admin_request_can_persist($dir, $capture, $normal_success = FALSE, $fatal = FALSE) {
	global $g_plugin_setting_admin_request, $g_plugin_setting_schema_keys_by_dir;
	$guard = $g_plugin_setting_admin_request;
	if($fatal || empty($guard) || !is_array($guard) || $guard['dir'] !== $dir || empty($guard['is_post'])) return FALSE;
	if(!empty($guard['message_seen'])) {
		if(empty($guard['message_success'])) return FALSE;
	} elseif(!$normal_success) {
		return FALSE;
	}
	// A POST is migratable only after the package's own setting_set() for the resolved schema key
	// succeeded. Merely rendering or validating a form is never treated as a successful save.
	$writes = isset($capture['writes']) && is_array($capture['writes']) ? $capture['writes'] : array();
	$bound = isset($g_plugin_setting_schema_keys_by_dir[$dir]) ? array_keys($g_plugin_setting_schema_keys_by_dir[$dir]) : array();
	return !empty(array_intersect($writes, $bound));
}

function plugin_setting_admin_request_clear() {
	global $g_plugin_setting_admin_request;
	$g_plugin_setting_admin_request = NULL;
}

function plugin_setting_error_is_fatal($error) {
	return is_array($error) && isset($error['type']) && in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR), TRUE);
}

function plugin_setting_compat_log($message) {
	if(function_exists('plugin_lifecycle_log')) {
		plugin_lifecycle_log($message);
	} else {
		@error_log('xiuno: '.$message);
	}
}

// 在安装、卸载插件的时候，需要先初始化。Schema 只注册到本次请求；调用方完成同类替换等
// 外层提交步骤后才持久化，失败/deferred 路径不能把半安装状态写回设置。
function plugin_compat_include_lifecycle($file, $dir = '') {
	$data = NULL;
	extract($GLOBALS, EXTR_REFS | EXTR_SKIP);
	$capture_token = plugin_setting_capture_begin($dir);
	try {
		return include _include($file);
	} finally {
		$capture = plugin_setting_capture_end($capture_token);
		if(is_array($data)) plugin_setting_schema_bind_plugin($dir, $data, $capture);
	}
}

// 后台设置文件常以 message()+exit 结束，所以同时注册 shutdown 捕获。只有管理员 POST、
// 成功消息/正常完成、且插件自己的 setting_set() 成功写入同一个 schema key 时才迁移。
// GET、受控错误、异常、fatal、直接 exit 与只渲染表单的请求一律不写设置。
function plugin_compat_include_setting($file, $dir) {
	$data = NULL;
	extract($GLOBALS, EXTR_REFS | EXTR_SKIP);
	$route_context = plugin_compat_setting_route_context_start($dir);
	$capture_token = plugin_setting_capture_begin($dir);
	plugin_setting_admin_request_start($dir);
	$finalized = FALSE;
	$include_completed = FALSE;
	$include_result = NULL;
	$persist_attempted = FALSE;
	$persist_ok = TRUE;
	$controlled_message = NULL;
	$finalize = function($shutdown = FALSE) use (
		&$data, &$finalized, &$include_completed, &$include_result,
		&$persist_attempted, &$persist_ok, $capture_token, $dir
	) {
		if($finalized) return $persist_ok;
		$finalized = TRUE;
		$error = $shutdown ? error_get_last() : NULL;
		$fatal = plugin_setting_error_is_fatal($error);
		$capture = plugin_setting_capture_end($capture_token);
		if(!$fatal && is_array($data)) plugin_setting_schema_bind_plugin($dir, $data, $capture);
		$normal_success = !$shutdown && $include_completed && $include_result !== FALSE && $include_result !== NULL;
		if(plugin_setting_admin_request_can_persist($dir, $capture, $normal_success, $fatal)) {
			$persist_attempted = TRUE;
			$persist_ok = plugin_setting_schema_persist_plugin($dir, $capture['writes']);
			if(!$persist_ok) {
				plugin_setting_compat_log('plugin setting defaults could not be persisted dir='.$dir);
			}
		}
		plugin_setting_admin_request_clear();
		return $persist_ok;
	};
	register_shutdown_function(function() use (&$finalize) { $finalize(TRUE); });
	try {
		try {
			$include_result = include _include($file);
			$include_completed = TRUE;
		} catch(PluginSettingMessage $e) {
			$controlled_message = $e;
			$include_completed = TRUE;
		}
	} finally {
		plugin_compat_setting_route_context_end($route_context);
		$finalize(FALSE);
	}
	if($persist_attempted && !$persist_ok) {
		message(-1, 'Plugin setting was saved, but compatibility metadata/default persistence failed. Check plugin_lifecycle_error and retry.');
	}
	if($controlled_message instanceof PluginSettingMessage) {
		message($controlled_message->response_code, $controlled_message->response_message, $controlled_message->response_extra);
	}
	return $include_result;
}

function plugin_compat_setting_output_has_content($output) {
	if(!is_string($output) || $output === '') return FALSE;
	if(substr($output, 0, 3) === "\xEF\xBB\xBF") $output = substr($output, 3);
	return trim($output) !== '';
}

function plugin_compat_include_setting_page($file, $dir) {
	$output = '';
	$initial_level = ob_get_level();
	$started = ob_start(function($chunk) use (&$output) {
		$output .= $chunk;
		return $chunk;
	}, 4096);
	if(!$started) {
		return array('result'=>plugin_compat_include_setting($file, $dir), 'has_output'=>TRUE);
	}
	try {
		$result = plugin_compat_include_setting($file, $dir);
	} finally {
		while(ob_get_level() > $initial_level) ob_end_flush();
	}
	return array(
		'result'=>$result,
		'has_output'=>plugin_compat_setting_output_has_content($output),
	);
}

function plugin_compat_form_action_is_local($action, $base_href = NULL) {
	return xn_html_form_action_is_local($action, $base_href);
}

function plugin_compat_html_tag_boundary($html, $start, $name) {
	return xn_html_tag_boundary($html, $start, $name);
}

// Return the exclusive end offset of one HTML tag. A plain `[^>]*` pattern is unsafe here because
// `>` is valid inside quoted attribute values and was previously rendered back to administrators.
function plugin_compat_html_tag_end($html, $start) {
	return xn_html_tag_end($html, $start);
}

function plugin_compat_html_tag_attribute($tag, $wanted, &$found = NULL) {
	return xn_html_tag_attribute($tag, $wanted, $found);
}

function plugin_compat_html_base_href($html, &$found = NULL) {
	return xn_html_base_href($html, $found);
}

function plugin_compat_html_remove_token_inputs($body) {
	$result = '';
	$cursor = 0;
	foreach(xn_html_scan_tags($body, 'input') as $token) {
		if(!empty($token['closing'])) continue;
		$start = $token['start'];
		$end = $token['end'];
		$tag = $token['tag'];
		$name = plugin_compat_html_tag_attribute($tag, 'name', $found);
		$name = $found ? xn_html_attribute_value_decode($name) : NULL;
		if($found && $name === '_token') {
			$result .= substr($body, $cursor, $start - $cursor);
			$cursor = $end;
		}
	}
	return $result.substr($body, $cursor);
}

function plugin_compat_inject_csrf_forms($message) {
	if(!is_string($message) || stripos($message, '<form') === FALSE) return $message;
	if(!function_exists('csrf_token')) return $message;
	$token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
	$result = '';
	$cursor = 0;
	$changed = FALSE;
	$base_href = plugin_compat_html_base_href($message, $base_found);
	$forms = xn_html_scan_tags($message, 'form');
	$form_open = FALSE;
	foreach($forms as $form_token) {
		if(empty($form_token['closing'])) {
			if($form_open) return $message;
			$form_open = TRUE;
		} else {
			if(!$form_open) return $message;
			$form_open = FALSE;
		}
	}
	if($form_open) return $message;
	$form_count = count($forms);
	for($i = 0; $i < $form_count; $i++) {
		if(!empty($forms[$i]['closing'])) continue;
		$start = $forms[$i]['start'];
		$open_end = $forms[$i]['end'];
		$open_tag = $forms[$i]['tag'];
		$method = plugin_compat_html_tag_attribute($open_tag, 'method', $method_found);
		$method = $method_found ? trim(xn_html_attribute_value_decode($method)) : NULL;
		if(!$method_found || strcasecmp($method, 'post') !== 0) continue;

		$close_index = FALSE;
		for($j = $i + 1; $j < $form_count; $j++) {
			if(!empty($forms[$j]['closing'])) {
				$close_index = $j;
				break;
			}
		}
		if($close_index === FALSE) break;
		$close_start = $forms[$close_index]['start'];
		$close_end = $forms[$close_index]['end'];

		$action = plugin_compat_html_tag_attribute($open_tag, 'action', $action_found);
		if($action_found) {
			$action = trim(xn_html_attribute_value_decode($action));
			if(!plugin_compat_form_action_is_local($action, $base_found ? $base_href : NULL)) continue;
		}

		$body = substr($message, $open_end, $close_start - $open_end);
		$result .= substr($message, $cursor, $start - $cursor)
			.$open_tag.'<input type="hidden" name="_token" value="'.$token.'">'
			.plugin_compat_html_remove_token_inputs($body)
			.substr($message, $close_start, $close_end - $close_start);
		$cursor = $close_end;
		$i = $close_index;
		$changed = TRUE;
	}
	return $changed ? $result.substr($message, $cursor) : $message;
}

// Plugin lifecycle state is persisted in the package's conf.json. Standard immutable deployments
// intentionally make that storage read-only; detect the exact state target without reviving the old
// assumption that unrelated core model/view/admin directories must all be writable.
function plugin_state_storage_writable($dir) {
	$error = '';
	$file = plugin_package_conf_path($dir, $error);
	if($file === FALSE) return FALSE;
	return is_file($file) && is_readable($file) && is_writable($file) && is_writable(dirname($file));
}

// Replacement is an explicit local-package contract. Keep the identifier deliberately small and
// portable so case folding, whitespace, Unicode normalization, or path syntax can never create an
// accidental match between two independently authored packages.
function plugin_exclusive_group_normalize($value) {
	if(!is_string($value) || !preg_match('~^[a-z0-9][a-z0-9._-]{0,63}$~D', $value)) return '';
	return $value;
}

function plugin_init() {
	global $plugin_srcfiles, $plugin_paths, $plugins, $official_plugins;
	$state_lock = plugin_state_visibility_read_lock_start();
	if($state_lock === FALSE) return FALSE;
	try {
	$plugin_srcfiles = array();
	$plugin_paths = array();
	$plugins = array();
	$official_plugins = array();
	plugin_file_index_reset();
	/*$plugin_srcfiles = array_merge(
		glob(APP_PATH.'model/*.php'), 
		glob(APP_PATH.'route/*.php'), 
		glob(APP_PATH.'view/htm/*.*'), 
		glob(ADMIN_PATH.'route/*.php'), 
		glob(ADMIN_PATH.'view/htm/*.*'),
		glob(APP_PATH.'lang/en-us/*.*'),
		glob(APP_PATH.'lang/zh-cn/*.*'),
		glob(APP_PATH.'lang/zh-tw/*.*'),
		array(APP_PATH.'model.inc.php')
	);
	foreach($plugin_srcfiles as $k=>$file) {
		$filename = file_name($file);
		if(is_backfile($filename)) {
			unset($plugin_srcfiles[$k]);
		}
	}*/
	
	$official_plugins = plugin_official_list_cache();
	empty($official_plugins) AND $official_plugins = array();
	
	$package_roots = plugin_package_roots();
	// Preserve the historical numeric global for legacy Hook code while discovery itself uses the
	// identifier-keyed canonical map.
	$plugin_paths = array_values(array_map(function($path) { return rtrim($path, '/'); }, $package_roots));
	if(!empty($package_roots)) {
		foreach($package_roots as $dir=>$path) {
			$conf_error = '';
			$conffile = plugin_package_conf_path($dir, $conf_error);
			if($conffile === FALSE) {
				plugin_package_root_diagnostic($dir, $conf_error);
				continue;
			}
			$conf_bytes = file_get_contents($conffile);
			if($conf_bytes === FALSE) {
				plugin_package_root_diagnostic($dir, 'package conf.json could not be read');
				continue;
			}
			$arr = xn_json_decode($conf_bytes);
			if(empty($arr)) continue;
			$plugins[$dir] = $arr;
			
			// 额外的信息
			$plugins[$dir]['hooks'] = array();
			$hook_root = $path.'hook/';
			$hookpaths = is_dir($hook_root) && !is_link(rtrim($hook_root, '/')) ? glob($hook_root.'*.*') : array(); // path
			if(is_array($hookpaths)) {
				foreach($hookpaths as $hookpath) {
					if(is_link($hookpath) || !is_file($hookpath) || plugin_realpath_within($hookpath, $hook_root) === FALSE) continue;
					$hookname = file_name($hookpath);
					$plugins[$dir]['hooks'][$hookname] = $hookpath;
				}
			}
			
			// 本地 + 线上数据
			$plugins[$dir] = plugin_read_by_dir($dir);
		}
	}
		return TRUE;
	} finally {
		plugin_state_visibility_read_lock_end($state_lock);
	}
}

// 插件依赖检测，返回依赖的插件列表，如果返回为空则表示不依赖
/*
	返回依赖的插件数组：
	array(
		'xn_ad'=>'1.0',
		'xn_umeditor'=>'1.0',
	);
*/
function plugin_dependencies($dir) {
	$details = plugin_dependency_details($dir);
	
	// 检查插件依赖关系
	$arr = array();
	foreach($details as $_dir=>$detail) {
		if($detail['status'] != 'ok') {
			$arr[$_dir] = $detail;
		}
	}
	return $arr;
}

/*
	返回被依赖的插件数组：
	array(
		'xn_ad'=>'1.0',
		'xn_umeditor'=>'1.0',
	);
*/
function plugin_dependency_details($dir) {
	global $plugins;
	if(!isset($plugins[$dir])) return array();

	$plugin = $plugins[$dir];
	if(!isset($plugin['dependencies']) || !is_array($plugin['dependencies'])) return array();

	$arr = array();
	foreach($plugin['dependencies'] as $_dir=>$version) {
		$detail = array(
			'dir'=>$_dir,
			'name'=>$_dir,
			'required_version'=>(string)$version,
			'current_version'=>'',
			'status'=>'ok',
			'cycle_path'=>array(),
		);

		if(!plugin_dependency_dir_valid($_dir)) {
			$detail['status'] = 'invalid_dir';
		} elseif(!isset($plugins[$_dir])) {
			$detail['status'] = 'not_downloaded';
		} else {
			$dep = $plugins[$_dir];
			$detail['name'] = isset($dep['name']) && $dep['name'] !== '' ? $dep['name'] : $_dir;
			$detail['current_version'] = isset($dep['version']) ? (string)$dep['version'] : '';
			if(!empty($dep['metadata_error']) || (isset($dep['dependencies']) && !is_array($dep['dependencies']))) {
				$detail['status'] = 'metadata_error';
			} elseif(empty($dep['installed'])) {
				$detail['status'] = 'downloaded_not_installed';
			} elseif(empty($dep['enable'])) {
				$detail['status'] = 'installed_disabled';
			} elseif($version !== '' && $detail['current_version'] !== '' && version_compare($detail['current_version'], (string)$version) < 0) {
				$detail['status'] = 'version_low';
			} else {
				$cycle_path = plugin_dependency_cycle_path($_dir, $dir);
				if(!empty($cycle_path)) {
					$detail['status'] = 'cycle';
					$detail['cycle_path'] = $cycle_path;
				}
			}
		}
		$arr[$_dir] = $detail;
	}
	return $arr;
}

function plugin_dependency_dir_valid($dir) {
	return is_string($dir) && plugin_dir_is_valid($dir);
}

function plugin_dependency_cycle_path($current, $target, $visited = array()) {
	global $plugins;
	if(!isset($plugins[$current])) return array();
	if(isset($visited[$current])) return array();
	$visited[$current] = TRUE;

	$dependencies = isset($plugins[$current]['dependencies']) && is_array($plugins[$current]['dependencies']) ? $plugins[$current]['dependencies'] : array();
	foreach($dependencies as $next=>$version) {
		if($next == $target) return array($current, $target);
		$path = plugin_dependency_cycle_path($next, $target, $visited);
		if(!empty($path)) {
			array_unshift($path, $current);
			return $path;
		}
	}
	return array();
}

function plugin_dependency_status_text($detail) {
	if(!is_array($detail)) return '';
	$status = isset($detail['status']) ? $detail['status'] : '';
	$required = isset($detail['required_version']) ? $detail['required_version'] : '';
	$current = isset($detail['current_version']) ? $detail['current_version'] : '';
	$map = array(
		'not_downloaded'=>'not downloaded',
		'downloaded_not_installed'=>'downloaded, not installed',
		'installed_disabled'=>'installed, disabled',
		'version_low'=>'version too low' . ($required !== '' ? " ({$current} < {$required})" : ''),
		'metadata_error'=>'metadata error',
		'cycle'=>'dependency cycle',
		'invalid_dir'=>'invalid dependency name',
	);
	return isset($map[$status]) ? $map[$status] : $status;
}

function plugin_by_dependencies($dir) {
	global $plugins;
	
	$arr = array();
	foreach($plugins as $_dir=>$plugin) {
		if(isset($plugin['dependencies']) && is_array($plugin['dependencies']) && isset($plugin['dependencies'][$dir]) && $plugin['enable']) {
			$arr[$_dir] = $plugin['version'];
		}
	}
	return $arr;
}

function plugin_enable($dir) {
	global $plugins;
	$state_lock_owned = FALSE;
	if(!plugin_state_mutation_lock_start($state_lock_owned)) return FALSE;
	try {
		if(!isset($plugins[$dir])) return FALSE;
		$conf_error = '';
		$conffile = plugin_package_conf_path($dir, $conf_error);
		if($conffile === FALSE) return FALSE;
		$old = plugin_state_snapshot($dir);
		$plugins[$dir]['enable'] = 1;

		//plugin_overwrite($dir, 'install');
		//plugin_hook($dir, 'install');

		$r = file_replace_var($conffile, array('enable'=>1), TRUE);
		if($r === FALSE) {
			plugin_state_restore($dir, $old);
			return FALSE;
		}
		if(!plugin_clear_tmp_dir()) {
			plugin_state_restore($dir, $old);
			return FALSE;
		}
		return TRUE;
	} finally {
		plugin_state_mutation_lock_end($state_lock_owned);
	}
}

// Clear only regenerable runtime caches. Every admin, lifecycle, update and CLI path must use this
// maintenance boundary instead of deleting tmp wholesale or unlinking cache files behind readers.
function runtime_cache_clear_regenerable() {
	global $conf;
	plugin_file_index_reset();
	// A lifecycle request may already have included core/plugin caches before changing state. Drop
	// only this request's leases so it can rebuild; leases held by other requests remain authoritative.
	plugin_include_cache_reader_release_all();
	$dir = rtrim(str_replace('\\', '/', $conf['tmp_path']), '/').'/';
	if(!is_dir($dir)) return FALSE;
	$ok = TRUE;
	$items = glob($dir.'*');
	$items = is_array($items) ? $items : array();
	$dotitems = glob($dir.'.*');
	if(is_array($dotitems)) {
		foreach($dotitems as $item) {
			$name = basename($item);
			if($name == '.' || $name == '..') continue;
			$items[] = $item;
		}
	}
	foreach($items as $item) {
		$name = basename($item);
		if(plugin_tmp_entry_protected($name, $item)) continue;
		// tmp is also used by image helpers, archive extraction, local diagnostics and third-party
		// code. Unknown files, directories and symlinks have no proven cache ownership and must stay.
		if(is_dir($item) || is_link($item)) continue;

		$stage_target = '';
		if(plugin_runtime_cache_staging_target($name, $item, $stage_target)) {
			if(!xn_unlink($item)) $ok = FALSE;
			continue;
		}
		if(!plugin_runtime_cache_target_is_known($name, $item)) continue;

		$publish_lock_path = $item.'.lock';
		$publish_lock = @fopen($publish_lock_path, 'c+b');
		$exclusive = $publish_lock ? flock($publish_lock, LOCK_EX | LOCK_NB) : FALSE;
		if($exclusive) {
			if(is_file($item) && !xn_unlink($item)) $ok = FALSE;
			flock($publish_lock, LOCK_UN);
		} elseif($publish_lock) {
			// A reader already received this path. Keep its bytes, but force the next request to
			// recompile after the reader releases its shared lease.
			if(is_file($item) && !@touch($item, 1)) $ok = FALSE;
		} else {
			$ok = FALSE;
		}
		$publish_lock AND fclose($publish_lock);
	}
	return $ok;
}

// Legacy lifecycle name retained for core callers and third-party Hook compatibility.
function plugin_clear_tmp_dir() {
	return runtime_cache_clear_regenerable();
}

// 受保护的 tmp 条目：并发锁、尚未发布的原子写 staging、插件包快照、在线更新备份和部署标记文件不属于可立即删除的缓存。
function plugin_tmp_entry_protected($name, $path = '') {
	global $time;
	if($name === '.gitkeep' || $name === '.htaccess' || $name === '.user.ini') return TRUE;
	if($name === 'safe_mode' || $name === 'safe_mode.php') return TRUE;
	if(preg_match('~^lock_[A-Za-z0-9_-]{1,64}\.lock$~', $name)) return TRUE;
	// plugin_cache_write_atomic() intentionally keeps one stable lock inode per cache target.
	// Unlinking even an apparently idle lock can split current holders/waiters from a newly opened
	// inode on Unix, so cache cleanup must leave every lock path in place.
	if(substr($name, -5) === '.lock') return TRUE;
	if(preg_match('~^.+\.[0-9]+\.[0-9a-f]{13,23}\.tmp$~Di', $name)) {
		// A writer creates this file before acquiring the publish lock. Keep fresh staging across a
		// concurrent clear; only crash leftovers older than one day are safe to collect here.
		$mtime = $path !== '' ? @filemtime($path) : 0;
		$now = !empty($time) ? $time : time();
		return $mtime === FALSE || $mtime === 0 || ($now - $mtime) <= 86400;
	}
	// Backups and update staging have their own owner-specific cleanup. Generic cache maintenance
	// cannot prove that an old-looking entry is abandoned, so it never removes these paths.
	if(strpos($name, 'update_backup_') === 0) return TRUE;
	if($name === 'update_extract' || preg_match('~^update_.+\.zip$~D', $name)) return TRUE;
	if(strpos($name, 'plugin_backup_') === 0) return TRUE;
	return FALSE;
}

// A published runtime cache is core-owned only when it is one of the two explicit aggregate model
// caches, or when plugin_cache_write_atomic() has established the stable sibling lock used by every
// current _include() target. The extension constraint excludes unrelated lock-based tmp protocols.
function plugin_runtime_cache_target_is_known($name, $path = '') {
	if($name === 'model.min.php' || $name === 'model.safe.min.php') return TRUE;
	if(!preg_match('~\.(?:php|htm)(?:\.safe_mode)?$~D', $name)) return FALSE;
	return $path !== '' && is_file($path.'.lock');
}

// Recognize only abandoned staging produced by plugin_cache_write_atomic() for a known cache
// target. Fresh writers are protected above; malformed or ownerless *.tmp files remain untouched.
function plugin_runtime_cache_staging_target($name, $path, &$target_path = NULL) {
	global $time;
	$target_path = '';
	if(!preg_match('~^(.+\.(?:php|htm)(?:\.safe_mode)?)\.[0-9]+\.[0-9a-f]{13,23}\.tmp$~Di', $name, $match)) return FALSE;
	$mtime = @filemtime($path);
	$now = !empty($time) ? $time : time();
	if($mtime === FALSE || $mtime === 0 || ($now - $mtime) <= 86400) return FALSE;
	$target_path = dirname($path).DIRECTORY_SEPARATOR.$match[1];
	return plugin_runtime_cache_target_is_known($match[1], $target_path);
}

function plugin_disable($dir) {
	global $plugins;
	$state_lock_owned = FALSE;
	if(!plugin_state_mutation_lock_start($state_lock_owned)) return FALSE;
	try {
		if(!isset($plugins[$dir])) return FALSE;
		$conf_error = '';
		$conffile = plugin_package_conf_path($dir, $conf_error);
		if($conffile === FALSE) return FALSE;
		$old = plugin_state_snapshot($dir);
		$plugins[$dir]['enable'] = 0;

		//plugin_overwrite($dir, 'unstall');
		//plugin_hook($dir, 'unstall');

		$r = file_replace_var($conffile, array('enable'=>0), TRUE);
		if($r === FALSE) {
			plugin_state_restore($dir, $old);
			return FALSE;
		}
		if(!plugin_clear_tmp_dir()) {
			plugin_state_restore($dir, $old);
			return FALSE;
		}
		return TRUE;
	} finally {
		plugin_state_mutation_lock_end($state_lock_owned);
	}
}

// 安装所有的本地插件
function plugin_install_all() {
	global $plugins;
	
	// 检查文件更新
	foreach ($plugins as $dir=>$plugin) {
		if(!plugin_install($dir)) return FALSE;
	}
	return TRUE;
}

// 卸载所有的本地插件
function plugin_unstall_all() {
	global $plugins;
	
	// 检查文件更新
	foreach ($plugins as $dir=>$plugin) {
		if(!plugin_unstall($dir)) return FALSE;
	}
	return TRUE;
}
/*
	插件安装：
		把所有的插件点合并，重新写入文件。如果没有备份文件，则备份一份。
		插件名可以为源文件名：view/header.htm
*/
function plugin_install($dir) {
	global $plugins, $conf;
	$state_lock_owned = FALSE;
	if(!plugin_state_mutation_lock_start($state_lock_owned)) return FALSE;
	try {
		if(!isset($plugins[$dir])) return FALSE;
		$conf_error = '';
		$conffile = plugin_package_conf_path($dir, $conf_error);
		if($conffile === FALSE) return FALSE;
		$old = plugin_state_snapshot($dir);
		$plugins[$dir]['installed'] = 1;
		$plugins[$dir]['enable'] = 1;

		// 1. 直接覆盖的方式
		//plugin_overwrite($dir, 'install');
		// 2. 钩子的方式
		//plugin_hook($dir, 'install');

		$r = file_replace_var($conffile, array('installed'=>1, 'enable'=>1), TRUE);
		if($r === FALSE) {
			plugin_state_restore($dir, $old);
			return FALSE;
		}
		if(!plugin_clear_tmp_dir()) {
			plugin_state_restore($dir, $old);
			return FALSE;
		}
		return TRUE;
	} finally {
		plugin_state_mutation_lock_end($state_lock_owned);
	}
}

// copy from plugin_install 修改
function plugin_unstall($dir) {
	global $plugins;
	$state_lock_owned = FALSE;
	if(!plugin_state_mutation_lock_start($state_lock_owned)) return FALSE;
	try {
		if(!isset($plugins[$dir])) return TRUE;
		$conf_error = '';
		$conffile = plugin_package_conf_path($dir, $conf_error);
		if($conffile === FALSE) return FALSE;
		$old = plugin_state_snapshot($dir);
		$plugins[$dir]['installed'] = 0;
		$plugins[$dir]['enable'] = 0;

		// 1. 直接覆盖的方式
		//plugin_overwrite($dir, 'unstall');
		// 2. 钩子的方式
		//plugin_hook($dir, 'unstall');

		$r = file_replace_var($conffile, array('installed'=>0, 'enable'=>0), TRUE);
		if($r === FALSE) {
			plugin_state_restore($dir, $old);
			return FALSE;
		}
		if(!plugin_clear_tmp_dir()) {
			plugin_state_restore($dir, $old);
			return FALSE;
		}
		return TRUE;
	} finally {
		plugin_state_mutation_lock_end($state_lock_owned);
	}
}

function plugin_state_snapshot($dir) {
	global $plugins;
	return isset($plugins[$dir]) ? $plugins[$dir] : NULL;
}

function plugin_state_restore($dir, $snapshot) {
	global $plugins;
	if($snapshot === NULL) return FALSE;
	$state_lock_owned = FALSE;
	if(!plugin_state_mutation_lock_start($state_lock_owned)) return FALSE;
	try {
		$conf_error = '';
		$conffile = plugin_package_conf_path($dir, $conf_error);
		if($conffile === FALSE) return FALSE;
		$plugins[$dir] = $snapshot;
		$replace = array();
		foreach(array('installed', 'enable') as $key) {
			if(isset($snapshot[$key])) $replace[$key] = empty($snapshot[$key]) ? 0 : 1;
		}
		if(!empty($replace)) {
			$r = file_replace_var($conffile, $replace, TRUE);
			if($r === FALSE) return FALSE;
		}
		return plugin_clear_tmp_dir();
	} finally {
		plugin_state_mutation_lock_end($state_lock_owned);
	}
}

function plugin_php_syntax_capability_error($dir, $detail) {
	return array(array(
		'file'=>'plugin/'.$dir.'/',
		'detail'=>$detail,
	));
}

function plugin_realpath_within($path, $directory) {
	$real_path = realpath($path);
	$real_directory = realpath($directory);
	if($real_path === FALSE || $real_directory === FALSE) return FALSE;
	$real_path = str_replace('\\', '/', $real_path);
	$real_directory = rtrim(str_replace('\\', '/', $real_directory), '/');
	$compare_path = $real_path;
	$compare_directory = $real_directory;
	if(DIRECTORY_SEPARATOR === '\\') {
		$compare_path = strtolower($compare_path);
		$compare_directory = strtolower($compare_directory);
	}
	if($compare_path !== $compare_directory && strpos($compare_path, $compare_directory.'/') !== 0) return FALSE;
	return $real_path;
}

function plugin_php_syntax_errors($dir, $php_binary = NULL) {
	$errors = array();
	$root_error = '';
	$root = plugin_package_root_path($dir, $root_error);
	if($root === FALSE) {
		return plugin_php_syntax_capability_error(
			$dir,
			'PHP package scan failed closed: '.$root_error.'. The plugin operation was blocked before lifecycle code ran.'
		);
	}
	$scan_error = '';
	$files = plugin_php_files($root, $scan_error, $root);
	if($scan_error !== '') {
		return plugin_php_syntax_capability_error(
			$dir,
			'PHP package scan failed closed: '.$scan_error.' The plugin operation was blocked before lifecycle code ran.'
		);
	}
	$lint_files = array();
	foreach($files as $file) {
		$path = str_replace('\\', '/', $file);
		if(strpos($path, "/plugin/$dir/hook/") !== FALSE) continue;
		$lint_files[] = array('file'=>$file, 'path'=>$path);
	}
	if(empty($lint_files)) return $errors;

	// Package lint is a fail-closed installation boundary. Silently skipping it when a shared host
	// disables process execution lets standalone lifecycle/model files reach production unchecked.
	if(!function_exists('exec')) {
		return plugin_php_syntax_capability_error(
			$dir,
			'PHP CLI syntax preflight is unavailable because exec() is disabled. Configure this PHP runtime to permit a trusted PHP CLI lint subprocess, or run installation in an environment that can execute php -l; the plugin operation was blocked before lifecycle code ran.'
		);
	}
	$php = $php_binary === NULL
		? ((PHP_SAPI === 'cli' || PHP_SAPI === 'cli-server') ? PHP_BINARY : PHP_BINDIR.DIRECTORY_SEPARATOR.(DIRECTORY_SEPARATOR === '\\' ? 'php.exe' : 'php'))
		: (string)$php_binary;
	if($php === '' || !is_file($php) || !is_executable($php)) {
		return plugin_php_syntax_capability_error(
			$dir,
			'PHP CLI syntax preflight is unavailable because the configured binary was not found or is not executable: '.$php.'. Install the matching PHP CLI binary or correct its path/permissions; the plugin operation was blocked before lifecycle code ran.'
		);
	}

	// Do not mistake php-fpm/php-cgi (or an unrelated executable) for the CLI. The historical FPM
	// failure printed usage text for every package and was reported as if plugin source were invalid.
	$probe_out = array();
	$probe_code = 0;
	// Avoid quoted literals here: escapeshellarg() has intentionally different Windows quoting
	// semantics. chr() keeps this probe identical under cmd.exe and POSIX shells.
	$probe_script = 'exit(PHP_SAPI === chr(99).chr(108).chr(105) ? 0 : 86);';
	exec(escapeshellarg($php).' -r '.escapeshellarg($probe_script).' 2>&1', $probe_out, $probe_code);
	if($probe_code !== 0) {
		$probe_detail = trim(implode("\n", array_slice($probe_out, 0, 8)));
		return plugin_php_syntax_capability_error(
			$dir,
			'PHP CLI syntax preflight is unavailable because the configured binary is not a usable CLI SAPI: '.$php.'.'.($probe_detail === '' ? '' : ' Detail: '.$probe_detail).' Install the matching PHP CLI binary or correct its path; the plugin operation was blocked before lifecycle code ran.'
		);
	}

	foreach($lint_files as $lint_file) {
		$file = $lint_file['file'];
		$path = $lint_file['path'];
		$out = array();
		$code = 0;
		exec(escapeshellarg($php).' -l '.escapeshellarg($file).' 2>&1', $out, $code);
		if($code !== 0) {
			$errors[] = array(
				'file'=>str_replace(APP_PATH, '', $path),
				'detail'=>implode("\n", $out),
			);
			continue;
		}
		$compat_error = plugin_php8_removed_function_error($file);
		if($compat_error !== '') {
			$errors[] = array(
				'file'=>str_replace(APP_PATH, '', $path),
				'detail'=>$compat_error,
			);
		}
	}
	return $errors;
}

function plugin_php_function_import_alias($tokens) {
	$as = FALSE;
	foreach($tokens as $token) {
		if(is_array($token) && in_array($token[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), TRUE)) continue;
		if(is_array($token) && $token[0] === T_AS) {
			$as = TRUE;
			continue;
		}
		if($as && is_array($token) && $token[0] === T_STRING) return strtolower($token[1]);
	}
	for($i = count($tokens) - 1; $i >= 0; $i--) {
		$token = $tokens[$i];
		if(!is_array($token)) continue;
		if(!in_array($token[0], array(T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE), TRUE)) continue;
		$name = trim($token[1], '\\');
		$parts = explode('\\', $name);
		return strtolower(end($parts));
	}
	return '';
}

function plugin_php_function_import_names($tokens, $offset) {
	$count = count($tokens);
	$function_index = $offset + 1;
	while($function_index < $count) {
		$token = $tokens[$function_index];
		if(!is_array($token) || !in_array($token[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), TRUE)) break;
		$function_index++;
	}
	if($function_index >= $count || !is_array($tokens[$function_index]) || $tokens[$function_index][0] !== T_FUNCTION) return array();

	$names = array();
	$item = array();
	$grouped = FALSE;
	for($i = $function_index + 1; $i < $count; $i++) {
		$token = $tokens[$i];
		if($token === '{' && !$grouped) {
			// The prefix in `use function Vendor\{count, each}` does not affect either local alias.
			$item = array();
			$grouped = TRUE;
			continue;
		}
		if($token === ',' || $token === ';' || ($grouped && $token === '}')) {
			$name = plugin_php_function_import_alias($item);
			if($name !== '') $names[$name] = TRUE;
			$item = array();
			if($token === ';' || $token === '}') break;
			continue;
		}
		$item[] = $token;
	}
	return $names;
}

function plugin_php_function_symbol_context($tokens) {
	$namespaces = array();
	$scopes = array();
	$direct_class_body = array();
	$current_namespace = '';
	$current_scope = 0;
	$brace_depth = 0;
	$class_pending = FALSE;
	$class_body_depths = array();
	$namespace_pending = NULL;
	$namespace_blocks = array();
	$class_ids = array(T_CLASS, T_INTERFACE, T_TRAIT);
	defined('T_ENUM') AND $class_ids[] = constant('T_ENUM');

	foreach($tokens as $i=>$token) {
		$namespaces[$i] = $current_namespace;
		$scopes[$i] = $current_scope;
		$direct_class_body[$i] = !empty($class_body_depths) && $brace_depth === end($class_body_depths);

		if($namespace_pending !== NULL) {
			if(is_array($token) && in_array($token[0], array(T_STRING, T_NAME_QUALIFIED, T_NS_SEPARATOR), TRUE)) {
				$namespace_pending['name'] .= $token[1];
				continue;
			}
			if($token === '\\') {
				$namespace_pending['name'] .= '\\';
				continue;
			}
			if($token === ';') {
				$current_namespace = strtolower(trim($namespace_pending['name'], '\\'));
				$current_scope = $namespace_pending['scope'];
				$namespace_pending = NULL;
				continue;
			}
			if($token === '{') {
				$namespace_blocks[] = array(
					'depth'=>$brace_depth + 1,
					'namespace'=>$current_namespace,
					'scope'=>$current_scope,
				);
				$brace_depth++;
				$current_namespace = strtolower(trim($namespace_pending['name'], '\\'));
				$current_scope = $namespace_pending['scope'];
				$namespace_pending = NULL;
				continue;
			}
		}

		if(is_array($token)) {
			if($token[0] === T_NAMESPACE) {
				$namespace_pending = array('name'=>'', 'scope'=>$i + 1);
			} elseif(in_array($token[0], $class_ids, TRUE)) {
				$class_pending = TRUE;
			}
			continue;
		}

		if($token === '{') {
			$brace_depth++;
			if($class_pending) {
				$class_body_depths[] = $brace_depth;
				$class_pending = FALSE;
			}
		} elseif($token === '}') {
			if(!empty($class_body_depths) && end($class_body_depths) === $brace_depth) array_pop($class_body_depths);
			if(!empty($namespace_blocks) && $namespace_blocks[count($namespace_blocks) - 1]['depth'] === $brace_depth) {
				$previous = array_pop($namespace_blocks);
				$current_namespace = $previous['namespace'];
				$current_scope = $previous['scope'];
			}
			$brace_depth = max(0, $brace_depth - 1);
		}
	}

	$declared = array();
	$imported = array();
	$count = count($tokens);
	for($i = 0; $i < $count; $i++) {
		$token = $tokens[$i];
		if(!is_array($token)) continue;
		if($token[0] === T_USE) {
			$names = plugin_php_function_import_names($tokens, $i);
			if(!empty($names)) {
				$scope = isset($scopes[$i]) ? $scopes[$i] : 0;
				isset($imported[$scope]) || $imported[$scope] = array();
				$imported[$scope] += $names;
			}
			continue;
		}
		if($token[0] !== T_FUNCTION || !empty($direct_class_body[$i])) continue;
		for($j = $i + 1; $j < $count; $j++) {
			$next = $tokens[$j];
			if(is_array($next) && in_array($next[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), TRUE)) continue;
			if($next === '&' || (is_array($next) && $next[1] === '&')) continue;
			if(is_array($next) && $next[0] === T_STRING) {
				$namespace = isset($namespaces[$i]) ? $namespaces[$i] : '';
				isset($declared[$namespace]) || $declared[$namespace] = array();
				$declared[$namespace][strtolower($next[1])] = TRUE;
			}
			break;
		}
	}

	return array(
		'namespaces'=>$namespaces,
		'scopes'=>$scopes,
		'declared'=>$declared,
		'imported'=>$imported,
	);
}

function plugin_php_function_symbol_preserved($context, $offset, $name, $fully_qualified = FALSE, $global_declaration = TRUE) {
	$name = strtolower($name);
	if($fully_qualified) return !empty($context['declared'][''][$name]);
	$namespace = isset($context['namespaces'][$offset]) ? $context['namespaces'][$offset] : '';
	if(($namespace !== '' || $global_declaration) && !empty($context['declared'][$namespace][$name])) return TRUE;
	$scope = isset($context['scopes'][$offset]) ? $context['scopes'][$offset] : 0;
	return !empty($context['imported'][$scope][$name]);
}

function plugin_php8_removed_function_error($file) {
	$code = file_get_contents($file);
	if($code === FALSE || !function_exists('token_get_all')) return '';
	$removed = array(
		'create_function'=>TRUE,
		'each'=>TRUE,
		'ereg'=>TRUE,
		'eregi'=>TRUE,
		'ereg_replace'=>TRUE,
		'eregi_replace'=>TRUE,
		'get_magic_quotes_gpc'=>TRUE,
		'split'=>TRUE,
		'spliti'=>TRUE,
	);
	$tokens = token_get_all($code);
	$symbol_context = plugin_php_function_symbol_context($tokens);
	$fully_qualified_id = defined('T_NAME_FULLY_QUALIFIED') ? constant('T_NAME_FULLY_QUALIFIED') : -1;
	$attribute_id = defined('T_ATTRIBUTE') ? constant('T_ATTRIBUTE') : -1;
	$attribute_depth = 0;
	$count = count($tokens);
	for($i = 0; $i < $count; $i++) {
		$token = $tokens[$i];
		if(!is_array($token)) {
			if($attribute_depth > 0) {
				if($token === '[') $attribute_depth++;
				if($token === ']') $attribute_depth--;
			}
			continue;
		}
		$id = $token[0];
		if($id === $attribute_id) {
			$attribute_depth = 1;
			continue;
		}
		if($attribute_depth > 0) continue;
		$is_fully_qualified = $id === $fully_qualified_id;
		if($id !== T_STRING && !$is_fully_qualified) continue;
		$name = strtolower($is_fully_qualified ? ltrim($token[1], '\\') : $token[1]);
		if($is_fully_qualified && strpos($name, '\\') !== FALSE) continue;
		if(empty($removed[$name]) && strpos($name, 'mysql_') !== 0) continue;
		if(plugin_php_function_symbol_preserved($symbol_context, $i, $name, $is_fully_qualified)) continue;
		if(!$is_fully_qualified) {
			$prev = plugin_previous_code_token($tokens, $i);
			if($prev === T_OBJECT_OPERATOR || $prev === T_DOUBLE_COLON || $prev === T_FUNCTION) continue;
		}
		$next = plugin_next_code_token($tokens, $i);
		if($next === '(') return 'PHP 8 removed function call: '.$name.'()';
	}
	return '';
}

function plugin_previous_code_token($tokens, $offset) {
	for($i = $offset - 1; $i >= 0; $i--) {
		$token = $tokens[$i];
		if(is_array($token) && in_array($token[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT))) continue;
		return is_array($token) ? $token[0] : $token;
	}
	return NULL;
}

function plugin_next_code_token($tokens, $offset) {
	$count = count($tokens);
	for($i = $offset + 1; $i < $count; $i++) {
		$token = $tokens[$i];
		if(is_array($token) && in_array($token[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT))) continue;
		return is_array($token) ? $token[0] : $token;
	}
	return NULL;
}

function plugin_php_files($dir, &$error = '', $allowed_root = NULL, &$state = NULL) {
	$files = array();
	if($error !== '') return $files;
	if($allowed_root === NULL) $allowed_root = $dir;
	if($state === NULL) $state = array('visited'=>array(), 'entries'=>0);
	if(is_link($dir)) {
		$error = 'A symbolic-link directory is not allowed inside the plugin package root: '.$dir;
		return $files;
	}
	$real_dir = plugin_realpath_within($dir, $allowed_root);
	if($real_dir === FALSE || !is_dir($real_dir)) {
		$error = 'A package directory resolves outside the plugin package root or is unreadable: '.$dir;
		return $files;
	}
	$visit_key = DIRECTORY_SEPARATOR === '\\' ? strtolower($real_dir) : $real_dir;
	if(isset($state['visited'][$visit_key])) {
		$error = 'A recursive package directory was encountered more than once: '.$real_dir;
		return $files;
	}
	$state['visited'][$visit_key] = TRUE;
	$items = @scandir($real_dir);
	if($items === FALSE) {
		$error = 'A package directory could not be enumerated: '.$real_dir;
		return $files;
	}
	foreach($items as $entry) {
		if($entry === '.' || $entry === '..') continue;
		$state['entries']++;
		if($state['entries'] > 10000) {
			$error = 'The package contains more than 10000 filesystem entries; refusing an unbounded PHP preflight scan.';
			return array();
		}
		$item = $real_dir.'/'.$entry;
		if(is_link($item)) {
			$error = 'A symbolic-link entry is not allowed inside the plugin package root: '.$item;
			return array();
		}
		$real_item = plugin_realpath_within($item, $allowed_root);
		if($real_item === FALSE) {
			$error = 'A package entry resolves outside the plugin package root or is unreadable: '.$item;
			return array();
		}
		if(is_dir($real_item)) {
			$nested = plugin_php_files($real_item, $error, $allowed_root, $state);
			if($error !== '') return array();
			$files = array_merge($files, $nested);
		} elseif(is_file($real_item) && strtolower(substr($real_item, -4)) === '.php') {
			$files[] = $real_item;
		}
	}
	return $files;
}

function plugin_file_index_generation() {
	global $g_plugin_file_index_generation;
	return $g_plugin_file_index_generation;
}

function plugin_file_index_reset() {
	global $g_plugin_file_index_generation, $g_plugin_enabled_paths_generation, $g_plugin_enabled_paths;
	global $g_plugin_file_index_built_generation, $g_plugin_file_index;
	clearstatcache();
	$g_plugin_file_index_generation++;
	$g_plugin_enabled_paths_generation = 0;
	$g_plugin_enabled_paths = array();
	$g_plugin_file_index_built_generation = 0;
	$g_plugin_file_index = array();
	return $g_plugin_file_index_generation;
}

function plugin_paths_enabled() {
	global $g_plugin_file_index_generation, $g_plugin_enabled_paths_generation, $g_plugin_enabled_paths;
	if($g_plugin_enabled_paths_generation !== $g_plugin_file_index_generation) {
		$state_lock = plugin_state_visibility_read_lock_start();
		if($state_lock === FALSE) throw new RuntimeException('Failed to acquire plugin state snapshot lock.');
		try {
			$g_plugin_enabled_paths = array();
			$plugin_paths = plugin_package_roots();
			if(!empty($plugin_paths)) {
				foreach($plugin_paths as $dir=>$path) {
					$conf_error = '';
					$conffile = plugin_package_conf_path($dir, $conf_error);
					if($conffile === FALSE) {
						plugin_package_root_diagnostic($dir, $conf_error);
						continue;
					}
					$conf_bytes = file_get_contents($conffile);
					if($conf_bytes === FALSE) {
						plugin_package_root_diagnostic($dir, 'package conf.json could not be read');
						continue;
					}
					$pconf = xn_json_decode($conf_bytes);
					if(empty($pconf)) continue;
					if(empty($pconf['enable']) || empty($pconf['installed'])) continue;
					$g_plugin_enabled_paths[$path] = $pconf;
				}
			}
			$g_plugin_enabled_paths_generation = $g_plugin_file_index_generation;
		} finally {
			plugin_state_visibility_read_lock_end($state_lock);
		}
	}
	return $g_plugin_enabled_paths;
}

function plugin_file_index_case_sensitive() {
	static $case_sensitive = NULL;
	if($case_sensitive !== NULL) return $case_sensitive;

	// The PHP runtime's OS is not the filesystem contract: Linux containers may serve a
	// case-insensitive Windows bind mount. Probe this existing source file without creating any
	// package-side marker, then cache the answer for the request.
	$source = str_replace('\\', '/', __FILE__);
	$basename = basename($source);
	$case_variant = strtoupper($basename);
	if($case_variant === $basename) {
		$case_sensitive = TRUE;
	} else {
		$case_sensitive = !is_file(substr($source, 0, -strlen($basename)).$case_variant);
	}
	return $case_sensitive;
}

function plugin_file_index_path_key($path) {
	$path = str_replace('\\', '/', $path);
	return plugin_file_index_case_sensitive() ? $path : strtolower($path);
}

// Hook candidates historically used is_file(), so a case-insensitive backing filesystem matched
// a marker even when the package filename used different casing. Keep that filesystem contract
// while preserving exact Hook names on case-sensitive filesystems.
function plugin_file_index_hook_key($hookname) {
	return plugin_file_index_path_key($hookname);
}

// Overwrites may mirror arbitrarily nested core paths. Enumerate actual regular files and never
// traverse or select symlinks, preserving the existing overwrite containment boundary.
function plugin_file_index_overwrite_files($dir) {
	$files = array();
	$dir = rtrim(str_replace('\\', '/', $dir), '/').'/';
	if(!is_dir($dir) || is_link(rtrim($dir, '/'))) return $files;
	$items = glob($dir.'*');
	$items = is_array($items) ? $items : array();
	$dotitems = glob($dir.'.*');
	if(is_array($dotitems)) {
		foreach($dotitems as $item) {
			$name = basename($item);
			if($name === '.' || $name === '..') continue;
			$items[] = $item;
		}
	}
	sort($items, SORT_STRING);
	foreach($items as $item) {
		if(is_link($item)) continue;
		if(is_dir($item)) {
			$files = array_merge($files, plugin_file_index_overwrite_files($item));
		} elseif(is_file($item)) {
			$files[] = str_replace('\\', '/', $item);
		}
	}
	return $files;
}

// Build one request-local index from files that actually exist. This replaces the former
// source-file x hook-name x enabled-package missing-path probes with direct lookups.
function plugin_file_index() {
	global $g_plugin_file_index_generation, $g_plugin_file_index_built_generation, $g_plugin_file_index;
	if($g_plugin_file_index_built_generation === $g_plugin_file_index_generation) return $g_plugin_file_index;

	$hooks = array();
	$hook_mtimes = array();
	$overwrites = array();
	foreach(plugin_paths_enabled() as $path=>$pconf) {
		$hook_ranks = array();
		if(isset($pconf['hooks_rank']) && is_array($pconf['hooks_rank'])) {
			foreach($pconf['hooks_rank'] as $rank_name=>$rank) {
				$hook_ranks[plugin_file_index_hook_key($rank_name)] = $rank;
			}
		}
		$hook_root = rtrim(str_replace('\\', '/', $path), '/').'/hook/';
		$hookpaths = is_dir($hook_root) && !is_link(rtrim($hook_root, '/')) ? glob($hook_root.'*.*') : array();
		if(is_array($hookpaths)) {
			foreach($hookpaths as $hookpath) {
				if(is_link($hookpath) || !is_file($hookpath) || plugin_realpath_within($hookpath, $hook_root) === FALSE) continue;
				$hookpath = str_replace('\\', '/', $hookpath);
				$hookname = file_name($hookpath);
				$hookkey = plugin_file_index_hook_key($hookname);
				$rank = isset($hook_ranks[$hookkey]) ? $hook_ranks[$hookkey] : 0;
				$hooks[$hookkey][] = array('hookpath'=>$hookpath, 'rank'=>$rank);
				$mtime = @filemtime($hookpath);
				if($mtime !== FALSE && (!isset($hook_mtimes[$hookkey]) || $mtime > $hook_mtimes[$hookkey])) {
					$hook_mtimes[$hookkey] = $mtime;
				}
			}
		}

		$overwrite_root = rtrim(str_replace('\\', '/', $path), '/').'/overwrite/';
		$overwrite_ranks = array();
		if(isset($pconf['overwrites_rank']) && is_array($pconf['overwrites_rank'])) {
			foreach($pconf['overwrites_rank'] as $rank_path=>$rank) {
				$overwrite_ranks[plugin_file_index_path_key($rank_path)] = $rank;
			}
		}
		foreach(plugin_file_index_overwrite_files($overwrite_root) as $overwrite_file) {
			$filepath_half = substr($overwrite_file, strlen($overwrite_root));
			$filepath_key = plugin_file_index_path_key($filepath_half);
			$rank = isset($overwrite_ranks[$filepath_key]) ? $overwrite_ranks[$filepath_key] : 0;
			// Historical overwrite arbitration starts at rank 0: negative-rank candidates do not
			// replace the core file. Preserve that contract while retaining later-equal ordering.
			if($rank < 0) continue;
			if(!isset($overwrites[$filepath_key]) || $rank >= $overwrites[$filepath_key]['rank']) {
				$overwrites[$filepath_key] = array('path'=>$overwrite_file, 'rank'=>$rank);
			}
		}
	}

	foreach($hooks as $hookname=>$arrlist) {
		$arrlist = arrlist_multisort($arrlist, 'rank', FALSE);
		$hooks[$hookname] = arrlist_values($arrlist, 'hookpath');
	}
	$g_plugin_file_index = array(
		'generation'=>$g_plugin_file_index_generation,
		'hooks'=>$hooks,
		'hook_mtimes'=>$hook_mtimes,
		'overwrites'=>$overwrites,
	);
	$g_plugin_file_index_built_generation = $g_plugin_file_index_generation;
	return $g_plugin_file_index;
}

// 编译源文件，把插件合并到该文件，不需要递归，执行的过程中 include _include() 自动会递归。
function plugin_compile_srcfile($srcfile) {
	global $conf;
	// 判断是否开启插件
	if(!empty($conf['disabled_plugin'])) {
		$s = file_get_contents($srcfile);
		return $s;
	}
	
	// 如果有 overwrite，则用 overwrite 替换掉
	$srcfile = plugin_find_overwrite($srcfile);
	$s = file_get_contents($srcfile);
	if(plugin_compat_package_php_source($srcfile)) {
		$s = plugin_compat_rewrite_php8_hook_calls($s);
	} elseif(plugin_compat_package_template_source($srcfile)) {
		$s = plugin_compat_rewrite_php8_template_blocks($s);
	}
	
	// 最多支持 10 层
	for($i = 0; $i < 10; $i++) {
		if(strpos($s, '<!--{hook') !== FALSE || strpos($s, '// hook') !== FALSE) {
			$s = preg_replace('#<!--{hook\s+(.*?)}-->#', '// hook \\1', $s);
			$s = preg_replace_callback('#//\s*hook\s+(\S+)#is', 'plugin_compile_srcfile_callback', $s);
		} else {
			break;
		}
	}
	return $s;
}

function plugin_compat_package_php_source($srcfile) {
	if(!is_string($srcfile) || file_ext($srcfile) !== 'php') return FALSE;
	$source = plugin_realpath_within($srcfile, APP_PATH.'plugin');
	return $source !== FALSE && is_file($source);
}

function plugin_compat_package_template_source($srcfile) {
	if(!is_string($srcfile) || file_ext($srcfile) !== 'htm') return FALSE;
	$source = plugin_realpath_within($srcfile, APP_PATH.'plugin');
	return $source !== FALSE && is_file($source);
}

function plugin_compat_array_multisort_variable_triplet($tokens, $open_index, $trivia) {
	if(!isset($tokens[$open_index]) || $tokens[$open_index] !== '(') return FALSE;
	$args = array(array());
	$depth = 0;
	$closed = FALSE;
	$token_count = count($tokens);
	for($i = $open_index + 1; $i < $token_count; $i++) {
		$token = $tokens[$i];
		if(!is_array($token)) {
			if($token === '(' || $token === '[' || $token === '{') {
				$depth++;
			} elseif($token === ')' || $token === ']' || $token === '}') {
				if($token === ')' && $depth === 0) {
					$closed = TRUE;
					break;
				}
				$depth--;
				if($depth < 0) return FALSE;
			} elseif($token === ',' && $depth === 0) {
				$args[] = array();
				continue;
			}
		}
		$args[count($args) - 1][] = $token;
	}
	if(!$closed || count($args) !== 3) return FALSE;

	foreach(array(0, 2) as $argument_index) {
		$meaningful = array();
		foreach($args[$argument_index] as $token) {
			if(is_array($token) && in_array($token[0], $trivia, TRUE)) continue;
			if(!is_array($token) && trim($token) === '') continue;
			$meaningful[] = $token;
		}
		if(count($meaningful) !== 1 || !is_array($meaningful[0]) || $meaningful[0][0] !== T_VARIABLE) return FALSE;
	}

	$sort_order = array();
	foreach($args[1] as $token) {
		if(is_array($token) && in_array($token[0], $trivia, TRUE)) continue;
		if(!is_array($token) && trim($token) === '') continue;
		$sort_order[] = $token;
	}
	if(count($sort_order) !== 1 || !is_array($sort_order[0]) || $sort_order[0][0] !== T_STRING) return FALSE;
	return in_array(strtoupper($sort_order[0][1]), array('SORT_ASC', 'SORT_DESC'), TRUE);
}

function plugin_compat_rewrite_php8_hook_calls($source, $mixed_template = FALSE) {
	if(!is_string($source)) return $source;
	$rewrites = array(
		'count'=>'xn_count_compat',
		'array_column'=>'xn_array_column_compat',
	);
	$has_candidate = FALSE;
	foreach(array_merge(array_keys($rewrites), array('array_multisort')) as $name) {
		if(stripos($source, $name) !== FALSE) {
			$has_candidate = TRUE;
			break;
		}
	}
	if(!$has_candidate) return $source;

	$tokens = token_get_all($mixed_template ? $source : '<?php '.$source);
	if(!$mixed_template && !empty($tokens) && is_array($tokens[0]) && $tokens[0][0] == T_OPEN_TAG) array_shift($tokens);
	$symbol_context = plugin_php_function_symbol_context($tokens);
	$fully_qualified_id = defined('T_NAME_FULLY_QUALIFIED') ? constant('T_NAME_FULLY_QUALIFIED') : -1;
	$attribute_id = defined('T_ATTRIBUTE') ? constant('T_ATTRIBUTE') : -1;
	$blocked_previous = array(T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW);
	defined('T_NULLSAFE_OBJECT_OPERATOR') AND $blocked_previous[] = constant('T_NULLSAFE_OBJECT_OPERATOR');
	$trivia = array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG, T_CLOSE_TAG);
	$previous = NULL;
	$expect_function_name = FALSE;
	$attribute_depth = 0;
	$in_php = !$mixed_template;
	$output = '';
	$token_count = count($tokens);

	foreach($tokens as $index=>$token) {
		if(is_array($token)) {
			$id = $token[0];
			$text = $token[1];
			if($mixed_template && ($id === T_OPEN_TAG || (defined('T_OPEN_TAG_WITH_ECHO') && $id === constant('T_OPEN_TAG_WITH_ECHO')))) {
				$in_php = TRUE;
				$previous = NULL;
				$expect_function_name = FALSE;
				$attribute_depth = 0;
				$output .= $text;
				continue;
			}
			if($mixed_template && $id === T_CLOSE_TAG) {
				$in_php = FALSE;
				$previous = NULL;
				$expect_function_name = FALSE;
				$attribute_depth = 0;
				$output .= $text;
				continue;
			}
			if(!$in_php) {
				$output .= $text;
				continue;
			}
			$is_trivia = in_array($id, $trivia, TRUE);
			$is_function_name = $expect_function_name && $id == T_STRING;
			$is_attribute_context = $attribute_depth > 0;
			if($id === $attribute_id) {
				$attribute_depth = 1;
				$is_attribute_context = TRUE;
			}

			$rewrite = NULL;
			$name = '';
			$is_fully_qualified = $id === $fully_qualified_id;
			if($id == T_STRING && !$is_function_name && !$is_attribute_context) {
				$name = strtolower($text);
				// count(), array_column() and array_multisort() are built-ins in the global namespace;
				// a same-name global declaration cannot provide a usable replacement there. Namespaced
				// declarations and explicit function imports remain valid package-owned symbols.
				if(!plugin_php_function_symbol_preserved($symbol_context, $index, $name, FALSE, FALSE)) {
					if(isset($rewrites[$name])) $rewrite = $rewrites[$name];
					if($name === 'array_multisort') $rewrite = 'xn_array_multisort_compat';
				}
			} elseif($is_fully_qualified && !$is_attribute_context) {
				$name = strtolower(ltrim($text, '\\'));
				if(strpos($name, '\\') === FALSE) {
					if(isset($rewrites[$name])) $rewrite = '\\'.$rewrites[$name];
					if($name === 'array_multisort') $rewrite = '\\xn_array_multisort_compat';
				}
			}
			if($rewrite !== NULL) {
				$next_index = $index + 1;
				while($next_index < $token_count) {
					$next = $tokens[$next_index];
					if(!is_array($next) || !in_array($next[0], $trivia, TRUE)) break;
					$next_index++;
				}
				$next = $next_index < $token_count ? $tokens[$next_index] : NULL;
				$shape_ok = $name !== 'array_multisort'
					|| plugin_compat_array_multisort_variable_triplet($tokens, $next_index, $trivia);
				if($next === '(' && $shape_ok && !in_array($previous, $blocked_previous, TRUE)) {
					$text = $rewrite;
				}
			}

			$output .= $text;
			if(!$is_trivia) {
				if($id == T_FUNCTION) {
					$expect_function_name = TRUE;
				} elseif($is_function_name) {
					$expect_function_name = FALSE;
				}
				$previous = $id;
			}
		} else {
			if(!$in_php) {
				$output .= $token;
				continue;
			}
			$output .= $token;
			if($attribute_depth > 0) {
				if($token === '[') $attribute_depth++;
				if($token === ']') $attribute_depth--;
			}
			if(trim($token) !== '') {
				if($expect_function_name && $token == '(') $expect_function_name = FALSE;
				$previous = $token;
			}
		}
	}

	return $output;
}

// Backward-compatible alias for callers introduced with the first PHP 8
// Hook rewrite. New compiler paths use the generic call rewriter above.
function plugin_compat_rewrite_php8_hook_count($source) {
	return plugin_compat_rewrite_php8_hook_calls($source);
}

// Legacy .htm Hook and overwrite files are mixed HTML/PHP documents. Rewrite only bytes tokenized
// inside real PHP blocks: feeding the whole template to the fragment rewriter would either treat
// markup as PHP or mutate lookalike function names in JavaScript, CSS and text attributes.
function plugin_compat_rewrite_php8_template_blocks($source) {
	if(!is_string($source) || strpos($source, '<?') === FALSE) return $source;
	return plugin_compat_rewrite_php8_hook_calls($source, TRUE);
}


// 只返回一个权重最高的文件名
function plugin_find_overwrite($srcfile) {
	$len = strlen(APP_PATH);
	/*
	// 如果发现插件目录，则尝试去掉插件目录前缀，避免新建的 overwrite 目录过深。
	if(strpos($srcfile, '/plugin/') !== FALSE) {
		preg_match('#'.preg_quote(APP_PATH).'plugin/\w+/#i', $srcfile, $m);
		if(!empty($m[0])) {
			$len = strlen($m[0]);
		}
	}*/
	$filepath_half = plugin_file_index_path_key(substr($srcfile, $len));
	$file_index = plugin_file_index();
	return isset($file_index['overwrites'][$filepath_half]) ? $file_index['overwrites'][$filepath_half]['path'] : $srcfile;
}

function plugin_compile_srcfile_callback($m) {
	$file_index = plugin_file_index();
	$hooks = $file_index['hooks'];
	
	$s = '';
	$hookname = $m[1];
	$hookkey = plugin_file_index_hook_key($hookname);
	if(!empty($hooks[$hookkey])) {
		foreach($hooks[$hookkey] as $path) {
			$t = file_get_contents($path);
			$fileext = file_ext($path);
			if($fileext == 'php' && preg_match('#^\s*<\?php\s+exit;#is', $t)) {
				// 正则表达式去除兼容性比较好。
				$t = preg_replace('#^\s*<\?php\s*exit;(.*?)(?:\?>)?\s*$#is', '\\1', $t);
				
				/* 去掉首尾标签
				if(substr($t, 0, 5) == '<?php' && substr($t, -2, 2) == '?>') {
					$t = substr($t, 5, -2);		
				}
				// 去掉 exit;
				$t = preg_replace('#\s*exit;\s*#', "\r\n", $t);
				*/
			}
			if($fileext == 'php') {
				$t = plugin_compat_rewrite_php8_hook_calls($t);
			} elseif($fileext == 'htm') {
				$t = plugin_compat_rewrite_php8_template_blocks($t);
			}
			$s .= $t;
		}
	}
	return $s;
}

// 先下载，购买，付费，再安装
function plugin_online_install($dir) {

}



// -------------------> 官方插件列表缓存到本地。

// 条件满足的总数
function plugin_official_total($cond = array()) {
	global $official_plugins;
	$offlist = $official_plugins;
	$offlist = arrlist_cond_orderby($offlist, $cond, array(), 1, 1000);
	return count($offlist);
}

// 远程插件列表，从官方服务器获取插件列表，全部缓存到本地，定期更新
function plugin_official_list($cond = array(), $orderby = array('pluginid'=>-1), $page = 1, $pagesize = 20) {
	global $official_plugins;
	// 服务端插件信息，缓存起来
	$offlist = $official_plugins;
	$offlist = arrlist_cond_orderby($offlist, $cond, $orderby, $page, $pagesize);
	foreach($offlist as &$plugin) $plugin = plugin_read_by_dir($plugin['dir'], FALSE);
	return $offlist;
}

function plugin_official_list_cache() {
	// 官方插件市场已关闭，直接返回空数组
	return array();
}

function plugin_official_read($dir) {
	global $official_plugins;
	$offlist = $official_plugins;
	$plugin = isset($offlist[$dir]) ? $offlist[$dir] : array();
	return $plugin;
}

// -------------------> 本地插件列表缓存到本地。
// 安装，卸载，禁用，更新
function plugin_compat_icon_url($dir, $pluginid = 0) {
	$root_error = '';
	$root = plugin_package_root_path($dir, $root_error);
	$icon_file = $root === FALSE ? '' : $root.'icon.png';
	if($icon_file !== '' && !is_link($icon_file) && is_file($icon_file) && is_readable($icon_file)
		&& plugin_realpath_within($icon_file, $root) !== FALSE) return '../plugin/'.$dir.'/icon.png';

	$pluginid = intval($pluginid);
	if($pluginid > 0) return PLUGIN_OFFICIAL_URL.'upload/plugin/'.$pluginid.'/icon.png';

	return '../view/img/logo.png';
}

function plugin_read_by_dir($dir, $local_first = TRUE) {
	global $plugins;

	$local = array_value($plugins, $dir, array());
	$official = plugin_official_read($dir);
	if(empty($local) && empty($official)) return array();
	if(empty($local)) $local_first = FALSE;
	
	// 本地插件信息
	//!isset($plugin['dir']) && $plugin['dir'] = '';
	!isset($local['name']) && $local['name'] = '';
	!isset($local['price']) && $local['price'] = 0;
	!isset($local['brief']) && $local['brief'] = '';
	!isset($local['version']) && $local['version'] = '1.0';
	!isset($local['bbs_version']) && $local['bbs_version'] = '4.0';
	!isset($local['installed']) && $local['installed'] = 0;
	!isset($local['enable']) && $local['enable'] = 0;
	!isset($local['hooks']) && $local['hooks'] = array();
	!isset($local['hooks_rank']) && $local['hooks_rank'] = array();
	!isset($local['dependencies']) && $local['dependencies'] = array();
	// Always materialize the local value before merging official metadata. Auto-replacement must be
	// authorized by both installed conf.json files, never inferred from a remote catalogue field.
	$exclusive_group_raw = isset($local['exclusive_group']) ? $local['exclusive_group'] : '';
	$exclusive_group_invalid = $exclusive_group_raw !== '' && plugin_exclusive_group_normalize($exclusive_group_raw) === '';
	$local['exclusive_group'] = plugin_exclusive_group_normalize($exclusive_group_raw);
	!isset($local['metadata_error']) && $local['metadata_error'] = 0;
	if($exclusive_group_invalid) $local['metadata_error'] = 1;
	if(!is_array($local['dependencies'])) {
		$local['dependencies'] = array();
		$local['metadata_error'] = 1;
	}
	$local['installed'] = empty($local['installed']) ? 0 : 1;
	$local['enable'] = empty($local['enable']) ? 0 : 1;
	if(!$local['installed'] && $local['enable']) {
		$local['metadata_error'] = 1;
	}
	!isset($local['icon_url']) && $local['icon_url'] = '';
	!isset($local['have_setting']) && $local['have_setting'] = 0;
	!isset($local['setting_url']) && $local['setting_url'] = 0;
	
	// 加上官方插件的信息
	!isset($official['pluginid']) && $official['pluginid'] = 0;
	!isset($official['name']) && $official['name'] = '';
	!isset($official['price']) && $official['price'] = 0;
	!isset($official['brief']) && $official['brief'] = '';
	!isset($official['bbs_version']) && $official['bbs_version'] = '4.0';
	!isset($official['version']) && $official['version'] = '1.0';
	!isset($official['cateid']) && $official['cateid'] = 0;
	!isset($official['lastupdate']) && $official['lastupdate'] = 0;
	!isset($official['stars']) && $official['stars'] = 0;
	!isset($official['user_stars']) && $official['user_stars'] = 0;
	!isset($official['installs']) && $official['installs'] = 0;
	!isset($official['sells']) && $official['sells'] = 0;
	!isset($official['file_md5']) && $official['file_md5'] = '';
	!isset($official['filename']) && $official['filename'] = '';
	!isset($official['is_cert']) && $official['is_cert'] = 0;
	!isset($official['is_show']) && $official['is_show'] = 0;
	!isset($official['img1']) && $official['img1'] = 0;
	!isset($official['img2']) && $official['img2'] = 0;
	!isset($official['img3']) && $official['img3'] = 0;
	!isset($official['img4']) && $official['img4'] = 0;
	!isset($official['brief_url']) && $official['brief_url'] = '';
	!isset($official['qq']) && $official['qq'] = '';
	
	$local['official'] = $official;
	
	if($local_first) {
		$plugin = $local + $official;
	} else {
		$plugin = $official + $local;
	}
	// exclusive_group is never catalogue-authoritative, including official-first display reads.
	// Only the value normalized from this machine's conf.json may authorize local lifecycle writes.
	$plugin['exclusive_group'] = $local['exclusive_group'];
	// 额外的判断
	$plugin['icon_url'] = plugin_compat_icon_url($dir, $plugin['pluginid']);
	$plugin['setting_url'] = $plugin['installed'] && is_file("../plugin/$dir/setting.php") ? 1 : 0;
	$plugin['downloaded'] = isset($plugins[$dir]);
	$plugin['stars_fmt'] = $plugin['pluginid'] ? str_repeat('<span class="icon star"></span>', $plugin['stars']) : '';
	$plugin['user_stars_fmt'] = $plugin['pluginid'] ? str_repeat('<span class="icon star"></span>', $plugin['user_stars']) : '';
	$plugin['is_cert_fmt'] = empty($plugin['is_cert']) ? '<span class="text-danger">'.lang('no').'</span>' : '<span class="text-success">'.lang('yes').'</span>';
	$plugin['have_upgrade'] = $plugin['installed'] && version_compare($official['version'], $local['version']) > 0 ? TRUE : FALSE;
	$plugin['official_version'] = $official['version']; // 官方版本
	$plugin['img1_url'] = $official['img1'] ? PLUGIN_OFFICIAL_URL.'upload/plugin/'.$plugin['pluginid'].'/img1.jpg' : ''; // 官方版本
	$plugin['img2_url'] = $official['img2'] ? PLUGIN_OFFICIAL_URL.'upload/plugin/'.$plugin['pluginid'].'/img2.jpg' : ''; // 官方版本
	$plugin['img3_url'] = $official['img3'] ? PLUGIN_OFFICIAL_URL.'upload/plugin/'.$plugin['pluginid'].'/img3.jpg' : ''; // 官方版本
	$plugin['img4_url'] = $official['img4'] ? PLUGIN_OFFICIAL_URL.'upload/plugin/'.$plugin['pluginid'].'/img4.jpg' : ''; // 官方版本
	return $plugin;
}

function plugin_siteid() {
	global $conf;
	$auth_key = $conf['auth_key'];
	$siteip = _SERVER('SERVER_ADDR');
	$siteid = md5($auth_key.$siteip);
	return $siteid;
}

/*function plugin_outid($dir) {
	global $conf;
	$auth_key = $conf['auth_key'];
	$siteip = _SERVER('SERVER_ADDR')
	$outid = md5($auth_key.$siteip.$dir);
	return $outid;
}*/
?>
