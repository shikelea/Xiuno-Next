<?php

$root = dirname(__DIR__);
$plugin_route = file_get_contents($root.'/admin/route/plugin.php');

defined('DEBUG') || define('DEBUG', 0);
defined('APP_PATH') || define('APP_PATH', $root.'/');
defined('ADMIN_PATH') || define('ADMIN_PATH', $root.'/admin/');
defined('XIUNOPHP_PATH') || define('XIUNOPHP_PATH', $root.'/xiunophp/');

function url($route, $extra = array()) {
	return $route.'.htm'.(empty($extra) ? '' : '?'.http_build_query($extra));
}

include $root.'/model/plugin.func.php';

function fail($message) {
	fwrite(STDERR, "FAIL: $message\n");
	exit(1);
}

function assert_status($details, $dir, $status) {
	if(!isset($details[$dir])) fail("Missing dependency detail for $dir");
	$actual = $details[$dir]['status'];
	if($actual !== $status) fail("Expected $dir to be $status, got $actual");
}

function section_between($source, $start, $end) {
	$start_pos = strpos($source, $start);
	if($start_pos === FALSE) fail("Missing section start: $start");
	$end_pos = strpos($source, $end, $start_pos + strlen($start));
	if($end_pos === FALSE) fail("Missing section end after $start: $end");
	return substr($source, $start_pos, $end_pos - $start_pos);
}

$plugins = array(
	'app'=>array(
		'name'=>'App',
		'version'=>'1.0.0',
		'installed'=>1,
		'enable'=>1,
		'dependencies'=>array(
			'missing'=>'1.0.0',
			'downloaded'=>'1.0.0',
			'disabled'=>'1.0.0',
			'old'=>'2.0.0',
			'badmeta'=>'1.0.0',
			'cycle'=>'1.0.0',
			'ok'=>'1.0.0',
			'bad/dir'=>'1.0.0',
			'hyphen-dep'=>'1.0.0',
		),
	),
	'downloaded'=>array(
		'name'=>'Downloaded',
		'version'=>'1.0.0',
		'installed'=>0,
		'enable'=>0,
		'dependencies'=>array(),
	),
	'disabled'=>array(
		'name'=>'Disabled',
		'version'=>'1.0.0',
		'installed'=>1,
		'enable'=>0,
		'dependencies'=>array(),
	),
	'old'=>array(
		'name'=>'Old',
		'version'=>'1.0.0',
		'installed'=>1,
		'enable'=>1,
		'dependencies'=>array(),
	),
	'badmeta'=>array(
		'name'=>'Bad metadata',
		'version'=>'1.0.0',
		'installed'=>1,
		'enable'=>1,
		'dependencies'=>'broken',
	),
	'cycle'=>array(
		'name'=>'Cycle',
		'version'=>'1.0.0',
		'installed'=>1,
		'enable'=>1,
		'dependencies'=>array('app'=>'1.0.0'),
	),
	'ok'=>array(
		'name'=>'OK',
		'version'=>'1.0.0',
		'installed'=>1,
		'enable'=>1,
		'dependencies'=>array(),
	),
	'hyphen-dep'=>array(
		'name'=>'Hyphen dependency',
		'version'=>'1.0.0',
		'installed'=>1,
		'enable'=>1,
		'dependencies'=>array(),
	),
);

$details = plugin_dependency_details('app');
assert_status($details, 'missing', 'not_downloaded');
assert_status($details, 'downloaded', 'downloaded_not_installed');
assert_status($details, 'disabled', 'installed_disabled');
assert_status($details, 'old', 'version_low');
assert_status($details, 'badmeta', 'metadata_error');
assert_status($details, 'cycle', 'cycle');
assert_status($details, 'ok', 'ok');
assert_status($details, 'bad/dir', 'invalid_dir');
assert_status($details, 'hyphen-dep', 'ok');

$blocked = plugin_dependencies('app');
foreach(array('missing', 'downloaded', 'disabled', 'old', 'badmeta', 'cycle', 'bad/dir') as $dir) {
	if(!isset($blocked[$dir])) fail("Expected $dir to block install/enable");
}
isset($blocked['ok']) && fail('Satisfied dependency must not block install/enable');

plugin_dependency_status_text($blocked['old']) !== '' || fail('Dependency status text must describe structured dependency details.');
plugin_dependency_status_text($blocked['bad/dir']) === 'invalid dependency name' || fail('Invalid dependency names must have an explicit status text.');
function_exists('plugin_dependency_dir_valid') || fail('Dependency dir validator helper is missing.');
plugin_dir_is_valid('theme-modern')
	&& plugin_dir_is_valid(str_repeat('a', 64))
	&& !plugin_dir_is_valid('-theme')
	&& !plugin_dir_is_valid(str_repeat('a', 65))
	&& !plugin_dir_is_valid("theme\n")
	|| fail('Plugin directory identifiers must share one strict, lossless 1-64 character contract.');
$hyphen_url = plugin_url('setting', 'theme-modern');
strpos($hyphen_url, 'plugin-setting.htm?') === 0 && strpos($hyphen_url, 'dir=theme-modern') !== FALSE
	|| fail('Plugin URLs must carry package identifiers in a named query argument without stripping hyphens.');
strpos($plugin_route, 'function plugin_route_dir($position = 2)') !== FALSE
	&& strpos($plugin_route, "param('dir', '', FALSE)") !== FALSE
	&& strpos($plugin_route, 'param_word(2)') === FALSE
	|| fail('Plugin routes must prefer the lossless named directory argument and never silently sanitize or truncate it.');

$dependency_guard = section_between($plugin_route, 'function plugin_check_dependency', 'function plugin_reload_local');
strpos($dependency_guard, '$check_self_metadata = TRUE') !== FALSE
	|| fail('Plugin dependency checks must keep self metadata validation enabled by default.');
strpos($dependency_guard, "!empty(\$plugins[\$dir]['metadata_error'])") !== FALSE
	|| fail('Plugin install/upgrade dependency checks must reject target plugin metadata errors.');
strpos($dependency_guard, '$check_self_metadata && !empty') !== FALSE
	|| fail('Self metadata validation must be explicitly controllable for upgrade repair preflights.');
strpos($dependency_guard, "'conf.json '.lang('format_maybe_error')") !== FALSE
	|| fail('Target plugin metadata errors must report a conf.json format error.');

$dependency_links = section_between($plugin_route, 'function plugin_dependency_arr_to_links', '// 下载插件、解压');
strpos($dependency_links, 'plugin_dependency_has_detail_page($dir)') !== FALSE
	|| fail('Dependency links must avoid dead plugin-read pages for missing local/official packages.');
strpos($dependency_links, 'function plugin_dependency_has_detail_page($dir)') !== FALSE
	|| fail('Dependency detail page availability helper is missing.');
strpos($dependency_links, 'isset($plugins[$dir])') !== FALSE
	|| fail('Dependency detail links must allow locally downloaded packages.');
strpos($dependency_links, 'plugin_official_read($dir)') !== FALSE
	|| fail('Dependency detail links must allow officially listed packages.');
strpos($dependency_links, '<span class="text-muted">') !== FALSE
	|| fail('Missing dependency without a detail page must render as text instead of a dead link.');
strpos($dependency_links, "htmlspecialchars(plugin_url('read', \$dir), ENT_QUOTES)") !== FALSE
	|| fail('Dependency detail links must use the lossless named-directory URL helper and escape the result.');

$upgrade_flow = section_between($plugin_route, "} elseif(\$action == 'upgrade')", "} elseif(\$action == 'setting')");
strpos($upgrade_flow, "plugin_check_dependency(\$dir, 'install', NULL, NULL, FALSE);") !== FALSE
	|| fail('Upgrade preflight must allow the new package to repair old target metadata errors.');
if(strpos($upgrade_flow, 'plugin_official_remote_closed();') === FALSE) {
	strpos($upgrade_flow, "plugin_check_dependency(\$dir, 'install', \$plugin_snapshot, \$package_snapshot);") !== FALSE
		|| fail('Upgrade must re-check dependencies and target metadata after loading the replacement package.');
}

echo "OK: plugin dependency status checks passed\n";
