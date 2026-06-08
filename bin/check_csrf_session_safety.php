<?php

$root = dirname(__DIR__) . '/';
$session = file_get_contents($root . 'model/session.func.php');
$errors = array();

if($session === FALSE) {
	$errors[] = 'failed to read model/session.func.php';
} else {
	if(!preg_match('#function\s+sess_new\s*\(\s*\$sid\s*\)(.*?)(?=^function\s+\w|\z)#ms', $session, $m)) {
		$errors[] = 'sess_new() must exist.';
	} else {
		$sessNew = $m[1];
		if(strpos($sessNew, "xn_setcookie('cookie_test', '', \$time - 86400, '/');") === FALSE) {
			$errors[] = 'sess_new() must clear the cookie capability probe on the site-wide path.';
		}
		if(strpos($sessNew, "xn_setcookie('cookie_test', \$cookie_test, \$time + 86400, '/');") === FALSE) {
			$errors[] = 'sess_new() must keep the cookie capability probe on the site-wide path.';
		}
		if(preg_match('#else\s*\{(?:(?!^\s*\}).)*xn_setcookie\s*\(\s*\'cookie_test\'\s*,\s*\$cookie_test\s*,\s*\$time\s*\+\s*86400\s*(?:,\s*\'/\'\s*)?\)(?:(?!^\s*\}).)*\breturn\s*;#ms', $sessNew)) {
			$errors[] = 'first page views must still create a session row so generated CSRF tokens persist for the next POST.';
		}
		if(strpos($sessNew, "db_insert('session', \$arr);") === FALSE) {
			$errors[] = 'sess_new() must insert a session row for new sid values.';
		}
	}

	if(!preg_match('#function\s+sess_start\s*\(\s*\)(.*?)(?=^function\s+\w|\z)#ms', $session, $m)) {
		$errors[] = 'sess_start() must exist.';
	} else {
		$sessStart = $m[1];
		if(strpos($sessStart, "ini_set('session.cookie_path', '/');") === FALSE) {
			$errors[] = 'sess_start() must use the site-wide session cookie path.';
		}
		if(strpos($sessStart, "\$admin_cookie_path = substr(\$script_name, 0, \$admin_pos + 6);") === FALSE) {
			$errors[] = 'sess_start() must compute the stale admin-scoped cookie path from SCRIPT_NAME.';
		}
		if(strpos($sessStart, "xn_setcookie('bbs_sid', '', \$time - 86400, \$admin_cookie_path);") === FALSE) {
			$errors[] = 'sess_start() must expire stale admin-scoped session cookies.';
		}
		if(strpos($sessStart, "xn_setcookie('cookie_test', '', \$time - 86400, \$admin_cookie_path);") === FALSE) {
			$errors[] = 'sess_start() must expire stale admin-scoped cookie probe cookies.';
		}
	}
}

if(!empty($errors)) {
	fwrite(STDERR, implode(PHP_EOL, $errors).PHP_EOL);
	exit(1);
}

echo "OK: CSRF session safety checks passed\n";
exit(0);

?>
