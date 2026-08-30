<?php

$root = dirname(__DIR__);
$check_model = file_get_contents($root.'/model/check.func.php');
$user_model = file_get_contents($root.'/model/user.func.php');
$kv_model = file_get_contents($root.'/model/kv.func.php');
$user_route = file_get_contents($root.'/route/user.php');
$user_route !== FALSE && strpos($user_route, "lang('resetpw_not_on')") !== FALSE
	|| fail('password reset disabled responses must use the shared localized message');
$index_source = file_get_contents($root.'/index.inc.php');
$my_route = file_get_contents($root.'/route/my.php');
$admin_user_route = file_get_contents($root.'/admin/route/user.php');
$api_user_route = file_get_contents($root.'/route/api/user.php');
$install_sql = file_get_contents($root.'/install/install.sql');
$auth_epoch_migration = file_get_contents($root.'/database/migrations/0002_add_user_auth_epoch.php');
$docker_http_smoke = file_get_contents($root.'/bin/check_docker_http_smoke.sh');
$resetpw_tool = file_get_contents($root.'/tool/resetpw.php');
$workflow = file_get_contents($root.'/.github/workflows/ci.yml');
$user_create_template = file_get_contents($root.'/view/htm/user_create.htm');
$user_create_template !== FALSE && strpos($user_create_template, "jsubmit.button('enable');") !== FALSE
	|| fail('successful registration email-code delivery must explicitly enable the initially disabled submit button');
$user_reset_complete_template = file_get_contents($root.'/view/htm/user_resetpw_complete.htm');
$header_template = file_get_contents($root.'/view/htm/header.inc.htm');
$footer_template = file_get_contents($root.'/view/htm/footer.inc.htm');
$my_password_template = file_get_contents($root.'/view/htm/my_password.htm');
$admin_login_template = file_get_contents($root.'/admin/view/htm/index_login.htm');
$misc_source = file_get_contents($root.'/xiunophp/misc.func.php');
$db_func_source = file_get_contents($root.'/xiunophp/db.func.php');
$cache_func_source = file_get_contents($root.'/xiunophp/cache.func.php');
$cache_mysql_source = file_get_contents($root.'/xiunophp/cache_mysql.class.php');
$db_driver_sources = array(
	'mysql'=>file_get_contents($root.'/xiunophp/db_mysql.class.php'),
	'pdo_mysql'=>file_get_contents($root.'/xiunophp/db_pdo_mysql.class.php'),
	'pdo_sqlite'=>file_get_contents($root.'/xiunophp/db_pdo_sqlite.class.php'),
);
$bbs_language_sources = array(
	'en-us'=>file_get_contents($root.'/lang/en-us/bbs.php'),
	'zh-cn'=>file_get_contents($root.'/lang/zh-cn/bbs.php'),
	'zh-tw'=>file_get_contents($root.'/lang/zh-tw/bbs.php'),
	'ru-ru'=>file_get_contents($root.'/lang/ru-ru/bbs.php'),
	'th-th'=>file_get_contents($root.'/lang/th-th/bbs.php'),
);

function fail($message) {
	fwrite(STDERR, "FAIL: $message\n");
	exit(1);
}

function section_between($source, $start, $end) {
	$start_pos = strpos($source, $start);
	if($start_pos === FALSE) fail("Missing section start: $start");
	$end_pos = strpos($source, $end, $start_pos + strlen($start));
	if($end_pos === FALSE) fail("Missing section end after $start: $end");
	return substr($source, $start_pos, $end_pos - $start_pos);
}

function auth_guard_run_php_child($source, $label) {
	function_exists('proc_open') || fail("$label requires proc_open().");
	$descriptors = array(
		0=>array('pipe', 'r'),
		1=>array('pipe', 'w'),
		2=>array('pipe', 'w'),
	);
	$pipes = array();
	$process = @proc_open(array(PHP_BINARY), $descriptors, $pipes, dirname(__DIR__), NULL, array('bypass_shell'=>TRUE));
	is_resource($process) || fail("Unable to start $label child process.");
	fwrite($pipes[0], $source);
	fclose($pipes[0]);
	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	$exit = proc_close($process);
	$exit === 0 || fail("$label child failed with exit $exit: $stderr$stdout");
	$result = json_decode(trim($stdout), TRUE);
	is_array($result) || fail("$label child returned invalid JSON: $stderr$stdout");
	return $result;
}

if(!function_exists('lang')) {
	function lang($key, $args = array()) {
		return $key;
	}
}
require_once $root.'/model/check.func.php';
require_once $root.'/model/user.func.php';

$identifier_cases = array(
	'admin@example.com'=>'email',
	'admin'=>'username',
	'测试用户'=>'username',
	'bad identifier'=>'',
);
foreach($identifier_cases as $identifier=>$expected_type) {
	$identifier_err = '';
	$actual_type = user_login_identifier_type($identifier, $identifier_err);
	$actual_type === $expected_type
		|| fail("Login identifier type mismatch for $identifier: $actual_type != $expected_type");
}

$token_gen = section_between($user_model, 'function user_token_gen', 'function user_token_fingerprint');
strpos($token_gen, 'user_token_fingerprint()') !== FALSE
	|| fail('Persistent login tokens must include the user-agent fingerprint.');
strpos($token_gen, '"$ip	$time	$uid	$fingerprint	$auth_epoch"') !== FALSE
	|| fail('New persistent login tokens must carry the fingerprint and credential epoch fields.');
strpos($token_gen, 'user_auth_epoch($_user)') !== FALSE
	|| fail('Persistent login token issuance must read the current user credential epoch.');
strpos($token_gen, 'user__read_primary_proven($uid)') !== FALSE
	|| fail('Persistent login token issuance must read the credential epoch from the primary database.');
strpos($token_gen, 'user_auth_epoch_matches($_user, $expected_auth_epoch)') !== FALSE
	|| fail('Persistent login token issuance must reject a generation newer than the verified proof.');

$token_get = section_between($user_model, 'function user_token_get_do', 'function user_token_set');
strpos($token_get, 'count($arr) != 3 && count($arr) != 4 && count($arr) != 5') !== FALSE
	|| fail('Persistent token reader must explicitly handle legacy and epoch-bound token shapes.');
strpos($token_get, 'hash_equals(user_token_fingerprint(), $_fingerprint)') !== FALSE
	|| fail('Current persistent tokens must be checked with hash_equals().');
strpos($token_get, 'user_auth_epoch_matches($_user, $_auth_epoch)') !== FALSE
	|| fail('Persistent tokens must match the current user credential epoch.');
strpos($token_get, 'user__read_primary_proven($_uid)') !== FALSE
	|| fail('Persistent token validation must not accept a stale replica credential epoch.');
strpos($token_get, '$auth_epoch = $_auth_epoch;') !== FALSE
	|| fail('Persistent token validation must return the exact generation that the token proved.');
strpos($token_get, '86400 * 30') !== FALSE
	|| fail('Persistent token reader must keep the 30-day expiry bound.');

$cookie = section_between($user_model, 'function user_token_cookie_set', 'function user_cookie_secure');
foreach(array("'httponly'=>TRUE", "'samesite'=>'Lax'", "'secure'=>user_cookie_secure()") as $needle) {
	strpos($cookie, $needle) !== FALSE || fail("Persistent login cookie must keep $needle.");
}
strpos($user_model, 'function user_cookie_secure()') !== FALSE
	|| fail('Persistent login cookie must derive Secure from HTTPS/proxy state.');
strpos($user_model, "HTTP_X_FORWARDED_PROTO") !== FALSE
	|| fail('Secure cookie detection must account for trusted HTTPS reverse-proxy headers.');

$code_issue = section_between($user_route, 'function user_email_code_issue', 'function user_email_code_verify');
strpos($code_issue, 'random_int(100000, 999999)') !== FALSE
	|| fail('Email verification codes must use random_int().');
strpos($code_issue, "user_email_code_rate_limit(\$prefix, \$email)") !== FALSE
	|| fail('Email verification code sends must pass through rate limiting.');
strpos($code_issue, "\$_SESSION[\$prefix.'_code_time'] = \$time") !== FALSE
	|| fail('Email verification codes must store an issuance timestamp.');
strpos($code_issue, "\$_SESSION[\$prefix.'_code_attempts'] = 0") !== FALSE
	|| fail('Email verification code attempts must reset on new issue.');
$reset_issue_clear = strpos($code_issue, 'user_reset_grant_revoke_uid($uid)');
$reset_issue_email = strpos($code_issue, "\$_SESSION[\$prefix.'_email'] = \$email;");
($reset_issue_clear !== FALSE && $reset_issue_email !== FALSE && $reset_issue_clear < $reset_issue_email)
	|| fail('Issuing a password-reset code must revoke an earlier durable reset grant.');
strpos($user_route, 'resetpw_verify_ok') === FALSE
	|| fail('Password-reset authorization must not use an unbound Session boolean.');

$code_verify = section_between($user_route, 'function user_email_code_verify', 'function user_email_code_clear');
strpos($code_verify, '$time - $sess_time > 300') !== FALSE
	|| fail('Email verification codes must expire after five minutes.');
strpos($code_verify, '$attempts >= 5') !== FALSE
	|| fail('Email verification codes must cap failed attempts.');
strpos($code_verify, 'hash_equals($sess_code, (string)$code)') !== FALSE
	|| fail('Email verification codes must use hash_equals().');
strpos($code_verify, "\$_SESSION[\$prefix.'_code_attempts'] = \$attempts + 1") !== FALSE
	|| fail('Email verification code failures must increment attempts.');

$rate_limit = section_between($user_model, 'function user_email_code_rate_take', 'function user_email_code_rate_limit');
strpos($rate_limit, '$now - $window_start > 3600') !== FALSE
	|| fail('Email verification code send rate limit must have a one-hour window.');
strpos($rate_limit, '$limits = array($email_key=>5, $ip_key=>20)') !== FALSE
	&& strpos($rate_limit, '$send_count >= $limits[$key]') !== FALSE
	|| fail('Email verification code sends must use a strict target limit and a NAT-tolerant IP limit.');
strpos($rate_limit, "user_email_code_rate_key(\$prefix, 'email'") !== FALSE
	&& strpos($rate_limit, "user_email_code_rate_key('all', 'ip'") !== FALSE
	|| fail('Email verification code sends must share action+email and global IP dimensions.');
strpos($rate_limit, 'cache_set($key, $record, 3700)') !== FALSE
	&& strpos($rate_limit, 'cache_get_primary($key) !== $record') !== FALSE
	|| fail('Email verification code rate limits must persist and verify expiring shared KV state.');
strpos($rate_limit, 'xn_lock_start($lockname, 10)') !== FALSE
	&& strpos($rate_limit, 'array_reverse($locked)') !== FALSE
	|| fail('Email verification code rate-limit updates must use stable ordered locks.');

strpos($user_route, "user_email_code_verify('user_create', \$email, \$code)") !== FALSE
	|| fail('User registration must use the shared email code verifier.');
strpos($user_route, "user_email_code_verify('user_resetpw', \$email, \$code)") !== FALSE
	|| fail('Password reset must use the shared email code verifier.');
strpos($user_route, "user_email_code_issue('user_create', \$email)") !== FALSE
	|| fail('User registration code sends must use the shared issuer.');
strpos($user_route, "user_email_code_issue('user_resetpw', \$email, \$_user['uid'])") !== FALSE
	|| fail('Password reset code sends must use the shared issuer.');
strpos($user_route, "user_email_code_clear('user_create')") !== FALSE
	|| fail('Successful user registration must clear email verification state.');
strpos($user_route, "user_email_code_clear('user_resetpw')") !== FALSE
	|| fail('Successful password reset must clear email verification state.');

$create = section_between($user_route, "} elseif(\$action == 'create')", "} elseif(\$action == 'logout')");
$create_rotate_pos = strpos($create, "sess_regenerate_id() OR message(-1, 'Unable to renew session. Please try again.');");
$create_user_pos = strpos($create, '$uid = user_create($_user);');
($create_rotate_pos !== FALSE && $create_user_pos !== FALSE && $create_rotate_pos < $create_user_pos)
	|| fail('User registration must rotate the anonymous session before creating an authenticated user.');
$create_bind_pos = strpos($create, '$create_session_bound = user_session_auth_bind($uid, $create_auth_epoch);');
$create_token_pos = strpos($create, '$create_token_set = $create_session_bound && user_token_set($uid, $create_auth_epoch);');
$create_recovery_pos = strpos($create, 'if(!$create_session_bound || !$create_token_set)');
$create_success_pos = strpos($create, "message(0, lang('user_create_sucessfully')");
($create_bind_pos !== FALSE && $create_token_pos !== FALSE && $create_recovery_pos !== FALSE && $create_success_pos !== FALSE
	&& $create_user_pos < $create_bind_pos && $create_bind_pos < $create_token_pos
	&& $create_token_pos < $create_recovery_pos && $create_recovery_pos < $create_success_pos)
	|| fail('Registration must establish both Session and persistent-token authentication before reporting login success.');
$create_recovery = $create_recovery_pos !== FALSE && $create_success_pos !== FALSE
	? substr($create, $create_recovery_pos, $create_success_pos - $create_recovery_pos)
	: '';
foreach(array(
	'$uid = 0;'=>'clear the request identity',
	'$user = array();'=>'clear the request user',
	"\$_SESSION['uid'] = 0;"=>'clear the Session identity',
	"unset(\$_SESSION['auth_epoch']);"=>'clear the Session credential generation',
	'user_token_clear();'=>'clear any partially issued persistent token',
	"lang('user_create_login_failed')"=>'return a recoverable account-created/login-failed result',
	"'account_created'=>1"=>'mark the account as already created',
	"'login_url'=>url('user-login')"=>'provide the manual-login recovery target',
) as $needle=>$label) {
	strpos($create_recovery, $needle) !== FALSE || fail("Registration authentication failure must $label.");
}
foreach($bbs_language_sources as $language=>$source) {
	is_string($source) && strpos($source, "'user_create_login_failed'=>") !== FALSE
		|| fail("Registration partial-success recovery text is missing from $language.");
}

$registration_child_template = <<<'PHP'
<?php
$root = __ROOT__;
$flow_case = __FLOW_CASE__;
$blank_include = tempnam(sys_get_temp_dir(), 'xn_register_');
if($blank_include === FALSE || file_put_contents($blank_include, "<?php\n") === FALSE) exit(90);
register_shutdown_function(function() use ($blank_include) { is_file($blank_include) && @unlink($blank_include); });

define('DEBUG', 1);
define('APP_PATH', $root.'/');
define('XIUNOPHP_PATH', $root.'/xiunophp/');

class RegistrationFlowMessage extends Exception {
	public $flow_code;
	public $flow_extra;
	public function __construct($code, $message, $extra) {
		parent::__construct((string)$message);
		$this->flow_code = $code;
		$this->flow_extra = $extra;
	}
}

$params = array('email'=>'new@example.com', 'username'=>'new-user', 'password'=>'password-hash', 'code'=>'');
$conf = array('user_create_on'=>1, 'user_create_email_on'=>0);
$method = 'POST';
$longip = 123;
$time = 456;
$uid = 0;
$user = array();
$_SESSION = array();
$account_created = FALSE;
$bind_calls = 0;
$token_calls = 0;
$token_clear_calls = 0;
$token_active = FALSE;

function _include($path) { return $GLOBALS['blank_include']; }
function param($key, $default = NULL, $htmlspecialchars = TRUE) {
	if($key === 1) return 'create';
	return array_key_exists($key, $GLOBALS['params']) ? $GLOBALS['params'][$key] : $default;
}
function lang($key, $args = array()) { return $key; }
function message($code, $message, $extra = array()) { throw new RegistrationFlowMessage($code, $message, $extra); }
function is_email($value, &$error = NULL) { return TRUE; }
function is_username($value, &$error = NULL) { return TRUE; }
function is_password($value, &$error = NULL) { return TRUE; }
function user_read_by_email($email, $primary = FALSE) { return FALSE; }
function user_read_by_username($username, $primary = FALSE) { return FALSE; }
function user_hash_password($password) { return 'stored-password'; }
function sess_regenerate_id() { return TRUE; }
function user_create($record) { $GLOBALS['account_created'] = TRUE; return 42; }
function user_read($uid, $primary = FALSE) { return array('uid'=>42, 'email'=>'new@example.com', 'auth_epoch'=>7); }
function user_read_primary_proven($uid) { return user_read($uid, TRUE); }
function user_auth_epoch($user) { return intval($user['auth_epoch']); }
function user_session_auth_bind($uid, $epoch) {
	$GLOBALS['bind_calls']++;
	if($GLOBALS['flow_case'] === 'bind-fail') return FALSE;
	$_SESSION['uid'] = intval($uid);
	$_SESSION['auth_epoch'] = intval($epoch);
	return TRUE;
}
function user_token_set($uid, $epoch = NULL) {
	$GLOBALS['token_calls']++;
	if($GLOBALS['flow_case'] === 'token-fail') return FALSE;
	$GLOBALS['token_active'] = TRUE;
	return TRUE;
}
function user_token_clear() {
	$GLOBALS['token_clear_calls']++;
	$GLOBALS['token_active'] = FALSE;
	return TRUE;
}
function user_token_gen($uid, $epoch = NULL) { return 'route-token'; }
function url($route) { return './?'.$route.'.htm'; }

try {
	include $root.'/route/user.php';
	fwrite(STDERR, "registration route returned without a message\n");
	exit(91);
} catch(RegistrationFlowMessage $message) {
	echo json_encode(array(
		'code'=>$message->flow_code,
		'message'=>$message->getMessage(),
		'extra'=>$message->flow_extra,
		'account_created'=>$account_created,
		'uid'=>$uid,
		'user'=>$user,
		'session'=>$_SESSION,
		'bind_calls'=>$bind_calls,
		'token_calls'=>$token_calls,
		'token_clear_calls'=>$token_clear_calls,
		'token_active'=>$token_active,
	));
}
PHP;

foreach(array('bind-fail', 'token-fail', 'success') as $registration_case) {
	$registration_child = str_replace(
		array('__ROOT__', '__FLOW_CASE__'),
		array(var_export(str_replace('\\', '/', $root), TRUE), var_export($registration_case, TRUE)),
		$registration_child_template
	);
	$registration_result = auth_guard_run_php_child($registration_child, "registration $registration_case");
	!empty($registration_result['account_created'])
		|| fail("Registration $registration_case must keep the already-created account.");
	if($registration_case === 'success') {
		intval($registration_result['code']) === 0
			&& intval($registration_result['uid']) === 42
			&& intval($registration_result['session']['uid']) === 42
			&& intval($registration_result['session']['auth_epoch']) === 7
			&& !empty($registration_result['token_active'])
			&& intval($registration_result['token_clear_calls']) === 0
			|| fail('Successful registration must retain the fully established Session and persistent token.');
		continue;
	}
	intval($registration_result['code']) < 0
		&& $registration_result['message'] === 'user_create_login_failed'
		&& intval($registration_result['extra']['account_created']) === 1
		&& $registration_result['extra']['login_url'] === './?user-login.htm'
		&& intval($registration_result['uid']) === 0
		&& empty($registration_result['user'])
		&& intval($registration_result['session']['uid']) === 0
		&& !isset($registration_result['session']['auth_epoch'])
		&& empty($registration_result['token_active'])
		&& intval($registration_result['token_clear_calls']) === 1
		|| fail("Registration $registration_case must return account-created/manual-login recovery and clear partial authentication state.");
}

$resetpw_complete = section_between($user_route, "} elseif(\$action == 'resetpw_complete')", "} elseif(\$action == 'send_code')");
$resetpw_consume_pos = strpos($resetpw_complete, '$password_reset_result = user_reset_grant_commit_password($password);');
$resetpw_update_pos = strpos($resetpw_complete, '$password_reset_result === FALSE');
$resetpw_clear_pos = strpos($resetpw_complete, "user_email_code_clear('user_resetpw');");
($resetpw_consume_pos !== FALSE && $resetpw_update_pos !== FALSE && $resetpw_clear_pos !== FALSE
	&& $resetpw_consume_pos < $resetpw_update_pos && $resetpw_update_pos < $resetpw_clear_pos)
	|| fail('Password reset must consume and commit its one-time grant through one atomic model operation.');
strpos($resetpw_complete, 'user_reset_grant_consume()') === FALSE
	&& strpos($resetpw_complete, 'user_password_commit($_uid, $password)') === FALSE
	|| fail('Password reset must not split grant consumption from password commit across two critical sections.');
$resetpw_verified_email_pos = strpos($resetpw_complete, "\$email = \$_user['email'];");
$resetpw_email_match_pos = strpos($resetpw_complete, "user_reset_grant_email(\$_user['email'])");
$resetpw_get_pos = strpos($resetpw_complete, "if(\$method == 'GET')");
($resetpw_email_match_pos !== FALSE && $resetpw_verified_email_pos !== FALSE && $resetpw_get_pos !== FALSE
	&& $resetpw_email_match_pos < $resetpw_verified_email_pos && $resetpw_verified_email_pos < $resetpw_get_pos)
	|| fail('Password-reset completion must expose the already verified account email to its GET template.');
is_string($user_reset_complete_template)
	&& strpos($user_reset_complete_template, 'value="<?php echo xn_html_escape($email);?>"') !== FALSE
	&& strpos($user_reset_complete_template, 'value="<?php echo $email;?>"') === FALSE
	|| fail('Password-reset completion must HTML-escape the verified account email in its value attribute.');

$identity = section_between($index_source, '$uid = intval(_SESSION(\'uid\'));', '$gid = empty($user)');
$missing_user_pos = strpos($identity, 'if($uid && empty($user))');
$clear_session_uid_pos = strpos($identity, "\$_SESSION['uid'] = 0;");
$clear_token_pos = strpos($identity, 'user_token_clear();');
($missing_user_pos !== FALSE && $clear_session_uid_pos !== FALSE && $clear_token_pos !== FALSE)
	|| fail('A missing user referenced by a Session or token must be cleared before authorization uses the uid.');
strpos($identity, 'user_session_auth_matches($user)') !== FALSE
	|| fail('Authenticated Sessions must match the current user credential epoch.');
strpos($identity, 'user_token_get($token_auth_epoch)') !== FALSE
	&& strpos($identity, 'user_auth_epoch_matches($user, $token_auth_epoch)') !== FALSE
	&& strpos($identity, 'user_session_auth_bind($uid, $token_auth_epoch)') !== FALSE
	|| fail('Persistent-token login must bind only the generation actually verified by that token.');
substr_count($identity, 'user_read_primary_proven($uid)') >= 2
	|| fail('Session and persistent-token authorization must load the user from the primary database.');

strpos($db_func_source, 'function db_find_one_master') !== FALSE
	&& strpos($db_func_source, "!method_exists(\$d, 'find_one_master')") !== FALSE
	|| fail('The database facade must expose a fail-closed primary-read contract.');
strpos($db_func_source, 'function db_sql_find_one_master') !== FALSE
	&& strpos($db_func_source, "!method_exists(\$d, 'sql_find_one_master')") !== FALSE
	|| fail('Raw schema checks must have a fail-closed write-connection read contract.');
foreach($db_driver_sources as $driver=>$driver_source) {
	is_string($driver_source)
		&& strpos($driver_source, 'function sql_find_one_master') !== FALSE
		&& strpos($driver_source, 'function find_one_master') !== FALSE
		|| fail("Database driver $driver must implement the primary-read contract.");
}
strpos($cache_func_source, 'function cache_get_primary') !== FALSE
	&& strpos($cache_mysql_source, 'function get_master') !== FALSE
	&& strpos($cache_mysql_source, 'db_find_one_master($this->table') !== FALSE
	|| fail('MySQL-backed security counters must be able to bypass stale database replicas.');
$login_rate_read = section_between($user_model, 'function user_login_rate_read', 'function user_login_rate_limited');
strpos($login_rate_read, 'cache_get_primary($key)') !== FALSE
	|| fail('Login throttling must read its counter from the primary cache state.');
strpos($kv_model, 'function kv__get($k, $primary = FALSE)') !== FALSE
	&& strpos($kv_model, "? db_find_one_master('kv', array('k'=>\$k))") !== FALSE
	&& strpos($kv_model, 'if($arr === FALSE) return FALSE;') !== FALSE
	&& strpos($kv_model, 'json_last_error() === JSON_ERROR_NONE ? $value : FALSE') !== FALSE
	|| fail('Durable KV reads must be able to opt into the primary database contract.');
strpos($kv_model, "\$setting = kv__get('setting', TRUE);") !== FALSE
	&& strpos($kv_model, 'if($setting === FALSE) return FALSE;') !== FALSE
	|| fail('Locked setting read-modify-write operations must start from the primary KV row.');

$synlogin_end = '// '.'hook user_end.php';
$synlogin = section_between($user_route, "} elseif(\$action == 'synlogin')", $synlogin_end);
strpos($synlogin, "param('token', '', FALSE)") !== FALSE
	|| fail('Synlogin must read encrypted tokens without HTML escaping.');
strpos($synlogin, "user_synlogin_return_url(param('return_url', '', FALSE))") !== FALSE
	|| fail('Synlogin must validate return_url before storing or redirecting.');
strpos($synlogin, 'count($token_parts) != 2') !== FALSE
	|| fail('Synlogin must validate incoming token structure.');
strpos($synlogin, 'abs($time - intval($_time)) > 60') !== FALSE
	|| fail('Synlogin incoming token must expire after one minute.');
strpos($synlogin, '$_SESSION[\'return_url\'] = $return_url') !== FALSE
	|| fail('Synlogin must replace stale return_url after validation.');
strpos($synlogin, 'user_synlogin_append_token($return_url, $s)') !== FALSE
	|| fail('Synlogin must append response tokens through a safe helper.');
strpos($synlogin, "xn_urldecode(\$return_url).'?token='") === FALSE
	|| fail('Synlogin must not concatenate raw token query strings.');

$return_url = section_between($user_route, 'function user_synlogin_return_url', 'function user_synlogin_public_host');
strpos($return_url, 'array(trim($raw), trim(xn_urldecode($raw)))') !== FALSE
	|| fail('Synlogin return_url must accept both raw URLs and Xiuno-encoded URLs.');
strpos($return_url, 'preg_match(\'/[\\x00-\\x1F\\x7F]/\', $url)') !== FALSE
	|| fail('Synlogin return_url must reject control characters.');
strpos($return_url, "in_array(strtolower(\$parts['scheme']), array('http', 'https'), TRUE)") !== FALSE
	|| fail('Synlogin return_url must be constrained to HTTP(S).');
strpos($return_url, "empty(\$parts['host']) || !user_synlogin_public_host(\$parts['host'])") !== FALSE
	|| fail('Synlogin return_url must validate public hosts.');
strpos($return_url, "!empty(\$parts['user']) || !empty(\$parts['pass'])") !== FALSE
	|| fail('Synlogin return_url must reject embedded credentials.');
strpos($return_url, "return '';") !== FALSE
	|| fail('Synlogin return_url must fail closed.');

$return_host = section_between($user_route, 'function user_synlogin_public_host', 'function user_synlogin_append_token');
strpos($return_host, "FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE") !== FALSE
	|| fail('Synlogin return_url must reject private/reserved IP hosts.');
strpos($return_host, "substr(\$host, -6) === '.local'") !== FALSE
	|| fail('Synlogin return_url must reject local hostnames.');

$append_token = section_between($user_route, 'function user_synlogin_append_token', 'function user_auth_check');
strpos($append_token, 'http_build_query(array(\'token\'=>$token))') !== FALSE
	|| fail('Synlogin must URL-encode response tokens with http_build_query().');

$_SERVER['HTTP_HOST'] = 'forum.example:8080';
$_SERVER['SERVER_PORT'] = 8080;
$_SERVER['HTTPS'] = 'off';
unset($_SERVER['HTTP_X_FORWARDED_PROTO']);
$return_url_cases = array(
	'./'=>'./',
	'?thread-1.htm'=>'?thread-1.htm',
	'/thread-1.htm?from=login#last'=>'/thread-1.htm?from=login#last',
	'http://forum.example:8080/thread-1.htm?from=login#last'=>'/thread-1.htm?from=login#last',
	'HTTP://FORUM.EXAMPLE:8080/forum-1.htm'=>'/forum-1.htm',
	'https://evil.example/steal'=>'./',
	'http://forum.example/thread-1.htm'=>'./',
	'//evil.example/steal'=>'./',
	'\\\\evil.example\\steal'=>'./',
	'javascript:alert(1)'=>'./',
	"/thread-1.htm\r\nLocation:https://evil.example"=>'./',
	'/user-login.htm'=>'./',
);
foreach($return_url_cases as $input=>$expected) {
	$actual = user_return_url_normalize($input);
	$actual === $expected || fail('Login return URL normalization mismatch for '.var_export($input, TRUE).': '.var_export($actual, TRUE).' != '.var_export($expected, TRUE));
}
$_SERVER['HTTPS'] = 'on';
$_SERVER['SERVER_PORT'] = 443;
$_SERVER['HTTP_HOST'] = 'forum.example';
user_return_url_normalize('https://forum.example/my.htm') === '/my.htm'
	|| fail('Login return URL normalization must accept the exact HTTPS origin.');
user_return_url_normalize('http://forum.example/my.htm') === './'
	|| fail('Login return URL normalization must reject an HTTP downgrade origin.');

$referer_helper = section_between($user_route, 'function user_http_referer()', 'function user_email_code_issue');
strpos($referer_helper, "param('referer', '', FALSE)") !== FALSE
	|| fail('Login return URLs must be normalized from the raw parameter rather than an HTML-escaped value.');
substr_count($referer_helper, 'user_return_url_normalize($referer)') >= 2
	|| fail('Login return URLs must be normalized before and after legacy hook execution.');
strpos($user_create_template, 'xn_json_encode_for_script($referer)') !== FALSE
	|| fail('Registration success must use the normalized return URL prepared by the route.');
strpos($user_create_template, 'xn_json_encode_for_script(http_referer())') === FALSE
	|| fail('Registration templates must not re-read an unnormalized HTTP referer.');
$logout_flow = section_between($user_route, "} elseif(\$action == 'logout')", '// 重设密码第 1 步');
strpos($logout_flow, 'user_http_referer()') !== FALSE && strpos($logout_flow, ', http_referer(),') === FALSE
	|| fail('Logout success must use the same normalized same-origin return URL contract.');
strpos($logout_flow, "if(\$method == 'GET')") !== FALSE
	&& strpos($logout_flow, "include _include(APP_PATH.'view/htm/user_logout.htm')") !== FALSE
	&& strpos($logout_flow, "} elseif(\$method == 'POST')") !== FALSE
	|| fail('Logout GET must render confirmation and reserve credential mutation for POST.');
$logout_post_pos = strpos($logout_flow, "} elseif(\$method == 'POST')");
$logout_clear_pos = strpos($logout_flow, 'user_token_clear();');
($logout_post_pos !== FALSE && $logout_clear_pos !== FALSE && $logout_post_pos < $logout_clear_pos)
	|| fail('Logout token clearing must occur only inside the POST branch.');
$logout_template = file_get_contents($root.'/view/htm/user_logout.htm');
is_string($logout_template) || fail('Unable to read logout confirmation template.');
strpos($logout_template, 'method="post"') !== FALSE
	&& strpos($logout_template, 'name="_token"') !== FALSE
	&& strpos($logout_template, 'csrf_token()') !== FALSE
	|| fail('Logout confirmation must submit a server-rendered CSRF token via POST.');
$logout_document = (string)$header_template.(string)$logout_template.(string)$footer_template;
strpos($logout_template, '<main') === FALSE && strpos($logout_template, '</main>') === FALSE
	&& substr_count($logout_document, '<main') === 1
	&& substr_count($logout_document, 'id="body"') === 1
	&& substr_count($logout_document, '</main>') === 1
	|| fail('Logout confirmation must reuse the single main#body opened and closed by the shared page shell.');
$header_nav = file_get_contents($root.'/view/htm/header_nav.inc.htm');
is_string($header_nav) || fail('Unable to read default navigation template.');
preg_match('~url\(\'user-logout\'\)[\s\S]{0,120}data-method="post"~', $header_nav) === 1
	|| fail('The default logout navigation must use the shared POST-link behavior.');
$jump_helper = section_between($misc_source, 'function jump($message, $url', 'function xn_html_escape');
strpos($jump_helper, 'xn_json_encode_for_script((string)$url)') !== FALSE
	&& strpos($jump_helper, '$url_html = xn_html_escape($url);') !== FALSE
	|| fail('Jump responses must encode return URLs independently for script and HTML attribute contexts.');

$ci_runs_deterministic = strpos($workflow, 'php bin/run_checks.php --profile=deterministic') !== FALSE;
strpos($workflow, 'php bin/check_user_auth_safety.php') !== FALSE || $ci_runs_deterministic
	|| fail('CI must run the user auth safety guard.');

$login = section_between($user_route, "} elseif(\$action == 'login')", "} elseif(\$action == 'create')");
$identifier_helper = section_between($check_model, 'function user_login_identifier_type', 'function is_password');
strpos($identifier_helper, "return 'email';") !== FALSE
	|| fail('Login identifier validation must preserve email login.');
strpos($identifier_helper, "return 'username';") !== FALSE
	|| fail('Login identifier validation must preserve username login.');
strpos($login, 'user_login_identifier_type($email, $err)') !== FALSE
	|| fail('Browser login must validate both email and username identifiers.');
strpos($login, "if(\$login_identifier_type == 'email')") !== FALSE
	|| fail('Browser login must choose email lookup from the validated identifier type.');
$rate_check_pos = strpos($login, 'user_login_rate_limited($email)');
$email_lookup_pos = strpos($login, 'user_read_by_email($email, TRUE)');
$username_lookup_pos = strpos($login, 'user_read_by_username($email, TRUE)');
($rate_check_pos !== FALSE && $email_lookup_pos !== FALSE && $rate_check_pos < $email_lookup_pos && $rate_check_pos < $username_lookup_pos)
	|| fail('Browser login must check login failure rate before credential lookup.');
substr_count($login, 'user_login_rate_fail($email);') >= 4
	|| fail('Browser login must record missing-account, invalid-password-format and bad-password failures.');
$password_verify_pos = strpos($login, 'user_verify_password($password, $_user)');
$password_finalize_pos = strpos($login, "user_login_credentials_refresh(\$_user['uid'], \$password)");
$password_format_pos = strpos($login, 'user_format($_user);');
$rate_clear_pos = strpos($login, 'user_login_rate_clear($email);');
($password_verify_pos !== FALSE && $password_finalize_pos !== FALSE && $password_format_pos !== FALSE
	&& $rate_clear_pos !== FALSE && $password_verify_pos < $password_finalize_pos
	&& $password_finalize_pos < $password_format_pos && $password_format_pos < $rate_clear_pos)
	|| fail('Browser login must re-verify under the credential lock before clearing its failure rate.');
strpos($login, 'user_token_set($_user[\'uid\'], $login_auth_epoch)') !== FALSE
	|| fail('Browser login must issue its persistent token only at the verified credential generation.');

$password_change = section_between($my_route, "} elseif(\$action == 'password')", "} elseif(\$action == 'thread')");
$thread_page = section_between($my_route, "} elseif(\$action == 'thread')", "} elseif(\$action == 'avatar')");
$avatar_page = section_between($my_route, "} elseif(\$action == 'avatar')", "// hook my_end.php");
strpos($password_change, "\$header['title'] = lang('modify_password');") !== FALSE
	|| fail('Password settings must expose a page-specific document title.');
strpos($thread_page, "\$header['title'] = lang('my_thread');") !== FALSE
	|| fail('My threads must expose a page-specific document title.');
strpos($avatar_page, "\$header['title'] = lang('modify_avatar');") !== FALSE
	|| fail('Avatar settings must expose a page-specific document title.');
$password_validate_pos = strpos($password_change, 'is_password($password_new, $err)');
$password_hash_pos = strpos($password_change, 'user_hash_password($password_new)');
($password_validate_pos !== FALSE && $password_hash_pos !== FALSE && $password_validate_pos < $password_hash_pos)
	|| fail('Password changes must reject empty or malformed new password digests before hashing.');
$password_commit_pos = strpos($password_change, 'user_password_change_verified($uid, $password_old, $password_new)');
$password_rotate_pos = strpos($password_change, 'sess_regenerate_id()');
$password_bind_pos = strpos($password_change, 'user_session_auth_bind($uid, $new_auth_epoch)');
$password_token_pos = strpos($password_change, 'user_token_set($uid, $new_auth_epoch)');
($password_commit_pos !== FALSE && $password_rotate_pos !== FALSE && $password_bind_pos !== FALSE
	&& $password_token_pos !== FALSE && $password_commit_pos < $password_rotate_pos
	&& $password_rotate_pos < $password_bind_pos && $password_bind_pos < $password_token_pos)
	|| fail('A self-service password change must commit, rotate, then bind only the current Session to the new epoch.');

strpos($admin_user_route, 'user_password_commit($_uid, $password_hash, $update)') !== FALSE
	|| fail('Administrator password replacement must revoke the target user credential generation.');
strpos($api_user_route, '$token === FALSE AND api_output') !== FALSE
	|| fail('API login must fail closed when an epoch-bound token cannot be issued.');
strpos($api_user_route, "user_login_credentials_refresh(\$user['uid'], \$password)") !== FALSE
	&& strpos($api_user_route, 'user_format($user);') !== FALSE
	&& strpos($api_user_route, "user_token_gen(\$user['uid'], user_auth_epoch(\$user))") !== FALSE
	|| fail('API login must finalize and reformat the proof before issuing only that verified generation.');
strpos($install_sql, 'auth_epoch int(11) unsigned NOT NULL DEFAULT \'0\'') !== FALSE
	|| fail('Fresh installs must create the user credential epoch column.');
substr_count($auth_epoch_migration, 'db_sql_find_one_master(') === 2
	&& strpos($auth_epoch_migration, "LIKE 'auth_epoch'") !== FALSE
	&& strpos($auth_epoch_migration, 'ADD `auth_epoch` int(11) unsigned NOT NULL DEFAULT \'0\'') !== FALSE
	|| fail('Legacy upgrades must inspect and verify auth_epoch on the write connection.');
strpos($docker_http_smoke, 'Password change did not revoke the older Session and persistent token generation.') !== FALSE
	|| fail('Real HTTP smoke must reject the pre-change Session and persistent token after password change.');

$grant_issue = section_between($user_model, 'function user_reset_grant_issue', 'function user_reset_grant_current');
foreach(array("'uid'=>\$uid", "'email'=>\$email", "'iat'=>intval(\$time)", "'nonce'=>\$nonce", "'auth_epoch'=>user_auth_epoch(\$_user)") as $grant_field) {
	strpos($grant_issue, $grant_field) !== FALSE
		|| fail("Password-reset grants must bind $grant_field.");
}
strpos($grant_issue, 'random_bytes(32)') !== FALSE
	|| fail('Password-reset grant nonces must come from random_bytes().');
$grant_consume = section_between($user_model, 'function user_reset_grant_commit_password', 'function user_reset_grant_revoke_locked');
$grant_lock_pos = strpos($grant_consume, 'xn_lock_start($lockname, 30)');
$grant_delete_pos = strpos($grant_consume, 'kv_delete(user_reset_grant_key($uid))');
($grant_lock_pos !== FALSE && $grant_delete_pos !== FALSE && $grant_lock_pos < $grant_delete_pos)
	|| fail('Password-reset grants must be consumed and committed under their per-user lock.');
strpos($grant_consume, 'kv__get(user_reset_grant_key($uid), TRUE) !== NULL') !== FALSE
	|| fail('Password-reset grant consumption must verify durable deletion.');
$grant_user_read_pos = strpos($grant_consume, '$_user = user__read_primary_proven($uid);');
$grant_epoch_check_pos = strpos($grant_consume, "user_auth_epoch_matches(\$_user, \$grant['auth_epoch'])");
$grant_commit_pos = strpos($grant_consume, "user_password_commit_locked(\$uid, \$password_hash, array(), intval(\$grant['auth_epoch']))");
($grant_user_read_pos !== FALSE && $grant_epoch_check_pos !== FALSE && $grant_commit_pos !== FALSE
	&& $grant_user_read_pos < $grant_epoch_check_pos && $grant_epoch_check_pos < $grant_commit_pos)
	|| fail('Password reset must re-read the user, match the issued epoch, then conditionally commit before releasing its lock.');
$password_commit = section_between($user_model, 'function user_password_commit_locked', 'function user_password_commit(');
strpos($password_commit, '$before = user__read_primary_proven($uid)') !== FALSE
	|| fail('Password commits must derive their compare-and-swap epoch from the primary database.');
strpos($password_commit, 'user_update($uid, $update, $before_epoch, $db_result, $committed_uid)') !== FALSE
	|| fail('Password commits must use the observed auth epoch as a compare-and-swap condition.');
strpos($password_commit, 'if($db_result !== 1 || $committed_uid !== $uid) return FALSE;') !== FALSE
	&& strpos($password_commit, '$after_epoch = $before_epoch + 1;') !== FALSE
	|| fail('Password commits must require an exact one-row result for the UID that reached the database.');
$user_update_raw_pos = strpos($user_model, '$raw_db_result = db_update(');
$user_update_uid_pos = strpos($user_model, '$committed_uid = intval($uid);');
$user_update_push_pos = strpos($user_model, "user_update_db_result_evidence('push', array('result'=>\$raw_db_result, 'uid'=>\$committed_uid))", $user_update_raw_pos === FALSE ? 0 : $user_update_raw_pos);
$user_update_hook_pos = strpos($user_model, '// hook model_user__update_end.php', $user_update_raw_pos === FALSE ? 0 : $user_update_raw_pos);
$user_update_finally_pos = strpos($user_model, '} finally {', $user_update_hook_pos === FALSE ? 0 : $user_update_hook_pos);
$user_update_freeze_pos = strpos($user_model, "\$evidence = user_update_db_result_evidence('pop');", $user_update_hook_pos === FALSE ? 0 : $user_update_hook_pos);
strpos($user_model, 'function user__update($uid, $update, $expected_auth_epoch = NULL, &$db_result = NULL, &$db_uid = NULL)') !== FALSE
	&& $user_update_uid_pos !== FALSE && $user_update_raw_pos !== FALSE && $user_update_push_pos !== FALSE && $user_update_hook_pos !== FALSE
	&& $user_update_finally_pos !== FALSE && $user_update_freeze_pos !== FALSE
	&& $user_update_uid_pos < $user_update_raw_pos && $user_update_raw_pos < $user_update_push_pos && $user_update_push_pos < $user_update_hook_pos
	&& $user_update_hook_pos < $user_update_finally_pos && $user_update_finally_pos < $user_update_freeze_pos
	|| fail('Password compare-and-swap checks must freeze the raw database result and committed uid after legacy Hook mutation.');
strpos($password_commit, '$after = user__read') === FALSE
	|| fail('Password commits must not verify an irreversible primary write through a possibly stale replica read.');
strpos($password_commit, 'user_reset_grant_revoke_locked($uid)') !== FALSE
	|| fail('Every successful password commit must invalidate outstanding reset grants.');
$password_commit_wrapper = section_between($user_model, 'function user_password_commit(', 'function user_reset_grant_ttl');
strpos($password_commit_wrapper, '$lockname = user_reset_grant_lock_name($uid);') !== FALSE
	&& strpos($password_commit_wrapper, 'user_password_commit_locked($uid, $password_hash, $update)') !== FALSE
	|| fail('Every public password commit must share the reset grant user-lock domain.');
$login_finalize = section_between($user_model, 'function user_login_credentials_refresh', 'function user_password_change_verified');
strpos($login_finalize, '$user = user__read_primary_proven($uid);') !== FALSE
	&& strpos($login_finalize, 'user_verify_password($password, $user)') !== FALSE
	&& strpos($login_finalize, 'user_password_commit_locked($uid, $new_hash, array(), user_auth_epoch($user))') !== FALSE
	|| fail('Login finalization must re-read and verify under the credential lock before legacy upgrade CAS.');
$verified_change = section_between($user_model, 'function user_password_change_verified', 'function user_reset_grant_ttl');
strpos($verified_change, '$user = user__read_primary_proven($uid);') !== FALSE
	&& strpos($verified_change, 'user_verify_password($password_old, $user)') !== FALSE
	&& strpos($verified_change, 'user_password_commit_locked($uid, $password_hash, $update, user_auth_epoch($user))') !== FALSE
	|| fail('Self-service password change must verify the old password inside the credential lock.');
$grant_revoke = section_between($user_model, 'function user_reset_grant_revoke_uid', 'function user_email_code_rate_key');
strpos($grant_issue, '$lockname = user_reset_grant_lock_name($uid);') !== FALSE
	&& strpos($grant_consume, '$lockname = user_reset_grant_lock_name($uid);') !== FALSE
	&& strpos($grant_revoke, '$lockname = user_reset_grant_lock_name($uid);') !== FALSE
	|| fail('Grant issuance, final reset commit, revocation and every password commit must use one user-lock namespace.');
$password_upgrade = section_between($user_model, 'function user_upgrade_password', 'function user_password_needs_upgrade');
strpos($password_upgrade, 'user_login_credentials_refresh($uid, $password)') !== FALSE
	|| fail('Automatic legacy password upgrades must re-verify inside the shared credential lock.');

is_string($resetpw_tool)
	&& strpos($resetpw_tool, "PHP_SAPI !== 'cli'") !== FALSE
	&& strpos($resetpw_tool, 'random_bytes(18)') !== FALSE
	&& strpos($resetpw_tool, 'user_password_commit($target_uid, $password_hash') !== FALSE
	&& strpos($resetpw_tool, "user_update(1, array('uid'=>1)") === FALSE
	&& strpos($resetpw_tool, "md5('1')") === FALSE
	|| fail('The emergency password reset utility must be CLI-only, non-fixed and epoch-aware.');

foreach(array(
	array('autocomplete="email"', $user_create_template),
	array('autocomplete="username"', $user_create_template),
	array('autocomplete="one-time-code"', $user_create_template),
	array('autocomplete="new-password"', $user_create_template),
	array('autocomplete="new-password"', $user_reset_complete_template),
	array('autocomplete="current-password"', $my_password_template),
	array('autocomplete="new-password"', $my_password_template),
	array('autocomplete="current-password"', $admin_login_template),
) as $autocomplete_case) {
	list($attribute, $template) = $autocomplete_case;
	is_string($template) && strpos($template, $attribute) !== FALSE
		|| fail("Account forms must expose password-manager and verification-code semantics: $attribute.");
}

foreach(array(
	array('id="username"', $user_create_template),
	array('id="password"', $user_create_template),
	array('id="code"', $user_create_template),
	array('id="password"', $user_reset_complete_template),
	array('id="password2"', $user_reset_complete_template),
) as $required_case) {
	list($id, $template) = $required_case;
	preg_match('/'.preg_quote($id, '/').'[^\r\n]*\brequired\b/i', $template)
		|| fail("Account forms must expose native required-field semantics: $id.");
}

// Behavior-level credential generation and one-time reset-grant checks use only in-memory stores.
$auth_test_users = array();
$auth_test_kv = array();
$auth_test_cache = array();
$auth_test_db_fail = FALSE;
$auth_test_db_result_override = NULL;
$auth_test_before_update = NULL;
$auth_test_kv_fail = FALSE;
$auth_test_kv_read_fail = FALSE;
$auth_test_cache_fail = FALSE;
$auth_test_lock_fail = FALSE;
$auth_test_locks = array();
$auth_test_replica_users = NULL;
$auth_test_primary_reads = 0;
$auth_test_replica_reads = 0;
$auth_test_user_primary_read_fail = FALSE;
$auth_test_kv_replica = NULL;
$auth_test_kv_primary_reads = 0;
$auth_test_kv_replica_reads = 0;
$auth_test_migration_has_epoch = FALSE;
$auth_test_migration_primary_reads = 0;
$auth_test_migration_writes = 0;
$auth_test_migration_read_fail = FALSE;

if(!function_exists('db_find_one')) {
	function db_find_one($table, $cond = array(), $orderby = array(), $col = array(), $d = NULL) {
		global $auth_test_users, $auth_test_replica_users, $auth_test_replica_reads;
		$auth_test_replica_reads++;
		$users = is_array($auth_test_replica_users) ? $auth_test_replica_users : $auth_test_users;
		if($table !== 'user') return array();
		foreach($users as $user) {
			$matches = TRUE;
			foreach($cond as $key=>$value) {
				if(!array_key_exists($key, $user) || (string)$user[$key] !== (string)$value) {
					$matches = FALSE;
					break;
				}
			}
			if($matches) return $user;
		}
		return array();
	}
}
if(!function_exists('db_find_one_master')) {
	function db_find_one_master($table, $cond = array(), $orderby = array(), $col = array(), $d = NULL) {
		global $auth_test_users, $auth_test_primary_reads, $auth_test_user_primary_read_fail;
		$auth_test_primary_reads++;
		if($table !== 'user') return array();
		if($auth_test_user_primary_read_fail) return FALSE;
		foreach($auth_test_users as $user) {
			$matches = TRUE;
			foreach($cond as $key=>$value) {
				if(!array_key_exists($key, $user) || (string)$user[$key] !== (string)$value) {
					$matches = FALSE;
					break;
				}
			}
			if($matches) return $user;
		}
		return array();
	}
}
if(!function_exists('db_sql_find_one_master')) {
	function db_sql_find_one_master($sql, $d = NULL) {
		global $auth_test_migration_has_epoch, $auth_test_migration_primary_reads, $auth_test_migration_read_fail;
		$auth_test_migration_primary_reads++;
		if($auth_test_migration_read_fail) return FALSE;
		return $auth_test_migration_has_epoch
			? array('Field'=>'auth_epoch', 'Type'=>'int unsigned', 'Null'=>'NO', 'Default'=>'0')
			: NULL;
	}
}
if(!function_exists('db_exec')) {
	function db_exec($sql, $d = NULL) {
		global $auth_test_migration_has_epoch, $auth_test_migration_writes;
		if(stripos($sql, 'ALTER TABLE') === FALSE || stripos($sql, 'auth_epoch') === FALSE) return FALSE;
		$auth_test_migration_writes++;
		$auth_test_migration_has_epoch = TRUE;
		return TRUE;
	}
}
if(!function_exists('db_update')) {
	function db_update($table, $cond, $update, $d = NULL) {
		global $auth_test_users, $auth_test_db_fail, $auth_test_db_result_override, $auth_test_before_update;
		if($auth_test_db_fail || $table !== 'user' || !isset($cond['uid'])) return FALSE;
		if($auth_test_db_result_override !== NULL) return $auth_test_db_result_override;
		$uid = intval($cond['uid']);
		if(!isset($auth_test_users[$uid])) return FALSE;
		if(is_callable($auth_test_before_update)) {
			$before_update = $auth_test_before_update;
			$auth_test_before_update = NULL;
			$before_update($uid);
		}
		if(isset($cond['auth_epoch'])
			&& user_auth_epoch($auth_test_users[$uid]) !== intval($cond['auth_epoch'])) return 0;
		foreach($update as $key=>$value) {
			$operator = substr($key, -1);
			if($operator === '+' || $operator === '-') {
				$field = substr($key, 0, -1);
				$current = isset($auth_test_users[$uid][$field]) ? intval($auth_test_users[$uid][$field]) : 0;
				$auth_test_users[$uid][$field] = $operator === '+' ? $current + intval($value) : $current - intval($value);
			} else {
				$auth_test_users[$uid][$key] = $value;
			}
		}
		return 1;
	}
}
if(!function_exists('cache_get')) {
	function cache_get($key, $cache = NULL) {
		global $auth_test_cache, $auth_test_cache_fail;
		if($auth_test_cache_fail) return FALSE;
		return array_key_exists($key, $auth_test_cache) ? $auth_test_cache[$key] : NULL;
	}
}
if(!function_exists('cache_get_primary')) {
	function cache_get_primary($key, $cache = NULL) {
		return cache_get($key, $cache);
	}
}
if(!function_exists('cache_set')) {
	function cache_set($key, $value, $life = 0, $cache = NULL) {
		global $auth_test_cache, $auth_test_cache_fail;
		if($auth_test_cache_fail) return FALSE;
		$auth_test_cache[$key] = $value;
		return TRUE;
	}
}
if(!function_exists('cache_delete')) {
	function cache_delete($key, $cache = NULL) {
		global $auth_test_cache, $auth_test_cache_fail;
		if($auth_test_cache_fail) return FALSE;
		unset($auth_test_cache[$key]);
		return TRUE;
	}
}
if(!function_exists('param')) {
	function param($key, $default = NULL, $htmlspecialchars = TRUE, $addslashes = FALSE) {
		return isset($_REQUEST[$key]) ? $_REQUEST[$key] : $default;
	}
}
if(!function_exists('_SERVER')) {
	function _SERVER($key, $default = '') { return isset($_SERVER[$key]) ? $_SERVER[$key] : $default; }
}
if(!function_exists('xn_key')) {
	function xn_key($fromso = TRUE) { return 'auth-test-secret'; }
}
if(!function_exists('xn_encrypt')) {
	function xn_encrypt($data, $key) { return base64_encode($data); }
}
if(!function_exists('xn_decrypt')) {
	function xn_decrypt($data, $key) {
		$decoded = base64_decode($data, TRUE);
		return $decoded === FALSE ? '' : $decoded;
	}
}
if(!function_exists('kv_set')) {
	function kv_set($key, $value, $life = 0) {
		global $auth_test_kv, $auth_test_kv_fail;
		if($auth_test_kv_fail) return FALSE;
		$auth_test_kv[$key] = $value;
		return 1;
	}
}
if(!function_exists('kv__get')) {
	function kv__get($key, $primary = FALSE) {
		global $auth_test_kv, $auth_test_kv_replica, $auth_test_kv_primary_reads, $auth_test_kv_replica_reads, $auth_test_kv_read_fail;
		if($auth_test_kv_read_fail) return FALSE;
		if($primary) {
			$auth_test_kv_primary_reads++;
			$store = $auth_test_kv;
		} else {
			$auth_test_kv_replica_reads++;
			$store = is_array($auth_test_kv_replica) ? $auth_test_kv_replica : $auth_test_kv;
		}
		return array_key_exists($key, $store) ? $store[$key] : NULL;
	}
}
if(!function_exists('kv_delete')) {
	function kv_delete($key) {
		global $auth_test_kv, $auth_test_kv_fail;
		if($auth_test_kv_fail) return FALSE;
		if(!array_key_exists($key, $auth_test_kv)) return 0;
		unset($auth_test_kv[$key]);
		return 1;
	}
}
if(!function_exists('xn_lock_start')) {
	function xn_lock_start($lockname = '', $life = 10) {
		global $auth_test_lock_fail, $auth_test_locks;
		if($auth_test_lock_fail || !empty($auth_test_locks[$lockname])) return FALSE;
		$auth_test_locks[$lockname] = TRUE;
		return TRUE;
	}
}
if(!function_exists('xn_lock_end')) {
	function xn_lock_end($lockname = '') {
		global $auth_test_locks;
		unset($auth_test_locks[$lockname]);
		return TRUE;
	}
}

$conf = array('cache'=>array('type'=>'mysql'));
$g_static_users = array();
$ip = '127.0.0.1';
$time = 2000;
$_SERVER['HTTP_USER_AGENT'] = 'Xiuno Auth Guard';
$auth_test_users[7] = array('uid'=>7, 'email'=>'owner@example.com', 'password'=>'hash-0', 'auth_epoch'=>0);
$auth_test_users[8] = array('uid'=>8, 'email'=>'legacy@example.com', 'password'=>'legacy-hash');
$auth_test_users[9] = array('uid'=>9, 'email'=>'reset@example.com', 'password'=>'reset-hash', 'auth_epoch'=>0);

// A supported primary user-read failure is not a missing row and must never fall through to a
// replica for token issuance or credential commit.
$primary_failure_snapshot = $auth_test_users[7];
$primary_failure_replica_reads = $auth_test_replica_reads;
$auth_test_user_primary_read_fail = TRUE;
user_token_gen(7) === FALSE
	&& user_password_commit(7, 'primary-read-failure-hash') === FALSE
	&& $auth_test_users[7] === $primary_failure_snapshot
	&& $auth_test_replica_reads === $primary_failure_replica_reads
	|| fail('Primary user-read failure fell through to a replica or authorized a credential write.');
$auth_test_user_primary_read_fail = FALSE;

// A failed or zero-row write must not manufacture a new request-cache generation. A successful
// write invalidates both caches so increment operators, Hooks and formatted group fields are read
// back from the database instead of being guessed with array_merge().
$auth_test_users[13] = array('uid'=>13, 'email'=>'cache@example.com', 'gid'=>2, 'logins'=>4, 'auth_epoch'=>0);
$conf['cache']['type'] = 'redis';
$auth_test_cache['user-13'] = array('uid'=>13, 'gid'=>2, 'logins'=>4, 'groupname'=>'member');
$g_static_users[13] = $auth_test_cache['user-13'];
$auth_test_db_fail = TRUE;
$update_db_result = NULL;
user_update(13, array('gid'=>9), NULL, $update_db_result) === FALSE
	&& $update_db_result === FALSE
	&& $g_static_users[13]['gid'] === 2
	&& isset($auth_test_cache['user-13'])
	|| fail('A failed user UPDATE must preserve the prior request and shared cache generations.');
$auth_test_db_fail = FALSE;
$auth_test_db_result_override = 0;
$update_db_result = NULL;
user_update(13, array('gid'=>9), NULL, $update_db_result) === 0
	&& $update_db_result === 0
	&& $g_static_users[13]['gid'] === 2
	&& isset($auth_test_cache['user-13'])
	|| fail('A zero-row user UPDATE must not synthesize or invalidate a cache generation.');
$auth_test_db_result_override = NULL;
$update_db_result = NULL;
user_update(13, array('gid'=>9, 'logins+'=>1), NULL, $update_db_result) === 1
	&& $update_db_result === 1
	&& $auth_test_users[13]['gid'] === 9
	&& $auth_test_users[13]['logins'] === 5
	&& !isset($g_static_users[13])
	&& !isset($auth_test_cache['user-13'])
	|| fail('A committed user UPDATE must invalidate guessed request/shared user cache state.');
$conf['cache']['type'] = 'mysql';

// A password that was valid at the first route read must not authorize a credential generation
// installed before finalization. Re-verification happens against the primary row under the lock.
$login_proof = 'browser-password-digest';
$login_old_hash = user_hash_password($login_proof);
$auth_test_users[10] = array('uid'=>10, 'email'=>'login@example.com', 'password'=>$login_old_hash, 'salt'=>'', 'auth_epoch'=>0);
$verified_snapshot = $auth_test_users[10];
user_verify_password($login_proof, $verified_snapshot)
	|| fail('Login TOCTOU fixture must start from a valid old password proof.');
$auth_test_users[10]['password'] = user_hash_password('concurrent-password-digest');
$auth_test_users[10]['auth_epoch'] = 1;
user_login_credentials_refresh(10, $login_proof) === FALSE
	|| fail('An old password proof must not inherit a credential generation installed before finalization.');

// Token validation returns the exact epoch it proved. A password change between validation and
// Session binding must make that proof mismatch instead of upgrading the old token.
$token_proof = 'token-password-digest';
$auth_test_users[10] = array('uid'=>10, 'email'=>'login@example.com', 'password'=>user_hash_password($token_proof), 'salt'=>'', 'auth_epoch'=>0);
$proof_user = user_login_credentials_refresh(10, $token_proof);
is_array($proof_user) && user_auth_epoch($proof_user) === 0
	|| fail('Current password proof must finalize at its observed credential generation.');
$proof_token = user_token_gen(10, 0);
$_REQUEST['bbs_token'] = $proof_token;
$verified_token_epoch = NULL;
user_token_get_do($verified_token_epoch) === 10 && $verified_token_epoch === 0
	|| fail('Token validation must return its exact verified credential generation.');
$auth_test_users[10]['password'] = user_hash_password('token-concurrent-change');
$auth_test_users[10]['auth_epoch'] = 1;
!user_auth_epoch_matches($auth_test_users[10], $verified_token_epoch)
	&& user_token_gen(10, $verified_token_epoch) === FALSE
	|| fail('A verified old token generation must neither bind nor mint at a concurrent newer epoch.');

// Cross-node writers may not share the filesystem lock, so the SQL epoch CAS remains the final
// barrier after a legacy password proof and a self-change proof.
$legacy_proof = 'legacy-password-digest';
$legacy_salt = 'legacy-salt';
$concurrent_legacy_hash = user_hash_password('legacy-concurrent-change');
$auth_test_users[11] = array(
	'uid'=>11,
	'email'=>'legacy-race@example.com',
	'password'=>md5($legacy_proof.$legacy_salt),
	'salt'=>$legacy_salt,
	'auth_epoch'=>0,
);
$auth_test_before_update = function($uid) use ($concurrent_legacy_hash) {
	global $auth_test_users;
	$auth_test_users[$uid]['password'] = $concurrent_legacy_hash;
	$auth_test_users[$uid]['salt'] = '';
	$auth_test_users[$uid]['auth_epoch'] = 1;
};
user_login_credentials_refresh(11, $legacy_proof) === FALSE
	&& $auth_test_users[11]['password'] === $concurrent_legacy_hash
	&& $auth_test_users[11]['auth_epoch'] === 1
	|| fail('Legacy login upgrade CAS failure must preserve a concurrently installed password.');

$self_old_proof = 'self-old-password-digest';
$self_concurrent_hash = user_hash_password('self-concurrent-change');
$auth_test_users[12] = array(
	'uid'=>12,
	'email'=>'self-race@example.com',
	'password'=>user_hash_password($self_old_proof),
	'salt'=>'',
	'auth_epoch'=>0,
);
$auth_test_before_update = function($uid) use ($self_concurrent_hash) {
	global $auth_test_users;
	$auth_test_users[$uid]['password'] = $self_concurrent_hash;
	$auth_test_users[$uid]['auth_epoch'] = 1;
};
user_password_change_verified(12, $self_old_proof, user_hash_password('self-requested-new')) === FALSE
	&& $auth_test_users[12]['password'] === $self_concurrent_hash
	&& $auth_test_users[12]['auth_epoch'] === 1
	|| fail('Self-service old-password proof must not overwrite a concurrent credential change.');

$epoch_zero_token = user_token_gen(7);
$epoch_zero_parts = explode("\t", xn_decrypt($epoch_zero_token, hash('sha256', xn_key())));
count($epoch_zero_parts) === 5 && $epoch_zero_parts[4] === '0'
	|| fail('New persistent tokens must serialize epoch zero explicitly.');
$_REQUEST['bbs_token'] = $epoch_zero_token;
user_token_get_do() === 7 || fail('A current epoch-bound token must authenticate its owner.');

$auth_test_replica_users = $auth_test_users;
$primary_reads_before_change = $auth_test_primary_reads;
$replica_reads_before_change = $auth_test_replica_reads;
$new_epoch = user_password_commit(7, 'hash-1');
$new_epoch === 1 && $auth_test_users[7]['password'] === 'hash-1' && $auth_test_users[7]['auth_epoch'] === 1
	|| fail('Password commit must atomically change the hash and increment the credential epoch.');
user_token_get_do() === FALSE || fail('A token from an older credential epoch must be rejected.');
$epoch_one_token = user_token_gen(7);
$_REQUEST['bbs_token'] = $epoch_one_token;
user_token_get_do() === 7 || fail('A token issued after password commit must carry the new epoch.');
$auth_test_primary_reads > $primary_reads_before_change
	&& $auth_test_replica_reads === $replica_reads_before_change
	|| fail('Credential commit, revocation checks and token issuance must ignore a deliberately stale replica.');
$auth_test_replica_users = NULL;

$legacy_token = xn_encrypt(
	$ip."\t".$time."\t8\t".user_token_fingerprint(),
	hash('sha256', xn_key())
);
$_REQUEST['bbs_token'] = $legacy_token;
user_token_get_do() === 8 || fail('A legacy four-field token must remain valid while its user is at epoch zero.');
$_SESSION = array('uid'=>8);
user_session_auth_matches($auth_test_users[8])
	|| fail('A legacy Session without an epoch must remain valid for an unmigrated epoch-zero user.');
$legacy_new_epoch = user_password_commit(8, 'legacy-hash-1');
$legacy_new_epoch === 1 || fail('First password commit must move a legacy user to epoch one.');
$_REQUEST['bbs_token'] = $legacy_token;
user_token_get_do() === FALSE || fail('A legacy token must be revoked after the first epoch increment.');
!user_session_auth_matches($auth_test_users[8])
	|| fail('A legacy Session must be revoked after the first epoch increment.');
user_session_auth_bind(8, 1) && user_session_auth_matches($auth_test_users[8])
	|| fail('The current Session must be bindable to the post-change epoch.');

$auth_test_db_fail = TRUE;
user_password_commit(7, 'hash-never') === FALSE && $auth_test_users[7]['auth_epoch'] === 1
	|| fail('A failed password write must not report or synthesize an epoch increment.');
$auth_test_db_fail = FALSE;
$auth_test_db_result_override = 0;
user_password_commit(7, 'hash-zero-rows') === FALSE && $auth_test_users[7]['password'] === 'hash-1'
	|| fail('A zero-row password compare-and-swap must fail without synthesizing success.');
$auth_test_db_result_override = 2;
user_password_commit(7, 'hash-many-rows') === FALSE && $auth_test_users[7]['password'] === 'hash-1'
	|| fail('A password commit must accept exactly one affected primary-key row.');
$auth_test_db_result_override = NULL;

$_SESSION = array();
$grant = user_reset_grant_issue(9, 'RESET@example.com');
is_array($grant) && intval($grant['uid']) === 9 && intval($grant['iat']) === $time
	&& intval($grant['auth_epoch']) === 0 && $grant['email'] === 'reset@example.com'
	&& preg_match('/^[a-f0-9]{64}$/D', $grant['nonce'])
	|| fail('Password-reset grant issuance must bind uid/email/iat/auth_epoch and a strong nonce.');
user_reset_grant_current() === $grant
	|| fail('A freshly issued durable reset grant must validate.');

$auth_test_kv_replica = $auth_test_kv;
$stale_replica_grant = $grant;
user_reset_grant_revoke_uid(9) || fail('A durable reset grant must be revocable from the primary KV store.');
$_SESSION['resetpw_grant'] = $stale_replica_grant;
$kv_primary_reads_before_stale_check = $auth_test_kv_primary_reads;
$kv_replica_reads_before_stale_check = $auth_test_kv_replica_reads;
user_reset_grant_current() === FALSE
	&& $auth_test_kv_primary_reads > $kv_primary_reads_before_stale_check
	&& $auth_test_kv_replica_reads === $kv_replica_reads_before_stale_check
	|| fail('A reset grant deleted on the primary must not remain usable through a stale KV replica.');
$auth_test_kv_replica = NULL;
$grant = user_reset_grant_issue(9, 'reset@example.com');
is_array($grant) || fail('A reset grant must be issuable again after primary revocation.');

$auth_test_kv_read_fail = TRUE;
user_reset_grant_current() === FALSE
	&& isset($_SESSION['resetpw_grant']) && $_SESSION['resetpw_grant'] === $grant
	&& user_reset_grant_commit_password('must-not-write') === FALSE
	&& isset($auth_test_kv[user_reset_grant_key(9)])
	&& $auth_test_users[9]['password'] === 'reset-hash'
	|| fail('A primary KV read failure must not be treated as a missing or consumed reset grant.');
$auth_test_kv_read_fail = FALSE;

$auth_test_lock_fail = TRUE;
user_reset_grant_commit_password('reset-hash-1') === FALSE && kv__get(user_reset_grant_key(9)) !== NULL
	&& $auth_test_users[9]['password'] === 'reset-hash'
	|| fail('Reset-grant lock contention must fail closed without consuming the grant or changing the password.');
$auth_test_lock_fail = FALSE;
$stale_grant_copy = $grant;
$reset_epoch = user_reset_grant_commit_password('reset-hash-1');
$reset_epoch === 1 && kv__get(user_reset_grant_key(9)) === NULL
	&& $auth_test_users[9]['password'] === 'reset-hash-1' && $auth_test_users[9]['auth_epoch'] === 1
	|| fail('Reset-grant commit must durably consume the grant and atomically advance the credential epoch.');
$_SESSION['resetpw_grant'] = $stale_grant_copy;
user_reset_grant_commit_password('reset-replay') === NULL && $auth_test_users[9]['password'] === 'reset-hash-1'
	|| fail('A stale concurrent Session copy must not reuse the same reset grant.');

$time = 2600;
$pre_change_grant = user_reset_grant_issue(9, 'reset@example.com');
is_array($pre_change_grant) && intval($pre_change_grant['auth_epoch']) === 1
	|| fail('A later reset grant must bind the current credential epoch.');
$changed_epoch = user_password_commit(9, 'out-of-band-change');
$changed_epoch === 2 && kv__get(user_reset_grant_key(9)) === NULL
	|| fail('A non-reset password change must advance auth_epoch and revoke outstanding reset grants.');
$_SESSION['resetpw_grant'] = $pre_change_grant;
user_reset_grant_current() === FALSE
	&& user_reset_grant_commit_password('stale-after-change') === NULL
	&& $auth_test_users[9]['password'] === 'out-of-band-change'
	|| fail('A grant issued before another password change must be rejected without changing the password.');

$time = 3000;
$expired_grant = user_reset_grant_issue(9, 'reset@example.com');
$time += user_reset_grant_ttl() + 1;
user_reset_grant_current() === FALSE
	|| fail('Password-reset grants must fail closed after their short TTL.');
user_reset_grant_revoke_uid(9) || fail('Expired reset grants must remain explicitly revocable.');

$time = 4000;
$grant = user_reset_grant_issue(9, 'reset@example.com');
$auth_test_db_fail = TRUE;
user_reset_grant_commit_password('reset-never') === FALSE
	&& kv__get(user_reset_grant_key(9)) === NULL && $auth_test_users[9]['password'] === 'out-of-band-change'
	|| fail('A reset grant must stay consumed when its conditional password commit fails.');
$auth_test_db_fail = FALSE;

$auth_test_kv_fail = TRUE;
unset($_SESSION['resetpw_grant']);
user_reset_grant_issue(9, 'reset@example.com') === FALSE && empty($_SESSION['resetpw_grant'])
	|| fail('Reset-grant storage failure must not create a Session-only authorization.');
$auth_test_kv_fail = FALSE;

$time = 4500;
$cleanup_failure_grant = user_reset_grant_issue(9, 'reset@example.com');
$auth_test_kv_fail = TRUE;
$cleanup_failure_epoch = user_password_commit(9, 'cleanup-failure-change');
$auth_test_kv_fail = FALSE;
$cleanup_failure_epoch === 3 && is_array(kv__get(user_reset_grant_key(9)))
	|| fail('A completed password change must not be reported as failed only because stale-grant cleanup failed.');
$_SESSION['resetpw_grant'] = $cleanup_failure_grant;
user_reset_grant_current() === FALSE && $auth_test_users[9]['password'] === 'cleanup-failure-change'
	|| fail('Auth-epoch binding must reject an old grant even when its best-effort KV cleanup previously failed.');

$stale_epoch_password = $auth_test_users[7]['password'];
user_password_commit_locked(7, 'stale-cas-write', array(), 0) === FALSE
	&& $auth_test_users[7]['password'] === $stale_epoch_password && $auth_test_users[7]['auth_epoch'] === 1
	|| fail('A stale expected auth epoch must not overwrite a newer password generation.');

// Shared verification-code limits survive Session replacement and enforce both dimensions.
$auth_test_cache = array();
$auth_test_locks = array();
$time = 5000;
$rate_email_key = user_email_code_rate_key('user_create', 'email', 'rate@example.com');
$rate_ip_key = user_email_code_rate_key('all', 'ip', '203.0.113.10');
strlen($rate_email_key) === 24 && strlen($rate_ip_key) === 24
	&& strpos($rate_email_key, 'rate@example.com') === FALSE
	|| fail('Verification-code rate keys must be fixed-length and must not disclose email or IP values.');
for($i = 0; $i < 3; $i++) {
	user_email_code_rate_take('user_create', 'rate@example.com', '203.0.113.10', $time) === 1
		|| fail('Verification-code rate limit rejected an allowed send before Session replacement.');
}
$_SESSION = array();
for($i = 0; $i < 2; $i++) {
	user_email_code_rate_take('user_create', 'rate@example.com', '203.0.113.10', $time) === 1
		|| fail('Verification-code rate limit must preserve the shared count after Session replacement.');
}
user_email_code_rate_take('user_create', 'rate@example.com', '203.0.113.10', $time) === 0
	|| fail('Action+email verification-code sends must stop after five attempts in one hour.');
user_email_code_rate_take('user_create', 'other@example.com', '203.0.113.10', $time) === 1
	|| fail('The IP dimension must leave a NAT-tolerant allowance after one email reaches its stricter limit.');
user_email_code_rate_take('user_create', 'rate@example.com', '203.0.113.11', $time) === 0
	|| fail('The action+email dimension must block changing IP addresses after the email limit is exhausted.');
for($i = 0; $i < 14; $i++) {
	user_email_code_rate_take('user_create', 'spread-'.$i.'@example.com', '203.0.113.10', $time) === 1
		|| fail('The IP dimension rejected an allowed send before its twenty-send NAT budget was exhausted.');
}
user_email_code_rate_take('user_resetpw', 'rotate@example.com', '203.0.113.10', $time) === 0
	|| fail('The global IP dimension must stop broad email and action rotation after twenty sends.');
user_email_code_rate_take('user_resetpw', 'fresh@example.com', '203.0.113.12', $time) === 1
	|| fail('An unrelated action, email and non-exhausted IP must keep an independent allowance.');
$time += 3601;
user_email_code_rate_take('user_create', 'rate@example.com', '203.0.113.10', $time) === 1
	|| fail('Verification-code send allowances must reopen after the one-hour window expires.');

$rate_state_before_lock_failure = $auth_test_cache;
$auth_test_lock_fail = TRUE;
user_email_code_rate_take('user_create', 'lock@example.com', '203.0.113.20', $time) === -1
	&& $auth_test_cache === $rate_state_before_lock_failure && empty($auth_test_locks)
	|| fail('Rate-limit lock failure must fail closed without mutating shared counters or leaking locks.');
$auth_test_lock_fail = FALSE;
$auth_test_cache_fail = TRUE;
user_email_code_rate_take('user_create', 'kv@example.com', '203.0.113.21', $time) === -1
	&& empty($auth_test_locks)
	|| fail('Rate-limit cache-backed KV failure must fail closed and release every acquired lock.');
$auth_test_cache_fail = FALSE;

// Migration discovery and write-after-DDL verification must both use the master view. Re-running
// against the already migrated master is idempotent, and a master read failure is never "missing".
$auth_epoch_migration_runner = require $root.'/database/migrations/0002_add_user_auth_epoch.php';
$auth_epoch_migration_runner->up('bbs_');
$auth_test_migration_primary_reads === 2 && $auth_test_migration_writes === 1 && $auth_test_migration_has_epoch
	|| fail('Auth-epoch migration must inspect, write and verify against the primary schema view.');
$auth_epoch_migration_runner->up('bbs_');
$auth_test_migration_primary_reads === 3 && $auth_test_migration_writes === 1
	|| fail('Auth-epoch migration must be idempotent against the primary schema view.');
$auth_test_migration_read_fail = TRUE;
$migration_failed_closed = FALSE;
try {
	$auth_epoch_migration_runner->up('bbs_');
} catch(RuntimeException $e) {
	$migration_failed_closed = strpos($e->getMessage(), 'Failed to inspect') !== FALSE;
}
$auth_test_migration_read_fail = FALSE;
$migration_failed_closed && $auth_test_migration_writes === 1
	|| fail('Auth-epoch migration must not treat a primary schema-read failure as an absent column.');

echo "OK: user auth safety checks passed\n";
