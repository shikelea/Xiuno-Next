<?php

$root = dirname(__DIR__);
$user_model = file_get_contents($root.'/model/user.func.php');
$user_route = file_get_contents($root.'/route/user.php');
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

strpos($workflow, 'php bin/check_user_auth_safety.php') !== FALSE
	|| fail('CI must run the user auth safety guard.');

echo "OK: user auth safety checks passed\n";
