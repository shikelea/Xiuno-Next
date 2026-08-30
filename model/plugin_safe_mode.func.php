<?php

function plugin_safe_mode_path_is_absolute($path) {
	$path = str_replace('\\', '/', (string)$path);
	return substr($path, 0, 1) === '/'
		|| substr($path, 0, 2) === '//'
		|| preg_match('/^[A-Za-z]:\//', $path) === 1;
}

function plugin_safe_mode_directory($path, $fallback, $app_path) {
	$app_path = str_replace('\\', '/', (string)$app_path);
	$app_path = rtrim($app_path, '/') . '/';
	$fallback = str_replace('\\', '/', (string)$fallback);
	$path = is_string($path) ? trim($path) : '';
	if($path === '' || strlen($path) > 4096 || strpos($path, "\0") !== FALSE) $path = $fallback;
	$path = str_replace('\\', '/', $path);
	if(substr($path, 0, 2) === './') {
		$path = $app_path.substr($path, 2);
	} elseif(!plugin_safe_mode_path_is_absolute($path)) {
		$path = $app_path.ltrim($path, '/');
	}
	$real_path = @realpath($path);
	if($real_path !== FALSE) $path = str_replace('\\', '/', $real_path);
	return rtrim($path, '/') . '/';
}

function plugin_safe_mode_unique_paths($paths) {
	$unique = array();
	$seen = array();
	foreach($paths as $path) {
		$path = str_replace('\\', '/', (string)$path);
		$key = DIRECTORY_SEPARATOR === '\\' ? strtolower($path) : $path;
		if(isset($seen[$key])) continue;
		$seen[$key] = TRUE;
		$unique[] = $path;
	}
	return $unique;
}

function plugin_safe_mode_paths($conf, $app_path) {
	$conf = is_array($conf) ? $conf : array();
	$app_path = plugin_safe_mode_directory($app_path, './', './');
	$legacy_tmp_path = $app_path.'tmp/';
	$legacy_log_path = $app_path.'log/';
	$tmp_path = plugin_safe_mode_directory(isset($conf['tmp_path']) ? $conf['tmp_path'] : '', $legacy_tmp_path, $app_path);
	$log_path = plugin_safe_mode_directory(isset($conf['log_path']) ? $conf['log_path'] : '', $legacy_log_path, $app_path);
	$tmp_paths = plugin_safe_mode_unique_paths(array($tmp_path, $legacy_tmp_path));
	$log_directories = plugin_safe_mode_unique_paths(array($log_path, $legacy_log_path));
	$marker_paths = array();
	$marker_write_paths = array();
	$lock_paths = array();
	foreach($tmp_paths as $directory) {
		$marker_write_paths[] = $directory.'safe_mode';
		$marker_paths[] = $directory.'safe_mode';
		$marker_paths[] = $directory.'safe_mode.php';
		$lock_paths[] = $directory.'safe_mode.lock';
	}
	$log_write_paths = array();
	$log_backup_paths = array();
	$legacy_log_paths = array();
	foreach($log_directories as $directory) {
		$log_write_paths[] = $directory.'safe_mode.php';
		$log_backup_paths[] = $directory.'safe_mode.previous.php';
		$legacy_log_paths[] = $directory.'safe_mode.log';
	}
	$log_write_paths = plugin_safe_mode_unique_paths($log_write_paths);
	$log_backup_paths = plugin_safe_mode_unique_paths($log_backup_paths);
	$log_paths = plugin_safe_mode_unique_paths(array_merge($log_write_paths, $log_backup_paths, $legacy_log_paths));

	return array(
		'tmp_path'=>$tmp_path,
		'log_directory'=>$log_path,
		'marker_paths'=>plugin_safe_mode_unique_paths($marker_paths),
		'marker_write_paths'=>plugin_safe_mode_unique_paths($marker_write_paths),
		'lock_paths'=>plugin_safe_mode_unique_paths($lock_paths),
		'log_write_paths'=>$log_write_paths,
		'log_backup_paths'=>$log_backup_paths,
		'log_paths'=>$log_paths,
	);
}

function plugin_safe_mode_path_within($path, $directory) {
	$path = str_replace('\\', '/', (string)$path);
	$directory = rtrim(str_replace('\\', '/', (string)$directory), '/') . '/';
	$real_path = @realpath($path);
	if($real_path !== FALSE) $path = str_replace('\\', '/', $real_path);
	if(DIRECTORY_SEPARATOR === '\\') {
		$path = strtolower($path);
		$directory = strtolower($directory);
	}
	return strpos($path, $directory) === 0;
}

function plugin_safe_mode_error_is_relevant($error, $conf, $app_path) {
	if(!is_array($error) || empty($error['file']) || !isset($error['type'])) return FALSE;
	if(!in_array(intval($error['type']), array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), TRUE)) return FALSE;
	$paths = plugin_safe_mode_paths($conf, $app_path);
	$directories = array($paths['tmp_path'], plugin_safe_mode_directory('tmp/', '', $app_path), plugin_safe_mode_directory('plugin/', '', $app_path));
	foreach(plugin_safe_mode_unique_paths($directories) as $directory) {
		if(plugin_safe_mode_path_within($error['file'], $directory)) return TRUE;
	}
	return FALSE;
}

function plugin_safe_mode_text($value, $max_bytes = 2048) {
	$value = is_scalar($value) ? (string)$value : '';
	$value = str_replace(array("\r\n", "\r", "\n", "\t"), ' ', $value);
	$value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
	$max_bytes = max(0, intval($max_bytes));
	if(strlen($value) > $max_bytes) {
		$value = $max_bytes > 3 ? substr($value, 0, $max_bytes - 3).'...' : substr($value, 0, $max_bytes);
	}
	return $value;
}

function plugin_safe_mode_html($value, $max_bytes = 2048) {
	return htmlspecialchars(plugin_safe_mode_text($value, $max_bytes), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function plugin_safe_mode_active_markers($paths) {
	$active = array();
	clearstatcache();
	foreach($paths['marker_paths'] as $path) {
		if(is_file($path)) $active[] = $path;
	}
	return $active;
}

function plugin_safe_mode_is_active($conf, $app_path) {
	return !empty(plugin_safe_mode_active_markers(plugin_safe_mode_paths($conf, $app_path)));
}

function plugin_safe_mode_operation_lock_paths($paths) {
	$lock_paths = array();
	foreach(plugin_safe_mode_active_markers($paths) as $marker_path) {
		$lock_paths[] = str_replace('\\', '/', dirname($marker_path)).'/safe_mode.lock';
	}
	return plugin_safe_mode_unique_paths(array_merge($lock_paths, $paths['lock_paths']));
}

function plugin_safe_mode_lock_acquire($paths, &$handle, &$lock_path, $options = array()) {
	$handle = NULL;
	$lock_path = '';
	$options = is_array($options) ? $options : array();
	$timeout = isset($options['timeout']) ? max(0, floatval($options['timeout'])) : 2.0;
	$open_callback = isset($options['open']) && is_callable($options['open']) ? $options['open'] : NULL;
	$flock_callback = isset($options['flock']) && is_callable($options['flock']) ? $options['flock'] : NULL;

	foreach(plugin_safe_mode_operation_lock_paths($paths) as $candidate) {
		$candidate_handle = $open_callback
			? call_user_func($open_callback, $candidate)
			: plugin_safe_mode_open_regular_file($candidate, TRUE);
		if(!is_resource($candidate_handle)) continue;
		$deadline = microtime(TRUE) + $timeout;
		do {
			$locked = $flock_callback
				? call_user_func($flock_callback, $candidate_handle, LOCK_EX | LOCK_NB)
				: @flock($candidate_handle, LOCK_EX | LOCK_NB);
			if($locked) {
				// The pathname may have been replaced while this process waited for the
				// inode lock. Never publish a lock owner against an unbound path.
				if(!plugin_safe_mode_handle_matches_path($candidate, $candidate_handle)) {
					@flock($candidate_handle, LOCK_UN);
					@fclose($candidate_handle);
					return FALSE;
				}
				$handle = $candidate_handle;
				$lock_path = $candidate;
				return TRUE;
			}
			if(microtime(TRUE) >= $deadline) break;
			usleep(10000);
		} while(TRUE);
		@fclose($candidate_handle);
		// Once a stable candidate can be opened, contention must not fall through
		// to a second inode and split the activation/deactivation lock domain.
		return FALSE;
	}
	return FALSE;
}

function plugin_safe_mode_lock_release($handle, $options = array()) {
	if(!is_resource($handle)) return FALSE;
	$options = is_array($options) ? $options : array();
	$unlock_callback = isset($options['unlock']) && is_callable($options['unlock']) ? $options['unlock'] : NULL;
	$unlocked = $unlock_callback
		? call_user_func($unlock_callback, $handle, LOCK_UN)
		: @flock($handle, LOCK_UN);
	$closed = @fclose($handle);
	return $unlocked && $closed;
}

function plugin_safe_mode_create_marker($paths, &$written_path = '') {
	$written_path = '';
	$active = plugin_safe_mode_active_markers($paths);
	if(!empty($active)) {
		$written_path = $active[0];
		return TRUE;
	}
	foreach($paths['marker_write_paths'] as $path) {
		if(is_link($path)) continue;
		$marker = @fopen($path, 'x+b');
		if(is_resource($marker)) {
			@fflush($marker);
			@fclose($marker);
		}
		clearstatcache(TRUE, $path);
		if(!is_file($path)) continue;
		$written_path = $path;
		return TRUE;
	}
	return FALSE;
}

function plugin_safe_mode_request_id() {
	$value = isset($_SERVER['request_id']) ? $_SERVER['request_id'] : '';
	return is_string($value) && preg_match('/\A[a-f0-9]{32}\z/D', $value) === 1 ? $value : '';
}

function plugin_safe_mode_error_fingerprint($error) {
	$error = is_array($error) ? $error : array();
	$type = isset($error['type']) ? intval($error['type']) : E_ERROR;
	$message = isset($error['message']) && is_scalar($error['message']) ? (string)$error['message'] : '';
	$file = isset($error['file']) && is_scalar($error['file']) ? str_replace('\\', '/', (string)$error['file']) : '';
	$real_file = $file !== '' ? @realpath($file) : FALSE;
	if($real_file !== FALSE) $file = str_replace('\\', '/', $real_file);
	if(DIRECTORY_SEPARATOR === '\\') $file = strtolower($file);
	$line = isset($error['line']) ? intval($error['line']) : 0;
	return hash('sha256', $type."\0".$message."\0".$file."\0".$line);
}

function plugin_safe_mode_log_max_bytes() {
	return 131072;
}

function plugin_safe_mode_log_prefix() {
	return "<?php exit;?>\t";
}

function plugin_safe_mode_stat_is_regular($stat) {
	return is_array($stat)
		&& isset($stat['mode'])
		&& (intval($stat['mode']) & 0170000) === 0100000;
}

function plugin_safe_mode_stats_have_same_identity($left, $right) {
	if(!plugin_safe_mode_stat_is_regular($left) || !plugin_safe_mode_stat_is_regular($right)) return FALSE;
	foreach(array('dev', 'ino') as $identity_key) {
		if(!array_key_exists($identity_key, $left) || !array_key_exists($identity_key, $right)) return FALSE;
		if((string)$left[$identity_key] !== (string)$right[$identity_key]) return FALSE;
	}
	return TRUE;
}

function plugin_safe_mode_handle_matches_path($path, $handle) {
	if(!is_resource($handle)) return FALSE;
	$path = (string)$path;
	clearstatcache(TRUE, $path);
	if(is_link($path)) return FALSE;
	$path_stat = @lstat($path);
	$handle_stat = @fstat($handle);
	return !is_link($path) && plugin_safe_mode_stats_have_same_identity($path_stat, $handle_stat);
}

function plugin_safe_mode_open_regular_file($path, $writable = FALSE) {
	$path = (string)$path;
	clearstatcache(TRUE, $path);
	if(is_link($path)) return FALSE;
	$exists = file_exists($path);
	if($exists && !is_file($path)) return FALSE;
	if(!$writable && !$exists) return FALSE;

	// Use exclusive creation for a missing writable path. Unlike c+b, x+b will not
	// follow a dangling symlink that appears between the pre-check and fopen().
	$mode = $writable ? ($exists ? 'r+b' : 'x+b') : 'rb';
	$handle = @fopen($path, $mode);
	if(!is_resource($handle)) return FALSE;

	if(!plugin_safe_mode_handle_matches_path($path, $handle)) {
		@fclose($handle);
		return FALSE;
	}
	return $handle;
}

function plugin_safe_mode_write_all($handle, $data, $options = array()) {
	if(!is_resource($handle) || !is_string($data)) return FALSE;
	$options = is_array($options) ? $options : array();
	$write_callback = isset($options['write']) && is_callable($options['write']) ? $options['write'] : NULL;
	$flush_callback = isset($options['flush']) && is_callable($options['flush']) ? $options['flush'] : NULL;
	$length = strlen($data);
	$offset = 0;
	while($offset < $length) {
		$remaining = substr($data, $offset);
		$written = $write_callback
			? call_user_func($write_callback, $handle, $remaining)
			: @fwrite($handle, $remaining);
		if(!is_int($written) || $written <= 0 || $written > strlen($remaining)) return FALSE;
		$offset += $written;
	}
	return $flush_callback
		? call_user_func($flush_callback, $handle) === TRUE
		: @fflush($handle);
}

function plugin_safe_mode_replace_log($path, $data, $options = array()) {
	$path = (string)$path;
	if(!is_string($data)) return FALSE;
	$options = is_array($options) ? $options : array();
	$rename_callback = isset($options['rename']) && is_callable($options['rename']) ? $options['rename'] : NULL;
	clearstatcache(TRUE, $path);
	$destination_stat = @lstat($path);
	if(is_link($path) || ($destination_stat !== FALSE && !plugin_safe_mode_stat_is_regular($destination_stat))) return FALSE;
	if(!function_exists('random_bytes')) return FALSE;

	$directory = str_replace('\\', '/', dirname($path));
	$basename = basename(str_replace('\\', '/', $path));
	$staging_path = '';
	$handle = FALSE;
	for($attempt = 0; $attempt < 4; $attempt++) {
		try {
			$random = bin2hex(random_bytes(16));
		} catch(Throwable $error) {
			return FALSE;
		}
		$staging_path = $directory.'/.'.$basename.'.'.$random.'.tmp.php';
		$handle = @fopen($staging_path, 'x+b');
		if(is_resource($handle)) break;
	}
	if(!is_resource($handle)) return FALSE;

	$locked = @flock($handle, LOCK_EX);
	$length = strlen($data);
	$final_stat = FALSE;
	$ready = $locked && plugin_safe_mode_handle_matches_path($staging_path, $handle);
	if($ready) {
		$ready = plugin_safe_mode_write_all($handle, $data, $options);
		$final_stat = $ready ? @fstat($handle) : FALSE;
		$ready = $ready
			&& is_array($final_stat)
			&& isset($final_stat['size'])
			&& intval($final_stat['size']) === $length
			&& plugin_safe_mode_handle_matches_path($staging_path, $handle);
	}
	if($locked) @flock($handle, LOCK_UN);
	$closed = @fclose($handle);
	if(!$ready || !$closed) {
		@unlink($staging_path);
		return FALSE;
	}

	clearstatcache(TRUE, $staging_path);
	$staging_stat = @lstat($staging_path);
	if(is_link($staging_path) || !plugin_safe_mode_stats_have_same_identity($staging_stat, $final_stat)) {
		@unlink($staging_path);
		return FALSE;
	}
	clearstatcache(TRUE, $path);
	$destination_stat = @lstat($path);
	if(is_link($path) || ($destination_stat !== FALSE && !plugin_safe_mode_stat_is_regular($destination_stat))) {
		@unlink($staging_path);
		return FALSE;
	}

	$renamed = $rename_callback
		? call_user_func($rename_callback, $staging_path, $path) === TRUE
		: @rename($staging_path, $path);
	if(!$renamed) @unlink($staging_path);
	return $renamed;
}

function plugin_safe_mode_latest_fingerprint_from_data($data) {
	if(!is_string($data) || $data === '') return '';
	$lines = preg_split('/\r\n|\r|\n/', $data);
	for($index = count($lines) - 1; $index >= 0; $index--) {
		if(preg_match('/(?:^|\s)fingerprint=([a-f0-9]{64})(?:\s|$)/D', $lines[$index], $match)) return $match[1];
	}
	return '';
}

function plugin_safe_mode_read_log_tail($handle, $max_bytes) {
	$stat = @fstat($handle);
	$size = is_array($stat) && isset($stat['size']) ? max(0, intval($stat['size'])) : 0;
	$max_bytes = max(1024, intval($max_bytes));
	$offset = max(0, $size - $max_bytes);
	if(@fseek($handle, $offset) !== 0) return '';
	$data = stream_get_contents($handle, $max_bytes);
	if(!is_string($data)) return '';
	return $data;
}

function plugin_safe_mode_bounded_protected_log($data, $max_bytes) {
	$max_bytes = max(1024, intval($max_bytes));
	$prefix = plugin_safe_mode_log_prefix();
	$lines = preg_split('/\r\n|\r|\n/', is_string($data) ? $data : '');
	$kept = array();
	$size = 0;
	for($index = count($lines) - 1; $index >= 0; $index--) {
		$line = trim($lines[$index]);
		if($line === '') continue;
		if(strpos($line, $prefix) === 0) $line = substr($line, strlen($prefix));
		$line = $prefix.plugin_safe_mode_text($line, 8192)."\n";
		if(strlen($line) > $max_bytes) $line = substr($line, 0, $max_bytes - 1)."\n";
		if($size + strlen($line) > $max_bytes) break;
		array_unshift($kept, $line);
		$size += strlen($line);
	}
	return implode('', $kept);
}

function plugin_safe_mode_log_backup_path($paths, $log_path) {
	foreach($paths['log_write_paths'] as $index=>$candidate) {
		if($candidate === $log_path && isset($paths['log_backup_paths'][$index])) return $paths['log_backup_paths'][$index];
	}
	return str_replace('\\', '/', dirname($log_path)).'/safe_mode.previous.php';
}

function plugin_safe_mode_append_log($paths, $message, &$written_path = '', $fingerprint = '', $options = array()) {
	$written_path = '';
	$options = is_array($options) ? $options : array();
	$fingerprint = is_string($fingerprint) && preg_match('/\A[a-f0-9]{64}\z/D', $fingerprint) === 1
		? $fingerprint
		: hash('sha256', (string)$message);
	$protected_message = plugin_safe_mode_log_prefix().plugin_safe_mode_text($message, 8192)."\n";
	$max_bytes = plugin_safe_mode_log_max_bytes();
	if(strlen($protected_message) > $max_bytes) $protected_message = substr($protected_message, 0, $max_bytes - 1)."\n";

	foreach($paths['log_write_paths'] as $path) {
		$handle = plugin_safe_mode_open_regular_file($path, TRUE);
		if(!is_resource($handle)) continue;
		if(!@flock($handle, LOCK_EX)) {
			@fclose($handle);
			continue;
		}
		if(!plugin_safe_mode_handle_matches_path($path, $handle)) {
			@flock($handle, LOCK_UN);
			@fclose($handle);
			continue;
		}
		$tail = plugin_safe_mode_read_log_tail($handle, $max_bytes * 2);
		$latest_fingerprint = plugin_safe_mode_latest_fingerprint_from_data($tail);
		$stat = @fstat($handle);
		if(!is_array($stat) || !isset($stat['size'])) {
			@flock($handle, LOCK_UN);
			@fclose($handle);
			continue;
		}
		$size = max(0, intval($stat['size']));
		$rotate = $size > $max_bytes || $size + strlen($protected_message) > $max_bytes;
		if($rotate) {
			$backup = plugin_safe_mode_bounded_protected_log($tail, $max_bytes);
			if($backup !== '' && !plugin_safe_mode_replace_log(plugin_safe_mode_log_backup_path($paths, $path), $backup)) {
				@flock($handle, LOCK_UN);
				@fclose($handle);
				continue;
			}
			@rewind($handle);
			if(!@ftruncate($handle, 0)) {
				@flock($handle, LOCK_UN);
				@fclose($handle);
				continue;
			}
			$size = 0;
		}
		$write_base_size = $size;
		if($latest_fingerprint === $fingerprint) {
			@fflush($handle);
			@flock($handle, LOCK_UN);
			@fclose($handle);
			$written_path = $path;
			return TRUE;
		}
		$expected_size = $write_base_size + strlen($protected_message);
		$write_ok = @fseek($handle, 0, SEEK_END) === 0
			&& plugin_safe_mode_write_all($handle, $protected_message, $options);
		$final_stat = $write_ok ? @fstat($handle) : FALSE;
		$write_ok = $write_ok
			&& is_array($final_stat)
			&& isset($final_stat['size'])
			&& intval($final_stat['size']) === $expected_size
			&& $expected_size <= $max_bytes;
		if(!$write_ok) {
			@ftruncate($handle, $write_base_size);
			@fflush($handle);
			@flock($handle, LOCK_UN);
			@fclose($handle);
			continue;
		}
		@flock($handle, LOCK_UN);
		@fclose($handle);
		$written_path = $path;
		return TRUE;
	}
	return FALSE;
}

function plugin_safe_mode_log_message($error, $fingerprint) {
	$error = is_array($error) ? $error : array();
	$message = isset($error['message']) ? $error['message'] : 'Unknown plugin or compiled runtime error';
	$file = isset($error['file']) ? $error['file'] : '';
	$line = isset($error['line']) ? intval($error['line']) : 0;
	$request_id = plugin_safe_mode_request_id();
	$request = $request_id === '' ? '' : ' request_id='.$request_id;
	return date('Y-m-d H:i:s').' FATAL fingerprint='.$fingerprint.$request.' : '
		.plugin_safe_mode_text($message, 4096).' in '.plugin_safe_mode_text($file, 2048).' on line '.$line;
}

function plugin_safe_mode_enable($conf, $app_path, &$error = '', &$marker_path = '', $lock_options = array()) {
	$error = '';
	$marker_path = '';
	$paths = plugin_safe_mode_paths($conf, $app_path);
	if(!plugin_safe_mode_lock_acquire($paths, $lock, $lock_path, $lock_options)) {
		$error = 'locked';
		return FALSE;
	}
	$enabled = FALSE;
	$unlocked = FALSE;
	try {
		$enabled = plugin_safe_mode_create_marker($paths, $marker_path);
	} finally {
		$unlocked = plugin_safe_mode_lock_release($lock, $lock_options);
	}
	if(!$enabled) {
		$error = 'marker_failed';
		return FALSE;
	}
	if(!$unlocked) {
		$error = 'unlock_failed';
		return FALSE;
	}
	return TRUE;
}

function plugin_safe_mode_activate($fatal_error, $conf, $app_path, &$operation_error = '', $lock_options = array()) {
	$operation_error = '';
	$paths = plugin_safe_mode_paths($conf, $app_path);
	if(!plugin_safe_mode_lock_acquire($paths, $lock, $lock_path, $lock_options)) {
		$operation_error = 'locked';
		return FALSE;
	}
	$marker_ready = FALSE;
	$log_ready = FALSE;
	$unlocked = FALSE;
	try {
		$marker_ready = plugin_safe_mode_create_marker($paths, $marker_path);
		$fingerprint = plugin_safe_mode_error_fingerprint($fatal_error);
		$log_ready = plugin_safe_mode_append_log($paths, plugin_safe_mode_log_message($fatal_error, $fingerprint), $written_path, $fingerprint);
	} finally {
		$unlocked = plugin_safe_mode_lock_release($lock, $lock_options);
	}
	if(!$marker_ready) $operation_error = 'marker_failed';
	elseif(!$log_ready) $operation_error = 'log_failed';
	elseif(!$unlocked) $operation_error = 'unlock_failed';
	return $marker_ready && $unlocked;
}

function plugin_safe_mode_handle_shutdown_error($error, $conf, $app_path) {
	if(!plugin_safe_mode_error_is_relevant($error, $conf, $app_path)) return FALSE;
	return plugin_safe_mode_activate($error, $conf, $app_path);
}

function plugin_safe_mode_handle_throwable($error, $conf, $app_path) {
	if(!($error instanceof Throwable)) return FALSE;
	return plugin_safe_mode_handle_shutdown_error(array(
		'type'=>E_ERROR,
		'message'=>$error->getMessage(),
		'file'=>$error->getFile(),
		'line'=>$error->getLine(),
	), $conf, $app_path);
}

function plugin_safe_mode_read_latest_error($path, $scan_bytes = 65536, $max_bytes = 2048) {
	$handle = plugin_safe_mode_open_regular_file($path, FALSE);
	if(!is_resource($handle)) return '';
	if(!@flock($handle, LOCK_SH)) {
		@fclose($handle);
		return '';
	}
	if(!plugin_safe_mode_handle_matches_path($path, $handle)) {
		@flock($handle, LOCK_UN);
		@fclose($handle);
		return '';
	}
	$stat = @fstat($handle);
	$size = is_array($stat) && isset($stat['size']) ? intval($stat['size']) : 0;
	$offset = max(0, $size - max(1024, intval($scan_bytes)));
	if($offset > 0 && @fseek($handle, $offset) !== 0) $offset = 0;
	$data = stream_get_contents($handle, max(1024, intval($scan_bytes)));
	@flock($handle, LOCK_UN);
	fclose($handle);
	if(!is_string($data) || $data === '') return '';
	$lines = preg_split('/\r\n|\r|\n/', $data);
	if($offset > 0 && count($lines) > 1) array_shift($lines);
	for($index = count($lines) - 1; $index >= 0; $index--) {
		if(trim($lines[$index]) === '') continue;
		$line = $lines[$index];
		$prefix = plugin_safe_mode_log_prefix();
		if(strpos($line, $prefix) === 0) $line = substr($line, strlen($prefix));
		return plugin_safe_mode_text($line, $max_bytes);
	}
	return '';
}

function plugin_safe_mode_status($conf, $app_path) {
	$paths = plugin_safe_mode_paths($conf, $app_path);
	$active_markers = plugin_safe_mode_active_markers($paths);
	$existing_logs = array();
	foreach($paths['log_paths'] as $index=>$path) {
		clearstatcache(TRUE, $path);
		$stat = @lstat($path);
		if(is_link($path) || !plugin_safe_mode_stat_is_regular($stat)) continue;
		$mtime = isset($stat['mtime']) ? intval($stat['mtime']) : 0;
		$existing_logs[] = array('path'=>$path, 'mtime'=>$mtime, 'index'=>$index);
	}
	usort($existing_logs, function($left, $right) {
		if($left['mtime'] === $right['mtime']) return $left['index'] - $right['index'];
		return $left['mtime'] > $right['mtime'] ? -1 : 1;
	});
	$log_path = !empty($existing_logs) ? $existing_logs[0]['path'] : $paths['log_paths'][0];
	$latest_error = '';
	foreach($existing_logs as $log) {
		$latest_error = plugin_safe_mode_read_latest_error($log['path']);
		if($latest_error !== '') {
			$log_path = $log['path'];
			break;
		}
	}

	return array(
		'active'=>!empty($active_markers),
		'marker_paths'=>$active_markers,
		'marker_path'=>!empty($active_markers) ? implode('; ', $active_markers) : $paths['marker_write_paths'][0],
		'lock_path'=>plugin_safe_mode_operation_lock_paths($paths)[0],
		'log_path'=>$log_path,
		'latest_error'=>$latest_error,
	);
}

function plugin_safe_mode_clear_markers($conf, $app_path, &$failed_paths = array(), $unlink_callback = NULL) {
	$failed_paths = array();
	$paths = plugin_safe_mode_paths($conf, $app_path);
	foreach($paths['marker_paths'] as $path) {
		if(!is_file($path)) continue;
		$result = is_callable($unlink_callback) ? call_user_func($unlink_callback, $path) : @unlink($path);
		clearstatcache(TRUE, $path);
		if($result === FALSE || is_file($path)) $failed_paths[] = $path;
	}
	foreach(plugin_safe_mode_active_markers($paths) as $path) $failed_paths[] = $path;
	$failed_paths = array_values(array_unique($failed_paths));
	return empty($failed_paths);
}

function plugin_safe_mode_exit($conf, $app_path, &$error = '', &$failed_paths = array(), $unlink_callback = NULL, $lock_options = array()) {
	$error = '';
	$failed_paths = array();
	$paths = plugin_safe_mode_paths($conf, $app_path);
	if(!plugin_safe_mode_lock_acquire($paths, $lock, $lock_path, $lock_options)) {
		$error = 'locked';
		return FALSE;
	}
	$cleared = FALSE;
	$unlocked = FALSE;
	try {
		$cleared = plugin_safe_mode_clear_markers($conf, $app_path, $failed_paths, $unlink_callback);
	} finally {
		$unlocked = plugin_safe_mode_lock_release($lock, $lock_options);
	}
	if(!$cleared) {
		$error = 'clear_failed';
		return FALSE;
	}
	if(!$unlocked) {
		$error = 'unlock_failed';
		return FALSE;
	}
	return TRUE;
}

?>
