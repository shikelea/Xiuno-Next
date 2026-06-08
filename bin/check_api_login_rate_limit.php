<?php

define('DEBUG', 1);
define('APP_PATH', dirname(__DIR__) . '/');

$time = 1700000000;
$longip = ip2long('203.0.113.10');
$cache = array();
$cache_life = array();
$deleted = array();
$locks = array();
$errors = array();

function xn_key($fromso = TRUE) {
	return 'unit-test-auth-key';
}

function cache_get($key) {
	global $cache, $cache_get_should_fail;
	if(!empty($cache_get_should_fail)) return FALSE;
	return array_key_exists($key, $cache) ? $cache[$key] : NULL;
}

function cache_set($key, $value, $life = 0) {
	global $cache, $cache_life, $cache_set_should_fail;
	if(!empty($cache_set_should_fail)) return FALSE;
	$cache[$key] = $value;
	$cache_life[$key] = $life;
	return TRUE;
}

function cache_delete($key) {
	global $cache, $deleted;
	$deleted[] = $key;
	unset($cache[$key]);
	return TRUE;
}

function xn_lock_start($lockname = '', $life = 10) {
	global $locks, $lock_start_should_fail;
	if(!empty($lock_start_should_fail)) return FALSE;
	if(!empty($locks[$lockname])) return FALSE;
	$locks[$lockname] = 'locked';
	return TRUE;
}

function xn_lock_end($lockname = '') {
	global $locks;
	unset($locks[$lockname]);
	return TRUE;
}

require APP_PATH.'model/user.func.php';

function assert_true($condition, $message) {
	global $errors;
	if(!$condition) $errors[] = $message;
}

$email = 'Admin@Example.COM ';
$key = user_login_rate_key($email);

assert_true(strpos($key, 'admin') === FALSE && strpos($key, 'example') === FALSE, 'rate limit key must not leak the account name');
assert_true(strlen($key) <= 32, 'rate limit key must stay within cache key limit');
assert_true(user_login_rate_limited($email) === FALSE, 'fresh account must not be limited');

for($i = 0; $i < 9; $i++) {
	user_login_rate_fail($email);
}
assert_true(user_login_rate_limited($email) === FALSE, 'nine failures must still be allowed');

user_login_rate_fail($email);
assert_true(user_login_rate_limited($email) === TRUE, 'ten failures must be limited');
assert_true(isset($cache_life[$key]) && $cache_life[$key] === 900, 'rate limit record must expire after 900 seconds');
assert_true(empty($locks), 'rate limit lock must be released after failure count write');

$other_account_key = user_login_rate_key('other@example.com');
assert_true($other_account_key !== $key, 'different accounts must not share rate limit keys');

$longip = ip2long('198.51.100.20');
$other_ip_key = user_login_rate_key($email);
assert_true($other_ip_key !== $key, 'different IPs must not share rate limit keys');

$longip = ip2long('203.0.113.10');
user_login_rate_clear($email);
assert_true(!isset($cache[$key]), 'successful login must clear the rate limit record');
assert_true(in_array($key, $deleted, TRUE), 'successful login must delete the rate limit key');

$cache_set_should_fail = TRUE;
assert_true(user_login_rate_fail('fail@example.com') === FALSE, 'cache write failure must return false without fatal');
assert_true(empty($locks), 'rate limit lock must be released after cache write failure');

$cache_set_should_fail = FALSE;
$lock_start_should_fail = TRUE;
$lock_fail_email = 'lock-fail@example.com';
$lock_fail_key = user_login_rate_key($lock_fail_email);
assert_true(user_login_rate_fail($lock_fail_email) === TRUE, 'lock contention must still write a limiting record');
assert_true(user_login_rate_limited($lock_fail_email) === TRUE, 'lock contention must fail closed into a limited state');
assert_true(isset($cache_life[$lock_fail_key]) && $cache_life[$lock_fail_key] === 900, 'lock contention limiting record must expire after 900 seconds');

$cache_get_should_fail = TRUE;
assert_true(user_login_rate_limited('cache-fail@example.com') === TRUE, 'cache read failure must fail closed into a limited state');

if(!empty($errors)) {
	fwrite(STDERR, implode(PHP_EOL, $errors).PHP_EOL);
	exit(1);
}

echo "API login rate limit OK\n";
exit(0);
