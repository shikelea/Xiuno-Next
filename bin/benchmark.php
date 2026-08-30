<?php

if(PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
	http_response_code(404);
	echo "Not Found\n";
	exit(1);
}

require_once __DIR__.'/benchmark.func.php';

try {
	$config = benchmark_parse_cli($argv);
} catch(Throwable $e) {
	fwrite(STDERR, 'FAIL: '.$e->getMessage()."\n\n".benchmark_usage()."\n");
	exit(2);
}
if(isset($config['help'])) {
	echo benchmark_usage()."\n";
	exit(0);
}

$root = realpath(__DIR__.'/..');
if($root === FALSE) {
	fwrite(STDERR, "FAIL: unable to locate the repository root.\n");
	exit(2);
}

try {
	$output_dir = benchmark_prepare_output_dir($root, $config['output_dir']);
} catch(Throwable $e) {
	fwrite(STDERR, 'FAIL: '.$e->getMessage()."\n");
	exit(2);
}

$started_at = gmdate('Y-m-d\TH:i:s\Z');
$manifest = benchmark_manifest_base($config, $root, $output_dir, $started_at);
$curl_binary = getenv('XIUNO_BENCH_CURL_BINARY');
$curl_binary = is_string($curl_binary) && $curl_binary !== '' ? $curl_binary : 'curl';
$ab_binary = getenv('XIUNO_BENCH_AB_BINARY');
$ab_binary = is_string($ab_binary) && $ab_binary !== '' ? $ab_binary : 'ab';
$manifest_path = '';

try {
	$manifest['tools']['curl'] = benchmark_tool_version($curl_binary, array('--version'), $root);
	$manifest['tools']['ab'] = benchmark_tool_version($ab_binary, array('-V'), $root);
	$endpoints = benchmark_endpoints($config);
	$preflight = array();

	echo "Xiuno Next benchmark contract\n";
	echo 'Output: '.$output_dir."\n";
	echo 'Dataset: '.$config['dataset'].' | Plugin set: '.$config['plugin_set'].' | Cache state: '.$config['cache_state']."\n";
	echo 'Requests: '.$config['requests'].'/'.$config['detail_requests'].' | Concurrency: '.$config['concurrency'].' | TTFB samples: '.$config['ttfb_samples']."\n\n";
	echo "Validating direct HTTP and page semantics...\n";
	foreach($endpoints as $page=>$url) {
		$preflight[$page] = benchmark_curl_snapshot(
			$curl_binary,
			$url,
			$output_dir,
			'preflight-'.$page,
			$config['user_agent'],
			$root
		);
		$manifest['pages'][$page] = array(
			'url'=>$url,
			'preflight'=>benchmark_public_snapshot($preflight[$page]),
		);
		benchmark_validate_snapshot($page, $preflight[$page]);
	}
	benchmark_validate_semantics($preflight, $config);
	foreach($preflight as $page=>$snapshot) {
		echo '  PASS '.$page.' HTTP '.$snapshot['http_status'].' '.$snapshot['sha256']."\n";
	}

	echo "\nRunning ApacheBench without redirect following...\n";
	$ab_metrics = array();
	foreach($endpoints as $page=>$url) {
		$requests = $page === 'thread' ? $config['detail_requests'] : $config['requests'];
		echo '  RUN  '.$page.' ('.$requests.' requests, '.$config['concurrency']." concurrent)\n";
		$metrics = benchmark_run_ab(
			$ab_binary,
			$url,
			$requests,
			$config['concurrency'],
			$config['user_agent'],
			$root,
			$raw_output
		);
		$report_name = 'bench_'.$page.'.txt';
		$report_path = $output_dir.DIRECTORY_SEPARATOR.$report_name;
		if(file_put_contents($report_path, $raw_output, LOCK_EX) !== strlen($raw_output)) {
			benchmark_fail('Unable to write ApacheBench report: '.$report_path);
		}
		$metrics['raw_report'] = $report_name;
		$ab_metrics[$page] = $metrics;
		echo '  PASS '.$page.' '.$metrics['requests_per_second'].' req/s, '.$metrics['mean_request_ms']." ms mean\n";
	}

	echo "\nCollecting validated post-benchmark TTFB samples...\n";
	$ttfb_values = array('home'=>array(), 'forum'=>array(), 'thread'=>array());
	$final_snapshots = array();
	for($sample = 1; $sample <= $config['ttfb_samples']; $sample++) {
		$sample_set = array();
		foreach($endpoints as $page=>$url) {
			$sample_set[$page] = benchmark_curl_snapshot(
				$curl_binary,
				$url,
				$output_dir,
				'sample-'.str_pad((string)$sample, 3, '0', STR_PAD_LEFT).'-'.$page,
				$config['user_agent'],
				$root
			);
			benchmark_validate_snapshot($page, $sample_set[$page]);
		}
		benchmark_validate_semantics($sample_set, $config);
		foreach($sample_set as $page=>$snapshot) {
			$ttfb_values[$page][] = floatval($snapshot['ttfb_ms']);
			$final_snapshots[$page] = $snapshot;
		}
	}

	foreach(array('home', 'forum', 'thread') as $page) {
		$average = round(array_sum($ttfb_values[$page]) / count($ttfb_values[$page]), 3);
		$manifest['pages'][$page]['benchmark'] = $ab_metrics[$page] + array(
			'validated_ttfb_samples_ms'=>$ttfb_values[$page],
			'validated_ttfb_average_ms'=>$average,
		);
		$manifest['pages'][$page]['final'] = benchmark_public_snapshot($final_snapshots[$page]);
		echo '  PASS '.$page.' average TTFB '.$average." ms\n";
	}

	$manifest['status'] = 'passed';
	$manifest['finished_at'] = gmdate('Y-m-d\TH:i:s\Z');
	$manifest_path = benchmark_write_manifest($output_dir, $manifest);

	echo "\nBenchmark passed.\n";
	echo 'Manifest: '.$manifest_path."\n";
	foreach(array('home', 'forum', 'thread') as $page) {
		$metrics = $manifest['pages'][$page]['benchmark'];
		echo sprintf(
			"  %-6s %8.2f req/s | %8.2f ms AB mean | %8.2f ms validated TTFB\n",
			$page,
			$metrics['requests_per_second'],
			$metrics['mean_request_ms'],
			$metrics['validated_ttfb_average_ms']
		);
	}
	exit(0);
} catch(Throwable $e) {
	$manifest['status'] = 'failed';
	$manifest['finished_at'] = gmdate('Y-m-d\TH:i:s\Z');
	$manifest['error'] = $e->getMessage();
	try {
		$manifest_path = benchmark_write_manifest($output_dir, $manifest);
	} catch(Throwable $manifest_error) {
		fwrite(STDERR, 'FAIL: unable to write failure manifest: '.$manifest_error->getMessage()."\n");
	}
	fwrite(STDERR, 'FAIL: '.$e->getMessage()."\n");
	if($manifest_path !== '') fwrite(STDERR, 'Failure manifest: '.$manifest_path."\n");
	exit(1);
}

?>
