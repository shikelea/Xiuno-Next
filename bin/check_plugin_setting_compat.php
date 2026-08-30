<?php

define('DEBUG', 2);
define('APP_PATH', dirname(__DIR__).'/');

$cache_store = array();
$db_kv_store = array();
$db_write_ok = TRUE;
$db_write_fail_keys = array();
$db_write_count = 0;
$db_write_keys = array();
$db_primary_read_ok = TRUE;
$plugin_setting_compat_logs = array();
$plugin_setting_message_responses = array();
$setting_lock_dir = APP_PATH.'tmp/xiuno_setting_lock_'.bin2hex(random_bytes(6));
if(!is_dir($setting_lock_dir) && !mkdir($setting_lock_dir, 0777, TRUE) && !is_dir($setting_lock_dir)) {
	fwrite(STDERR, "FAIL: could not create setting lock fixture directory\n");
	exit(1);
}
$conf = array('tmp_path'=>$setting_lock_dir.'/');

function array_value($array, $key, $default = NULL) {
	return is_array($array) && array_key_exists($key, $array) ? $array[$key] : $default;
}

function xn_json_encode($value) {
	return json_encode($value);
}

function xn_json_decode($value) {
	return json_decode($value, TRUE);
}

function file_put_contents_try($file, $contents) {
	return file_put_contents($file, $contents);
}

function file_get_contents_try($file) {
	return file_get_contents($file);
}

function xn_unlink($file) {
	return !is_file($file) || unlink($file);
}

function cache_get($key) {
	global $cache_store;
	return array_key_exists($key, $cache_store) ? $cache_store[$key] : NULL;
}

function cache_set($key, $value, $life = 0) {
	global $cache_store;
	$cache_store[$key] = $value;
	return TRUE;
}

function cache_delete($key) {
	global $cache_store;
	unset($cache_store[$key]);
	return TRUE;
}

function db_find_one($table, $cond) {
	global $db_kv_store;
	$key = array_value($cond, 'k', '');
	return isset($db_kv_store[$key]) ? $db_kv_store[$key] : NULL;
}

function db_find_one_master($table, $cond) {
	global $db_primary_read_ok;
	if(!$db_primary_read_ok) return FALSE;
	return db_find_one($table, $cond);
}

function db_replace($table, $row) {
	global $db_kv_store, $db_write_ok, $db_write_fail_keys, $db_write_count, $db_write_keys;
	$db_write_count++;
	isset($db_write_keys[$row['k']]) ? $db_write_keys[$row['k']]++ : $db_write_keys[$row['k']] = 1;
	if(!$db_write_ok || !empty($db_write_fail_keys[$row['k']])) return FALSE;
	$db_kv_store[$row['k']] = $row;
	return TRUE;
}

function db_delete($table, $cond) {
	global $db_kv_store;
	unset($db_kv_store[array_value($cond, 'k', '')]);
	return TRUE;
}

function fail($message) {
	fwrite(STDERR, "FAIL: $message\n");
	exit(1);
}

function plugin_lifecycle_log($message) {
	global $plugin_setting_compat_logs;
	$plugin_setting_compat_logs[] = $message;
}

final class PluginSettingResponseFixture extends Error {}

function message($code, $message, $extra = array()) {
	// Match the production message() ordering: the compatibility capture runs before a response is
	// emitted. During the package include it throws PluginSettingMessage; during the deferred replay
	// the request guard has been cleared, so this fixture records the one externally visible response.
	if(function_exists('plugin_setting_admin_request_capture_message')) {
		plugin_setting_admin_request_capture_message($code, $message, $extra);
	}
	$GLOBALS['plugin_setting_message_responses'][] = array($code, $message, $extra);
	throw new PluginSettingResponseFixture('message response');
}

function remove_fixture_tree($dir) {
	if(is_link($dir) || is_file($dir)) {
		@unlink($dir);
		return;
	}
	if(!is_dir($dir)) return;
	$items = scandir($dir);
	foreach($items as $item) {
		if($item === '.' || $item === '..') continue;
		$path = $dir.'/'.$item;
		(is_dir($path) && !is_link($path)) ? remove_fixture_tree($path) : @unlink($path);
	}
	@rmdir($dir);
}

function reset_setting_runtime($settings = array()) {
	global $cache_store, $db_kv_store, $db_write_ok, $db_write_fail_keys, $db_write_count, $db_write_keys, $db_primary_read_ok, $g_setting;
	$cache_store = array('setting'=>$settings);
	$db_kv_store = array('setting'=>array('k'=>'setting', 'v'=>xn_json_encode($settings)));
	$db_write_ok = TRUE;
	$db_write_fail_keys = array();
	$db_write_count = 0;
	$db_write_keys = array();
	$db_primary_read_ok = TRUE;
	$g_setting = FALSE;
}

function reset_request_runtime_preserving_db() {
	global $cache_store, $db_kv_store, $db_write_ok, $db_write_fail_keys, $db_write_count, $db_write_keys, $db_primary_read_ok, $g_setting;
	$settings = isset($db_kv_store['setting']) ? xn_json_decode($db_kv_store['setting']['v']) : array();
	$cache_store = array('setting'=>is_array($settings) ? $settings : array());
	$db_write_ok = TRUE;
	$db_write_fail_keys = array();
	$db_write_count = 0;
	$db_write_keys = array();
	$db_primary_read_ok = TRUE;
	$g_setting = FALSE;
	reset_plugin_setting_compat();
}

function db_write_count_for($key) {
	global $db_write_keys;
	return isset($db_write_keys[$key]) ? $db_write_keys[$key] : 0;
}

function sidecar_value() {
	global $db_kv_store;
	$settings = isset($db_kv_store['setting']) ? xn_json_decode($db_kv_store['setting']['v']) : array();
	$key = plugin_setting_schema_setting_key();
	if(is_array($settings) && array_key_exists($key, $settings)) return $settings[$key];
	$legacy_key = plugin_setting_schema_sidecar_kv_key();
	return isset($db_kv_store[$legacy_key]) ? xn_json_decode($db_kv_store[$legacy_key]['v']) : NULL;
}

function setting_user_values() {
	global $db_kv_store;
	$settings = isset($db_kv_store['setting']) ? xn_json_decode($db_kv_store['setting']['v']) : array();
	if(!is_array($settings)) return array();
	unset($settings[plugin_setting_schema_setting_key()]);
	return $settings;
}

function reset_plugin_setting_compat() {
	global $g_plugin_setting_schema_defaults, $g_plugin_setting_schema_registry, $g_plugin_setting_schema_keys_by_dir;
	global $g_plugin_setting_schema_candidates_by_dir, $g_plugin_setting_schema_conflict_logged;
	global $g_plugin_setting_capture_stack, $g_plugin_setting_capture_serial, $g_plugin_setting_admin_request;
	$g_plugin_setting_schema_defaults = array();
	$g_plugin_setting_schema_registry = array();
	$g_plugin_setting_schema_keys_by_dir = array();
	$g_plugin_setting_schema_candidates_by_dir = array();
	$g_plugin_setting_schema_conflict_logged = array();
	$g_plugin_setting_capture_stack = array();
	$g_plugin_setting_capture_serial = 0;
	$g_plugin_setting_admin_request = NULL;
}

require_once APP_PATH.'model/plugin.func.php';
require_once APP_PATH.'model/kv.func.php';
register_shutdown_function(function() use ($setting_lock_dir) { remove_fixture_tree($setting_lock_dir); });

$writes_before_invalid_json = $db_write_count;
$invalid_resource = fopen('php://memory', 'rb');
kv_set('invalid-resource', $invalid_resource) === FALSE || fail('kv_set must reject resources that JSON cannot encode');
fclose($invalid_resource);
$recursive = array();
$recursive['self'] = &$recursive;
kv_set('invalid-recursion', $recursive) === FALSE || fail('kv_set must reject recursive values that JSON cannot encode');
kv_set('invalid-utf8', "\xB1\x31") === FALSE || fail('kv_set must reject invalid UTF-8 instead of publishing invalid JSON');
$db_write_count === $writes_before_invalid_json || fail('invalid JSON values must be rejected before db_replace');
kv_set('valid-false', FALSE) !== FALSE || fail('kv_set must preserve a valid false JSON scalar');
kv_set('valid-null', NULL) !== FALSE || fail('kv_set must preserve a valid null JSON scalar');
xn_json_decode($db_kv_store['valid-false']['v']) === FALSE || fail('valid false JSON did not round-trip');
array_key_exists('valid-null', $db_kv_store) && $db_kv_store['valid-null']['v'] === 'null' || fail('valid null JSON did not round-trip');
$cache_store = array();
$db_write_ok = FALSE;
kv_cache_set('failed-cache-publication', array('value'=>1)) === FALSE || fail('kv_cache_set must expose a failed durable write');
array_key_exists('failed-cache-publication', $cache_store) && fail('kv_cache_set must not publish a value before the durable write succeeds');
$db_write_ok = TRUE;
kv_cache_set('valid-cache-publication', array('value'=>1)) !== FALSE || fail('kv_cache_set valid fixture should succeed');
isset($cache_store['valid-cache-publication']['value']) && $cache_store['valid-cache-publication']['value'] === 1 || fail('kv_cache_set must publish after the durable write succeeds');

$schema = array(
	'panels'=>array(
		'ui'=>array(
			'sections'=>array(
				'color'=>array(
					'options'=>array(
						'theme'=>array('default'=>'#696cff'),
						'body'=>array('default'=>'#697a8d'),
						'enabled'=>array('default'=>FALSE),
						'weight'=>array('default'=>0),
						'label'=>array('default'=>'default label'),
						'typography'=>array('default'=>array('family'=>'sans', 'size'=>16)),
						'choices'=>array('default'=>array('one', 'two')),
						'implicit_zero'=>array(),
					),
				),
			),
		),
	),
	'kumquat_flag'=>array('reset_settings'=>0),
);

$defaults = plugin_setting_schema_defaults($schema);
$defaults['ui']['color']['body'] === '#697a8d' || fail('schema defaults must preserve nested option defaults');
$defaults['ui']['color']['implicit_zero'] === 0 || fail('schema options without explicit defaults must use the legacy zero fallback');

$saved = array(
	'ui'=>array(
		'color'=>array(
			'theme'=>'#123456',
			'enabled'=>FALSE,
			'weight'=>0,
			'label'=>'',
			'typography'=>array('size'=>18),
			'choices'=>array('two'),
		),
	),
	'custom'=>array('kept'=>TRUE),
);
$merged = plugin_setting_merge_defaults($defaults, $saved);
$merged['ui']['color']['theme'] === '#123456' || fail('saved scalar values must override defaults');
$merged['ui']['color']['body'] === '#697a8d' || fail('missing nested values must be restored from schema defaults');
$merged['ui']['color']['enabled'] === FALSE || fail('explicit false values must not be treated as missing');
$merged['ui']['color']['weight'] === 0 || fail('explicit zero values must not be treated as missing');
$merged['ui']['color']['label'] === '' || fail('explicit empty strings must not be treated as missing');
$merged['ui']['color']['typography'] === array('family'=>'sans', 'size'=>18) || fail('associative option values must merge missing nested defaults');
$merged['ui']['color']['choices'] === array('two') || fail('saved list values must replace default lists');
$merged['custom']['kept'] === TRUE || fail('package-defined keys outside the schema must be preserved');
$empty_associative_saved = plugin_setting_merge_defaults($defaults, array());
$empty_associative_saved === $defaults || fail('an empty saved associative container must receive every schema default');
plugin_setting_merge_defaults(array('one', 'two'), array()) === array() || fail('an empty saved list must remain an explicit clear when the schema default is a list');
plugin_setting_merge_defaults(array('nested'=>array('enabled'=>TRUE)), array('legacy-list-value')) === array('legacy-list-value')
	|| fail('a non-empty saved list with a mismatched associative schema must remain an opaque package value');

// The real setting API must capture reads and only successful writes while a wrapper is active.
reset_setting_runtime(array('custom_theme_options'=>$saved));
reset_plugin_setting_compat();
$token = plugin_setting_capture_begin('demo-theme');
setting_get('custom_theme_options');
setting_set('custom_theme_options', $saved) || fail('setting_set fixture should succeed');
$capture = plugin_setting_capture_end($token);
$capture['reads'] === array('custom_theme_options') || fail('setting_get/raw must capture the actual key once');
$capture['writes'] === array('custom_theme_options') || fail('successful setting_set must capture the actual key');
plugin_setting_schema_bind_plugin('demo-theme', $schema, $capture) === 'custom_theme_options' || fail('schema must bind to the key actually read and written by the package');
plugin_setting_schema_persist_plugin('demo-theme') || fail('registered plugin defaults could not be persisted');
setting_get_raw('custom_theme_options') === $merged || fail('persisted settings must equal the deep normalized result');
setting_get_raw('demo-theme_setting') === NULL || fail('an observed custom key must not create the legacy dir_setting guess');

$token = plugin_setting_capture_begin('failed-write');
$db_write_ok = FALSE;
setting_set('failed_write_key', array('x'=>1)) === FALSE || fail('failed setting_set fixture should report failure');
$failed_capture = plugin_setting_capture_end($token);
$failed_capture['writes'] === array() || fail('failed setting_set must not be recorded as a successful package save');
$db_write_ok = TRUE;

// The setting row is shared by every setting key. A writer must reload it under the write lock
// instead of overwriting an unrelated concurrent change with its request-local snapshot.
reset_setting_runtime(array('alpha'=>1, 'beta'=>1, 'remove_me'=>TRUE));
setting_get_raw('alpha');
$db_kv_store['setting']['v'] = xn_json_encode(array('alpha'=>1, 'beta'=>2, 'remove_me'=>TRUE));
$cache_store['setting'] = array('alpha'=>1, 'beta'=>2, 'remove_me'=>TRUE);
setting_set('alpha', 2) !== FALSE || fail('locked setting_set fixture should succeed');
xn_json_decode($db_kv_store['setting']['v']) === array('alpha'=>2, 'beta'=>2, 'remove_me'=>TRUE)
	|| fail('setting_set must preserve an unrelated key written after the request snapshot was read');
$db_kv_store['setting']['v'] = xn_json_encode(array('alpha'=>3, 'beta'=>2, 'remove_me'=>TRUE));
$cache_store['setting'] = array('alpha'=>3, 'beta'=>2, 'remove_me'=>TRUE);
setting_delete('remove_me') || fail('locked setting_delete fixture should succeed');
xn_json_decode($db_kv_store['setting']['v']) === array('alpha'=>3, 'beta'=>2)
	|| fail('setting_delete must preserve unrelated concurrent updates');

// Key resolution: explicit declaration wins; only actual, unambiguous observations may infer a key.
$explicit_schema = $schema;
$explicit_schema['setting_key'] = 'declared_schema_key';
plugin_setting_schema_resolve_key('demo-theme', $explicit_schema, array('reads'=>array('other'), 'writes'=>array('other'))) === 'declared_schema_key' || fail('explicit schema key must override observed keys');
plugin_setting_schema_resolve_key('demo-theme', $schema, array('reads'=>array('global_key', 'actual_key'), 'writes'=>array('actual_key'))) === 'actual_key' || fail('the unique read/write intersection must disambiguate pages that inspect other settings');
plugin_setting_schema_resolve_key('demo-theme', $schema, array('reads'=>array('one', 'two'), 'writes'=>array())) === FALSE || fail('ambiguous observed keys must not be guessed');
plugin_setting_schema_resolve_key('demo-theme', $schema, array('reads'=>array('global_key'), 'writes'=>array('actual_key'))) === FALSE || fail('disjoint read/write keys must fail closed without an explicit schema key');
plugin_setting_schema_resolve_key('demo-theme', $schema, array('reads'=>array('one', 'demo-theme_setting'), 'writes'=>array())) === FALSE || fail('an observed conventional key must not override another observed key');
plugin_setting_schema_resolve_key('demo-theme', $schema, array('reads'=>array('demo-theme_setting'), 'writes'=>array())) === 'demo-theme_setting' || fail('a uniquely observed conventional key remains valid evidence');
plugin_setting_schema_resolve_key('demo-theme', $schema, array('reads'=>array(), 'writes'=>array())) === FALSE || fail('an unobserved schema must not guess a dir_setting key');
$invalid_explicit = $schema;
$invalid_explicit['setting_key'] = "bad\nkey";
plugin_setting_schema_resolve_key('demo-theme', $invalid_explicit, array()) === FALSE || fail('an invalid explicit key must fail closed instead of falling back');
plugin_setting_schema_bind_plugin('../outside', $schema, array()) === FALSE || fail('unsafe plugin directory names must be rejected');
plugin_setting_schema_bind_plugin('empty_schema', array(), array()) === FALSE || fail('schemas without supported panels must be ignored');
$unsupported_schema = $schema;
$unsupported_schema['setting_key'] = 'unsupported_schema_key';
$unsupported_schema['panels']['ui']['sections']['color']['options']['body']['default'] = function() {};
plugin_setting_schema_bind_plugin('unsupported-schema', $unsupported_schema, array()) === FALSE || fail('unserializable schema defaults must fail closed without throwing');
$resource_schema = $schema;
$resource_schema['setting_key'] = 'resource_schema_key';
$resource_default = fopen('php://memory', 'rb');
$resource_schema['panels']['ui']['sections']['color']['options']['body']['default'] = $resource_default;
plugin_setting_schema_bind_plugin('resource-schema', $resource_schema, array()) === FALSE || fail('schema defaults that cannot survive JSON persistence must fail closed');
fclose($resource_default);

reset_setting_runtime(array());
reset_plugin_setting_compat();
plugin_setting_schema_bind_plugin('unobserved-demo', $schema, array()) === FALSE || fail('an unobserved schema must not bind a guessed key');
plugin_setting_schema_persist_plugin('unobserved-demo') || fail('an unbound schema should remain a persistence no-op');
setting_get_raw('unobserved-demo_setting') === NULL || fail('an unobserved schema must not manufacture a guessed KV entry');
$db_write_count === 0 || fail('an unobserved schema must not perform a KV write');

// A global setting key may be shared only when every owner registers identical defaults. Any
// defaults drift makes the key inert for reads and persistence for the rest of the request.
$shared_schema = $schema;
$shared_schema['setting_key'] = 'shared_schema_key';
reset_setting_runtime(array('shared_schema_key'=>$saved));
reset_plugin_setting_compat();
plugin_setting_schema_bind_plugin('shared-owner-a', $shared_schema, array()) === 'shared_schema_key' || fail('first shared schema owner must bind');
plugin_setting_schema_bind_plugin('shared-owner-b', $shared_schema, array()) === 'shared_schema_key' || fail('identical shared schema owner must bind idempotently');
empty($g_plugin_setting_schema_registry['shared_schema_key']['conflict']) || fail('identical defaults fingerprints must not conflict');
isset($g_plugin_setting_schema_registry['shared_schema_key']['owners']['shared-owner-a']) || fail('first shared schema owner must be recorded');
isset($g_plugin_setting_schema_registry['shared_schema_key']['owners']['shared-owner-b']) || fail('second shared schema owner must be recorded');
count($g_plugin_setting_schema_registry['shared_schema_key']['fingerprints']) === 1 || fail('identical shared schemas must retain one defaults fingerprint');
plugin_setting_schema_persist_plugin('shared-owner-a') || fail('an identical shared schema must remain persistable');
setting_get_raw('shared_schema_key') === $merged || fail('identical shared defaults must normalize the saved value');

$conflicting_schema = $shared_schema;
$conflicting_schema['panels']['ui']['sections']['color']['options']['body']['default'] = '#ff0000';
reset_setting_runtime(array('shared_schema_key'=>$saved));
reset_plugin_setting_compat();
$plugin_setting_compat_logs = array();
plugin_setting_schema_bind_plugin('conflict-owner-a', $shared_schema, array()) === 'shared_schema_key' || fail('first conflicting schema owner must initially bind');
plugin_setting_schema_bind_plugin('conflict-owner-b', $conflicting_schema, array()) === FALSE || fail('a different defaults fingerprint must conflict instead of replacing the first owner');
!empty($g_plugin_setting_schema_registry['shared_schema_key']['conflict']) || fail('different shared defaults must mark the key conflicted');
count($g_plugin_setting_schema_registry['shared_schema_key']['fingerprints']) === 2 || fail('a schema conflict must retain both fingerprints for diagnosis');
count($plugin_setting_compat_logs) === 1 || fail('a new schema conflict must emit one diagnostic');
strpos($plugin_setting_compat_logs[0], 'key=shared_schema_key') !== FALSE || fail('schema conflict diagnostic must identify the setting key');
strpos($plugin_setting_compat_logs[0], 'conflict-owner-a,conflict-owner-b') !== FALSE || fail('schema conflict diagnostic must identify every known owner');
setting_get('shared_schema_key') === $saved || fail('a conflicted key read must return the raw saved value');
$setting_writes_before_conflict_persist = db_write_count_for('setting');
plugin_setting_schema_persist_plugin('conflict-owner-a') === FALSE || fail('the first owner must not persist after a later schema conflict');
plugin_setting_schema_persist_plugin('conflict-owner-b') === FALSE || fail('the conflicting owner must not persist defaults');
db_write_count_for('setting') === $setting_writes_before_conflict_persist + 2
	|| fail('each conflicted owner must publish diagnostic ownership with one atomic setting-row write and perform no value normalization; before='.
		$setting_writes_before_conflict_persist.' after='.db_write_count_for('setting'));
setting_get_raw('shared_schema_key') === $saved || fail('a conflicted key must remain byte-for-byte equivalent to the raw saved value');
$conflict_sidecar = plugin_setting_schema_sidecar_normalize(sidecar_value());
!empty($conflict_sidecar['keys']['shared_schema_key']['conflict']) || fail('an observed schema conflict must be persisted in the embedded registry');

reset_setting_runtime(array('shared_schema_key'=>$saved));
reset_plugin_setting_compat();
plugin_setting_schema_bind_plugin('drifting-owner', $shared_schema, array()) === 'shared_schema_key' || fail('same-owner drift fixture must bind its first schema');
plugin_setting_schema_bind_plugin('drifting-owner', $conflicting_schema, array()) === FALSE || fail('same-owner defaults drift must conflict within one request');
plugin_setting_schema_persist_plugin('drifting-owner') === FALSE || fail('same-owner defaults drift must disable persistence');
setting_get('shared_schema_key') === $saved || fail('same-owner defaults drift must disable virtual defaults');

// Persistent ownership is the production boundary: plugin settings normally execute in separate
// PHP requests, so the embedded registry must retain owners/fingerprints after request resets.
reset_setting_runtime(array('shared_schema_key'=>$saved));
reset_plugin_setting_compat();
plugin_setting_schema_bind_plugin('persistent-owner-a', $shared_schema, array()) === 'shared_schema_key' || fail('first persistent owner must bind');
plugin_setting_schema_persist_plugin('persistent-owner-a') || fail('first persistent owner must register and normalize');
db_write_count_for(plugin_setting_schema_sidecar_kv_key()) === 0 || fail('new persistence must not write the legacy independent sidecar row');
db_write_count_for('setting') === 1 || fail('first successful persistence must atomically normalize value and metadata in one setting-row write');

reset_request_runtime_preserving_db();
plugin_setting_schema_bind_plugin('persistent-owner-b', $shared_schema, array()) === 'shared_schema_key' || fail('an identical owner in a later request must remain compatible');
$db_write_count === 0 || fail('read-only schema binding in a later request must not write metadata or settings');
plugin_setting_schema_persist_plugin('persistent-owner-b') || fail('an identical later owner must persist successfully');
db_write_count_for(plugin_setting_schema_sidecar_kv_key()) === 0 || fail('the successful later owner must not recreate the legacy sidecar row');
db_write_count_for('setting') === 1 || fail('the later owner metadata must be recorded by one atomic setting-row write without changing its normalized value');
$identical_sidecar = plugin_setting_schema_sidecar_normalize(sidecar_value());
empty($identical_sidecar['keys']['shared_schema_key']['conflict']) || fail('identical cross-request owners must not conflict');
isset($identical_sidecar['keys']['shared_schema_key']['owners']['persistent-owner-a']) || fail('the first cross-request owner must remain in sidecar metadata');
isset($identical_sidecar['keys']['shared_schema_key']['owners']['persistent-owner-b']) || fail('the later identical owner must be added to sidecar metadata');

// A different owner/fingerprint discovered in a later request persists a sticky conflict in the
// same row while leaving every package-owned setting value unchanged.
reset_setting_runtime(array('shared_schema_key'=>$saved));
reset_plugin_setting_compat();
plugin_setting_schema_bind_plugin('cross-owner-a', $shared_schema, array()) === 'shared_schema_key' || fail('cross-request conflict fixture owner A must bind');
plugin_setting_schema_persist_plugin('cross-owner-a') || fail('cross-request conflict fixture owner A must persist');
$setting_after_owner_a = setting_user_values();
reset_request_runtime_preserving_db();
$plugin_setting_compat_logs = array();
plugin_setting_schema_bind_plugin('cross-owner-b', $conflicting_schema, array()) === FALSE || fail('different defaults in a later request must fail closed during binding');
$db_write_count === 0 || fail('cross-request conflict discovery must remain read-only before the success boundary');
plugin_setting_schema_persist_plugin('cross-owner-b') === FALSE || fail('different defaults in a later request must fail persistence');
db_write_count_for('setting') === 1 || fail('cross-request conflict metadata must be recorded by one atomic setting-row write');
db_write_count_for(plugin_setting_schema_sidecar_kv_key()) === 0 || fail('cross-request conflict must not write the legacy sidecar row');
setting_user_values() === $setting_after_owner_a || fail('cross-request conflict must leave normalized package values byte-for-byte unchanged');
$cross_conflict_sidecar = plugin_setting_schema_sidecar_normalize(sidecar_value());
!empty($cross_conflict_sidecar['keys']['shared_schema_key']['conflict']) || fail('cross-request owner conflict must remain sticky in sidecar metadata');
isset($cross_conflict_sidecar['keys']['shared_schema_key']['owners']['cross-owner-a']) || fail('cross-request conflict metadata must retain owner A');
isset($cross_conflict_sidecar['keys']['shared_schema_key']['owners']['cross-owner-b']) || fail('cross-request conflict metadata must retain owner B');

reset_request_runtime_preserving_db();
plugin_setting_schema_bind_plugin('cross-owner-a', $shared_schema, array()) === FALSE || fail('a persisted conflict must remain visible in every later request');
setting_get('shared_schema_key') === $setting_after_owner_a['shared_schema_key'] || fail('persisted conflict must disable virtual defaults in later requests');
$db_write_count === 0 || fail('reading a persisted conflict must remain write-free');

// Successful uninstall owns metadata cleanup, not package setting-data deletion. Removing the
// conflicting owner must make the remaining single contract usable again; removing the last owner
// must delete only the sidecar entry and preserve the package's saved setting value.
plugin_setting_schema_unbind_plugin('cross-owner-b') || fail('uninstall must detach a durable schema owner');
$unbound_conflict_sidecar = plugin_setting_schema_sidecar_normalize(sidecar_value());
isset($unbound_conflict_sidecar['keys']['shared_schema_key']['owners']['cross-owner-a'])
	|| fail('schema owner cleanup removed the remaining package owner');
empty($unbound_conflict_sidecar['keys']['shared_schema_key']['owners']['cross-owner-b'])
	|| fail('schema owner cleanup retained the uninstalled package owner');
empty($unbound_conflict_sidecar['keys']['shared_schema_key']['conflict'])
	|| fail('removing the conflicting owner must recompute and clear the resolved conflict');
$setting_after_conflict_unbind = xn_json_decode($db_kv_store['setting']['v']);
$setting_after_conflict_unbind['shared_schema_key'] === $setting_after_owner_a['shared_schema_key']
	|| fail('schema owner cleanup must not delete or rewrite the package setting value');

reset_request_runtime_preserving_db();
plugin_setting_schema_bind_plugin('cross-owner-a', $shared_schema, array()) === 'shared_schema_key'
	|| fail('the remaining owner must become usable after the conflicting package is uninstalled');
plugin_setting_schema_unbind_plugin('cross-owner-a') || fail('uninstall must detach the final schema owner');
$unbound_last_sidecar = plugin_setting_schema_sidecar_normalize(sidecar_value());
empty($unbound_last_sidecar['keys']['shared_schema_key'])
	|| fail('removing the final owner must remove the empty sidecar key');
$setting_after_last_unbind = xn_json_decode($db_kv_store['setting']['v']);
$setting_after_last_unbind['shared_schema_key'] === $setting_after_owner_a['shared_schema_key']
	|| fail('removing the final owner must still preserve the package setting value');

// One owner may have environment-derived defaults (host, language, current year). A read-only
// request may use the current candidate virtually but must not mutate durable ownership. At the
// next verified success boundary, that owner's old fingerprint is replaced rather than accumulated.
$drift_schema = $shared_schema;
$drift_schema['setting_key'] = 'persistent_drift_key';
$drift_changed_schema = $conflicting_schema;
$drift_changed_schema['setting_key'] = 'persistent_drift_key';
reset_setting_runtime(array('persistent_drift_key'=>$saved));
reset_plugin_setting_compat();
plugin_setting_schema_bind_plugin('persistent-drift-owner', $drift_schema, array()) === 'persistent_drift_key' || fail('persistent drift fixture must bind its initial schema');
plugin_setting_schema_persist_plugin('persistent-drift-owner') || fail('persistent drift fixture must persist its initial schema');
$initial_drift_sidecar = plugin_setting_schema_sidecar_normalize(sidecar_value());
$initial_drift_fingerprint = key($initial_drift_sidecar['keys']['persistent_drift_key']['owners']['persistent-drift-owner']);
reset_request_runtime_preserving_db();
$drift_setting_row = xn_json_decode($db_kv_store['setting']['v']);
$drift_setting_row['persistent_drift_key'] = $saved;
$db_kv_store['setting']['v'] = xn_json_encode($drift_setting_row);
$cache_store['setting'] = $drift_setting_row;
$g_setting = FALSE;
plugin_setting_schema_bind_plugin('persistent-drift-owner', $drift_changed_schema, array()) === 'persistent_drift_key' || fail('single-owner drift must remain usable in a later read-only request');
setting_get('persistent_drift_key')['ui']['color']['body'] === '#ff0000' || fail('single-owner drift must expose the current request candidate virtually');
$db_write_count === 0 || fail('read-only single-owner drift must not write sidecar metadata or settings');
$read_only_drift_sidecar = plugin_setting_schema_sidecar_normalize(sidecar_value());
isset($read_only_drift_sidecar['keys']['persistent_drift_key']['owners']['persistent-drift-owner'][$initial_drift_fingerprint])
	|| fail('read-only single-owner drift must leave the durable fingerprint unchanged');

reset_request_runtime_preserving_db();
plugin_setting_schema_bind_plugin('persistent-drift-owner', $drift_changed_schema, array()) === 'persistent_drift_key' || fail('single-owner drift must bind at the later success boundary');
plugin_setting_schema_persist_plugin('persistent-drift-owner') || fail('single-owner drift must replace durable ownership at a verified success boundary');
db_write_count_for(plugin_setting_schema_sidecar_kv_key()) === 0 || fail('single-owner drift replacement must not update the legacy sidecar row');
db_write_count_for('setting') === 1 || fail('single-owner drift must replace metadata and normalize the sparse value in one atomic setting-row write');
$drift_sidecar = plugin_setting_schema_sidecar_normalize(sidecar_value());
empty($drift_sidecar['keys']['persistent_drift_key']['conflict']) || fail('single-owner drift replacement must not create a sticky conflict');
count($drift_sidecar['keys']['persistent_drift_key']['owners']['persistent-drift-owner']) === 1 || fail('single-owner drift must replace, not accumulate, its old fingerprint');
$changed_drift_fingerprint = plugin_setting_schema_defaults_fingerprint(plugin_setting_schema_defaults($drift_changed_schema));
isset($drift_sidecar['keys']['persistent_drift_key']['owners']['persistent-drift-owner'][$changed_drift_fingerprint]) || fail('single-owner drift sidecar must contain the current fingerprint');
!isset($drift_sidecar['keys']['persistent_drift_key']['owners']['persistent-drift-owner'][$initial_drift_fingerprint]) || fail('single-owner drift sidecar must remove the superseded fingerprint');

$legacy_drift_schema = $drift_changed_schema;
$legacy_drift_schema['setting_key'] = 'legacy_single_owner_key';
reset_setting_runtime(array('legacy_single_owner_key'=>$saved));
$legacy_entry = plugin_setting_schema_registry_entry_empty();
plugin_setting_schema_registry_entry_add($legacy_entry, 'legacy-single-owner', $initial_drift_fingerprint) || fail('could not build legacy same-owner drift fixture');
plugin_setting_schema_registry_entry_add($legacy_entry, 'legacy-single-owner', $changed_drift_fingerprint) || fail('could not add legacy same-owner drift fingerprint');
$sidecar_key = plugin_setting_schema_sidecar_kv_key();
$db_kv_store[$sidecar_key] = array('k'=>$sidecar_key, 'v'=>xn_json_encode(array('version'=>1, 'keys'=>array('legacy_single_owner_key'=>$legacy_entry))));
reset_request_runtime_preserving_db();
plugin_setting_schema_bind_plugin('legacy-single-owner', $legacy_drift_schema, array()) === 'legacy_single_owner_key' || fail('a legacy single-owner multi-fingerprint record must be virtually repairable');
$db_write_count === 0 || fail('read-only legacy single-owner repair must remain write-free');
plugin_setting_schema_persist_plugin('legacy-single-owner') || fail('a verified success boundary must repair legacy single-owner drift metadata');
$legacy_repaired_sidecar = plugin_setting_schema_sidecar_normalize(sidecar_value());
empty($legacy_repaired_sidecar['keys']['legacy_single_owner_key']['conflict']) || fail('legacy single-owner repair must clear the false sticky conflict');
count($legacy_repaired_sidecar['keys']['legacy_single_owner_key']['owners']['legacy-single-owner']) === 1 || fail('legacy single-owner repair must retain only the current fingerprint');

// Once another owner shares the key, one owner's drift is ambiguous and must become a sticky
// cross-owner conflict rather than replacing the common contract silently.
$multi_drift_schema = $shared_schema;
$multi_drift_schema['setting_key'] = 'multi_owner_drift_key';
$multi_drift_changed_schema = $conflicting_schema;
$multi_drift_changed_schema['setting_key'] = 'multi_owner_drift_key';
reset_setting_runtime(array('multi_owner_drift_key'=>$saved));
reset_plugin_setting_compat();
plugin_setting_schema_bind_plugin('multi-drift-owner-a', $multi_drift_schema, array()) === 'multi_owner_drift_key' || fail('multi-owner drift fixture owner A must bind');
plugin_setting_schema_persist_plugin('multi-drift-owner-a') || fail('multi-owner drift fixture owner A must persist');
reset_request_runtime_preserving_db();
plugin_setting_schema_bind_plugin('multi-drift-owner-b', $multi_drift_schema, array()) === 'multi_owner_drift_key' || fail('multi-owner drift fixture owner B must bind');
plugin_setting_schema_persist_plugin('multi-drift-owner-b') || fail('multi-owner drift fixture owner B must persist');
reset_request_runtime_preserving_db();
plugin_setting_schema_bind_plugin('multi-drift-owner-a', $multi_drift_changed_schema, array()) === FALSE || fail('one owner drifting away from another owner must fail closed during binding');
$db_write_count === 0 || fail('read-only multi-owner drift detection must remain write-free');
plugin_setting_schema_persist_plugin('multi-drift-owner-a') === FALSE || fail('multi-owner drift must fail persistence');
db_write_count_for('setting') === 1 || fail('multi-owner drift conflict metadata must use one atomic setting-row write without normalizing the package value');
db_write_count_for(plugin_setting_schema_sidecar_kv_key()) === 0 || fail('multi-owner drift conflict must not recreate the legacy sidecar row');
$multi_drift_sidecar = plugin_setting_schema_sidecar_normalize(sidecar_value());
!empty($multi_drift_sidecar['keys']['multi_owner_drift_key']['conflict']) || fail('multi-owner drift conflict must remain sticky');
count($multi_drift_sidecar['keys']['multi_owner_drift_key']['fingerprints']) === 2 || fail('multi-owner drift conflict must retain both owner fingerprints');

// Simulate another worker committing sidecar ownership after this request binds but before it
// persists. The locked persistence path must re-read that state, record the conflict, and skip the
// setting row rather than trusting its stale request-local registry.
$concurrent_schema = $shared_schema;
$concurrent_schema['setting_key'] = 'concurrent_schema_key';
$concurrent_other_schema = $conflicting_schema;
$concurrent_other_schema['setting_key'] = 'concurrent_schema_key';
reset_setting_runtime(array('concurrent_schema_key'=>$saved));
reset_plugin_setting_compat();
plugin_setting_schema_bind_plugin('concurrent-owner-a', $concurrent_schema, array()) === 'concurrent_schema_key' || fail('concurrent fixture owner A must bind before the simulated race');
$other_defaults = plugin_setting_schema_defaults($concurrent_other_schema);
$other_fingerprint = plugin_setting_schema_defaults_fingerprint($other_defaults);
$other_entry = plugin_setting_schema_registry_entry_empty();
plugin_setting_schema_registry_entry_add($other_entry, 'concurrent-owner-b', $other_fingerprint) || fail('could not build concurrent sidecar fixture');
$sidecar_key = plugin_setting_schema_sidecar_kv_key();
$db_kv_store[$sidecar_key] = array('k'=>$sidecar_key, 'v'=>xn_json_encode(array('version'=>1, 'keys'=>array('concurrent_schema_key'=>$other_entry))));
plugin_setting_schema_persist_plugin('concurrent-owner-a') === FALSE || fail('locked persistence must detect sidecar ownership committed after request-local binding');
db_write_count_for('setting') === 1 || fail('a concurrently discovered legacy conflict must migrate metadata with one atomic setting-row write');
db_write_count_for($sidecar_key) === 0 || fail('a concurrently discovered conflict must not rewrite the legacy sidecar row');
$concurrent_sidecar = plugin_setting_schema_sidecar_normalize(sidecar_value());
!empty($concurrent_sidecar['keys']['concurrent_schema_key']['conflict']) || fail('concurrently discovered conflict must remain sticky');
isset($concurrent_sidecar['keys']['concurrent_schema_key']['owners']['concurrent-owner-a']) || fail('concurrent conflict metadata must include stale request owner A');
isset($concurrent_sidecar['keys']['concurrent_schema_key']['owners']['concurrent-owner-b']) || fail('concurrent conflict metadata must include committed owner B');

// A later package save to the same key must remain authoritative. Compatibility normalization
// reloads and merges the latest whole setting row instead of writing the request-local snapshot.
$fresh_schema = $shared_schema;
$fresh_schema['setting_key'] = 'fresh_setting_key';
$stale_saved = $saved;
$stale_saved['ui']['color']['theme'] = '#111111';
$later_saved = $saved;
$later_saved['ui']['color']['theme'] = '#abcdef';
$later_saved['concurrent'] = array('kept'=>TRUE);
reset_setting_runtime(array('fresh_setting_key'=>$stale_saved));
reset_plugin_setting_compat();
plugin_setting_schema_bind_plugin('fresh-setting-owner', $fresh_schema, array()) === 'fresh_setting_key' || fail('fresh setting fixture must bind');
setting_get_raw('fresh_setting_key') === $stale_saved || fail('fresh setting fixture must prime the request-local stale snapshot');
$db_kv_store['setting']['v'] = xn_json_encode(array('fresh_setting_key'=>$later_saved));
$cache_store['setting'] = array('fresh_setting_key'=>$later_saved);
plugin_setting_schema_persist_plugin('fresh-setting-owner') || fail('fresh setting persistence should succeed');
$fresh_normalized = setting_get_raw('fresh_setting_key');
$fresh_normalized['ui']['color']['theme'] === '#abcdef' || fail('compatibility normalization must not overwrite a later same-key package save');
$fresh_normalized['ui']['color']['body'] === '#697a8d' || fail('compatibility normalization must merge defaults into the latest same-key value');
$fresh_normalized['concurrent']['kept'] === TRUE || fail('compatibility normalization must preserve later unknown extension keys');

// The setting row is normalized before durable ownership is recorded. A setting failure must write
// no sidecar; a later sidecar failure may leave safe defaults in place and can be retried.
$storage_schema = $shared_schema;
$storage_schema['setting_key'] = 'storage_failure_key';
reset_setting_runtime(array('storage_failure_key'=>$saved));
reset_plugin_setting_compat();
plugin_setting_schema_bind_plugin('storage-failure-owner', $storage_schema, array()) === 'storage_failure_key' || fail('storage failure fixture must bind before persistence');
$db_write_fail_keys['setting'] = TRUE;
plugin_setting_schema_persist_plugin('storage-failure-owner') === FALSE || fail('setting storage failure must fail persistence');
db_write_count_for('setting') === 1 || fail('setting storage failure fixture must reach exactly one failed setting write');
db_write_count_for(plugin_setting_schema_sidecar_kv_key()) === 0 || fail('setting storage failure must not register sidecar ownership');
setting_get_raw('storage_failure_key') === $saved || fail('setting storage failure must preserve the raw setting value');
sidecar_value() === NULL || fail('failed atomic setting-row storage must preserve both value and metadata state');

reset_setting_runtime(array('storage_failure_key'=>$saved));
reset_plugin_setting_compat();
plugin_setting_schema_bind_plugin('storage-failure-owner', $storage_schema, array()) === 'storage_failure_key' || fail('atomic value/metadata fixture must bind before persistence');
plugin_setting_schema_persist_plugin('storage-failure-owner') || fail('atomic value/metadata persistence should succeed');
db_write_count_for('setting') === 1 || fail('value normalization and ownership metadata must commit in exactly one setting-row write');
db_write_count_for(plugin_setting_schema_sidecar_kv_key()) === 0 || fail('atomic persistence must never write an independent sidecar row');
setting_get_raw('storage_failure_key') === $merged || fail('atomic persistence must publish normalized defaults');
is_array(plugin_setting_schema_sidecar_normalize(sidecar_value())) || fail('atomic persistence must publish ownership metadata with the value');

// A primary read failure is not an empty setting row. Whole-row RMW must fail before db_replace so
// a replica/outage cannot erase unrelated values or publish metadata derived from missing state.
reset_setting_runtime(array('storage_failure_key'=>$saved, 'unrelated'=>array('kept'=>TRUE)));
reset_plugin_setting_compat();
plugin_setting_schema_bind_plugin('primary-read-failure-owner', $storage_schema, array()) === 'storage_failure_key' || fail('primary-read failure fixture must bind before the injected outage');
$row_before_primary_failure = $db_kv_store['setting']['v'];
$db_primary_read_ok = FALSE;
plugin_setting_schema_persist_plugin('primary-read-failure-owner') === FALSE || fail('schema persistence must fail closed when the primary setting row cannot be read');
setting_set('another_key', 1) === FALSE || fail('ordinary setting RMW must fail closed when the primary setting row cannot be read');
$db_write_count === 0 && $db_kv_store['setting']['v'] === $row_before_primary_failure
	|| fail('primary setting read failure must perform zero writes and preserve the complete row');
$db_primary_read_ok = TRUE;

reset_setting_runtime(array('storage_failure_key'=>$saved));
reset_plugin_setting_compat();
plugin_setting_schema_bind_plugin('lock-failure-owner', $storage_schema, array()) === 'storage_failure_key' || fail('lock failure fixture must bind before persistence');
$valid_conf = $conf;
$conf = array('tmp_path'=>$setting_lock_dir.'/missing/');
plugin_setting_schema_persist_plugin('lock-failure-owner') === FALSE || fail('missing sidecar lock directory must fail persistence');
$conf = $valid_conf;
$db_write_count === 0 || fail('sidecar lock failure must perform zero metadata and setting writes');
setting_get_raw('storage_failure_key') === $saved || fail('sidecar lock failure must preserve the raw setting value');

reset_setting_runtime(array('storage_failure_key'=>$saved));
$sidecar_key = plugin_setting_schema_sidecar_kv_key();
$db_kv_store[$sidecar_key] = array('k'=>$sidecar_key, 'v'=>xn_json_encode(array('version'=>1, 'keys'=>'corrupt')));
reset_request_runtime_preserving_db();
plugin_setting_schema_bind_plugin('corrupt-sidecar-owner', $storage_schema, array()) === FALSE || fail('malformed sidecar metadata must make registration inert');
plugin_setting_schema_persist_plugin('corrupt-sidecar-owner') === FALSE || fail('malformed sidecar metadata must fail persistence');
$db_write_count === 0 || fail('malformed sidecar metadata must not be overwritten or normalize settings');
setting_get_raw('storage_failure_key') === $saved || fail('malformed sidecar metadata must preserve the raw setting value');

// Admin setting migration gates: POST alone is insufficient; the package must save the same key,
// and the request must finish successfully (or emit message(0)).
reset_setting_runtime(array('admin_schema_key'=>$saved));
reset_plugin_setting_compat();
$admin_schema = $schema;
$admin_schema['setting_key'] = 'admin_schema_key';
$method = 'GET';
plugin_setting_admin_request_start('admin-demo');
$token = plugin_setting_capture_begin('admin-demo');
setting_set('admin_schema_key', $saved);
$admin_capture = plugin_setting_capture_end($token);
plugin_setting_schema_bind_plugin('admin-demo', $admin_schema, $admin_capture);
plugin_setting_admin_request_can_persist('admin-demo', $admin_capture, TRUE, FALSE) === FALSE || fail('GET must never trigger compatibility persistence');
plugin_setting_admin_request_clear();

$method = 'POST';
plugin_setting_admin_request_start('admin-demo');
plugin_setting_admin_request_capture_message(1);
plugin_setting_admin_request_can_persist('admin-demo', $admin_capture, TRUE, FALSE) === FALSE || fail('controlled validation errors must not persist defaults');
plugin_setting_admin_request_clear();

plugin_setting_admin_request_start('admin-demo');
plugin_setting_admin_request_capture_message(0);
plugin_setting_admin_request_can_persist('admin-demo', $admin_capture, FALSE, FALSE) || fail('message(0) after a successful package save must permit POST normalization');
plugin_setting_admin_request_clear();

plugin_setting_admin_request_start('admin-demo');
plugin_setting_admin_request_can_persist('admin-demo', $admin_capture, TRUE, FALSE) || fail('normal successful return after a successful package save must permit POST normalization');
plugin_setting_admin_request_clear();

plugin_setting_admin_request_start('admin-demo');
plugin_setting_admin_request_can_persist('admin-demo', $admin_capture, FALSE, FALSE) === FALSE || fail('abnormal/unknown completion must not persist defaults');
plugin_setting_admin_request_clear();

plugin_setting_admin_request_start('admin-demo');
plugin_setting_admin_request_capture_message(0);
plugin_setting_admin_request_can_persist('admin-demo', array('reads'=>array('admin_schema_key'), 'writes'=>array()), FALSE, FALSE) === FALSE || fail('a success response without the package setting save must not persist defaults');
plugin_setting_admin_request_clear();

plugin_setting_admin_request_start('admin-demo');
plugin_setting_admin_request_capture_message(0);
plugin_setting_admin_request_can_persist('admin-demo', $admin_capture, FALSE, TRUE) === FALSE || fail('fatal shutdown must not persist defaults');
plugin_setting_admin_request_clear();

// Exercise the real include wrapper with compiled temporary fixtures. These cases lock the write
// boundary itself, not merely the decision helper.
$fixture_dir = rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/').'/xiuno_plugin_setting_compat_'.getmypid().'_'.str_replace('.', '', uniqid('', TRUE));
mkdir($fixture_dir, 0777, TRUE) || fail('could not create plugin setting compatibility fixture directory');
register_shutdown_function(function() use ($fixture_dir) { remove_fixture_tree($fixture_dir); });
$conf = array('tmp_path'=>$fixture_dir.'/', 'disabled_plugin'=>1);
$fixture_file = $fixture_dir.'/setting.php';
$fixture_source = <<<'PHP'
<?php
$data = $GLOBALS['fixture_schema'];
setting_get($GLOBALS['fixture_key']);
if($GLOBALS['fixture_write']) setting_set($GLOBALS['fixture_key'], $GLOBALS['fixture_saved']);
if($GLOBALS['fixture_message_code'] !== NULL) plugin_setting_admin_request_capture_message($GLOBALS['fixture_message_code']);
if($GLOBALS['fixture_throw']) throw new RuntimeException('fixture failure');
return $GLOBALS['fixture_result'];
PHP;
file_put_contents($fixture_file, $fixture_source) !== FALSE || fail('could not write plugin setting compatibility fixture');
$fixture_schema = $schema;
$fixture_saved = $saved;
$fixture_key = 'wrapper_schema_key';
$fixture_write = TRUE;
$fixture_throw = FALSE;
$fixture_result = TRUE;

$method = 'GET';
$fixture_write = FALSE;
$fixture_message_code = NULL;
reset_setting_runtime(array($fixture_key=>$saved));
reset_plugin_setting_compat();
ob_start();
$empty_page = plugin_compat_include_setting_page($fixture_file, 'empty-page-demo');
$empty_page_output = ob_get_clean();
$empty_page['result'] === TRUE || fail('a normally completed empty settings page must preserve its include result');
$empty_page['has_output'] === FALSE || fail('a normally completed settings page with no output must be identifiable');
$empty_page_output === '' || fail('empty settings-page capture must not manufacture output');

$output_fixture = $fixture_dir.'/output_setting.php';
$output_source = "<?php\necho '<section id=\"settings-fixture\">Settings</section>';\nreturn TRUE;\n";
file_put_contents($output_fixture, $output_source) !== FALSE || fail('could not write output settings-page fixture');
reset_plugin_setting_compat();
ob_start();
$output_page = plugin_compat_include_setting_page($output_fixture, 'output-page-demo');
$output_page_html = ob_get_clean();
$output_page['has_output'] === TRUE || fail('a rendered settings page must not be replaced by the generic empty state');
$output_page_html === '<section id="settings-fixture">Settings</section>'
	|| fail('settings-page output capture must preserve package output byte-for-byte');
plugin_compat_setting_output_has_content("\xEF\xBB\xBF \r\n\t") === FALSE
	|| fail('a BOM followed only by whitespace must remain an empty settings page');

// Core plugin URLs carry the validated package identifier in a named `dir` argument so hyphens
// survive routing. Legacy setting pages still commonly read param(2) / _GET(2), so the include
// boundary must expose that same canonical identifier temporarily and restore any prior value.
function_exists('plugin_compat_setting_route_context_start')
	&& function_exists('plugin_compat_setting_route_context_end')
	|| fail('legacy plugin setting route context compatibility helpers are missing');
$route_fixture = $fixture_dir.'/route_setting.php';
$route_source = <<<'PHP'
<?php
echo json_encode(array(
	'request'=>array_key_exists(2, $_REQUEST) ? $_REQUEST[2] : NULL,
	'get'=>array_key_exists(2, $_GET) ? $_GET[2] : NULL,
));
if($GLOBALS['fixture_route_throw']) throw new RuntimeException('route fixture failure');
return TRUE;
PHP;
file_put_contents($route_fixture, $route_source) !== FALSE || fail('could not write legacy route setting fixture');
$method = 'GET';
$fixture_route_throw = FALSE;
$_GET = array(0=>'plugin', 1=>'setting', 2=>'untrusted-get', 'dir'=>'route-demo');
$_REQUEST = array(0=>'plugin', 1=>'setting', 2=>'untrusted-request', 'dir'=>'route-demo');
$route_get_before = $_GET;
$route_request_before = $_REQUEST;
reset_plugin_setting_compat();
ob_start();
$route_page = plugin_compat_include_setting_page($route_fixture, 'route-demo');
$route_page_output = ob_get_clean();
$route_page['has_output'] === TRUE || fail('legacy route fixture must produce setting page output');
json_decode($route_page_output, TRUE) === array('request'=>'route-demo', 'get'=>'route-demo')
	|| fail('setting include must expose the validated named package as legacy position 2');
$_GET === $route_get_before && $_REQUEST === $route_request_before
	|| fail('normal setting include must restore prior GET and REQUEST position 2 values exactly');

$fixture_route_throw = TRUE;
reset_plugin_setting_compat();
ob_start();
try {
	plugin_compat_include_setting($route_fixture, 'route-demo');
	fail('legacy route fixture exception should escape the compatibility wrapper');
} catch(RuntimeException $e) {
	$e->getMessage() === 'route fixture failure' || fail('unexpected legacy route fixture exception');
}
ob_end_clean();
$_GET === $route_get_before && $_REQUEST === $route_request_before
	|| fail('exceptional setting include must restore prior GET and REQUEST position 2 values exactly');
$fixture_route_throw = FALSE;

unset($_GET[2], $_REQUEST[2]);
$route_get_without_position = $_GET;
$route_request_without_position = $_REQUEST;
$outer_route_context = plugin_compat_setting_route_context_start('outer-route');
isset($_GET[2], $_REQUEST[2]) && $_GET[2] === 'outer-route' && $_REQUEST[2] === 'outer-route'
	|| fail('outer legacy route context did not publish its canonical position');
$inner_route_context = plugin_compat_setting_route_context_start('inner-route');
$_GET[2] === 'inner-route' && $_REQUEST[2] === 'inner-route'
	|| fail('nested legacy route context did not publish its canonical position');
plugin_compat_setting_route_context_end($inner_route_context);
$_GET[2] === 'outer-route' && $_REQUEST[2] === 'outer-route'
	|| fail('nested legacy route context did not restore its parent position');
plugin_compat_setting_route_context_end($outer_route_context);
$_GET === $route_get_without_position && $_REQUEST === $route_request_without_position
	|| fail('legacy route context must remove compatibility-only positions after restoration');
$fixture_write = TRUE;

// Capturing a successful third-party lifecycle is not the outer install/upgrade commit point.
// If later same-type replacement or package finalization fails, the package's own setting_set()
// cannot be undone, but compatibility-owned sidecar/default writes must still be zero.
$lifecycle_fixture = $fixture_dir.'/install.php';
$lifecycle_source = <<<'PHP'
<?php
$data = $GLOBALS['fixture_schema'];
setting_get($GLOBALS['fixture_key']);
setting_set($GLOBALS['fixture_key'], $GLOBALS['fixture_saved']);
return TRUE;
PHP;
file_put_contents($lifecycle_fixture, $lifecycle_source) !== FALSE || fail('could not write lifecycle outer-boundary fixture');
$fixture_key = 'outer_boundary_key';
reset_setting_runtime(array($fixture_key=>$saved));
reset_plugin_setting_compat();
plugin_compat_include_lifecycle($lifecycle_fixture, 'outer-boundary-demo');
db_write_count_for('setting') === 1 || fail('simulated outer failure may retain exactly the third-party lifecycle setting write');
db_write_count_for(plugin_setting_schema_sidecar_kv_key()) === 0 || fail('captured lifecycle success must not write sidecar metadata before outer finalization');
setting_get_raw($fixture_key) === $saved || fail('simulated outer failure must add no compatibility defaults');

reset_setting_runtime(array($fixture_key=>$saved));
reset_plugin_setting_compat();
plugin_compat_include_lifecycle($lifecycle_fixture, 'outer-boundary-demo');
plugin_setting_schema_persist_plugin('outer-boundary-demo') || fail('outer lifecycle success must explicitly persist captured schema defaults');
db_write_count_for('setting') === 2 || fail('outer lifecycle success must add exactly one compatibility normalization write');
db_write_count_for(plugin_setting_schema_sidecar_kv_key()) === 0 || fail('outer lifecycle success must keep metadata in the atomic setting-row write');
setting_get_raw($fixture_key) === $merged || fail('outer lifecycle success must persist the normalized defaults');

$method = 'GET';
$fixture_message_code = 0;
$fixture_key = 'wrapper_schema_key';
reset_setting_runtime(array($fixture_key=>$saved));
reset_plugin_setting_compat();
plugin_compat_include_setting($fixture_file, 'wrapper-demo');
setting_get_raw($fixture_key) === $saved || fail('real GET wrapper must not persist schema defaults even after a package write/success message');
db_write_count_for('setting') === 1 || fail('GET may include the package write but must add no compatibility normalization write');
db_write_count_for(plugin_setting_schema_sidecar_kv_key()) === 0 || fail('GET must not register persistent schema metadata');

$method = 'POST';
$fixture_message_code = 1;
reset_setting_runtime(array($fixture_key=>$saved));
reset_plugin_setting_compat();
plugin_compat_include_setting($fixture_file, 'wrapper-demo');
setting_get_raw($fixture_key) === $saved || fail('real POST wrapper must not normalize a controlled validation failure');
db_write_count_for('setting') === 1 || fail('failed POST may include the package write but must add no compatibility normalization write');
db_write_count_for(plugin_setting_schema_sidecar_kv_key()) === 0 || fail('failed POST must not register persistent schema metadata');

$method = 'POST';
$fixture_message_code = 0;
reset_setting_runtime(array($fixture_key=>$saved));
reset_plugin_setting_compat();
plugin_compat_include_setting($fixture_file, 'wrapper-demo');
setting_get_raw($fixture_key) === $merged || fail('real POST wrapper must normalize after package save and message(0)');
db_write_count_for('setting') === 2 || fail('successful POST normalization should add exactly one setting-row compatibility write');
db_write_count_for(plugin_setting_schema_sidecar_kv_key()) === 0 || fail('successful POST normalization must not create an independent sidecar write');

$method = 'POST';
$fixture_message_code = NULL;
$fixture_result = FALSE;
reset_setting_runtime(array($fixture_key=>$saved));
reset_plugin_setting_compat();
plugin_compat_include_setting($fixture_file, 'wrapper-demo');
setting_get_raw($fixture_key) === $saved || fail('real POST wrapper must not normalize an explicit FALSE return');
db_write_count_for(plugin_setting_schema_sidecar_kv_key()) === 0 || fail('explicit FALSE POST must not register persistent schema metadata');

$fixture_result = TRUE;
$fixture_throw = TRUE;
reset_setting_runtime(array($fixture_key=>$saved));
reset_plugin_setting_compat();
try {
	plugin_compat_include_setting($fixture_file, 'wrapper-demo');
	fail('fixture exception should escape the compatibility wrapper');
} catch(RuntimeException $e) {
	$e->getMessage() === 'fixture failure' || fail('unexpected compatibility wrapper exception');
}
setting_get_raw($fixture_key) === $saved || fail('real POST wrapper must not normalize an exception path');
db_write_count_for(plugin_setting_schema_sidecar_kv_key()) === 0 || fail('exception POST must not register persistent schema metadata');
$fixture_throw = FALSE;

// Request-local include_once contract: a schema registered by an earlier wrapped include remains
// usable later in that request. A conf.php loaded before every wrapper is intentionally not adopted
// or re-executed; without a captured schema, a successful POST remains a no-op.
$include_conf = $fixture_dir.'/captured_conf.php';
$include_entry = $fixture_dir.'/include_once_setting.php';
file_put_contents($include_conf, "<?php\n\$GLOBALS['fixture_schema_count']++;\n\$data = \$GLOBALS['fixture_schema'];\n") !== FALSE || fail('could not write captured include_once schema fixture');
$include_source = <<<'PHP'
<?php
setting_get($GLOBALS['fixture_key']);
include_once $GLOBALS['fixture_conf_file'];
if($GLOBALS['fixture_write']) setting_set($GLOBALS['fixture_key'], $GLOBALS['fixture_saved']);
if($GLOBALS['fixture_message_code'] !== NULL) plugin_setting_admin_request_capture_message($GLOBALS['fixture_message_code']);
return TRUE;
PHP;
file_put_contents($include_entry, $include_source) !== FALSE || fail('could not write include_once setting fixture');
$fixture_key = 'include_once_key';
$fixture_conf_file = $include_conf;
$fixture_schema_count = 0;
$fixture_write = FALSE;
$fixture_message_code = NULL;
$method = 'GET';
reset_setting_runtime(array($fixture_key=>$saved));
reset_plugin_setting_compat();
plugin_compat_include_setting($include_entry, 'include-once-demo');
setting_get_raw($fixture_key) === $saved || fail('first include_once GET must not persist defaults');

$fixture_write = TRUE;
$fixture_message_code = 0;
$method = 'POST';
plugin_compat_include_setting($include_entry, 'include-once-demo');
$fixture_schema_count === 1 || fail('include_once schema must not be re-executed by the wrapper');
setting_get_raw($fixture_key) === $merged || fail('later include_once skip must reuse the earlier request-local schema registration');

$preloaded_conf = $fixture_dir.'/preloaded_conf.php';
$preloaded_entry = $fixture_dir.'/preloaded_setting.php';
file_put_contents($preloaded_conf, "<?php\n\$GLOBALS['preloaded_schema_count']++;\n\$data = \$GLOBALS['fixture_schema'];\n") !== FALSE || fail('could not write preloaded schema fixture');
$preloaded_source = <<<'PHP'
<?php
setting_get($GLOBALS['fixture_key']);
include_once $GLOBALS['fixture_conf_file'];
setting_set($GLOBALS['fixture_key'], $GLOBALS['fixture_saved']);
plugin_setting_admin_request_capture_message(0);
return TRUE;
PHP;
file_put_contents($preloaded_entry, $preloaded_source) !== FALSE || fail('could not write preloaded setting fixture');
$fixture_key = 'preloaded_key';
$fixture_conf_file = $preloaded_conf;
$preloaded_schema_count = 0;
reset_setting_runtime(array($fixture_key=>$saved));
reset_plugin_setting_compat();
include_once $preloaded_conf;
$method = 'POST';
plugin_compat_include_setting($preloaded_entry, 'preloaded-outside');
$preloaded_schema_count === 1 || fail('preloaded schema must not be re-executed to manufacture compatibility state');
setting_get_raw($fixture_key) === $saved || fail('schema preloaded outside every wrapper must not be guessed or persisted');

// A package message() response is deferred until the compatibility-owned atomic row update has
// reached its real commit boundary. Success is replayed only after persistence; package errors are
// replayed unchanged without normalization; a compatibility write failure must replace, not follow,
// the package success response.
$message_entry = $fixture_dir.'/message_setting.php';
$message_source = <<<'PHP'
<?php
$data = $GLOBALS['fixture_schema'];
setting_get($GLOBALS['fixture_key']);
setting_set($GLOBALS['fixture_key'], $GLOBALS['fixture_saved']);
if($GLOBALS['fixture_force_compat_failure']) $GLOBALS['db_write_ok'] = FALSE;
message($GLOBALS['fixture_message_code'], $GLOBALS['fixture_message_text'], $GLOBALS['fixture_message_extra']);
return TRUE;
PHP;
file_put_contents($message_entry, $message_source) !== FALSE || fail('could not write deferred message fixture');
$fixture_key = 'message_setting_key';
$fixture_schema = $schema;
$fixture_schema['setting_key'] = $fixture_key;
$fixture_saved = $saved;
$fixture_force_compat_failure = FALSE;
$fixture_message_code = 0;
$fixture_message_text = 'package save succeeded';
$fixture_message_extra = array('redirect'=>'settings');
$method = 'POST';
reset_setting_runtime(array($fixture_key=>$saved));
reset_plugin_setting_compat();
$plugin_setting_message_responses = array();
$message_get_before = $_GET;
$message_request_before = $_REQUEST;
try {
	plugin_compat_include_setting($message_entry, 'message-success');
	fail('a replayed success message must terminate through the response fixture');
} catch(PluginSettingResponseFixture $e) {}
$_GET === $message_get_before && $_REQUEST === $message_request_before
	|| fail('controlled setting message must restore the legacy route compatibility context before replay');
setting_get_raw($fixture_key) === $merged || fail('message(0) must persist compatibility defaults before replay');
$plugin_setting_message_responses === array(array(0, 'package save succeeded', array('redirect'=>'settings')))
	|| fail('message(0) must be replayed once with its original code, text and extra payload');

$fixture_message_code = 1;
$fixture_message_text = 'package validation failed';
$fixture_message_extra = array('field'=>'colour');
reset_setting_runtime(array($fixture_key=>$saved));
reset_plugin_setting_compat();
$plugin_setting_message_responses = array();
try {
	plugin_compat_include_setting($message_entry, 'message-error');
	fail('a replayed package error must terminate through the response fixture');
} catch(PluginSettingResponseFixture $e) {}
setting_get_raw($fixture_key) === $saved || fail('message(1) must not normalize package settings');
sidecar_value() === NULL || fail('message(1) must not register compatibility ownership metadata');
db_write_count_for('setting') === 1 || fail('message(1) must perform only the package-owned setting write');
$plugin_setting_message_responses === array(array(1, 'package validation failed', array('field'=>'colour')))
	|| fail('message(1) must be replayed once without changing its payload');

$fixture_message_code = 0;
$fixture_message_text = 'package save succeeded';
$fixture_message_extra = array('redirect'=>'settings');
$fixture_force_compat_failure = TRUE;
reset_setting_runtime(array($fixture_key=>$saved));
reset_plugin_setting_compat();
$plugin_setting_message_responses = array();
try {
	plugin_compat_include_setting($message_entry, 'message-persist-failure');
	fail('a compatibility persistence failure must terminate through the error response fixture');
} catch(PluginSettingResponseFixture $e) {}
$plugin_setting_message_responses_count = count($plugin_setting_message_responses);
$plugin_setting_message_responses_count === 1 || fail('compatibility persistence failure must emit exactly one response');
$plugin_setting_message_responses[0][0] === -1 || fail('compatibility persistence failure must replace the package success response with an error');
strpos($plugin_setting_message_responses[0][1], 'compatibility metadata/default persistence failed') !== FALSE
	|| fail('compatibility persistence failure must return an actionable diagnostic');
setting_get_raw($fixture_key) === $saved || fail('failed compatibility persistence must leave the package-owned setting value intact');
$fixture_force_compat_failure = FALSE;

$plugin_model_source = file_get_contents(APP_PATH.'model/plugin.func.php');
$kv_source = file_get_contents(APP_PATH.'model/kv.func.php');
$route_source = file_get_contents(APP_PATH.'admin/route/plugin.php');
strpos($kv_source, "plugin_setting_capture_key(\$k, 'read')") !== FALSE || fail('setting_get/raw capture hook is missing');
strpos($kv_source, "plugin_setting_capture_key(\$k, 'write')") !== FALSE || fail('successful setting_set capture hook is missing');
strpos($plugin_model_source, 'plugin_setting_admin_request_can_persist') !== FALSE || fail('admin POST persistence gate is missing');
strpos($plugin_model_source, "return '__xn_plugin_schema_registry_v1';") !== FALSE || fail('persistent schema ownership must use a reserved key in the setting row');
strpos($plugin_model_source, "return 'plugin_setting_schema_v1';") !== FALSE || fail('legacy independent sidecar metadata must remain readable for one-way migration');
strpos($plugin_model_source, "'lock_plugin_setting_schema.lock'") !== FALSE || fail('schema registry persistence must use an independent cross-process lock');
strpos($plugin_model_source, 'setting_row_update_atomic(function($setting)') !== FALSE
	&& strpos($kv_source, "\$setting = kv__get('setting', TRUE);") !== FALSE
	|| fail('compatibility persistence must merge value and metadata from the latest primary setting row under one write lock');
strpos($route_source, 'plugin_setting_admin_request_capture_message($code, $message, $extra)') !== FALSE || fail('plugin message payload must feed the admin persistence gate for deferred replay');
$run_start = strpos($route_source, 'function plugin_run_lifecycle(');
$persist_helper_start = strpos($route_source, 'function plugin_lifecycle_persist_setting_schema(');
($run_start !== FALSE && $persist_helper_start !== FALSE && strpos(substr($route_source, $run_start, $persist_helper_start - $run_start), 'plugin_lifecycle_persist_setting_schema(') === FALSE)
	|| fail('plugin_run_lifecycle must capture schema only; compatibility persistence belongs to the outer action commit point');
$install_start = strpos($route_source, "} elseif(\$action == 'install')");
$unstall_start = strpos($route_source, "} elseif(\$action == 'unstall')");
$install_source = ($install_start !== FALSE && $unstall_start !== FALSE) ? substr($route_source, $install_start, $unstall_start - $install_start) : '';
$install_lifecycle_pos = strpos($install_source, "plugin_run_lifecycle(\$dir, 'install'");
$install_same_type_pos = strpos($install_source, 'plugin_auto_unstall_same_type(');
$install_schema_pos = strpos($install_source, "plugin_lifecycle_persist_setting_schema(\$dir, 'install')");
$install_unlock_pos = strpos($install_source, 'plugin_lock_end();');
($install_lifecycle_pos !== FALSE && $install_same_type_pos !== FALSE && $install_schema_pos !== FALSE && $install_unlock_pos !== FALSE
	&& $install_lifecycle_pos < $install_same_type_pos && $install_same_type_pos < $install_schema_pos && $install_schema_pos < $install_unlock_pos)
	|| fail('install compatibility persistence must run only after same-type finalization and before releasing the outer task lock');

remove_fixture_tree($fixture_dir);
echo "OK: plugin setting compatibility checks passed\n";
