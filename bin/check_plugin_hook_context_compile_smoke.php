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
$package_php_target = $app.'plugin/enabled_high_hook/model/legacy.func.php';
$package_template_target = $app.'plugin/enabled_high_hook/view/legacy.htm';
$hook_comment = '// hoo'.'k ';
$hook_template = '<!--{hoo'.'k template_hook.htm}-->';
$hook_template_count = '<!--{hoo'.'k template_count_hook.htm}-->';
$template_php_markup = <<<'HTM'
<section data-code="count(NULL)"><script>window.legacyCall = "array_column(NULL, 'id')";</script><style>.legacy::after{content:"count(NULL)"}</style><?php $template_count = count(NULL); $template_columns = array_column(NULL, "id"); echo 'php-ok:'.count($template_columns); ?><?= count(NULL) ?><span>template-tail</span></section>
HTM;

file_put_contents($case_target, "<?php\nswitch(\$action) {\n".$hook_comment."case_hook.php\n\tdefault: echo 'default';\n}\n");
file_put_contents($template_target, 'before '.$hook_template.' after');
file_put_contents($guard_target, "<?php\n".$hook_comment."guarded_hook.php\n");
file_put_contents($count_target, "<?php\n\$compat_matches = array(array(), NULL);\n\$compat_count = -1;\n\$compat_comment_count = -1;\n\$compat_columns = array('unreached');\n\$compat_spaced_columns = array('unreached');\n\$compat_unrelated_call = -1;\n".$hook_comment."count_hook.php\necho 'continued:'.\$compat_count.':'.\$compat_comment_count.':'.count(\$compat_columns).':'.count(\$compat_spaced_columns).':'.\$compat_unrelated_call;\n");
file_put_contents($core_count_target, "<?php\n\$core_count = count(array(1, 2));\n\$core_columns = array_column(array(array('id'=>1)), 'id');\n");
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
$compat_columns = array_column($compat_matches[1], 'id');
$compat_spaced_columns = ARRAY_COLUMN /* legacy spacing */ ($compat_matches[1], 'id');
$compat_unrelated_call = strlen('ok');
if(FALSE) {
	$counter->count($compat_matches);
	PluginCounter::count($compat_matches);
	$counter?->count($compat_matches);
	$counter->array_column($compat_matches);
	PluginCounter::array_column($compat_matches);
	$counter?->array_column($compat_matches);
}
$compat_literal = 'count($literal)';
$compat_column_literal = 'array_column($literal, "id")';
// count($comment)
// array_column($comment, 'id')
PHP
,
	'template_count_hook.htm'=>$template_php_markup,
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

mkdir(dirname($package_php_target), 0777, TRUE);
file_put_contents($package_php_target, <<<'PHP'
<?php
$package_rows = NULL;
$package_columns = array_column($package_rows, 'id');
$package_sorted = array_multisort($package_columns, SORT_ASC, $package_rows);
$package_length = strlen('ok');
echo 'package:'.count($package_columns).':'.intval($package_sorted).':'.$package_length;
PHP
);
mkdir(dirname($package_template_target), 0777, TRUE);
$package_template_source = '<div data-call="count(NULL)"><?php $package_template_count = count(NULL); echo count(array()); ?><script>array_column(NULL, "id")</script></div>';
file_put_contents($package_template_target, $package_template_source);

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

$message_source = file_get_contents($root.'/model/misc.func.php');
strpos($message_source, 'global $ajax, $header, $conf, $db;') !== FALSE
	|| fail('message() must expose the shared database object to legacy Hook fragments.');

xn_count_compat(NULL) === 0 || fail('PHP 7 count compatibility must return zero for null.');
xn_count_compat(FALSE) === 1 || fail('PHP 7 count compatibility must return one for non-null scalar values.');
xn_count_compat(array(1, 2)) === 2 || fail('PHP 7 count compatibility must preserve array counts.');
xn_count_compat(new ArrayObject(array(1, 2, 3))) === 3 || fail('PHP 7 count compatibility must preserve Countable objects.');
xn_array_column_compat(NULL, 'id') === array() || fail('Legacy array_column compatibility must treat null query results as an empty list.');
xn_array_column_compat(FALSE, 'id') === array() || fail('Legacy array_column compatibility must fail soft for non-array query results.');
xn_array_column_compat(array(array('id'=>7), array('id'=>9)), 'id') === array(7, 9)
	|| fail('Legacy array_column compatibility must preserve normal column extraction.');
xn_array_column_compat(array(array('key'=>'a', 'id'=>7), array('key'=>'b', 'id'=>9)), 'id', 'key') === array('a'=>7, 'b'=>9)
	|| fail('Legacy array_column compatibility must preserve index-key semantics.');
$sort_keys = array(2, 1);
$sort_rows = array(array('id'=>2), array('id'=>1));
xn_array_multisort_compat($sort_keys, SORT_ASC, $sort_rows) === TRUE
	|| fail('Legacy array_multisort compatibility must preserve valid two-array sorting.');
$sort_keys === array(1, 2) && $sort_rows === array(array('id'=>1), array('id'=>2))
	|| fail('Legacy array_multisort compatibility must preserve by-reference row alignment.');
$invalid_sort_rows = NULL;
$sort_keys_before_invalid = $sort_keys;
xn_array_multisort_compat($sort_keys, SORT_ASC, $invalid_sort_rows) === FALSE
	|| fail('Legacy array_multisort compatibility must fail soft for a null secondary array.');
$sort_keys === $sort_keys_before_invalid
	|| fail('A rejected null secondary array must not mutate the primary array.');
$uneven_sort_keys = array(2, 1);
$uneven_sort_rows = array(array('id'=>2));
$uneven_sort_keys_before = $uneven_sort_keys;
$uneven_sort_rows_before = $uneven_sort_rows;
xn_array_multisort_compat($uneven_sort_keys, SORT_ASC, $uneven_sort_rows) === FALSE
	|| fail('Legacy array_multisort compatibility must fail soft for arrays with different lengths.');
$uneven_sort_keys === $uneven_sort_keys_before && $uneven_sort_rows === $uneven_sort_rows_before
	|| fail('Rejected arrays with different lengths must remain unchanged.');
$typed_sort_keys = array('2', '10');
$typed_sort_type = SORT_STRING;
xn_array_multisort_compat($typed_sort_keys, SORT_ASC, $typed_sort_type) === TRUE
	|| fail('A sort-type variable in the third position must preserve native array_multisort semantics.');
$typed_sort_keys === array('10', '2') && $typed_sort_type === SORT_STRING
	|| fail('String sorting through the compatibility helper must preserve both ordering and the flag variable.');

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
substr_count($count_compiled, 'xn_array_column_compat') === 2
	|| fail('Bare array_column() calls in PHP Hook fragments must use the PHP 7 compatibility helper.' . "\nCompiled:\n" . $count_compiled);
assert_contains($count_compiled, '$counter->count(', 'Object count() methods must not be rewritten.');
assert_contains($count_compiled, 'PluginCounter::count(', 'Static count() methods must not be rewritten.');
assert_contains($count_compiled, '$counter?->count(', 'Nullsafe count() methods must not be rewritten.');
assert_contains($count_compiled, '$counter->array_column(', 'Object array_column() methods must not be rewritten.');
assert_contains($count_compiled, 'PluginCounter::array_column(', 'Static array_column() methods must not be rewritten.');
assert_contains($count_compiled, '$counter?->array_column(', 'Nullsafe array_column() methods must not be rewritten.');
assert_contains($count_compiled, "'count(\$literal)'", 'count() text inside strings must not be rewritten.');
assert_contains($count_compiled, "'array_column(\$literal, \"id\")'", 'array_column() text inside strings must not be rewritten.');
assert_contains($count_compiled, '// count($comment)', 'count() text inside comments must not be rewritten.');
assert_contains($count_compiled, "// array_column(\$comment, 'id')", 'array_column() text inside comments must not be rewritten.');
assert_contains($count_compiled, "strlen('ok')", 'Bare functions outside the explicit compatibility map must remain unchanged.');
$declaration_probe = plugin_compat_rewrite_php8_hook_calls('function array_column($rows, $key) { return $rows; }');
assert_contains($declaration_probe, 'function array_column(', 'Function declarations named array_column must not be rewritten.');
$symbol_context_probe = plugin_compat_rewrite_php8_hook_calls(<<<'PHP'
namespace LegacyCompat;
function count($value) { return 77; }
function array_column($rows, $column) { return array('local-column'); }
#[count('attribute-argument')]
function local_count($value) { return count($value); }
$local = count(NULL);
$local_columns = array_column(NULL, 'id');
$global = \count(NULL);
$global_columns = \array_column(NULL, 'id');
PHP
);
assert_contains($symbol_context_probe, 'function count(', 'Namespaced compatibility helpers must preserve package function declarations.');
assert_contains($symbol_context_probe, "#[count('attribute-argument')]", 'Attribute class names must never be rewritten as function calls.');
assert_contains($symbol_context_probe, 'return count($value);', 'Bare namespaced calls must preserve the package-local function.');
assert_contains($symbol_context_probe, '$local = count(NULL);', 'Bare namespaced calls must preserve package symbol resolution.');
assert_contains($symbol_context_probe, '$local_columns = array_column(NULL, \'id\');', 'Each namespaced function declaration must preserve only its matching bare calls.');
assert_not_contains($symbol_context_probe, 'xn_count_compat($value)', 'A namespaced local function call must never be redirected to the core helper.');
assert_contains($symbol_context_probe, '\\xn_count_compat(NULL)', 'Explicit fully-qualified built-in calls may use the global compatibility helper.');
assert_contains($symbol_context_probe, '\\xn_array_column_compat(NULL, \'id\')', 'Explicit fully-qualified array_column calls may use the global compatibility helper.');
$global_attribute_probe = plugin_compat_rewrite_php8_hook_calls("#[count('attribute')]\nclass CountAttributeProbe {}\n\$value = count(NULL);");
assert_contains($global_attribute_probe, "#[count('attribute')]", 'Global Attribute names must remain byte-compatible.');
assert_contains($global_attribute_probe, '$value = xn_count_compat(NULL);', 'A normal global bare call must still receive the compatibility rewrite.');
$function_import_probe = plugin_compat_rewrite_php8_hook_calls("use function Vendor\\count;\n\$local = count(NULL);\n\$columns = array_column(NULL, 'id');\n\$global = \\count(NULL);");
assert_contains($function_import_probe, '$local = count(NULL);', 'Imported function aliases must preserve PHP symbol resolution.');
assert_contains($function_import_probe, '$columns = xn_array_column_compat(NULL, \'id\');', 'Importing count must not suppress array_column compatibility in the same file.');
assert_contains($function_import_probe, '$global = \\xn_count_compat(NULL);', 'An explicit global call remains safe to rewrite beside an import.');
$namespace_without_symbol_probe = plugin_compat_rewrite_php8_hook_calls(<<<'PHP'
namespace Demo;
$unsafe_count = count(NULL);
$unsafe_columns = array_column(NULL, 'id');
PHP
);
assert_contains($namespace_without_symbol_probe, '$unsafe_count = xn_count_compat(NULL);', 'A namespace declaration alone must not suppress count compatibility.');
assert_contains($namespace_without_symbol_probe, '$unsafe_columns = xn_array_column_compat(NULL, \'id\');', 'A namespace declaration alone must not suppress array_column compatibility.');
$method_shadow_probe = plugin_compat_rewrite_php8_hook_calls(<<<'PHP'
namespace MethodShadow;
class CountMethods { public function count($value) { return $value; } }
trait ColumnMethods { public function array_column($rows, $column) { return $rows; } }
interface SortMethods { public function array_multisort($keys, $order, $rows); }
$unsafe_count = count(NULL);
$unsafe_columns = array_column(NULL, 'id');
$unsafe_sort = array_multisort($keys, SORT_ASC, $rows);
PHP
);
assert_contains($method_shadow_probe, '$unsafe_count = xn_count_compat(NULL);', 'Class methods must not be treated as namespaced count functions.');
assert_contains($method_shadow_probe, '$unsafe_columns = xn_array_column_compat(NULL, \'id\');', 'Trait methods must not be treated as namespaced array_column functions.');
assert_contains($method_shadow_probe, '$unsafe_sort = xn_array_multisort_compat($keys, SORT_ASC, $rows);', 'Interface methods must not be treated as namespaced array_multisort functions.');
if(defined('T_ENUM')) {
	$enum_method_probe = plugin_compat_rewrite_php8_hook_calls("namespace EnumMethodShadow; enum CountMethods { public function count(\$value) { return \$value; } } \$unsafe = count(NULL);");
	assert_contains($enum_method_probe, '$unsafe = xn_count_compat(NULL);', 'Enum methods must not be treated as namespaced count functions.');
}
file_put_contents($app.'tmp/count_compiled.php', $count_compiled);
ob_start();
include $app.'tmp/count_compiled.php';
$count_output = ob_get_clean();
$count_output === 'continued:0:0:0:0:2'
	|| fail('Compiled legacy count()/array_column() Hook must preserve fail-soft behavior and continue the core route. Output: '.$count_output);

$core_count_source = file_get_contents($core_count_target);
$core_count_compiled = plugin_compile_srcfile($core_count_target);
$core_count_compiled === $core_count_source
	|| fail('Core source count() calls must not be rewritten by the plugin Hook compatibility layer.');

$package_php_compiled = plugin_compile_srcfile($package_php_target);
assert_contains($package_php_compiled, 'xn_array_column_compat($package_rows', 'Standalone package PHP files must receive the generic array_column compatibility rewrite.');
assert_contains($package_php_compiled, 'xn_array_multisort_compat($package_columns', 'Simple-variable package array_multisort triplets must receive the by-reference compatibility rewrite.');
assert_contains($package_php_compiled, "strlen('ok')", 'Standalone package PHP rewriting must preserve unrelated bare functions.');
$array_multisort_probe = plugin_compat_rewrite_php8_hook_calls(<<<'PHP'
ArRaY_MuLtIsOrT ( $keys, /* explicit order */ SORT_DESC, $rows );
array_multisort(make_keys(), SORT_ASC, $rows);
array_multisort($keys, $dynamic_order, $rows);
array_multisort($keys, SORT_ASC, SORT_REGULAR);
array_multisort($keys, $rows, $third_rows);
array_multisort($keys, SORT_ASC, $rows, SORT_REGULAR);
$sorter->array_multisort($keys, SORT_ASC, $rows);
PluginSorter::array_multisort($keys, SORT_ASC, $rows);
$sorter?->array_multisort($keys, SORT_ASC, $rows);
array_multisort($holder->keys, SORT_ASC, $rows);
array_multisort($keys[0], SORT_ASC, $rows);
function array_multisort($keys, $order, $rows) { return TRUE; }
$literal = 'array_multisort($keys, SORT_ASC, $rows)';
// array_multisort($keys, SORT_ASC, $rows)
PHP
);
substr_count($array_multisort_probe, 'xn_array_multisort_compat') === 1
	|| fail('Only the explicit variable/SORT_ASC-or-DESC/variable array_multisort shape may be rewritten. Output: '.$array_multisort_probe);
assert_contains($array_multisort_probe, 'xn_array_multisort_compat ( $keys, /* explicit order */ SORT_DESC, $rows )', 'Function names are case-insensitive and trivia must not block the safe target shape.');
assert_contains($array_multisort_probe, 'array_multisort($keys, $rows, $third_rows)', 'A valid three-array call must remain native.');
assert_contains($array_multisort_probe, 'array_multisort($keys, $dynamic_order, $rows)', 'A dynamic middle argument must remain native.');
assert_contains($array_multisort_probe, 'array_multisort($keys, SORT_ASC, SORT_REGULAR)', 'A third constant must remain native.');
assert_contains($array_multisort_probe, '$sorter->array_multisort(', 'Object methods must not be rewritten.');
assert_contains($array_multisort_probe, 'PluginSorter::array_multisort(', 'Static methods must not be rewritten.');
assert_contains($array_multisort_probe, '$sorter?->array_multisort(', 'Nullsafe methods must not be rewritten.');
assert_contains($array_multisort_probe, 'function array_multisort(', 'Function declarations must not be rewritten.');
assert_contains($array_multisort_probe, "'array_multisort(\$keys, SORT_ASC, \$rows)'", 'String contents must not be rewritten.');
assert_contains($array_multisort_probe, '// array_multisort($keys, SORT_ASC, $rows)', 'Comment contents must not be rewritten.');
file_put_contents($app.'tmp/package_php_compiled.php', $package_php_compiled);
ob_start();
include $app.'tmp/package_php_compiled.php';
$package_php_output = ob_get_clean();
$package_php_output === 'package:0:0:2'
	|| fail('Compiled standalone package PHP must continue after a null array_column source. Output: '.$package_php_output);

$template_count_compiled = plugin_compile_srcfile($template_count_target);
assert_contains($template_count_compiled, '$template_count = xn_count_compat(NULL)', 'PHP blocks embedded in .htm Hook files must receive count() compatibility rewriting.');
assert_contains($template_count_compiled, '$template_columns = xn_array_column_compat(NULL', 'PHP blocks embedded in .htm Hook files must receive array_column() compatibility rewriting.');
assert_contains($template_count_compiled, '<?= xn_count_compat(NULL) ?>', 'Short echo PHP blocks embedded in .htm Hook files must be rewritten without changing their tag.');
assert_contains($template_count_compiled, '<section data-code="count(NULL)"><script>window.legacyCall = "array_column(NULL, \'id\')";</script><style>.legacy::after{content:"count(NULL)"}</style><?php ', 'HTML, JavaScript and CSS bytes before an embedded PHP block must remain unchanged.');
assert_contains($template_count_compiled, '<span>template-tail</span></section> after', 'HTML bytes after embedded PHP blocks must remain unchanged.');
file_put_contents($app.'tmp/template_count_compiled.php', $template_count_compiled);
ob_start();
include $app.'tmp/template_count_compiled.php';
$template_count_output = ob_get_clean();
assert_contains($template_count_output, 'before <section data-code="count(NULL)"><script>window.legacyCall = "array_column(NULL, \'id\')";</script><style>.legacy::after{content:"count(NULL)"}</style>php-ok:00<span>template-tail</span></section> after', 'A real compiled .htm Hook must continue after legacy null calls while preserving surrounding markup.');

$package_template_compiled = plugin_compile_srcfile($package_template_target);
assert_contains($package_template_compiled, '<?php $package_template_count = xn_count_compat(NULL); echo xn_count_compat(array()); ?>', 'Standalone package .htm files must rewrite only their embedded PHP blocks.');
assert_contains($package_template_compiled, '<div data-call="count(NULL)">', 'Standalone package template attributes must remain byte-identical.');
assert_contains($package_template_compiled, '<script>array_column(NULL, "id")</script></div>', 'Standalone package JavaScript text must not be treated as PHP.');
$cross_block_import = plugin_compat_rewrite_php8_template_blocks(<<<'HTM'
<?php namespace TemplateImport; use function Vendor\count; ?><em>count(NULL)</em><?php $kept = count(NULL); $columns = array_column(NULL, 'id'); ?>
HTM
);
assert_contains($cross_block_import, '$kept = count(NULL);', 'A function import in an earlier PHP block must protect only its matching call in later blocks.');
assert_contains($cross_block_import, '$columns = xn_array_column_compat(NULL, \'id\');', 'A cross-block import must not suppress unrelated compatibility rewriting.');
assert_contains($cross_block_import, '<em>count(NULL)</em>', 'Cross-block symbol analysis must not alter intervening markup.');
$cross_block_declaration = plugin_compat_rewrite_php8_template_blocks(<<<'HTM'
<?php namespace TemplateDeclaration; function array_column($rows, $key) { return $rows; } ?><i>array_column(NULL, 'id')</i><?php $kept = array_column(NULL, 'id'); $counted = count(NULL); ?>
HTM
);
assert_contains($cross_block_declaration, '$kept = array_column(NULL, \'id\');', 'A namespaced function declared in an earlier PHP block must remain visible to later blocks.');
assert_contains($cross_block_declaration, '$counted = xn_count_compat(NULL);', 'A cross-block declaration must protect only its own function name.');

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
