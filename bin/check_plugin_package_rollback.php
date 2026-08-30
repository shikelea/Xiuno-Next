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

$snapshot_source = section_between($plugin_route, 'function plugin_package_snapshot(', 'function plugin_package_restore(');
foreach(array('backup_manifest_version', 'backup_manifest_path_sha256', 'backup_manifest_sha256', 'backup_manifest_file_count') as $field) {
	strpos($snapshot_source, "'$field'") !== FALSE
		|| fail("Plugin package snapshots must bind $field before lifecycle code runs.");
}
strpos($snapshot_source, 'plugin_package_manifest_summary($dest_dir, $source_summary, $error)') !== FALSE
	|| fail('Plugin package snapshot creation must summarize the original package tree.');
strpos($snapshot_source, 'plugin_package_manifest_summary($snapshot[\'backup_dir\'], $backup_summary, $error)') !== FALSE
	|| fail('Plugin package snapshot creation must summarize the protected backup tree.');
strpos($snapshot_source, 'hash_equals($source_summary[\'sha256\'], $backup_summary[\'sha256\'])') !== FALSE
	|| fail('Plugin package snapshot creation must compare aggregate digests in constant time.');

$dependency = section_between($plugin_route, 'function plugin_check_dependency', 'function plugin_reload_local');
substr_count($dependency, 'plugin_lifecycle_restore_or_fail($dir, $snapshot, $package_snapshot);') === 3
	|| fail('Plugin dependency failures inside upgrade must use checked aggregate package and state restoration on every branch.');

$reload = section_between($plugin_route, 'function plugin_reload_local', 'function plugin_require_state_write');
strpos($reload, 'file_get_contents($conffile)') !== FALSE
	|| fail('Plugin metadata reload must read the replaced package conf.json from disk.');
strpos($reload, '$plugins[$dir] = plugin_read_by_dir($dir);') !== FALSE
	|| fail('Plugin metadata reload must normalize the refreshed plugin record.');
substr_count($reload, 'plugin_lifecycle_restore_or_fail($dir, $snapshot, $package_snapshot);') === 2
	|| fail('Every plugin metadata reload failure must use checked aggregate package and state restoration.');

$syntax_check = section_between($plugin_route, 'function plugin_check_php_syntax(', 'function plugin_check_exists');
strpos($syntax_check, 'plugin_lifecycle_restore_or_fail($dir, NULL, $package_snapshot);') !== FALSE
	|| fail('Plugin syntax-check failure must not ignore package restoration failure.');

$restore = section_between($plugin_route, 'function plugin_package_restore', 'function plugin_package_snapshot_delete');
strpos($restore, 'function plugin_package_restore($snapshot, $silent = FALSE, $log_silent_failure = TRUE)') !== FALSE
	|| fail('Plugin package restore must keep standalone silent logging by default while allowing aggregate callers to suppress it.');
strpos($restore, 'rmdir_recusive($dest_dir, 0);') === FALSE
	|| fail('Plugin package restore must not delete the live target before backup validation.');
strpos($restore, "plugin_copy_dir(\$backup_dir.'/', \$staging_dir.'/', \$error)") !== FALSE
	|| fail('Plugin package restore must copy the backup into same-parent staging first.');
strpos($restore, 'plugin_package_dirs_equal(') !== FALSE
	|| fail('Plugin package restore must validate staged contents before exchange.');
substr_count($restore, 'plugin_package_snapshot_manifest_verify(') >= 2
	|| fail('Plugin package restore must verify both the protected backup and staged copy against the original snapshot manifest.');
strpos($restore, 'plugin_package_restore_exchange(') !== FALSE
	|| fail('Plugin package restore must use a recoverable directory exchange.');
strpos($restore, 'plugin_package_restore_absent(') !== FALSE
	|| fail('Plugin package restore must isolate had_dest=false targets before removing them.');
strpos($restore, "if(\$log_silent_failure) plugin_lifecycle_log('plugin package rollback failed:") !== FALSE
	|| fail('Silent plugin package restore failures must be logged.');
strpos($restore, "plugin_message(-1, 'Plugin package rollback failed:") !== FALSE
	|| fail('Interactive plugin package restore failures must keep the existing message boundary.');
strpos($restore, "if(!plugin_clear_tmp_dir())") !== FALSE
	&& strpos($restore, 'Runtime cache invalidation failed after package restoration.') !== FALSE
	|| fail('Plugin package restore must fail honestly when compiled-cache invalidation fails after rollback.');
strpos($restore, 'if(!plugin_package_snapshot_delete($snapshot))') !== FALSE
	&& strpos($restore, 'Protected package snapshot cleanup failed after package restoration.') !== FALSE
	|| fail('Plugin package restore must not report success when protected snapshot cleanup fails.');
$manifest_verify = section_between($plugin_route, 'function plugin_package_snapshot_manifest_verify(', 'function plugin_package_dir_manifest(');
strpos($manifest_verify, 'Legacy plugin package snapshot has no valid integrity manifest; refusing rollback.') !== FALSE
	|| fail('Legacy had_dest=true snapshots must fail closed with an explicit diagnostic.');
substr_count($manifest_verify, 'hash_equals(') >= 2
	|| fail('Snapshot path and aggregate digest comparisons must use constant-time equality.');
strpos($manifest_verify, "\$summary['file_count'] !== \$snapshot['backup_manifest_file_count']") !== FALSE
	|| fail('Snapshot file-count comparison must preserve strict integer semantics.');

$copy = section_between($plugin_route, 'function plugin_copy_dir', 'function plugin_mkdir_recursive');
strpos($copy, 'plugin_dir_items($src)') !== FALSE
	|| fail('Plugin package copy must include dotfiles for complete package snapshots.');
strpos($plugin_route, 'function plugin_dir_items($dir)') !== FALSE
	|| fail('plugin_dir_items() helper is missing.');

function package_guard_remove_tree($path) {
	if(is_link($path) || !is_dir($path)) {
		if(file_exists($path) || is_link($path)) @unlink($path);
		return;
	}
	$items = array_merge(glob(rtrim($path, '/\\').'/*') ?: array(), glob(rtrim($path, '/\\').'/.??*') ?: array());
	foreach($items as $item) package_guard_remove_tree($item);
	@rmdir($path);
}

function package_guard_snapshot($dir, $dest, $backup, $had_dest, $restore_id, $bind_manifest = TRUE) {
	$snapshot = array(
		'dir'=>$dir,
		'dest_dir'=>$dest,
		'backup_dir'=>$backup,
		'had_dest'=>$had_dest,
		'restore_id'=>$restore_id,
	);
	if(!$had_dest) {
		$empty = plugin_package_manifest_digest(array());
		$snapshot['backup_manifest_version'] = 1;
		$snapshot['backup_manifest_path_sha256'] = hash('sha256', '');
		$snapshot['backup_manifest_sha256'] = $empty['sha256'];
		$snapshot['backup_manifest_file_count'] = $empty['file_count'];
	} elseif($bind_manifest && is_dir($backup)) {
		$error = '';
		$summary = array();
		$path_sha256 = plugin_package_manifest_path_sha256($backup, $error);
		$path_sha256 !== FALSE && plugin_package_manifest_summary($backup, $summary, $error)
			|| fail('Could not bind package restore fixture manifest: '.$error);
		$snapshot['backup_manifest_version'] = 1;
		$snapshot['backup_manifest_path_sha256'] = $path_sha256;
		$snapshot['backup_manifest_sha256'] = $summary['sha256'];
		$snapshot['backup_manifest_file_count'] = $summary['file_count'];
	}
	return $snapshot;
}

function package_guard_lock_windows_file($path) {
	$path_literal = str_replace("'", "''", str_replace('\\', '/', $path));
	$script = '$ErrorActionPreference="Stop"; $fixturePath=\''.$path_literal.'\'; $stream=[IO.File]::Open($fixturePath,[IO.FileMode]::Open,[IO.FileAccess]::ReadWrite,[IO.FileShare]::None); [Console]::Out.WriteLine("READY"); [Console]::Out.Flush(); [Console]::In.ReadLine() | Out-Null; $stream.Dispose();';
	$descriptors = array(
		0=>array('pipe', 'r'),
		1=>array('pipe', 'w'),
		2=>array('pipe', 'w'),
	);
	$pipes = array();
	$process = proc_open(array('powershell.exe', '-NoProfile', '-NonInteractive', '-Command', $script), $descriptors, $pipes, NULL, NULL, array('bypass_shell'=>TRUE));
	if(!is_resource($process)) fail('Could not start the Windows copy-failure fixture.');
	stream_set_timeout($pipes[1], 5);
	$ready = fgets($pipes[1]);
	if(trim((string)$ready) !== 'READY') {
		@fclose($pipes[0]);
		$error = stream_get_contents($pipes[2]);
		@fclose($pipes[1]);
		@fclose($pipes[2]);
		@proc_terminate($process);
		@proc_close($process);
		fail('Windows copy-failure fixture did not acquire its lock: '.$error);
	}
	return array($process, $pipes);
}

function package_guard_unlock_windows_file($lock) {
	list($process, $pipes) = $lock;
	@fwrite($pipes[0], "\n");
	@fclose($pipes[0]);
	stream_get_contents($pipes[1]);
	$error = stream_get_contents($pipes[2]);
	@fclose($pipes[1]);
	@fclose($pipes[2]);
	$status = proc_get_status($process);
	$close_exit = proc_close($process);
	$exit = !$status['running'] && $status['exitcode'] >= 0 ? $status['exitcode'] : $close_exit;
	$exit === 0 || fail('Windows copy-failure fixture did not exit cleanly: '.$error);
}

$system_tmp = realpath(sys_get_temp_dir());
$system_tmp !== FALSE || fail('System temporary directory is unavailable.');
$sandbox = rtrim(str_replace('\\', '/', $system_tmp), '/').'/xiuno_plugin_package_restore_'.str_replace('.', '', uniqid('', TRUE)).'/';
mkdir($sandbox.'app/plugin/', 0777, TRUE) || fail('Could not create isolated package restore app directory.');
mkdir($sandbox.'app/tmp/', 0777, TRUE) || fail('Could not create isolated package restore tmp directory.');
mkdir($sandbox.'app/log/', 0777, TRUE) || fail('Could not create isolated package restore log directory.');
$sandbox_real = realpath($sandbox);
$tmp_prefix = rtrim(str_replace('\\', '/', $system_tmp), '/').'/';
strpos(str_replace('\\', '/', (string)$sandbox_real).'/', $tmp_prefix) === 0
	|| fail('Package restore guard escaped the system temporary directory.');
register_shutdown_function(function() use($sandbox) { package_guard_remove_tree($sandbox); });

$app = $sandbox.'app/';
defined('DEBUG') || define('DEBUG', 0);
defined('APP_PATH') || define('APP_PATH', $app);
defined('ADMIN_PATH') || define('ADMIN_PATH', $root.'/admin/');
defined('XIUNOPHP_PATH') || define('XIUNOPHP_PATH', $root.'/xiunophp/');
$_SERVER['time'] = time();
$_SERVER['ajax'] = 1;
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/admin/?plugin-__package_restore_guard.htm';
$_SERVER['lang'] = array();
$_SERVER['conf'] = array(
	'tmp_path'=>$app.'tmp/',
	'log_path'=>$app.'log/',
	'url_rewrite_on'=>0,
	'version'=>'4.5.1',
);
$_REQUEST = array(1=>'__package_restore_guard');
$time = $_SERVER['time'];
$conf = $_SERVER['conf'];
$method = 'GET';
$header = array();
$plugins = array();
$official_plugins = array();
$gid = 1;
include $root.'/xiunophp/array.func.php';
include $root.'/xiunophp/misc.func.php';
include $root.'/xiunophp/logger.func.php';
include $root.'/model/misc.func.php';
include $root.'/model/plugin.func.php';
ob_start();
include $root.'/admin/route/plugin.php';
ob_end_clean();

// Missing backup: silent restore must fail and log without touching the existing destination.
$dest = $app.'plugin/missing_backup';
mkdir($dest, 0777, TRUE);
file_put_contents($dest.'/keep.txt', 'current-package');
$snapshot = package_guard_snapshot('missing_backup', $dest, $app.'tmp/plugin_backup_missing/', TRUE, 'missing_backup_001');
plugin_package_restore($snapshot, TRUE) === FALSE
	|| fail('Missing backup must fail in silent mode.');
is_file($dest.'/keep.txt') && file_get_contents($dest.'/keep.txt') === 'current-package'
	|| fail('Missing backup failure deleted or changed the current destination.');
$logfile = $app.'log/'.date('Ym', $time).'/plugin_lifecycle_error.php';
is_file($logfile) && strpos(file_get_contents($logfile), 'plugin package rollback failed: dir=missing_backup') !== FALSE
	|| fail('Silent missing-backup failure was not logged.');

// Aggregate quiet mode must preserve the same failure/result semantics without writing an
// individual package log; the outer lifecycle owner emits the single combined diagnostic.
$quiet_dest = $app.'plugin/quiet_missing_backup';
mkdir($quiet_dest, 0777, TRUE);
file_put_contents($quiet_dest.'/keep.txt', 'current-package');
$quiet_snapshot = package_guard_snapshot('quiet_missing_backup', $quiet_dest, $app.'tmp/plugin_backup_quiet_missing/', TRUE, 'quiet_missing_001');
plugin_package_restore($quiet_snapshot, TRUE, FALSE) === FALSE
	|| fail('Missing backup must fail in aggregate quiet mode.');
is_file($quiet_dest.'/keep.txt') && file_get_contents($quiet_dest.'/keep.txt') === 'current-package'
	|| fail('Aggregate quiet missing-backup failure deleted or changed the current destination.');
$quiet_log = is_file($logfile) ? file_get_contents($logfile) : '';
strpos($quiet_log, 'plugin package rollback failed: dir=quiet_missing_backup') === FALSE
	|| fail('Aggregate quiet package restore must not duplicate the outer lifecycle diagnostic.');

// Existing-package snapshots from the pre-manifest format cannot prove that their backup still
// contains the original bytes. They must fail closed and retain both current and backup trees.
$legacy_dest = $app.'plugin/legacy_snapshot';
$legacy_backup = $app.'tmp/plugin_backup_legacy_snapshot';
mkdir($legacy_dest, 0777, TRUE);
mkdir($legacy_backup, 0777, TRUE);
file_put_contents($legacy_dest.'/keep.txt', 'current-package');
file_put_contents($legacy_backup.'/keep.txt', 'legacy-backup');
$legacy_snapshot = package_guard_snapshot('legacy_snapshot', $legacy_dest, $legacy_backup, TRUE, 'legacy_snapshot_001', FALSE);
plugin_package_restore($legacy_snapshot, TRUE) === FALSE
	|| fail('A had_dest=true legacy snapshot without an integrity manifest must fail closed.');
file_get_contents($legacy_dest.'/keep.txt') === 'current-package' && file_get_contents($legacy_backup.'/keep.txt') === 'legacy-backup'
	|| fail('Legacy snapshot rejection changed the current package or unverified backup.');
$legacy_log = is_file($logfile) ? file_get_contents($logfile) : '';
strpos($legacy_log, 'Legacy plugin package snapshot has no valid integrity manifest; refusing rollback.') !== FALSE
	|| fail('Legacy snapshot rejection must leave an explicit compatibility diagnostic.');

// Exercise the real snapshot creator, then mutate the protected backup. A backup-to-staging-only
// comparison would accept this; binding the original aggregate digest must reject it before staging.
$integrity_dest = $app.'plugin/snapshot_integrity/';
mkdir($integrity_dest.'nested/', 0777, TRUE);
file_put_contents($integrity_dest.'nested/original.txt', 'ORIGINAL-SNAPSHOT');
$integrity_snapshot = plugin_package_snapshot('snapshot_integrity');
$integrity_snapshot['backup_manifest_version'] === 1
	|| fail('Real package snapshot did not record the manifest version.');
$integrity_snapshot['backup_manifest_file_count'] === 1
	|| fail('Real package snapshot did not record the exact original file count.');
preg_match('/^[a-f0-9]{64}$/D', $integrity_snapshot['backup_manifest_path_sha256'])
	&& preg_match('/^[a-f0-9]{64}$/D', $integrity_snapshot['backup_manifest_sha256'])
	|| fail('Real package snapshot did not bind path and aggregate SHA-256 values.');
file_put_contents(rtrim($integrity_snapshot['backup_dir'], '/').'/nested/original.txt', 'TAMPERED-BACKUP');
file_put_contents($integrity_dest.'nested/original.txt', 'CURRENT-PACKAGE');
plugin_package_restore($integrity_snapshot, TRUE) === FALSE
	|| fail('A mutated protected backup must fail original-manifest verification.');
file_get_contents($integrity_dest.'nested/original.txt') === 'CURRENT-PACKAGE'
	|| fail('Mutated backup rejection changed the current package.');
$integrity_path_error = '';
plugin_package_restore_paths($integrity_snapshot, rtrim($integrity_dest, '/'), $integrity_staging, $integrity_previous, $integrity_path_error)
	|| fail('Could not resolve integrity failure work paths: '.$integrity_path_error);
!plugin_package_path_exists($integrity_staging) && !plugin_package_path_exists($integrity_previous)
	|| fail('Original-manifest failure created staging or recovery artifacts.');

// The digest is also bound to the protected backup path. Identical bytes copied elsewhere cannot
// be substituted by changing only snapshot['backup_dir'].
$path_dest = $app.'plugin/path_binding';
$path_backup = $app.'tmp/plugin_backup_path_binding';
$path_alias = $app.'tmp/plugin_backup_path_alias';
mkdir($path_dest, 0777, TRUE);
mkdir($path_backup, 0777, TRUE);
mkdir($path_alias, 0777, TRUE);
file_put_contents($path_dest.'/keep.txt', 'current-package');
file_put_contents($path_backup.'/keep.txt', 'original-backup');
file_put_contents($path_alias.'/keep.txt', 'original-backup');
$path_snapshot = package_guard_snapshot('path_binding', $path_dest, $path_backup, TRUE, 'path_binding_001');
$path_snapshot['backup_dir'] = $path_alias;
plugin_package_restore($path_snapshot, TRUE) === FALSE
	|| fail('Changing the protected backup path must fail even when package bytes are identical.');
file_get_contents($path_dest.'/keep.txt') === 'current-package'
	|| fail('Backup path substitution changed the current package.');

// An unreadable/unsupported backup entry must fail manifest verification before exchange. Bind a
// valid original first, then replace/lock the entry to model corruption after snapshot creation.
$dest = $app.'plugin/copy_failure';
$backup = $app.'tmp/plugin_backup_copy_failure';
mkdir($dest, 0777, TRUE);
mkdir($backup, 0777, TRUE);
file_put_contents($dest.'/keep.txt', 'current-package');
$copy_fixture = $backup.'/cannot-copy';
file_put_contents($copy_fixture, 'snapshot-source');
$snapshot = package_guard_snapshot('copy_failure', $dest, $backup, TRUE, 'copy_failure_001');
$windows_lock = NULL;
if(PHP_OS_FAMILY === 'Windows') {
	$windows_lock = package_guard_lock_windows_file($copy_fixture);
} elseif(function_exists('posix_mkfifo')) {
	unlink($copy_fixture);
	posix_mkfifo($copy_fixture, 0600) || fail('Could not create the FIFO copy-failure fixture.');
} else {
	unlink($copy_fixture);
	$out = array();
	$exit = 0;
	exec('mkfifo '.escapeshellarg($copy_fixture).' 2>&1', $out, $exit);
	$exit === 0 || fail('Could not create the copy-failure fixture: '.implode("\n", $out));
}
$copy_result = plugin_package_restore($snapshot, TRUE);
if($windows_lock !== NULL) package_guard_unlock_windows_file($windows_lock);
$copy_result === FALSE || fail('Uncopyable backup must fail before exchange.');
is_file($dest.'/keep.txt') && file_get_contents($dest.'/keep.txt') === 'current-package'
	|| fail('Copy failure deleted or changed the current destination.');
$path_error = '';
plugin_package_restore_paths($snapshot, $dest, $staging, $previous, $path_error)
	|| fail('Could not resolve copy-failure restore paths: '.$path_error);
!plugin_package_path_exists($staging)
	|| fail('Copy failure left a partial staging directory behind.');
is_dir($backup) || fail('Copy failure must retain the backup for diagnosis or retry.');

// Exchange failure: a pre-existing recovery path must fail closed after staging validation.
$dest = $app.'plugin/exchange_failure';
$backup = $app.'tmp/plugin_backup_exchange_failure';
mkdir($dest, 0777, TRUE);
mkdir($backup.'/nested', 0777, TRUE);
file_put_contents($dest.'/keep.txt', 'current-package');
file_put_contents($backup.'/nested/original.txt', 'backup-package');
$snapshot = package_guard_snapshot('exchange_failure', $dest, $backup, TRUE, 'exchange_failure_001');
$path_error = '';
plugin_package_restore_paths($snapshot, $dest, $staging, $previous, $path_error)
	|| fail('Could not resolve exchange-failure restore paths: '.$path_error);
mkdir($previous, 0777, TRUE);
file_put_contents($previous.'/collision.txt', 'do-not-touch');
plugin_package_restore($snapshot, TRUE) === FALSE
	|| fail('Recovery-path collision must fail the package exchange.');
is_file($dest.'/keep.txt') && file_get_contents($dest.'/keep.txt') === 'current-package'
	|| fail('Exchange failure deleted or changed the current destination.');
is_file($previous.'/collision.txt') && file_get_contents($previous.'/collision.txt') === 'do-not-touch'
	|| fail('Exchange failure changed the pre-existing recovery path.');
!plugin_package_path_exists($staging)
	|| fail('Exchange failure left the verified staging directory behind.');
is_dir($backup) || fail('Exchange failure must retain the backup for diagnosis or retry.');

// Activation rename failure: moving a staging directory nested under dest makes it disappear with
// the first rename, deterministically exercising the reverse rename that restores the old dest.
$dest = $app.'plugin/exchange_activation_failure';
$staging = $dest.'/staging';
$previous = $app.'plugin/.exchange_activation_failure_previous';
mkdir($staging, 0777, TRUE);
file_put_contents($dest.'/keep.txt', 'current-package');
file_put_contents($staging.'/candidate.txt', 'backup-package');
$exchange_error = '';
plugin_package_restore_exchange($dest, $staging, $previous, $exchange_error) === FALSE
	|| fail('Missing staged source after the first rename must fail activation.');
is_file($dest.'/keep.txt') && file_get_contents($dest.'/keep.txt') === 'current-package'
	|| fail('Activation rename failure did not restore the original destination.');
is_file($dest.'/staging/candidate.txt') && !plugin_package_path_exists($previous)
	|| fail('Activation rename failure did not reverse the directory exchange completely.');
strpos($exchange_error, 'previous destination restored') !== FALSE
	|| fail('Activation rename failure did not report its successful reverse exchange.');

// had_dest=false follows the same fail-closed rule and removes a new target as one isolated unit on success.
$dest = $app.'plugin/new_package_failure';
mkdir($dest, 0777, TRUE);
file_put_contents($dest.'/keep.txt', 'new-package');
$snapshot = package_guard_snapshot('new_package_failure', $dest, '', FALSE, 'new_package_failure_001');
$path_error = '';
plugin_package_restore_paths($snapshot, $dest, $staging, $previous, $path_error)
	|| fail('Could not resolve had_dest=false failure paths: '.$path_error);
mkdir($previous, 0777, TRUE);
file_put_contents($previous.'/collision.txt', 'do-not-touch');
plugin_package_restore($snapshot, TRUE) === FALSE
	|| fail('had_dest=false recovery-path collision must fail explicitly.');
is_file($dest.'/keep.txt') && file_get_contents($dest.'/keep.txt') === 'new-package'
	|| fail('had_dest=false failure partially deleted the new destination.');

$dest = $app.'plugin/new_package_success';
mkdir($dest.'/nested', 0777, TRUE);
file_put_contents($dest.'/nested/new.txt', 'new-package');
$snapshot = package_guard_snapshot('new_package_success', $dest, '', FALSE, 'new_package_success_001');
plugin_package_restore($snapshot, TRUE) === TRUE
	|| fail('had_dest=false restore should remove the new package safely.');
!plugin_package_path_exists($dest)
	|| fail('had_dest=false successful restore left the new destination in place.');
$path_error = '';
plugin_package_restore_paths($snapshot, $dest, $staging, $previous, $path_error)
	|| fail('Could not resolve had_dest=false success paths: '.$path_error);
!plugin_package_path_exists($previous)
	|| fail('had_dest=false successful restore left a recovery directory behind.');

// Acquire the lifecycle visibility writer while tmp is valid, then invalidate only tmp_path. The
// directory exchange must complete, but cache invalidation must make the restore return FALSE while
// retaining the protected snapshot. Restoring tmp_path must allow the exact snapshot to be retried.
$dest = $app.'plugin/cache_failure_retry';
$backup = $app.'tmp/plugin_backup_cache_failure_retry';
mkdir($dest.'/current', 0777, TRUE);
mkdir($backup.'/original', 0777, TRUE);
file_put_contents($dest.'/current/new.txt', 'current-package');
file_put_contents($backup.'/original/keep.txt', 'backup-package');
$snapshot = package_guard_snapshot('cache_failure_retry', $dest, $backup, TRUE, 'cache_failure_retry_001');
$valid_tmp_path = $conf['tmp_path'];
plugin_state_visibility_write_lock_start()
	|| fail('Could not acquire the valid plugin-state writer before package cache failure injection.');
$conf['tmp_path'] = $app.'missing-runtime-cache/';
$_SERVER['conf'] = $conf;
!is_dir($conf['tmp_path'])
	|| fail('Package cache failure fixture unexpectedly exists.');
plugin_package_restore($snapshot, TRUE) === FALSE
	|| fail('Package restore reported success after the directory exchange when cache invalidation failed.');
is_file($dest.'/original/keep.txt') && file_get_contents($dest.'/original/keep.txt') === 'backup-package'
	|| fail('Package cache failure did not leave the restored target directory active.');
!plugin_package_path_exists($dest.'/current')
	|| fail('Package cache failure retained bytes from the displaced target.');
is_dir($backup) && is_file($backup.'/original/keep.txt')
	|| fail('Package cache failure deleted the protected snapshot required for retry.');

$conf['tmp_path'] = $valid_tmp_path;
$_SERVER['conf'] = $conf;
plugin_package_restore($snapshot, TRUE) === TRUE
	|| fail('Package restore could not retry the retained snapshot after tmp_path recovered.');
is_file($dest.'/original/keep.txt') && file_get_contents($dest.'/original/keep.txt') === 'backup-package'
	|| fail('Retried package restore changed the verified restored target.');
!plugin_package_path_exists($backup)
	|| fail('Successful package restore retry did not delete its completed protected snapshot.');
plugin_state_visibility_write_lock_end()
	|| fail('Could not release the package cache failure fixture writer lock.');

// Successful restore: nested files and dotfiles must match the verified backup, with no exchange artifacts.
$dest = $app.'plugin/success';
$backup = $app.'tmp/plugin_backup_success';
mkdir($dest.'/obsolete', 0777, TRUE);
mkdir($backup.'/nested', 0777, TRUE);
file_put_contents($dest.'/obsolete/current.txt', 'current-package');
file_put_contents($backup.'/nested/original.txt', 'backup-package');
file_put_contents($backup.'/.hidden', 'hidden-backup');
$snapshot = package_guard_snapshot('success', $dest, $backup, TRUE, 'restore_success_001');
plugin_package_restore($snapshot, TRUE) === TRUE
	|| fail('Valid backup should restore successfully.');
is_file($dest.'/nested/original.txt') && file_get_contents($dest.'/nested/original.txt') === 'backup-package'
	|| fail('Successful restore did not activate the verified backup contents.');
is_file($dest.'/.hidden') && file_get_contents($dest.'/.hidden') === 'hidden-backup'
	|| fail('Successful restore did not preserve backup dotfiles.');
!plugin_package_path_exists($dest.'/obsolete')
	|| fail('Successful restore retained files from the displaced package.');
!plugin_package_path_exists($backup)
	|| fail('Successful restore did not delete its completed backup snapshot.');
$path_error = '';
plugin_package_restore_paths($snapshot, $dest, $staging, $previous, $path_error)
	|| fail('Could not resolve successful restore paths: '.$path_error);
!plugin_package_path_exists($staging) && !plugin_package_path_exists($previous)
	|| fail('Successful restore left staging or recovery directories behind.');

echo "OK: plugin package rollback checks passed\n";
