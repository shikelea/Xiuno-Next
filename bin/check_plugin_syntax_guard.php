<?php

$root = dirname(__DIR__);
$app = $root.'/tmp/plugin_syntax_guard_app/';

defined('DEBUG') || define('DEBUG', 0);
defined('APP_PATH') || define('APP_PATH', $app);
defined('ADMIN_PATH') || define('ADMIN_PATH', $app.'admin/');
defined('XIUNOPHP_PATH') || define('XIUNOPHP_PATH', $root.'/xiunophp/');

function fail($message) {
	fwrite(STDERR, "FAIL: $message\n");
	exit(1);
}

function rm_dir($dir) {
	if(!is_dir($dir)) return;
	$items = glob(rtrim($dir, '/').'/*');
	if($items) {
		foreach($items as $item) {
			is_link($item) ? unlink($item) : (is_dir($item) ? rm_dir($item) : unlink($item));
		}
	}
	rmdir($dir);
}

function run_php_child($command, $code, $label) {
	$descriptor = array(
		0=>array('pipe', 'r'),
		1=>array('pipe', 'w'),
		2=>array('pipe', 'w'),
	);
	$process = proc_open($command, $descriptor, $pipes);
	if(!is_resource($process)) fail("$label could not start a PHP child process.");
	fwrite($pipes[0], "<?php\n".$code);
	fclose($pipes[0]);
	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	$exit = proc_close($process);
	if($exit !== 0) fail("$label child failed with exit $exit:\n$stderr\n$stdout");
	$result = json_decode(trim($stdout), TRUE);
	if(!is_array($result)) fail("$label child emitted invalid JSON:\n$stdout\n$stderr");
	return $result;
}

function syntax_child_code($root, $app, $dir, $php_binary = NULL) {
	$php_argument = $php_binary === NULL ? '' : ', '.var_export($php_binary, TRUE);
	return 'define("DEBUG", 0);'."\n"
		.'define("APP_PATH", '.var_export($app, TRUE).');'."\n"
		.'require '.var_export($root.'/model/plugin.func.php', TRUE).';'."\n"
		.'echo json_encode(plugin_php_syntax_errors('.var_export($dir, TRUE).$php_argument.'));'."\n";
}

rm_dir($app);
mkdir($app.'plugin/good/model', 0777, TRUE);
mkdir($app.'plugin/good/hook', 0777, TRUE);
mkdir($app.'plugin/bad/model', 0777, TRUE);
mkdir($app.'plugin/bad/hook', 0777, TRUE);
mkdir($app.'plugin/legacy/model', 0777, TRUE);
mkdir($app.'plugin/legacy/hook', 0777, TRUE);
mkdir($app.'plugin/legacy/overwrite/theme/demo', 0777, TRUE);
mkdir($app.'plugin_evil/outside', 0777, TRUE);

file_put_contents($app.'plugin/good/model/ok.func.php', "<?php\nfunction syntax_guard_ok() { return true; }\n");
file_put_contents($app.'plugin/good/hook/index_route_case_end.php', "<?php exit;\ncase 'legacy': echo 'hook fragment'; break;\n");
file_put_contents($app.'plugin/bad/model/broken.func.php', "<?php\nfunction syntax_guard_broken( {\n");
file_put_contents($app.'plugin/bad/model/nested_ternary.func.php', "<?php\nfunction syntax_guard_nested_ternary(\$n) { return \$n > 20 ? 10 : \$n % 2 ? 1 : 0; }\n");
file_put_contents($app.'plugin/bad/hook/my_end.php', "<?php exit;\nelse { echo 'hook fragment'; }\n");
file_put_contents($app.'plugin/legacy/model/legacy.func.php', "<?php\nfunction syntax_guard_legacy(\$arr) { return each(\$arr); }\n");
file_put_contents($app.'plugin/legacy/model/method.func.php', "<?php\nfunction syntax_guard_method(\$obj) { return \$obj->each(); }\n");
file_put_contents($app.'plugin/legacy/model/namespaced.func.php', "<?php\nnamespace LegacyFixture;\nfunction each(\$arr) { return \$arr; }\nfunction call_each(\$arr) { return each(\$arr); }\n");
file_put_contents($app.'plugin/legacy/model/namespaced_removed.func.php', "<?php\nnamespace Demo;\nfunction unrelated_local(\$value) { return \$value; }\nfunction call_removed_mysql() { return mysql_query('SELECT 1'); }\n");
file_put_contents($app.'plugin/legacy/model/imported.func.php', "<?php\nnamespace ImportedFixture;\nuse function Vendor\\each;\nfunction call_imported_each(\$arr) { return each(\$arr); }\n");
file_put_contents($app.'plugin/legacy/model/import_mask.func.php', "<?php\nnamespace ImportMaskFixture;\nuse function Vendor\\each;\nfunction call_unimported_mysql() { return mysql_query('SELECT 1'); }\n");
file_put_contents($app.'plugin/legacy/model/class_method_shadow.func.php', <<<'PHP'
<?php
namespace MethodShadowFixture;
class LegacyMethods {
	public function each($array) { return $array; }
}
function call_removed_each_after_method($array) { return each($array); }
PHP
);
file_put_contents($app.'plugin/legacy/model/attribute.func.php', "<?php\n#[each('fixture')]\nclass LegacyEachAttribute {}\n");
file_put_contents($app.'plugin/legacy/model/fully_qualified.func.php', "<?php\nfunction syntax_guard_mysql_removed() { return \\mysql_query('SELECT 1'); }\n");
file_put_contents($app.'plugin/legacy/model/polyfill.func.php', <<<'PHP'
<?php
function each($array) { return array('value'=>reset($array)); }
function mysql_query($sql) { return $sql; }
function syntax_guard_polyfill_calls($array) {
	return array(each($array), \mysql_query('SELECT 1'));
}
PHP
);
file_put_contents($app.'plugin/legacy/overwrite/theme/demo/legacy.php', "<?php\nfunction syntax_guard_legacy_overwrite(\$arr) { return create_function('', 'return 1;'); }\n");
file_put_contents($app.'plugin/legacy/overwrite/theme/demo/jquery_each.php', "<script>\n$('.items').each(function() { console.log(this); });\n</script>\n");
file_put_contents($app.'plugin/legacy/hook/legacy.php', "<?php exit;\nreturn each(\$arr);\n");
file_put_contents($app.'plugin_evil/outside/outside.php', "<?php\nreturn true;\n");

include $root.'/model/plugin.func.php';

$inside_realpath = plugin_realpath_within($app.'plugin/good/model/ok.func.php', $app.'plugin');
$inside_realpath !== FALSE && is_file($inside_realpath)
	|| fail('Plugin canonical containment must accept files below the package root.');
plugin_realpath_within($app.'plugin_evil/outside/outside.php', $app.'plugin') === FALSE
	|| fail('Plugin canonical containment must reject a sibling directory sharing the package-root prefix.');

$outside_link = $app.'plugin/linked_outside';
$symlink_skip_reason = '';
$outside_link_created = function_exists('symlink') && @symlink($app.'plugin_evil/outside', $outside_link);
if($outside_link_created) {
	$link_errors = plugin_php_syntax_errors('linked_outside');
	count($link_errors) === 1 || fail('A linked package escaping plugin/ must fail closed exactly once.');
	strpos($link_errors[0]['detail'], 'package root is a symbolic link') !== FALSE
		|| fail('A linked package failure must report the stable package-root symlink category.');
} else {
	$symlink_skip_reason = 'package-root symlink creation is unavailable on this host; the fail-closed negative case was not executed.';
}

$plugin_model = file_get_contents($root.'/model/plugin.func.php');
$plugin_route = file_get_contents($root.'/admin/route/plugin.php');
strpos($plugin_model, "PHP_SAPI === 'cli'") !== FALSE
	|| fail('Plugin syntax guard must distinguish CLI from Web/FPM runtimes.');
strpos($plugin_model, "PHP_SAPI === 'cli-server'") !== FALSE
	|| fail('The local development server must reuse its actual PHP CLI binary.');
strpos($plugin_model, "PHP_BINDIR.DIRECTORY_SEPARATOR") !== FALSE
	|| fail('Web/FPM syntax checks must invoke the matching PHP CLI binary from PHP_BINDIR.');
strpos($plugin_model, "if(!function_exists('exec'))") !== FALSE
	|| fail('Plugin syntax guard must fail closed when process execution is unavailable.');
strpos($plugin_model, '!is_file($php) || !is_executable($php)') !== FALSE
	|| fail('Plugin syntax guard must reject a missing or non-executable PHP CLI binary.');
strpos($plugin_route, 'plugin_require_package_root($dir)') !== FALSE
	&& strpos($plugin_route, 'plugin_realpath_within($pluginfile, $package_root)') !== FALSE
	&& strpos($plugin_route, 'is_link($pluginfile)') !== FALSE
	&& strpos($plugin_route, 'strpos($real_path, $safe_dir)') === FALSE
	|| fail('Plugin setting includes must use a canonical directory boundary instead of a raw string prefix.');

$child_good = run_php_child(array(PHP_BINARY), syntax_child_code($root, $app, 'good'), 'normal CLI preflight');
empty($child_good) || fail('A normal PHP CLI child must lint a valid standalone plugin file successfully.');

$child_exec_disabled = run_php_child(
	array(PHP_BINARY, '-d', 'disable_functions=exec'),
	syntax_child_code($root, $app, 'good'),
	'disabled exec preflight'
);
count($child_exec_disabled) === 1 || fail('Disabled exec must produce exactly one fail-closed capability error.');
$disabled_exec_error = $child_exec_disabled[0];
strpos($disabled_exec_error['file'], 'plugin/good/') !== FALSE
	|| fail('Disabled exec capability errors must identify the blocked package.');
strpos($disabled_exec_error['detail'], 'exec() is disabled') !== FALSE
	|| fail('Disabled exec capability errors must explain the missing process capability.');
strpos($disabled_exec_error['detail'], 'plugin operation was blocked') !== FALSE
	|| fail('Disabled exec capability errors must state the fail-closed installation outcome.');

$missing_cli = $app.'missing-php-cli'.(DIRECTORY_SEPARATOR === '\\' ? '.exe' : '');
$child_missing_cli = run_php_child(
	array(PHP_BINARY),
	syntax_child_code($root, $app, 'good', $missing_cli),
	'missing CLI preflight'
);
count($child_missing_cli) === 1 || fail('A missing PHP CLI binary must produce exactly one fail-closed capability error.');
$missing_cli_error = $child_missing_cli[0];
strpos($missing_cli_error['file'], 'plugin/good/') !== FALSE
	|| fail('Missing CLI capability errors must identify the blocked package.');
strpos($missing_cli_error['detail'], 'not found or is not executable') !== FALSE
	|| fail('Missing CLI capability errors must explain the binary capability failure.');
strpos($missing_cli_error['detail'], $missing_cli) !== FALSE
	|| fail('Missing CLI capability errors must report the path administrators need to correct.');

$good = plugin_php_syntax_errors('good');
empty($good) || fail('Hook fragments should be skipped and valid standalone plugin files should pass.');

$bad = plugin_php_syntax_errors('bad');
count($bad) === 2 || fail('Exactly two standalone PHP syntax errors should be reported.');
strpos($bad[0]['file'], 'plugin/bad/model/broken.func.php') !== FALSE
	|| fail('Syntax guard should report the standalone broken model file.');
$bad_files = implode("\n", array_column($bad, 'file'));
strpos($bad_files, 'plugin/bad/model/nested_ternary.func.php') !== FALSE
	|| fail('Syntax guard should report PHP 8 unsupported unparenthesized nested ternary files.');
strpos($bad[0]['file'], '/hook/') === FALSE
	|| fail('Syntax guard must not report hook fragments as standalone PHP errors.');

$legacy = plugin_php_syntax_errors('legacy');
count($legacy) === 6 || fail('Exactly six standalone PHP 8 removed-function errors should be reported.');
$legacy_details = implode("\n", array_column($legacy, 'detail'));
strpos($legacy_details, 'PHP 8 removed function call: each()') !== FALSE
	|| fail('Syntax guard should report removed PHP 8 function calls in standalone plugin files.');
strpos($legacy_details, 'PHP 8 removed function call: create_function()') !== FALSE
	|| fail('Syntax guard should report removed PHP 8 calls in overwrite package files.');
strpos($legacy_details, 'PHP 8 removed function call: mysql_query()') !== FALSE
	|| fail('Syntax guard should report an explicitly global removed mysql_* call.');
strpos($legacy[0]['file'], '/hook/') === FALSE
	|| fail('Syntax guard must not report removed PHP 8 calls inside hook fragments.');
$legacy_files = implode("\n", array_column($legacy, 'file'));
strpos($legacy_files, 'jquery_each.php') === FALSE
	|| fail('Syntax guard must not treat frontend jQuery .each() as removed PHP each().');
strpos($legacy_files, 'namespaced.func.php') === FALSE && strpos($legacy_files, 'attribute.func.php') === FALSE
	|| fail('Syntax guard must preserve namespaced package functions and Attribute class names.');
strpos($legacy_files, 'imported.func.php') === FALSE
	|| fail('Syntax guard must preserve the specific function name imported into the current namespace.');
strpos($legacy_files, 'polyfill.func.php') === FALSE
	|| fail('Syntax guard must preserve package-provided global polyfills for removed PHP functions.');
strpos($legacy_files, 'namespaced_removed.func.php') !== FALSE
	|| fail('A namespace declaration must not hide an unprovided mysql_* call in that namespace.');
strpos($legacy_files, 'import_mask.func.php') !== FALSE
	|| fail('Importing one function name must not hide a different removed function call.');
strpos($legacy_files, 'class_method_shadow.func.php') !== FALSE
	|| fail('A class method declaration must not be treated as a namespaced function polyfill.');

rm_dir($app);

if($symlink_skip_reason !== '') {
	echo 'SKIP: '.$symlink_skip_reason." Remaining non-symlink plugin PHP syntax guard checks passed.\n";
} else {
	echo "OK: plugin PHP syntax guard checks passed\n";
}
