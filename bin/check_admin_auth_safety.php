<?php

$root = dirname(__DIR__);
$admin_func = file_get_contents($root.'/admin/admin.func.php');
$admin_index = file_get_contents($root.'/admin/index.inc.php');
$session_model = file_get_contents($root.'/model/session.func.php');
$misc = file_get_contents($root.'/xiunophp/misc.func.php');
$install = file_get_contents($root.'/install/index.php');
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

strpos($misc, 'function xn_cookie_secure()') !== FALSE
	|| fail('Core must expose a shared HTTPS-aware cookie Secure helper.');
strpos($misc, "HTTP_X_FORWARDED_PROTO") !== FALSE
	|| fail('Cookie Secure helper must account for HTTPS reverse-proxy headers.');
strpos($misc, 'function xn_setcookie($name, $value, $expires = 0, $path = \'\', $httponly = TRUE, $samesite = \'Lax\')') !== FALSE
	|| fail('Core must expose a shared hardened setcookie helper.');
foreach(array("'secure'=>xn_cookie_secure()", "'httponly'=>\$httponly", "'samesite'=>\$samesite") as $needle) {
	strpos($misc, $needle) !== FALSE || fail("Shared cookie helper must keep $needle.");
}

$token_check = section_between($admin_func, 'function admin_token_check', 'function admin_token_set');
strpos($token_check, "\$admin_token = _COOKIE('bbs_admin_token')") !== FALSE
	|| fail('Admin token check must read only the cookie token, not request parameters.');
strpos($token_check, 'param(\'bbs_admin_token\')') === FALSE
	|| fail('Admin token check must not allow request parameters to override the cookie token.');
strpos($token_check, '$token_parts = explode("\t", $s)') !== FALSE
	|| fail('Admin token check must validate decrypted token structure.');
strpos($token_check, 'count($token_parts) != 2') !== FALSE
	|| fail('Admin token check must reject malformed decrypted token structure.');
strpos($token_check, '(XN_ADMIN_BIND_IP && $_ip != $longip) || $time - intval($_time) > 3600') !== FALSE
	|| fail('Admin token check must expire on IP mismatch when IP binding is enabled, or after one hour.');
strpos($token_check, "xn_setcookie('bbs_admin_token', '', 0)") !== FALSE
	|| fail('Admin token failures must clear the token through the hardened cookie helper.');

$token_set = section_between($admin_func, 'function admin_token_set', 'function admin_token_clean');
strpos($token_set, 'param(\'bbs_admin_token\')') === FALSE
	|| fail('Admin token setter must not read the previous token from request parameters.');
strpos($token_set, "xn_setcookie('bbs_admin_token', \$admin_token, \$time + 3600)") !== FALSE
	|| fail('Admin token setter must use the hardened cookie helper.');

$token_clean = section_between($admin_func, 'function admin_token_clean', '// bootstrap style');
strpos($token_clean, "xn_setcookie('bbs_admin_token', '', \$time - 86400)") !== FALSE
	|| fail('Admin token cleanup must use the hardened cookie helper.');

strpos($session_model, "xn_setcookie('cookie_test', '', \$time - 86400, '/')") !== FALSE
	|| fail('Session cookie_test cleanup must use the hardened cookie helper.');
strpos($session_model, "xn_setcookie('cookie_test', \$cookie_test, \$time + 86400, '/')") !== FALSE
	|| fail('Session cookie_test write must use the hardened cookie helper.');
strpos($session_model, "ini_set('session.cookie_secure', xn_cookie_secure() ? 'On' : 'Off')") !== FALSE
	|| fail('Session cookie must use the shared HTTPS-aware Secure helper.');
strpos($session_model, "ini_set('session.cookie_samesite', 'Lax')") !== FALSE
	|| fail('Session cookie must set SameSite=Lax.');
strpos($admin_index, "xn_setcookie('bbs_sid', '', \$time - 86400)") !== FALSE
	|| fail('Admin session cleanup must use the hardened cookie helper.');
strpos($install, "xn_setcookie('lang', \$_lang, 0, '', TRUE)") !== FALSE
	|| fail('Installer language cookie must use the hardened cookie helper.');

strpos($workflow, 'php bin/check_admin_auth_safety.php') !== FALSE
	|| fail('CI must run the admin auth safety guard.');

echo "OK: admin auth safety checks passed\n";
