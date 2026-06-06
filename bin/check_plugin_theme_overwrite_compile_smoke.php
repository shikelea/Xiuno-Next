<?php

$root = dirname(__DIR__);
$app = $root.'/tmp/plugin_theme_overwrite_compile_smoke_app/';

function fail($message) {
	fwrite(STDERR, "FAIL: $message\n");
	exit(1);
}

function rm_dir($dir) {
	if(!is_dir($dir)) return;
	$items = glob(rtrim($dir, '/').'/*');
	if($items) {
		foreach($items as $item) {
			is_dir($item) ? rm_dir($item) : unlink($item);
		}
	}
	foreach(glob(rtrim($dir, '/').'/.??*') ?: array() as $item) {
		is_dir($item) ? rm_dir($item) : unlink($item);
	}
	rmdir($dir);
}

function write_plugin($app, $dir, $installed, $enable, $rank = 0, $body = '') {
	$path = $app.'plugin/'.$dir.'/';
	mkdir($path.'overwrite/view/htm/', 0777, TRUE);
	file_put_contents($path.'conf.json', json_encode(array(
		'name'=>$dir,
		'brief'=>'Theme overwrite compile smoke fixture',
		'version'=>'1.0.0',
		'bbs_version'=>'4.0',
		'installed'=>$installed,
		'enable'=>$enable,
		'hooks_rank'=>array(),
		'overwrites_rank'=>array('view/htm/theme_target.htm'=>$rank),
		'dependencies'=>array(),
	), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
	if($body !== '') {
		file_put_contents($path.'overwrite/view/htm/theme_target.htm', $body);
	}
}

function create_symlink_fixture($target, $link) {
	if(file_exists($link) || is_link($link)) @unlink($link);
	$ok = @symlink($target, $link);
	clearstatcache(TRUE, $link);
	return $ok && is_link($link);
}

rm_dir($app);
mkdir($app.'plugin/', 0777, TRUE);
mkdir($app.'tmp/', 0777, TRUE);
mkdir($app.'view/htm/', 0777, TRUE);

$target = $app.'view/htm/theme_target.htm';
file_put_contents($target, 'core-template');

write_plugin($app, 'low_theme_demo', 1, 1, 10, 'low-overwrite');
write_plugin($app, 'high_theme_demo', 1, 1, 30, 'high-overwrite');
write_plugin($app, 'disabled_theme_demo', 1, 0, 50, 'disabled-overwrite');
write_plugin($app, 'not_installed_theme_demo', 0, 1, 60, 'not-installed-overwrite');
write_plugin($app, 'symlink_theme_demo', 1, 1, 70);
$symlink_created = create_symlink_fixture($app.'outside_target.htm', $app.'plugin/symlink_theme_demo/overwrite/view/htm/theme_target.htm');
if($symlink_created) {
	file_put_contents($app.'outside_target.htm', 'symlink-overwrite');
}

defined('DEBUG') || define('DEBUG', 0);
defined('APP_PATH') || define('APP_PATH', $app);
defined('ADMIN_PATH') || define('ADMIN_PATH', $app.'admin/');
defined('XIUNOPHP_PATH') || define('XIUNOPHP_PATH', $root.'/xiunophp/');
$_SERVER['time'] = time();
$_SERVER['ajax'] = 0;
$_SERVER['lang'] = array(
	'no'=>'no',
	'yes'=>'yes',
);
$_SERVER['conf'] = array(
	'sitename'=>'smoke',
	'tmp_path'=>$app.'tmp/',
	'url_rewrite_on'=>0,
);
$conf = $_SERVER['conf'];

include $root.'/xiunophp/array.func.php';
include $root.'/xiunophp/misc.func.php';
include $root.'/model/plugin.func.php';

plugin_init();

$compiled = plugin_compile_srcfile($target);
if($compiled !== 'high-overwrite') {
	fail("Highest-rank enabled overwrite should win, got: $compiled");
}
if(strpos($compiled, 'disabled-overwrite') !== FALSE || strpos($compiled, 'not-installed-overwrite') !== FALSE) {
	fail('Disabled or not-installed overwrite packages must not participate in compilation.');
}
if(strpos($compiled, 'symlink-overwrite') !== FALSE) {
	fail('Symlink overwrite files must be skipped during template resolution.');
}
if($symlink_created && plugin_find_overwrite($target) === $app.'plugin/symlink_theme_demo/overwrite/view/htm/theme_target.htm') {
	fail('plugin_find_overwrite() must not select symlink overwrite files even when rank is highest.');
}

$_SERVER['conf']['disabled_plugin'] = 1;
$conf = $_SERVER['conf'];
$disabled = plugin_compile_srcfile($target);
if($disabled !== 'core-template') {
	fail('disabled_plugin mode must bypass theme overwrite compilation.');
}

rm_dir($app);

echo "OK: theme overwrite compile smoke checks passed\n";
