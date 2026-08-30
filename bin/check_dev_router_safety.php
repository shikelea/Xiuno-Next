<?php

$root = realpath(__DIR__.'/..');
if($root === FALSE) {
	fwrite(STDERR, "Unable to locate project root.\n");
	exit(1);
}

$router = $root.'/bin/dev_router.php';
$nginx_file = $root.'/docker/nginx/conf.d/default.conf';
$docker_smoke_file = $root.'/bin/check_docker_http_smoke.sh';
$workflow_file = $root.'/.github/workflows/ci.yml';
$readme_file = $root.'/README.md';
$contributing_file = $root.'/CONTRIBUTING.md';
$errors = array();
$skips = array();

function dev_router_fail($message) {
	global $errors;
	$errors[] = $message;
}

function dev_router_write($path, $content) {
	$parent = dirname($path);
	if(!is_dir($parent) && !mkdir($parent, 0777, TRUE) && !is_dir($parent)) {
		throw new RuntimeException('Unable to create fixture directory: '.$parent);
	}
	$written = file_put_contents($path, $content);
	if($written !== strlen($content)) throw new RuntimeException('Unable to write fixture file: '.$path);
}

function dev_router_remove_tree($path, $allowed_parent) {
	$path_real = realpath($path);
	$parent_real = realpath($allowed_parent);
	if($path_real === FALSE) return;
	if($parent_real === FALSE || dirname($path_real) !== $parent_real || strpos(basename($path_real), 'xiuno-dev-router-') !== 0) {
		throw new RuntimeException('Refusing to remove an unexpected fixture path.');
	}
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($path_real, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach($iterator as $item) {
		$item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
	}
	rmdir($path_real);
}

function dev_router_free_port() {
	$errno = 0;
	$errstr = '';
	$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
	if($socket === FALSE) throw new RuntimeException('Unable to reserve a local test port: '.$errstr);
	$name = stream_socket_get_name($socket, FALSE);
	fclose($socket);
	$pos = strrpos($name, ':');
	if($pos === FALSE) throw new RuntimeException('Unable to parse the local test port.');
	return intval(substr($name, $pos + 1));
}

function dev_router_http_get($url) {
	$parts = parse_url($url);
	$host = isset($parts['host']) ? $parts['host'] : '127.0.0.1';
	$port = isset($parts['port']) ? intval($parts['port']) : 80;
	$target = isset($parts['path']) && $parts['path'] !== '' ? $parts['path'] : '/';
	isset($parts['query']) AND $target .= '?'.$parts['query'];
	$errno = 0;
	$errstr = '';
	$socket = @stream_socket_client('tcp://'.$host.':'.$port, $errno, $errstr, 2);
	if($socket === FALSE) return array('status'=>0, 'body'=>'', 'headers'=>array());
	stream_set_timeout($socket, 2);
	fwrite($socket, "GET ".$target." HTTP/1.1\r\nHost: ".$host.":".$port."\r\nConnection: close\r\n\r\n");
	$response = stream_get_contents($socket);
	fclose($socket);
	if(!is_string($response)) return array('status'=>0, 'body'=>'', 'headers'=>array());
	$separator = strpos($response, "\r\n\r\n");
	$header_text = $separator === FALSE ? $response : substr($response, 0, $separator);
	$body = $separator === FALSE ? '' : substr($response, $separator + 4);
	$headers = explode("\r\n", $header_text);
	$status = 0;
	if(isset($headers[0]) && preg_match('#\s(\d{3})(?:\s|$)#', $headers[0], $matches)) $status = intval($matches[1]);
	return array('status'=>$status, 'body'=>$body, 'headers'=>$headers);
}

function dev_router_assert_response($base_url, $path, $status, $body_contains = NULL) {
	$response = dev_router_http_get($base_url.$path);
	if($response['status'] !== $status) {
		dev_router_fail($path.' expected HTTP '.$status.', got '.$response['status'].'. Body: '.$response['body']);
		return;
	}
	if($body_contains !== NULL && strpos($response['body'], $body_contains) === FALSE) {
		dev_router_fail($path.' response did not contain expected marker: '.$body_contains);
	}
}

foreach(array($router, $nginx_file, $docker_smoke_file, $workflow_file, $readme_file, $contributing_file) as $required_file) {
	if(!is_file($required_file)) dev_router_fail('Required development-router contract file is missing: '.$required_file);
}

$nginx = is_file($nginx_file) ? file_get_contents($nginx_file) : '';
$docker_smoke = is_file($docker_smoke_file) ? file_get_contents($docker_smoke_file) : '';
$workflow = is_file($workflow_file) ? file_get_contents($workflow_file) : '';
$readme = is_file($readme_file) ? file_get_contents($readme_file) : '';
$contributing = is_file($contributing_file) ? file_get_contents($contributing_file) : '';

strpos($nginx, 'location ~* ^/plugin/[^/]+/(install|unstall|upgrade|setting)\\.php$') !== FALSE
	|| dev_router_fail('Nginx must deny direct access to plugin lifecycle and setting scripts.');
strpos($nginx, 'location ~* ^/plugin/[^/]+/(hook|overwrite)/.*\\.php$') !== FALSE
	|| dev_router_fail('Nginx must deny direct access to plugin Hook and overwrite PHP fragments.');
strpos($nginx, 'location ~* ^/upload/.*\\.php$') !== FALSE
	|| dev_router_fail('Nginx must deny upload PHP paths case-insensitively.');
strpos($nginx, 'location ~* ^/plugin/.*\\.php$') === FALSE
	|| dev_router_fail('Nginx must not block every legacy plugin PHP endpoint without an explicit public-entry contract.');

$blocked_docker_paths = array(
	'/plugin/xiuno-http-smoke/install.php',
	'/plugin/xiuno-http-smoke/unstall.php',
	'/plugin/xiuno-http-smoke/upgrade.php',
	'/plugin/xiuno-http-smoke/setting.php',
	'/plugin/xiuno-http-smoke/hook/probe.php',
	'/plugin/xiuno-http-smoke/overwrite/probe.php',
);
foreach($blocked_docker_paths as $path) {
	strpos($docker_smoke, "assert_status '$path' '404'") !== FALSE
		|| dev_router_fail('Docker HTTP smoke must retain the blocked internal plugin path: '.$path);
}
$ci_runs_deterministic = strpos($workflow, 'php bin/run_checks.php --profile=deterministic') !== FALSE;
strpos($workflow, 'php bin/check_dev_router_safety.php') !== FALSE || $ci_runs_deterministic
	|| dev_router_fail('CI must run the development-router behavior guard.');
foreach(array('README.md'=>$readme, 'CONTRIBUTING.md'=>$contributing) as $name=>$documentation) {
	strpos($documentation, 'php -S 127.0.0.1:8081 -t . bin/dev_router.php') !== FALSE
		|| dev_router_fail($name.' must document the safe local development server command.');
	strpos($documentation, 'Nginx/PHP-FPM') !== FALSE
		|| dev_router_fail($name.' must separate local functional checks from deployment and performance validation.');
}

$temp_parent = realpath(sys_get_temp_dir());
$test_root = $temp_parent.DIRECTORY_SEPARATOR.'xiuno-dev-router-'.bin2hex(random_bytes(8));
$site_root = $test_root.DIRECTORY_SEPARATOR.'site';
$marker = $test_root.DIRECTORY_SEPARATOR.'executed.marker';
$process = NULL;
$pipes = array();

try {
	mkdir($site_root, 0777, TRUE);
	$fixture_router = $site_root.'/bin/dev_router.php';
	$router_source = file_get_contents($router);
	if($router_source === FALSE) throw new RuntimeException('Unable to read the tracked development router.');
	dev_router_write($fixture_router, $router_source);
	$front = <<<'PHP'
<?php
$front_scope_marker = array('value'=>'front-global');
function dev_router_front_scope_probe() {
	global $front_scope_marker;
	return $front_scope_marker;
}
$front_scope_result = dev_router_front_scope_probe();
if(!is_array($front_scope_result) || !isset($front_scope_result['value'])) {
	http_response_code(500);
	echo 'FRONT|SCOPE=missing';
	exit;
}
$xn_dev_router_previous_cwd = array('fixture-clobber');
$xn_dev_router_script = NULL;
header('Content-Type: text/plain; charset=UTF-8');
echo 'FRONT|SCRIPT='.(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '').'|URI='.(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '').'|SCOPE='.$front_scope_result['value'];
PHP;
	$admin = <<<'PHP'
<?php
$admin_scope_marker = array('value'=>'admin-global');
function dev_router_admin_scope_probe() {
	global $admin_scope_marker;
	return $admin_scope_marker;
}
$admin_scope_result = dev_router_admin_scope_probe();
if(!is_array($admin_scope_result) || !isset($admin_scope_result['value'])) {
	http_response_code(500);
	echo 'ADMIN|SCOPE=missing';
	exit;
}
header('Content-Type: text/plain; charset=UTF-8');
echo 'ADMIN|SCRIPT='.(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '').'|CWD='.basename(getcwd()).'|SCOPE='.$admin_scope_result['value'];
exit;
PHP;
	$install = <<<'PHP'
<?php
$install_scope_marker = array('value'=>'install-global');
function dev_router_install_scope_probe() {
	global $install_scope_marker;
	return $install_scope_marker;
}
$install_scope_result = dev_router_install_scope_probe();
if(!is_array($install_scope_result) || !isset($install_scope_result['value'])) {
	http_response_code(500);
	echo 'INSTALL|SCOPE=missing';
	exit;
}
header('Content-Type: text/plain; charset=UTF-8');
echo 'INSTALL|SCRIPT='.(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '').'|CWD='.basename(getcwd()).'|SCOPE='.$install_scope_result['value'];
PHP;
	$forbidden = '<?php file_put_contents('.var_export($marker, TRUE).', basename(__FILE__)."\\n", FILE_APPEND); echo "EXECUTED";';

	dev_router_write($site_root.'/index.php', $front);
	dev_router_write($site_root.'/admin/index.php', $admin);
	dev_router_write($site_root.'/install/index.php', $install);
	dev_router_write($site_root.'/view/app.css', 'body{color:#123456}');
	dev_router_write($site_root.'/admin/view/css/admin.css', '.admin{display:block}');
	dev_router_write($site_root.'/lang/zh-cn/bbs.js', 'window.lang={confirm_delete:"确认删除"};');
	dev_router_write($site_root.'/lang/zh-cn/bbs.php', '<?php echo "PRIVATE LANGUAGE PHP";');
	dev_router_write($site_root.'/lang/zh-cn/extra.js', 'window.extra=true;');
	dev_router_write($site_root.'/lang/custom_2-test/bbs.js', 'window.custom_locale=true;');
	dev_router_write($site_root.'/lang/custom_2-test/nested/bbs.js', 'window.nested_locale=true;');
	dev_router_write($site_root.'/lang/-invalid/bbs.js', 'window.invalid_start=true;');
	dev_router_write($site_root.'/lang/Upper/bbs.js', 'window.invalid_uppercase=true;');
	dev_router_write($site_root.'/lang/custom.locale/bbs.js', 'window.invalid_character=true;');
	$max_length_locale = '1'.str_repeat('a', 63);
	dev_router_write($site_root.'/lang/'.$max_length_locale.'/bbs.js', 'window.max_length_locale=true;');
	$overlong_locale = str_repeat('a', 65);
	dev_router_write($site_root.'/lang/'.$overlong_locale.'/bbs.js', 'window.invalid_length=true;');
	dev_router_write($site_root.'/plugin/synthetic/icon.png', 'synthetic-png');
	foreach(array(
		'/plugin/synthetic/install.php',
		'/plugin/synthetic/unstall.php',
		'/plugin/synthetic/upgrade.php',
		'/plugin/synthetic/setting.php',
		'/plugin/synthetic/public.php',
		'/plugin/synthetic/hook/probe.php',
		'/plugin/synthetic/overwrite/probe.php',
		'/upload/shell.php',
		'/admin/route/task.php',
		'/model/direct.php',
		'/install/install.func.php',
		'/secret.php',
	) as $relative) {
		dev_router_write($site_root.str_replace('/', DIRECTORY_SEPARATOR, $relative), $forbidden);
	}
	dev_router_write($site_root.'/view/htm/template.htm', 'PRIVATE TEMPLATE');
	dev_router_write($site_root.'/view/.hidden/secret.css', 'SECRET');
	dev_router_write($site_root.'/conf/internal-secret.php', '<?php echo "INTERNAL SECRET";');
	dev_router_write($test_root.'/outside.css', 'OUTSIDE');
	$symlink_created = @symlink($test_root.'/outside.css', $site_root.'/view/escape.css');
	$internal_symlink_created = @symlink($site_root.'/conf/internal-secret.php', $site_root.'/view/internal-leak.css');
	$template_symlink_created = @symlink($site_root.'/view/htm/template.htm', $site_root.'/view/template-leak.css');
	$plugin_symlink_created = @symlink($site_root.'/plugin/synthetic/install.php', $site_root.'/plugin/synthetic/install-leak.css');

	$port = dev_router_free_port();
	$base_url = 'http://127.0.0.1:'.$port;
	$command = array(PHP_BINARY, '-S', '127.0.0.1:'.$port, '-t', $site_root, $fixture_router);
	$descriptors = array(
		0=>array('pipe', 'r'),
		1=>array('file', $test_root.'/php-server.stdout.log', 'ab'),
		2=>array('file', $test_root.'/php-server.stderr.log', 'ab'),
	);
	$process = proc_open($command, $descriptors, $pipes, $site_root);
	if(!is_resource($process)) throw new RuntimeException('Unable to start the PHP development server fixture.');
	fclose($pipes[0]);

	$ready = FALSE;
	for($attempt = 0; $attempt < 50; $attempt++) {
		$response = dev_router_http_get($base_url.'/view/app.css');
		if($response['status'] === 200 && strpos($response['body'], 'body{color:#123456}') !== FALSE) {
			$ready = TRUE;
			break;
		}
		$status = proc_get_status($process);
		if(empty($status['running'])) break;
		usleep(100000);
	}
	if(!$ready) throw new RuntimeException('PHP development server fixture did not become ready.');

	dev_router_assert_response($base_url, '/', 200, 'FRONT|SCRIPT=/index.php');
	dev_router_assert_response($base_url, '/', 200, '|SCOPE=front-global');
	dev_router_assert_response($base_url, '/thread-1.htm', 200, '|SCOPE=front-global');
	dev_router_assert_response($base_url, '/index.php?forum-1.htm', 200, '|SCOPE=front-global');
	dev_router_assert_response($base_url, '/admin/', 200, 'ADMIN|SCRIPT=/admin/index.php|CWD=admin');
	dev_router_assert_response($base_url, '/admin/', 200, '|SCOPE=admin-global');
	dev_router_assert_response($base_url, '/admin/plugin-list.htm', 200, '|SCOPE=admin-global');
	// The admin fixture exits; a later front request proves shutdown restored the working directory.
	dev_router_assert_response($base_url, '/after-admin.htm', 200, '|SCOPE=front-global');
	dev_router_assert_response($base_url, '/install/', 200, 'INSTALL|SCRIPT=/install/index.php|CWD=install');
	dev_router_assert_response($base_url, '/install/', 200, '|SCOPE=install-global');
	dev_router_assert_response($base_url, '/install/index.php?action=db', 200, '|SCOPE=install-global');

	dev_router_assert_response($base_url, '/view/app.css', 200, 'body{color:#123456}');
	dev_router_assert_response($base_url, '/admin/view/css/admin.css', 200, '.admin{display:block}');
	dev_router_assert_response($base_url, '/lang/zh-cn/bbs.js', 200, 'confirm_delete');
	dev_router_assert_response($base_url, '/lang/custom_2-test/bbs.js', 200, 'custom_locale');
	dev_router_assert_response($base_url, '/lang/'.$max_length_locale.'/bbs.js', 200, 'max_length_locale');
	dev_router_assert_response($base_url, '/plugin/synthetic/icon.png', 200, 'synthetic-png');
	foreach(array(
		'/view/missing.css',
		'/admin/view/css/missing.css',
		'/plugin/synthetic/missing.png',
		'/plugin/synthetic/install.php',
		'/plugin/synthetic/unstall.php',
		'/plugin/synthetic/upgrade.php',
		'/plugin/synthetic/setting.php',
		'/plugin/synthetic/public.php',
		'/plugin/synthetic/hook/probe.php',
		'/plugin/synthetic/overwrite/probe.php',
		'/upload/shell.php',
		'/admin/route/task.php',
		'/model/direct.php',
		'/install/install.func.php',
		'/secret.php',
		'/lang/zh-cn/bbs.php',
		'/lang/zh-cn/extra.js',
		'/lang/custom_2-test/nested/bbs.js',
		'/lang/custom_2-test%2Fnested/bbs.js',
		'/lang/-invalid/bbs.js',
		'/lang/Upper/bbs.js',
		'/lang/custom.locale/bbs.js',
		'/lang/'.$overlong_locale.'/bbs.js',
		'/view/htm/template.htm',
		'/view/.hidden/secret.css',
		'/%2e/view/app.css',
		'/%2e%2e/outside.css',
		'/view%5C..%5Csecret.php',
	) as $blocked_path) {
		dev_router_assert_response($base_url, $blocked_path, 404);
	}
	$symlink_checks = array(
		array($symlink_created, '/view/escape.css'),
		array($internal_symlink_created, '/view/internal-leak.css'),
		array($template_symlink_created, '/view/template-leak.css'),
		array($plugin_symlink_created, '/plugin/synthetic/install-leak.css'),
	);
	foreach($symlink_checks as $symlink_check) {
		if($symlink_check[0]) {
			dev_router_assert_response($base_url, $symlink_check[1], 404);
		} else {
			$skips[] = 'symlink creation is unavailable; boundary fixture was not exercised: '.$symlink_check[1];
		}
	}
	if(is_file($marker)) dev_router_fail('A forbidden PHP fixture was executed through the development router.');
	$server_errors = @file_get_contents($test_root.'/php-server.stderr.log');
	if(is_string($server_errors) && preg_match('/(?:Fatal error|Uncaught (?:TypeError|Error))/i', $server_errors)) {
		dev_router_fail('An entry point could corrupt development-router cleanup state: '.$server_errors);
	}
} catch(Throwable $e) {
	dev_router_fail($e->getMessage());
} finally {
	if(is_resource($process)) {
		@proc_terminate($process);
		for($attempt = 0; $attempt < 20; $attempt++) {
			$status = proc_get_status($process);
			if(empty($status['running'])) break;
			usleep(50000);
		}
		foreach($pipes as $pipe) is_resource($pipe) AND fclose($pipe);
		@proc_close($process);
	}
	try {
		is_dir($test_root) AND dev_router_remove_tree($test_root, $temp_parent);
	} catch(Throwable $cleanup_error) {
		dev_router_fail($cleanup_error->getMessage());
	}
}

if($errors) {
	fwrite(STDERR, implode(PHP_EOL, $errors).PHP_EOL);
	exit(1);
}

if($skips) {
	echo "OK: safe PHP development router behavior and Web entry guards passed for available fixtures\n";
	foreach($skips as $skip) echo 'SKIP: '.$skip.PHP_EOL;
} else {
	echo "OK: safe PHP development router behavior and Web entry guards passed\n";
}
