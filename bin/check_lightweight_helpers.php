<?php

define('DEBUG', 0);
define('APP_PATH', dirname(__DIR__) . '/');

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/helper-smoke';
$_SERVER['REQUEST_METHOD'] = 'GET';

$conf = include APP_PATH . 'conf/conf.default.php';
$conf['log_path'] = APP_PATH . 'tmp/helper_smoke/';

include APP_PATH . 'xiunophp/xiunophp.php';
include_once APP_PATH . 'xiunophp/xn_zip.func.php';
include_once APP_PATH . 'model/attach.func.php';
include_once APP_PATH . 'model/misc.func.php';

$errors = array();

if (conf_get('version') !== '4.5.5') {
	$errors[] = 'conf_get(version) failed';
}

XiunoLogger::error('helper smoke', array(), 'helper_smoke');
$logfile = $conf['log_path'] . date('Ym', $_SERVER['time']) . '/helper_smoke.php';
if (!is_file($logfile)) {
	$errors[] = 'XiunoLogger did not write smoke log';
}

$response = XiunoHttp::get('http://127.0.0.1:9/', array('timeout' => 1, 'connect_timeout' => 1));
foreach (array('ok', 'code', 'headers', 'body', 'json', 'errno', 'errstr') as $key) {
	if (!array_key_exists($key, $response)) {
		$errors[] = 'XiunoHttp missing response key: ' . $key;
	}
}

$blocked = XiunoHttp::get('file:///etc/passwd');
if ($blocked['ok'] || strpos($blocked['errstr'], 'Only http and https') === FALSE) {
	$errors[] = 'XiunoHttp did not reject unsupported URL scheme';
}

$blocked = XiunoHttp::get("https://example.com/\r\nX-Test: 1");
if ($blocked['ok'] || strpos($blocked['errstr'], 'control characters') === FALSE) {
	$errors[] = 'XiunoHttp did not reject URL control characters';
}

if (http_get('file:///etc/passwd') !== FALSE) {
	$errors[] = 'legacy http_get did not reject unsupported URL scheme';
}

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_PORT'] = 80;
unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO'], $_SERVER['PHP_SELF'], $_SERVER['HTTP_REFERER'], $_REQUEST['referer']);
$previousHandler = set_error_handler(function($severity, $message, $file, $line) {
	throw new ErrorException($message, 0, $severity, $file, $line);
});
try {
	$urlPath = http_url_path();
	$referer = http_referer();
	if ($urlPath !== 'http://localhost/' || $referer !== './') {
		$errors[] = 'HTTP URL helpers returned an unsafe fallback when optional server headers were absent';
	}
} catch (Throwable $e) {
	$errors[] = 'HTTP URL helpers emitted a PHP 8 warning for absent optional headers: ' . $e->getMessage();
} finally {
	restore_error_handler();
}

$_REQUEST['helper_smoke_b64'] = base64_encode('xiuno');
if (param_base64('helper_smoke_b64') !== 'xiuno') {
	$errors[] = 'param_base64 did not decode raw base64 safely';
}
unset($_REQUEST['helper_smoke_b64']);

if (!xn_zip_safe_name('safe/path.txt') || xn_zip_safe_name('../unsafe.txt') || xn_zip_safe_name('/unsafe.txt')) {
	$errors[] = 'xn_zip_safe_name did not enforce zip path safety';
}

$lock_name = 'helper_smoke_' . getmypid();
$lockfile = $conf['tmp_path'] . 'lock_' . $lock_name . '.lock';
@unlink($lockfile);
if (!xn_lock_start($lock_name, 60)) {
	$errors[] = 'xn_lock_start did not create a fresh lock';
} elseif (xn_lock_start($lock_name, 60)) {
	$errors[] = 'xn_lock_start allowed duplicate lock acquisition';
}
if (!is_file($lockfile) || strpos(file_get_contents($lockfile), "\n") === FALSE) {
	$errors[] = 'xn_lock_start did not persist an owner token';
}
file_put_contents($lockfile, $_SERVER['time'] . "\nforeign-owner-token");
xn_lock_end($lock_name);
if (!is_file($lockfile)) {
	$errors[] = 'xn_lock_end removed a lock owned by another task';
}
@unlink($lockfile);
if (!xn_lock_start($lock_name, 60)) {
	$errors[] = 'xn_lock_start did not recreate a removed smoke lock';
}
xn_lock_end($lock_name);
if (is_file($lockfile)) {
	$errors[] = 'xn_lock_end did not remove the current owner lock';
}
@unlink($lockfile);

if (attach_download_filename("bad\r\nX-Test: 1\"name.txt") !== "badX-Test: 1'name.txt") {
	$errors[] = 'attach_download_filename did not strip header-control characters safely';
}

$_REQUEST[1] = 'noop';
include_once APP_PATH . 'admin/route/update.php';
unset($_REQUEST[1]);
$hash = str_repeat('a', 64);
if (update_parse_sha256_text($hash . '  v4.5.3.zip', 'v4.5.3.zip', 'v4.5.3') !== $hash) {
	$errors[] = 'update_parse_sha256_text did not parse targeted checksum lines';
}
if (update_parse_sha256_text(str_repeat('b', 64) . '  other.zip', 'v4.5.3.zip', 'v4.5.3') !== '') {
	$errors[] = 'update_parse_sha256_text accepted checksum for another file';
}
if (!update_public_ip_allowed('8.8.8.8')) {
	$errors[] = 'update_public_ip_allowed rejected a public IPv4 address';
}
foreach (array('127.0.0.1', '10.0.0.1', '169.254.169.254', '::1', '::ffff:127.0.0.1') as $blocked_ip) {
	if (update_public_ip_allowed($blocked_ip)) {
		$errors[] = 'update_public_ip_allowed accepted blocked IP: ' . $blocked_ip;
	}
}

@unlink($logfile);
@rmdir(dirname($logfile));
@rmdir($conf['log_path']);

if (!empty($errors)) {
	fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
	exit(1);
}

echo "Lightweight helper smoke OK\n";
exit(0);
