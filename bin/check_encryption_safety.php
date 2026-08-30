<?php

define('DEBUG', 0);
define('APP_PATH', dirname(__DIR__).'/');
define('XIUNOPHP_PATH', APP_PATH.'xiunophp/');

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/encryption-safety';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_USER_AGENT'] = 'encryption-safety-agent';

$conf = include APP_PATH.'conf/conf.default.php';
$conf['auth_key'] = 'encryption-safety-auth-key';
$conf['cache']['enable'] = FALSE;
include XIUNOPHP_PATH.'xiunophp.min.php';

// Keep this cryptographic guard independent from a developer database while satisfying the
// primary-read credential contract used by persistent-token validation.
class EncryptionSafetyDb {
	public $errno = 0;
	public $errstr = '';
	public function find_one_master($table, $cond = array(), $orderby = array(), $col = array()) {
		return $table === 'user' && isset($cond['uid']) && intval($cond['uid']) === 42
			? array('uid'=>42, 'auth_epoch'=>0)
			: array();
	}
}
$_SERVER['db'] = new EncryptionSafetyDb();

function encryption_fail($message) {
	fwrite(STDERR, "[FAIL] $message\n");
	exit(1);
}

if(!function_exists('openssl_encrypt') || !function_exists('random_bytes')) {
	encryption_fail('AES-GCM requires openssl_encrypt() and random_bytes().');
}

$key = 'encryption-safety-test-key';
$plain = "uid\t1700000000\t42\tfingerprint";
$token = xn_encrypt($plain, $key);

if(!is_string($token) || strncmp($token, 'v2.', 3) !== 0) {
	encryption_fail('new tokens must use the v2 authenticated format.');
}
if(!preg_match('#^v2\.[A-Za-z0-9_]+$#D', $token)) {
	encryption_fail('new tokens must use the URL-safe v2 authenticated format.');
}
if(xn_decrypt($token, $key) !== $plain) {
	encryption_fail('v2 token did not round-trip.');
}

$empty = xn_encrypt('', $key);
if(!is_string($empty) || strncmp($empty, 'v2.', 3) !== 0 || xn_decrypt($empty, $key) !== '') {
	encryption_fail('empty plaintext did not round-trip through v2.');
}

$second = xn_encrypt($plain, $key);
if($second === $token) {
	encryption_fail('v2 token reused its nonce.');
}

$last = substr($token, -1);
$tampered = substr($token, 0, -1).($last === 'A' ? 'B' : 'A');
if(xn_decrypt($tampered, $key) !== FALSE) {
	encryption_fail('tampered v2 token was accepted.');
}
if(xn_decrypt(substr($token, 0, 12), $key) !== FALSE) {
	encryption_fail('truncated v2 token was accepted.');
}
if(xn_decrypt($token, 'wrong-key') !== FALSE) {
	encryption_fail('v2 token was accepted with the wrong key.');
}
foreach(array(
	'v2.',
	'v2.invalid',
	'v2.'.xn_urlencode(base64_encode(str_repeat('x', 27))),
	$token.'A',
	'v3.invalid',
) as $invalid) {
	if(xn_decrypt($invalid, $key) !== FALSE) {
		encryption_fail('invalid token was accepted: '.$invalid);
	}
}

$legacyPayload = function_exists('xiuno_encrypt') ? xiuno_encrypt($plain, $key) : xxtea_encrypt($plain, $key);
$legacy = xn_urlencode(base64_encode($legacyPayload));
if(xn_decrypt($legacy, $key) !== $plain) {
	encryption_fail('legacy XXTEA token no longer decrypts.');
}

require APP_PATH.'model/user.func.php';

$time = 1700000000;
$ip = '127.0.0.1';
$tokenkey = hash('sha256', xn_key());
$fingerprint = user_token_fingerprint();
foreach(array('v2', 'legacy') as $format) {
	foreach(array($time - 86400 * 30 => 42, $time - 86400 * 30 - 1 => FALSE) as $issuedAt => $expectedUid) {
		$payload = "$ip\t$issuedAt\t42\t$fingerprint";
		if($format === 'v2') {
			$userToken = xn_encrypt($payload, $tokenkey);
		} else {
			$legacyPayload = function_exists('xiuno_encrypt') ? xiuno_encrypt($payload, $tokenkey) : xxtea_encrypt($payload, $tokenkey);
			$userToken = xn_urlencode(base64_encode($legacyPayload));
		}
		$_REQUEST['bbs_token'] = $userToken;
		if(user_token_get_do() !== $expectedUid) {
			encryption_fail($format.' persistent token expiry check failed.');
		}
	}
}
unset($_REQUEST['bbs_token']);

echo "[OK] Authenticated encryption safety checks passed.\n";
