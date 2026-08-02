<?php

$root = dirname(__DIR__);
$app = $root.'/tmp/plugin_hook_context_compile_smoke_app/';

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

function write_plugin($app, $dir, $installed, $enable, $ranks = array(), $hooks = array()) {
	$path = $app.'plugin/'.$dir.'/';
	mkdir($path.'hook/', 0777, TRUE);
	file_put_contents($path.'conf.json', json_encode(array(
		'name'=>$dir,
		'brief'=>'Hook context compile smoke fixture',
		'version'=>'1.0.0',
		'bbs_version'=>'4.0',
		'installed'=>$installed,
		'enable'=>$enable,
		'hooks_rank'=>$ranks,
		'overwrites_rank'=>array(),
		'dependencies'=>array(),
	), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
	foreach($hooks as $name=>$body) {
		file_put_contents($path.'hook/'.$name, $body);
	}
}

function assert_contains($haystack, $needle, $message) {
	if(strpos($haystack, $needle) === FALSE) fail($message."\nCompiled:\n".$haystack);
}

function assert_not_contains($haystack, $needle, $message) {
	if(strpos($haystack, $needle) !== FALSE) fail($message."\nCompiled:\n".$haystack);
}

rm_dir($app);
mkdir($app.'plugin/', 0777, TRUE);
mkdir($app.'tmp/', 0777, TRUE);
mkdir($app.'route/', 0777, TRUE);
mkdir($app.'view/htm/', 0777, TRUE);
mkdir($app.'model/', 0777, TRUE);
mkdir($app.'lang/zh-cn/', 0777, TRUE);

$case_target = $app.'route/case_target.php';
$template_target = $app.'view/htm/template_target.htm';
$guard_target = $app.'route/guard_target.php';
$count_target = $app.'route/count_target.php';
$core_count_target = $app.'route/core_count_target.php';
$model_array_target = $app.'model.inc.php';
$lang_array_target = $app.'lang/zh-cn/bbs.php';
$template_count_target = $app.'view/htm/template_count_target.htm';
$hook_comment = '// hoo'.'k ';
$hook_template = '<!--{hoo'.'k template_hook.htm}-->';
$hook_template_count = '<!--{hoo'.'k template_count_hook.htm}-->';

file_put_contents($case_target, "<?php\nswitch(\$action) {\n".$hook_comment."case_hook.php\n\tdefault: echo 'default';\n}\n");
file_put_contents($template_target, 'before '.$hook_template.' after');
file_put_contents($guard_target, "<?php\n".$hook_comment."guarded_hook.php\n");
file_put_contents($count_target, "<?php\n\$compat_matches = array(array(), NULL);\n\$compat_count = -1;\n\$compat_comment_count = -1;\n".$hook_comment."count_hook.php\necho 'continued:'.\$compat_count.':'.\$compat_comment_count;\n");
file_put_contents($core_count_target, "<?php\n\$core_count = count(array(1, 2));\n");
file_put_contents($template_count_target, 'before '.$hook_template_count.' after');
file_put_contents($model_array_target, "<?php\n\$include_model_files = array(\n\tAPP_PATH.'model/core.func.php',\n\t".$hook_comment."model_inc_file.php\n);\n");
file_put_contents($lang_array_target, "<?php\nreturn array(\n\t'core_lang'=>'core',\n\t".$hook_comment."lang_zh_cn_bbs.php\n);\n");

write_plugin($app, 'enabled_low_hook', 1, 1, array(
	'case_hook.php'=>10,
	'template_hook.htm'=>10,
	'guarded_hook.php'=>10,
	'model_inc_file.php'=>10,
	'lang_zh_cn_bbs.php'=>10,
), array(
	'case_hook.php'=>"case 'enabled_low': echo 'enabled-low'; break;\n",
	'template_hook.htm'=>'low-template',
	'guarded_hook.php'=>"<?php exit; echo 'low-guarded'; ?>",
	'model_inc_file.php'=>"\tAPP_PATH.'model/low.func.php',\n",
	'lang_zh_cn_bbs.php'=>"\t'low_lang'=>'low',\n",
));

write_plugin($app, 'enabled_high_hook', 1, 1, array(
	'case_hook.php'=>30,
	'template_hook.htm'=>30,
	'guarded_hook.php'=>30,
	'count_hook.php'=>30,
	'template_count_hook.htm'=>30,
	'model_inc_file.php'=>30,
	'lang_zh_cn_bbs.php'=>30,
), array(
	'case_hook.php'=>"case 'enabled_high': echo 'enabled-high'; break;\n",
	'template_hook.htm'=>'high-template',
	'guarded_hook.php'=>"<?php exit; echo 'high-guarded'; ?>",
	'count_hook.php'=><<<'PHP'
<?php exit;
$compat_count = count($compat_matches[1]);
$compat_comment_count = COUNT /* legacy spacing */ ($compat_matches[1]);
if(FALSE) {
	$counter->count($compat_matches);
	PluginCounter::count($compat_matches);
	$counter?->count($compat_matches);
}
$compat_literal = 'count($literal)';
// count($comment)
PHP
,
	'template_count_hook.htm'=>'<?php $template_count = count(NULL); ?>',
	'model_inc_file.php'=>"\tAPP_PATH.'model/high.func.php',\n",
	'lang_zh_cn_bbs.php'=>"\t'high_lang'=>'high',\n",
));

write_plugin($app, 'disabled_hook', 1, 0, array(
	'case_hook.php'=>80,
	'template_hook.htm'=>80,
	'guarded_hook.php'=>80,
	'model_inc_file.php'=>80,
	'lang_zh_cn_bbs.php'=>80,
), array(
	'case_hook.php'=>"case 'disabled': echo 'disabled'; break;\n",
	'template_hook.htm'=>'disabled-template',
	'guarded_hook.php'=>"<?php exit; echo 'disabled-guarded'; ?>",
	'model_inc_file.php'=>"\tAPP_PATH.'model/disabled.func.php',\n",
	'lang_zh_cn_bbs.php'=>"\t'disabled_lang'=>'disabled',\n",
));

write_plugin($app, 'not_installed_hook', 0, 1, array(
	'case_hook.php'=>90,
	'template_hook.htm'=>90,
	'guarded_hook.php'=>90,
	'model_inc_file.php'=>90,
	'lang_zh_cn_bbs.php'=>90,
), array(
	'case_hook.php'=>"case 'not_installed': echo 'not-installed'; break;\n",
	'template_hook.htm'=>'not-installed-template',
	'guarded_hook.php'=>"<?php exit; echo 'not-installed-guarded'; ?>",
	'model_inc_file.php'=>"\tAPP_PATH.'model/not-installed.func.php',\n",
	'lang_zh_cn_bbs.php'=>"\t'not_installed_lang'=>'not-installed',\n",
));

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
include $root.'/xiunophp/logger.func.php';
include $root.'/xiunophp/misc.func.php';
include $root.'/xiunophp/php8_compat.php';
include $root.'/model/plugin.func.php';

plugin_init();

xn_count_compat(NULL) === 0 || fail('PHP 7 count compatibility must return zero for null.');
xn_count_compat(FALSE) === 1 || fail('PHP 7 count compatibility must return one for non-null scalar values.');
xn_count_compat(array(1, 2)) === 2 || fail('PHP 7 count compatibility must preserve array counts.');
xn_count_compat(new ArrayObject(array(1, 2, 3))) === 3 || fail('PHP 7 count compatibility must preserve Countable objects.');

$case_compiled = plugin_compile_srcfile($case_target);
assert_contains($case_compiled, "case 'enabled_high':", 'High-rank case hook fragment should compile into route context.');
assert_contains($case_compiled, "case 'enabled_low':", 'Low-rank case hook fragment should also compile into route context.');
assert_not_contains($case_compiled, "case 'disabled':", 'Disabled hook packages must not compile into route context.');
assert_not_contains($case_compiled, "case 'not_installed':", 'Not-installed hook packages must not compile into route context.');
if(strpos($case_compiled, "case 'enabled_high':") > strpos($case_compiled, "case 'enabled_low':")) {
	fail('Hook fragments must compile in hooks_rank descending order.');
}

$template_compiled = plugin_compile_srcfile($template_target);
assert_contains($template_compiled, 'before high-templatelow-template after', 'Template hooks should compile in rank order inside HTML context.');
assert_not_contains($template_compiled, 'disabled-template', 'Disabled template hook packages must not compile.');
assert_not_contains($template_compiled, 'not-installed-template', 'Not-installed template hook packages must not compile.');

$guard_compiled = plugin_compile_srcfile($guard_target);
assert_contains($guard_compiled, "echo 'high-guarded';", 'PHP hook guard wrappers should be stripped while preserving high-rank body.');
assert_contains($guard_compiled, "echo 'low-guarded';", 'PHP hook guard wrappers should be stripped while preserving low-rank body.');
assert_not_contains($guard_compiled, '<?php exit;', 'Compiled PHP hook fragments must not retain direct-access guard wrappers.');
assert_not_contains($guard_compiled, 'disabled-guarded', 'Disabled guarded hook packages must not compile.');
assert_not_contains($guard_compiled, 'not-installed-guarded', 'Not-installed guarded hook packages must not compile.');

$count_compiled = plugin_compile_srcfile($count_target);
substr_count($count_compiled, 'xn_count_compat') === 2
	|| fail('Bare count() calls in PHP Hook fragments must use the PHP 7 compatibility helper.' . "\nCompiled:\n" . $count_compiled);
assert_contains($count_compiled, '$counter->count(', 'Object count() methods must not be rewritten.');
assert_contains($count_compiled, 'PluginCounter::count(', 'Static count() methods must not be rewritten.');
assert_contains($count_compiled, '$counter?->count(', 'Nullsafe count() methods must not be rewritten.');
assert_contains($count_compiled, "'count(\$literal)'", 'count() text inside strings must not be rewritten.');
assert_contains($count_compiled, '// count($comment)', 'count() text inside comments must not be rewritten.');
file_put_contents($app.'tmp/count_compiled.php', $count_compiled);
ob_start();
include $app.'tmp/count_compiled.php';
$count_output = ob_get_clean();
$count_output === 'continued:0:0'
	|| fail('Compiled legacy count() Hook must preserve PHP 7 behavior and continue the core route. Output: '.$count_output);

$core_count_source = file_get_contents($core_count_target);
$core_count_compiled = plugin_compile_srcfile($core_count_target);
$core_count_compiled === $core_count_source
	|| fail('Core source count() calls must not be rewritten by the plugin Hook compatibility layer.');

$template_count_compiled = plugin_compile_srcfile($template_count_target);
assert_contains($template_count_compiled, 'count(NULL)', 'Template Hook count() calls must remain unchanged.');
assert_not_contains($template_count_compiled, 'xn_count_compat', 'Only PHP Hook files may receive the count() compatibility rewrite.');

$model_array_compiled = plugin_compile_srcfile($model_array_target);
assert_contains($model_array_compiled, "APP_PATH.'model/high.func.php'", 'Array-entry model hook fragments should compile inside model include arrays.');
assert_contains($model_array_compiled, "APP_PATH.'model/low.func.php'", 'Low-rank array-entry model hook fragments should also compile.');
assert_not_contains($model_array_compiled, 'disabled.func.php', 'Disabled array-entry model hooks must not compile.');
assert_not_contains($model_array_compiled, 'not-installed.func.php', 'Not-installed array-entry model hooks must not compile.');
if(strpos($model_array_compiled, "APP_PATH.'model/high.func.php'") > strpos($model_array_compiled, "APP_PATH.'model/low.func.php'")) {
	fail('Array-entry model hook fragments must compile in hooks_rank descending order.');
}
file_put_contents($app.'tmp/model_array_compiled.php', $model_array_compiled);
exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($app.'tmp/model_array_compiled.php').' 2>&1', $model_lint, $model_lint_code);
if($model_lint_code !== 0) {
	fail('Compiled model array hook context must pass php -l.'."\n".implode("\n", $model_lint));
}

$lang_array_compiled = plugin_compile_srcfile($lang_array_target);
assert_contains($lang_array_compiled, "'high_lang'=>'high'", 'Array-entry language hook fragments should compile inside lang arrays.');
assert_contains($lang_array_compiled, "'low_lang'=>'low'", 'Low-rank array-entry language hook fragments should also compile.');
assert_not_contains($lang_array_compiled, 'disabled_lang', 'Disabled array-entry language hooks must not compile.');
assert_not_contains($lang_array_compiled, 'not_installed_lang', 'Not-installed array-entry language hooks must not compile.');
if(strpos($lang_array_compiled, "'high_lang'=>'high'") > strpos($lang_array_compiled, "'low_lang'=>'low'")) {
	fail('Array-entry language hook fragments must compile in hooks_rank descending order.');
}
file_put_contents($app.'tmp/lang_array_compiled.php', $lang_array_compiled);
exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($app.'tmp/lang_array_compiled.php').' 2>&1', $lang_lint, $lang_lint_code);
if($lang_lint_code !== 0) {
	fail('Compiled language array hook context must pass php -l.'."\n".implode("\n", $lang_lint));
}

$handler_time = time();
$handler_script = $app.'tmp/php8_handler_ajax.php';
$handler_status = $app.'tmp/php8_handler_status.txt';
$handler_source = "<?php\n"
	."define('DEBUG', 0);\n"
	."define('APP_PATH', ".var_export($root.'/', TRUE).");\n"
	."ini_set('display_errors', '0');\n"
	."error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);\n"
	."\$_SERVER['REMOTE_ADDR'] = '127.0.0.1';\n"
	."\$_SERVER['REQUEST_URI'] = '/post-create-1-1.htm';\n"
	."\$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';\n"
	."\$_SERVER['ajax'] = 1;\n"
	."\$_SERVER['time'] = ".$handler_time.";\n"
	."\$_SERVER['conf'] = array('log_path'=>".var_export($app.'log/', TRUE).");\n"
	."\$_REQUEST = array();\n"
	."register_shutdown_function(function() { file_put_contents(".var_export($handler_status, TRUE).", (string)http_response_code()); });\n"
	."include ".var_export($root.'/xiunophp/logger.func.php', TRUE).";\n"
	."include ".var_export($root.'/xiunophp/misc.func.php', TRUE).";\n"
	."include ".var_export($root.'/xiunophp/php8_compat.php', TRUE).";\n"
	."\$matches = array(array());\n"
	."count(\$matches[1]);\n"
	."echo 'unreachable';\n";
file_put_contents($handler_script, $handler_source);
$handler_output = array();
$handler_code = 0;
exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($handler_script).' 2>&1', $handler_output, $handler_code);
$handler_body = trim(implode("\n", $handler_output));
$handler_json = json_decode($handler_body, TRUE);
$handler_code === 1 || fail('Production TypeError handler must exit with a failure status. Output: '.$handler_body);
is_array($handler_json) || fail('AJAX TypeError handler must return non-empty JSON. Output: '.$handler_body);
isset($handler_json['code']) && $handler_json['code'] === -1
	|| fail('AJAX TypeError response must use the Xiuno error code contract.');
!empty($handler_json['message']) || fail('AJAX TypeError response must include a user-visible message.');
strpos($handler_body, 'count()') === FALSE && strpos($handler_body, $handler_script) === FALSE
	|| fail('Production AJAX TypeError responses must not expose internal exception details.');
is_file($handler_status) && trim(file_get_contents($handler_status)) === '500'
	|| fail('TypeError handler must set HTTP status 500 before terminating.');
$handler_log = $app.'log/'.date('Ym', $handler_time).'/php8_compat_error.php';
is_file($handler_log) || fail('Production TypeError handler must write the php8_compat_error log.');
$handler_log_source = file_get_contents($handler_log);
strpos($handler_log_source, 'TypeError caught') !== FALSE && strpos($handler_log_source, '/post-create-1-1.htm') !== FALSE
	|| fail('PHP 8 compatibility error log must include the failure and request route.');

rm_dir($app);

echo "OK: hook context compile smoke checks passed\n";
