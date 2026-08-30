<?php

$root = dirname(__DIR__);
$app = rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/').'/xiuno_plugin_file_index_'.getmypid().'_'.substr(hash('sha256', __FILE__), 0, 10).'/';
$skips = array();

function fail($message) {
	fwrite(STDERR, "FAIL: $message\n");
	exit(1);
}

function rm_dir($dir) {
	if(!is_dir($dir) || is_link($dir)) return;
	$items = glob(rtrim($dir, '/').'/*');
	$items = is_array($items) ? $items : array();
	$dotitems = glob(rtrim($dir, '/').'/.??*');
	if(is_array($dotitems)) $items = array_merge($items, $dotitems);
	foreach($items as $item) {
		if(is_link($item) || !is_dir($item)) {
			@unlink($item);
		} else {
			rm_dir($item);
		}
	}
	@rmdir($dir);
}

function write_plugin($app, $dir, $installed, $enable, $hook_rank, $overwrite_rank, $hook_body, $overwrite_body) {
	$path = $app.'plugin/'.$dir.'/';
	$overwrite_relative = 'view/htm/nested/index_target.htm';
	@mkdir($path.'hook/', 0777, TRUE);
	@mkdir(dirname($path.'overwrite/'.$overwrite_relative), 0777, TRUE);
	$conf = array(
		'name'=>$dir,
		'brief'=>'Plugin file index smoke fixture',
		'version'=>'1.0.0',
		'bbs_version'=>'4.0',
		'installed'=>$installed,
		'enable'=>$enable,
		'hooks_rank'=>array('demo_hook.htm'=>$hook_rank),
		'overwrites_rank'=>array($overwrite_relative=>$overwrite_rank),
		'dependencies'=>array(),
	);
	file_put_contents($path.'conf.json', json_encode($conf, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
	if($hook_body !== NULL) file_put_contents($path.'hook/demo_hook.htm', $hook_body);
	if($overwrite_body !== NULL) file_put_contents($path.'overwrite/'.$overwrite_relative, $overwrite_body);
}

function function_body($source, $name) {
	$pattern = '#function\s+'.preg_quote($name, '#').'\s*\([^)]*\)(.*?)(?=^function\s+\w|\z)#ms';
	return preg_match($pattern, $source, $match) ? $match[1] : FALSE;
}

rm_dir($app);
@mkdir($app.'plugin/', 0777, TRUE);
@mkdir($app.'tmp/', 0777, TRUE);
@mkdir($app.'view/htm/nested/', 0777, TRUE);
register_shutdown_function(function() use ($app) { rm_dir($app); });

$hook_target = $app.'view/htm/hook_target.htm';
$symlink_hook_target = $app.'view/htm/symlink_hook_target.htm';
$package_root_hook_target = $app.'view/htm/package_root_hook_target.htm';
$overwrite_target = $app.'view/htm/nested/index_target.htm';
$package_root_overwrite_target = $app.'view/htm/nested/package_root_target.htm';
file_put_contents($hook_target, "START|\n<!--{hook demo_hook.htm}-->\n|END");
file_put_contents($symlink_hook_target, "SYMLINK-START|\n<!--{hook outside_hook.htm}-->\n|SYMLINK-END");
file_put_contents($package_root_hook_target, "PACKAGE-ROOT-START|\n<!--{hook package_root_hook.htm}-->\n|PACKAGE-ROOT-END");
file_put_contents($overwrite_target, 'core-overwrite-target');
file_put_contents($package_root_overwrite_target, 'core-package-root-target');

write_plugin($app, 'aa_low', 1, 1, 10, 10, 'HOOK-LOW|', 'overwrite-low');
write_plugin($app, 'mm_high', 1, 1, 30, 20, 'HOOK-HIGH|', 'overwrite-high');
write_plugin($app, 'xx_disabled', 1, 0, 100, 100, 'HOOK-DISABLED|', 'overwrite-disabled');
write_plugin($app, 'xy_not_installed', 0, 1, 110, 110, 'HOOK-NOT-INSTALLED|', 'overwrite-not-installed');
write_plugin($app, 'zz_equal', 1, 1, 10, 20, 'HOOK-EQUAL|', 'overwrite-equal-later');
write_plugin($app, 'zz_symlink', 1, 1, 5, 200, 'HOOK-SYMLINK-PACKAGE|', NULL);
write_plugin($app, 'nn_negative', 1, 1, -10, -20, NULL, 'overwrite-negative-shared');

$negative_target = $app.'view/htm/nested/negative_target.htm';
$negative_relative = 'view/htm/nested/negative_target.htm';
$negative_overwrite = $app.'plugin/nn_negative/overwrite/'.$negative_relative;
file_put_contents($negative_target, 'core-negative-target');
file_put_contents($negative_overwrite, 'overwrite-negative-only');
$negative_conf_file = $app.'plugin/nn_negative/conf.json';
$negative_conf = json_decode(file_get_contents($negative_conf_file), TRUE);
$negative_conf['overwrites_rank'][$negative_relative] = -25;
file_put_contents($negative_conf_file, json_encode($negative_conf, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

$case_target = $app.'view/htm/case_target.htm';
$case_overwrite = $app.'plugin/mm_high/overwrite/view/htm/CASE_TARGET.htm';
file_put_contents($case_target, 'core-case-target');
file_put_contents($case_overwrite, 'overwrite-case-target');
$case_hook_target = $app.'view/htm/case_hook_target.htm';
$case_hook_file = $app.'plugin/mm_high/hook/CASE_ONLY.HTM';
$case_hook_low_file = $app.'plugin/aa_low/hook/case_only.htm';
file_put_contents($case_hook_target, "CASE-START\n<!--{hook case_only.htm}-->\nCASE-END");
file_put_contents($case_hook_file, 'HOOK-CASE-INSENSITIVE|');
file_put_contents($case_hook_low_file, 'HOOK-CASE-LOW|');
$case_conf_file = $app.'plugin/mm_high/conf.json';
$case_conf = json_decode(file_get_contents($case_conf_file), TRUE);
$case_conf['overwrites_rank']['view/htm/case_target.htm'] = 25;
$case_conf['hooks_rank']['case_only.htm'] = 40;
file_put_contents($case_conf_file, json_encode($case_conf, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
$case_low_conf_file = $app.'plugin/aa_low/conf.json';
$case_low_conf = json_decode(file_get_contents($case_low_conf_file), TRUE);
$case_low_conf['hooks_rank']['case_only.htm'] = 20;
file_put_contents($case_low_conf_file, json_encode($case_low_conf, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

$outside_overwrite = $app.'outside-overwrite.htm';
$symlink_overwrite = $app.'plugin/zz_symlink/overwrite/view/htm/nested/index_target.htm';
file_put_contents($outside_overwrite, 'overwrite-symlink-outside');
$symlink_created = @symlink($outside_overwrite, $symlink_overwrite);
clearstatcache(TRUE, $symlink_overwrite);
$symlink_created = $symlink_created && is_link($symlink_overwrite);

$outside_hook = $app.'outside-hook.htm';
$symlink_hook = $app.'plugin/zz_symlink/hook/outside_hook.htm';
file_put_contents($outside_hook, 'HOOK-SYMLINK-OUTSIDE|');
$hook_symlink_created = @symlink($outside_hook, $symlink_hook);
clearstatcache(TRUE, $symlink_hook);
$hook_symlink_created = $hook_symlink_created && is_link($symlink_hook);

$outside_hook_dir = $app.'outside-hook-dir/';
$hook_dir_plugin = $app.'plugin/zy_hook_dir_symlink/';
@mkdir($outside_hook_dir, 0777, TRUE);
@mkdir($hook_dir_plugin, 0777, TRUE);
file_put_contents($outside_hook_dir.'outside_hook.htm', 'HOOK-SYMLINK-DIR-OUTSIDE|');
file_put_contents($hook_dir_plugin.'conf.json', json_encode(array(
	'name'=>'zy_hook_dir_symlink',
	'installed'=>1,
	'enable'=>1,
	'hooks_rank'=>array('outside_hook.htm'=>500),
	'overwrites_rank'=>array(),
	'dependencies'=>array(),
), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
$hook_dir_symlink_created = @symlink(rtrim($outside_hook_dir, '/'), $hook_dir_plugin.'hook');
clearstatcache(TRUE, $hook_dir_plugin.'hook');
$hook_dir_symlink_created = $hook_dir_symlink_created && is_link($hook_dir_plugin.'hook');

$symlink_dir_target = $app.'view/htm/symlink_dir/symlink_dir_target.htm';
$outside_dir = $app.'outside-overwrite-dir/';
$symlink_dir = $app.'plugin/zz_symlink/overwrite/view/htm/symlink_dir/';
@mkdir(dirname($symlink_dir_target), 0777, TRUE);
file_put_contents($symlink_dir_target, 'core-symlink-dir-target');
@mkdir($outside_dir, 0777, TRUE);
@mkdir(dirname(rtrim($symlink_dir, '/')), 0777, TRUE);
file_put_contents($outside_dir.'symlink_dir_target.htm', 'overwrite-through-symlink-dir');
$symlink_dir_created = @symlink(rtrim($outside_dir, '/'), rtrim($symlink_dir, '/'));
clearstatcache(TRUE, rtrim($symlink_dir, '/'));
$symlink_dir_created = $symlink_dir_created && is_link(rtrim($symlink_dir, '/'));
$symlink_conf_file = $app.'plugin/zz_symlink/conf.json';
$symlink_conf = json_decode(file_get_contents($symlink_conf_file), TRUE);
$symlink_conf['overwrites_rank']['view/htm/symlink_dir/symlink_dir_target.htm'] = 250;
file_put_contents($symlink_conf_file, json_encode($symlink_conf, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

// A whole package root symlink is more dangerous than an individual Hook symlink: if both the
// candidate file and its comparison root resolve through the same link, a within(root) check made
// below that boundary incorrectly proves an external file safe. Package discovery must reject the
// link before reading conf.json or building any runtime index.
$outside_package_root = $app.'outside-package-root/';
$package_root_symlink = $app.'plugin/yy_package_root_link';
@mkdir($outside_package_root.'hook/', 0777, TRUE);
@mkdir($outside_package_root.'overwrite/view/htm/nested/', 0777, TRUE);
file_put_contents($outside_package_root.'conf.json', json_encode(array(
	'name'=>'External package root fixture',
	'installed'=>1,
	'enable'=>1,
	'hooks_rank'=>array('package_root_hook.htm'=>1000),
	'overwrites_rank'=>array('view/htm/nested/package_root_target.htm'=>1000),
	'dependencies'=>array(),
), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
file_put_contents($outside_package_root.'hook/package_root_hook.htm', 'HOOK-OUTSIDE-PACKAGE-ROOT|');
file_put_contents($outside_package_root.'overwrite/view/htm/nested/package_root_target.htm', 'overwrite-outside-package-root');
$package_root_symlink_created = @symlink(rtrim($outside_package_root, '/'), $package_root_symlink);
clearstatcache(TRUE, $package_root_symlink);
$package_root_symlink_created = $package_root_symlink_created && is_link($package_root_symlink);
if(!$package_root_symlink_created && DIRECTORY_SEPARATOR !== '\\') {
	fail('A Unix test host must support the package-root symlink negative case; refusing a false PASS.');
}
foreach(array(
	'overwrite file symlink'=>$symlink_created,
	'Hook file symlink'=>$hook_symlink_created,
	'Hook directory symlink'=>$hook_dir_symlink_created,
	'overwrite directory symlink'=>$symlink_dir_created,
	'package-root symlink'=>$package_root_symlink_created,
) as $label=>$created) {
	if(!$created) $skips[] = $label.' creation is unavailable; its file-index boundary fixture was not exercised.';
}

defined('DEBUG') || define('DEBUG', 0);
defined('APP_PATH') || define('APP_PATH', $app);
defined('ADMIN_PATH') || define('ADMIN_PATH', $app.'admin/');
defined('XIUNOPHP_PATH') || define('XIUNOPHP_PATH', $root.'/xiunophp/');
$_SERVER['time'] = time();
$_SERVER['ajax'] = 0;
$_SERVER['lang'] = array('no'=>'no', 'yes'=>'yes');
$_SERVER['conf'] = array(
	'sitename'=>'plugin file index smoke',
	'tmp_path'=>$app.'tmp/',
	'url_rewrite_on'=>0,
);
$time = $_SERVER['time'];
$conf = $_SERVER['conf'];

include $root.'/xiunophp/array.func.php';
include $root.'/xiunophp/misc.func.php';
include $root.'/model/plugin.func.php';

plugin_init();
$index = plugin_file_index();
if($index['generation'] !== plugin_file_index_generation()) {
	fail('Built file index must be bound to the current request generation.');
}
if(count(plugin_paths_enabled()) !== 6) {
	fail('Only installed and enabled packages may enter the request file index.');
}
if(plugin_package_root_path('aa_low') === FALSE) {
	fail('A normal readable package directory inside APP_PATH/plugin must pass the canonical package-root boundary.');
}
if($package_root_symlink_created) {
	if(plugin_package_root_path('yy_package_root_link') !== FALSE || isset($plugins['yy_package_root_link'])) {
		fail('A symlink used as the whole package root must not enter local plugin metadata.');
	}
	if(array_key_exists($package_root_symlink, plugin_paths_enabled())) {
		fail('A symlink used as the whole package root must not enter the enabled-package snapshot.');
	}
	if(strpos(plugin_compile_srcfile($package_root_hook_target), 'HOOK-OUTSIDE-PACKAGE-ROOT|') !== FALSE) {
		fail('A Hook below an external package-root symlink must not enter runtime compilation.');
	}
	if(plugin_find_overwrite($package_root_overwrite_target) !== $package_root_overwrite_target) {
		fail('An overwrite below an external package-root symlink must not replace a core file.');
	}
}

$selected = plugin_find_overwrite($overwrite_target);
$expected = $app.'plugin/zz_equal/overwrite/view/htm/nested/index_target.htm';
if($selected !== $expected || plugin_compile_srcfile($overwrite_target) !== 'overwrite-equal-later') {
	fail('Nested overwrite lookup must preserve rank arbitration and let the later equal-rank package win.');
}
if(plugin_find_overwrite($negative_target) !== $negative_target
	|| plugin_compile_srcfile($negative_target) !== 'core-negative-target') {
	fail('Negative-rank overwrite candidates must preserve the historical core-file fallback.');
}
if($symlink_created && $selected === $symlink_overwrite) {
	fail('The actual-file index must not select a symlink overwrite outside the package tree.');
}
if($symlink_dir_created && plugin_find_overwrite($symlink_dir_target) !== $symlink_dir_target) {
	fail('The actual-file index must not traverse an overwrite symlink directory outside the package tree.');
}
$symlink_hook_compiled = plugin_compile_srcfile($symlink_hook_target);
if($hook_symlink_created && strpos($symlink_hook_compiled, 'HOOK-SYMLINK-OUTSIDE|') !== FALSE) {
	fail('The actual-file index must not compile a Hook file symlink outside the package tree.');
}
if($hook_dir_symlink_created && strpos($symlink_hook_compiled, 'HOOK-SYMLINK-DIR-OUTSIDE|') !== FALSE) {
	fail('The actual-file index must not traverse a Hook root symlink outside the package tree.');
}
$case_selected = plugin_find_overwrite($case_target);
if(!plugin_file_index_case_sensitive() && $case_selected !== str_replace('\\', '/', $case_overwrite)) {
	fail('A case-insensitive backing filesystem must preserve the legacy is_file() overwrite behavior even under a Linux PHP runtime.');
}
if(plugin_file_index_case_sensitive() && $case_selected !== $case_target) {
	fail('Case-distinct overwrite paths must remain distinct on case-sensitive filesystems.');
}
$case_hook_compiled = plugin_compile_srcfile($case_hook_target);
$case_hook_key = plugin_file_index_hook_key('case_only.htm');
if(!plugin_file_index_case_sensitive()) {
	if(strpos($case_hook_compiled, 'HOOK-CASE-INSENSITIVE|') === FALSE) {
		fail('A case-insensitive backing filesystem must preserve legacy Hook filename matching.');
	}
	$case_high_position = strpos($case_hook_compiled, 'HOOK-CASE-INSENSITIVE|');
	$case_low_position = strpos($case_hook_compiled, 'HOOK-CASE-LOW|');
	if($case_low_position === FALSE || $case_high_position >= $case_low_position) {
		fail('Case-insensitive Hook rank lookup must normalize conf.json keys with actual filenames.');
	}
	if(empty($index['hooks'][$case_hook_key]) || !isset($index['hook_mtimes'][$case_hook_key])) {
		fail('Case-insensitive Hook bodies and mtimes must share the normalized marker key.');
	}
	$case_compiled_file = _include($case_hook_target);
	$case_compiled_mtime = filemtime($case_compiled_file);
	file_put_contents($case_hook_file, 'HOOK-CASE-UPDATED|');
	touch($case_hook_file, $case_compiled_mtime + 10);
	clearstatcache(TRUE, $case_hook_file);
	plugin_file_index_reset();
	$case_compiled_after = file_get_contents(_include($case_hook_target));
	if(strpos($case_compiled_after, 'HOOK-CASE-UPDATED|') === FALSE
		|| strpos($case_compiled_after, 'HOOK-CASE-INSENSITIVE|') !== FALSE) {
		fail('A differently-cased Hook mtime must invalidate the normalized include cache entry.');
	}
} elseif(strpos($case_hook_compiled, 'HOOK-CASE-INSENSITIVE|') !== FALSE
	|| strpos($case_hook_compiled, 'HOOK-CASE-LOW|') === FALSE) {
	fail('Case-distinct Hook filenames must remain distinct while the exact lowercase marker still compiles.');
}

$compiled_hooks = plugin_compile_srcfile($hook_target);
$high = strpos($compiled_hooks, 'HOOK-HIGH|');
$low = strpos($compiled_hooks, 'HOOK-LOW|');
$equal = strpos($compiled_hooks, 'HOOK-EQUAL|');
$symlink_package = strpos($compiled_hooks, 'HOOK-SYMLINK-PACKAGE|');
if($high === FALSE || $low === FALSE || $equal === FALSE || $symlink_package === FALSE || !($high < $low && $low < $equal && $equal < $symlink_package)) {
	fail('Hook compilation must preserve descending rank and existing equal-rank package order. Got: '.$compiled_hooks);
}
if(strpos($compiled_hooks, 'HOOK-DISABLED|') !== FALSE || strpos($compiled_hooks, 'HOOK-NOT-INSTALLED|') !== FALSE) {
	fail('Disabled and not-installed Hook files must not enter compilation.');
}

$compiled_file = _include($hook_target);
$compiled_before = file_get_contents($compiled_file);
if(strpos($compiled_before, 'HOOK-HIGH|') === FALSE) {
	fail('Initial include cache must contain the indexed Hook body.');
}
$compiled_mtime = filemtime($compiled_file);
$high_hook = $app.'plugin/mm_high/hook/demo_hook.htm';
file_put_contents($high_hook, 'HOOK-HIGH-UPDATED|');
touch($high_hook, $compiled_mtime + 5);
clearstatcache(TRUE, $high_hook);
$generation_before_mtime_reset = plugin_file_index_generation();
plugin_file_index_reset();
if(plugin_file_index_generation() <= $generation_before_mtime_reset) {
	fail('Explicit file-index reset must advance the generation.');
}
$compiled_after = file_get_contents(_include($hook_target));
if(strpos($compiled_after, 'HOOK-HIGH-UPDATED|') === FALSE || strpos($compiled_after, 'HOOK-HIGH|') !== FALSE) {
	fail('A newer indexed Hook mtime must invalidate and rebuild the include cache.');
}

$generation_before_disable = plugin_file_index_generation();
if(!plugin_disable('zz_equal')) {
	fail('Fixture package disable failed.');
}
if(plugin_file_index_generation() <= $generation_before_disable) {
	fail('Plugin state/cache clearing must reset the request file-index generation.');
}
$selected_after_disable = plugin_find_overwrite($overwrite_target);
$expected_after_disable = $app.'plugin/mm_high/overwrite/view/htm/nested/index_target.htm';
if($selected_after_disable !== $expected_after_disable) {
	fail('The same request must not reuse an enabled-set/index snapshot after plugin_disable().');
}
if(strpos(plugin_compile_srcfile($hook_target), 'HOOK-EQUAL|') !== FALSE) {
	fail('The same request must not reuse disabled Hook paths after lifecycle cache clearing.');
}
if(!plugin_enable('zz_equal') || plugin_find_overwrite($overwrite_target) !== $expected) {
	fail('Plugin re-enable must rebuild the same-request enabled set and actual-file index.');
}

$source = file_get_contents($root.'/model/plugin.func.php');
$mtime_body = function_body($source, 'plugin_include_src_mtime');
$overwrite_body = function_body($source, 'plugin_find_overwrite');
$callback_body = function_body($source, 'plugin_compile_srcfile_callback');
$index_body = function_body($source, 'plugin_file_index');
$case_body = function_body($source, 'plugin_file_index_case_sensitive');
$path_key_body = function_body($source, 'plugin_file_index_path_key');
$hook_key_body = function_body($source, 'plugin_file_index_hook_key');
$reset_body = function_body($source, 'plugin_file_index_reset');
$init_body = function_body($source, 'plugin_init');
$package_roots_body = function_body($source, 'plugin_package_roots');
$enabled_paths_body = function_body($source, 'plugin_paths_enabled');
$clear_body = function_body($source, 'runtime_cache_clear_regenerable');
if($mtime_body === FALSE || $overwrite_body === FALSE || $callback_body === FALSE || $index_body === FALSE || $case_body === FALSE || $path_key_body === FALSE || $hook_key_body === FALSE || $reset_body === FALSE || $package_roots_body === FALSE || $enabled_paths_body === FALSE) {
	fail('File-index compiler functions must remain inspectable by the structural guard.');
}
if(strpos($mtime_body, 'plugin_file_index()') === FALSE || strpos($mtime_body, 'plugin_file_index_hook_key($hookname)') === FALSE || strpos($mtime_body, 'plugin_paths_enabled()') !== FALSE || strpos($mtime_body, '/hook/') !== FALSE) {
	fail('plugin_include_src_mtime() must use indexed Hook mtimes without constructing per-package candidates.');
}
if(strpos($overwrite_body, 'plugin_file_index()') === FALSE || strpos($overwrite_body, 'plugin_paths_enabled()') !== FALSE || strpos($overwrite_body, '/overwrite/') !== FALSE || strpos($overwrite_body, 'is_file(') !== FALSE) {
	fail('plugin_find_overwrite() must be an index lookup without per-package missing-file probes.');
}
if(strpos($callback_body, 'plugin_file_index()') === FALSE || strpos($callback_body, 'plugin_file_index_hook_key($hookname)') === FALSE || strpos($callback_body, 'glob(') !== FALSE || strpos($callback_body, 'static $hooks') !== FALSE) {
	fail('Hook compilation callback must reuse the generation-aware request file index.');
}
if(substr_count($index_body, "glob(\$hook_root.'*.*')") !== 1 || strpos($index_body, 'plugin_file_index_hook_key($hookname)') === FALSE
	|| strpos($index_body, '$hook_ranks[plugin_file_index_hook_key($rank_name)] = $rank;') === FALSE
	|| strpos($index_body, 'is_link($hookpath)') === FALSE || strpos($index_body, 'plugin_realpath_within($hookpath, $hook_root)') === FALSE
	|| strpos($index_body, 'plugin_file_index_overwrite_files(') === FALSE || strpos($index_body, 'plugin_paths_enabled()') === FALSE) {
	fail('The request index must enumerate actual Hook and nested overwrite files once per enabled-set generation.');
}
if(strpos($case_body, '__FILE__') === FALSE || strpos($case_body, 'is_file(') === FALSE || strpos($case_body, 'DIRECTORY_SEPARATOR') !== FALSE
	|| strpos($path_key_body, 'plugin_file_index_case_sensitive()') === FALSE || strpos($path_key_body, 'DIRECTORY_SEPARATOR') !== FALSE
	|| strpos($hook_key_body, 'plugin_file_index_path_key($hookname)') === FALSE) {
	fail('Case folding must follow a read-only probe of the actual backing filesystem, not the PHP runtime OS.');
}
if(strpos($reset_body, '$g_plugin_file_index_generation++') === FALSE || strpos($init_body, 'plugin_file_index_reset();') === FALSE || strpos($clear_body, 'plugin_file_index_reset();') === FALSE) {
	fail('Plugin initialization and cache clearing must explicitly invalidate enabled paths and the file index.');
}
if(strpos($package_roots_body, 'plugin_package_root_path(') === FALSE
	|| strpos($init_body, 'plugin_package_roots()') === FALSE
	|| strpos($enabled_paths_body, 'plugin_package_roots()') === FALSE
	|| strpos($init_body, "glob(APP_PATH.'plugin/*'") !== FALSE
	|| strpos($enabled_paths_body, "glob(APP_PATH.'plugin/*'") !== FALSE) {
	fail('Metadata discovery and enabled-path snapshots must share the canonical package-root validator.');
}

rm_dir($app);
echo "OK: plugin actual-file index checks passed for available fixtures\n";
foreach($skips as $skip) echo 'SKIP: '.$skip.PHP_EOL;

?>
