<?php

$root = dirname(__DIR__);

function fail($message) {
	fwrite(STDERR, "FAIL: $message\n");
	exit(1);
}

function source_text($path) {
	$source = file_get_contents($path);
	$source === FALSE AND fail("Unable to read $path");
	return str_replace(array("\r\n", "\r"), "\n", $source);
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

function param($key, $default = '') {
	return isset($_REQUEST[$key]) ? $_REQUEST[$key] : $default;
}

function lang($key, $replace = array()) {
	return isset($replace['status']) ? $key.':'.$replace['status'] : $key;
}

$update_route = source_text($root.'/admin/route/update.php');

strpos($update_route, 'function update_lock_start()') !== FALSE
	|| fail('update_lock_start() helper is missing.');
strpos($update_route, "!xn_lock_start(update_lock_name(), 600)") !== FALSE
	|| fail('Online update writes must share an extended update_task lock.');
strpos($update_route, "return 'update_task';") !== FALSE
	|| fail('Online update lock must use the stable update_task name.');
$lock_start = section_between($update_route, 'function update_lock_start()', 'function update_lock_end()');
strpos($lock_start, "register_shutdown_function('update_lock_end')") !== FALSE
	|| fail('Online update must release update_task during shutdown after fatal exits.');

strpos($update_route, 'function update_message($code, $message)') !== FALSE
	|| fail('update_message() helper is missing.');
strpos($update_route, "update_lock_end();\n\tmessage(\$code, \$message);") !== FALSE
	|| fail('update_message() must release the update task lock before exiting.');

$download = section_between($update_route, "} elseif (\$action == 'download')", "} elseif (\$action == 'rollback')");
strpos($download, 'update_lock_start();') !== FALSE
	|| fail('Download/update action must acquire the update task lock.');
strpos($download, 'update_tag_valid($tag_name)') !== FALSE
	|| fail('Download/update action must validate release tags before building archive URLs.');
strpos($download, 'rawurlencode($tag_name)') !== FALSE
	|| fail('Download/update action must URL-encode release tags in archive URLs.');
substr_count($update_route, "'https://codeload.github.com/' . GITHUB_REPO . '/zip/refs/tags/'") === 2
	|| fail('Update check and download actions must use the direct GitHub codeload archive URL.');
strpos($update_route, "'https://github.com/' . GITHUB_REPO . '/archive/refs/tags/'") === FALSE
	|| fail('Online updates must not depend on the unstable github.com/archive redirect hop.');
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
strpos($update_route, "define('UPDATE_MAX_ZIP_BYTES'") !== FALSE
	|| fail('Online update must define a maximum update ZIP size.');
strpos($update_route, 'function update_http_get_body($url, $timeout, $headers, $max_redirects, $max_bytes = 0, &$error =') !== FALSE
	|| fail('Online update HTTP reads must use a shared bounded redirect helper.');
strpos($update_route, "'cURL is required for safe online updates'") !== FALSE
	|| fail('Online update must require cURL so resolved public IPs can be pinned.');
strpos($update_route, 'update_url_public_https_allowed($current, $resolved_ips, $error)') !== FALSE
	|| fail('Online update must resolve and validate every request hop before connecting.');
strpos($update_route, 'function update_curl_pin_resolved_ips($ch, $url, $resolved_ips, &$error =') !== FALSE
	|| fail('Online update cURL requests must pin already validated DNS results.');
strpos($update_route, 'CURLOPT_RESOLVE') !== FALSE
	|| fail('Online update cURL requests must use CURLOPT_RESOLVE for DNS rebinding resistance.');
strpos($update_route, "function update_resolve_public_ips(\$host, &\$ips = array(), &\$error = '')") !== FALSE
	|| fail('Online update must resolve hostnames before HTTP requests.');
strpos($update_route, 'dns_get_record($host, DNS_A | DNS_AAAA)') !== FALSE
	|| fail('Online update DNS validation must inspect A and AAAA records.');
strpos($update_route, 'function update_public_ip_allowed($ip)') !== FALSE
	|| fail('Online update must validate resolved public IP addresses.');
strpos($update_route, "stripos(\$ip, '::ffff:') === 0") !== FALSE
	|| fail('Online update must handle IPv4-mapped IPv6 addresses.');
strpos($update_route, 'FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE') !== FALSE
	|| fail('Online update must reject private and reserved resolved IP addresses.');
strpos($update_route, 'strlen($result[\'body\']) > $max_bytes') !== FALSE
	|| fail('Shared update HTTP helper must reject oversized response bodies.');
strpos($update_route, 'return update_http_get_body($url, $timeout, array(\'Accept: */*\'), 10, UPDATE_MAX_ZIP_BYTES, $error);') !== FALSE
	|| fail('Binary update downloads must pass the maximum ZIP size into the shared HTTP helper.');
strpos($update_route, 'CURLOPT_FOLLOWLOCATION, true') === FALSE
	|| fail('Online update must not rely on cURL automatic redirects.');
strpos($update_route, "'follow_location' => 1") === FALSE
	|| fail('Online update stream fallback must not automatically follow redirects.');
strpos($update_route, 'function update_redirect_url($location, $base_url)') !== FALSE
	|| fail('Online update redirects must be normalized by a dedicated helper.');
strpos($update_route, "function update_url_public_https_allowed(\$url, &\$resolved_ips = NULL, &\$error = '')") !== FALSE
	|| fail('Online update redirects must be constrained to public HTTPS URLs.');
strpos($update_route, "strtolower(\$parts['scheme']) !== 'https'") !== FALSE
	|| fail('Online update redirects must reject protocol downgrade to HTTP.');
strpos($update_route, "update_resolve_public_ips(\$parts['host'], \$ips, \$error)") !== FALSE
	|| fail('Online update redirects must reject private/local hosts after DNS resolution.');
strpos($download, 'update_github_latest_release($check_error)') !== FALSE
	|| fail('Online update must fetch release metadata directly from GitHub.');
strpos($download, 'update_github_latest_release($proxy)') === FALSE
	|| fail('Online update must not fetch trusted release metadata through an artifact proxy.');
strpos($download, "if (\$expected_sha256 === '')") !== FALSE
	|| fail('Online update must fail closed when release SHA256 metadata is missing.');
strpos($download, "lang('update_checksum_missing_blocked')") !== FALSE
	|| fail('Missing release SHA256 metadata must return a dedicated blocked-update error.');
strpos($update_route, "empty(\$conf['allow_unverified_update'])") === FALSE
	|| fail('Online update must not expose an unverified-update bypass.');
$release_lookup = section_between($update_route, 'function update_github_latest_release', 'function update_proxied_url');
strpos($release_lookup, 'update_proxied_url') === FALSE
	|| fail('Release metadata lookup must stay on the direct GitHub trust channel.');
$checksum_lookup = section_between($update_route, 'function update_release_expected_sha256', 'function update_checksum_asset_name');
strpos($checksum_lookup, 'update_proxied_url') === FALSE
	|| fail('Release checksum assets must stay on the direct GitHub trust channel.');
strpos($download, 'if (!$zip->extractTo($extract_dir))') !== FALSE
	|| fail('ZIP extraction failures must stop the update before source selection.');
strpos($download, 'rmdir_recusive($extract_dir);') !== FALSE
	|| fail('Update extraction must remove a stale root before recreating it.');
strpos($download, 'if (is_dir($extract_dir) || !update_mkdir_recursive($extract_dir))') !== FALSE
	|| fail('Update extraction must create a writable directory with explicit permissions and stop on failure.');
strpos($download, 'if (!update_mkdir_recursive($backup_dir))') !== FALSE
	|| fail('Update backups must create their directory with explicit permissions and stop on failure.');
strpos($download, 'xn_mkdir($extract_dir)') === FALSE && strpos($download, 'xn_mkdir($backup_dir)') === FALSE
	|| fail('Update directories must not use xn_mkdir() with its nullable mode default.');
$source = section_between($update_route, 'function update_find_source_dir', 'function update_zip_validate');
strpos($source, 'count($top) !== 1') !== FALSE
	|| fail('Update source selection must require exactly one top-level package directory.');
strpos($source, 'function update_source_dir_valid($dir)') !== FALSE
	|| fail('Update source selection must use a dedicated source root sentinel validator.');
strpos($source, "array('index.php', 'conf/conf.default.php')") !== FALSE
	|| fail('Update source validation must require core sentinel files.');
strpos($source, "array('admin', 'model', 'view', 'xiunophp')") !== FALSE
	|| fail('Update source validation must require core sentinel directories.');
strpos($source, "is_file(\$dir . 'index.php') || is_dir(\$dir . 'model')") === FALSE
	|| fail('Update source selection must not accept loose index.php-or-model matches.');
strpos($update_route, 'function update_tag_valid($tag)') !== FALSE
	|| fail('Online update must expose a release tag validator.');
strpos($update_route, "preg_match('/^v?\d+\.\d+\.\d+") !== FALSE
	|| fail('Release tag validation must constrain tags to version-shaped values.');
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
strpos($download, '$conf_default_relative = \'conf/conf.default.php\';') !== FALSE
	|| fail('Online updates must explicitly identify the canonical default configuration file.');
strpos($download, 'update_backup_file($conf_default_target, $backup_dir . $conf_default_relative, $backup_error)') !== FALSE
	|| fail('Online updates must back up the canonical default configuration before replacing it.');
strpos($download, '$added_files[] = $conf_default_relative') !== FALSE
	|| fail('A newly added canonical default configuration must be tracked for rollback.');
strpos($download, '@copy($conf_default_source, $conf_default_target)') !== FALSE
	|| fail('Online updates must replace the canonical default configuration from the verified release.');
strpos($download, '$hint = substr($zipdata') === FALSE
	|| fail('Invalid update responses must not be reflected into the admin page.');

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
strpos($update_route, 'function update_zip_entry_is_symlink($zip, $index)') !== FALSE
	|| fail('Update ZIP validation must detect symlink entries.');
strpos($update_route, 'update_zip_entry_is_symlink($zip, $i)') !== FALSE
	|| fail('Update ZIP validation must reject symlink entries before extraction.');
strpos($copy, 'is_link($item)') !== FALSE
	|| fail('update_copy_files() must reject symlinks before copying.');
strpos($copy, 'Unsupported file type') !== FALSE
	|| fail('update_copy_files() must reject unsupported filesystem entries.');
substr_count($update_route, '$items = update_directory_entries(') === 5
	|| fail('Copy, backup, added-file, restore, and count paths must share hidden-file enumeration.');
substr_count($update_route, '$name === basename(update_added_manifest(') === 2
	|| fail('Restore and backup counts must exclude the internal added-file manifest.');

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
strpos($conf_version, "'static_version' => '") !== FALSE
	|| fail('update_conf_version() must update the static asset cache version with the release version.');
strpos($conf_version, '$static_count < 1') !== FALSE
	|| fail('update_conf_version() must verify that static_version was replaced or added.');
strpos($conf_version, '=== strlen($s)') !== FALSE
	|| fail('update_conf_version() must detect partial writes.');

defined('DEBUG') || define('DEBUG', 1);
$_REQUEST[1] = 'noop';
ob_start();
include $root.'/admin/route/update.php';
ob_end_clean();

$hidden_root = sys_get_temp_dir().'/xiuno-update-hidden-'.bin2hex(random_bytes(6));
$hidden_source = $hidden_root.'/source/';
$hidden_target = $hidden_root.'/target/';
$hidden_backup = $hidden_root.'/backup/';
$hidden_ready = mkdir($hidden_source, 0777, TRUE) && mkdir($hidden_target, 0777, TRUE)
	&& mkdir($hidden_backup, 0777, TRUE)
	&& file_put_contents($hidden_source.'.managed', 'new') === 3
	&& file_put_contents($hidden_target.'.managed', 'old') === 3;
$hidden_error = '';
$hidden_backed_up = $hidden_ready ? update_backup_existing_files($hidden_source, $hidden_target, array(), $hidden_backup, $hidden_error) : FALSE;
$hidden_copied = $hidden_backed_up !== FALSE ? update_copy_files($hidden_source, $hidden_target, array(), $hidden_error) : FALSE;
$hidden_updated = $hidden_copied !== FALSE
	&& @file_get_contents($hidden_target.'.managed') === 'new'
	&& update_count_files($hidden_backup) === 1;
$hidden_restored = $hidden_updated ? update_restore_backup($hidden_backup, $hidden_target, $hidden_error) : FALSE;
$hidden_ok = $hidden_restored !== FALSE
	&& @file_get_contents($hidden_target.'.managed') === 'old';
@unlink($hidden_source.'.managed');
@unlink($hidden_target.'.managed');
@unlink($hidden_backup.'.managed');
@rmdir($hidden_source);
@rmdir($hidden_target);
@rmdir($hidden_backup);
@rmdir($hidden_root);
$hidden_ok || fail('Online update must preserve hidden files across update and rollback.');

$network_errors = array(
	'cURL #60: unable to get local issuer certificate'=>'update_network_ca',
	'cURL #35: TLS handshake failed'=>'update_network_tls',
	'cURL #6: Could not resolve host'=>'update_network_dns',
	'cURL #7: Failed to connect'=>'update_network_connect',
	'cURL #28: Operation timed out'=>'update_network_timeout',
	'HTTP 403'=>'update_network_http:403',
	'too many redirects'=>'update_network_redirect',
	'cURL is required for safe online updates'=>'update_network_curl',
	'URL is not an allowed public HTTPS URL'=>'update_network_policy',
);
foreach ($network_errors as $raw_error=>$expected_message) {
	update_http_error_message($raw_error, 'fallback') === $expected_message
		|| fail("Online update error classification failed for $expected_message.");
}
update_http_error_message('unknown internal detail', 'fallback') === 'fallback'
	|| fail('Unknown update errors must use the safe fallback message.');

echo "OK: update task safety checks passed\n";
