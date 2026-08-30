<?php

function xn_install_state_nonempty_string($value) {
	return is_string($value) && trim($value) !== '';
}

// Validate only the shape required to enter the runtime safely. This is deliberately not a
// connectivity check: an unavailable database must remain a runtime diagnostic, while malformed
// local configuration must never be mistaken for a completed installation.
function xn_install_state_config_valid($config) {
	if(!is_array($config) || empty($config)) return FALSE;
	if(!isset($config['installed']) || !in_array($config['installed'], array(1, '1', TRUE), TRUE)) return FALSE;
	if(!xn_install_state_nonempty_string(isset($config['lang']) ? $config['lang'] : NULL)
		|| !preg_match('/\A[a-z0-9][a-z0-9_-]*\z/iD', $config['lang'])) {
		return FALSE;
	}
	foreach(array('log_path', 'tmp_path', 'upload_path', 'auth_key') as $key) {
		if(!xn_install_state_nonempty_string(isset($config[$key]) ? $config[$key] : NULL)) return FALSE;
	}

	if(!isset($config['db']) || !is_array($config['db'])) return FALSE;
	$type = isset($config['db']['type']) ? $config['db']['type'] : NULL;
	if(!is_string($type) || !in_array($type, array('mysql', 'pdo_mysql', 'pdo_sqlite'), TRUE)) return FALSE;
	if(!isset($config['db'][$type]) || !is_array($config['db'][$type])) return FALSE;
	$driver = $config['db'][$type];
	if(!isset($driver['master']) || !is_array($driver['master'])) return FALSE;
	foreach(array('host', 'user', 'password', 'name', 'tablepre', 'charset', 'engine') as $key) {
		if(!array_key_exists($key, $driver['master']) || !is_string($driver['master'][$key])) return FALSE;
	}
	foreach(array('host', 'name', 'tablepre') as $key) {
		if(trim($driver['master'][$key]) === '') return FALSE;
	}
	if(isset($driver['slaves']) && !is_array($driver['slaves'])) return FALSE;
	return TRUE;
}

// Shared by the front entry and installer so incomplete installation states have one meaning.
function xn_install_state_inspect($config_file, $lock_file) {
	$config_present = is_string($config_file) && (file_exists($config_file) || is_link($config_file));
	$lock_present = is_string($lock_file) && (file_exists($lock_file) || is_link($lock_file));
	if(!$config_present) {
		return array(
			'state'=>$lock_present ? 'lock-only' : 'missing',
			'config'=>NULL,
			'config_present'=>FALSE,
			'lock_present'=>$lock_present,
		);
	}

	$config = FALSE;
	if(is_file($config_file) && !is_link($config_file)) {
		try {
			$config = (static function($file) { return @include $file; })($config_file);
		} catch(Throwable $e) {
			$config = FALSE;
		}
	}
	$valid = xn_install_state_config_valid($config);

	return array(
		'state'=>$valid ? 'valid' : 'present-invalid',
		'config'=>$valid ? $config : NULL,
		'config_present'=>TRUE,
		'lock_present'=>$lock_present,
	);
}

function xn_install_state_diagnostic($state) {
	if($state === 'present-invalid') {
		return array(
			'title'=>'Configuration cannot be loaded / 配置无法加载',
			'message'=>'conf/conf.php exists but is not a complete readable Xiuno configuration. Restore a known-good backup, or back up and move the invalid file aside before reopening the installer. The application will not overwrite it automatically. / conf/conf.php 已存在，但不是可读取的完整 Xiuno 配置。请恢复有效备份，或先备份并移走无效文件，再重新打开安装器；程序不会自动覆盖。',
		);
	}
	if($state === 'lock-only') {
		return array(
			'title'=>'Installation lock without configuration / 仅存在安装锁',
			'message'=>'conf/.installed.lock exists but conf/conf.php is missing. Restore the missing configuration. If installation never completed, inspect and back up the lock before removing it manually, then reopen the installer. / conf/.installed.lock 存在，但 conf/conf.php 缺失。请恢复配置；若安装从未完成，请先检查并备份锁文件，再手动移除，然后重新打开安装器。',
		);
	}
	return array(
		'title'=>'Installation state error / 安装状态错误',
		'message'=>'The installation state is inconsistent and requires manual inspection. / 安装状态不一致，需要人工检查。',
	);
}
