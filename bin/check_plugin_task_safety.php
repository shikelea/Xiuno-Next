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
strpos($plugin_route, 'function plugin_require_post()') !== FALSE
	|| fail('Plugin write actions must have a POST guard.');

$dependency = section_between($plugin_route, 'function plugin_check_dependency', 'function plugin_dependency_arr_to_links');
substr_count($dependency, 'plugin_message(-1, $msg);') === 2
	|| fail('Dependency checks must release the lock before reporting errors.');
$dependency_direct = str_replace('plugin_message(-1, $msg)', '', $dependency);
strpos($dependency_direct, 'message(-1, $msg)') === FALSE
	|| fail('Dependency checks must not call message() directly.');

$download = section_between($plugin_route, 'function plugin_download_unzip', 'function plugin_is_bought');
substr_count($download, 'plugin_message(') >= 6
	|| fail('Download/unzip error exits must release the plugin task lock.');
$download_code = preg_replace('#//[^\n]*#', '', $download);
strpos($download_code, 'AND message(') === FALSE
	|| fail('Download/unzip guards must not call message() directly.');
strpos($download, 'file_put_contents($zipfile, $s) !== strlen($s)') !== FALSE
	|| fail('Plugin ZIP writes must detect short writes.');
strpos($download, 'plugin_zip_validate_package($zip, $dir, $zip_error)') !== FALSE
	|| fail('Plugin ZIP packages must be validated before extraction.');
strpos($download, 'if(!$zip->extractTo($extract_dir))') !== FALSE
	|| fail('Plugin ZIP extraction failures must stop the task.');
strpos($download, '$extract_dir =') !== FALSE && strpos($download, '$source_dir = $extract_dir.$dir') !== FALSE
	|| fail('Plugin ZIP packages must be extracted to a temporary directory first.');
strpos($download, 'plugin_copy_dir($source_dir, $dest_dir, $copy_error)') !== FALSE
	|| fail('Validated plugin packages must be copied into the target directory with checked writes.');
strpos($download_code, 'xn_unzip(') === FALSE
	|| fail('Plugin downloads must not use direct xn_unzip() into plugin/.');

$zip_validate = section_between($plugin_route, 'function plugin_zip_validate_package', 'function plugin_copy_dir');
strpos($zip_validate, 'xn_zip_safe_name($name)') !== FALSE
	|| fail('Plugin ZIP validation must reject unsafe archive paths.');
strpos($zip_validate, "strpos(\$name, \$dir.'/') !== 0") !== FALSE
	|| fail('Plugin ZIP validation must constrain entries to the expected plugin directory.');

$copy_dir = section_between($plugin_route, 'function plugin_copy_dir', 'function plugin_mkdir_recursive');
strpos($copy_dir, 'return FALSE;') !== FALSE
	|| fail('Plugin package copy must return FALSE on write failures.');

$install = section_between($plugin_route, "} elseif(\$action == 'install')", "} elseif(\$action == 'unstall')");
$last_unstall = strrpos($install, 'plugin_unstall($_dir);');
$lock_end = strpos($install, 'plugin_lock_end();');
($last_unstall !== FALSE && $lock_end !== FALSE && $lock_end > $last_unstall)
	|| fail('Install flow must keep auto-uninstall writes inside the plugin task lock.');

foreach (array('download', 'install', 'unstall', 'enable', 'disable', 'upgrade') as $action) {
	$branch = section_between($plugin_route, "} elseif(\$action == '$action')", "\n\tplugin_lock_start();");
	strpos($branch, 'plugin_require_post();') !== FALSE
		|| fail("Plugin $action action must require POST before locking.");
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

strpos($misc, "fopen(\$lockfile, 'x')") !== FALSE
	|| fail('xn_lock_start() should create lock files atomically.');

echo "OK: plugin task lock safety checks passed\n";
