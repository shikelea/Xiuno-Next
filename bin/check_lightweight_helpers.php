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

$errors = array();

if (conf_get('version') !== '4.4.5') {
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

$_REQUEST['helper_smoke_b64'] = base64_encode('xiuno');
if (param_base64('helper_smoke_b64') !== 'xiuno') {
	$errors[] = 'param_base64 did not decode raw base64 safely';
}
unset($_REQUEST['helper_smoke_b64']);

if (!xn_zip_safe_name('safe/path.txt') || xn_zip_safe_name('../unsafe.txt') || xn_zip_safe_name('/unsafe.txt')) {
	$errors[] = 'xn_zip_safe_name did not enforce zip path safety';
}

if (attach_download_filename("bad\r\nX-Test: 1\"name.txt") !== "badX-Test: 1'name.txt") {
	$errors[] = 'attach_download_filename did not strip header-control characters safely';
}

$_REQUEST[1] = 'noop';
include_once APP_PATH . 'admin/route/update.php';
unset($_REQUEST[1]);
$hash = str_repeat('a', 64);
if (update_parse_sha256_text($hash . '  v4.4.5.zip', 'v4.4.5.zip', 'v4.4.5') !== $hash) {
	$errors[] = 'update_parse_sha256_text did not parse targeted checksum lines';
}
if (update_parse_sha256_text(str_repeat('b', 64) . '  other.zip', 'v4.4.5.zip', 'v4.4.5') !== '') {
	$errors[] = 'update_parse_sha256_text accepted checksum for another file';
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
