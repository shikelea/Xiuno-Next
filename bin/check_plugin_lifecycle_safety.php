<?php

$root = dirname(__DIR__);
$plugin_route = file_get_contents($root.'/admin/route/plugin.php');
$plugin_model = file_get_contents($root.'/model/plugin.func.php');

function fail($message) {
	fwrite(STDERR, "FAIL: $message\n");
	exit(1);
}

function section_between($source, $start, $end) {
	$start_pos = strpos($source, $start);
	if($start_pos === FALSE) fail("Missing section start: $start");
	$end_pos = strpos($source, $end, $start_pos + strlen($start));
	if($end_pos === FALSE) fail("Missing section end after $start: $end");
	return substr($source, $start_pos, $end_pos - $start_pos);
}

$model_functions = array(
	'plugin_enable'=>'function plugin_clear_tmp_dir',
	'plugin_disable'=>'function plugin_install_all',
	'plugin_install'=>'function plugin_unstall',
	'plugin_unstall'=>'function plugin_state_snapshot',
);
foreach($model_functions as $function=>$next_function) {
	$section = section_between($plugin_model, "function $function(", $next_function);
	strpos($section, '$old = plugin_state_snapshot($dir);') !== FALSE
		|| fail("$function must snapshot plugin state before mutating globals.");
	strpos($section, '$r = file_replace_var(') !== FALSE
		|| fail("$function must check file_replace_var() result.");
	strpos($section, 'if($r === FALSE)') !== FALSE
		|| fail("$function must hard-fail on config write errors.");
	strpos($section, 'plugin_state_restore($dir, $old);') !== FALSE
		|| fail("$function must restore in-memory state when config write fails.");
}

strpos($plugin_model, 'function plugin_state_snapshot($dir)') !== FALSE
	|| fail('plugin_state_snapshot() helper is missing.');
strpos($plugin_model, 'function plugin_state_restore($dir, $snapshot)') !== FALSE
	|| fail('plugin_state_restore() helper is missing.');

strpos($plugin_route, 'function plugin_require_state_write($ok, $dir, $snapshot = NULL, $package_snapshot = NULL)') !== FALSE
	|| fail('plugin_require_state_write() helper is missing.');
strpos($plugin_route, "lang('save_conf_failed'") !== FALSE
	|| fail('Plugin config write failures must use the existing save_conf_failed message.');

$lifecycle = section_between($plugin_route, 'function plugin_run_lifecycle', 'function plugin_dependency_arr_to_links');
strpos($lifecycle, 'plugin_lifecycle_guard_start($dir, $action, $snapshot, $package_snapshot, $extra_state_restore);') !== FALSE
	|| fail('Plugin lifecycle execution must arm a shutdown rollback guard before including third-party code.');
strpos($lifecycle, 'plugin_lifecycle_guard_clear();') !== FALSE
	|| fail('Plugin lifecycle execution must clear the shutdown rollback guard after normal return or catch.');
strpos($lifecycle, 'catch(Throwable $e)') !== FALSE
	|| fail('Plugin lifecycle files must be wrapped in a Throwable catch.');
strpos($lifecycle, 'plugin_state_restore($dir, $snapshot);') !== FALSE
	|| fail('Plugin lifecycle failures must restore the previous state.');
strpos($lifecycle, 'plugin_restore_extra_states($extra_state_restore);') !== FALSE
	|| fail('Plugin lifecycle failures must restore related state snapshots when provided.');
strpos($lifecycle, 'plugin_package_restore($package_snapshot);') !== FALSE
	|| fail('Plugin lifecycle failures must restore downloaded package files when available.');
strpos($lifecycle, 'plugin_message(-1') !== FALSE
	|| fail('Plugin lifecycle failures must release the task lock before exiting.');
strpos($plugin_route, 'function plugin_restore_extra_states($states)') !== FALSE
	|| fail('Related plugin state restore helper is missing.');
strpos($plugin_route, 'function plugin_lifecycle_guard_restore()') !== FALSE
	|| fail('Plugin lifecycle shutdown restore helper is missing.');
strpos($plugin_route, 'plugin_package_restore($guard[\'package_snapshot\'])') !== FALSE
	|| fail('Plugin lifecycle shutdown restore must restore package snapshots.');
strpos($plugin_route, "plugin_state_restore(\$guard['dir'], \$guard['snapshot'])") !== FALSE
	|| fail('Plugin lifecycle shutdown restore must restore plugin state snapshots.');

foreach(array('install'=>'unstall', 'unstall'=>'enable', 'upgrade'=>'setting') as $action=>$next) {
	$branch = section_between($plugin_route, "} elseif(\$action == '$action')", "} elseif(\$action == '$next')");
	strpos($branch, '$plugin_snapshot = plugin_state_snapshot($dir);') !== FALSE
		|| fail("Plugin $action action must snapshot state before lifecycle work.");
	strpos($branch, 'plugin_require_state_write(') !== FALSE
		|| fail("Plugin $action action must hard-fail on config write errors.");
	(
		strpos($branch, "plugin_run_lifecycle(\$dir, '$action', \$plugin_snapshot);") !== FALSE ||
		strpos($branch, "plugin_run_lifecycle(\$dir, '$action', \$plugin_snapshot, \$package_snapshot);") !== FALSE
	)
		|| fail("Plugin $action action must run lifecycle files through the rollback wrapper.");
}

foreach(array('enable'=>'disable', 'disable'=>'upgrade') as $action=>$next) {
	$branch = section_between($plugin_route, "} elseif(\$action == '$action')", "} elseif(\$action == '$next')");
	strpos($branch, 'plugin_require_state_write(') !== FALSE
		|| fail("Plugin $action action must hard-fail on config write errors.");
}

echo "OK: plugin lifecycle safety checks passed\n";
