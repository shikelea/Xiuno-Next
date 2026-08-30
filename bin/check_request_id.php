<?php

$root = realpath(__DIR__.'/..');
if($root === FALSE) {
	fwrite(STDERR, "FAIL: unable to locate repository root.\n");
	exit(1);
}
$root = str_replace('\\', '/', $root).'/';
$errors = array();
$server = NULL;
$server_pipes = array();
$index_source = file_get_contents($root.'index.php');
$request_source = file_get_contents($root.'xiunophp/request.func.php');
$merge_source = file_get_contents($root.'tool/merge.php');
$message_templates = array(
	'front'=>file_get_contents($root.'view/htm/message.htm'),
	'admin'=>file_get_contents($root.'admin/view/htm/message.htm'),
	'installer'=>file_get_contents($root.'install/view/htm/message.htm'),
);
if(!is_string($index_source) || !is_string($request_source) || !is_string($merge_source)
	|| in_array(FALSE, $message_templates, TRUE)) {
	fwrite(STDERR, "Request ID guard failed: unable to read request bootstrap sources.\n");
	exit(1);
}
$request_load = strpos($index_source, "require_once XIUNOPHP_PATH . 'request.func.php';");
$request_init = strpos($index_source, 'xn_request_id_init();', $request_load === FALSE ? 0 : $request_load);
$install_state_load = strpos($index_source, "require_once APP_PATH . 'install/install-state.func.php';");
if($request_load === FALSE || $request_init === FALSE || $install_state_load === FALSE
	|| !($request_load < $request_init && $request_init < $install_state_load)) {
	fwrite(STDERR, "Request ID guard failed: the front controller must initialize correlation before installation-state failures.\n");
	exit(1);
}
substr_count($index_source, 'xn_request_id_support_html()') >= 2
	|| $errors[] = 'front-controller 503 HTML responses do not expose their correlation ID';
strpos($index_source, "'request_id'=>xn_request_id_current()") !== FALSE
	|| $errors[] = 'front-controller database outage JSON does not expose its correlation ID';
strpos($request_source, "\$_SERVER['HTTP_X_REQUEST_ID']") === FALSE
	|| $errors[] = 'request bootstrap reads the client-supplied request ID';
strpos($merge_source, "xiuno_strip_file(\$dir.'request.func.php')") !== FALSE
	|| $errors[] = 'generated XiunoPHP bundle does not inline the early request bootstrap';
strpos($merge_source, "if(!function_exists('xn_runtime_is_command'))") !== FALSE
	|| $errors[] = 'generated XiunoPHP bundle does not guard an already preloaded request bootstrap';
foreach($message_templates as $surface=>$template) {
	strpos($template, 'intval($code) !== 0') !== FALSE
		|| $errors[] = $surface.' message page does not limit correlation details to failures';
	strpos($template, 'xn_request_id_support_html()') !== FALSE
		|| $errors[] = $surface.' message page does not expose the request ID for support';
}

function request_id_path_identity($path) {
	$path = str_replace('\\', '/', (string)$path);
	$path = rtrim($path, '/');
	return DIRECTORY_SEPARATOR === '\\' ? strtolower($path) : $path;
}

function request_id_remove_fixture($path) {
	$system_tmp = realpath(sys_get_temp_dir());
	$fixture = realpath($path);
	if($system_tmp === FALSE || $fixture === FALSE) return FALSE;
	if(request_id_path_identity(dirname($fixture)) !== request_id_path_identity($system_tmp)) return FALSE;
	if(strpos(basename($fixture), 'xiuno-request-id-') !== 0) return FALSE;

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($fixture, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach($iterator as $item) {
		$pathname = $item->getPathname();
		if($item->isLink() || !$item->isDir()) {
			@unlink($pathname);
		} else {
			@rmdir($pathname);
		}
	}
	return @rmdir($fixture);
}

function request_id_stop_server(&$process, &$pipes) {
	if(is_resource($process)) {
		$status = proc_get_status($process);
		if(!empty($status['running'])) @proc_terminate($process);
		$deadline = microtime(TRUE) + 2.0;
		do {
			$status = proc_get_status($process);
			if(empty($status['running'])) break;
			usleep(50000);
		} while(microtime(TRUE) < $deadline);
		if(!empty($status['running'])) @proc_terminate($process, 9);
	}
	foreach($pipes as $pipe) {
		if(is_resource($pipe)) fclose($pipe);
	}
	$pipes = array();
	if(is_resource($process)) @proc_close($process);
	$process = NULL;
}

function request_id_fixture_source($root, $fixture, $bundle, $name, $preload = FALSE) {
	$include = $root.'xiunophp/'.($bundle ? 'xiunophp.min.php' : 'xiunophp.php');
	$channel = 'request_id_'.$name;
	return "<?php\n"
		."define('DEBUG', 0);\n"
		."define('APP_PATH', ".var_export($fixture.'/', TRUE).");\n"
		."define('XIUNOPHP_PATH', ".var_export($root.'xiunophp/', TRUE).");\n"
		."if(PHP_SAPI === 'cli') \$_SERVER['HTTP_X_REQUEST_ID'] = str_repeat('c', 32);\n"
		."\$conf = array(\n"
		."\t'db'=>array(),\n"
		."\t'cache'=>array('enable'=>FALSE),\n"
		."\t'tmp_path'=>".var_export($fixture.'/tmp/', TRUE).",\n"
		."\t'log_path'=>".var_export($fixture.'/log/', TRUE).",\n"
		."\t'timezone'=>'UTC',\n"
		.");\n"
		.($preload
			? "require_once ".var_export($root.'xiunophp/request.func.php', TRUE).";\n xn_request_id_init();\n"
			: '')
		."include ".var_export($include, TRUE).";\n"
		."\$invalid = IN_CMD && isset(\$argv[1]) && \$argv[1] === '--invalid-log-id';\n"
		."if(\$invalid) \$_SERVER['request_id'] = \"forged\\trequest\\nid\";\n"
		."\$channel = ".var_export($channel, TRUE).".(IN_CMD ? '_cli' : '_web').(\$invalid ? '_invalid' : '');\n"
		."XiunoLogger::error('request id behavior smoke', array(), \$channel);\n"
		."if(!IN_CMD) header('Content-Type: application/json; charset=UTF-8');\n"
		."echo json_encode(array(\n"
		."\t'request_id'=>isset(\$_SERVER['request_id']) ? \$_SERVER['request_id'] : NULL,\n"
		."\t'client_request_id'=>isset(\$_SERVER['HTTP_X_REQUEST_ID']) ? \$_SERVER['HTTP_X_REQUEST_ID'] : NULL,\n"
		."\t'in_cmd'=>IN_CMD,\n"
		."\t'support_html'=>xn_request_id_support_html(),\n"
		."\t'headers'=>headers_list(),\n"
		."));\n";
}

function request_id_free_port(&$detail) {
	$detail = '';
	$errno = 0;
	$errstr = '';
	$socket = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
	if(!is_resource($socket)) {
		$detail = $errstr !== '' ? $errstr : 'stream_socket_server failed';
		return FALSE;
	}
	$name = stream_socket_get_name($socket, FALSE);
	fclose($socket);
	$colon = is_string($name) ? strrpos($name, ':') : FALSE;
	$port = $colon === FALSE ? 0 : intval(substr($name, $colon + 1));
	if($port < 1 || $port > 65535) {
		$detail = 'invalid ephemeral port: '.(string)$name;
		return FALSE;
	}
	return $port;
}

function request_id_wait_for_server($port, $process, &$detail) {
	$detail = '';
	$deadline = microtime(TRUE) + 5.0;
	do {
		$status = proc_get_status($process);
		if(empty($status['running'])) {
			$detail = 'PHP development server exited before accepting requests.';
			return FALSE;
		}
		$errno = 0;
		$errstr = '';
		$socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
		if(is_resource($socket)) {
			fclose($socket);
			return TRUE;
		}
		usleep(50000);
	} while(microtime(TRUE) < $deadline);
	$detail = 'timed out waiting for PHP development server';
	return FALSE;
}

function request_id_http_get($url, $forged, &$response_headers) {
	$response_headers = array();
	$context = stream_context_create(array('http'=>array(
		'method'=>'GET',
		'header'=>'X-Request-ID: '.$forged."\r\nConnection: close\r\n",
		'ignore_errors'=>TRUE,
		'timeout'=>5,
		'protocol_version'=>1.1,
	)));
	$stream = @fopen($url, 'rb', FALSE, $context);
	if(!is_resource($stream)) return FALSE;
	$metadata = stream_get_meta_data($stream);
	if(isset($metadata['wrapper_data']) && is_array($metadata['wrapper_data'])) {
		$response_headers = $metadata['wrapper_data'];
	}
	$body = stream_get_contents($stream);
	fclose($stream);
	return $body;
}

function request_id_response_values($headers) {
	$values = array();
	foreach($headers as $header) {
		if(stripos($header, 'X-Request-ID:') !== 0) continue;
		$values[] = trim(substr($header, strlen('X-Request-ID:')));
	}
	return $values;
}

function request_id_run_child($command, $cwd, &$stdout, &$stderr) {
	$stdout = '';
	$stderr = '';
	$descriptor = array(
		0=>array('pipe', 'r'),
		1=>array('pipe', 'w'),
		2=>array('pipe', 'w'),
	);
	$pipes = array();
	$process = @proc_open($command, $descriptor, $pipes, $cwd, NULL, array('bypass_shell'=>TRUE));
	if(!is_resource($process)) return 127;
	fclose($pipes[0]);
	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	return proc_close($process);
}

function request_id_log_fields($fixture, $channel) {
	$files = glob($fixture.'/log/*/'.$channel.'.php');
	if(!is_array($files) || count($files) !== 1) return FALSE;
	$lines = file($files[0], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	if(!is_array($lines) || empty($lines)) return FALSE;
	return explode("\t", $lines[count($lines) - 1]);
}

try {
	$suffix = function_exists('random_bytes') ? bin2hex(random_bytes(8)) : str_replace('.', '', uniqid('', TRUE));
	$fixture = rtrim(sys_get_temp_dir(), '/\\').DIRECTORY_SEPARATOR.'xiuno-request-id-'.$suffix;
	if(!mkdir($fixture, 0700, TRUE) || !mkdir($fixture.'/tmp', 0700, TRUE) || !mkdir($fixture.'/log', 0700, TRUE)) {
		throw new RuntimeException('unable to create isolated request ID fixture');
	}

	$fixture_modes = array(
		'source'=>array('bundle'=>FALSE, 'preload'=>FALSE),
		'bundle'=>array('bundle'=>TRUE, 'preload'=>FALSE),
		'preloaded_bundle'=>array('bundle'=>TRUE, 'preload'=>TRUE),
	);
	foreach($fixture_modes as $name=>$mode) {
		$script = request_id_fixture_source($root, str_replace('\\', '/', $fixture), $mode['bundle'], $name, $mode['preload']);
		$path = $fixture.DIRECTORY_SEPARATOR.$name.'.php';
		if(file_put_contents($path, $script) !== strlen($script)) {
			throw new RuntimeException('unable to write '.$name.' request ID fixture entry');
		}
	}

	$port = request_id_free_port($port_detail);
	if($port === FALSE) throw new RuntimeException('unable to allocate HTTP fixture port: '.$port_detail);
	$descriptor = array(
		0=>array('pipe', 'r'),
		1=>array('pipe', 'w'),
		2=>array('pipe', 'w'),
	);
	$environment = getenv();
	if(is_array($environment)) {
		$environment['SHELL'] = '/bin/xiuno-request-id-fixture';
		unset($environment['PHP_CLI_SERVER_WORKERS']);
	} else {
		$environment = NULL;
	}
	$server = @proc_open(
		array(PHP_BINARY, '-S', '127.0.0.1:'.$port, '-t', $fixture),
		$descriptor,
		$server_pipes,
		$fixture,
		$environment,
		array('bypass_shell'=>TRUE)
	);
	if(!is_resource($server)) throw new RuntimeException('unable to start PHP development server');
	fclose($server_pipes[0]);
	unset($server_pipes[0]);
	foreach($server_pipes as $pipe) stream_set_blocking($pipe, FALSE);
	if(!request_id_wait_for_server($port, $server, $server_detail)) throw new RuntimeException($server_detail);

	$forged = str_repeat('c', 32);
	$web_ids = array();
	foreach(array_keys($fixture_modes) as $name) {
		$body = request_id_http_get('http://127.0.0.1:'.$port.'/'.$name.'.php', $forged, $headers);
		$data = is_string($body) ? json_decode($body, TRUE) : NULL;
		$values = request_id_response_values($headers);
		if(!is_array($data)) {
			$errors[] = $name.' HTTP fixture returned invalid JSON: '.(is_string($body) ? $body : 'transport failure');
			continue;
		}
		if(count($values) !== 1 || preg_match('/\A[a-f0-9]{32}\z/D', $values[0]) !== 1) {
			$errors[] = $name.' HTTP response did not expose exactly one valid X-Request-ID header';
			continue;
		}
		$id = $values[0];
		$web_ids[] = $id;
		if($id === $forged || $data['request_id'] !== $id || $data['client_request_id'] !== $forged || !empty($data['in_cmd'])
			|| substr_count((string)$data['support_html'], $id) !== 2 || strpos((string)$data['support_html'], 'data-request-id=') === FALSE) {
			$errors[] = $name.' HTTP request trusted the client ID, lost response/log correlation, or treated cli-server as CLI';
		}
		$fields = request_id_log_fields($fixture, 'request_id_'.$name.'_web');
		if($fields === FALSE || count($fields) < 7 || strpos($fields[5], 'request id behavior smoke') === FALSE || $fields[6] !== $id) {
			$errors[] = $name.' HTTP log did not contain the response X-Request-ID in its dedicated field';
		}
	}
	if(count($web_ids) === count($fixture_modes) && count(array_unique($web_ids)) !== count($web_ids)) {
		$errors[] = 'independent Web requests reused the same request ID';
	}

	request_id_stop_server($server, $server_pipes);

	foreach(array_keys($fixture_modes) as $name) {
		$script = $fixture.DIRECTORY_SEPARATOR.$name.'.php';
		$exit = request_id_run_child(array(PHP_BINARY, $script), $fixture, $stdout, $stderr);
		$data = json_decode($stdout, TRUE);
		if($exit !== 0 || !is_array($data)) {
			$errors[] = $name.' CLI fixture failed: '.trim($stderr.' '.$stdout);
			continue;
		}
		if($data['request_id'] !== '' || empty($data['in_cmd']) || !empty($data['headers']) || $data['client_request_id'] !== str_repeat('c', 32)
			|| $data['support_html'] !== '') {
			$errors[] = $name.' CLI request ID semantics were not a stable empty value without response headers';
		}
		$fields = request_id_log_fields($fixture, 'request_id_'.$name.'_cli');
		if($fields === FALSE || count($fields) < 7 || $fields[6] !== '') {
			$errors[] = $name.' CLI log must preserve an empty request ID field';
		}
	}

	$invalid_script = $fixture.DIRECTORY_SEPARATOR.'source.php';
	$exit = request_id_run_child(array(PHP_BINARY, $invalid_script, '--invalid-log-id'), $fixture, $stdout, $stderr);
	$invalid_fields = request_id_log_fields($fixture, 'request_id_source_cli_invalid');
	if($exit !== 0 || $invalid_fields === FALSE || count($invalid_fields) < 7 || $invalid_fields[6] !== '') {
		$errors[] = 'logger accepted a malformed request ID instead of emitting an empty field';
	}
} catch(Throwable $e) {
	$errors[] = $e->getMessage();
} finally {
	request_id_stop_server($server, $server_pipes);
	if(isset($fixture) && is_dir($fixture)) request_id_remove_fixture($fixture);
}

if($errors) {
	fwrite(STDERR, "Request ID guard failed:\n - ".implode("\n - ", $errors)."\n");
	exit(1);
}

echo "Request ID guard passed.\n";
