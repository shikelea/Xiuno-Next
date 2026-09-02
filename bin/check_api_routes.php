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

function api_output($code, $message, $data = array(), $http_status = 200) {
	$effective_status = isset($_SERVER['api_version']) && $_SERVER['api_version'] === 'v1' ? intval($http_status) : 200;
	throw new ApiSmokeOutput(array(
		'code' => $code,
		'message' => $message,
		'data' => $data,
		'_http_status' => $effective_status,
	));
}

function api_is_v1() {
	return isset($_SERVER['api_version']) && $_SERVER['api_version'] === 'v1';
}

function api_method_required($required) {
	global $method;
	if(strtoupper($method) === strtoupper($required)) return TRUE;
	api_output(-1, 'Method Not Allowed', array(), 405);
}

function api_route_source($path) {
	return file_get_contents(APP_PATH . $path);
}

function api_smoke_run($request, $request_method = 'GET') {
	global $conf, $method;
	$method = strtoupper($request_method);
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
if(!is_array($response) || $response['code'] !== 0 || $response['data']['version'] !== 'smoke' || $response['data']['api_version'] !== 'legacy' || $response['_http_status'] !== 200) {
	$errors[] = 'default API route did not return index response';
}

$response = api_smoke_run(array(1 => 'v1'));
if(!is_array($response) || $response['code'] !== 0 || $response['data']['api_version'] !== 'v1' || $response['_http_status'] !== 200) {
	$errors[] = 'versioned API route did not return v1 index response';
}

$response = api_smoke_run(array(1 => 'v1'), 'POST');
if(!is_array($response) || $response['code'] !== -1 || $response['_http_status'] !== 405) {
	$errors[] = 'versioned API index must reject POST with HTTP 405';
}

$response = api_smoke_run(array(), 'POST');
if(!is_array($response) || $response['code'] !== 0 || $response['_http_status'] !== 200) {
	$errors[] = 'legacy API index must preserve its historical POST behavior';
}

$response = api_smoke_run(array(1 => 'missing'));
if(!is_array($response) || $response['code'] !== 404 || $response['_http_status'] !== 200) {
	$errors[] = 'legacy missing API route must preserve HTTP 200 with JSON code 404';
}

$response = api_smoke_run(array(1 => '../user'));
if(!is_array($response) || $response['code'] !== 404 || $response['_http_status'] !== 200) {
	$errors[] = 'unsafe legacy API route action must preserve HTTP 200 with JSON code 404';
}

$response = api_smoke_run(array(1 => 'v1', 2 => 'thread', 3 => '../list'));
if(!is_array($response) || $response['code'] !== 404 || $response['_http_status'] !== 404) {
	$errors[] = 'unsafe versioned API action must return HTTP and JSON 404';
}

$response = api_smoke_run(array(1 => 'v1', 2 => 'missing', 3 => 'index'));
if(!is_array($response) || $response['code'] !== 404 || $response['_http_status'] !== 404) {
	$errors[] = 'missing versioned API controller must return HTTP and JSON 404';
}

$indexRoute = api_route_source('route/api/index.php');
$forumRoute = api_route_source('route/api/forum.php');
$searchRoute = api_route_source('route/api/search.php');
$userRoute = api_route_source('route/api/user.php');
$postRoute = api_route_source('route/api/post.php');
if(strpos($postRoute, 'api_login_required();') === FALSE) {
	$errors[] = 'post create API must use api_login_required()';
}
if(strpos($postRoute, 'api_method_required(\'POST\');') === FALSE) {
	$errors[] = 'post create API must require POST through api_method_required()';
}
if(strpos($postRoute, '$doctype = api_post_doctype();') === FALSE) {
	$errors[] = 'post create API must use the version-aware document type contract';
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
if(strpos($threadRoute, '$doctype = api_post_doctype();') === FALSE) {
	$errors[] = 'thread create API must use the version-aware document type contract';
}
if(strpos($threadRoute, 'if(!api_is_v1()) thread_inc_views($tid);') === FALSE) {
	$errors[] = 'v1 thread reads must not mutate the view counter';
}
if(substr_count($threadRoute, 'if(api_is_v1() && is_array($threadlist)) $threadlist = array_values($threadlist);') !== 1
	|| substr_count($threadRoute, 'if(api_is_v1() && is_array($postlist)) $postlist = array_values($postlist);') !== 1
	|| substr_count($userRoute, "if(api_is_v1()) \$result['list'] = array_values(\$result['list']);") !== 1
	|| substr_count($searchRoute, "if(api_is_v1()) \$result['list'] = array_values(\$result['list']);") !== 1) {
	$errors[] = 'v1 collection responses must use JSON arrays without changing legacy keyed lists';
}
if(strpos($threadRoute, '$cond[\'fid\'] = $allowfids;') === FALSE) {
	$errors[] = 'thread list API must restrict global listing to readable forums';
}
if(strpos($threadRoute, '$forum[\'accesson\'] && !forum_access_user') !== FALSE) {
	$errors[] = 'thread API must not bypass group allowread when forum accesson is disabled';
}

foreach(array(
	'API index' => array($indexRoute, 1),
	'forum read routes' => array($forumRoute, 2),
	'thread read routes' => array($threadRoute, 2),
	'user read routes' => array($userRoute, 2),
	'search read route' => array($searchRoute, 1),
) as $label => $expectation) {
	if(substr_count($expectation[0], "api_is_v1() AND api_method_required('GET');") !== $expectation[1]) {
		$errors[] = $label.' must reject non-GET methods in v1 without changing legacy behavior';
	}
}
$loginStart = strpos($userRoute, "if(\$action == 'login')");
$loginEnd = strpos($userRoute, "} elseif(\$action == 'read')");
if($loginStart === FALSE || $loginEnd === FALSE || $loginEnd <= $loginStart) {
	$errors[] = 'user route must keep a detectable login branch before read branch';
	$userLoginRoute = '';
} else {
	$userLoginRoute = substr($userRoute, $loginStart, $loginEnd - $loginStart);
}
if(strpos($userRoute, 'api_auth_uid(FALSE)') === FALSE) {
	$errors[] = 'user read API must use api_auth_uid(FALSE) for token fallback';
}
if(strpos($userRoute, 'api_method_required(\'POST\');') === FALSE) {
	$errors[] = 'user login API must require POST through api_method_required()';
}
$rateLimitPos = strpos($userLoginRoute, 'user_login_rate_limited($email)');
$emailLookupPos = strpos($userLoginRoute, 'user_read_by_email($email, TRUE)');
$usernameLookupPos = strpos($userLoginRoute, 'user_read_by_username($email, TRUE)');
$lookupPositions = array_filter(array($emailLookupPos, $usernameLookupPos), function($pos) { return $pos !== FALSE; });
$firstLookupPos = empty($lookupPositions) ? FALSE : min($lookupPositions);
if($firstLookupPos === FALSE) {
	$errors[] = 'user login API must keep a detectable credential lookup';
}
if($rateLimitPos === FALSE || ($firstLookupPos !== FALSE && $rateLimitPos > $firstLookupPos)) {
	$errors[] = 'user login API must check login failure rate before credential lookup';
}
if(substr_count($userLoginRoute, 'user_login_rate_fail($email);') !== 1) {
	$errors[] = 'user login API must record its unified credential failure once';
}
if(strpos($userLoginRoute, 'user_login_password_verify($password, $user)') === FALSE
	|| substr_count($userLoginRoute, "'Email or password is incorrect'") !== 1
	|| strpos($userLoginRoute, "api_output(-1, 'Email or password is incorrect', array(), 401)") === FALSE
	|| strpos($userLoginRoute, "lang('user_not_exists')") !== FALSE
	|| strpos($userLoginRoute, "lang('password_incorrect')") !== FALSE) {
	$errors[] = 'user login API must use a generic failure response to avoid account enumeration';
}
if(strpos($userLoginRoute, 'user_login_rate_clear($email);') === FALSE) {
	$errors[] = 'user login API must clear login failure rate after successful authentication';
}
if(strpos($userRoute, 'mythread_find_visible_by_uid($_uid, $gid, $page, $pagesize)') === FALSE) {
	$errors[] = 'user threads API must use visible mythread helper';
}

$userModel = api_route_source('model/user.func.php');
foreach(array(
	'function user_login_rate_key',
	'function user_login_rate_read',
	'function user_login_rate_limited',
	'function user_login_rate_lockname',
	'function user_login_rate_fail',
	'function user_login_rate_clear',
	'hash_hmac(\'sha256\', $account, xn_key())',
	'xn_lock_start($lockname, 5)',
	'finally',
	'cache_set($key, array(\'count\'=>$count, \'time\'=>$time), 900)',
) as $needle) {
	if(strpos($userModel, $needle) === FALSE) {
		$errors[] = 'user model must define cache-backed API login rate limiting helper: '.$needle;
	}
}

$miscModel = api_route_source('model/misc.func.php');
if(strpos($miscModel, 'function api_method_required') === FALSE) {
	$errors[] = 'misc helpers must define api_method_required()';
}
if(strpos($miscModel, 'function api_page_params') === FALSE) {
	$errors[] = 'misc helpers must define api_page_params()';
}
foreach(array(
	'function api_output($code, $message, $data = array(), $http_status = 200)',
	'if(api_is_v1())',
	'http_response_code($http_status)',
	"header('X-Xiuno-API-Version: v1')",
	"api_output(-1, 'Method Not Allowed', array(), 405)",
	'function api_post_doctype()',
	"if(!api_is_v1()) return param('doctype', 0)",
	"if(!array_key_exists('doctype', \$_REQUEST)) return 1",
	"api_output(-1, 'Document type is not supported', array(), 422)",
) as $needle) {
	if(strpos($miscModel, $needle) === FALSE) {
		$errors[] = 'v1 API response contract is missing: '.$needle;
	}
}
if(strpos($miscModel, 'function api_csrf_check') === FALSE || strpos($miscModel, 'api_request_token() === \'\'') === FALSE) {
	$errors[] = 'API session-backed POST requests must require CSRF when no API token is present';
}
if(substr_count($miscModel, "api_output(-1, lang('please_login'), array(), 401)") !== 2
	|| strpos($miscModel, "api_output(-1, 'CSRF token validation failed', array(), 403)") === FALSE) {
	$errors[] = 'v1 authentication and CSRF failures must request stable HTTP statuses';
}
if(strpos($miscModel, '$_user = user_read_primary_proven($_uid);') === FALSE) {
	$errors[] = 'API token authorization must load permission fields from the primary database';
}

// Run the real api_auth_uid() function in an isolated process with a deliberately stale replica.
// The token is valid, but authorization must use the downgraded primary gid instead of replica gid 1.
$authChild = "<?php\ndefine('DEBUG', 1);\ndefine('APP_PATH', ".var_export(APP_PATH, TRUE).");\ndefine('XIUNOPHP_PATH', APP_PATH.'xiunophp/');\n".<<<'PHP'
$masterUser = array('uid'=>77, 'gid'=>0);
$replicaUser = array('uid'=>77, 'gid'=>1);
$primaryFlags = array();
function param($key, $default = NULL, $htmlspecialchars = TRUE, $addslashes = FALSE) {
	return isset($_REQUEST[$key]) ? $_REQUEST[$key] : $default;
}
function user_token_get_do(&$auth_epoch = NULL) {
	$auth_epoch = 4;
	return 77;
}
function user_read($uid, $primary = FALSE) {
	global $masterUser, $replicaUser, $primaryFlags;
	$primaryFlags[] = $primary;
	return $primary ? $masterUser : $replicaUser;
}
function user_read_primary_proven($uid) { return user_read($uid, TRUE); }
function group_list_cache() {
	return array(0=>array('allowpost'=>0), 1=>array('allowpost'=>1));
}
function lang($key, $args = array()) { return $key; }
$_REQUEST = array('token'=>'valid-token');
$_SERVER = array();
$uid = 0;
$user = array();
$gid = 0;
$group = array();
$grouplist = group_list_cache();
require APP_PATH.'model/misc.func.php';
$result = api_auth_uid(TRUE);
if($result !== 77 || $uid !== 77 || $gid !== 0 || $primaryFlags !== array(TRUE)) {
	fwrite(STDERR, 'stale replica authorization was accepted');
	exit(1);
}
echo 'OK';
PHP;
$authDescriptors = array(
	0=>array('pipe', 'r'),
	1=>array('pipe', 'w'),
	2=>array('pipe', 'w'),
);
$authProcess = proc_open(array(PHP_BINARY), $authDescriptors, $authPipes, APP_PATH);
if(!is_resource($authProcess)) {
	$errors[] = 'failed to start API primary-authorization behavior child';
} else {
	fwrite($authPipes[0], $authChild);
	fclose($authPipes[0]);
	$authStdout = stream_get_contents($authPipes[1]);
	$authStderr = stream_get_contents($authPipes[2]);
	fclose($authPipes[1]);
	fclose($authPipes[2]);
	$authExit = proc_close($authProcess);
	if($authExit !== 0 || trim($authStdout) !== 'OK') {
		$errors[] = 'API primary-authorization behavior failed: '.trim($authStderr.' '.$authStdout);
	}
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
