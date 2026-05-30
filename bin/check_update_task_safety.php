<?php

$root = dirname(__DIR__);
$update_route = file_get_contents($root.'/admin/route/update.php');

function fail($message) {
	fwrite(STDERR, "FAIL: $message\n");
	exit(1);
}

function section_between($source, $start, $end) {
	$start_pos = strpos($source, $start);
	if ($start_pos === FALSE) fail("Missing section start: $start");
	$end_pos = strpos($source, $end, $start_pos + strlen($start));
	if ($end_pos === FALSE) fail("Missing section end after $start: $end");
	return substr($source, $start_pos, $end_pos - $start_pos);
}

function section_after($source, $needle) {
	$pos = strpos($source, $needle);
	if ($pos === FALSE) fail("Missing section marker: $needle");
	return substr($source, $pos + strlen($needle));
}

strpos($update_route, 'function update_lock_start()') !== FALSE
	|| fail('update_lock_start() helper is missing.');
strpos($update_route, "!xn_lock_start(update_lock_name(), 600)") !== FALSE
	|| fail('Online update writes must share an extended update_task lock.');
strpos($update_route, "return 'update_task';") !== FALSE
	|| fail('Online update lock must use the stable update_task name.');

strpos($update_route, 'function update_message($code, $message)') !== FALSE
	|| fail('update_message() helper is missing.');
strpos($update_route, "update_lock_end();\n\tmessage(\$code, \$message);") !== FALSE
	|| fail('update_message() must release the update task lock before exiting.');

$download = section_between($update_route, "} elseif (\$action == 'download')", "} elseif (\$action == 'rollback')");
strpos($download, 'update_lock_start();') !== FALSE
	|| fail('Download/update action must acquire the update task lock.');
$download_locked = section_after($download, 'update_lock_start();');
strpos($download_locked, "\tmessage(") === FALSE
	|| fail('Download/update locked paths must call update_message(), not message().');
strpos($download_locked, 'AND message(') === FALSE
	|| fail('Download/update locked guards must call update_message(), not message().');

$rollback = section_between($update_route, "} elseif (\$action == 'rollback')", "\n}\n\n//");
strpos($rollback, 'update_lock_start();') !== FALSE
	|| fail('Rollback action must acquire the update task lock.');
$rollback_locked = section_after($rollback, 'update_lock_start();');
strpos($rollback_locked, "\tmessage(") === FALSE
	|| fail('Rollback locked paths must call update_message(), not message().');
strpos($rollback_locked, 'AND message(') === FALSE
	|| fail('Rollback locked guards must call update_message(), not message().');
strpos($rollback_locked, "trim(_POST('backup', ''))") !== FALSE
	|| fail('Rollback backup selection must read from POST only.');
strpos($rollback_locked, "param('backup', '', 'POST')") === FALSE
	|| fail('Rollback must not use param(..., "POST"); param() does not select the request method.');

strpos($download, 'file_put_contents($zipfile, $zipdata) !== strlen($zipdata)') !== FALSE
	|| fail('Downloaded ZIP writes must be checked for short writes.');
strpos($download, 'if (!$zip->extractTo($extract_dir))') !== FALSE
	|| fail('ZIP extraction failures must stop the update before source selection.');
strpos($download, '$copy_error =') !== FALSE
	|| fail('Update copy errors must be captured.');
strpos($download, 'if ($result === FALSE)') !== FALSE
	|| fail('Update copy failures must stop the update.');
strpos($download, 'if (!update_conf_version($latest_version))') !== FALSE
	|| fail('conf.php version write failures must stop the update.');
strpos($download, 'update_added_files($source_dir, $app_root, $protected)') !== FALSE
	|| fail('Update must record files added by the replacement package before copying.');
strpos($download, 'update_write_added_files($backup_dir, $added_files, $added_error)') !== FALSE
	|| fail('Update must persist the added-file manifest in the backup directory.');

$copy_failure = section_between($download, 'if ($result === FALSE)', '$result[\'backed_up\']');
strpos($copy_failure, 'update_restore_backup_with_added_cleanup($backup_dir, $app_root, $restore_error)') !== FALSE
	|| fail('Update copy failures must restore the backup and remove newly added files before reporting failure.');
$version_failure = section_between($download, 'if (!update_conf_version($latest_version))', '// 清理临时文件');
strpos($version_failure, 'update_restore_backup_with_added_cleanup($backup_dir, $app_root, $restore_error)') !== FALSE
	|| fail('conf.php version write failures must restore the backup and remove newly added files before reporting failure.');

strpos($rollback_locked, 'update_restore_backup_with_added_cleanup($backup_dir, APP_PATH, $restore_error)') !== FALSE
	|| fail('Manual rollback must restore the backup and remove files that were added by the update.');

strpos($update_route, 'function update_copy_files($src, $dst, $protected = array(), &$error =') !== FALSE
	|| fail('update_copy_files() must expose a failure error string.');
$copy = section_between($update_route, 'function update_copy_files', 'function update_backup_existing_files');
strpos($copy, 'return FALSE;') !== FALSE
	|| fail('update_copy_files() must return FALSE on write failures.');

$restore = section_between($update_route, 'function update_restore_backup', 'function update_count_files');
strpos($restore, 'Cannot create restore directory') !== FALSE
	|| fail('Rollback restore must fail when target directories cannot be created.');

strpos($update_route, 'function update_added_files($src, $dst, $protected = array(), $relative =') !== FALSE
	|| fail('Added-file discovery helper is missing.');
strpos($update_route, 'function update_write_added_files($backup_dir, $added_files, &$error =') !== FALSE
	|| fail('Added-file manifest writer is missing.');
strpos($update_route, 'function update_restore_backup_with_added_cleanup($backup_dir, $dst_root, &$error =') !== FALSE
	|| fail('Combined restore and added-file cleanup helper is missing.');
strpos($update_route, 'function update_remove_added_files($backup_dir, $dst_root, &$error =') !== FALSE
	|| fail('Added-file cleanup helper is missing.');
strpos($update_route, 'function update_remove_empty_parent_dirs($dir, $root)') !== FALSE
	|| fail('Added-file cleanup must remove empty parent directories safely.');
$added_cleanup = section_between($update_route, 'function update_remove_added_files', 'function update_remove_empty_parent_dirs');
strpos($added_cleanup, "preg_match('#^[A-Za-z]:#', \$rel)") !== FALSE
	|| fail('Added-file cleanup must reject drive-qualified paths.');
strpos($added_cleanup, "preg_match('#(^|/)\.\.(/|$)#', \$rel)") !== FALSE
	|| fail('Added-file cleanup must reject parent-directory traversal.');
strpos($added_cleanup, '@unlink($target)') !== FALSE
	|| fail('Added-file cleanup must remove tracked added files.');
strpos($added_cleanup, 'update_remove_empty_parent_dirs(dirname($target), $dst_root)') !== FALSE
	|| fail('Added-file cleanup must remove empty directories left by added files.');

$conf_version = section_between($update_route, 'function update_conf_version', 'function update_conf_setting');
strpos($conf_version, '$count = 0;') !== FALSE
	|| fail('update_conf_version() must verify that the version key was replaced.');
strpos($conf_version, '=== strlen($s)') !== FALSE
	|| fail('update_conf_version() must detect partial writes.');

echo "OK: update task safety checks passed\n";
