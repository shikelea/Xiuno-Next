<?php

define('DEBUG', 1);
define('APP_PATH', dirname(__DIR__) . '/');

$conf = array('version' => 'smoke');
$errors = array();

class ApiSmokeOutput extends Exception {
	public $payload;

	public function __construct($payload) {
		parent::__construct('api output');
		$this->payload = $payload;
	}
}

function param($key, $defval = '') {
	return isset($_REQUEST[$key]) ? $_REQUEST[$key] : $defval;
}

function api_output($code, $message, $data = array()) {
	throw new ApiSmokeOutput(array(
		'code' => $code,
		'message' => $message,
		'data' => $data,
	));
}

function api_smoke_run($action) {
	global $conf;
	$_REQUEST = array();
	if($action !== NULL) $_REQUEST[1] = $action;
	try {
		include APP_PATH . 'route/api.php';
	} catch (ApiSmokeOutput $e) {
		return $e->payload;
	}
	return NULL;
}

$response = api_smoke_run(NULL);
if(!is_array($response) || $response['code'] !== 0 || $response['data']['version'] !== 'smoke') {
	$errors[] = 'default API route did not return index response';
}

$response = api_smoke_run('missing');
if(!is_array($response) || $response['code'] !== 404) {
	$errors[] = 'missing API route did not return 404';
}

$response = api_smoke_run('../user');
if(!is_array($response) || $response['code'] !== 404) {
	$errors[] = 'unsafe API route action did not return 404';
}

if(!empty($errors)) {
	fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
	exit(1);
}

echo "API route smoke OK\n";
exit(0);
