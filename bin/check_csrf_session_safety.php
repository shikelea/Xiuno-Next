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
		if(strpos($sessNew, 'xn_setcookie(\'cookie_test\', $cookie_test, $time + 86400);') === FALSE) {
			$errors[] = 'sess_new() must keep the cookie capability probe.';
		}
		if(preg_match('#else\s*\{(?:(?!^\s*\}).)*xn_setcookie\s*\(\s*\'cookie_test\'\s*,\s*\$cookie_test\s*,\s*\$time\s*\+\s*86400\s*\)(?:(?!^\s*\}).)*\breturn\s*;#ms', $sessNew)) {
			$errors[] = 'first page views must still create a session row so generated CSRF tokens persist for the next POST.';
		}
		if(strpos($sessNew, "db_insert('session', \$arr);") === FALSE) {
			$errors[] = 'sess_new() must insert a session row for new sid values.';
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
