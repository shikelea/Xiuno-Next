<?php

$root = realpath(__DIR__.'/..');
if($root === FALSE) {
	fwrite(STDERR, "FAIL: unable to resolve repository root\n");
	exit(1);
}
require_once __DIR__.'/benchmark.func.php';

function fail_benchmark_guard($message) {
	fwrite(STDERR, 'FAIL: '.$message."\n");
	exit(1);
}

function expect_benchmark_guard_throw($callback, $needle, $message) {
	try {
		$callback();
	} catch(Throwable $e) {
		strpos($e->getMessage(), $needle) !== FALSE
			|| fail_benchmark_guard($message.' Wrong error: '.$e->getMessage());
		return;
	}
	fail_benchmark_guard($message.' No exception was thrown.');
}

function benchmark_guard_snapshot($url, $body, $title, $overrides = array()) {
	$body = '<!doctype html><html><head><title>'.htmlspecialchars($title, ENT_QUOTES, 'UTF-8').'</title></head><body>'.$body.'</body></html>';
	$snapshot = array(
		'url'=>$url,
		'http_status'=>200,
		'effective_url'=>$url,
		'content_type'=>'text/html; charset=UTF-8',
		'ttfb_ms'=>12.5,
		'redirects'=>0,
		'request_ids'=>array('0123456789abcdef0123456789abcdef'),
		'request_id'=>'0123456789abcdef0123456789abcdef',
		'sha256'=>hash('sha256', $body),
		'bytes'=>strlen($body),
		'title'=>benchmark_extract_title($body),
		'body'=>$body,
	);
	return array_replace($snapshot, $overrides);
}

$arguments = array(
	'benchmark.php',
	'--url=http://127.0.0.1:8081/bench',
	'--fid=7',
	'--tid=19',
	'--dataset=seed-2026-08',
	'--plugin-set=core-only',
	'--cache-state=warm',
	'--requests=20',
	'--detail-requests=10',
	'--concurrency=5',
	'--ttfb-samples=2',
);
$config = benchmark_parse_cli($arguments, array());
$config['base_url'] === 'http://127.0.0.1:8081/bench/'
	|| fail_benchmark_guard('the explicit base URL must be normalized with one trailing slash.');
$config['fid'] === 7 && $config['tid'] === 19
	|| fail_benchmark_guard('explicit fid/tid values must be retained.');
$config['dataset'] === 'seed-2026-08' && $config['plugin_set'] === 'core-only' && $config['cache_state'] === 'warm'
	|| fail_benchmark_guard('comparison labels must be retained.');
$config['requests'] === 20 && $config['detail_requests'] === 10 && $config['concurrency'] === 5 && $config['ttfb_samples'] === 2
	|| fail_benchmark_guard('explicit load parameters must be retained.');

expect_benchmark_guard_throw(
	function() { benchmark_parse_cli(array('benchmark.php'), array()); },
	'Missing required benchmark option: --url',
	'the benchmark must not fall back to a default URL.'
);
expect_benchmark_guard_throw(
	function() use ($arguments) {
		$missing = array_values(array_filter($arguments, function($argument) { return strpos($argument, '--plugin-set=') !== 0; }));
		benchmark_parse_cli($missing, array());
	},
	'Missing required benchmark option: --plugin-set',
	'the plugin-set comparison label must be explicit.'
);
expect_benchmark_guard_throw(
	function() use ($arguments) {
		$invalid = $arguments;
		$invalid[1] = '--url=http://127.0.0.1:8081/?redirect=1';
		benchmark_parse_cli($invalid, array());
	},
	'without credentials, query, or fragment',
	'the base URL must not hide an endpoint query.'
);

$endpoints = benchmark_endpoints($config);
$endpoints === array(
	'home'=>'http://127.0.0.1:8081/bench/',
	'forum'=>'http://127.0.0.1:8081/bench/?forum-7.htm',
	'thread'=>'http://127.0.0.1:8081/bench/?thread-19.htm',
) || fail_benchmark_guard('endpoint construction must use the explicit fid/tid values.');

$valid = array(
	'home'=>benchmark_guard_snapshot(
		$endpoints['home'],
		'<script>$(\'li[data-active="fid-0"]\').addClass(\'active\');</script>',
		'Benchmark Home'
	),
	'forum'=>benchmark_guard_snapshot(
		$endpoints['forum'],
		'<a href="?forum-7.htm">Forum</a><a href="?thread-create-7.htm">Create</a>',
		'Benchmark Forum'
	),
	'thread'=>benchmark_guard_snapshot(
		$endpoints['thread'],
		'<a href="?thread-19.htm">Thread</a><div class="card card-postlist"></div>',
		'Benchmark Thread'
	),
);
benchmark_validate_semantics($valid, $config);

expect_benchmark_guard_throw(
	function() use ($valid) {
		$redirect = $valid['home'];
		$redirect['http_status'] = 302;
		$redirect['redirects'] = 1;
		$redirect['effective_url'] = 'http://127.0.0.1:8081/install/';
		benchmark_validate_snapshot('home', $redirect);
	},
	'must return direct HTTP 200',
	'a redirect to a successful installer/login page must not pass preflight.'
);
expect_benchmark_guard_throw(
	function() use ($valid) {
		$wrong_type = $valid['home'];
		$wrong_type['content_type'] = 'application/json';
		benchmark_validate_snapshot('home', $wrong_type);
	},
	'Content-Type text/html',
	'a non-HTML response must not pass preflight.'
);
expect_benchmark_guard_throw(
	function() use ($valid) {
		$missing_id = $valid['home'];
		$missing_id['request_ids'] = array();
		benchmark_validate_snapshot('home', $missing_id);
	},
	'exactly one valid X-Request-ID',
	'a response without a valid Request ID must not pass preflight.'
);
expect_benchmark_guard_throw(
	function() use ($valid, $config) {
		$confused = $valid;
		$confused['thread'] = $valid['forum'];
		benchmark_validate_semantics($confused, $config);
	},
	'Benchmark thread',
	'a forum page must not be accepted as a thread page.'
);
expect_benchmark_guard_throw(
	function() use ($valid, $config) {
		$same_title = $valid;
		$same_title['forum']['title'] = $same_title['home']['title'];
		benchmark_validate_semantics($same_title, $config);
	},
	'share the same HTML title',
	'three endpoints with indistinguishable titles must fail closed.'
);

$custom_config = $config;
$custom_config['markers'] = array('home'=>'HOME_ONLY', 'forum'=>'FORUM_ONLY', 'thread'=>'THREAD_ONLY');
$custom = array(
	'home'=>benchmark_guard_snapshot($endpoints['home'], 'HOME_ONLY', 'Custom Home'),
	'forum'=>benchmark_guard_snapshot($endpoints['forum'], 'FORUM_ONLY', 'Custom Forum'),
	'thread'=>benchmark_guard_snapshot($endpoints['thread'], 'THREAD_ONLY', 'Custom Thread'),
);
benchmark_validate_semantics($custom, $custom_config);
$custom_same_title = $custom;
$custom_same_title['home']['title'] = 'Shared Theme Title';
$custom_same_title['forum']['title'] = 'Shared Theme Title';
$custom_same_title['thread']['title'] = 'Shared Theme Title';
benchmark_validate_semantics($custom_same_title, $custom_config);
expect_benchmark_guard_throw(
	function() use ($custom, $custom_config) {
		$ambiguous = $custom;
		$ambiguous['forum']['body'] .= 'HOME_ONLY';
		$ambiguous['forum']['sha256'] = hash('sha256', $ambiguous['forum']['body']);
		benchmark_validate_semantics($ambiguous, $custom_config);
	},
	'also appears on forum',
	'an explicit theme marker must be unique to one page.'
);

$ab_output = <<<'TEXT'
Server Software:        nginx
Complete requests:      20
Failed requests:        0
Requests per second:    125.50 [#/sec] (mean)
Time per request:       39.841 [ms] (mean)
Time per request:       7.968 [ms] (mean, across all concurrent requests)
TEXT;
$metrics = benchmark_parse_ab_output($ab_output, 20);
$metrics['complete_requests'] === 20 && $metrics['failed_requests'] === 0
	&& $metrics['non_2xx_responses'] === 0 && $metrics['requests_per_second'] === 125.5
	|| fail_benchmark_guard('valid ApacheBench output must produce comparable metrics.');
expect_benchmark_guard_throw(
	function() use ($ab_output) { benchmark_parse_ab_output($ab_output."\nNon-2xx responses:      20\n", 20); },
	'non-2xx responses',
	'ApacheBench redirects and other non-2xx responses must fail the run.'
);

if(PHP_OS_FAMILY === 'Windows') {
	$contract_parent = sys_get_temp_dir().DIRECTORY_SEPARATOR.'xiuno-benchmark-contract-'.bin2hex(random_bytes(4));
	$configured_output = $contract_parent.DIRECTORY_SEPARATOR.'results';
	try {
		$resolved_output = benchmark_prepare_output_dir($root, $configured_output);
		$expected_output = realpath($configured_output);
		$expected_output !== FALSE && $resolved_output === $expected_output
			|| fail_benchmark_guard('a drive-qualified output path must not be prefixed with the repository root.');
	} finally {
		is_dir($configured_output) && @rmdir($configured_output);
		is_dir($contract_parent) && @rmdir($contract_parent);
	}
}
benchmark_is_absolute_path('X:\\xiuno-benchmark\\output', 'Windows')
	|| fail_benchmark_guard('a drive-qualified backslash path must be recognized as absolute.');
benchmark_is_absolute_path('F:/xinuocs/benchmark-output', 'Windows')
	|| fail_benchmark_guard('a drive-qualified slash path must be recognized as absolute.');
benchmark_is_absolute_path('\\\\server\\share\\benchmark-output', 'Windows')
	|| fail_benchmark_guard('a Windows UNC path must be recognized as absolute.');
benchmark_is_absolute_path('//server/share/benchmark-output', 'Windows')
	|| fail_benchmark_guard('a forward-slash Windows UNC path must be recognized as absolute.');
!benchmark_is_absolute_path('\\rooted-on-current-drive', 'Windows')
	&& !benchmark_is_absolute_path('/rooted-on-current-drive', 'Windows')
	|| fail_benchmark_guard('a current-drive rooted Windows path must not be classified as fully qualified.');
!benchmark_is_absolute_path('reports\\benchmark-output', 'Windows')
	|| fail_benchmark_guard('a relative backslash path must remain relative to the repository root.');
benchmark_is_absolute_path('/var/tmp/benchmark-output', 'Linux')
	|| fail_benchmark_guard('a Unix rooted path must be recognized as absolute.');
!benchmark_is_absolute_path('X:\\xiuno-benchmark\\output', 'Linux')
	&& !benchmark_is_absolute_path('F:/xinuocs/benchmark-output', 'Linux')
	&& !benchmark_is_absolute_path('\\rooted-on-windows-only', 'Linux')
	|| fail_benchmark_guard('Windows path syntax must not be misclassified as Unix absolute syntax.');
expect_benchmark_guard_throw(
	function() { benchmark_validate_output_path_style('X:\\xiuno-benchmark\\output', 'Linux'); },
	'uses Windows path syntax on a non-Windows host',
	'a Windows drive path on Unix must fail instead of creating a drive-named repository directory.'
);
expect_benchmark_guard_throw(
	function() { benchmark_validate_output_path_style('F:drive-relative', 'Linux'); },
	'uses Windows path syntax on a non-Windows host',
	'a Windows drive-relative path on Unix must fail instead of becoming a repository subdirectory.'
);
expect_benchmark_guard_throw(
	function() { benchmark_validate_output_path_style('\\rooted-on-windows-only', 'Linux'); },
	'uses Windows path syntax on a non-Windows host',
	'a rooted backslash path on Unix must fail instead of becoming a literal repository filename.'
);
benchmark_validate_output_path_style('reports/benchmark-output', 'Linux') === 'reports/benchmark-output'
	&& benchmark_validate_output_path_style('X:\\xiuno-benchmark\\output', 'Windows') === 'X:\\xiuno-benchmark\\output'
	&& benchmark_validate_output_path_style('\\\\server\\share\\benchmark-output', 'Windows') === '\\\\server\\share\\benchmark-output'
	|| fail_benchmark_guard('native and portable relative benchmark output paths must remain valid.');
expect_benchmark_guard_throw(
	function() { benchmark_validate_output_path_style('F:drive-relative', 'Windows'); },
	'drive-relative Windows path',
	'a Windows drive-relative output path must not depend on hidden per-drive current state.'
);
expect_benchmark_guard_throw(
	function() { benchmark_validate_output_path_style('C:', 'Windows'); },
	'drive-relative Windows path or bare drive',
	'a bare Windows drive must not depend on hidden per-drive current state.'
);
expect_benchmark_guard_throw(
	function() { benchmark_validate_output_path_style('\\rooted-on-current-drive', 'Windows'); },
	'explicit drive or UNC server/share',
	'a rooted Windows path without a drive must be rejected.'
);
expect_benchmark_guard_throw(
	function() { benchmark_validate_output_path_style('/rooted-on-current-drive', 'Windows'); },
	'explicit drive or UNC server/share',
	'a slash-rooted Windows path without a drive must be rejected.'
);

$manifest = benchmark_manifest_base($config, $root, $root.'/tmp/fixture-output', '2026-08-29T00:00:00Z');
$manifest['status'] = 'passed';
$manifest['pages'] = array(
	'home'=>array('preflight'=>benchmark_public_snapshot($valid['home']), 'final'=>benchmark_public_snapshot($valid['home'])),
	'forum'=>array('preflight'=>benchmark_public_snapshot($valid['forum']), 'final'=>benchmark_public_snapshot($valid['forum'])),
	'thread'=>array('preflight'=>benchmark_public_snapshot($valid['thread']), 'final'=>benchmark_public_snapshot($valid['thread'])),
);
$json = benchmark_manifest_json($manifest);
$decoded = json_decode($json, TRUE);
is_array($decoded)
	&& array_key_exists('commit', $decoded['repository'])
	&& array_key_exists('dirty', $decoded['repository'])
	&& $decoded['labels']['dataset'] === 'seed-2026-08'
	&& $decoded['labels']['plugin_set'] === 'core-only'
	&& $decoded['labels']['cache_state'] === 'warm'
	&& $decoded['status'] === 'passed'
	&& isset($decoded['environment']['os_family'], $decoded['environment']['php_version'])
	&& $decoded['pages']['thread']['final']['http_status'] === 200
	&& preg_match('/\A[a-f0-9]{64}\z/D', $decoded['pages']['thread']['final']['sha256']) === 1
	|| fail_benchmark_guard('the manifest must retain repository, labels, HTTP status, and body hashes.');

$function_source = file_get_contents(__DIR__.'/benchmark.func.php');
$shell_source = file_get_contents(__DIR__.'/benchmark.sh');
$batch_source = file_get_contents(__DIR__.'/benchmark.bat');
$benchmark_entry = __DIR__.'/benchmark.php';
is_file($benchmark_entry) || fail_benchmark_guard('the shared benchmark.php entry point is missing.');
$benchmark_help_exit = benchmark_run_process(array(PHP_BINARY, $benchmark_entry, '--help'), $root, $benchmark_help, $benchmark_help_error);
$benchmark_help_exit === 0 && strpos($benchmark_help, 'Xiuno Next benchmark') !== FALSE && strpos($benchmark_help, '--url=<base-url>') !== FALSE
	|| fail_benchmark_guard('the shared benchmark.php entry point must execute --help successfully.');
is_string($function_source) && strpos($function_source, "'--max-redirs', '0'") !== FALSE
	&& strpos($function_source, "'--location'") === FALSE
	|| fail_benchmark_guard('curl preflight must reject redirects instead of following them.');
foreach(array('benchmark.sh'=>$shell_source, 'benchmark.bat'=>$batch_source) as $name=>$source) {
	is_string($source) && strpos($source, 'benchmark.php') !== FALSE
		|| fail_benchmark_guard($name.' must delegate to the shared PHP benchmark contract.');
	strpos($source, 'forum-1.htm') === FALSE && strpos($source, 'thread-1.htm') === FALSE
		|| fail_benchmark_guard($name.' must not retain fixed fid/tid assumptions.');
}

echo "OK: shared benchmark preflight, semantics, ApacheBench, and manifest contract checks passed\n";

?>
