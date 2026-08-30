<?php

$root = dirname(__DIR__);
$skips = array();

function fail($message) {
	fwrite(STDERR, "FAIL: $message\n");
	exit(1);
}

function source_text($path) {
	$source = file_get_contents($path);
	$source === FALSE AND fail("Unable to read $path");
	return str_replace(array("\r\n", "\r"), "\n", $source);
}

function section_between($source, $start, $end) {
	$start_pos = strpos($source, $start);
	if($start_pos === FALSE) fail("Missing section start: $start");
	$end_pos = strpos($source, $end, $start_pos + strlen($start));
	if($end_pos === FALSE) fail("Missing section end after $start: $end");
	return substr($source, $start_pos, $end_pos - $start_pos);
}

function remove_task_fixture($dir) {
	if(is_link($dir) || is_file($dir)) {
		@unlink($dir);
		return;
	}
	if(!is_dir($dir)) return;
	$items = glob(rtrim($dir, '/').'/*') ?: array();
	$dotitems = glob(rtrim($dir, '/').'/.??*') ?: array();
	foreach(array_merge($items, $dotitems) as $item) {
		(is_dir($item) && !is_link($item)) ? remove_task_fixture($item) : @unlink($item);
	}
	@rmdir($dir);
}

$plugin_route = source_text($root.'/admin/route/plugin.php');
$other_route = source_text($root.'/admin/route/other.php');
$update_route = source_text($root.'/admin/route/update.php');
$upgrade_command = source_text($root.'/src/Console/Command/UpgradeCommand.php');
$misc = source_text($root.'/model/misc.func.php');
$plugin_list = source_text($root.'/admin/view/htm/plugin_list.htm');
$plugin_read = source_text($root.'/admin/view/htm/plugin_read.htm');
$bbs_js = source_text($root.'/view/js/bbs.js');

strpos($plugin_route, "function plugin_message(\$code, \$message)") !== FALSE
	|| fail('plugin_message() helper is missing.');
strpos($plugin_route, "plugin_lock_end();\n\tmessage(\$code, \$message);") !== FALSE
	|| fail('plugin_message() must release the plugin task lock before exiting.');

strpos($plugin_route, "function plugin_lock_name()") !== FALSE
	|| fail('plugin_lock_name() helper is missing.');
strpos($plugin_route, "return 'plugin_task';") !== FALSE
	|| fail('Plugin writes must share one global plugin_task lock.');
strpos($plugin_route, "!xn_lock_start(plugin_lock_name(), 300)") !== FALSE
	|| fail('Plugin task lock must use the shared key with an extended TTL.');
strpos($plugin_route, 'plugin_state_visibility_write_lock_start()') !== FALSE
	|| fail('Plugin lifecycle writes must hold the cross-request state visibility lock.');
strpos($plugin_route, 'plugin_state_visibility_write_lock_end();') !== FALSE
	|| fail('Plugin lifecycle completion must release the state visibility lock.');
$lock_start = section_between($plugin_route, 'function plugin_lock_start()', 'function plugin_lock_end()');
$task_lock_pos = strpos($lock_start, '!xn_lock_start(plugin_lock_name(), 300)');
$task_owned_pos = strpos($lock_start, '$plugin_task_locked = TRUE;');
$shutdown_guard_pos = strpos($lock_start, "register_shutdown_function('plugin_shutdown_guard')");
$write_lock_pos = strpos($lock_start, 'plugin_state_visibility_write_lock_start()');
$reload_pos = strpos($lock_start, 'plugin_init() === TRUE');
($task_lock_pos !== FALSE && $task_owned_pos !== FALSE && $shutdown_guard_pos !== FALSE
	&& $task_lock_pos < $task_owned_pos && $task_owned_pos < $shutdown_guard_pos
	&& $shutdown_guard_pos < $write_lock_pos)
	|| fail('Plugin task ownership and shutdown cleanup must be active before waiting for the visibility writer lock.');
($write_lock_pos !== FALSE && $reload_pos !== FALSE && $write_lock_pos < $reload_pos)
	|| fail('Plugin task acquisition must rebuild plugin state only after obtaining the exclusive visibility lock.');
strpos($plugin_route, "register_shutdown_function('plugin_shutdown_guard')") !== FALSE
	|| fail('Plugin task lock must register a shutdown guard for direct lifecycle exits.');
strpos($plugin_route, 'function plugin_shutdown_guard()') !== FALSE
	|| fail('Plugin shutdown guard helper is missing.');
strpos($plugin_route, "plugin_lifecycle_guard_restore();\n\tplugin_lock_end();") !== FALSE
	|| fail('Plugin shutdown guard must restore pending lifecycle state and release the task lock.');
strpos($plugin_route, 'function plugin_require_post()') !== FALSE
	|| fail('Plugin write actions must have a POST guard.');
strpos($plugin_route, 'function plugin_require_action_state($dir, $action, $plugin = NULL)') !== FALSE
	|| fail('Plugin write actions must have a state precondition helper.');
strpos($plugin_route, "lang('plugin_not_need_update')") !== FALSE
	|| fail('Plugin upgrade action must reject stale no-op upgrade requests.');

$dependency = section_between($plugin_route, 'function plugin_check_dependency', 'function plugin_dependency_arr_to_links');
substr_count($dependency, 'plugin_message(-1, $msg);') === 2
	|| fail('Dependency checks must release the lock before reporting errors.');
$dependency_direct = str_replace('plugin_message(-1, $msg)', '', $dependency);
strpos($dependency_direct, 'message(-1, $msg)') === FALSE
	|| fail('Dependency checks must not call message() directly.');

$download = section_between($plugin_route, 'function plugin_download_unzip', 'function plugin_package_snapshot');
strpos($download, 'plugin_official_remote_closed();') !== FALSE
	|| fail('Legacy official plugin download/unzip must fail closed while no trusted registry exists.');
strpos($download, 'http_get(') === FALSE && strpos($download, 'extractTo(') === FALSE && strpos($download, 'xn_unzip(') === FALSE
	|| fail('Legacy official plugin download/unzip must not fetch or extract remote packages.');
strpos($plugin_route, 'function plugin_official_remote_closed()') !== FALSE
	|| fail('Official plugin marketplace fail-closed helper is missing.');
strpos($plugin_route, "plugin_message(-1, plugin_official_remote_closed_error());") !== FALSE
	|| fail('Official plugin marketplace fail-closed helper must release the plugin task lock through plugin_message().');

$zip_validate = section_between($plugin_route, 'function plugin_zip_validate_package', 'function plugin_copy_dir');
strpos($zip_validate, 'xn_zip_safe_name($name)') !== FALSE
	|| fail('Plugin ZIP validation must reject unsafe archive paths.');
strpos($zip_validate, 'plugin_zip_entry_is_symlink($zip, $i)') !== FALSE
	|| fail('Plugin ZIP validation must reject symlink entries.');
strpos($zip_validate, "strpos(\$name, \$dir.'/') !== 0") !== FALSE
	|| fail('Plugin ZIP validation must constrain entries to the expected plugin directory.');
strpos($plugin_route, 'function plugin_zip_entry_is_symlink($zip, $index)') !== FALSE
	|| fail('Plugin ZIP symlink detector helper is missing.');

$copy_dir = section_between($plugin_route, 'function plugin_copy_dir', 'function plugin_mkdir_recursive');
strpos($copy_dir, 'return FALSE;') !== FALSE
	|| fail('Plugin package copy must return FALSE on write failures.');
strpos($copy_dir, 'is_link($item)') !== FALSE
	|| fail('Plugin package copy must reject symlinks before copying.');
strpos($copy_dir, 'Unsupported file type') !== FALSE
	|| fail('Plugin package copy must reject unsupported filesystem entries.');

$install = section_between($plugin_route, "} elseif(\$action == 'install')", "} elseif(\$action == 'unstall')");
strpos($install, '$replacement_dirs = plugin_require_auto_unstall_contract($dir);') !== FALSE
	|| fail('Install flow must resolve the explicit replacement contract before lifecycle state changes.');
strpos($install, 'plugin_require_auto_unstall_storage_writable($dir, $replacement_dirs);') !== FALSE
	|| fail('Install flow must preflight every explicit replacement state target.');
strpos($install, 'plugin_check_auto_unstall_dependencies($dir, $replacement_dirs);') !== FALSE
	|| fail('Install flow must preflight reverse dependencies for the frozen explicit replacement set.');
strpos($install, 'plugin_auto_unstall_same_type($dir, $plugin_snapshot, $replacement_dirs);') !== FALSE
	|| fail('Install flow must route only the preflighted explicit replacement set through aggregate cleanup.');
$replacement_contract_pos = strpos($install, '$replacement_dirs = plugin_require_auto_unstall_contract($dir);');
$install_state_pos = strpos($install, 'plugin_require_state_write(plugin_install($dir)');
$install_lifecycle_pos = strpos($install, "plugin_run_lifecycle(\$dir, 'install'");
($replacement_contract_pos !== FALSE && $install_state_pos !== FALSE && $install_lifecycle_pos !== FALSE
	&& $replacement_contract_pos < $install_state_pos && $replacement_contract_pos < $install_lifecycle_pos)
	|| fail('The explicit replacement set must be frozen before plugin_install() or third-party install.php can run.');
$last_unstall = strrpos($install, 'plugin_require_state_write(plugin_unstall($_dir), $_dir);');
$lock_end = strpos($install, 'plugin_lock_end();');
($last_unstall === FALSE && $lock_end !== FALSE)
	|| fail('Install flow must not directly unstall same-type plugins without dependency/lifecycle guards.');

$auto_plan = section_between($plugin_route, 'function plugin_auto_unstall_plan', 'function plugin_require_auto_unstall_contract');
strpos($auto_plan, "empty(\$_plugin['installed'])") !== FALSE
	|| fail('Explicit auto-uninstall candidates must be limited to installed plugins.');
substr_count($auto_plan, 'plugin_exclusive_group_normalize(') >= 2
	&& strpos($auto_plan, "\$plan['exclusive_group'] !== '' && \$_group === \$plan['exclusive_group']") !== FALSE
	|| fail('Auto-uninstall must require both packages to declare the same normalized exclusive_group.');
$auto_contract = section_between($plugin_route, 'function plugin_require_auto_unstall_contract', 'function plugin_auto_unstall_candidates');
strpos($plugin_route, 'plugin_legacy_auto_unstall_match') === FALSE
	&& strpos($auto_plan, 'legacy_candidates') === FALSE
	&& strpos($auto_contract, "return \$plan['candidates'];") !== FALSE
	&& strpos($auto_contract, 'plugin_unstall(') === FALSE
	|| fail('Directory names must remain opaque and replacement authority must come only from explicit groups.');
$auto_candidates = section_between($plugin_route, 'function plugin_auto_unstall_candidates', 'function plugin_check_auto_unstall_dependencies');
strpos($auto_candidates, 'return plugin_require_auto_unstall_contract($dir);') !== FALSE
	|| fail('Compatibility callers must pass through the same fail-closed replacement contract.');
$auto_dependency = section_between($plugin_route, 'function plugin_check_auto_unstall_dependencies', 'function plugin_auto_unstall_same_type');
strpos($auto_dependency, "plugin_check_dependency(\$_dir, 'unstall');") !== FALSE
	|| fail('Auto-uninstall must check reverse dependencies before installing the replacement.');
$auto_unstall = section_between($plugin_route, 'function plugin_auto_unstall_same_type', 'function plugin_check_dependency');
strpos($auto_unstall, '$restore_states = array();') !== FALSE
	|| fail('Auto-uninstall must track all same-type rollback snapshots in one replacement batch.');
strpos($auto_unstall, '$restore_states[$dir] = $primary_snapshot') !== FALSE
	|| fail('Auto-uninstall must include the newly installed plugin state in batch rollback context.');
strpos($auto_unstall, '$restore_states[$_dir] = $snapshot;') !== FALSE
	|| fail('Auto-uninstall must add each old same-type plugin snapshot before mutating it.');
strpos($auto_unstall, 'plugin_require_state_write(FALSE, $_dir, $snapshot, NULL, $restore_states);') !== FALSE
	|| fail('Auto-uninstall write failures must restore every plugin state already touched through the checked state-write boundary.');
strpos($auto_unstall, "plugin_run_lifecycle(\$_dir, 'unstall', \$snapshot, NULL, \$restore_states);") !== FALSE
	|| fail('Auto-uninstall must run the old plugin unstall lifecycle with full batch rollback context.');
strpos($auto_unstall, 'plugin_check_auto_unstall_result($dir, $restore_states);') !== FALSE
	|| fail('Auto-uninstall must re-check the new plugin dependencies after same-type removals.');
$auto_result = section_between($plugin_route, 'function plugin_check_auto_unstall_result', 'function plugin_check_dependency');
strpos($auto_result, 'plugin_dependencies($dir)') !== FALSE
	|| fail('Auto-uninstall result check must inspect the new plugin dependencies.');
strpos($auto_result, 'plugin_lifecycle_restore_or_fail($dir, NULL, NULL, $restore_states);') !== FALSE
	|| fail('Auto-uninstall result failures must restore the full replacement batch through checked aggregate restoration.');
strpos($auto_result, 'plugin_dependency_arr_to_links($arr)') !== FALSE
	|| fail('Auto-uninstall result failures must show structured dependency details.');
strpos($auto_result, 'plugin_message(-1, $msg);') !== FALSE
	|| fail('Auto-uninstall result failures must release the plugin task lock.');

foreach (array('download', 'install', 'unstall', 'enable', 'disable', 'upgrade') as $action) {
	$branch = section_between($plugin_route, "} elseif(\$action == '$action')", "\n\tplugin_lock_start();");
	strpos($branch, 'plugin_require_post();') !== FALSE
		|| fail("Plugin $action action must require POST before locking.");
}

foreach (array('install', 'enable', 'upgrade') as $action) {
	$next = $action == 'install' ? 'unstall' : ($action == 'enable' ? 'disable' : 'setting');
	$branch = section_between($plugin_route, "} elseif(\$action == '$action')", "} elseif(\$action == '$next')");
	$code = preg_replace('#//[^\n]*#', '', $branch);
	if($action == 'upgrade' && strpos($code, 'plugin_official_remote_closed();') !== FALSE) {
		continue;
	}
	strpos($code, "plugin_require_action_state(\$dir, '$action") !== FALSE
		|| fail("Plugin $action action must reject stale or repeated state transitions.");
	(
		strpos($code, "plugin_check_dependency(\$dir, 'install');") !== FALSE ||
		strpos($code, "plugin_check_dependency(\$dir, 'install',") !== FALSE
	)
		|| fail("Plugin $action action must check install dependencies in executable code.");
	(strpos($code, 'plugin_check_php_syntax($dir);') !== FALSE || strpos($code, 'plugin_check_php_syntax($dir, $package_snapshot);') !== FALSE)
		|| fail("Plugin $action action must run PHP syntax checks before enabling code.");
}

foreach (array('unstall', 'disable') as $action) {
	$next = $action == 'unstall' ? 'enable' : 'upgrade';
	$branch = section_between($plugin_route, "} elseif(\$action == '$action')", "} elseif(\$action == '$next')");
	$code = preg_replace('#//[^\n]*#', '', $branch);
	strpos($code, "plugin_require_action_state(\$dir, '$action") !== FALSE
		|| fail("Plugin $action action must reject stale or repeated state transitions.");
	strpos($code, "plugin_check_dependency(\$dir, 'unstall');") !== FALSE
		|| fail("Plugin $action action must check reverse dependencies in executable code.");
}

foreach (array($plugin_list, $plugin_read) as $template) {
	foreach (array('download', 'install', 'unstall', 'enable', 'disable', 'upgrade') as $action) {
		$needle = "plugin_url('$action', \$dir)";
		$pos = strpos($template, $needle);
		$pos !== FALSE || fail("Plugin $action control must use the lossless named-directory URL helper.");
		while($pos !== FALSE) {
			$line_start = strrpos(substr($template, 0, $pos), "\n");
			$line_end = strpos($template, "\n", $pos);
			$line = substr($template, $line_start === FALSE ? 0 : $line_start, $line_end - $line_start);
			if(strpos($line, '<a ') === FALSE) {
				$pos = strpos($template, $needle, $pos + strlen($needle));
				continue;
			}
			strpos($line, 'data-method="post"') !== FALSE
				|| fail("Plugin $action link must use data-method=\"post\".");
			$pos = strpos($template, $needle, $pos + strlen($needle));
		}
	}
}

strpos($plugin_read, 'class="btn btn-primary download" data-method="post"') !== FALSE
	|| fail('Plugin read download link must use data-method="post".');

strpos($bbs_js, 'a[data-method="post"]:not(.confirm)') !== FALSE
	|| fail('Non-confirm POST links must be handled by bbs.js.');
strpos($bbs_js, 'function xn_post_link_lock(jlink)') !== FALSE
	|| fail('POST links must share a pending-state guard in bbs.js.');
strpos($bbs_js, "if(!xn_post_link_lock(jthis)) return false;") !== FALSE
	|| fail('POST links must ignore duplicate clicks while a request is pending.');
strpos($bbs_js, 'xn_post_link_done(jlink, code, message);') !== FALSE
	|| fail('POST links must restore pending state on failed requests.');

$plugin_model = source_text($root.'/model/plugin.func.php');
$plugin_init = section_between($plugin_model, 'function plugin_init()', 'function plugin_dependencies');
$enabled_paths = section_between($plugin_model, 'function plugin_paths_enabled()', 'function plugin_file_index_case_sensitive');
$include_runtime = section_between($plugin_model, 'function _include(', 'function plugin_include_src_mtime');
strpos($include_runtime, 'plugin_include_cache_reader_release_paths();') !== FALSE
	|| fail('_include() must release only the previous cache-path lease between fragments.');
strpos($include_runtime, 'plugin_include_cache_reader_release_all();') === FALSE
	|| fail('_include() must retain the request-wide plugin-state snapshot between fragments.');
strpos($include_runtime, 'if($state_lock_owned) $g_plugin_include_state_lock = $state_lock;') !== FALSE
	|| fail('_include() must retain only the real shared-lock resource, never a nested TRUE token.');
foreach(array($plugin_init, $enabled_paths) as $state_reader) {
	strpos($state_reader, 'plugin_state_visibility_read_lock_start()') !== FALSE
		|| fail('Every core plugin-state snapshot reader must acquire the shared visibility lock.');
	strpos($state_reader, 'plugin_state_visibility_read_lock_end($state_lock)') !== FALSE
		|| fail('Every core plugin-state snapshot reader must release the shared visibility lock.');
}
$plugin_init_reset_pos = strpos($plugin_init, '$plugins = array();');
$plugin_init_scan_pos = strpos($plugin_init, 'plugin_package_roots()');
($plugin_init_reset_pos !== FALSE && $plugin_init_scan_pos !== FALSE && $plugin_init_reset_pos < $plugin_init_scan_pos)
	|| fail('Plugin state reload must clear the old request snapshot before scanning the final generation.');
strpos($enabled_paths, "throw new RuntimeException('Failed to acquire plugin state snapshot lock.')") !== FALSE
	|| fail('A plugin state read-lock failure must fail closed instead of compiling an empty enabled set.');
$clear_tmp = section_between($plugin_model, 'function runtime_cache_clear_regenerable()', 'function plugin_disable');
strpos($clear_tmp, "rmdir_recusive(\$conf['tmp_path']") === FALSE
	|| fail('plugin_clear_tmp_dir() must not blanket-wipe tmp; active locks and recovery backups live there.');
strpos($clear_tmp, 'rmdir_recusive($item)') === FALSE
	|| fail('Runtime cache maintenance must preserve unknown tmp directories instead of recursively deleting them.');
strpos($clear_tmp, 'plugin_tmp_entry_protected(') !== FALSE
	|| fail('plugin_clear_tmp_dir() must skip protected tmp entries.');
strpos($clear_tmp, 'function plugin_tmp_entry_protected(') !== FALSE
	|| fail('plugin_tmp_entry_protected() helper is missing.');
strpos($clear_tmp, 'function plugin_runtime_cache_target_is_known(') !== FALSE
	&& strpos($clear_tmp, 'if(!plugin_runtime_cache_target_is_known($name, $item)) continue;') !== FALSE
	&& strpos($clear_tmp, "is_file(\$path.'.lock')") !== FALSE
	|| fail('Runtime cache maintenance must use a stable-lock allowlist for published include caches.');
strpos($clear_tmp, 'function plugin_runtime_cache_staging_target(') !== FALSE
	&& strpos($clear_tmp, 'plugin_runtime_cache_staging_target($name, $item, $stage_target)') !== FALSE
	|| fail('Runtime cache maintenance must only collect expired atomic staging for a known cache target.');
strpos($clear_tmp, 'if(is_dir($item) || is_link($item)) continue;') !== FALSE
	|| fail('Runtime cache maintenance must preserve ownerless directories and symlinks.');
strpos($clear_tmp, "preg_match('~^lock_[A-Za-z0-9_-]{1,64}\\.lock\$~', \$name)") !== FALSE
	|| fail('Cache cleanup must never delete task lock files.');
strpos($clear_tmp, "strpos(\$name, 'update_backup_') === 0") !== FALSE
	|| fail('Cache cleanup must never delete online update backups.');
strpos($clear_tmp, "strpos(\$name, 'plugin_backup_') === 0") !== FALSE
	|| fail('Cache cleanup must never delete active plugin package snapshots.');
strpos($clear_tmp, "\$name === 'safe_mode'") !== FALSE
	&& strpos($clear_tmp, "\$name === 'update_extract'") !== FALSE
	&& strpos($clear_tmp, "preg_match('~^update_.+\\.zip\$~D', \$name)") !== FALSE
	|| fail('Unified cache cleanup must preserve fault-isolation state and active update staging.');
strpos($other_route, 'runtime_cache_clear_regenerable()') !== FALSE
	&& strpos($other_route, "rmdir_recusive(\$conf['tmp_path']") === FALSE
	|| fail('The admin cache action must use the unified lease-aware cache maintenance API.');
substr_count($update_route, 'runtime_cache_clear_regenerable()') >= 2
	&& strpos($update_route, "glob(\$conf['tmp_path'] . '*.php')") === FALSE
	|| fail('Online update and rollback must use the unified lease-aware cache maintenance API.');
strpos($upgrade_command, 'runtime_cache_clear_regenerable()') !== FALSE
	&& strpos($upgrade_command, "include_once APP_PATH . 'model/plugin.func.php';") !== FALSE
	&& strpos($upgrade_command, "@unlink(\$path)") === FALSE
	|| fail('CLI upgrade cleanup must bootstrap and use the unified lease-aware cache maintenance API.');

strpos($misc, "fopen(\$lockfile, 'x')") !== FALSE
	|| fail('xn_lock_start() should create lock files atomically.');
strpos($misc, 'function xn_lock_token()') !== FALSE
	|| fail('xn_lock_start() must create owner tokens for lock files.');
strpos($misc, '$g_xn_lock_tokens[$lockname] = $token;') !== FALSE
	|| fail('xn_lock_start() must remember the current task owner token.');
strpos($misc, 'hash_equals($g_xn_lock_tokens[$lockname], $token)') !== FALSE
	|| fail('xn_lock_end() must only remove locks owned by the current task.');

// A stalled request snapshot must not leave lifecycle writers blocked forever. Exercise the real
// lock helper with a short test-only deadline, then prove the same writer succeeds after release.
defined('DEBUG') || define('DEBUG', 0);
defined('APP_PATH') || define('APP_PATH', $root.'/');
require_once $root.'/model/plugin.func.php';
$write_lock_timeout_parameter = (new ReflectionFunction('plugin_state_visibility_write_lock_start'))->getParameters()[0];
$write_lock_timeout_parameter->isDefaultValueAvailable()
	&& $write_lock_timeout_parameter->getDefaultValue() > 0
	&& $write_lock_timeout_parameter->getDefaultValue() <= 20000
	|| fail('Plugin state writer default timeout must leave response headroom below common 30-second Web limits.');
$timeout_fixture = $root.'/tmp/plugin_state_timeout_'.bin2hex(random_bytes(6)).'/';
@mkdir($timeout_fixture, 0777, TRUE) || fail('Unable to create plugin state timeout fixture.');
register_shutdown_function(function() use ($timeout_fixture) { remove_task_fixture($timeout_fixture); });
$previous_conf = isset($conf) ? $conf : NULL;
$conf = array('tmp_path'=>$timeout_fixture);
$reader_lock = plugin_state_visibility_lock_acquire(LOCK_SH);
is_resource($reader_lock) || fail('Unable to acquire plugin state timeout reader fixture.');
$timeout_started = microtime(TRUE);
plugin_state_visibility_write_lock_start(50) === FALSE
	|| fail('Plugin state writer must time out while a request snapshot remains active.');
(microtime(TRUE) - $timeout_started) < 1
	|| fail('Plugin state writer timeout exceeded its bounded test deadline.');
plugin_state_visibility_read_lock_end($reader_lock)
	|| fail('Unable to release plugin state timeout reader fixture.');
plugin_state_visibility_write_lock_start(50)
	|| fail('Plugin state writer must acquire the lock after active snapshots release it.');
plugin_state_visibility_write_lock_end()
	|| fail('Unable to release plugin state timeout writer fixture.');
$conf = $previous_conf;
remove_task_fixture($timeout_fixture);

// Behavioral invariant: while a lifecycle request has published a temporary conf.json state but
// has not committed/rolled back, another request must block before building its enabled-package
// snapshot and then observe only the final generation.
if(function_exists('proc_open')) {
	$fixture = $root.'/tmp/plugin_state_visibility_'.bin2hex(random_bytes(6)).'/';
	@mkdir($fixture.'plugin/state_probe/', 0777, TRUE);
	@mkdir($fixture.'tmp/', 0777, TRUE);
	register_shutdown_function(function() use ($fixture) { remove_task_fixture($fixture); });
	$conf_file = $fixture.'plugin/state_probe/conf.json';
	$base_conf = array(
		'name'=>'state visibility probe',
		'installed'=>0,
		'enable'=>0,
		'hooks_rank'=>array(),
		'overwrites_rank'=>array(),
	);
	file_put_contents($conf_file, json_encode($base_conf)) !== FALSE
		|| fail('Unable to create plugin state visibility fixture.');

	$writer_file = $fixture.'writer.php';
	$reader_file = $fixture.'reader.php';
	$writer_source = '<?php'."\n"
		.'define(\'DEBUG\', 0);'."\n"
		.'define(\'APP_PATH\', '.var_export($fixture, TRUE).');'."\n"
		.'define(\'XIUNOPHP_PATH\', '.var_export($root.'/xiunophp/', TRUE).');'."\n"
		.'include XIUNOPHP_PATH.\'array.func.php\';'."\n"
		.'include XIUNOPHP_PATH.\'misc.func.php\';'."\n"
		.'include '.var_export($root.'/model/plugin.func.php', TRUE).';'."\n"
		.'$conf = array(\'tmp_path\'=>'.var_export($fixture.'tmp/', TRUE).');'."\n"
		.'plugin_state_visibility_write_lock_start() || exit(2);'."\n"
		.'$mid = '.var_export(array_merge($base_conf, array('installed'=>1, 'enable'=>1)), TRUE).';'."\n"
		.'file_put_contents('.var_export($conf_file, TRUE).', json_encode($mid)) !== FALSE || exit(3);'."\n"
		.'file_put_contents($argv[1], \'ready\') !== FALSE || exit(4);'."\n"
		.'$deadline = microtime(TRUE) + 10;'."\n"
		.'while(!is_file($argv[2]) && microtime(TRUE) < $deadline) usleep(10000);'."\n"
		.'is_file($argv[2]) || exit(5);'."\n"
		.'$final = '.var_export($base_conf, TRUE).';'."\n"
		.'file_put_contents('.var_export($conf_file, TRUE).', json_encode($final)) !== FALSE || exit(6);'."\n"
		.'plugin_state_visibility_write_lock_end() || exit(7);'."\n";
	$reader_source = '<?php'."\n"
		.'define(\'DEBUG\', 0);'."\n"
		.'define(\'APP_PATH\', '.var_export($fixture, TRUE).');'."\n"
		.'define(\'XIUNOPHP_PATH\', '.var_export($root.'/xiunophp/', TRUE).');'."\n"
		.'include XIUNOPHP_PATH.\'array.func.php\';'."\n"
		.'include XIUNOPHP_PATH.\'misc.func.php\';'."\n"
		.'include '.var_export($root.'/model/plugin.func.php', TRUE).';'."\n"
		.'$conf = array(\'tmp_path\'=>'.var_export($fixture.'tmp/', TRUE).');'."\n"
		.'$enabled = plugin_paths_enabled();'."\n"
		.'file_put_contents($argv[1], empty($enabled) ? \'final-disabled\' : \'saw-intermediate\') !== FALSE || exit(2);'."\n";
	file_put_contents($writer_file, $writer_source) !== FALSE || fail('Unable to create state writer worker.');
	file_put_contents($reader_file, $reader_source) !== FALSE || fail('Unable to create state reader worker.');

	$ready_file = $fixture.'writer-ready';
	$go_file = $fixture.'writer-go';
	$result_file = $fixture.'reader-result';
	$descriptor = array(0=>array('pipe', 'r'), 1=>array('pipe', 'w'), 2=>array('pipe', 'w'));
	$writer_command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($writer_file).' '.escapeshellarg($ready_file).' '.escapeshellarg($go_file);
	$writer = proc_open($writer_command, $descriptor, $writer_pipes);
	is_resource($writer) || fail('Unable to start state writer worker.');
	fclose($writer_pipes[0]);
	$deadline = microtime(TRUE) + 10;
	do {
		if(is_file($ready_file)) break;
		$status = proc_get_status($writer);
		if(!$status['running']) break;
		usleep(10000);
	} while(microtime(TRUE) < $deadline);
	is_file($ready_file) || fail('State writer did not expose its intermediate generation.');

	$reader_command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($reader_file).' '.escapeshellarg($result_file);
	$reader = proc_open($reader_command, $descriptor, $reader_pipes);
	is_resource($reader) || fail('Unable to start state reader worker.');
	fclose($reader_pipes[0]);
	usleep(200000);
	$reader_status = proc_get_status($reader);
	$reader_status['running'] && !is_file($result_file)
		|| fail('A concurrent request observed lifecycle intermediate plugin state instead of waiting.');
	file_put_contents($go_file, 'go') !== FALSE || fail('Unable to release state writer worker.');

	$workers = array(
		'writer'=>array('process'=>$writer, 'pipes'=>$writer_pipes, 'exitcode'=>NULL),
		'reader'=>array('process'=>$reader, 'pipes'=>$reader_pipes, 'exitcode'=>NULL),
	);
	foreach($workers as $name=>&$entry) {
		$deadline = microtime(TRUE) + 10;
		do {
			$status = proc_get_status($entry['process']);
			if(!$status['running']) {
				if($status['exitcode'] !== -1) $entry['exitcode'] = $status['exitcode'];
				break;
			}
			usleep(10000);
		} while(microtime(TRUE) < $deadline);
		$stdout = stream_get_contents($entry['pipes'][1]);
		$stderr = stream_get_contents($entry['pipes'][2]);
		fclose($entry['pipes'][1]);
		fclose($entry['pipes'][2]);
		$close_code = proc_close($entry['process']);
		$entry['exitcode'] === NULL AND $entry['exitcode'] = $close_code;
		$entry['exitcode'] === 0 || fail("State $name worker failed: ".$stdout.$stderr);
	}
	unset($entry);
	file_get_contents($result_file) === 'final-disabled'
		|| fail('State reader did not observe the final rolled-back generation after lifecycle completion.');
	remove_task_fixture($fixture);
} else {
	$skips[] = 'proc_open is unavailable; lifecycle intermediate-state concurrency was not exercised.';
}

// A frontend request may compile many fragments from one request-local enabled set. Once its first
// _include() has taken the shared state snapshot, a lifecycle writer must not cross between later
// fragments: otherwise that old request can publish a fresh-mtime cache from the pre-write index
// after the writer already cleared caches. The writer may commit only after the old request exits;
// a fresh request must then compile exclusively from the final disabled generation.
if(function_exists('proc_open')) {
	$request_fixture = $root.'/tmp/plugin_request_snapshot_'.bin2hex(random_bytes(6)).'/';
	@mkdir($request_fixture.'plugin/request_probe/hook/', 0777, TRUE);
	@mkdir($request_fixture.'tmp/', 0777, TRUE);
	@mkdir($request_fixture.'view/', 0777, TRUE);
	register_shutdown_function(function() use ($request_fixture) { remove_task_fixture($request_fixture); });

	$request_conf_file = $request_fixture.'plugin/request_probe/conf.json';
	$request_conf = array(
		'name'=>'request snapshot probe',
		'installed'=>1,
		'enable'=>1,
		'hooks_rank'=>array('request_snapshot_hook.htm'=>10),
		'overwrites_rank'=>array(),
	);
	file_put_contents($request_conf_file, json_encode($request_conf)) !== FALSE
		|| fail('Unable to create request-wide snapshot plugin fixture.');
	file_put_contents($request_fixture.'plugin/request_probe/hook/request_snapshot_hook.htm', 'REQUEST-SNAPSHOT-HOOK|') !== FALSE
		|| fail('Unable to create request-wide snapshot Hook fixture.');
	$request_source_a = $request_fixture.'view/request-a.htm';
	$request_source_b = $request_fixture.'view/request-b.htm';
	file_put_contents($request_source_a, "A|\n<!--{hook request_snapshot_hook.htm}-->\n|END") !== FALSE
		|| fail('Unable to create first request-wide snapshot source.');
	file_put_contents($request_source_b, "B|\n<!--{hook request_snapshot_hook.htm}-->\n|END") !== FALSE
		|| fail('Unable to create second request-wide snapshot source.');

	$request_reader_file = $request_fixture.'request-reader.php';
	$request_writer_file = $request_fixture.'request-writer.php';
	$request_reader_source = '<?php'."\n"
		.'define(\'DEBUG\', 0);'."\n"
		.'define(\'APP_PATH\', '.var_export($request_fixture, TRUE).');'."\n"
		.'define(\'XIUNOPHP_PATH\', '.var_export($root.'/xiunophp/', TRUE).');'."\n"
		.'include XIUNOPHP_PATH.\'array.func.php\';'."\n"
		.'include XIUNOPHP_PATH.\'misc.func.php\';'."\n"
		.'include '.var_export($root.'/model/plugin.func.php', TRUE).';'."\n"
		.'$conf = array(\'tmp_path\'=>'.var_export($request_fixture.'tmp/', TRUE).', \'disabled_plugin\'=>0);'."\n"
		.'$_SERVER[\'conf\'] = $conf;'."\n"
		.'$first = file_get_contents(_include($argv[1]));'."\n"
		.'$expect_hook = $argv[6] === \'old\';'."\n"
		.'if(((strpos($first, \'REQUEST-SNAPSHOT-HOOK|\') !== FALSE) !== $expect_hook)) { fwrite(STDERR, \'unexpected first fragment: \'.base64_encode((string)$first)); exit(2); }'."\n"
		.'file_put_contents($argv[3], \'ready\') !== FALSE || exit(3);'."\n"
		.'$deadline = microtime(TRUE) + 15;'."\n"
		.'while(!is_file($argv[4]) && microtime(TRUE) < $deadline) usleep(10000);'."\n"
		.'is_file($argv[4]) || exit(4);'."\n"
		.'$second = file_get_contents(_include($argv[2]));'."\n"
		.'file_put_contents($argv[5], $second) !== FALSE || exit(5);'."\n";
	$request_writer_source = '<?php'."\n"
		.'define(\'DEBUG\', 0);'."\n"
		.'define(\'APP_PATH\', '.var_export($request_fixture, TRUE).');'."\n"
		.'define(\'XIUNOPHP_PATH\', '.var_export($root.'/xiunophp/', TRUE).');'."\n"
		.'include XIUNOPHP_PATH.\'array.func.php\';'."\n"
		.'include XIUNOPHP_PATH.\'misc.func.php\';'."\n"
		.'include '.var_export($root.'/model/plugin.func.php', TRUE).';'."\n"
		.'$conf = array(\'tmp_path\'=>'.var_export($request_fixture.'tmp/', TRUE).', \'disabled_plugin\'=>0);'."\n"
		.'$_SERVER[\'conf\'] = $conf;'."\n"
		.'file_put_contents($argv[1], \'attempting\') !== FALSE || exit(2);'."\n"
		.'plugin_state_visibility_write_lock_start() || exit(3);'."\n"
		.'file_put_contents($argv[2], \'acquired\') !== FALSE || exit(4);'."\n"
		.'$state = json_decode(file_get_contents('.var_export($request_conf_file, TRUE).'), TRUE);'."\n"
		.'is_array($state) || exit(5);'."\n"
		.'$state[\'enable\'] = 0;'."\n"
		.'file_put_contents('.var_export($request_conf_file, TRUE).', json_encode($state)) !== FALSE || exit(6);'."\n"
		.'plugin_clear_tmp_dir() || exit(7);'."\n"
		.'file_put_contents($argv[3], \'committed\') !== FALSE || exit(8);'."\n"
		.'plugin_state_visibility_write_lock_end() || exit(9);'."\n";
	file_put_contents($request_reader_file, $request_reader_source) !== FALSE
		|| fail('Unable to create request-wide snapshot reader worker.');
	file_put_contents($request_writer_file, $request_writer_source) !== FALSE
		|| fail('Unable to create request-wide snapshot writer worker.');

	$request_descriptor = array(0=>array('pipe', 'r'), 1=>array('pipe', 'w'), 2=>array('pipe', 'w'));
	$finish_request_worker = function($process, $pipes, $name) {
		$exitcode = NULL;
		$deadline = microtime(TRUE) + 15;
		do {
			$status = proc_get_status($process);
			if(!$status['running']) {
				if($status['exitcode'] !== -1) $exitcode = $status['exitcode'];
				break;
			}
			usleep(10000);
		} while(microtime(TRUE) < $deadline);
		if($status['running']) proc_terminate($process);
		$stdout = stream_get_contents($pipes[1]);
		$stderr = stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		$close_code = proc_close($process);
		$exitcode === NULL AND $exitcode = $close_code;
		$exitcode === 0 || fail("Request snapshot $name worker failed: ".$stdout.$stderr);
	};

	$request_reader_ready = $request_fixture.'reader-ready';
	$request_reader_go = $request_fixture.'reader-go';
	$request_reader_result = $request_fixture.'reader-result';
	$request_reader_command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($request_reader_file)
		.' '.escapeshellarg($request_source_a).' '.escapeshellarg($request_source_b)
		.' '.escapeshellarg($request_reader_ready).' '.escapeshellarg($request_reader_go)
		.' '.escapeshellarg($request_reader_result).' old';
	$request_reader = proc_open($request_reader_command, $request_descriptor, $request_reader_pipes);
	is_resource($request_reader) || fail('Unable to start request-wide snapshot reader.');
	fclose($request_reader_pipes[0]);
	$request_deadline = microtime(TRUE) + 15;
	do {
		if(is_file($request_reader_ready)) break;
		$request_reader_status = proc_get_status($request_reader);
		if(!$request_reader_status['running']) break;
		usleep(10000);
	} while(microtime(TRUE) < $request_deadline);
	if(!is_file($request_reader_ready)) {
		$finish_request_worker($request_reader, $request_reader_pipes, 'old reader startup');
		fail('Request-wide snapshot reader did not hold its first generation.');
	}

	$request_writer_attempting = $request_fixture.'writer-attempting';
	$request_writer_acquired = $request_fixture.'writer-acquired';
	$request_writer_committed = $request_fixture.'writer-committed';
	$request_writer_command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($request_writer_file)
		.' '.escapeshellarg($request_writer_attempting).' '.escapeshellarg($request_writer_acquired)
		.' '.escapeshellarg($request_writer_committed);
	$request_writer = proc_open($request_writer_command, $request_descriptor, $request_writer_pipes);
	is_resource($request_writer) || fail('Unable to start request-wide snapshot writer.');
	fclose($request_writer_pipes[0]);
	$request_deadline = microtime(TRUE) + 15;
	do {
		if(is_file($request_writer_attempting)) break;
		$request_writer_status = proc_get_status($request_writer);
		if(!$request_writer_status['running']) break;
		usleep(10000);
	} while(microtime(TRUE) < $request_deadline);
	if(!is_file($request_writer_attempting)) {
		$finish_request_worker($request_writer, $request_writer_pipes, 'writer startup');
		fail('Request-wide snapshot writer did not attempt the exclusive lock.');
	}
	usleep(250000);
	$request_writer_status = proc_get_status($request_writer);
	$request_writer_status['running'] && !is_file($request_writer_acquired) && !is_file($request_writer_committed)
		|| fail('Lifecycle writer crossed a live request-wide plugin-state snapshot.');

	file_put_contents($request_reader_go, 'go') !== FALSE
		|| fail('Unable to release request-wide snapshot reader.');
	$finish_request_worker($request_reader, $request_reader_pipes, 'old reader');
	$old_request_result = file_get_contents($request_reader_result);
	strpos($old_request_result, 'REQUEST-SNAPSHOT-HOOK|') !== FALSE
		|| fail('The old request did not remain internally consistent through its second include.');
	$finish_request_worker($request_writer, $request_writer_pipes, 'writer');
	is_file($request_writer_acquired) && is_file($request_writer_committed)
		|| fail('Lifecycle writer did not commit after the old request released its snapshot.');
	$final_request_conf = json_decode(file_get_contents($request_conf_file), TRUE);
	is_array($final_request_conf) && empty($final_request_conf['enable'])
		|| fail('Lifecycle writer did not publish the final disabled plugin generation.');

	$fresh_reader_ready = $request_fixture.'fresh-reader-ready';
	$fresh_reader_result = $request_fixture.'fresh-reader-result';
	$fresh_reader_command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($request_reader_file)
		.' '.escapeshellarg($request_source_a).' '.escapeshellarg($request_source_b)
		.' '.escapeshellarg($fresh_reader_ready).' '.escapeshellarg($request_reader_go)
		.' '.escapeshellarg($fresh_reader_result).' final';
	$fresh_reader = proc_open($fresh_reader_command, $request_descriptor, $fresh_reader_pipes);
	is_resource($fresh_reader) || fail('Unable to start final-generation request reader.');
	fclose($fresh_reader_pipes[0]);
	$finish_request_worker($fresh_reader, $fresh_reader_pipes, 'fresh reader');
	$fresh_request_result = file_get_contents($fresh_reader_result);
	strpos($fresh_request_result, 'REQUEST-SNAPSHOT-HOOK|') === FALSE
		|| fail('A fresh request executed stale Hook bytes after lifecycle cache invalidation.');

	remove_task_fixture($request_fixture);
} else {
	$skips[] = 'proc_open is unavailable; request-snapshot/lifecycle concurrency was not exercised.';
}

echo "OK: plugin task lock safety checks passed for available fixtures\n";
foreach($skips as $skip) echo 'SKIP: '.$skip.PHP_EOL;
