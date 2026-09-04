<?php

function install_language_options() {
	return array('zh-cn', 'zh-tw', 'en-us', 'ru-ru', 'th-th');
}

function install_language_normalize($language, $fallback = 'zh-cn') {
	$options = install_language_options();
	$fallback = is_string($fallback) && in_array($fallback, $options, TRUE) ? $fallback : 'zh-cn';
	if(!is_string($language)) return $fallback;
	$language = strtolower(trim($language));
	return in_array($language, $options, TRUE) ? $language : $fallback;
}

// Resolve the installer locale before loading any language file. POST is used only for the
// language-selection form; later steps reuse the validated HttpOnly cookie.
function install_language_resolve($fallback = 'zh-cn') {
	if(isset($_POST['lang'])) return install_language_normalize($_POST['lang'], $fallback);
	if(isset($_COOKIE['lang'])) return install_language_normalize($_COOKIE['lang'], $fallback);
	return install_language_normalize($fallback);
}

// Compose advertises only a non-secret deployment profile. Never copy a database password from
// process environment into the unauthenticated installer HTML.
function install_db_form_defaults($profile = NULL) {
	if($profile === NULL) $profile = getenv('XIUNO_INSTALL_PROFILE');
	$profile = is_string($profile) ? strtolower(trim($profile)) : '';
	if($profile === 'docker') {
		return array('host'=>'db', 'name'=>'xiunobbs', 'user'=>'xiuno');
	}
	return array('host'=>'127.0.0.1', 'name'=>'xiunobbs', 'user'=>'root');
}

function install_secure_random_hex($bytes = 32) {
	$bytes = intval($bytes);
	if($bytes < 1 || $bytes > 64) return FALSE;
	try {
		return bin2hex(random_bytes($bytes));
	} catch(Throwable $e) {
		return FALSE;
	}
}

function install_config_read($file) {
	if(!is_string($file) || !is_file($file) || is_link($file)) return FALSE;
	try {
		$config = include $file;
	} catch(Throwable $e) {
		return FALSE;
	}
	return is_array($config) ? $config : FALSE;
}

function install_config_matches($config, $replace) {
	if(!is_array($config) || !is_array($replace)) return FALSE;
	foreach($replace as $key=>$value) {
		if(!array_key_exists($key, $config) || $config[$key] !== $value) return FALSE;
	}
	return TRUE;
}

function install_record_update_verified($write_result, $record, $expected) {
	return $write_result !== FALSE && install_config_matches($record, $expected);
}

function install_file_write_exclusive($file, $content) {
	if(!is_string($file) || $file === '' || !is_string($content)) return FALSE;
	$old_umask = umask(0077);
	$handle = @fopen($file, 'xb');
	umask($old_umask);
	if($handle === FALSE) return FALSE;

	$ok = FALSE;
	try {
		if(!flock($handle, LOCK_EX)) return FALSE;
		$length = strlen($content);
		$offset = 0;
		while($offset < $length) {
			$written = fwrite($handle, substr($content, $offset));
			if($written === FALSE || $written === 0) return FALSE;
			$offset += $written;
		}
		if(!fflush($handle)) return FALSE;
		$ok = TRUE;
	} finally {
		flock($handle, LOCK_UN);
		fclose($handle);
		if(!$ok && (is_file($file) || is_link($file))) @unlink($file);
	}
	return TRUE;
}

function install_config_backup_path($target) {
	$dir = dirname($target);
	$name = basename($target);
	$dot = strrpos($name, '.');
	if($dot === FALSE) return $dir.'/'.$name.'.backup';
	return $dir.'/'.substr($name, 0, $dot).'.backup'.substr($name, $dot);
}

function install_config_stage_track($record) {
	global $g_install_config_stages, $g_install_config_stage_shutdown_registered;
	if(!isset($g_install_config_stages) || !is_array($g_install_config_stages)) $g_install_config_stages = array();
	if(empty($g_install_config_stage_shutdown_registered)) {
		register_shutdown_function('install_config_stage_cleanup');
		$g_install_config_stage_shutdown_registered = TRUE;
	}
	$key = $record['temp'];
	$g_install_config_stages[$key] = $record;
	return $key;
}

function install_config_stage_cleanup($stage_key = NULL) {
	global $g_install_config_stages;
	if(empty($g_install_config_stages) || !is_array($g_install_config_stages)) return TRUE;
	if($stage_key === NULL) {
		$ok = TRUE;
		foreach(array_keys($g_install_config_stages) as $key) {
			install_config_stage_cleanup($key) || $ok = FALSE;
		}
		return $ok;
	}
	if(empty($g_install_config_stages[$stage_key])) return TRUE;

	$record = $g_install_config_stages[$stage_key];
	$ok = TRUE;
	foreach(array('probe', 'temp') as $path_key) {
		$path = isset($record[$path_key]) ? $record[$path_key] : '';
		if($path !== '' && (is_file($path) || is_link($path)) && !@unlink($path)) $ok = FALSE;
	}
	if(!$ok) return FALSE;
	unset($g_install_config_stages[$stage_key]);
	return TRUE;
}

// The writer is injectable only so short or invalid writes can exercise the publication boundary
// without changing the process-wide filesystem functions used by production.
function install_config_stage_begin($source, $target, $replace, $operations = array()) {
	if(!is_array($replace)) return array('ok'=>FALSE, 'error'=>'Installer configuration values are invalid.');
	if(!is_file($source) || is_link($source)) return array('ok'=>FALSE, 'error'=>'Installer default configuration is unavailable.');
	$backup = install_config_backup_path($target);
	$existing_stages = glob($target.'.install-*.tmp');
	if(file_exists($target) || is_link($target) || file_exists($backup) || is_link($backup)
		|| (is_array($existing_stages) && !empty($existing_stages))) {
		return array('ok'=>FALSE, 'error'=>'Installer configuration target is not clean.');
	}

	$defaults = install_config_read($source);
	if($defaults === FALSE) return array('ok'=>FALSE, 'error'=>'Installer default configuration is invalid.');
	$config = array_merge($defaults, $replace);
	$content = "<?php\r\nreturn ".var_export($config, TRUE).";\r\n";
	$suffix = install_secure_random_hex(8);
	if($suffix === FALSE) return array('ok'=>FALSE, 'error'=>'Unable to generate secure installer state.');
	$stage_base = $target.'.install-'.getmypid().'-'.$suffix;
	$temp = $stage_base.'.tmp';
	$probe = $stage_base.'.probe.tmp';
	$record = array(
		'temp'=>$temp,
		'probe'=>$probe,
		'target'=>$target,
		'backup'=>$backup,
		'config'=>$config,
	);
	$stage_key = install_config_stage_track($record);
	$write = isset($operations['write']) ? $operations['write'] : 'install_file_write_exclusive';
	if(!is_callable($write) || call_user_func($write, $temp, $content) !== TRUE) {
		install_config_stage_cleanup($stage_key);
		return array('ok'=>FALSE, 'error'=>'Unable to stage the installer configuration.');
	}
	$staged = install_config_read($temp);
	if($staged !== $config) {
		install_config_stage_cleanup($stage_key);
		return array('ok'=>FALSE, 'error'=>'Unable to validate the staged installer configuration.');
	}
	// Prove hard-link publication works on this exact directory before any database side effect.
	// Both names are owned and tracked so shutdown can clean an interruption inside this probe.
	if(!@link($temp, $probe) || !@unlink($probe)) {
		install_config_stage_cleanup($stage_key);
		return array('ok'=>FALSE, 'error'=>'This filesystem cannot atomically publish the installer configuration.');
	}
	return array('ok'=>TRUE, 'key'=>$stage_key, 'temp'=>$temp, 'target'=>$target, 'error'=>'');
}

function install_config_stage_abort($stage) {
	$stage_key = is_array($stage) && isset($stage['key']) ? $stage['key'] : $stage;
	return is_string($stage_key) ? install_config_stage_cleanup($stage_key) : FALSE;
}

function install_config_stage_commit($stage, $operations = array()) {
	global $g_install_config_stages;
	$stage_key = is_array($stage) && isset($stage['key']) ? $stage['key'] : '';
	if($stage_key === '' || empty($g_install_config_stages[$stage_key])) return FALSE;
	$record = $g_install_config_stages[$stage_key];
	if(!isset($record['config']) || install_config_read($record['temp']) !== $record['config']) {
		install_config_stage_cleanup($stage_key);
		return FALSE;
	}
	if(file_exists($record['target']) || is_link($record['target']) || file_exists($record['backup']) || is_link($record['backup'])) {
		install_config_stage_cleanup($stage_key);
		return FALSE;
	}
	// A same-directory hard link is the portable no-clobber publication primitive available here:
	// unlike POSIX rename(), it atomically fails if a target appears after the preflight check.
	$publish = isset($operations['link']) ? $operations['link'] : 'link';
	if(!is_callable($publish)) {
		install_config_stage_cleanup($stage_key);
		return FALSE;
	}
	if(@call_user_func($publish, $record['temp'], $record['target']) !== TRUE) {
		install_config_stage_cleanup($stage_key);
		return FALSE;
	}
	// Publication is now committed. Failure to unlink the second name must never remove or report
	// failure for the valid target; retained ownership lets shutdown cleanup retry the staging name.
	if(@unlink($record['temp'])) unset($g_install_config_stages[$stage_key]);
	clearstatcache(TRUE, $record['target']);
	return TRUE;
}

function install_directory_prepare($dir, $mode = 0777, $mkdir = NULL) {
	clearstatcache(TRUE, $dir);
	if(is_dir($dir)) return TRUE;
	if(file_exists($dir) || is_link($dir)) return FALSE;
	$mkdir = $mkdir === NULL ? 'xn_mkdir' : $mkdir;
	if(!is_callable($mkdir)) return FALSE;
	call_user_func($mkdir, $dir, $mode, TRUE);
	clearstatcache(TRUE, $dir);
	return is_dir($dir);
}

function install_required_extensions() {
	return array(
		'json'=>'JSON',
		'openssl'=>'OpenSSL',
		'pdo'=>'PDO',
		'pdo_mysql'=>'PDO MySQL',
		'mbstring'=>'Mbstring',
		'gd'=>'GD',
		'zip'=>'Zip',
	);
}

function install_required_writable_directories($app_path = NULL) {
	if($app_path === NULL) $app_path = APP_PATH;
	$app_path = rtrim(str_replace('\\', '/', (string)$app_path), '/').'/';
	return array(
		'../conf/'   => $app_path.'conf/',
		'../log/'    => $app_path.'log/',
		'../tmp/'    => $app_path.'tmp/',
		'../upload/' => $app_path.'upload/',
	);
}

function get_env(&$env, &$write) {
	$env['os']['name'] = lang('os');
	$env['os']['must'] = TRUE;
	$env['os']['current'] = PHP_OS;
	$env['os']['need'] = lang('unix_like');
	$env['os']['status'] = 1;
	// glob gzip
	//$env['os']['disable'] = 1;
	
	$env['php_version']['name'] = lang('php_version');
	$env['php_version']['must'] = TRUE;
	$env['php_version']['current'] = PHP_VERSION;
	$env['php_version']['need'] = '8.0';
	$env['php_version']['status'] = version_compare(PHP_VERSION , '8.0.0') >= 0;

	foreach(install_required_extensions() as $extension=>$label) {
		$key = 'php_'.$extension;
		$env[$key]['name'] = $extension === 'openssl' ? lang('php_openssl') : $label;
		$env[$key]['must'] = TRUE;
		$env[$key]['current'] = extension_loaded($extension) ? lang('enabled') : lang('disabled');
		$env[$key]['need'] = lang('enabled');
		$env[$key]['status'] = extension_loaded($extension);
	}

	// 目录可写（使用绝对路径，避免 CWD 不一致导致检测失败）
	$writedir = install_required_writable_directories();

	$write = array();
	foreach($writedir as $label => $dir) {
		if(!is_dir($dir)) @mkdir($dir, 0777, TRUE);
		$write[$label] = xn_is_writable($dir);
	}
}

function install_requirements_check() {
	$env = $write = array();
	get_env($env, $write);
	$errors = array();
	foreach($env as $item) {
		if(!empty($item['must']) && empty($item['status'])) {
			$errors[] = $item['name'].': required '.$item['need'].', current '.$item['current'];
		}
	}
	foreach($write as $path=>$writable) {
		if(!$writable) $errors[] = $path.': directory is not writable';
	}
	return array('ok'=>empty($errors), 'errors'=>$errors, 'env'=>$env, 'write'=>$write);
}

function install_core_table_names($tablepre) {
	$tablepre = (string)$tablepre;
	$tables = array(
		'user', 'group', 'forum', 'forum_access', 'thread', 'thread_top', 'post', 'attach',
		'mythread', 'mypost', 'session', 'session_data', 'modlog', 'kv', 'cache', 'queue', 'mail_outbox', 'table_day',
	);
	$return = array();
	foreach($tables as $table) $return[] = $tablepre.$table;
	return $return;
}

// A MySQL advisory lock serializes Xiuno installers that target the same database and prefix.
// It is held on the same PDO connection later used by db_exec(), and shutdown releases it for
// message()/exit and exception paths.
function install_db_advisory_lock_start($db) {
	global $g_install_db_advisory_lock;
	if(!empty($g_install_db_advisory_lock)) {
		return array('ok'=>FALSE, 'error'=>'The target database install lock is already held by this request.');
	}
	if(!is_object($db)) {
		return array('ok'=>FALSE, 'error'=>'Unable to use the installer database connection for a safety lock.');
	}
	$tablepre = isset($db->tablepre) ? (string)$db->tablepre : '';
	if(!preg_match('/^[A-Za-z0-9_]{1,32}$/D', $tablepre)) {
		return array('ok'=>FALSE, 'error'=>'The configured database table prefix is invalid.');
	}
	try {
		// The database may have been created moments earlier after the first connection returned 1049.
		// Reconnect through the DB object so the lock and all following DDL share its write connection.
		if(!isset($db->wlink) || !($db->wlink instanceof PDO)) {
			if(!is_callable(array($db, 'connect_master'))) {
				return array('ok'=>FALSE, 'error'=>'Unable to use the installer database connection for a safety lock.');
			}
			$db->connect_master();
		}
		if(!isset($db->wlink) || !($db->wlink instanceof PDO)) {
			return array('ok'=>FALSE, 'error'=>'Unable to use the installer database connection for a safety lock.');
		}
		$link = $db->wlink;
		$database = $link->query('SELECT DATABASE()')->fetchColumn();
		if(!is_string($database) || $database === '') {
			return array('ok'=>FALSE, 'error'=>'Unable to identify the target database before installation.');
		}
		$lock_name = 'xiuno_install_'.substr(hash('sha256', $database."\0".$tablepre), 0, 40);
		$stmt = $link->prepare('SELECT GET_LOCK(?, 0)');
		if(!$stmt || !$stmt->execute(array($lock_name)) || intval($stmt->fetchColumn()) !== 1) {
			return array('ok'=>FALSE, 'error'=>'Another installer is already using the target database.');
		}
		$stmt->closeCursor();
		$g_install_db_advisory_lock = array('link'=>$link, 'name'=>$lock_name);
		register_shutdown_function('install_db_advisory_lock_end');
		return array('ok'=>TRUE, 'error'=>'');
	} catch(Throwable $e) {
		return array('ok'=>FALSE, 'error'=>'Unable to acquire the target database safety lock.');
	}
}

function install_db_advisory_lock_end() {
	global $g_install_db_advisory_lock;
	if(empty($g_install_db_advisory_lock) || !is_array($g_install_db_advisory_lock)) return TRUE;
	$guard = $g_install_db_advisory_lock;
	$g_install_db_advisory_lock = NULL;
	try {
		$stmt = $guard['link']->prepare('SELECT RELEASE_LOCK(?)');
		if(!$stmt || !$stmt->execute(array($guard['name']))) return FALSE;
		$released = $stmt->fetchColumn();
		$stmt->closeCursor();
		return intval($released) === 1;
	} catch(Throwable $e) {
		return FALSE;
	}
}

function install_target_database_probe($db) {
	if(!is_object($db) || !isset($db->wlink) || !($db->wlink instanceof PDO)) {
		return array('ok'=>FALSE, 'found'=>array(), 'error'=>'Unable to inspect the target database safely.');
	}
	$tablepre = isset($db->tablepre) ? (string)$db->tablepre : '';
	if(!preg_match('/^[A-Za-z0-9_]{1,32}$/D', $tablepre)) {
		return array('ok'=>FALSE, 'found'=>array(), 'error'=>'The configured database table prefix is invalid.');
	}
	$tables = install_core_table_names($tablepre);
	$placeholders = implode(',', array_fill(0, count($tables), '?'));
	try {
		$stmt = $db->wlink->prepare(
			'SELECT TABLE_NAME FROM information_schema.TABLES '
			.'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('.$placeholders.') ORDER BY TABLE_NAME'
		);
		if(!$stmt || !$stmt->execute($tables)) {
			return array('ok'=>FALSE, 'found'=>array(), 'error'=>'Unable to inspect the target database safely.');
		}
		$found = $stmt->fetchAll(PDO::FETCH_COLUMN);
		$stmt->closeCursor();
		if(!is_array($found)) {
			return array('ok'=>FALSE, 'found'=>array(), 'error'=>'Unable to inspect the target database safely.');
		}
		return array('ok'=>TRUE, 'found'=>$found, 'error'=>'');
	} catch(Throwable $e) {
		return array('ok'=>FALSE, 'found'=>array(), 'error'=>'Unable to inspect the target database safely.');
	}
}

// Operations are injectable only so the safety boundary can be exercised without a live database.
// Production callers use the defaults below.
function install_database_prepare($db, $sqlfile, $operations = array()) {
	$lock_start = isset($operations['lock_start']) ? $operations['lock_start'] : 'install_db_advisory_lock_start';
	$probe = isset($operations['probe']) ? $operations['probe'] : 'install_target_database_probe';
	$ddl = isset($operations['ddl']) ? $operations['ddl'] : 'install_sql_file';
	if(!is_callable($lock_start) || !is_callable($probe) || !is_callable($ddl)) {
		return array('ok'=>FALSE, 'error'=>'Installer database safety operations are unavailable.');
	}

	$lock_result = call_user_func($lock_start, $db);
	if(!is_array($lock_result) || empty($lock_result['ok'])) {
		$error = is_array($lock_result) && !empty($lock_result['error']) ? $lock_result['error'] : 'Unable to lock the target database safely.';
		return array('ok'=>FALSE, 'error'=>$error);
	}
	$probe_result = call_user_func($probe, $db);
	if(!is_array($probe_result) || empty($probe_result['ok'])) {
		$error = is_array($probe_result) && !empty($probe_result['error']) ? $probe_result['error'] : 'Unable to inspect the target database safely.';
		return array('ok'=>FALSE, 'error'=>$error);
	}
	if(!array_key_exists('found', $probe_result) || !is_array($probe_result['found'])) {
		return array('ok'=>FALSE, 'error'=>'Unable to inspect the target database safely.');
	}
	$found = $probe_result['found'];
	if(!empty($found)) {
		return array(
			'ok'=>FALSE,
			'error'=>'Installation stopped because Xiuno core tables already exist: '.implode(', ', $found).'. Use an empty database, or keep the existing site and run the upgrade command.',
		);
	}

	$ddl_result = call_user_func($ddl, $sqlfile);
	if(!is_array($ddl_result) || empty($ddl_result['ok'])) {
		$error = is_array($ddl_result) && !empty($ddl_result['error'])
			? $ddl_result['error']
			: 'Unable to initialize the target database schema. The dedicated target database may contain a partial schema; empty it before retrying.';
		return array('ok'=>FALSE, 'error'=>$error);
	}
	return array('ok'=>TRUE, 'error'=>'');
}

function install_sql_file($sqlfile) {
	global $errno, $errstr;
	$s = @file_get_contents($sqlfile);
	if ($s === false) {
		return array('ok'=>FALSE, 'error'=>'Unable to read the installer schema file.');
	}
	$s = str_replace(array("\r\n", "\r"), "\n", $s);
	// Remove comments starting with #
	$s = preg_replace('/^#.*$/m', '', $s);
	
	$arr = explode(";\n", $s);
	$statement_number = 0;
	foreach ($arr as $i => $sql) {
		$sql = trim($sql);
		if(empty($sql)) continue;
		$statement_number++;
		try {
			// 某些 SQL 语句可能包含 USE `dbname`; 这种语句在 db_exec 中可能会有问题，或者不需要执行
			if (strncasecmp($sql, 'USE ', 4) === 0) {
				continue;
			}

			if(db_exec($sql) === FALSE) {
				return array(
					'ok'=>FALSE,
					'error'=>'Schema statement #'.$statement_number.' failed (database error '.intval($errno).'). The dedicated target database may contain a partial schema; empty it before retrying.',
				);
			}
		} catch (Throwable $e) {
			return array(
				'ok'=>FALSE,
				'error'=>'Schema statement #'.$statement_number.' raised an error. The dedicated target database may contain a partial schema; empty it before retrying.',
			);
		}
	}
	return array('ok'=>TRUE, 'error'=>'', 'statements'=>$statement_number);
}



?>
