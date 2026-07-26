<?php

$root = dirname(__DIR__);
$check_model = file_get_contents($root.'/model/check.func.php');
$user_model = file_get_contents($root.'/model/user.func.php');
$user_route = file_get_contents($root.'/route/user.php');
$index_source = file_get_contents($root.'/index.inc.php');
$my_route = file_get_contents($root.'/route/my.php');
$workflow = file_get_contents($root.'/.github/workflows/ci.yml');

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

if(!function_exists('lang')) {
	function lang($key, $args = array()) {
		return $key;
	}
}
require_once $root.'/model/check.func.php';

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
strpos($token_gen, '"$ip	$time	$uid	$fingerprint"') !== FALSE
	|| fail('New persistent login tokens must carry the fingerprint field.');

$token_get = section_between($user_model, 'function user_token_get_do', 'function user_token_set');
strpos($token_get, 'count($arr) != 3 && count($arr) != 4') !== FALSE
	|| fail('Persistent token reader must explicitly handle legacy and current token shapes.');
strpos($token_get, 'hash_equals(user_token_fingerprint(), $_fingerprint)') !== FALSE
	|| fail('Current persistent tokens must be checked with hash_equals().');
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
$reset_issue_clear = strpos($code_issue, "if(\$prefix == 'user_resetpw') unset(\$_SESSION['resetpw_verify_ok']);");
$reset_issue_email = strpos($code_issue, "\$_SESSION[\$prefix.'_email'] = \$email;");
($reset_issue_clear !== FALSE && $reset_issue_email !== FALSE && $reset_issue_clear < $reset_issue_email)
	|| fail('Issuing a password-reset code must revoke an earlier password-reset verification.');

$code_verify = section_between($user_route, 'function user_email_code_verify', 'function user_email_code_rate_limit');
strpos($code_verify, '$time - $sess_time > 300') !== FALSE
	|| fail('Email verification codes must expire after five minutes.');
strpos($code_verify, '$attempts >= 5') !== FALSE
	|| fail('Email verification codes must cap failed attempts.');
strpos($code_verify, 'hash_equals($sess_code, (string)$code)') !== FALSE
	|| fail('Email verification codes must use hash_equals().');
strpos($code_verify, "\$_SESSION[\$prefix.'_code_attempts'] = \$attempts + 1") !== FALSE
	|| fail('Email verification code failures must increment attempts.');

$rate_limit = section_between($user_route, 'function user_email_code_rate_limit', 'function user_email_code_clear');
strpos($rate_limit, '$time - $window_start > 3600') !== FALSE
	|| fail('Email verification code send rate limit must have a one-hour window.');
strpos($rate_limit, '$send_count >= 5') !== FALSE
	|| fail('Email verification code sends must be capped in the one-hour window.');

strpos($user_route, "user_email_code_verify('user_create', \$email, \$code)") !== FALSE
	|| fail('User registration must use the shared email code verifier.');
strpos($user_route, "user_email_code_verify('user_resetpw', \$email, \$code)") !== FALSE
	|| fail('Password reset must use the shared email code verifier.');
strpos($user_route, "user_email_code_issue('user_create', \$email)") !== FALSE
	|| fail('User registration code sends must use the shared issuer.');
strpos($user_route, "user_email_code_issue('user_resetpw', \$email)") !== FALSE
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

$resetpw_complete = section_between($user_route, "} elseif(\$action == 'resetpw_complete')", "} elseif(\$action == 'send_code')");
$resetpw_update_pos = strpos($resetpw_complete, "user_update(\$_uid, array('password'=>\$password)) === FALSE AND message(-1, lang('password_modify_failed'));");
$resetpw_clear_pos = strpos($resetpw_complete, "user_email_code_clear('user_resetpw');");
($resetpw_update_pos !== FALSE && $resetpw_clear_pos !== FALSE && $resetpw_update_pos < $resetpw_clear_pos)
	|| fail('Password reset must report a failed password update before consuming its verification state.');

$identity = section_between($index_source, '$user = user_read($uid);', '$gid = empty($user)');
$missing_user_pos = strpos($identity, 'if($uid && empty($user))');
$clear_session_uid_pos = strpos($identity, "\$_SESSION['uid'] = 0;");
$clear_token_pos = strpos($identity, 'user_token_clear();');
($missing_user_pos !== FALSE && $clear_session_uid_pos !== FALSE && $clear_token_pos !== FALSE)
	|| fail('A missing user referenced by a Session or token must be cleared before authorization uses the uid.');

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

strpos($workflow, 'php bin/check_user_auth_safety.php') !== FALSE
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
$email_lookup_pos = strpos($login, 'user_read_by_email($email)');
$username_lookup_pos = strpos($login, 'user_read_by_username($email)');
($rate_check_pos !== FALSE && $email_lookup_pos !== FALSE && $rate_check_pos < $email_lookup_pos && $rate_check_pos < $username_lookup_pos)
	|| fail('Browser login must check login failure rate before credential lookup.');
substr_count($login, 'user_login_rate_fail($email);') >= 4
	|| fail('Browser login must record missing-account, invalid-password-format and bad-password failures.');
$password_verify_pos = strpos($login, 'user_verify_password($password, $_user)');
$rate_clear_pos = strpos($login, 'user_login_rate_clear($email);');
($password_verify_pos !== FALSE && $rate_clear_pos !== FALSE && $rate_clear_pos > $password_verify_pos)
	|| fail('Browser login must clear login failure rate after successful authentication.');

$password_change = section_between($my_route, "} elseif(\$action == 'password')", "} elseif(\$action == 'thread')");
$password_validate_pos = strpos($password_change, 'is_password($password_new, $err)');
$password_hash_pos = strpos($password_change, 'user_hash_password($password_new)');
($password_validate_pos !== FALSE && $password_hash_pos !== FALSE && $password_validate_pos < $password_hash_pos)
	|| fail('Password changes must reject empty or malformed new password digests before hashing.');

echo "OK: user auth safety checks passed\n";
