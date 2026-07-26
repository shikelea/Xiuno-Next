<?php

$root = dirname(__DIR__);
$plugin_route = file_get_contents($root.'/admin/route/plugin.php');
$plugin_model = file_get_contents($root.'/model/plugin.func.php');
$misc_model = file_get_contents($root.'/model/misc.func.php');

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
strpos($lifecycle, 'plugin_compat_include_lifecycle($file)') !== FALSE
	|| fail('Plugin lifecycle execution must preserve the legacy global include scope.');
$compat_include = section_between($plugin_model, 'function plugin_compat_include_lifecycle(', 'function plugin_compat_form_action_is_local');
strpos($compat_include, 'extract($GLOBALS, EXTR_REFS | EXTR_SKIP);') !== FALSE
	|| fail('Plugin lifecycle include scope must expose legacy global variables by reference.');
strpos($lifecycle, 'plugin_lifecycle_restore_or_fail($dir, $snapshot, $package_snapshot, $extra_state_restore);') !== FALSE
	|| fail('Plugin lifecycle failures must restore state and package snapshots through the checked rollback helper.');
$restore_helper = section_between($plugin_route, 'function plugin_lifecycle_restore_or_fail(', 'function plugin_lifecycle_handle_message');
$package_restore = section_between($plugin_route, 'function plugin_package_restore(', 'function plugin_package_snapshot_delete');
strpos($restore_helper, 'if($snapshot !== NULL && !plugin_state_restore($dir, $snapshot)) $ok = FALSE;') !== FALSE
	|| fail('Plugin lifecycle rollback must check primary state restoration.');
strpos($restore_helper, 'if(!plugin_restore_extra_states($extra_state_restore)) $ok = FALSE;') !== FALSE
	|| fail('Plugin lifecycle rollback must check related state restoration.');
strpos($restore_helper, 'if($package_snapshot !== NULL) plugin_package_restore($package_snapshot);') !== FALSE
	|| fail('Plugin lifecycle failures must restore downloaded package files when available.');
strpos($package_restore, "plugin_message(-1, 'Plugin package rollback failed:") !== FALSE
	|| fail('Plugin package restoration must hard-fail instead of returning an unchecked rollback error.');
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
$message_function = section_between($misc_model, 'function message($code, $message, $extra = array())', 'function xn_lock_start(');
strpos($message_function, "if(function_exists('plugin_lifecycle_capture_message'))") !== FALSE
	|| fail('message() must detect plugin lifecycle capture support.');
strpos($message_function, 'plugin_lifecycle_capture_message($code, $message, $extra);') !== FALSE
	|| fail('message() must hand code, message, and extra to the lifecycle wrapper.');
$capture_helper = section_between($plugin_route, 'function plugin_lifecycle_capture_message(', 'function plugin_lifecycle_message_is_success');
strpos($capture_helper, 'if(empty($plugin_lifecycle_guard) || !is_array($plugin_lifecycle_guard)) return;') !== FALSE
	|| fail('Lifecycle message capture must be inactive outside a running plugin lifecycle.');
$guard_clear = section_between($plugin_route, 'function plugin_lifecycle_guard_clear()', 'function plugin_lifecycle_guard_restore');
strpos($guard_clear, '$plugin_lifecycle_message_pending = NULL;') !== FALSE
	|| fail('Lifecycle guard cleanup must clear any pending controlled message.');
strpos($plugin_route, 'final class PluginLifecycleMessage extends Error') !== FALSE
	|| fail('Plugin lifecycle controlled message exception is missing.');
strpos($plugin_route, 'catch(PluginLifecycleMessage $e)') !== FALSE
	|| fail('Plugin lifecycle wrapper must distinguish controlled messages from failures.');
strpos($plugin_route, 'function plugin_lifecycle_message_is_deferred($dir, $action, $message)') !== FALSE
	|| fail('Plugin lifecycle wrapper must recognize deferred install wizard forms.');
strpos($plugin_route, 'function plugin_lifecycle_form_action_is_local($action)') !== FALSE
	|| fail('Deferred lifecycle forms must reject external actions.');
strpos($plugin_route, "\$route === 'plugin-'.\$action.'-'.\$dir") !== FALSE
	|| fail('Deferred lifecycle messages must equal the current query-format lifecycle route.');
strpos($plugin_route, "\$route === 'plugin/'.\$action.'/'.\$dir") !== FALSE
	|| fail('Deferred lifecycle messages must equal the current path-format lifecycle route.');
strpos($plugin_route, 'function plugin_lifecycle_pending_message_take()') !== FALSE
	|| fail('Lifecycle messages swallowed by broad plugin catch blocks must remain detectable.');
strpos($plugin_route, 'function plugin_lifecycle_restore_or_fail(') !== FALSE
	|| fail('Lifecycle rollback failures must be surfaced.');
strpos($plugin_route, 'message($e->response_code, $e->response_message, $e->response_extra)') !== FALSE
	|| fail('Controlled lifecycle responses must preserve extra response fields.');

$auto_unstall = section_between($plugin_route, 'function plugin_auto_unstall_same_type(', 'function plugin_check_auto_unstall_result');
strpos($auto_unstall, "\$lifecycle_message = plugin_run_lifecycle(\$_dir, 'unstall', \$snapshot, NULL, \$restore_states);") !== FALSE
	|| fail('Same-type auto-uninstall must retain controlled lifecycle messages.');
strpos($auto_unstall, 'if(is_array($lifecycle_message))') !== FALSE
	|| fail('Same-type auto-uninstall must stop and restore replacement state on controlled lifecycle messages.');

$setting_branch = section_between($plugin_route, "} elseif(\$action == 'setting')", '// 检查目录是否可写');
strpos($setting_branch, "\$gid != 1 AND message(-1, lang('insufficient_privilege'));") !== FALSE
	|| fail('Plugin settings must allow the super administrator and reject other groups.');
strpos($setting_branch, "allowadminpanel") === FALSE
	|| fail('Plugin settings must not depend on the nonexistent allowadminpanel group field.');

foreach(array('install'=>'unstall', 'unstall'=>'enable', 'upgrade'=>'setting') as $action=>$next) {
	$branch = section_between($plugin_route, "} elseif(\$action == '$action')", "} elseif(\$action == '$next')");
	if($action == 'upgrade' && strpos($branch, 'plugin_official_remote_closed();') !== FALSE) {
		continue;
	}
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

$install_branch = section_between($plugin_route, "} elseif(\$action == 'install')", "} elseif(\$action == 'unstall')");
strpos($install_branch, "\$lifecycle_message = plugin_run_lifecycle(\$dir, 'install', \$plugin_snapshot);") !== FALSE
	|| fail('Plugin install must retain controlled lifecycle success messages until finalization completes.');
strpos($install_branch, 'if(is_array($lifecycle_message))') !== FALSE
	|| fail('Plugin install must only treat structured lifecycle messages as response payloads.');
strpos($install_branch, "message(\$lifecycle_message['code'], \$lifecycle_message['message'], \$lifecycle_message['extra']);") !== FALSE
	|| fail('Plugin install must emit controlled lifecycle messages after finalization.');
$install_lifecycle_pos = strpos($install_branch, "\$lifecycle_message = plugin_run_lifecycle(");
$install_finalize_pos = strpos($install_branch, 'plugin_auto_unstall_same_type(');
$install_unlock_pos = strpos($install_branch, 'plugin_lock_end();');
$install_response_pos = strpos($install_branch, 'if(is_array($lifecycle_message))');
($install_lifecycle_pos < $install_finalize_pos && $install_finalize_pos < $install_unlock_pos && $install_unlock_pos < $install_response_pos)
	|| fail('Plugin install must finalize same-type replacement and release the task lock before emitting a controlled success message.');

$unstall_branch = section_between($plugin_route, "} elseif(\$action == 'unstall')", "} elseif(\$action == 'enable')");
strpos($unstall_branch, "\$lifecycle_message = plugin_run_lifecycle(\$dir, 'unstall', \$plugin_snapshot);") !== FALSE
	|| fail('Plugin unstall must retain controlled lifecycle success messages until finalization completes.');
strpos($unstall_branch, 'if(is_array($lifecycle_message))') !== FALSE
	|| fail('Plugin unstall must only treat structured lifecycle messages as response payloads.');
strpos($unstall_branch, "message(\$lifecycle_message['code'], \$lifecycle_message['message'], \$lifecycle_message['extra']);") !== FALSE
	|| fail('Plugin unstall must emit controlled lifecycle messages after finalization.');
$unstall_lifecycle_pos = strpos($unstall_branch, "\$lifecycle_message = plugin_run_lifecycle(");
$unstall_unlock_pos = strpos($unstall_branch, 'plugin_lock_end();');
$unstall_response_pos = strpos($unstall_branch, 'if(is_array($lifecycle_message))');
($unstall_lifecycle_pos < $unstall_unlock_pos && $unstall_unlock_pos < $unstall_response_pos)
	|| fail('Plugin unstall must release the task lock before emitting a controlled success message.');

echo "OK: plugin lifecycle safety checks passed\n";
