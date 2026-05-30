<?php

$root = dirname(__DIR__);
$plugin_route = file_get_contents($root.'/admin/route/plugin.php');
$misc = file_get_contents($root.'/model/misc.func.php');
$plugin_list = file_get_contents($root.'/admin/view/htm/plugin_list.htm');
$plugin_read = file_get_contents($root.'/admin/view/htm/plugin_read.htm');
$bbs_js = file_get_contents($root.'/view/js/bbs.js');

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

strpos($plugin_route, "function plugin_message(\$code, \$message)") !== FALSE
	|| fail('plugin_message() helper is missing.');
strpos($plugin_route, "plugin_lock_end();\n\tmessage(\$code, \$message);") !== FALSE
	|| fail('plugin_message() must release the plugin task lock before exiting.');

strpos($plugin_route, "function plugin_lock_name()") !== FALSE
	|| fail('plugin_lock_name() helper is missing.');
strpos($plugin_route, "return 'plugin_task';") !== FALSE
	|| fail('Plugin writes must share one global plugin_task lock.');
strpos($plugin_route, "!xn_lock_start(plugin_lock_name(), 300)") !== FALSE
	|| fail('Plugin task lock must use the shared key with an extended TTL.');
strpos($plugin_route, "register_shutdown_function('plugin_shutdown_guard')") !== FALSE
	|| fail('Plugin task lock must register a shutdown guard for direct lifecycle exits.');
strpos($plugin_route, 'function plugin_shutdown_guard()') !== FALSE
	|| fail('Plugin shutdown guard helper is missing.');
strpos($plugin_route, "plugin_lifecycle_guard_restore();\n\tplugin_lock_end();") !== FALSE
	|| fail('Plugin shutdown guard must restore pending lifecycle state and release the task lock.');
strpos($plugin_route, 'function plugin_require_post()') !== FALSE
	|| fail('Plugin write actions must have a POST guard.');
strpos($plugin_route, 'function plugin_require_action_state($dir, $action, $plugin = NULL)') !== FALSE
	|| fail('Plugin write actions must have a state precondition helper.');
strpos($plugin_route, "lang('plugin_not_need_update')") !== FALSE
	|| fail('Plugin upgrade action must reject stale no-op upgrade requests.');

$dependency = section_between($plugin_route, 'function plugin_check_dependency', 'function plugin_dependency_arr_to_links');
substr_count($dependency, 'plugin_message(-1, $msg);') === 2
	|| fail('Dependency checks must release the lock before reporting errors.');
$dependency_direct = str_replace('plugin_message(-1, $msg)', '', $dependency);
strpos($dependency_direct, 'message(-1, $msg)') === FALSE
	|| fail('Dependency checks must not call message() directly.');

$download = section_between($plugin_route, 'function plugin_download_unzip', 'function plugin_package_snapshot');
strpos($download, 'plugin_official_remote_closed();') !== FALSE
	|| fail('Legacy official plugin download/unzip must fail closed while no trusted registry exists.');
strpos($download, 'http_get(') === FALSE && strpos($download, 'extractTo(') === FALSE && strpos($download, 'xn_unzip(') === FALSE
	|| fail('Legacy official plugin download/unzip must not fetch or extract remote packages.');
strpos($plugin_route, 'function plugin_official_remote_closed()') !== FALSE
	|| fail('Official plugin marketplace fail-closed helper is missing.');
strpos($plugin_route, "plugin_message(-1, plugin_official_remote_closed_error());") !== FALSE
	|| fail('Official plugin marketplace fail-closed helper must release the plugin task lock through plugin_message().');

$zip_validate = section_between($plugin_route, 'function plugin_zip_validate_package', 'function plugin_copy_dir');
strpos($zip_validate, 'xn_zip_safe_name($name)') !== FALSE
	|| fail('Plugin ZIP validation must reject unsafe archive paths.');
strpos($zip_validate, 'plugin_zip_entry_is_symlink($zip, $i)') !== FALSE
	|| fail('Plugin ZIP validation must reject symlink entries.');
strpos($zip_validate, "strpos(\$name, \$dir.'/') !== 0") !== FALSE
	|| fail('Plugin ZIP validation must constrain entries to the expected plugin directory.');
strpos($plugin_route, 'function plugin_zip_entry_is_symlink($zip, $index)') !== FALSE
	|| fail('Plugin ZIP symlink detector helper is missing.');

$copy_dir = section_between($plugin_route, 'function plugin_copy_dir', 'function plugin_mkdir_recursive');
strpos($copy_dir, 'return FALSE;') !== FALSE
	|| fail('Plugin package copy must return FALSE on write failures.');
strpos($copy_dir, 'is_link($item)') !== FALSE
	|| fail('Plugin package copy must reject symlinks before copying.');
strpos($copy_dir, 'Unsupported file type') !== FALSE
	|| fail('Plugin package copy must reject unsupported filesystem entries.');

$install = section_between($plugin_route, "} elseif(\$action == 'install')", "} elseif(\$action == 'unstall')");
strpos($install, 'plugin_check_auto_unstall_dependencies($dir);') !== FALSE
	|| fail('Install flow must preflight reverse dependencies before auto-uninstalling same-type plugins.');
strpos($install, 'plugin_auto_unstall_same_type($dir, $plugin_snapshot);') !== FALSE
	|| fail('Install flow must route same-type plugin cleanup through the guarded auto-uninstall helper.');
$last_unstall = strrpos($install, 'plugin_require_state_write(plugin_unstall($_dir), $_dir);');
$lock_end = strpos($install, 'plugin_lock_end();');
($last_unstall === FALSE && $lock_end !== FALSE)
	|| fail('Install flow must not directly unstall same-type plugins without dependency/lifecycle guards.');

$auto_candidates = section_between($plugin_route, 'function plugin_auto_unstall_candidates', 'function plugin_check_auto_unstall_dependencies');
strpos($auto_candidates, "empty(\$_plugin['installed'])") !== FALSE
	|| fail('Auto-uninstall candidates must be limited to installed plugins.');
$auto_dependency = section_between($plugin_route, 'function plugin_check_auto_unstall_dependencies', 'function plugin_auto_unstall_same_type');
strpos($auto_dependency, "plugin_check_dependency(\$_dir, 'unstall');") !== FALSE
	|| fail('Auto-uninstall must check reverse dependencies before installing the replacement.');
$auto_unstall = section_between($plugin_route, 'function plugin_auto_unstall_same_type', 'function plugin_check_dependency');
strpos($auto_unstall, '$restore_states = array();') !== FALSE
	|| fail('Auto-uninstall must track all same-type rollback snapshots in one replacement batch.');
strpos($auto_unstall, '$restore_states[$dir] = $primary_snapshot') !== FALSE
	|| fail('Auto-uninstall must include the newly installed plugin state in batch rollback context.');
strpos($auto_unstall, '$restore_states[$_dir] = $snapshot;') !== FALSE
	|| fail('Auto-uninstall must add each old same-type plugin snapshot before mutating it.');
strpos($auto_unstall, 'plugin_restore_extra_states($restore_states);') !== FALSE
	|| fail('Auto-uninstall write failures must restore every plugin state already touched in the batch.');
strpos($auto_unstall, "plugin_run_lifecycle(\$_dir, 'unstall', \$snapshot, NULL, \$restore_states);") !== FALSE
	|| fail('Auto-uninstall must run the old plugin unstall lifecycle with full batch rollback context.');
strpos($auto_unstall, 'plugin_check_auto_unstall_result($dir, $restore_states);') !== FALSE
	|| fail('Auto-uninstall must re-check the new plugin dependencies after same-type removals.');
$auto_result = section_between($plugin_route, 'function plugin_check_auto_unstall_result', 'function plugin_check_dependency');
strpos($auto_result, 'plugin_dependencies($dir)') !== FALSE
	|| fail('Auto-uninstall result check must inspect the new plugin dependencies.');
strpos($auto_result, 'plugin_restore_extra_states($restore_states);') !== FALSE
	|| fail('Auto-uninstall result failures must restore the full replacement batch.');
strpos($auto_result, 'plugin_dependency_arr_to_links($arr)') !== FALSE
	|| fail('Auto-uninstall result failures must show structured dependency details.');
strpos($auto_result, 'plugin_message(-1, $msg);') !== FALSE
	|| fail('Auto-uninstall result failures must release the plugin task lock.');

foreach (array('download', 'install', 'unstall', 'enable', 'disable', 'upgrade') as $action) {
	$branch = section_between($plugin_route, "} elseif(\$action == '$action')", "\n\tplugin_lock_start();");
	strpos($branch, 'plugin_require_post();') !== FALSE
		|| fail("Plugin $action action must require POST before locking.");
}

foreach (array('install', 'enable', 'upgrade') as $action) {
	$next = $action == 'install' ? 'unstall' : ($action == 'enable' ? 'disable' : 'setting');
	$branch = section_between($plugin_route, "} elseif(\$action == '$action')", "} elseif(\$action == '$next')");
	$code = preg_replace('#//[^\n]*#', '', $branch);
	if($action == 'upgrade' && strpos($code, 'plugin_official_remote_closed();') !== FALSE) {
		continue;
	}
	strpos($code, "plugin_require_action_state(\$dir, '$action") !== FALSE
		|| fail("Plugin $action action must reject stale or repeated state transitions.");
	(
		strpos($code, "plugin_check_dependency(\$dir, 'install');") !== FALSE ||
		strpos($code, "plugin_check_dependency(\$dir, 'install',") !== FALSE
	)
		|| fail("Plugin $action action must check install dependencies in executable code.");
	(strpos($code, 'plugin_check_php_syntax($dir);') !== FALSE || strpos($code, 'plugin_check_php_syntax($dir, $package_snapshot);') !== FALSE)
		|| fail("Plugin $action action must run PHP syntax checks before enabling code.");
}

foreach (array('unstall', 'disable') as $action) {
	$next = $action == 'unstall' ? 'enable' : 'upgrade';
	$branch = section_between($plugin_route, "} elseif(\$action == '$action')", "} elseif(\$action == '$next')");
	$code = preg_replace('#//[^\n]*#', '', $branch);
	strpos($code, "plugin_require_action_state(\$dir, '$action") !== FALSE
		|| fail("Plugin $action action must reject stale or repeated state transitions.");
	strpos($code, "plugin_check_dependency(\$dir, 'unstall');") !== FALSE
		|| fail("Plugin $action action must check reverse dependencies in executable code.");
}

foreach (array($plugin_list, $plugin_read) as $template) {
	foreach (array('plugin-download-', 'plugin-install-', 'plugin-unstall-', 'plugin-enable-', 'plugin-disable-', 'plugin-upgrade-') as $needle) {
		$pos = strpos($template, $needle);
		while($pos !== FALSE) {
			$line_start = strrpos(substr($template, 0, $pos), "\n");
			$line_end = strpos($template, "\n", $pos);
			$line = substr($template, $line_start === FALSE ? 0 : $line_start, $line_end - $line_start);
			if(strpos($line, '<a ') === FALSE) {
				$pos = strpos($template, $needle, $pos + strlen($needle));
				continue;
			}
			strpos($line, 'data-method="post"') !== FALSE
				|| fail("$needle link must use data-method=\"post\".");
			$pos = strpos($template, $needle, $pos + strlen($needle));
		}
	}
}

strpos($plugin_read, 'class="btn btn-primary download" data-method="post"') !== FALSE
	|| fail('Plugin read download link must use data-method="post".');

strpos($bbs_js, 'a[data-method="post"]:not(.confirm)') !== FALSE
	|| fail('Non-confirm POST links must be handled by bbs.js.');
strpos($bbs_js, 'function xn_post_link_lock(jlink)') !== FALSE
	|| fail('POST links must share a pending-state guard in bbs.js.');
strpos($bbs_js, "if(!xn_post_link_lock(jthis)) return false;") !== FALSE
	|| fail('POST links must ignore duplicate clicks while a request is pending.');
strpos($bbs_js, 'xn_post_link_done(jthis, code, message);') !== FALSE
	|| fail('POST links must restore pending state on failed requests.');

strpos($misc, "fopen(\$lockfile, 'x')") !== FALSE
	|| fail('xn_lock_start() should create lock files atomically.');
strpos($misc, 'function xn_lock_token()') !== FALSE
	|| fail('xn_lock_start() must create owner tokens for lock files.');
strpos($misc, '$g_xn_lock_tokens[$lockname] = $token;') !== FALSE
	|| fail('xn_lock_start() must remember the current task owner token.');
strpos($misc, 'hash_equals($g_xn_lock_tokens[$lockname], $token)') !== FALSE
	|| fail('xn_lock_end() must only remove locks owned by the current task.');

echo "OK: plugin task lock safety checks passed\n";
