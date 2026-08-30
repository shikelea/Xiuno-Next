<?php

function plugin_safe_mode_guard_fail($message) {
	fwrite(STDERR, "FAIL: $message\n");
	exit(1);
}

function plugin_safe_mode_guard_assert($condition, $message) {
	if(!$condition) plugin_safe_mode_guard_fail($message);
}

function plugin_safe_mode_guard_remove_tree($path) {
	if(is_link($path) || is_file($path)) return @unlink($path);
	if(!is_dir($path)) return TRUE;
	$entries = scandir($path);
	if($entries === FALSE) return FALSE;
	$ok = TRUE;
	foreach($entries as $entry) {
		if($entry === '.' || $entry === '..') continue;
		if(!plugin_safe_mode_guard_remove_tree($path.DIRECTORY_SEPARATOR.$entry)) $ok = FALSE;
	}
	return @rmdir($path) && $ok;
}

function plugin_safe_mode_guard_mkdir($directory) {
	if(is_dir($directory)) return;
	if(!mkdir($directory, 0777, TRUE) && !is_dir($directory)) plugin_safe_mode_guard_fail('Unable to create fixture directory: '.$directory);
}

function plugin_safe_mode_guard_write($path, $contents) {
	$length = strlen($contents);
	file_put_contents($path, $contents) === $length || plugin_safe_mode_guard_fail('Unable to write fixture file: '.$path);
}

function plugin_safe_mode_guard_run($command, &$output, $cwd = NULL) {
	$output = '';
	$descriptor = array(
		0=>array('pipe', 'r'),
		1=>array('pipe', 'w'),
		2=>array('redirect', 1),
	);
	$pipes = array();
	$process = @proc_open($command, $descriptor, $pipes, $cwd, NULL, array('bypass_shell'=>TRUE));
	if(!is_resource($process)) return 127;
	fclose($pipes[0]);
	$output = stream_get_contents($pipes[1]);
	fclose($pipes[1]);
	return proc_close($process);
}

function plugin_safe_mode_guard_run_php($script, $arguments, &$output) {
	$command = array_merge(array(PHP_BINARY, $script), $arguments);
	return plugin_safe_mode_guard_run($command, $output, dirname($script));
}

function plugin_safe_mode_guard_available_port() {
	$errno = 0;
	$errstr = '';
	$socket = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
	if(!is_resource($socket)) plugin_safe_mode_guard_fail('Unable to allocate a local HTTP fixture port: '.$errstr);
	$name = stream_socket_get_name($socket, FALSE);
	fclose($socket);
	$separator = strrpos($name, ':');
	$port = $separator === FALSE ? 0 : intval(substr($name, $separator + 1));
	$port > 0 || plugin_safe_mode_guard_fail('Unable to parse the local HTTP fixture port.');
	return $port;
}

function plugin_safe_mode_guard_start_server($document_root, &$pipes, &$port) {
	$port = plugin_safe_mode_guard_available_port();
	$descriptor = array(
		0=>array('pipe', 'r'),
		1=>array('pipe', 'w'),
		2=>array('redirect', 1),
	);
	$pipes = array();
	$process = @proc_open(
		array(PHP_BINARY, '-S', '127.0.0.1:'.$port, '-t', $document_root),
		$descriptor,
		$pipes,
		$document_root,
		NULL,
		array('bypass_shell'=>TRUE)
	);
	if(!is_resource($process)) plugin_safe_mode_guard_fail('Unable to start the local PHP server fixture.');
	fclose($pipes[0]);
	stream_set_blocking($pipes[1], FALSE);
	for($attempt = 0; $attempt < 100; $attempt++) {
		$socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
		if(is_resource($socket)) {
			fclose($socket);
			return $process;
		}
		$status = proc_get_status($process);
		if(!$status['running']) break;
		usleep(50000);
	}
	$diagnostic = stream_get_contents($pipes[1]);
	@fclose($pipes[1]);
	@proc_terminate($process);
	@proc_close($process);
	plugin_safe_mode_guard_fail('Local PHP server fixture did not become ready: '.$diagnostic);
}

function plugin_safe_mode_guard_stop_server($process, $pipes) {
	if(is_resource($process)) {
		@proc_terminate($process);
		for($attempt = 0; $attempt < 40; $attempt++) {
			$status = proc_get_status($process);
			if(!$status['running']) break;
			usleep(25000);
		}
	}
	foreach($pipes as $pipe) if(is_resource($pipe)) @fclose($pipe);
	if(is_resource($process)) @proc_close($process);
}

function plugin_safe_mode_guard_http_get($url, &$status_code) {
	$status_code = 0;
	$context = stream_context_create(array('http'=>array('ignore_errors'=>TRUE, 'timeout'=>5)));
	$handle = @fopen($url, 'rb', FALSE, $context);
	if(!is_resource($handle)) return '';
	$metadata = stream_get_meta_data($handle);
	$headers = isset($metadata['wrapper_data']) && is_array($metadata['wrapper_data']) ? $metadata['wrapper_data'] : array();
	$body = stream_get_contents($handle);
	fclose($handle);
	if(!empty($headers) && preg_match('/\s(\d{3})(?:\s|$)/', $headers[0], $match)) $status_code = intval($match[1]);
	return is_string($body) ? $body : '';
}

function plugin_safe_mode_guard_type_error_child($fixture, $root, $app, $conf, $source_file, $request_id, &$output) {
	$child = $fixture.'/type_error_'.substr(hash('sha256', $source_file.microtime(TRUE)), 0, 12).'.php';
	$script = "<?php\n"
		."define('DEBUG', 0);\n"
		."define('APP_PATH', ".var_export($app, TRUE).");\n"
		."\$conf = ".var_export($conf, TRUE).";\n"
		."\$GLOBALS['conf'] = \$conf; \$_SERVER['conf'] = \$conf;\n"
		."\$_SERVER['request_id'] = ".var_export($request_id, TRUE).";\n"
		."function xn_log(\$message, \$channel = 'error') { return TRUE; }\n"
		."require ".var_export($root.'/model/plugin_safe_mode.func.php', TRUE).";\n"
		."require ".var_export($root.'/xiunophp/php8_compat.php', TRUE).";\n"
		."require ".var_export($source_file, TRUE).";\n";
	plugin_safe_mode_guard_write($child, $script);
	return plugin_safe_mode_guard_run_php($child, array(), $output);
}

$root = realpath(__DIR__.'/..');
$root !== FALSE || plugin_safe_mode_guard_fail('Unable to locate project root.');
require $root.'/model/plugin_safe_mode.func.php';
$skips = array();

$fixture = rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/').'/xiuno_plugin_safe_mode_'.getmypid().'_'.str_replace('.', '', uniqid('', TRUE));
$app = $fixture.'/app/';
$external = $fixture.'/external/';
foreach(array(
	$app.'tmp/', $app.'log/', $app.'model/', $app.'plugin/example/',
	$external.'tmp/', $external.'log/'
) as $directory) plugin_safe_mode_guard_mkdir($directory);
register_shutdown_function(function() use ($fixture) { plugin_safe_mode_guard_remove_tree($fixture); });

$conf = array('tmp_path'=>$external.'tmp/', 'log_path'=>$external.'log/');
$paths = plugin_safe_mode_paths($conf, $app);
plugin_safe_mode_guard_assert($paths['marker_write_paths'][0] === $external.'tmp/safe_mode', 'Configured external tmp_path must be the primary marker location.');
plugin_safe_mode_guard_assert($paths['lock_paths'][0] === $external.'tmp/safe_mode.lock', 'Safe-mode operations must use a stable lock adjacent to the configured marker.');
plugin_safe_mode_guard_assert($paths['log_write_paths'][0] === $external.'log/safe_mode.php', 'Configured external log_path must use a PHP-protected primary log location.');
plugin_safe_mode_guard_assert($paths['log_backup_paths'][0] === $external.'log/safe_mode.previous.php', 'Rotated diagnostics must retain a PHP-protected filename.');
plugin_safe_mode_guard_assert(in_array($external.'log/safe_mode.log', $paths['log_paths'], TRUE), 'Legacy plaintext safe-mode logs must remain readable without receiving new writes.');
plugin_safe_mode_guard_assert(in_array($app.'tmp/safe_mode.php', $paths['marker_paths'], TRUE), 'Legacy APP_PATH safe_mode.php marker must remain detectable.');

$status = plugin_safe_mode_status($conf, $app);
plugin_safe_mode_guard_assert(!$status['active'], 'Fresh fixture must not start in safe mode.');
plugin_safe_mode_guard_assert($status['marker_path'] === $external.'tmp/safe_mode', 'Inactive diagnostics must show the configured marker path.');
plugin_safe_mode_guard_assert($status['lock_path'] === $external.'tmp/safe_mode.lock', 'Inactive diagnostics must show the stable operation lock.');

$identity_lock_path = $external.'tmp/safe_mode.lock';
$identity_decoy_path = $fixture.'/identity-decoy.lock';
plugin_safe_mode_guard_write($identity_lock_path, '');
plugin_safe_mode_guard_write($identity_decoy_path, 'decoy');
$identity_lock_options = array(
	'timeout'=>0,
	'open'=>function($path) use ($identity_decoy_path) { return @fopen($identity_decoy_path, 'r+b'); },
);
$identity_handle = NULL;
$identity_path = '';
plugin_safe_mode_guard_assert(!plugin_safe_mode_lock_acquire($paths, $identity_handle, $identity_path, $identity_lock_options), 'An operation lock handle that is not bound to the candidate pathname must fail closed after locking.');
plugin_safe_mode_guard_assert($identity_handle === NULL && $identity_path === '', 'A rejected operation lock identity must not publish a lock owner.');

$atomic_directory = $fixture.'/atomic-log/';
plugin_safe_mode_guard_mkdir($atomic_directory);
$atomic_backup = $atomic_directory.'safe_mode.previous.php';
$atomic_sentinel = plugin_safe_mode_log_prefix()."preserved backup\n";
$atomic_replacement = plugin_safe_mode_log_prefix()."complete replacement payload\n";
plugin_safe_mode_guard_write($atomic_backup, $atomic_sentinel);
$partial_replace_calls = 0;
$partial_replace_options = array('write'=>function($handle, $remaining) use (&$partial_replace_calls) {
	$partial_replace_calls++;
	if($partial_replace_calls !== 1) return FALSE;
	return @fwrite($handle, substr($remaining, 0, min(7, strlen($remaining))));
});
plugin_safe_mode_guard_assert(!plugin_safe_mode_replace_log($atomic_backup, $atomic_replacement, $partial_replace_options), 'A partial backup staging write followed by failure must reject replacement.');
plugin_safe_mode_guard_assert(file_get_contents($atomic_backup) === $atomic_sentinel, 'A failed backup staging write must preserve the previous backup byte-for-byte.');
plugin_safe_mode_guard_assert(empty(glob($atomic_directory.'.*.tmp.php')), 'A failed backup staging write must remove its staging file.');

$flush_replace_options = array('flush'=>function($handle) { return FALSE; });
plugin_safe_mode_guard_assert(!plugin_safe_mode_replace_log($atomic_backup, $atomic_replacement, $flush_replace_options), 'A backup staging flush failure must reject replacement.');
plugin_safe_mode_guard_assert(file_get_contents($atomic_backup) === $atomic_sentinel, 'A backup staging flush failure must preserve the previous backup byte-for-byte.');
plugin_safe_mode_guard_assert(empty(glob($atomic_directory.'.*.tmp.php')), 'A backup staging flush failure must remove its staging file.');

$rename_failure_options = array('rename'=>function($source, $destination) { return FALSE; });
plugin_safe_mode_guard_assert(!plugin_safe_mode_replace_log($atomic_backup, $atomic_replacement, $rename_failure_options), 'A failed atomic backup rename must reject replacement.');
plugin_safe_mode_guard_assert(file_get_contents($atomic_backup) === $atomic_sentinel, 'A failed atomic backup rename must preserve the previous backup byte-for-byte.');
plugin_safe_mode_guard_assert(empty(glob($atomic_directory.'.*.tmp.php')), 'A failed atomic backup rename must remove its staging file.');

$short_replace_options = array('write'=>function($handle, $remaining) {
	return @fwrite($handle, substr($remaining, 0, min(3, strlen($remaining))));
});
plugin_safe_mode_guard_assert(plugin_safe_mode_replace_log($atomic_backup, $atomic_replacement, $short_replace_options), 'Repeated short writes must complete an atomic backup replacement.');
plugin_safe_mode_guard_assert(file_get_contents($atomic_backup) === $atomic_replacement, 'A successful short-write backup replacement must publish the complete payload.');
plugin_safe_mode_guard_assert(empty(glob($atomic_directory.'.*.tmp.php')), 'A successful backup replacement must not leave a staging file.');

$append_directory = $fixture.'/append-log/';
plugin_safe_mode_guard_mkdir($append_directory);
$append_log = $append_directory.'safe_mode.php';
$append_seed = plugin_safe_mode_log_prefix()."existing diagnostic\n";
$append_paths = array(
	'log_write_paths'=>array($append_log),
	'log_backup_paths'=>array($append_directory.'safe_mode.previous.php'),
);
plugin_safe_mode_guard_write($append_log, $append_seed);
$partial_append_calls = 0;
$partial_append_options = array('write'=>function($handle, $remaining) use (&$partial_append_calls) {
	$partial_append_calls++;
	if($partial_append_calls !== 1) return FALSE;
	return @fwrite($handle, substr($remaining, 0, min(7, strlen($remaining))));
});
$append_written_path = '';
plugin_safe_mode_guard_assert(!plugin_safe_mode_append_log($append_paths, 'partial append must roll back', $append_written_path, '', $partial_append_options), 'A partial diagnostic append followed by failure must be rejected.');
plugin_safe_mode_guard_assert($append_written_path === '' && file_get_contents($append_log) === $append_seed, 'A failed partial append must restore the main log byte-for-byte.');

$flush_append_options = array('flush'=>function($handle) { return FALSE; });
$append_written_path = '';
plugin_safe_mode_guard_assert(!plugin_safe_mode_append_log($append_paths, 'flush failure must roll back', $append_written_path, '', $flush_append_options), 'A diagnostic append flush failure must be rejected.');
plugin_safe_mode_guard_assert($append_written_path === '' && file_get_contents($append_log) === $append_seed, 'A failed append flush must restore the main log byte-for-byte.');

$short_append_message = 'short writes must complete';
$short_append_options = array('write'=>function($handle, $remaining) {
	return @fwrite($handle, substr($remaining, 0, min(3, strlen($remaining))));
});
$append_written_path = '';
plugin_safe_mode_guard_assert(plugin_safe_mode_append_log($append_paths, $short_append_message, $append_written_path, '', $short_append_options), 'Repeated short writes must complete a diagnostic append.');
$expected_append = $append_seed.plugin_safe_mode_log_prefix().$short_append_message."\n";
plugin_safe_mode_guard_assert($append_written_path === $append_log && file_get_contents($append_log) === $expected_append, 'A successful short-write append must publish one complete protected record.');

$rotation_failure_directory = $fixture.'/rotation-failure/';
plugin_safe_mode_guard_mkdir($rotation_failure_directory);
$rotation_failure_log = $rotation_failure_directory.'safe_mode.php';
$rotation_failure_backup = $rotation_failure_directory.'safe_mode.previous.php';
$rotation_failure_seed = plugin_safe_mode_log_prefix().str_repeat('r', plugin_safe_mode_log_max_bytes() + 1024)."\n";
plugin_safe_mode_guard_write($rotation_failure_log, $rotation_failure_seed);
plugin_safe_mode_guard_mkdir($rotation_failure_backup);
$rotation_failure_paths = array(
	'log_write_paths'=>array($rotation_failure_log),
	'log_backup_paths'=>array($rotation_failure_backup),
);
$append_written_path = '';
plugin_safe_mode_guard_assert(!plugin_safe_mode_append_log($rotation_failure_paths, 'backup failure must precede truncation', $append_written_path), 'A rejected backup replacement must reject log rotation.');
plugin_safe_mode_guard_assert($append_written_path === '' && file_get_contents($rotation_failure_log) === $rotation_failure_seed, 'A rejected backup replacement must leave the oversized main log byte-for-byte intact.');

$valid_request_id = str_repeat('a', 32);
$plugin_type_error = $app.'plugin/example/type_error.php';
plugin_safe_mode_guard_write($plugin_type_error, "<?php strlen(array());\n");
$exit_code = plugin_safe_mode_guard_type_error_child($fixture, $root, $app, $conf, $plugin_type_error, $valid_request_id, $output);
plugin_safe_mode_guard_assert($exit_code !== 0, 'A terminal plugin TypeError must return a non-zero child status.');
plugin_safe_mode_guard_assert(is_file($external.'tmp/safe_mode'), 'A real plugin TypeError must activate safe mode.');
plugin_safe_mode_guard_assert(is_file($external.'log/safe_mode.php'), 'A real plugin TypeError must write the protected diagnostic log.');
$type_error_log = file_get_contents($external.'log/safe_mode.php');
plugin_safe_mode_guard_assert(strpos($type_error_log, plugin_safe_mode_log_prefix()) === 0, 'Safe-mode TypeError diagnostics must be PHP-protected.');
plugin_safe_mode_guard_assert(strpos($type_error_log, 'strlen') !== FALSE && strpos($type_error_log, 'request_id='.$valid_request_id) !== FALSE, 'Plugin TypeError diagnostics must identify the failure and valid Request ID.');
@unlink($external.'tmp/safe_mode');
@unlink($external.'log/safe_mode.php');

$compiled_type_error = $external.'tmp/model.min.php';
plugin_safe_mode_guard_write($compiled_type_error, "<?php strlen(array());\n");
$exit_code = plugin_safe_mode_guard_type_error_child($fixture, $root, $app, $conf, $compiled_type_error, str_repeat('b', 32), $output);
plugin_safe_mode_guard_assert($exit_code !== 0 && is_file($external.'tmp/safe_mode'), 'A real TypeError in the configured compiled tmp tree must activate safe mode.');
@unlink($external.'tmp/safe_mode');
@unlink($external.'log/safe_mode.php');

$core_type_error = $app.'model/core_type_error.php';
plugin_safe_mode_guard_write($core_type_error, "<?php strlen(array());\n");
$exit_code = plugin_safe_mode_guard_type_error_child($fixture, $root, $app, $conf, $core_type_error, str_repeat('c', 32), $output);
plugin_safe_mode_guard_assert($exit_code !== 0, 'A real core TypeError must still terminate with a non-zero status.');
plugin_safe_mode_guard_assert(!is_file($external.'tmp/safe_mode') && !is_file($app.'tmp/safe_mode'), 'A real core TypeError outside plugin/compiled trees must not activate safe mode.');
plugin_safe_mode_guard_assert(!is_file($external.'log/safe_mode.php'), 'A real core TypeError must not create a plugin safe-mode diagnostic.');

plugin_safe_mode_guard_write($app.'tmp/safe_mode.php', '');
$status = plugin_safe_mode_status($conf, $app);
plugin_safe_mode_guard_assert($status['active'] && in_array($app.'tmp/safe_mode.php', $status['marker_paths'], TRUE), 'Legacy APP_PATH marker must activate safe mode.');
@unlink($app.'tmp/safe_mode.php');

$fatal = array(
	'type'=>E_PARSE,
	'message'=>"broken <script>alert(1)</script>\nforged line",
	'file'=>$external.'tmp/model.min.php',
	'line'=>37,
);
$_SERVER['request_id'] = $valid_request_id;
plugin_safe_mode_guard_assert(plugin_safe_mode_handle_shutdown_error($fatal, $conf, $app), 'A fatal error in external tmp_path must activate safe mode.');
plugin_safe_mode_guard_assert(is_file($external.'tmp/safe_mode') && !is_file($app.'tmp/safe_mode'), 'Fresh activation must prefer the configured external marker.');
plugin_safe_mode_guard_assert(is_file($external.'tmp/safe_mode.lock'), 'Activation must establish the stable shared lock inode.');
plugin_safe_mode_guard_assert(is_file($external.'log/safe_mode.php'), 'Fatal diagnostics must use the configured PHP-protected log_path.');
$first_log_size = filesize($external.'log/safe_mode.php');
plugin_safe_mode_guard_assert(plugin_safe_mode_handle_shutdown_error($fatal, $conf, $app), 'A repeated eligible fatal must preserve active safe mode.');
clearstatcache(TRUE, $external.'log/safe_mode.php');
plugin_safe_mode_guard_assert(filesize($external.'log/safe_mode.php') === $first_log_size, 'An adjacent duplicate error fingerprint must not append another log entry.');

$different_fatal = $fatal;
$different_fatal['message'] = 'different plugin failure';
plugin_safe_mode_guard_assert(plugin_safe_mode_handle_shutdown_error($different_fatal, $conf, $app), 'A different eligible fatal must remain recoverable.');
clearstatcache(TRUE, $external.'log/safe_mode.php');
plugin_safe_mode_guard_assert(filesize($external.'log/safe_mode.php') > $first_log_size, 'A different error fingerprint must append a new diagnostic.');

$_SERVER['request_id'] = str_repeat('z', 32);
$invalid_request_fatal = $fatal;
$invalid_request_fatal['message'] = 'invalid request id must be ignored';
plugin_safe_mode_guard_assert(plugin_safe_mode_handle_shutdown_error($invalid_request_fatal, $conf, $app), 'Invalid diagnostic context must not prevent activation.');
$diagnostic_log = file_get_contents($external.'log/safe_mode.php');
plugin_safe_mode_guard_assert(strpos($diagnostic_log, str_repeat('z', 32)) === FALSE, 'Forged or malformed Request IDs must never enter safe-mode logs.');
$invalid_request_log_size = filesize($external.'log/safe_mode.php');
plugin_safe_mode_guard_assert(plugin_safe_mode_handle_shutdown_error($invalid_request_fatal, $conf, $app), 'A repeated fatal without a valid Request ID must preserve active safe mode.');
clearstatcache(TRUE, $external.'log/safe_mode.php');
plugin_safe_mode_guard_assert(filesize($external.'log/safe_mode.php') === $invalid_request_log_size, 'Fingerprint deduplication must not depend on a Request ID being present.');

$status = plugin_safe_mode_status($conf, $app);
plugin_safe_mode_guard_assert($status['active'] && $status['marker_path'] === $external.'tmp/safe_mode', 'Diagnostics must report the actual external marker path.');
plugin_safe_mode_guard_assert(strpos($status['latest_error'], 'invalid request id') !== FALSE, 'Latest error reader must return the most recent distinct diagnostic.');
plugin_safe_mode_guard_assert(strpos($status['latest_error'], "\n") === FALSE && strlen($status['latest_error']) <= 2048, 'Latest error diagnostics must be single-line and length bounded.');
$escaped_error = plugin_safe_mode_html($fatal['message'], 2048);
plugin_safe_mode_guard_assert(strpos($escaped_error, '<script>') === FALSE && strpos($escaped_error, '&lt;script&gt;') !== FALSE, 'Admin diagnostic HTML must escape fatal error content.');

$oversized = plugin_safe_mode_log_prefix().'old FATAL fingerprint='.str_repeat('d', 64).': '.str_repeat('x', plugin_safe_mode_log_max_bytes() * 3)."\n";
plugin_safe_mode_guard_write($external.'log/safe_mode.php', $oversized);
$_SERVER['request_id'] = str_repeat('e', 32);
$rotation_fatal = $fatal;
$rotation_fatal['message'] = 'rotation trigger';
plugin_safe_mode_guard_assert(plugin_safe_mode_handle_shutdown_error($rotation_fatal, $conf, $app), 'An oversized log must not prevent safe-mode activation.');
$main_log = $external.'log/safe_mode.php';
$backup_log = $external.'log/safe_mode.previous.php';
clearstatcache(TRUE, $main_log);
clearstatcache(TRUE, $backup_log);
plugin_safe_mode_guard_assert(is_file($backup_log), 'Oversized diagnostics must rotate to a protected backup.');
plugin_safe_mode_guard_assert(filesize($main_log) <= plugin_safe_mode_log_max_bytes() && filesize($backup_log) <= plugin_safe_mode_log_max_bytes(), 'Main and backup diagnostics must both remain size bounded.');
plugin_safe_mode_guard_assert(strpos(file_get_contents($main_log), plugin_safe_mode_log_prefix()) === 0 && strpos(file_get_contents($backup_log), plugin_safe_mode_log_prefix()) === 0, 'Main and backup diagnostics must both be PHP-protected.');
$main_log_exit = plugin_safe_mode_guard_run_php($main_log, array(), $main_log_output);
$backup_log_exit = plugin_safe_mode_guard_run_php($backup_log, array(), $backup_log_output);
plugin_safe_mode_guard_assert($main_log_exit === 0 && $backup_log_exit === 0 && $main_log_output === '' && $backup_log_output === '', 'Direct PHP execution of main or backup diagnostics must disclose no log content.');
plugin_safe_mode_guard_assert(!is_file($external.'log/safe_mode.log'), 'New activations must never append to the legacy plaintext log.');

$symlink_app = $fixture.'/symlink-app/';
$symlink_external = $fixture.'/symlink-external/';
$symlink_target_directory = $fixture.'/symlink-target/';
foreach(array($symlink_app.'tmp/', $symlink_app.'log/', $symlink_external.'tmp/', $symlink_external.'log/', $symlink_target_directory) as $directory) {
	plugin_safe_mode_guard_mkdir($directory);
}
$symlink_conf = array('tmp_path'=>$symlink_external.'tmp/', 'log_path'=>$symlink_external.'log/');
$symlink_paths = plugin_safe_mode_paths($symlink_conf, $symlink_app);
$symlink_target = $symlink_target_directory.'victim.php';
$symlink_sentinel = "symlink target must remain unchanged\n";
plugin_safe_mode_guard_write($symlink_target, $symlink_sentinel);
$symlink_main_log = $symlink_external.'log/safe_mode.php';
if(function_exists('symlink') && @symlink($symlink_target, $symlink_main_log)) {
	$symlink_lock = $symlink_external.'tmp/safe_mode.lock';
	plugin_safe_mode_guard_assert(@symlink($symlink_target, $symlink_lock), 'Unable to create the safe-mode operation-lock symlink fixture after log symlinks were available.');
	$symlink_error = '';
	$symlink_marker_path = '';
	plugin_safe_mode_guard_assert(plugin_safe_mode_enable($symlink_conf, $symlink_app, $symlink_error, $symlink_marker_path), 'A symlinked primary operation lock must fall back to a safe application lock.');
	plugin_safe_mode_guard_assert($symlink_marker_path === $symlink_external.'tmp/safe_mode' && is_file($symlink_app.'tmp/safe_mode.lock'), 'A rejected primary operation-lock symlink must use the next stable lock path.');
	plugin_safe_mode_guard_assert(file_get_contents($symlink_target) === $symlink_sentinel, 'Acquiring a safe-mode operation lock must not follow a lock symlink.');
	$symlink_failed_paths = array();
	plugin_safe_mode_guard_assert(plugin_safe_mode_exit($symlink_conf, $symlink_app, $symlink_error, $symlink_failed_paths), 'A marker protected by a fallback operation lock must remain deactivatable.');
	plugin_safe_mode_guard_assert(file_get_contents($symlink_target) === $symlink_sentinel, 'Releasing safe mode must not follow a lock symlink.');

	@unlink($symlink_lock);
	$symlink_dangling_target = $symlink_target_directory.'missing.lock';
	plugin_safe_mode_guard_assert(!file_exists($symlink_dangling_target) && @symlink($symlink_dangling_target, $symlink_lock), 'Unable to create the dangling safe-mode operation-lock symlink fixture.');
	plugin_safe_mode_guard_assert(plugin_safe_mode_enable($symlink_conf, $symlink_app, $symlink_error, $symlink_marker_path), 'A dangling primary operation-lock symlink must fall back without creating its target.');
	plugin_safe_mode_guard_assert(!file_exists($symlink_dangling_target) && is_file($symlink_app.'tmp/safe_mode.lock'), 'A dangling operation-lock symlink must never create its target.');
	plugin_safe_mode_guard_assert(plugin_safe_mode_exit($symlink_conf, $symlink_app, $symlink_error, $symlink_failed_paths), 'Safe mode activated beside a dangling lock symlink must remain deactivatable.');
	@unlink($symlink_lock);

	plugin_safe_mode_guard_assert(plugin_safe_mode_read_latest_error($symlink_main_log) === '', 'Safe-mode diagnostics must not read through a main-log symlink.');
	$symlink_status = plugin_safe_mode_status($symlink_conf, $symlink_app);
	plugin_safe_mode_guard_assert($symlink_status['latest_error'] === '', 'Safe-mode status must ignore a symlinked diagnostic log.');
	$symlink_written_path = '';
	plugin_safe_mode_guard_assert(plugin_safe_mode_append_log($symlink_paths, 'safe fallback diagnostic', $symlink_written_path), 'A symlinked primary log must fall back to a safe application log.');
	plugin_safe_mode_guard_assert(file_get_contents($symlink_target) === $symlink_sentinel, 'Writing a diagnostic must not follow a main-log symlink.');
	plugin_safe_mode_guard_assert($symlink_written_path === $symlink_app.'log/safe_mode.php', 'A rejected main-log symlink must use the next safe log path.');

	@unlink($symlink_main_log);
	@unlink($symlink_app.'log/safe_mode.php');
	$oversized_symlink_log = plugin_safe_mode_log_prefix().str_repeat('x', plugin_safe_mode_log_max_bytes() + 1024)."\n";
	plugin_safe_mode_guard_write($symlink_main_log, $oversized_symlink_log);
	$symlink_backup_log = $symlink_external.'log/safe_mode.previous.php';
	plugin_safe_mode_guard_assert(@symlink($symlink_target, $symlink_backup_log), 'Unable to create the safe-mode backup symlink fixture after main-log symlinks were available.');
	$symlink_written_path = '';
	plugin_safe_mode_guard_assert(plugin_safe_mode_append_log($symlink_paths, 'safe rotation fallback', $symlink_written_path), 'A symlinked rotation target must fall back without truncating its target.');
	plugin_safe_mode_guard_assert(file_get_contents($symlink_target) === $symlink_sentinel, 'Rotating diagnostics must not follow a backup-log symlink.');
	plugin_safe_mode_guard_assert($symlink_written_path === $symlink_app.'log/safe_mode.php', 'A rejected backup-log symlink must use the next safe log path.');
} else {
	$skips[] = 'safe-mode symlink creation is unavailable; no-follow lock/write/read fixtures were not executed.';
}

$unrelated_error = $fatal;
$unrelated_error['file'] = $app.'route/index.php';
plugin_safe_mode_guard_assert(!plugin_safe_mode_error_is_relevant($unrelated_error, $conf, $app), 'Unrelated core fatals must not be misclassified as plugin safe-mode failures.');
$plugin_error = $fatal;
$plugin_error['file'] = $app.'plugin/example/hook.php';
plugin_safe_mode_guard_assert(plugin_safe_mode_error_is_relevant($plugin_error, $conf, $app), 'Plugin tree fatals must remain eligible for safe-mode activation.');

@unlink($external.'tmp/safe_mode');
$lock_events = array();
$blocked_options = array(
	'timeout'=>0,
	'open'=>function($path) use (&$lock_events) {
		$lock_events[] = $path;
		return @fopen($path, 'c+b');
	},
	'flock'=>function($handle, $operation) { return FALSE; },
);
$error = '';
$marker_path = '';
plugin_safe_mode_guard_assert(!plugin_safe_mode_enable($conf, $app, $error, $marker_path, $blocked_options) && $error === 'locked', 'Activation lock contention must fail closed.');
plugin_safe_mode_guard_write($external.'tmp/safe_mode', '');
$failed_paths = array();
plugin_safe_mode_guard_assert(!plugin_safe_mode_exit($conf, $app, $error, $failed_paths, NULL, $blocked_options) && $error === 'locked', 'Deactivation lock contention must fail closed.');
plugin_safe_mode_guard_assert(count($lock_events) === 2 && $lock_events[0] === $lock_events[1] && $lock_events[0] === $external.'tmp/safe_mode.lock', 'Activation and deactivation must contend on the same stable lock path.');
plugin_safe_mode_guard_assert(is_file($external.'tmp/safe_mode'), 'Lock failure must leave the active marker untouched.');

$unlink_failure = function($path) { return FALSE; };
plugin_safe_mode_guard_assert(!plugin_safe_mode_exit($conf, $app, $error, $failed_paths, $unlink_failure) && $error === 'clear_failed', 'Marker deletion failure must fail closed.');
plugin_safe_mode_guard_assert(!empty($failed_paths) && is_file($external.'tmp/safe_mode'), 'Deletion failure must identify and preserve remaining markers.');
plugin_safe_mode_guard_assert(plugin_safe_mode_exit($conf, $app, $error, $failed_paths), 'Writable markers must clear successfully.');
plugin_safe_mode_guard_assert(!plugin_safe_mode_status($conf, $app)['active'], 'Successful exit must verify that every compatible marker is gone.');
plugin_safe_mode_guard_assert(is_file($external.'tmp/safe_mode.lock'), 'Deactivation must preserve the stable lock inode.');
plugin_safe_mode_guard_assert(plugin_safe_mode_exit($conf, $app, $error, $failed_paths), 'Repeated deactivation must be idempotent.');

$unlock_failure = array('unlock'=>function($handle, $operation) { return FALSE; });
plugin_safe_mode_guard_assert(!plugin_safe_mode_enable($conf, $app, $error, $marker_path, $unlock_failure) && $error === 'unlock_failed', 'Lock release failure after activation must remain explicit.');
plugin_safe_mode_guard_assert(is_file($external.'tmp/safe_mode'), 'Unlock failure after activation must not hide the active marker.');
plugin_safe_mode_guard_assert(plugin_safe_mode_exit($conf, $app, $error, $failed_paths), 'A marker created before an unlock diagnostic must remain recoverable.');

foreach(array_merge($paths['log_write_paths'], $paths['log_backup_paths']) as $path) if(is_file($path)) @touch($path, 100);
plugin_safe_mode_guard_write($app.'log/safe_mode.log', "legacy latest error\n");
@touch($app.'log/safe_mode.log', 200);
clearstatcache();
$status = plugin_safe_mode_status($conf, $app);
plugin_safe_mode_guard_assert($status['log_path'] === $app.'log/safe_mode.log' && $status['latest_error'] === 'legacy latest error', 'Diagnostics must retain read-only compatibility with the newest legacy log.');

$missing_conf = array('tmp_path'=>$fixture.'/missing/tmp/', 'log_path'=>$fixture.'/missing/log/');
plugin_safe_mode_guard_assert(plugin_safe_mode_activate($plugin_error, $missing_conf, $app), 'Unavailable configured paths must fall back to compatible APP marker storage.');
plugin_safe_mode_guard_assert(is_file($app.'tmp/safe_mode') && is_file($app.'log/safe_mode.php'), 'APP fallback must retain recovery with a protected diagnostic log.');
plugin_safe_mode_guard_assert(plugin_safe_mode_exit($missing_conf, $app, $error, $failed_paths), 'Fallback markers must use the same recoverable deactivation contract.');

$failure_app = $fixture.'/failure-app/';
plugin_safe_mode_guard_mkdir($failure_app);
plugin_safe_mode_guard_write($failure_app.'tmp', 'not a directory');
plugin_safe_mode_guard_assert(!plugin_safe_mode_enable(array(), $failure_app, $error, $marker_path) && $error === 'locked', 'An unusable fallback tmp path must fail with a non-zero operation result instead of claiming activation.');

$cli_app = $fixture.'/cli-app/';
$cli_external = $fixture.'/cli-external/';
foreach(array($cli_app.'bin/', $cli_app.'model/', $cli_app.'install/', $cli_app.'conf/', $cli_app.'tmp/', $cli_app.'log/', $cli_external.'tmp/', $cli_external.'log/') as $directory) plugin_safe_mode_guard_mkdir($directory);
foreach(array(
	$root.'/bin/plugin_safe_mode.php'=>$cli_app.'bin/plugin_safe_mode.php',
	$root.'/model/plugin_safe_mode.func.php'=>$cli_app.'model/plugin_safe_mode.func.php',
	$root.'/install/install-state.func.php'=>$cli_app.'install/install-state.func.php',
) as $source=>$target) copy($source, $target) || plugin_safe_mode_guard_fail('Unable to copy CLI fixture dependency: '.$source);
$valid_cli_conf = array(
	'installed'=>1,
	'lang'=>'zh-cn',
	'tmp_path'=>$cli_external.'tmp/',
	'log_path'=>$cli_external.'log/',
	'upload_path'=>'./upload/',
	'auth_key'=>str_repeat('f', 64),
	'db'=>array('type'=>'pdo_mysql', 'pdo_mysql'=>array('master'=>array(
		'host'=>'127.0.0.1', 'user'=>'fixture', 'password'=>'fixture', 'name'=>'fixture',
		'tablepre'=>'bbs_', 'charset'=>'utf8mb4', 'engine'=>'myisam',
	), 'slaves'=>array())),
);
plugin_safe_mode_guard_write($cli_app.'conf/conf.php', "<?php\nreturn ".var_export($valid_cli_conf, TRUE).";\n");
$cli_script = $cli_app.'bin/plugin_safe_mode.php';
$exit_code = plugin_safe_mode_guard_run_php($cli_script, array('status'), $output);
plugin_safe_mode_guard_assert($exit_code === 0 && strpos($output, 'Safe mode: inactive') !== FALSE && strpos($output, 'valid conf/conf.php') !== FALSE, 'CLI status must work without Composer, database, or application bootstrap.');
$exit_code = plugin_safe_mode_guard_run_php($cli_script, array('activate'), $output);
plugin_safe_mode_guard_assert($exit_code === 0 && is_file($cli_external.'tmp/safe_mode'), 'CLI activate must honor a valid external tmp_path.');
$exit_code = plugin_safe_mode_guard_run_php($cli_script, array('activate'), $output);
plugin_safe_mode_guard_assert($exit_code === 0 && is_file($cli_external.'tmp/safe_mode'), 'Repeated CLI activation must be idempotent.');
$exit_code = plugin_safe_mode_guard_run_php($cli_script, array('status'), $output);
plugin_safe_mode_guard_assert($exit_code === 0 && strpos($output, 'Safe mode: active') !== FALSE && strpos($output, $cli_external.'tmp/safe_mode') !== FALSE, 'CLI status must expose the actual active external marker.');
$exit_code = plugin_safe_mode_guard_run_php($cli_script, array('deactivate'), $output);
plugin_safe_mode_guard_assert($exit_code === 0 && !is_file($cli_external.'tmp/safe_mode') && is_file($cli_external.'tmp/safe_mode.lock'), 'CLI deactivate must clear only the marker and retain its stable lock.');
$exit_code = plugin_safe_mode_guard_run_php($cli_script, array('deactivate'), $output);
plugin_safe_mode_guard_assert($exit_code === 0, 'Repeated CLI deactivation must be idempotent.');

@unlink($cli_app.'conf/conf.php');
$exit_code = plugin_safe_mode_guard_run_php($cli_script, array('activate'), $output);
plugin_safe_mode_guard_assert($exit_code === 0 && is_file($cli_app.'tmp/safe_mode') && strpos($output, 'using APP tmp/log fallback') !== FALSE, 'Missing configuration must use the documented APP tmp/log recovery fallback.');
$exit_code = plugin_safe_mode_guard_run_php($cli_script, array('deactivate'), $output);
plugin_safe_mode_guard_assert($exit_code === 0 && !is_file($cli_app.'tmp/safe_mode'), 'Fallback CLI activation must remain deactivatable.');
$exit_code = plugin_safe_mode_guard_run_php($cli_script, array('unknown'), $output);
plugin_safe_mode_guard_assert($exit_code !== 0 && strpos($output, 'Usage:') !== FALSE, 'Unknown CLI actions must fail with actionable usage text.');

$server = plugin_safe_mode_guard_start_server($cli_app, $server_pipes, $server_port);
try {
	$body = plugin_safe_mode_guard_http_get('http://127.0.0.1:'.$server_port.'/bin/plugin_safe_mode.php?action=activate', $http_status);
	plugin_safe_mode_guard_assert($http_status === 404 && trim($body) === 'Not Found', 'The safe-mode CLI endpoint must fail closed under cli-server.');
	plugin_safe_mode_guard_assert(!is_file($cli_app.'tmp/safe_mode') && !is_file($cli_external.'tmp/safe_mode'), 'A Web request to the CLI script must have no marker side effect.');
} finally {
	plugin_safe_mode_guard_stop_server($server, $server_pipes);
}

$index = file_get_contents($root.'/index.php');
$admin_route = file_get_contents($root.'/admin/route/index.php');
$admin_index = file_get_contents($root.'/admin/index.inc.php');
$admin_view = file_get_contents($root.'/admin/view/htm/index.htm');
$cache_route = file_get_contents($root.'/admin/route/other.php');
$php8_compat = file_get_contents($root.'/xiunophp/php8_compat.php');
$cli_source = file_get_contents($root.'/bin/plugin_safe_mode.php');
$plugin_model = file_get_contents($root.'/model/plugin.func.php');
foreach(array($index, $admin_route, $admin_index, $admin_view, $cache_route, $php8_compat, $cli_source, $plugin_model) as $source) is_string($source) || plugin_safe_mode_guard_fail('Unable to read core safe-mode source.');
plugin_safe_mode_guard_assert(strpos($index, "require_once APP_PATH . 'model/plugin_safe_mode.func.php';") !== FALSE, 'Front controller must load safe-mode helpers before registering the fatal handler.');
plugin_safe_mode_guard_assert(strpos($index, 'plugin_safe_mode_handle_shutdown_error(') !== FALSE && strpos($index, 'plugin_safe_mode_is_active($conf, APP_PATH)') !== FALSE, 'Front controller must use the shared safe-mode activation and lightweight marker contract.');
plugin_safe_mode_guard_assert(strpos($index, "\$_GET['safe_mode']") === FALSE && strpos($index, '$safe_key') === FALSE, 'Front controller must not expose auth_key-backed safe mode through a URL query.');
plugin_safe_mode_guard_assert(strpos($index, 'plugin_safe_mode_status($conf, APP_PATH)') === FALSE, 'Normal requests must not read diagnostic logs merely to test the marker.');
plugin_safe_mode_guard_assert(strpos($php8_compat, 'plugin_safe_mode_handle_throwable($e') !== FALSE, 'The terminal PHP 8 TypeError path must enter the shared safe-mode classifier.');
plugin_safe_mode_guard_assert(strpos($cli_source, "PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg'") !== FALSE, 'Safe-mode recovery CLI must reject cli-server and all Web SAPIs before loading helpers.');
plugin_safe_mode_guard_assert(strpos($cli_source, 'vendor/autoload.php') === FALSE && strpos($cli_source, 'model.inc.php') === FALSE && strpos($cli_source, 'plugin_init') === FALSE, 'Safe-mode recovery CLI must not depend on Composer, database, or plugin bootstrap.');
plugin_safe_mode_guard_assert(strpos($admin_route, "ini_get('safe_mode')") === FALSE, 'Admin diagnostics must not read removed PHP safe_mode configuration.');
plugin_safe_mode_guard_assert(strpos($admin_route, "\$action == 'safe_mode_exit'") !== FALSE && strpos($admin_route, "\$method != 'POST'") !== FALSE && strpos($admin_route, 'plugin_safe_mode_exit($conf, APP_PATH') !== FALSE, 'Safe-mode exit route must be explicit POST and use the shared locked helper.');
plugin_safe_mode_guard_assert(strpos($admin_index, "if (\$method == 'POST')") !== FALSE && strpos($admin_index, 'csrf_check();') !== FALSE, 'Admin POST routes must remain behind the global CSRF gate.');
plugin_safe_mode_guard_assert(strpos($admin_view, 'method="post"') !== FALSE && strpos($admin_view, 'name="_token"') !== FALSE && substr_count($admin_view, 'plugin_safe_mode_html(') >= 4, 'Admin UI must submit CSRF explicitly and escape bounded diagnostic values.');
plugin_safe_mode_guard_assert(strpos($cache_route, 'plugin_safe_mode') === FALSE && strpos(file_get_contents($root.'/model/plugin_safe_mode.func.php'), 'runtime_cache_clear_regenerable') === FALSE, 'Safe-mode exit must remain separate from ordinary cache cleanup.');
plugin_safe_mode_guard_assert(strpos($plugin_model, "substr(\$name, -5) === '.lock'") !== FALSE, 'Ordinary runtime cache cleanup must preserve the stable safe-mode lock inode.');

$language_files = glob($root.'/lang/*/bbs_admin.php');
plugin_safe_mode_guard_assert(is_array($language_files) && !empty($language_files), 'At least one admin language catalogue must be discoverable.');
$language_keys = array('plugin_safe_mode', 'plugin_safe_mode_marker_path', 'plugin_safe_mode_log_path', 'plugin_safe_mode_latest_error', 'plugin_safe_mode_exit', 'plugin_safe_mode_exit_success', 'plugin_safe_mode_exit_locked', 'plugin_safe_mode_exit_clear_failed', 'plugin_safe_mode_exit_unlock_failed');
foreach($language_files as $language_file) {
	$lang_source = file_get_contents($language_file);
	$language = basename(dirname($language_file));
	foreach($language_keys as $key) strpos($lang_source, "'$key'=>") !== FALSE || plugin_safe_mode_guard_fail("Missing $language admin language key: $key");
}

echo "OK: plugin safe-mode safety checks passed for available fixtures\n";
foreach($skips as $skip) echo 'SKIP: '.$skip.PHP_EOL;

?>
