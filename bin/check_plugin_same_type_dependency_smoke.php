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

function write_plugin($app, $dir, $installed, $enable, $dependencies = array(), $exclusive_group = '') {
	$path = $app.'plugin/'.$dir.'/';
	mkdir($path, 0777, TRUE);
	$conf = array(
		'name'=>$dir,
		'brief'=>'Same type dependency smoke fixture',
		'version'=>'1.0.0',
		'bbs_version'=>'4.0',
		'installed'=>$installed,
		'enable'=>$enable,
		'hooks_rank'=>array(),
		'overwrites_rank'=>array(),
		'dependencies'=>$dependencies,
	);
	if($exclusive_group !== '') $conf['exclusive_group'] = $exclusive_group;
	file_put_contents($path.'conf.json', json_encode($conf, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
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
$replacement_dirs = plugin_require_auto_unstall_contract('new_theme_demo');
plugin_check_auto_unstall_dependencies('new_theme_demo', $replacement_dirs);
$snapshot = plugin_state_snapshot('new_theme_demo');
plugin_require_state_write(plugin_install('new_theme_demo'), 'new_theme_demo', $snapshot);
plugin_auto_unstall_same_type('new_theme_demo', $snapshot, $replacement_dirs);
plugin_lock_end();
PHP;
	$code = sprintf($code, var_export($root, TRUE), var_export($app, TRUE));
	$result = run_php_code($code);
	if($result['exit'] !== 0) {
		fail("child same-type dependency process failed:\n".$result['stdout']."\n".$result['stderr']);
	}
	return $result['stdout'].$result['stderr'];
}

function run_install_route_child($root, $app, $dir) {
	$code = <<<'PHP'
$root = %s;
$app = %s;
$dir = %s;
defined('DEBUG') || define('DEBUG', 0);
defined('APP_PATH') || define('APP_PATH', $app);
defined('ADMIN_PATH') || define('ADMIN_PATH', $app.'admin/');
defined('XIUNOPHP_PATH') || define('XIUNOPHP_PATH', $root.'/xiunophp/');
$_SERVER['lang'] = array(
	'plugin_task_locked'=>'plugin task locked',
	'plugin_state_storage_readonly'=>'runtime storage unavailable: {file}',
	'format_maybe_error'=>'invalid metadata',
);
$_SERVER['time'] = time();
$_SERVER['ajax'] = 1;
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['conf'] = array(
	'sitename'=>'smoke',
	'tmp_path'=>$app.'tmp/',
	'log_path'=>$app.'log/',
	'url_rewrite_on'=>0,
);
$_REQUEST = array(1=>'install', 2=>$dir);
$method = 'POST';
$time = $_SERVER['time'];
$conf = $_SERVER['conf'];
$ajax = 1;
$header = array();
include $root.'/xiunophp/array.func.php';
include $root.'/xiunophp/misc.func.php';
include $root.'/xiunophp/logger.func.php';
include $root.'/model/misc.func.php';
include $root.'/model/check.func.php';
include $root.'/model/plugin.func.php';
include $root.'/admin/route/plugin.php';
PHP;
	$code = sprintf($code, var_export($root, TRUE), var_export($app, TRUE), var_export($dir, TRUE));
	$result = run_php_code($code);
	if($result['exit'] !== 0) {
		fail("legacy route child failed:\n".$result['stdout']."\n".$result['stderr']);
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
mkdir($app.'log/', 0777, TRUE);

// Directory names are opaque identifiers. Similar names without an explicit shared group must not
// authorize, block, disable, or uninstall another package.
write_plugin($app, 'new_theme_legacy', 0, 0);
write_plugin($app, 'old_theme_legacy', 1, 1);
file_put_contents($app.'plugin/new_theme_legacy/install.php', "<?php\nfile_put_contents(".var_export($app.'new_install_ran', TRUE).", 'yes');\n");
file_put_contents($app.'plugin/old_theme_legacy/unstall.php', "<?php\nfile_put_contents(".var_export($app.'old_unstall_ran', TRUE).", 'yes');\n");
$old_before = file_get_contents($app.'plugin/old_theme_legacy/conf.json');
$legacy_output = run_install_route_child($root, $app, 'new_theme_legacy');
$opaque_new = read_conf($app, 'new_theme_legacy');
$opaque_old = read_conf($app, 'old_theme_legacy');
if(empty($opaque_new['installed']) || empty($opaque_new['enable']) || empty($opaque_old['installed']) || empty($opaque_old['enable'])) {
	fail("similar directory names without metadata must coexist without a guessed state transition.\n$legacy_output");
}
if(file_get_contents($app.'plugin/old_theme_legacy/conf.json') !== $old_before) {
	fail('an opaque existing package must retain its exact local state when the new package has no explicit group.');
}
if(!is_file($app.'new_install_ran') || is_file($app.'old_unstall_ran')) {
	fail('an ungrouped install must run only its own lifecycle and never invoke a name-guessed package lifecycle.');
}
is_file($app.'tmp/lock_plugin_task.lock') && fail('plugin_task lock must be released after an ungrouped install.');

// A non-empty malformed declaration is not silently treated as absence, even when no legacy name
// happens to collide with it.
write_plugin($app, 'invalid_group_target', 0, 0, array(), 'Themes/Main');
file_put_contents($app.'plugin/invalid_group_target/install.php', "<?php\nfile_put_contents(".var_export($app.'invalid_group_install_ran', TRUE).", 'yes');\n");
$invalid_before = file_get_contents($app.'plugin/invalid_group_target/conf.json');
$invalid_output = run_install_route_child($root, $app, 'invalid_group_target');
if(strpos($invalid_output, 'conf.json invalid metadata') === FALSE
	|| file_get_contents($app.'plugin/invalid_group_target/conf.json') !== $invalid_before
	|| is_file($app.'invalid_group_install_ran')) {
	fail("invalid non-empty exclusive_group metadata must fail before state or lifecycle changes.\n$invalid_output");
}

rm_dir($app);
mkdir($app.'plugin/', 0777, TRUE);
mkdir($app.'tmp/', 0777, TRUE);
mkdir($app.'admin/', 0777, TRUE);
mkdir($app.'log/', 0777, TRUE);

// A candidate can lose its storage capability after preflight because an earlier third-party
// lifecycle has arbitrary filesystem side effects. The core cannot undo that external rename, but
// it must restore the new package and every earlier candidate before returning the residual error.
write_plugin($app, 'new_runtime_target', 0, 0, array(), 'runtime.group');
write_plugin($app, 'alpha_runtime_old', 1, 1, array(), 'runtime.group');
write_plugin($app, 'omega_runtime_old', 1, 1, array(), 'runtime.group');
file_put_contents($app.'plugin/new_runtime_target/install.php', "<?php\nfile_put_contents(".var_export($app.'runtime_install_ran', TRUE).", 'yes');\n");
$omega_conf = $app.'plugin/omega_runtime_old/conf.json';
$omega_blocked = $app.'plugin/omega_runtime_old/conf.blocked';
$alpha_unstall = "<?php\nif(!rename(".var_export($omega_conf, TRUE).", ".var_export($omega_blocked, TRUE).")) message(-1, 'fixture rename failed');\nreturn TRUE;\n";
file_put_contents($app.'plugin/alpha_runtime_old/unstall.php', $alpha_unstall);
$runtime_output = run_install_route_child($root, $app, 'new_runtime_target');
if(!is_file($app.'runtime_install_ran')) {
	fail('execution-time storage-loss fixture must occur after the new install lifecycle ran.');
}
if(!is_file($omega_blocked) || is_file($omega_conf)) {
	fail('first replacement lifecycle must make the later candidate unavailable after preflight.');
}
$runtime_new = read_conf($app, 'new_runtime_target');
$runtime_alpha = read_conf($app, 'alpha_runtime_old');
if(!empty($runtime_new['installed']) || !empty($runtime_new['enable'])
	|| empty($runtime_alpha['installed']) || empty($runtime_alpha['enable'])) {
	fail('execution-time candidate storage loss must restore the new state and every earlier replacement state.');
}
if(strpos($runtime_output, 'runtime storage unavailable: plugin/omega_runtime_old/conf.json') === FALSE) {
	fail("execution-time candidate storage loss must identify the residual unavailable target.\n$runtime_output");
}
if(!rename($omega_blocked, $omega_conf)) fail('unable to restore the runtime storage-loss fixture file');
$runtime_omega = read_conf($app, 'omega_runtime_old');
if(empty($runtime_omega['installed']) || empty($runtime_omega['enable'])) {
	fail('the unavailable later candidate must remain untouched by core lifecycle mutation.');
}
if(is_file($app.'tmp/lock_plugin_task.lock')) {
	fail('plugin_task lock must be released after execution-time replacement rollback.');
}

rm_dir($app);
mkdir($app.'plugin/', 0777, TRUE);
mkdir($app.'tmp/', 0777, TRUE);
mkdir($app.'admin/', 0777, TRUE);
mkdir($app.'log/', 0777, TRUE);

write_plugin($app, 'new_theme_demo', 0, 0, array('legacy_base'=>'1.0.0'), 'theme.demo');
// This dependency does not match the historical _theme_ heuristic; the shared explicit group is
// the sole authority that includes it in the aggregate replacement and rollback set.
write_plugin($app, 'legacy_base', 1, 1, array(), 'theme.demo');
write_plugin($app, 'other_theme_demo', 1, 1, array(), 'theme.demo');

$output = run_child($root, $app);

if(is_file($app.'tmp/lock_plugin_task.lock')) {
	fail('plugin_task lock must be released after same-type dependency rollback.');
}

$new = read_conf($app, 'new_theme_demo');
$old = read_conf($app, 'legacy_base');
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
if(strpos($output, 'downloaded, not installed') === FALSE || strpos($output, '?plugin-read.htm&amp;dir=legacy_base') === FALSE) {
	fail("same-type dependency rollback must report structured dependency details.\n$output");
}

rm_dir($app);

echo "OK: same-type dependency rollback smoke checks passed\n";
