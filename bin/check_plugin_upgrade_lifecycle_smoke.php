<?php

$root = dirname(__DIR__);
$app = $root.'/tmp/plugin_upgrade_lifecycle_smoke_app/';

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

function write_package($base, $dir, $version, $installed, $enable, $marker, $upgrade_body = '') {
	$path = $base.$dir.'/';
	mkdir($path, 0777, TRUE);
	file_put_contents($path.'conf.json', json_encode(array(
		'name'=>$dir,
		'brief'=>'Upgrade lifecycle smoke fixture',
		'version'=>$version,
		'bbs_version'=>'4.0',
		'installed'=>$installed,
		'enable'=>$enable,
		'hooks_rank'=>array(),
		'overwrites_rank'=>array(),
		'dependencies'=>array(),
	), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
	file_put_contents($path.$marker, $marker);
	if($upgrade_body !== '') {
		file_put_contents($path.'upgrade.php', $upgrade_body);
	}
}

function read_conf($app, $dir) {
	$json = file_get_contents($app.'plugin/'.$dir.'/conf.json');
	$data = json_decode($json, TRUE);
	if(!is_array($data)) fail("Unable to read conf.json for $dir");
	return $data;
}

function setup_app($app, $mode) {
	rm_dir($app);
	mkdir($app.'plugin/', 0777, TRUE);
	mkdir($app.'incoming/', 0777, TRUE);
	mkdir($app.'tmp/', 0777, TRUE);
	mkdir($app.'admin/', 0777, TRUE);
	write_package($app.'plugin/', 'upgrade_demo', '1.0.0', 1, 1, 'old.marker');
	$upgrade = $mode == 'throw' ? "<?php\nthrow new RuntimeException('upgrade failed');\n" : "<?php\nexit;\n";
	write_package($app.'incoming/', 'upgrade_demo', '2.0.0', 1, 1, 'new.marker', $upgrade);
}

function run_child($root, $app) {
	$code = <<<'PHP'
$root = %s;
$app = %s;
defined('DEBUG') || define('DEBUG', 0);
defined('IN_CMD') || define('IN_CMD', 1);
defined('APP_PATH') || define('APP_PATH', $app);
defined('ADMIN_PATH') || define('ADMIN_PATH', $app.'admin/');
defined('XIUNOPHP_PATH') || define('XIUNOPHP_PATH', $root.'/xiunophp/');
$_SERVER['lang'] = array('plugin_task_locked'=>'plugin task locked');
$_SERVER['time'] = time();
$_SERVER['ajax'] = 0;
$_SERVER['conf'] = array(
	'sitename'=>'smoke',
	'tmp_path'=>$app.'tmp/',
	'url_rewrite_on'=>0,
);
$_REQUEST = array(1=>'__noop');
$method = 'POST';
$time = $_SERVER['time'];
$conf = $_SERVER['conf'];
$ajax = 0;
include $root.'/xiunophp/array.func.php';
include $root.'/xiunophp/misc.func.php';
include $root.'/model/misc.func.php';
include $root.'/model/plugin.func.php';
ob_start();
include $root.'/admin/route/plugin.php';
ob_end_clean();
plugin_lock_start();
$snapshot = plugin_state_snapshot('upgrade_demo');
$package_snapshot = plugin_package_snapshot('upgrade_demo');
rmdir_recusive($app.'plugin/upgrade_demo/', 0);
$error = '';
if(!plugin_copy_dir($app.'incoming/upgrade_demo/', $app.'plugin/upgrade_demo/', $error)) {
	plugin_package_restore($package_snapshot);
	plugin_message(-1, 'copy failed: '.$error);
}
plugin_reload_local('upgrade_demo', $snapshot, $package_snapshot);
plugin_run_lifecycle('upgrade_demo', 'upgrade', $snapshot, $package_snapshot);
plugin_lock_end();
PHP;
	$code = sprintf($code, var_export($root, TRUE), var_export($app, TRUE));
	$result = run_php_code($code);
	if($result['exit'] !== 0) {
		fail("child upgrade process failed:\n".$result['stdout']."\n".$result['stderr']);
	}
	return $result['stdout'].$result['stderr'];
}

function run_php_code($code) {
	$descriptor = array(
		0=>array('pipe', 'r'),
		1=>array('pipe', 'w'),
		2=>array('pipe', 'w'),
	);
	$process = proc_open(PHP_BINARY, $descriptor, $pipes);
	if(!is_resource($process)) fail('Unable to start child PHP process.');
	fwrite($pipes[0], "<?php\n".$code);
	fclose($pipes[0]);
	$stdout = stream_get_contents($pipes[1]);
	fclose($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[2]);
	$exit = proc_close($process);
	return array('exit'=>$exit, 'stdout'=>$stdout, 'stderr'=>$stderr);
}

function assert_restored($app, $mode) {
	$conf = read_conf($app, 'upgrade_demo');
	if($conf['version'] !== '1.0.0' || empty($conf['installed']) || empty($conf['enable'])) {
		fail("$mode upgrade failure must restore old conf.json state.");
	}
	if(!is_file($app.'plugin/upgrade_demo/old.marker')) {
		fail("$mode upgrade failure must restore old package files.");
	}
	if(is_file($app.'plugin/upgrade_demo/new.marker') || is_file($app.'plugin/upgrade_demo/upgrade.php')) {
		fail("$mode upgrade failure must remove replaced package files.");
	}
	if(is_file($app.'tmp/lock_plugin_task.lock')) {
		fail("$mode upgrade failure must release plugin_task lock.");
	}
	$backups = glob($app.'tmp/plugin_backup_upgrade_demo_*');
	if(!empty($backups)) {
		fail("$mode upgrade failure must clean package backup snapshots.");
	}
}

foreach(array('exit', 'throw') as $mode) {
	setup_app($app, $mode);
	run_child($root, $app);
	assert_restored($app, $mode);
}

rm_dir($app);

echo "OK: plugin upgrade lifecycle smoke checks passed\n";
