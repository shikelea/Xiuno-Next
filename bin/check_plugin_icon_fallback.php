<?php

$root = getenv('XIUNO_ROOT');
$root = $root === FALSE || $root === '' ? dirname(__DIR__).'/' : rtrim($root, '\\/').DIRECTORY_SEPARATOR;
$fixture_root = $root.'tmp/plugin-icon-compat-'.getmypid().'/';

function fail($message) {
	fwrite(STDERR, "FAIL: $message\n");
	exit(1);
}

function remove_tree($path) {
	if(is_dir($path)) {
		foreach(scandir($path) as $entry) {
			if($entry === '.' || $entry === '..') continue;
			remove_tree(rtrim($path, '\\/').DIRECTORY_SEPARATOR.$entry);
		}
		@rmdir($path);
	} elseif(is_file($path)) {
		@unlink($path);
	}
}

foreach(glob($root.'tmp/plugin-icon-compat-*', GLOB_ONLYDIR) as $stale_fixture) {
	remove_tree($stale_fixture);
}

if(!mkdir($fixture_root.'plugin/with_icon', 0777, TRUE) && !is_dir($fixture_root.'plugin/with_icon')) {
	fail('failed to create isolated plugin icon fixture');
}
register_shutdown_function(function() use ($fixture_root) {
	remove_tree($fixture_root);
});

if(!copy($root.'view/img/logo.png', $fixture_root.'plugin/with_icon/icon.png')) {
	fail('failed to create existing-icon fixture');
}
if(!is_file($root.'view/img/logo.png')) {
	fail('core plugin fallback asset is missing');
}

define('DEBUG', 0);
define('APP_PATH', $fixture_root);

function array_value($array, $key, $default = NULL) {
	return isset($array[$key]) ? $array[$key] : $default;
}

function lang($key, $args = array()) {
	return $key;
}

include $root.'model/plugin.func.php';

$plugins = array(
	'missing_icon' => array('name'=>'Missing icon', 'installed'=>1, 'enable'=>1),
	'with_icon' => array('name'=>'Existing icon', 'installed'=>1, 'enable'=>1),
);
$official_plugins = array(
	'official_icon' => array('pluginid'=>42, 'name'=>'Official icon'),
);

$missing = plugin_read_by_dir('missing_icon');
$missing['icon_url'] === '../view/img/logo.png' || fail('local plugin without icon.png must use the core fallback asset');

$existing = plugin_read_by_dir('with_icon');
$existing['icon_url'] === '../plugin/with_icon/icon.png' || fail('local plugin with icon.png must keep its local icon URL');

$official = plugin_read_by_dir('official_icon', FALSE);
$official['icon_url'] === 'http://plugin.xiuno.com/upload/plugin/42/icon.png' || fail('official plugin must keep its remote icon URL');

echo "OK: plugin icon fallback checks passed\n";
