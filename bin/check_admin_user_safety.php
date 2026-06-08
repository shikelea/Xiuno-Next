<?php

$root = dirname(__DIR__);
$user_route = file_get_contents($root.'/admin/route/user.php');

function fail($message) {
	fwrite(STDERR, "FAIL: $message\n");
	exit(1);
}

function lang($key, $arr = array()) {
	return $key.(isset($arr['length']) ? ':'.$arr['length'] : '');
}

include $root.'/model/check.func.php';

function section_between($source, $start, $end) {
	$start_pos = strpos($source, $start);
	if($start_pos === FALSE) fail("Missing section start: $start");
	$end_pos = strpos($source, $end, $start_pos + strlen($start));
	if($end_pos === FALSE) fail("Missing section end after $start: $end");
	return substr($source, $start_pos, $end_pos - $start_pos);
}

$create = section_between($user_route, "} elseif(\$action == 'create')", "} elseif(\$action == 'update')");
strpos($create, "empty(\$username) AND message('username', lang('please_input_username'));") !== FALSE
	|| fail('Admin user create must require username.');
strpos($create, "empty(\$password) AND message('password', lang('please_input_password'));") !== FALSE
	|| fail('Admin user create must require password.');
strpos($create, "!isset(\$grouplist[\$_gid])") !== FALSE
	|| fail('Admin user create must validate selected group.');
strpos($create, '!is_email($email, $err)') !== FALSE
	|| fail('Admin user create must validate email unconditionally.');
strpos($create, '!is_username($username, $err)') !== FALSE
	|| fail('Admin user create must validate username unconditionally.');
strpos($create, '!is_password(md5($password), $err)') !== FALSE
	|| fail('Admin user create must reject empty or invalid password before hashing.');

$update = section_between($user_route, "} elseif(\$action == 'update')", "} elseif(\$action == 'delete')");
strpos($update, "empty(\$_user) AND message('username', lang('uid_not_exists'));") !== FALSE
	|| fail('Admin user update GET must reject missing users before rendering.');
strpos($update, "empty(\$old) AND message('username', lang('uid_not_exists'));") !== FALSE
	|| fail('Admin user update POST must reject missing users.');
strpos($update, "empty(\$email) AND message('email', lang('please_input_email'));") !== FALSE
	|| fail('Admin user update must not allow empty email.');
strpos($update, "empty(\$username) AND message('username', lang('please_input_username'));") !== FALSE
	|| fail('Admin user update must not allow empty username.');
strpos($update, "!isset(\$grouplist[\$_gid])") !== FALSE
	|| fail('Admin user update must validate selected group.');
strpos($update, '!is_email($email, $err)') !== FALSE
	|| fail('Admin user update must validate email unconditionally.');
strpos($update, '!is_username($username, $err)') !== FALSE
	|| fail('Admin user update must validate username unconditionally.');
strpos($update, '!is_password(md5($password), $err)') !== FALSE
	|| fail('Admin user update must validate non-empty password changes before hashing.');
strpos($update, 'message(2, $err)') === FALSE
	|| fail('Admin user update must report validation errors against field names, not numeric codes.');

$err = '';
is_email(str_repeat('a', 23).'@test.com', $err) === TRUE
	|| fail('Email validation must allow addresses at the 32-character storage boundary.');
$err = '';
is_email(str_repeat('a', 28).'@test.com', $err) === FALSE
	|| fail('Email validation must reject addresses longer than the 32-character storage boundary.');
strpos($err, 'email_too_long') !== FALSE
	|| fail('Long email validation must report the email_too_long error.');

echo "OK: admin user safety checks passed\n";
