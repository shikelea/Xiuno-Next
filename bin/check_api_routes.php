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

function api_route_source($path) {
	return file_get_contents(APP_PATH . $path);
}

function api_smoke_run($request) {
	global $conf;
	$_REQUEST = array();
	foreach((array)$request as $key => $value) {
		$_REQUEST[$key] = $value;
	}
	try {
		include APP_PATH . 'route/api.php';
	} catch (ApiSmokeOutput $e) {
		return $e->payload;
	}
	return NULL;
}

$response = api_smoke_run(array());
if(!is_array($response) || $response['code'] !== 0 || $response['data']['version'] !== 'smoke' || $response['data']['api_version'] !== 'legacy') {
	$errors[] = 'default API route did not return index response';
}

$response = api_smoke_run(array(1 => 'v1'));
if(!is_array($response) || $response['code'] !== 0 || $response['data']['api_version'] !== 'v1') {
	$errors[] = 'versioned API route did not return v1 index response';
}

$response = api_smoke_run(array(1 => 'missing'));
if(!is_array($response) || $response['code'] !== 404) {
	$errors[] = 'missing API route did not return 404';
}

$response = api_smoke_run(array(1 => '../user'));
if(!is_array($response) || $response['code'] !== 404) {
	$errors[] = 'unsafe API route action did not return 404';
}

$response = api_smoke_run(array(1 => 'v1', 2 => 'thread', 3 => '../list'));
if(!is_array($response) || $response['code'] !== 404) {
	$errors[] = 'unsafe versioned API action did not return 404';
}

$postRoute = api_route_source('route/api/post.php');
if(strpos($postRoute, 'api_login_required();') === FALSE) {
	$errors[] = 'post create API must use api_login_required()';
}

$threadRoute = api_route_source('route/api/thread.php');
if(strpos($threadRoute, 'api_login_required();') === FALSE) {
	$errors[] = 'thread create API must use api_login_required()';
}

$userRoute = api_route_source('route/api/user.php');
if(strpos($userRoute, 'api_auth_uid(FALSE)') === FALSE) {
	$errors[] = 'user read API must use api_auth_uid(FALSE) for token fallback';
}

if(!empty($errors)) {
	fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
	exit(1);
}

echo "API route smoke OK\n";
exit(0);
