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

function write_message_plugin($app, $dir, $installed, $enable, $code, $message, $lifecycle = 'install', $extra = array()) {
	write_plain_plugin($app, $dir, $installed, $enable);
	$source = "<?php\nmessage(".var_export($code, TRUE).", ".var_export($message, TRUE).", ".var_export($extra, TRUE).");\n";
	file_put_contents($app.'plugin/'.$dir.'/'.$lifecycle.'.php', $source);
}

function write_return_plugin($app, $dir, $installed, $enable, $lifecycle, $result = TRUE) {
	write_plain_plugin($app, $dir, $installed, $enable);
	file_put_contents($app.'plugin/'.$dir.'/'.$lifecycle.'.php', "<?php\nreturn ".var_export($result, TRUE).";\n");
}

function write_global_context_plugin($app, $dir) {
	write_plain_plugin($app, $dir, 0, 0);
	$source = <<<'PHP'
<?php
if($method !== 'POST' || $conf['sitename'] !== 'Lifecycle Smoke') {
	message(-1, 'Legacy lifecycle globals missing');
}
return TRUE;
PHP;
	file_put_contents($app.'plugin/'.$dir.'/install.php', $source);
}

function write_catching_message_plugin($app, $dir, $installed, $enable, $code, $message, $catch, $lifecycle = 'install') {
	write_plain_plugin($app, $dir, $installed, $enable);
	$marker = $app.'plugin/'.$dir.'/continued';
	$source = "<?php\ntry {\n\tmessage(".var_export($code, TRUE).", ".var_export($message, TRUE).");\n} catch(".$catch." \$e) {\n\tfile_put_contents(".var_export($marker, TRUE).", 'caught');\n}\nfile_put_contents(".var_export($marker, TRUE).", 'continued');\n";
	file_put_contents($app.'plugin/'.$dir.'/'.$lifecycle.'.php', $source);
}

function write_multiple_message_plugin($app, $dir, $installed, $enable) {
	write_plain_plugin($app, $dir, $installed, $enable);
	$source = <<<'PHP'
<?php
try {
	message(-1, 'First lifecycle message');
} catch(Throwable $e) {
}
try {
	message(0, 'Second lifecycle message');
} catch(Throwable $e) {
}
PHP;
	file_put_contents($app.'plugin/'.$dir.'/install.php', $source);
}

function write_success_message_then_throw_plugin($app, $dir) {
	write_plain_plugin($app, $dir, 0, 0);
	$source = <<<'PHP'
<?php
try {
	message(0, 'Success before real failure');
} catch(Throwable $e) {
	throw new RuntimeException('Failure after caught success');
}
PHP;
	file_put_contents($app.'plugin/'.$dir.'/install.php', $source);
}

function write_lock_probe_plugin($app, $dir) {
	write_plain_plugin($app, $dir, 0, 0);
	$probe = $app.'plugin/'.$dir.'/lock_probe';
	$source = "<?php\nfile_put_contents(".var_export($probe, TRUE).", is_file(\$conf['tmp_path'].'lock_plugin_task.lock') ? 'present' : 'missing');\nreturn TRUE;\n";
	file_put_contents($app.'plugin/'.$dir.'/install.php', $source);
}

function package_conf($app, $dir) {
	$json = file_get_contents($app.'plugin/'.$dir.'/conf.json');
	$arr = json_decode($json, TRUE);
	if(!is_array($arr)) fail("$dir conf.json did not decode");
	return $arr;
}

function set_plugin_exclusive_group($app, $dir, $group) {
	$path = $app.'plugin/'.$dir.'/conf.json';
	$conf = package_conf($app, $dir);
	$conf['exclusive_group'] = $group;
	file_put_contents($path, json_encode($conf, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function child_response($out, $label) {
	$json = trim(implode("\n", $out));
	if($json === '') fail("$label did not emit a response");
	$arr = json_decode($json, TRUE);
	if(!is_array($arr)) fail("$label emitted invalid JSON:\n$json");
	return $arr;
}

function assert_csrf_form($message, $label) {
	if(!is_string($message) || !preg_match_all('~<input\b[^>]*\sname\s*=\s*([\'\"]?)_token\1[^>]*>~i', $message, $matches)) {
		fail("$label must contain a CSRF token field.");
	}
	if(count($matches[0]) !== 1 || !preg_match('~\svalue\s*=\s*([\'\"])([^\'\"]+)\1~i', $matches[0][0])) {
		fail("$label must contain exactly one non-empty CSRF token field.");
	}
}

function run_php_child($app, $prefix, $code, $label) {
	$childfile = tempnam($app.'tmp/', 'child_'.$prefix.'_');
	if($childfile === FALSE) fail("$label temp file creation failed");
	$out = array();
	$exit = 0;
	try {
		if(file_put_contents($childfile, "<?php\n".$code) === FALSE) fail("$label temp file write failed");
		exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($childfile).' 2>&1', $out, $exit);
	} finally {
		if(is_file($childfile)) unlink($childfile);
	}
	if($exit !== 0) fail("$label process failed:\n".implode("\n", $out));
	return $out;
}

function run_child($root, $app, $dir, $action) {
	$code = <<<'PHP'
$root = %s;
$app = %s;
$dir = %s;
$lifecycle_action = %s;
defined('DEBUG') || define('DEBUG', 0);
defined('APP_PATH') || define('APP_PATH', $app);
defined('ADMIN_PATH') || define('ADMIN_PATH', $app.'admin/');
defined('XIUNOPHP_PATH') || define('XIUNOPHP_PATH', $root.'/xiunophp/');
$_SERVER['lang'] = array('plugin_task_locked'=>'plugin task locked');
$_SERVER['time'] = time();
$_SERVER['ajax'] = 0;
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['conf'] = array('tmp_path'=>$app.'tmp/', 'log_path'=>$app.'log/', 'url_rewrite_on'=>0, 'sitename'=>'Lifecycle Smoke');
$_REQUEST = array(1=>'__noop');
$method = 'POST';
$time = $_SERVER['time'];
$conf = $_SERVER['conf'];
$ajax = 1;
include $root.'/xiunophp/array.func.php';
include $root.'/xiunophp/misc.func.php';
include $root.'/xiunophp/logger.func.php';
include $root.'/model/misc.func.php';
include $root.'/model/plugin.func.php';
ob_start();
include $root.'/admin/route/plugin.php';
ob_end_clean();
plugin_lock_start();
$snapshot = plugin_state_snapshot($dir);
if($lifecycle_action === 'install') {
	plugin_require_state_write(plugin_install($dir), $dir, $snapshot);
	$lifecycle_result = plugin_run_lifecycle($dir, 'install', $snapshot);
} else {
	plugin_require_state_write(plugin_unstall($dir), $dir, $snapshot);
	$lifecycle_result = plugin_run_lifecycle($dir, 'unstall', $snapshot);
}
plugin_lock_end();
	if(isset($lifecycle_result) && is_array($lifecycle_result)) echo json_encode($lifecycle_result);
PHP;
	$code = sprintf($code, var_export($root, TRUE), var_export($app, TRUE), var_export($dir, TRUE), var_export($action, TRUE));
	return run_php_child($app, $action, $code, "child $action");
}

function run_route_child($root, $app, $dir, $action) {
	$code = <<<'PHP'
$root = %s;
$app = %s;
$dir = %s;
$route_action = %s;
defined('DEBUG') || define('DEBUG', 0);
defined('APP_PATH') || define('APP_PATH', $app);
defined('ADMIN_PATH') || define('ADMIN_PATH', $app.'admin/');
defined('XIUNOPHP_PATH') || define('XIUNOPHP_PATH', $root.'/xiunophp/');
$_SERVER['lang'] = array('plugin_task_locked'=>'plugin task locked');
$_SERVER['time'] = time();
$_SERVER['ajax'] = 1;
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['conf'] = array('tmp_path'=>$app.'tmp/', 'log_path'=>$app.'log/', 'url_rewrite_on'=>0, 'sitename'=>'Lifecycle Smoke');
$_REQUEST = array(1=>$route_action, 2=>$dir);
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
function kv__get($key) { return NULL; }
function kv_set($key, $value) { return TRUE; }
function setting_row_update_atomic($mutator) {
	$next = $mutator(array());
	return $next === FALSE ? FALSE : TRUE;
}
include $root.'/admin/route/plugin.php';
PHP;
	$code = sprintf($code, var_export($root, TRUE), var_export($app, TRUE), var_export($dir, TRUE), var_export($action, TRUE));
	return run_php_child($app, 'route_'.$action, $code, "route child $action");
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
$_SERVER['conf'] = array('tmp_path'=>$app.'tmp/', 'log_path'=>$app.'log/', 'url_rewrite_on'=>0, 'sitename'=>'Lifecycle Smoke');
$_REQUEST = array(1=>'__noop');
$method = 'POST';
$time = $_SERVER['time'];
$conf = $_SERVER['conf'];
include $root.'/xiunophp/array.func.php';
include $root.'/xiunophp/misc.func.php';
include $root.'/xiunophp/logger.func.php';
include $root.'/model/misc.func.php';
include $root.'/model/plugin.func.php';
ob_start();
include $root.'/admin/route/plugin.php';
ob_end_clean();
plugin_lock_start();
$replacement_dirs = plugin_require_auto_unstall_contract($dir);
plugin_check_auto_unstall_dependencies($dir, $replacement_dirs);
$snapshot = plugin_state_snapshot($dir);
plugin_require_state_write(plugin_install($dir), $dir, $snapshot);
plugin_run_lifecycle($dir, 'install', $snapshot);
plugin_auto_unstall_same_type($dir, $snapshot, $replacement_dirs);
plugin_lock_end();
PHP;
	$code = sprintf($code, var_export($root, TRUE), var_export($app, TRUE), var_export($dir, TRUE));
	run_php_child($app, 'same_type', $code, 'child same-type replacement');
}

rm_dir($app);
mkdir($app.'plugin/', 0777, TRUE);
mkdir($app.'tmp/', 0777, TRUE);
mkdir($app.'admin/', 0777, TRUE);
mkdir($app.'log/', 0777, TRUE);

write_plugin($app, 'exit_install', 0, 0, 'install');
run_child($root, $app, 'exit_install', 'install');
$conf = package_conf($app, 'exit_install');
if(!empty($conf['installed']) || !empty($conf['enable'])) {
	fail('install.php exit must restore the original uninstalled state.');
}
if(is_file($app.'tmp/lock_plugin_task.lock')) {
	fail('plugin_task lock must be released after install.php exit.');
}

write_plain_plugin($app, 'no_lifecycle_install', 0, 0);
$no_lifecycle_out = run_child($root, $app, 'no_lifecycle_install', 'install');
$conf = package_conf($app, 'no_lifecycle_install');
if(empty($conf['installed']) || empty($conf['enable'])) {
	fail('plugin without install.php must preserve the installed state.');
}
if(!empty($no_lifecycle_out)) {
	fail('plugin without install.php must not emit a controlled lifecycle response.');
}

write_return_plugin($app, 'return_install', 0, 0, 'install');
$return_out = run_child($root, $app, 'return_install', 'install');
$conf = package_conf($app, 'return_install');
if(empty($conf['installed']) || empty($conf['enable'])) {
	fail('install.php normal return must preserve the installed state.');
}
if(!empty($return_out)) {
	fail('install.php normal return must not emit a controlled lifecycle response.');
}

write_global_context_plugin($app, 'global_context_install');
$global_context_out = run_child($root, $app, 'global_context_install', 'install');
$conf = package_conf($app, 'global_context_install');
if(empty($conf['installed']) || empty($conf['enable']) || !empty($global_context_out)) {
	fail('install.php must retain legacy access to global request and configuration variables.');
}

write_return_plugin($app, 'false_install', 0, 0, 'install', FALSE);
$false_install_response = child_response(run_child($root, $app, 'false_install', 'install'), 'false install return');
$conf = package_conf($app, 'false_install');
if(!empty($conf['installed']) || !empty($conf['enable']) || (string)$false_install_response['code'] !== '-1') {
	fail('install.php FALSE return must restore the original state and emit an error.');
}

write_catching_message_plugin($app, 'exception_catch_install', 0, 0, 0, 'Exception catch bypassed', 'Exception');
$exception_catch_response = child_response(run_child($root, $app, 'exception_catch_install', 'install'), 'Exception catch install message');
if(is_file($app.'plugin/exception_catch_install/continued') || $exception_catch_response['message'] !== 'Exception catch bypassed') {
	fail('Legacy catch(Exception) blocks must not intercept lifecycle message termination.');
}

write_catching_message_plugin($app, 'throwable_catch_failed_install', 0, 0, -1, 'Caught failure still rolls back', 'Throwable');
$throwable_catch_response = child_response(run_child($root, $app, 'throwable_catch_failed_install', 'install'), 'Throwable catch install message');
$conf = package_conf($app, 'throwable_catch_failed_install');
if(!is_file($app.'plugin/throwable_catch_failed_install/continued') || !empty($conf['installed']) || !empty($conf['enable']) || $throwable_catch_response['message'] !== 'Caught failure still rolls back') {
	fail('A lifecycle message caught by Throwable must remain pending and restore state after the include returns.');
}

write_multiple_message_plugin($app, 'multiple_message_install', 0, 0);
$multiple_message_response = child_response(run_child($root, $app, 'multiple_message_install', 'install'), 'multiple install messages');
$conf = package_conf($app, 'multiple_message_install');
if(!empty($conf['installed']) || !empty($conf['enable']) || $multiple_message_response['message'] !== 'First lifecycle message' || (string)$multiple_message_response['code'] !== '-1') {
	fail('The first lifecycle message must remain authoritative when broad catches invoke message() again.');
}

write_success_message_then_throw_plugin($app, 'success_then_throw_install');
$success_then_throw_response = child_response(run_child($root, $app, 'success_then_throw_install', 'install'), 'success message followed by real failure');
$conf = package_conf($app, 'success_then_throw_install');
if(!empty($conf['installed']) || !empty($conf['enable']) || (string)$success_then_throw_response['code'] !== '-1' || strpos($success_then_throw_response['message'], 'Failure after caught success') === FALSE) {
	fail('A real Throwable after a caught success message must take precedence and roll back lifecycle state.');
}

write_message_plugin($app, 'message_deferred_install', 0, 0, 0, '<form method="post" action="./?plugin-install-message_deferred_install.htm"></form>');
$deferred_out = run_child($root, $app, 'message_deferred_install', 'install');
$deferred_response = child_response($deferred_out, 'deferred install message');
$conf = package_conf($app, 'message_deferred_install');
if(!empty($conf['installed']) || !empty($conf['enable'])) {
	fail('install.php deferred message form must restore the original uninstalled state.');
}
if((string)$deferred_response['code'] !== '0' || strpos($deferred_response['message'], '<form') === FALSE || strpos($deferred_response['message'], 'name="_token"') === FALSE) {
	fail('deferred install message must preserve the wizard form and inject a CSRF token.');
}
if(is_file($app.'tmp/lock_plugin_task.lock')) {
	fail('plugin_task lock must be released after deferred install message.');
}

write_message_plugin($app, 'message_encoded_method_install', 0, 0, 0, '<form method="p&#111;st" action="./?plugin-install-message_encoded_method_install.htm"></form>');
$encoded_method_response = child_response(run_child($root, $app, 'message_encoded_method_install', 'install'), 'encoded-method deferred install message');
assert_csrf_form($encoded_method_response['message'], 'encoded-method deferred install message');
$conf = package_conf($app, 'message_encoded_method_install');
if(!empty($conf['installed']) || !empty($conf['enable'])) {
	fail('HTML-decoded POST method must keep an install wizard deferred and restore the original state.');
}

$literal_token_form = '<form method="post" action="./?plugin-install-message_literal_token_install.htm"><input type="hidden" name="_token" value="<?php echo csrf_token(); ?>"><p>中文安装向导</p></form>';
write_message_plugin($app, 'message_literal_token_install', 0, 0, 0, $literal_token_form);
$literal_token_response = child_response(run_child($root, $app, 'message_literal_token_install', 'install'), 'literal-token deferred install message');
$conf = package_conf($app, 'message_literal_token_install');
if(!empty($conf['installed']) || !empty($conf['enable'])) {
	fail('install wizard with a literal PHP token must restore the original uninstalled state.');
}
assert_csrf_form($literal_token_response['message'], 'literal-token deferred install message');
if(strpos($literal_token_response['message'], '<?php') !== FALSE || !preg_match('~name="_token" value="[a-f0-9]{64}"~', $literal_token_response['message'])) {
	fail('literal PHP token must be replaced with the current session CSRF token.');
}
if(preg_match('~name="_token" value="[a-f0-9]{64}">">~', $literal_token_response['message'])) {
	fail('literal PHP token replacement must consume the complete input tag without leaving a quote suffix.');
}
if(strpos($literal_token_response['message'], '中文安装向导') === FALSE) {
	fail('CSRF token replacement must preserve UTF-8 form content.');
}

write_message_plugin($app, 'message_deferred_path_install', 0, 0, 0, '<form action="./?/plugin/install/message_deferred_path_install" method="post"></form>');
$deferred_path_out = run_child($root, $app, 'message_deferred_path_install', 'install');
$deferred_path_response = child_response($deferred_path_out, 'path-format deferred install message');
assert_csrf_form($deferred_path_response['message'], 'path-format deferred install message');
$conf = package_conf($app, 'message_deferred_path_install');
if(!empty($conf['installed']) || !empty($conf['enable'])) {
	fail('path-format install wizard form must restore the original uninstalled state.');
}
if(is_file($app.'tmp/lock_plugin_task.lock')) {
	fail('plugin_task lock must be released after path-format deferred install message.');
}

write_message_plugin($app, 'message_deferred_actionless_install', 0, 0, 0, '<form method="post"></form>');
$deferred_actionless_out = run_child($root, $app, 'message_deferred_actionless_install', 'install');
$deferred_actionless_response = child_response($deferred_actionless_out, 'actionless deferred install message');
assert_csrf_form($deferred_actionless_response['message'], 'actionless deferred install message');
$conf = package_conf($app, 'message_deferred_actionless_install');
if(!empty($conf['installed']) || !empty($conf['enable'])) {
	fail('actionless install wizard form must restore the original uninstalled state.');
}
if(is_file($app.'tmp/lock_plugin_task.lock')) {
	fail('plugin_task lock must be released after actionless deferred install message.');
}

write_message_plugin($app, 'message_non_wizard_post_install', 0, 0, 0, '<form method="post" action="./?plugin-setting-message_non_wizard_post_install.htm"></form>');
$non_wizard_out = run_child($root, $app, 'message_non_wizard_post_install', 'install');
$non_wizard_response = child_response($non_wizard_out, 'non-wizard post install message');
$conf = package_conf($app, 'message_non_wizard_post_install');
if(empty($conf['installed']) || empty($conf['enable'])) {
	fail('install.php success message with a non-wizard POST form must preserve the installed state.');
}
if((string)$non_wizard_response['code'] !== '0' || strpos($non_wizard_response['message'], '<form') === FALSE) {
	fail('non-wizard POST install message must preserve its success response.');
}
assert_csrf_form($non_wizard_response['message'], 'non-wizard POST install message');
if(is_file($app.'tmp/lock_plugin_task.lock')) {
	fail('plugin_task lock must be released after non-wizard POST install message.');
}

write_message_plugin($app, 'message_other_plugin_install', 0, 0, 0, '<form method="post" action="./?plugin-install-another_plugin.htm"></form>');
$other_plugin_response = child_response(run_child($root, $app, 'message_other_plugin_install', 'install'), 'other-plugin install form');
$conf = package_conf($app, 'message_other_plugin_install');
if(empty($conf['installed']) || empty($conf['enable'])) {
	fail('An install form for another plugin must not defer the current plugin lifecycle.');
}
assert_csrf_form($other_plugin_response['message'], 'other-plugin install form');

write_message_plugin($app, 'message_external_action_install', 0, 0, 0, '<form method="post" action="https://evil.example/?plugin-install-message_external_action_install.htm"></form>');
$external_action_response = child_response(run_child($root, $app, 'message_external_action_install', 'install'), 'external-action install form');
$conf = package_conf($app, 'message_external_action_install');
if(empty($conf['installed']) || empty($conf['enable']) || strpos($external_action_response['message'], 'name="_token"') !== FALSE) {
	fail('An external form action must not defer lifecycle state or receive the local CSRF token.');
}

write_message_plugin($app, 'message_cross_scheme_install', 0, 0, 0, '<form method="post" action="https://localhost/?plugin-install-message_cross_scheme_install.htm"></form>');
$cross_scheme_response = child_response(run_child($root, $app, 'message_cross_scheme_install', 'install'), 'cross-scheme install form');
$conf = package_conf($app, 'message_cross_scheme_install');
if(empty($conf['installed']) || empty($conf['enable']) || strpos($cross_scheme_response['message'], 'name="_token"') !== FALSE) {
	fail('A cross-scheme absolute action must not defer lifecycle state or receive the local CSRF token.');
}

write_message_plugin($app, 'message_redirect_param_install', 0, 0, 0, '<form method="post" action="./?next=plugin-install-message_redirect_param_install.htm"></form>');
$redirect_param_response = child_response(run_child($root, $app, 'message_redirect_param_install', 'install'), 'redirect-parameter install form');
$conf = package_conf($app, 'message_redirect_param_install');
if(empty($conf['installed']) || empty($conf['enable'])) {
	fail('A lifecycle route appearing only inside a query value must not defer plugin state.');
}
assert_csrf_form($redirect_param_response['message'], 'redirect-parameter install form');

write_message_plugin($app, 'message_local_absolute_install', 0, 0, 0, '<form method="post" action="http://localhost/?plugin-install-message_local_absolute_install.htm"></form>');
$local_absolute_response = child_response(run_child($root, $app, 'message_local_absolute_install', 'install'), 'same-origin absolute install form');
$conf = package_conf($app, 'message_local_absolute_install');
if(!empty($conf['installed']) || !empty($conf['enable'])) {
	fail('A same-origin absolute action targeting the current install route must defer lifecycle state.');
}
assert_csrf_form($local_absolute_response['message'], 'same-origin absolute install form');

write_message_plugin($app, 'message_query_args_install', 0, 0, 0, '<form method="post" action="./?plugin-install-message_query_args_install.htm&amp;step=2"></form>');
$query_args_response = child_response(run_child($root, $app, 'message_query_args_install', 'install'), 'query-args install form');
$conf = package_conf($app, 'message_query_args_install');
if(!empty($conf['installed']) || !empty($conf['enable'])) {
	fail('The exact current lifecycle route must remain deferred when it has query arguments.');
}
assert_csrf_form($query_args_response['message'], 'query-args install form');

write_message_plugin($app, 'message_path_query_args_install', 0, 0, 0, '<form method="post" action="plugin-install-message_path_query_args_install.htm?step=2"></form>');
$path_query_args_response = child_response(run_child($root, $app, 'message_path_query_args_install', 'install'), 'path-query-args install form');
$conf = package_conf($app, 'message_path_query_args_install');
if(!empty($conf['installed']) || !empty($conf['enable'])) {
	fail('A path-rewrite lifecycle route must remain deferred when ordinary query arguments are present.');
}
assert_csrf_form($path_query_args_response['message'], 'path-query-args install form');

write_message_plugin($app, 'message_success_install', 0, 0, 0, 'Install complete', 'install', array('marker'=>'install-success'));
$success_out = run_child($root, $app, 'message_success_install', 'install');
$success_response = child_response($success_out, 'successful install message');
$conf = package_conf($app, 'message_success_install');
if(empty($conf['installed']) || empty($conf['enable'])) {
	fail("install.php success message must preserve the installed state.\n".implode("\n", $success_out));
}
if((string)$success_response['code'] !== '0' || $success_response['message'] !== 'Install complete' || $success_response['extra']['marker'] !== 'install-success') {
	fail('install.php success message must preserve code, message, and extra fields.');
}
if(is_file($app.'tmp/lock_plugin_task.lock')) {
	fail('plugin_task lock must be released after successful install message.');
}

write_message_plugin($app, 'message_failed_install', 0, 0, -1, 'Install failed');
$failed_out = run_child($root, $app, 'message_failed_install', 'install');
$failed_response = child_response($failed_out, 'failed install message');
$conf = package_conf($app, 'message_failed_install');
if(!empty($conf['installed']) || !empty($conf['enable'])) {
	fail('install.php failure message must restore the original uninstalled state.');
}
if((string)$failed_response['code'] !== '-1' || $failed_response['message'] !== 'Install failed') {
	fail('install.php failure message must preserve its error response.');
}
if(is_file($app.'tmp/lock_plugin_task.lock')) {
	fail('plugin_task lock must be released after failed install message.');
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

write_plain_plugin($app, 'no_lifecycle_unstall', 1, 1);
$no_lifecycle_unstall_out = run_child($root, $app, 'no_lifecycle_unstall', 'unstall');
$conf = package_conf($app, 'no_lifecycle_unstall');
if(!empty($conf['installed']) || !empty($conf['enable']) || !empty($no_lifecycle_unstall_out)) {
	fail('Plugin without unstall.php must preserve the uninstalled state without a controlled response.');
}

write_return_plugin($app, 'return_unstall', 1, 1, 'unstall');
$return_unstall_out = run_child($root, $app, 'return_unstall', 'unstall');
$conf = package_conf($app, 'return_unstall');
if(!empty($conf['installed']) || !empty($conf['enable']) || !empty($return_unstall_out)) {
	fail('unstall.php normal return must preserve the uninstalled state without a controlled response.');
}

write_return_plugin($app, 'false_unstall', 1, 1, 'unstall', FALSE);
$false_unstall_response = child_response(run_child($root, $app, 'false_unstall', 'unstall'), 'false unstall return');
$conf = package_conf($app, 'false_unstall');
if(empty($conf['installed']) || empty($conf['enable']) || (string)$false_unstall_response['code'] !== '-1') {
	fail('unstall.php FALSE return must restore the installed state and emit an error.');
}

write_message_plugin($app, 'message_deferred_unstall', 1, 1, 0, '<form method="post" action="./?plugin-unstall-message_deferred_unstall.htm"></form>', 'unstall');
$deferred_unstall_response = child_response(run_child($root, $app, 'message_deferred_unstall', 'unstall'), 'deferred unstall message');
$conf = package_conf($app, 'message_deferred_unstall');
if(empty($conf['installed']) || empty($conf['enable'])) {
	fail('unstall.php wizard form targeting the current uninstall route must restore the installed state.');
}
assert_csrf_form($deferred_unstall_response['message'], 'deferred unstall message');

write_message_plugin($app, 'message_install_form_unstall', 1, 1, 0, '<form method="post" action="./?plugin-install-message_install_form_unstall.htm"></form>', 'unstall');
$install_form_unstall_response = child_response(run_child($root, $app, 'message_install_form_unstall', 'unstall'), 'install form from unstall message');
$conf = package_conf($app, 'message_install_form_unstall');
if(!empty($conf['installed']) || !empty($conf['enable'])) {
	fail('An install route form emitted by unstall.php must not defer the uninstall lifecycle.');
}
assert_csrf_form($install_form_unstall_response['message'], 'install form from unstall message');

write_message_plugin($app, 'message_success_unstall', 1, 1, 0, 'Uninstall complete', 'unstall', array('marker'=>'unstall-success'));
$unstall_success_out = run_child($root, $app, 'message_success_unstall', 'unstall');
$unstall_success_response = child_response($unstall_success_out, 'successful unstall message');
$conf = package_conf($app, 'message_success_unstall');
if(!empty($conf['installed']) || !empty($conf['enable'])) {
	fail('unstall.php success message must preserve the uninstalled state.');
}
if((string)$unstall_success_response['code'] !== '0' || $unstall_success_response['extra']['marker'] !== 'unstall-success') {
	fail('unstall.php success message must preserve code and extra fields.');
}
if(is_file($app.'tmp/lock_plugin_task.lock')) {
	fail('plugin_task lock must be released after successful unstall message.');
}

write_message_plugin($app, 'message_failed_unstall', 1, 1, -1, 'Uninstall failed', 'unstall');
$unstall_failed_out = run_child($root, $app, 'message_failed_unstall', 'unstall');
$unstall_failed_response = child_response($unstall_failed_out, 'failed unstall message');
$conf = package_conf($app, 'message_failed_unstall');
if(empty($conf['installed']) || empty($conf['enable'])) {
	fail('unstall.php failure message must restore the installed/enabled state.');
}
if((string)$unstall_failed_response['code'] !== '-1' || $unstall_failed_response['message'] !== 'Uninstall failed') {
	fail('unstall.php failure message must preserve its error response.');
}
if(is_file($app.'tmp/lock_plugin_task.lock')) {
	fail('plugin_task lock must be released after failed unstall message.');
}

write_plain_plugin($app, 'old_theme_route', 1, 1);
write_message_plugin($app, 'new_theme_route', 0, 0, 0, 'Route install complete', 'install', array('marker'=>'route-install-success'));
set_plugin_exclusive_group($app, 'old_theme_route', 'theme.route');
set_plugin_exclusive_group($app, 'new_theme_route', 'theme.route');
$route_install_response = child_response(run_route_child($root, $app, 'new_theme_route', 'install'), 'route install success message');
$new_route_conf = package_conf($app, 'new_theme_route');
$old_route_conf = package_conf($app, 'old_theme_route');
if(empty($new_route_conf['installed']) || empty($new_route_conf['enable']) || !empty($old_route_conf['installed']) || !empty($old_route_conf['enable'])) {
	fail('Real install route must finalize same-type replacement before emitting a controlled success response.');
}
if((string)$route_install_response['code'] !== '0' || $route_install_response['message'] !== 'Route install complete' || $route_install_response['marker'] !== 'route-install-success' || isset($route_install_response['extra'])) {
	fail('Real install route must merge controlled response extras into the public response shape.');
}
if(is_file($app.'tmp/lock_plugin_task.lock')) {
	fail('Real install route must release the task lock before finishing its controlled success response.');
}

write_message_plugin($app, 'route_success_unstall', 1, 1, 0, 'Route uninstall complete', 'unstall', array('marker'=>'route-unstall-success'));
$route_unstall_response = child_response(run_route_child($root, $app, 'route_success_unstall', 'unstall'), 'route unstall success message');
$route_unstall_conf = package_conf($app, 'route_success_unstall');
if(!empty($route_unstall_conf['installed']) || !empty($route_unstall_conf['enable']) || $route_unstall_response['marker'] !== 'route-unstall-success' || isset($route_unstall_response['extra'])) {
	fail('Real unstall route must preserve final state and public controlled response shape.');
}

write_message_plugin($app, 'old_theme_nested_message', 1, 1, 0, 'Old theme stopped replacement', 'unstall', array('marker'=>'nested-unstall-message'));
write_plain_plugin($app, 'new_theme_nested_message', 0, 0);
set_plugin_exclusive_group($app, 'old_theme_nested_message', 'theme.route');
set_plugin_exclusive_group($app, 'new_theme_nested_message', 'theme.route');
$nested_message_response = child_response(run_route_child($root, $app, 'new_theme_nested_message', 'install'), 'same-type nested unstall message');
$new_nested_conf = package_conf($app, 'new_theme_nested_message');
$old_nested_conf = package_conf($app, 'old_theme_nested_message');
$previous_theme_conf = package_conf($app, 'new_theme_route');
if(empty($new_nested_conf['installed']) || empty($new_nested_conf['enable']) || !empty($old_nested_conf['installed']) || !empty($old_nested_conf['enable']) || !empty($previous_theme_conf['installed']) || !empty($previous_theme_conf['enable'])) {
	fail('A completed message(0) from nested same-type unstall must commit the replacement state.');
}
if((string)$nested_message_response['code'] !== '0' || strpos($nested_message_response['message'], 'plugin_install_sucessfully') === FALSE || isset($nested_message_response['marker'])) {
	fail('Nested same-type unstall success must continue to the outer install completion response.');
}

// 锁存续断言：plugin_install() 的缓存清理不得删除自己持有的任务锁，生命周期脚本执行期间锁必须始终存在
write_lock_probe_plugin($app, 'lock_probe_install');
run_child($root, $app, 'lock_probe_install', 'install');
$lock_probe = @file_get_contents($app.'plugin/lock_probe_install/lock_probe');
if($lock_probe !== 'present') {
	fail('plugin_task lock must persist while install.php executes; cache cleanup must not delete the active lock.');
}
if(is_file($app.'tmp/lock_plugin_task.lock')) {
	fail('plugin_task lock must still be released after the lock persistence probe.');
}

// 缓存清理 allowlist 断言：只删除有明确 ownership 的编译缓存；锁、快照、更新数据、
// 图片 helper 临时文件和未知文件/目录全部保留。
$clear_tmp_code = <<<'PHP'
$root = %s;
$app = %s;
defined('DEBUG') || define('DEBUG', 0);
defined('APP_PATH') || define('APP_PATH', $app);
defined('XIUNOPHP_PATH') || define('XIUNOPHP_PATH', $root.'/xiunophp/');
$_SERVER['time'] = time();
$_SERVER['conf'] = array('tmp_path'=>$app.'tmp/', 'log_path'=>$app.'log/', 'url_rewrite_on'=>0);
$time = $_SERVER['time'];
$conf = $_SERVER['conf'];
include $root.'/xiunophp/array.func.php';
include $root.'/xiunophp/misc.func.php';
include $root.'/model/misc.func.php';
include $root.'/model/plugin.func.php';
$tmp = $conf['tmp_path'];
xn_lock_start('plugin_task', 300) || exit(1);
xn_lock_start('foo-bar', 300) || exit(1);
file_put_contents($tmp.'lock_update_task.lock', $time."\nother-owner");
mkdir($tmp.'plugin_backup_demo_1/', 0777, TRUE);
file_put_contents($tmp.'plugin_backup_demo_1/conf.json', '{}');
mkdir($tmp.'update_backup_20260101_000000/conf/', 0777, TRUE);
file_put_contents($tmp.'update_backup_20260101_000000/conf/conf.php', '<?php');
mkdir($tmp.'plugin_backup_stale_9/', 0777, TRUE);
file_put_contents($tmp.'plugin_backup_stale_9/conf.json', '{}');
touch($tmp.'plugin_backup_stale_9/conf.json', $time - 200000);
touch($tmp.'plugin_backup_stale_9', $time - 200000);
file_put_contents($tmp.'model.min.php', '<?php // cache');
file_put_contents($tmp.'model_misc.func.php', '<?php // cache');
file_put_contents($tmp.'model_misc.func.php.lock', '');
file_put_contents($tmp.'unknown.php', '<?php // locally owned helper');
file_put_contents($tmp.'image-active.tmp', 'image helper bytes');
mkdir($tmp.'compiled_dir/', 0777, TRUE);
file_put_contents($tmp.'compiled_dir/cache.php', '<?php // cache');
plugin_clear_tmp_dir();
echo json_encode(array(
	'plugin_lock'=>is_file($tmp.'lock_plugin_task.lock'),
	'hyphen_lock'=>is_file($tmp.'lock_foo-bar.lock'),
	'other_lock'=>is_file($tmp.'lock_update_task.lock'),
	'plugin_backup'=>is_file($tmp.'plugin_backup_demo_1/conf.json'),
	'update_backup'=>is_file($tmp.'update_backup_20260101_000000/conf/conf.php'),
	'stale_backup'=>is_dir($tmp.'plugin_backup_stale_9'),
	'model_cache'=>is_file($tmp.'model.min.php'),
	'include_cache'=>is_file($tmp.'model_misc.func.php'),
	'unknown_php'=>is_file($tmp.'unknown.php'),
	'image_tmp'=>is_file($tmp.'image-active.tmp'),
	'compiled_dir'=>is_dir($tmp.'compiled_dir'),
));
xn_unlink($tmp.'lock_update_task.lock');
xn_lock_end('foo-bar');
xn_lock_end('plugin_task');
PHP;
$clear_tmp_code = sprintf($clear_tmp_code, var_export($root, TRUE), var_export($app, TRUE));
$clear_tmp_result = child_response(run_php_child($app, 'clear_tmp_guard', $clear_tmp_code, 'clear-tmp protection child'), 'clear-tmp protection child');
$clear_tmp_expect = array(
	'plugin_lock'=>TRUE, 'hyphen_lock'=>TRUE, 'other_lock'=>TRUE, 'plugin_backup'=>TRUE, 'update_backup'=>TRUE,
	'stale_backup'=>TRUE, 'model_cache'=>FALSE, 'include_cache'=>FALSE,
	'unknown_php'=>TRUE, 'image_tmp'=>TRUE, 'compiled_dir'=>TRUE,
);
foreach($clear_tmp_expect as $key=>$expected) {
	$actual = isset($clear_tmp_result[$key]) ? $clear_tmp_result[$key] : NULL;
	if($actual !== $expected) {
		fail("plugin_clear_tmp_dir() protection contract failed: $key expected ".var_export($expected, TRUE).", got ".var_export($actual, TRUE).'.');
	}
}

// Keep the explicit-group rollback fixture isolated from the earlier route replacement family.
// Their legacy-looking names intentionally collide while their explicit groups differ, so a real
// administrator flow would require disabling or removing that active family before continuing.
foreach(array('old_theme_route', 'new_theme_route', 'old_theme_nested_message', 'new_theme_nested_message') as $fixture_dir) {
	rm_dir($app.'plugin/'.$fixture_dir);
}

write_plain_plugin($app, 'new_theme_demo', 0, 0);
write_plugin($app, 'old_theme_demo', 1, 1, 'unstall');
set_plugin_exclusive_group($app, 'new_theme_demo', 'theme.demo');
set_plugin_exclusive_group($app, 'old_theme_demo', 'theme.demo');
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

rm_dir($app.'plugin/new_theme_demo');
rm_dir($app.'plugin/old_theme_demo');
write_plain_plugin($app, 'new_theme_demo', 0, 0);
write_message_plugin($app, 'old_theme_demo', 1, 1, 0, 'Old theme removed', 'unstall');
set_plugin_exclusive_group($app, 'new_theme_demo', 'theme.demo');
set_plugin_exclusive_group($app, 'old_theme_demo', 'theme.demo');
run_same_type_child($root, $app, 'new_theme_demo');
$new_conf = package_conf($app, 'new_theme_demo');
$old_conf = package_conf($app, 'old_theme_demo');
if(empty($new_conf['installed']) || empty($new_conf['enable'])) {
	fail('same-type replacement must commit the new theme after an old unstall message(0).');
}
if(!empty($old_conf['installed']) || !empty($old_conf['enable'])) {
	fail('same-type replacement must keep the old theme uninstalled after its completed message(0).');
}
if(is_file($app.'tmp/lock_plugin_task.lock')) {
	fail('plugin_task lock must be released after a successful same-type replacement message.');
}

rm_dir($app);

echo "OK: plugin lifecycle exit smoke checks passed\n";
