<?php

$root = dirname(__DIR__);

defined('DEBUG') || define('DEBUG', 0);
defined('APP_PATH') || define('APP_PATH', $root.'/');
defined('ADMIN_PATH') || define('ADMIN_PATH', $root.'/admin/');
defined('XIUNOPHP_PATH') || define('XIUNOPHP_PATH', $root.'/xiunophp/');

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
);

$details = plugin_dependency_details('app');
assert_status($details, 'missing', 'not_downloaded');
assert_status($details, 'downloaded', 'downloaded_not_installed');
assert_status($details, 'disabled', 'installed_disabled');
assert_status($details, 'old', 'version_low');
assert_status($details, 'badmeta', 'metadata_error');
assert_status($details, 'cycle', 'cycle');
assert_status($details, 'ok', 'ok');

$blocked = plugin_dependencies('app');
foreach(array('missing', 'downloaded', 'disabled', 'old', 'badmeta', 'cycle') as $dir) {
	if(!isset($blocked[$dir])) fail("Expected $dir to block install/enable");
}
isset($blocked['ok']) && fail('Satisfied dependency must not block install/enable');

plugin_dependency_status_text($blocked['old']) !== '' || fail('Dependency status text must describe structured dependency details.');

echo "OK: plugin dependency status checks passed\n";
