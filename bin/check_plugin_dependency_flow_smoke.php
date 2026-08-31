<?php

$root = dirname(__DIR__);
$app = $root.'/tmp/plugin_dependency_flow_smoke_app/';

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

function write_plugin($app, $dir, $installed, $enable, $version = '1.0.0', $dependencies = array()) {
	$path = $app.'plugin/'.$dir.'/';
	mkdir($path, 0777, TRUE);
	file_put_contents($path.'conf.json', json_encode(array(
		'name'=>$dir,
		'brief'=>'Dependency flow smoke fixture',
		'version'=>$version,
		'bbs_version'=>'4.0',
		'installed'=>$installed,
		'enable'=>$enable,
		'hooks_rank'=>array(),
		'dependencies'=>$dependencies,
	), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function run_child($root, $app, $dir, $action) {
	$code = <<<'PHP'
$root = %s;
$app = %s;
$dir = %s;
$dependency_action = %s;
defined('DEBUG') || define('DEBUG', 0);
defined('IN_CMD') || define('IN_CMD', 1);
defined('APP_PATH') || define('APP_PATH', $app);
defined('ADMIN_PATH') || define('ADMIN_PATH', $app.'admin/');
defined('XIUNOPHP_PATH') || define('XIUNOPHP_PATH', $root.'/xiunophp/');
$_SERVER['lang'] = array(
	'plugin_task_locked'=>'plugin task locked',
	'plugin_dependency_following'=>'{name}: {s}',
	'plugin_being_dependent_cant_delete'=>'DELETE {name}: {s}',
	'plugin_being_dependent_cant_disable'=>'DISABLE {name}: {s}',
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
plugin_check_dependency($dir, $dependency_action);
plugin_lock_end();
PHP;
	$code = sprintf($code, var_export($root, TRUE), var_export($app, TRUE), var_export($dir, TRUE), var_export($action, TRUE));
	$result = run_php_code($code);
	$exit = $result['exit'];
	if($exit !== 0) {
		fail("child $action dependency process failed:\n".$result['stdout']."\n".$result['stderr']);
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

write_plugin($app, 'app', 0, 0, '1.0.0', array(
	'missing'=>'1.0.0',
	'downloaded'=>'1.0.0',
	'disabled'=>'1.0.0',
	'old'=>'2.0.0',
	'ok'=>'1.0.0',
));
write_plugin($app, 'downloaded', 0, 0);
write_plugin($app, 'disabled', 1, 0);
write_plugin($app, 'old', 1, 1, '1.0.0');
write_plugin($app, 'ok', 1, 1, '1.0.0');

$output = run_child($root, $app, 'app', 'install');
if(is_file($app.'tmp/lock_plugin_task.lock')) {
	fail('plugin_task lock must be released after dependency install failure.');
}
if(strpos($output, '?plugin-read.htm&amp;dir=downloaded') === FALSE) {
	fail("Downloaded dependency should render a detail link.\n$output");
}
if(strpos($output, '?plugin-read.htm&amp;dir=missing') !== FALSE) {
	fail("Missing remote dependency must not render a dead detail link.\n$output");
}
if(strpos($output, 'not downloaded') === FALSE || strpos($output, 'downloaded, not installed') === FALSE || strpos($output, 'installed, disabled') === FALSE || strpos($output, 'version too low') === FALSE) {
	fail("Dependency failure output must include structured status text.\n$output");
}

write_plugin($app, 'shared', 1, 1);
write_plugin($app, 'uses_shared', 1, 1, '1.0.0', array('shared'=>'1.0.0'));
$output = run_child($root, $app, 'shared', 'unstall');
if(is_file($app.'tmp/lock_plugin_task.lock')) {
	fail('plugin_task lock must be released after reverse dependency failure.');
}
if(strpos($output, '?plugin-read.htm&amp;dir=uses_shared') === FALSE) {
	fail('Reverse dependency should render a local detail link.');
}
if(strpos($output, 'DELETE shared:') === FALSE || strpos($output, 'DISABLE shared:') !== FALSE) {
	fail("Uninstall dependency failure should retain delete semantics.\n$output");
}

$output = run_child($root, $app, 'shared', 'disable');
if(is_file($app.'tmp/lock_plugin_task.lock')) {
	fail('plugin_task lock must be released after disable dependency failure.');
}
if(strpos($output, 'DISABLE shared:') === FALSE || strpos($output, 'DELETE shared:') !== FALSE) {
	fail("Disable dependency failure should use disable semantics.\n$output");
}

rm_dir($app);

echo "OK: plugin dependency flow smoke checks passed\n";
