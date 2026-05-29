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
if(strpos($postRoute, 'api_method_required(\'POST\');') === FALSE) {
	$errors[] = 'post create API must require POST through api_method_required()';
}
if(strpos($postRoute, '$quotepost = post__read($quotepid);') === FALSE || strpos($postRoute, '$quotepost[\'tid\'] != $tid') === FALSE) {
	$errors[] = 'post create API must validate quotepid belongs to the target thread';
}

$threadRoute = api_route_source('route/api/thread.php');
if(strpos($threadRoute, 'api_login_required();') === FALSE) {
	$errors[] = 'thread create API must use api_login_required()';
}
if(strpos($threadRoute, 'api_method_required(\'POST\');') === FALSE) {
	$errors[] = 'thread create API must require POST through api_method_required()';
}
if(strpos($threadRoute, '$cond[\'fid\'] = $allowfids;') === FALSE) {
	$errors[] = 'thread list API must restrict global listing to readable forums';
}
if(strpos($threadRoute, '$forum[\'accesson\'] && !forum_access_user') !== FALSE) {
	$errors[] = 'thread API must not bypass group allowread when forum accesson is disabled';
}

$userRoute = api_route_source('route/api/user.php');
if(strpos($userRoute, 'api_auth_uid(FALSE)') === FALSE) {
	$errors[] = 'user read API must use api_auth_uid(FALSE) for token fallback';
}
if(strpos($userRoute, 'api_method_required(\'POST\');') === FALSE) {
	$errors[] = 'user login API must require POST through api_method_required()';
}
if(strpos($userRoute, 'mythread_find_visible_by_uid($_uid, $gid, $page, $pagesize)') === FALSE) {
	$errors[] = 'user threads API must use visible mythread helper';
}

$miscModel = api_route_source('model/misc.func.php');
if(strpos($miscModel, 'function api_method_required') === FALSE) {
	$errors[] = 'misc helpers must define api_method_required()';
}
if(strpos($miscModel, 'function api_page_params') === FALSE) {
	$errors[] = 'misc helpers must define api_page_params()';
}
if(strpos($miscModel, 'function api_csrf_check') === FALSE || strpos($miscModel, 'api_request_token() === \'\'') === FALSE) {
	$errors[] = 'API session-backed POST requests must require CSRF when no API token is present';
}

$forumRoute = api_route_source('route/api/forum.php');
if(strpos($forumRoute, 'forum_list_access_filter($forumlist, $gid, \'allowread\')') === FALSE) {
	$errors[] = 'forum list API must filter forums by read permission';
}
if(strpos($forumRoute, 'forum_safe_info($forum)') === FALSE) {
	$errors[] = 'forum API must return forum_safe_info() output';
}

if(substr_count($threadRoute, 'api_page_params()') < 2) {
	$errors[] = 'thread list/read APIs must use api_page_params()';
}

$searchRoute = api_route_source('route/api/search.php');
if(strpos($searchRoute, 'thread_search_by_subject($keyword, $gid, $page, $pagesize)') === FALSE) {
	$errors[] = 'thread search API must use thread_search_by_subject()';
}
if(strpos($searchRoute, 'api_page_params(20, 50)') === FALSE) {
	$errors[] = 'thread search API must use bounded pagination';
}
if(strpos($searchRoute, "str_replace(array('%', '_'), '', \$keyword)") === FALSE || strpos($searchRoute, 'Keyword is too short') === FALSE) {
	$errors[] = 'thread search API must reject broad wildcard-only keywords';
}

$threadModel = api_route_source('model/thread.func.php');
if(strpos($threadModel, 'function thread_search_by_subject') === FALSE) {
	$errors[] = 'thread model must define thread_search_by_subject()';
}
if(strpos($threadModel, 'forum_access_user($thread[\'fid\'], $gid, \'allowread\')') === FALSE) {
	$errors[] = 'thread search helper must filter by forum read permission';
}
if(strpos($threadModel, 'thread_safe_info($thread)') === FALSE) {
	$errors[] = 'thread search helper must return thread_safe_info() output';
}

$mythreadModel = api_route_source('model/mythread.func.php');
if(strpos($mythreadModel, 'function mythread_find_visible_by_uid') === FALSE) {
	$errors[] = 'mythread model must define mythread_find_visible_by_uid()';
}
if(strpos($mythreadModel, 'forum_access_user($thread[\'fid\'], $gid, \'allowread\')') === FALSE) {
	$errors[] = 'visible mythread helper must filter by forum read permission';
}
if(strpos($mythreadModel, 'thread_safe_info($thread)') === FALSE) {
	$errors[] = 'visible mythread helper must return thread_safe_info() output';
}

if(!empty($errors)) {
	fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
	exit(1);
}

echo "API route smoke OK\n";
exit(0);
