<?php

$root = dirname(__DIR__);
$app = $root.'/tmp/plugin_lifecycle_exit_smoke_app/';

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

function write_plugin($app, $dir, $installed, $enable, $lifecycle) {
	$path = $app.'plugin/'.$dir.'/';
	mkdir($path, 0777, TRUE);
	file_put_contents($path.'conf.json', json_encode(array(
		'name'=>$dir,
		'brief'=>'Lifecycle exit smoke fixture',
		'version'=>'1.0.0',
		'bbs_version'=>'4.0',
		'installed'=>$installed,
		'enable'=>$enable,
		'hooks_rank'=>array(),
		'dependencies'=>array(),
	), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
	file_put_contents($path.$lifecycle.'.php', "<?php\nexit;\n");
}

function write_plain_plugin($app, $dir, $installed, $enable) {
	$path = $app.'plugin/'.$dir.'/';
	mkdir($path, 0777, TRUE);
	file_put_contents($path.'conf.json', json_encode(array(
		'name'=>$dir,
		'brief'=>'Lifecycle exit smoke fixture',
		'version'=>'1.0.0',
		'bbs_version'=>'4.0',
		'installed'=>$installed,
		'enable'=>$enable,
		'hooks_rank'=>array(),
		'dependencies'=>array(),
	), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function package_conf($app, $dir) {
	$json = file_get_contents($app.'plugin/'.$dir.'/conf.json');
	$arr = json_decode($json, TRUE);
	if(!is_array($arr)) fail("$dir conf.json did not decode");
	return $arr;
}

function run_child($root, $app, $dir, $action) {
	$code = <<<'PHP'
$root = %s;
$app = %s;
$dir = %s;
$action = %s;
defined('DEBUG') || define('DEBUG', 0);
defined('APP_PATH') || define('APP_PATH', $app);
defined('ADMIN_PATH') || define('ADMIN_PATH', $app.'admin/');
defined('XIUNOPHP_PATH') || define('XIUNOPHP_PATH', $root.'/xiunophp/');
$_SERVER['lang'] = array('plugin_task_locked'=>'plugin task locked');
$_SERVER['time'] = time();
$_SERVER['ajax'] = 0;
$_SERVER['conf'] = array('tmp_path'=>$app.'tmp/', 'url_rewrite_on'=>0);
$_REQUEST = array(1=>'__noop');
$method = 'POST';
$time = $_SERVER['time'];
$conf = $_SERVER['conf'];
include $root.'/xiunophp/array.func.php';
include $root.'/xiunophp/misc.func.php';
include $root.'/model/misc.func.php';
include $root.'/model/plugin.func.php';
ob_start();
include $root.'/admin/route/plugin.php';
ob_end_clean();
plugin_lock_start();
$snapshot = plugin_state_snapshot($dir);
if($action === 'install') {
	plugin_require_state_write(plugin_install($dir), $dir, $snapshot);
	plugin_run_lifecycle($dir, 'install', $snapshot);
} else {
	plugin_require_state_write(plugin_unstall($dir), $dir, $snapshot);
	plugin_run_lifecycle($dir, 'unstall', $snapshot);
}
plugin_lock_end();
PHP;
	$code = sprintf($code, var_export($root, TRUE), var_export($app, TRUE), var_export($dir, TRUE), var_export($action, TRUE));
	$out = array();
	$exit = 0;
	exec(escapeshellarg(PHP_BINARY).' -r '.escapeshellarg($code).' 2>&1', $out, $exit);
	if($exit !== 0) {
		fail("child $action process failed:\n".implode("\n", $out));
	}
}

function run_same_type_child($root, $app, $dir) {
	$code = <<<'PHP'
$root = %s;
$app = %s;
$dir = %s;
defined('DEBUG') || define('DEBUG', 0);
defined('APP_PATH') || define('APP_PATH', $app);
defined('ADMIN_PATH') || define('ADMIN_PATH', $app.'admin/');
defined('XIUNOPHP_PATH') || define('XIUNOPHP_PATH', $root.'/xiunophp/');
$_SERVER['lang'] = array('plugin_task_locked'=>'plugin task locked');
$_SERVER['time'] = time();
$_SERVER['ajax'] = 0;
$_SERVER['conf'] = array('tmp_path'=>$app.'tmp/', 'url_rewrite_on'=>0);
$_REQUEST = array(1=>'__noop');
$method = 'POST';
$time = $_SERVER['time'];
$conf = $_SERVER['conf'];
include $root.'/xiunophp/array.func.php';
include $root.'/xiunophp/misc.func.php';
include $root.'/model/misc.func.php';
include $root.'/model/plugin.func.php';
ob_start();
include $root.'/admin/route/plugin.php';
ob_end_clean();
plugin_lock_start();
$snapshot = plugin_state_snapshot($dir);
plugin_require_state_write(plugin_install($dir), $dir, $snapshot);
plugin_run_lifecycle($dir, 'install', $snapshot);
plugin_check_auto_unstall_dependencies($dir);
plugin_auto_unstall_same_type($dir, $snapshot);
plugin_lock_end();
PHP;
	$code = sprintf($code, var_export($root, TRUE), var_export($app, TRUE), var_export($dir, TRUE));
	$out = array();
	$exit = 0;
	exec(escapeshellarg(PHP_BINARY).' -r '.escapeshellarg($code).' 2>&1', $out, $exit);
	if($exit !== 0) {
		fail("child same-type replacement process failed:\n".implode("\n", $out));
	}
}

rm_dir($app);
mkdir($app.'plugin/', 0777, TRUE);
mkdir($app.'tmp/', 0777, TRUE);
mkdir($app.'admin/', 0777, TRUE);

write_plugin($app, 'exit_install', 0, 0, 'install');
run_child($root, $app, 'exit_install', 'install');
$conf = package_conf($app, 'exit_install');
if(!empty($conf['installed']) || !empty($conf['enable'])) {
	fail('install.php exit must restore the original uninstalled state.');
}
if(is_file($app.'tmp/lock_plugin_task.lock')) {
	fail('plugin_task lock must be released after install.php exit.');
}

write_plugin($app, 'exit_unstall', 1, 1, 'unstall');
run_child($root, $app, 'exit_unstall', 'unstall');
$conf = package_conf($app, 'exit_unstall');
if(empty($conf['installed']) || empty($conf['enable'])) {
	fail('unstall.php exit must restore the original installed/enabled state.');
}
if(is_file($app.'tmp/lock_plugin_task.lock')) {
	fail('plugin_task lock must be released after unstall.php exit.');
}

write_plain_plugin($app, 'new_theme_demo', 0, 0);
write_plugin($app, 'old_theme_demo', 1, 1, 'unstall');
run_same_type_child($root, $app, 'new_theme_demo');
$new_conf = package_conf($app, 'new_theme_demo');
$old_conf = package_conf($app, 'old_theme_demo');
if(!empty($new_conf['installed']) || !empty($new_conf['enable'])) {
	fail('same-type replacement must restore the new plugin state after old unstall.php exit.');
}
if(empty($old_conf['installed']) || empty($old_conf['enable'])) {
	fail('same-type replacement must restore the old plugin state after old unstall.php exit.');
}
if(is_file($app.'tmp/lock_plugin_task.lock')) {
	fail('plugin_task lock must be released after same-type replacement exit.');
}

rm_dir($app);

echo "OK: plugin lifecycle exit smoke checks passed\n";
