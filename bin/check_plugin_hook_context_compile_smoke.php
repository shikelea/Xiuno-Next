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
$model_array_target = $app.'model.inc.php';
$lang_array_target = $app.'lang/zh-cn/bbs.php';
$hook_comment = '// hoo'.'k ';
$hook_template = '<!--{hoo'.'k template_hook.htm}-->';

file_put_contents($case_target, "<?php\nswitch(\$action) {\n".$hook_comment."case_hook.php\n\tdefault: echo 'default';\n}\n");
file_put_contents($template_target, 'before '.$hook_template.' after');
file_put_contents($guard_target, "<?php\n".$hook_comment."guarded_hook.php\n");
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
	'model_inc_file.php'=>30,
	'lang_zh_cn_bbs.php'=>30,
), array(
	'case_hook.php'=>"case 'enabled_high': echo 'enabled-high'; break;\n",
	'template_hook.htm'=>'high-template',
	'guarded_hook.php'=>"<?php exit; echo 'high-guarded'; ?>",
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
include $root.'/xiunophp/misc.func.php';
include $root.'/model/plugin.func.php';

plugin_init();

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

rm_dir($app);

echo "OK: hook context compile smoke checks passed\n";
