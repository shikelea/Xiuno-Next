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
strpos($download_action, '$package_snapshot = plugin_package_snapshot($dir);') !== FALSE
	|| fail('Plugin download action must snapshot the target package directory before copying files.');
strpos($download_action, 'plugin_download_unzip($dir, $package_snapshot);') !== FALSE
	|| fail('Plugin download action must pass the package snapshot into download/unzip.');
strpos($download_action, 'plugin_package_snapshot_delete($package_snapshot);') !== FALSE
	|| fail('Plugin download action must delete package snapshots after success.');

$upgrade_action = section_between($plugin_route, "} elseif(\$action == 'upgrade')", "} elseif(\$action == 'setting')");
strpos($upgrade_action, '$package_snapshot = plugin_package_snapshot($dir);') !== FALSE
	|| fail('Plugin upgrade action must snapshot the old package directory before replacing files.');
strpos($upgrade_action, 'plugin_download_unzip($dir, $package_snapshot);') !== FALSE
	|| fail('Plugin upgrade action must pass the snapshot into download/unzip.');
strpos($upgrade_action, 'plugin_check_php_syntax($dir, $package_snapshot);') !== FALSE
	|| fail('Plugin upgrade syntax failures must restore the previous package directory.');
strpos($upgrade_action, 'plugin_reload_local($dir, $plugin_snapshot, $package_snapshot);') !== FALSE
	|| fail('Plugin upgrade must reload metadata from the new package before installing.');
strpos($upgrade_action, "plugin_check_dependency(\$dir, 'install', \$plugin_snapshot, \$package_snapshot);") !== FALSE
	|| fail('Plugin upgrade must re-check new package dependencies inside the rollback boundary.');
strpos($upgrade_action, 'plugin_require_state_write(plugin_install($dir), $dir, $plugin_snapshot, $package_snapshot);') !== FALSE
	|| fail('Plugin upgrade config-write failures must restore the previous package directory.');
strpos($upgrade_action, "plugin_run_lifecycle(\$dir, 'upgrade', \$plugin_snapshot, \$package_snapshot);") !== FALSE
	|| fail('Plugin upgrade lifecycle failures must restore the previous package directory.');
strpos($upgrade_action, 'plugin_package_snapshot_delete($package_snapshot);') !== FALSE
	|| fail('Plugin upgrade must delete package snapshots after success.');

$download = section_between($plugin_route, 'function plugin_download_unzip', 'function plugin_zip_validate_package');
strpos($download, 'function plugin_download_unzip($dir, $package_snapshot = NULL)') !== FALSE
	|| fail('plugin_download_unzip() must accept a package snapshot.');
strpos($download, 'plugin_package_snapshot($dir)') !== FALSE
	|| fail('plugin_download_unzip() must snapshot when called without an explicit snapshot.');
strpos($download, 'plugin_package_restore($package_snapshot);') !== FALSE
	|| fail('plugin_download_unzip() must restore the package directory on copy/finalization failure.');
strpos($download, 'plugin_package_snapshot_delete($package_snapshot);') !== FALSE
	|| fail('plugin_download_unzip() must clean self-owned snapshots after success.');

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
