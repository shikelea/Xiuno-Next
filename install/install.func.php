<?php

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
	$env['php_version']['need'] = '5.0';
	$env['php_version']['status'] = version_compare(PHP_VERSION , '5') > 0;

	// 目录可写（使用绝对路径，避免 CWD 不一致导致检测失败）
	$writedir = array(
		'../conf/'   => APP_PATH . 'conf/',
		'../log/'    => APP_PATH . 'log/',
		'../tmp/'    => APP_PATH . 'tmp/',
		'../upload/' => APP_PATH . 'upload/',
		'../plugin/' => APP_PATH . 'plugin/',
	);

	$write = array();
	foreach($writedir as $label => $dir) {
		$write[$label] = xn_is_writable($dir);
	}
}

function install_sql_file($sqlfile) {
	global $errno, $errstr;
	$s = file_get_contents($sqlfile);
	if ($s === false) {
		message(-1, "Failed to read SQL file: $sqlfile");
	}
	$s = str_replace(array("\r\n", "\r"), "\n", $s);
	// Remove comments starting with #
	$s = preg_replace('/^#.*$/m', '', $s);
	
	$arr = explode(";\n", $s);
	foreach ($arr as $i => $sql) {
		$sql = trim($sql);
		if(empty($sql)) continue;
		try {
			// Check if $sql is valid string before exec
			if (!is_string($sql)) {
				continue;
			}
			
			// 某些 SQL 语句可能包含 USE `dbname`; 这种语句在 db_exec 中可能会有问题，或者不需要执行
			if (strncasecmp($sql, 'USE ', 4) === 0) {
				continue;
			}

			if(db_exec($sql) === FALSE) {
				message(-1, "sql: $sql, errno: $errno, errstr: $errstr");
			}
		} catch (Exception $e) {
			message(-1, "sql exception: " . $e->getMessage());
		} catch (Error $e) {
			message(-1, "sql fatal error: " . $e->getMessage());
		}
	}
}



?>