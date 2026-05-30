<?php

$root = dirname(__DIR__);
$plugin_route = file_get_contents($root.'/admin/route/plugin.php');

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

$download_action = section_between($plugin_route, "} elseif(\$action == 'download')", "} elseif(\$action == 'install')");
strpos($download_action, 'plugin_official_remote_closed();') !== FALSE
	|| fail('Legacy official plugin download action must fail closed while no trusted registry exists.');
strpos($download_action, 'plugin_download_unzip(') === FALSE
	|| fail('Legacy official plugin download action must not continue into remote package retrieval.');

$upgrade_action = section_between($plugin_route, "} elseif(\$action == 'upgrade')", "} elseif(\$action == 'setting')");
strpos($upgrade_action, 'plugin_official_remote_closed();') !== FALSE
	|| fail('Legacy official plugin upgrade action must fail closed while no trusted registry exists.');
strpos($upgrade_action, 'plugin_download_unzip(') === FALSE
	|| fail('Legacy official plugin upgrade action must not continue into remote package retrieval.');

$download = section_between($plugin_route, 'function plugin_download_unzip', 'function plugin_package_snapshot');
strpos($download, 'function plugin_download_unzip($dir, $package_snapshot = NULL)') !== FALSE
	|| fail('plugin_download_unzip() must accept a package snapshot.');
strpos($download, 'plugin_official_remote_closed();') !== FALSE
	|| fail('plugin_download_unzip() must fail closed while no trusted registry exists.');

foreach(array('plugin_official_remote_closed', 'plugin_official_remote_closed_error') as $function) {
	strpos($plugin_route, "function $function(") !== FALSE
		|| fail("$function() helper is missing.");
}

foreach(array('plugin_is_bought', 'plugin_order_buy_qrcode_url') as $function) {
	$section = section_between($plugin_route, "function $function", $function == 'plugin_is_bought' ? 'function plugin_order_buy_qrcode_url' : 'function plugin_official_remote_closed');
	strpos($section, 'return xn_error(-1, plugin_official_remote_closed_error());') !== FALSE
		|| fail("$function() must fail closed instead of contacting the legacy official market.");
}

foreach(array('plugin-download', 'plugin-is_bought', 'plugin-buy_qrcode_url') as $endpoint) {
	strpos($plugin_route, 'PLUGIN_OFFICIAL_URL."'.$endpoint) === FALSE
		|| fail("Legacy official marketplace endpoint remains reachable: $endpoint.");
}
strpos($plugin_route, 'http_get($url)') === FALSE
	|| fail('Legacy official plugin marketplace must not fetch remote packages/payment checks over HTTP.');

foreach(array('plugin_package_snapshot', 'plugin_package_restore', 'plugin_package_snapshot_delete') as $function) {
	strpos($plugin_route, "function $function(") !== FALSE
		|| fail("$function() helper is missing.");
}

$dependency = section_between($plugin_route, 'function plugin_check_dependency', 'function plugin_reload_local');
strpos($dependency, 'plugin_package_restore($package_snapshot);') !== FALSE
	|| fail('Plugin dependency failures inside upgrade must restore the previous package directory.');
strpos($dependency, 'plugin_state_restore($dir, $snapshot);') !== FALSE
	|| fail('Plugin dependency failures inside upgrade must restore the previous plugin state.');

$reload = section_between($plugin_route, 'function plugin_reload_local', 'function plugin_require_state_write');
strpos($reload, 'file_get_contents($conffile)') !== FALSE
	|| fail('Plugin metadata reload must read the replaced package conf.json from disk.');
strpos($reload, '$plugins[$dir] = plugin_read_by_dir($dir);') !== FALSE
	|| fail('Plugin metadata reload must normalize the refreshed plugin record.');

$restore = section_between($plugin_route, 'function plugin_package_restore', 'function plugin_package_snapshot_delete');
strpos($restore, 'rmdir_recusive($dest_dir, 0);') !== FALSE
	|| fail('Plugin package restore must clear the partially replaced target directory.');
strpos($restore, 'plugin_copy_dir($backup_dir, $dest_dir, $error)') !== FALSE
	|| fail('Plugin package restore must copy the backup directory back into place.');
strpos($restore, 'plugin_clear_tmp_dir();') !== FALSE
	|| fail('Plugin package restore must clear compiled plugin cache after rollback.');

$copy = section_between($plugin_route, 'function plugin_copy_dir', 'function plugin_mkdir_recursive');
strpos($copy, 'plugin_dir_items($src)') !== FALSE
	|| fail('Plugin package copy must include dotfiles for complete package snapshots.');
strpos($plugin_route, 'function plugin_dir_items($dir)') !== FALSE
	|| fail('plugin_dir_items() helper is missing.');

echo "OK: plugin package rollback checks passed\n";
