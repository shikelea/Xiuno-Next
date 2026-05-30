<?php

$root = dirname(__DIR__);
$app = $root.'/tmp/plugin_same_type_dependency_smoke_app/';

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

function write_plugin($app, $dir, $installed, $enable, $dependencies = array()) {
	$path = $app.'plugin/'.$dir.'/';
	mkdir($path, 0777, TRUE);
	file_put_contents($path.'conf.json', json_encode(array(
		'name'=>$dir,
		'brief'=>'Same type dependency smoke fixture',
		'version'=>'1.0.0',
		'bbs_version'=>'4.0',
		'installed'=>$installed,
		'enable'=>$enable,
		'hooks_rank'=>array(),
		'overwrites_rank'=>array(),
		'dependencies'=>$dependencies,
	), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function read_conf($app, $dir) {
	$json = file_get_contents($app.'plugin/'.$dir.'/conf.json');
	$data = json_decode($json, TRUE);
	if(!is_array($data)) fail("Unable to read conf.json for $dir");
	return $data;
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
$_SERVER['lang'] = array(
	'plugin_task_locked'=>'plugin task locked',
	'plugin_dependency_following'=>'{name}: {s}',
	'plugin_being_dependent_cant_delete'=>'{name}: {s}',
);
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
plugin_check_dependency('new_theme_demo', 'install');
plugin_check_auto_unstall_dependencies('new_theme_demo');
$snapshot = plugin_state_snapshot('new_theme_demo');
plugin_require_state_write(plugin_install('new_theme_demo'), 'new_theme_demo', $snapshot);
plugin_auto_unstall_same_type('new_theme_demo', $snapshot);
plugin_lock_end();
PHP;
	$code = sprintf($code, var_export($root, TRUE), var_export($app, TRUE));
	$result = run_php_code($code);
	if($result['exit'] !== 0) {
		fail("child same-type dependency process failed:\n".$result['stdout']."\n".$result['stderr']);
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

rm_dir($app);
mkdir($app.'plugin/', 0777, TRUE);
mkdir($app.'tmp/', 0777, TRUE);
mkdir($app.'admin/', 0777, TRUE);

write_plugin($app, 'new_theme_demo', 0, 0, array('old_theme_base'=>'1.0.0'));
write_plugin($app, 'old_theme_base', 1, 1);
write_plugin($app, 'other_theme_demo', 1, 1);

$output = run_child($root, $app);

if(is_file($app.'tmp/lock_plugin_task.lock')) {
	fail('plugin_task lock must be released after same-type dependency rollback.');
}

$new = read_conf($app, 'new_theme_demo');
$old = read_conf($app, 'old_theme_base');
$other = read_conf($app, 'other_theme_demo');

if(!empty($new['installed']) || !empty($new['enable'])) {
	fail('new theme state must roll back when auto-uninstall invalidates its dependency.');
}
if(empty($old['installed']) || empty($old['enable'])) {
	fail('dependency theme state must be restored after same-type dependency rollback.');
}
if(empty($other['installed']) || empty($other['enable'])) {
	fail('all same-type candidates touched in the batch must be restored.');
}
if(strpos($output, 'downloaded, not installed') === FALSE || strpos($output, 'plugin-read-old_theme_base') === FALSE) {
	fail("same-type dependency rollback must report structured dependency details.\n$output");
}

rm_dir($app);

echo "OK: same-type dependency rollback smoke checks passed\n";
