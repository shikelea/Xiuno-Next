<?php

// hook model_diagnostic_start.php

function diagnostic_path_absolute($path) {
	$path = (string)$path;
	if($path === '') return FALSE;
	if($path[0] === '/' || $path[0] === '\\') return TRUE;
	return preg_match('/\A[A-Za-z]:[\\\\\/]/D', $path) === 1;
}

function diagnostic_runtime_path($path, $app_path) {
	$path = (string)$path;
	$app_path = rtrim((string)$app_path, '/\\').DIRECTORY_SEPARATOR;
	if($path === '') return $app_path;
	if(diagnostic_path_absolute($path)) return $path;
	if(substr($path, 0, 2) === './' || substr($path, 0, 2) === '.\\') $path = substr($path, 2);
	return $app_path.ltrim($path, '/\\');
}

function diagnostic_storage_spaces($conf, $app_path, $disk_free_probe = NULL) {
	$conf = is_array($conf) ? $conf : array();
	$paths = array(
		'app_path'=>(string)$app_path,
		'upload_path'=>isset($conf['upload_path']) ? $conf['upload_path'] : 'upload/',
		'tmp_path'=>isset($conf['tmp_path']) ? $conf['tmp_path'] : 'tmp/',
		'log_path'=>isset($conf['log_path']) ? $conf['log_path'] : 'log/',
	);
	if($disk_free_probe === NULL && function_exists('disk_free_space')) $disk_free_probe = 'disk_free_space';

	$result = array();
	foreach($paths as $key=>$configured_path) {
		$path = diagnostic_runtime_path($configured_path, $app_path);
		$real_path = realpath($path);
		$probe_path = $real_path === FALSE ? $path : $real_path;
		$free_bytes = FALSE;
		if(is_callable($disk_free_probe)) $free_bytes = @call_user_func($disk_free_probe, $probe_path);
		$result[$key] = array(
			'key'=>$key,
			'path'=>$probe_path,
			'exists'=>is_dir($path),
			'writable'=>is_dir($path) && is_writable($path),
			'free_bytes'=>is_numeric($free_bytes) && $free_bytes >= 0 ? (float)$free_bytes : FALSE,
		);
	}
	return $result;
}

// hook model_diagnostic_end.php

?>
