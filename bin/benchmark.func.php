<?php

function benchmark_usage() {
	return <<<'TEXT'
Xiuno Next benchmark

Usage:
  php bin/benchmark.php --url=<base-url> --fid=<id> --tid=<id> \
    --dataset=<label> --plugin-set=<label> --cache-state=<label> [options]

Required labels record operator-declared dataset, plugin-set, and cache-state metadata; the tool
cannot verify those labels, so comparable runs must use the same audited environment. The target
must return each page directly with HTTP 200, HTML, one valid X-Request-ID, and page-specific
semantic markers; redirects are rejected.

Options:
  --requests=<n>          Homepage/forum requests (default: XIUNO_BENCH_REQUESTS or 1000)
  --detail-requests=<n>   Thread requests (default: XIUNO_BENCH_DETAIL_REQUESTS or 500)
  --concurrency=<n>       Concurrent requests (default: XIUNO_BENCH_CONCURRENCY or 50)
  --ttfb-samples=<n>      Validated curl samples per page (default: XIUNO_BENCH_TTFB_SAMPLES or 5)
  --home-marker=<text>    Unique literal marker for a non-default home theme
  --forum-marker=<text>   Unique literal marker for a non-default forum theme
  --thread-marker=<text>  Unique literal marker for a non-default thread theme
  --output-dir=<path>     New output directory (default: tmp/benchmark-<UTC>-<random>)
  --help                  Show this help
TEXT;
}

function benchmark_fail($message) {
	throw new RuntimeException($message);
}

function benchmark_parse_cli($argv, $environment = NULL) {
	if(!is_array($argv)) benchmark_fail('Benchmark arguments must be an array.');
	$allowed = array(
		'url'=>TRUE,
		'fid'=>TRUE,
		'tid'=>TRUE,
		'dataset'=>TRUE,
		'plugin-set'=>TRUE,
		'cache-state'=>TRUE,
		'requests'=>TRUE,
		'detail-requests'=>TRUE,
		'concurrency'=>TRUE,
		'ttfb-samples'=>TRUE,
		'home-marker'=>TRUE,
		'forum-marker'=>TRUE,
		'thread-marker'=>TRUE,
		'output-dir'=>TRUE,
	);
	$options = array();
	for($index = 1; $index < count($argv); $index++) {
		$argument = (string)$argv[$index];
		if($argument === '--help') {
			$options['help'] = TRUE;
			continue;
		}
		if(strncmp($argument, '--', 2) !== 0) {
			benchmark_fail('Unexpected positional benchmark argument: '.$argument);
		}
		$option = substr($argument, 2);
		$separator = strpos($option, '=');
		if($separator === FALSE) {
			$name = $option;
			if(!isset($allowed[$name])) benchmark_fail('Unknown benchmark option: --'.$name);
			if(!isset($argv[$index + 1]) || strncmp((string)$argv[$index + 1], '--', 2) === 0) {
				benchmark_fail('Benchmark option --'.$name.' requires a value.');
			}
			$value = (string)$argv[++$index];
		} else {
			$name = substr($option, 0, $separator);
			$value = substr($option, $separator + 1);
			if(!isset($allowed[$name])) benchmark_fail('Unknown benchmark option: --'.$name);
		}
		if(isset($options[$name])) benchmark_fail('Duplicate benchmark option: --'.$name);
		if($value === '') benchmark_fail('Benchmark option --'.$name.' must not be empty.');
		$options[$name] = $value;
	}
	if(isset($options['help'])) return array('help'=>TRUE);

	foreach(array('url', 'fid', 'tid', 'dataset', 'plugin-set', 'cache-state') as $required) {
		if(!isset($options[$required])) benchmark_fail('Missing required benchmark option: --'.$required);
	}
	if($environment === NULL) {
		$environment = getenv();
		if(!is_array($environment)) $environment = array();
	}
	if(!is_array($environment)) benchmark_fail('Benchmark environment must be an array.');

	$config = array(
		'base_url'=>benchmark_base_url($options['url']),
		'fid'=>benchmark_positive_integer($options['fid'], 'fid'),
		'tid'=>benchmark_positive_integer($options['tid'], 'tid'),
		'dataset'=>benchmark_label($options['dataset'], 'dataset'),
		'plugin_set'=>benchmark_label($options['plugin-set'], 'plugin-set'),
		'cache_state'=>benchmark_label($options['cache-state'], 'cache-state'),
		'requests'=>benchmark_count_option($options, $environment, 'requests', 'XIUNO_BENCH_REQUESTS', 1000),
		'detail_requests'=>benchmark_count_option($options, $environment, 'detail-requests', 'XIUNO_BENCH_DETAIL_REQUESTS', 500),
		'concurrency'=>benchmark_count_option($options, $environment, 'concurrency', 'XIUNO_BENCH_CONCURRENCY', 50),
		'ttfb_samples'=>benchmark_count_option($options, $environment, 'ttfb-samples', 'XIUNO_BENCH_TTFB_SAMPLES', 5),
		'markers'=>array(
			'home'=>isset($options['home-marker']) ? benchmark_marker($options['home-marker'], 'home-marker') : '',
			'forum'=>isset($options['forum-marker']) ? benchmark_marker($options['forum-marker'], 'forum-marker') : '',
			'thread'=>isset($options['thread-marker']) ? benchmark_marker($options['thread-marker'], 'thread-marker') : '',
		),
		'output_dir'=>isset($options['output-dir']) ? benchmark_path_value($options['output-dir']) : '',
		'user_agent'=>'Xiuno-Next-Benchmark/1.0',
	);
	if($config['concurrency'] > $config['requests'] || $config['concurrency'] > $config['detail_requests']) {
		benchmark_fail('Benchmark concurrency must not exceed either request count.');
	}
	return $config;
}

function benchmark_count_option($options, $environment, $option, $environment_key, $default) {
	if(isset($options[$option])) return benchmark_positive_integer($options[$option], $option);
	if(isset($environment[$environment_key]) && (string)$environment[$environment_key] !== '') {
		return benchmark_positive_integer((string)$environment[$environment_key], $environment_key);
	}
	return intval($default);
}

function benchmark_positive_integer($value, $name) {
	$value = (string)$value;
	if(!preg_match('/\A[1-9][0-9]{0,8}\z/D', $value)) {
		benchmark_fail('Benchmark '.$name.' must be a positive integer with at most nine digits.');
	}
	return intval($value);
}

function benchmark_label($value, $name) {
	$value = trim((string)$value);
	if($value === '' || strlen($value) > 160 || preg_match('/[\x00-\x1F\x7F]/', $value)) {
		benchmark_fail('Benchmark '.$name.' must be a non-empty label of at most 160 bytes without control characters.');
	}
	return $value;
}

function benchmark_marker($value, $name) {
	$value = (string)$value;
	if($value === '' || strlen($value) > 512 || preg_match('/[\x00\r\n]/', $value)) {
		benchmark_fail('Benchmark '.$name.' must be a non-empty literal of at most 512 bytes without NUL or newlines.');
	}
	return $value;
}

function benchmark_path_value($value) {
	$value = trim((string)$value);
	if($value === '' || strpos($value, "\0") !== FALSE || preg_match('/[\r\n]/', $value)) {
		benchmark_fail('Benchmark output-dir must be a non-empty path without NUL or newlines.');
	}
	return $value;
}

function benchmark_base_url($value) {
	$value = trim((string)$value);
	if($value === '' || preg_match('/[\x00-\x20\x7F]/', $value)) {
		benchmark_fail('Benchmark url must be an explicit HTTP(S) base URL without whitespace.');
	}
	$parts = parse_url($value);
	if(!is_array($parts) || !isset($parts['scheme'], $parts['host'])
		|| !in_array(strtolower($parts['scheme']), array('http', 'https'), TRUE)
		|| isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
		benchmark_fail('Benchmark url must be an HTTP(S) base URL without credentials, query, or fragment.');
	}
	return substr($value, -1) === '/' ? $value : $value.'/';
}

function benchmark_endpoints($config) {
	return array(
		'home'=>$config['base_url'],
		'forum'=>$config['base_url'].'?forum-'.$config['fid'].'.htm',
		'thread'=>$config['base_url'].'?thread-'.$config['tid'].'.htm',
	);
}

function benchmark_run_process($command, $cwd, &$stdout, &$stderr) {
	if(!function_exists('proc_open')) benchmark_fail('proc_open() is required for the benchmark.');
	$descriptor = array(
		0=>array('pipe', 'r'),
		1=>array('pipe', 'w'),
		2=>array('redirect', 1),
	);
	$pipes = array();
	$process = @proc_open($command, $descriptor, $pipes, $cwd, NULL, array('bypass_shell'=>TRUE));
	if(!is_resource($process)) benchmark_fail('Unable to start benchmark command: '.implode(' ', $command));
	fclose($pipes[0]);
	$stdout = stream_get_contents($pipes[1]);
	fclose($pipes[1]);
	$stderr = '';
	return proc_close($process);
}

function benchmark_tool_version($binary, $arguments, $cwd) {
	$command = array_merge(array($binary), $arguments);
	$exit_code = benchmark_run_process($command, $cwd, $stdout, $stderr);
	$output = trim((string)$stdout."\n".(string)$stderr);
	if($exit_code !== 0 || $output === '') benchmark_fail('Required benchmark tool is unavailable: '.$binary);
	$lines = preg_split('/\R/', $output);
	$line = is_array($lines) && isset($lines[0]) ? trim($lines[0]) : $output;
	return strlen($line) > 300 ? substr($line, 0, 300) : $line;
}

function benchmark_header_values($headers, $name) {
	$values = array();
	$lines = preg_split('/\r\n|\n|\r/', (string)$headers);
	if(!is_array($lines)) return $values;
	foreach($lines as $line) {
		$separator = strpos($line, ':');
		if($separator === FALSE) continue;
		if(strcasecmp(trim(substr($line, 0, $separator)), $name) !== 0) continue;
		$values[] = trim(substr($line, $separator + 1));
	}
	return $values;
}

function benchmark_extract_title($body) {
	if(!preg_match('/<title\b[^>]*>(.*?)<\/title>/is', (string)$body, $matches)) return '';
	$title = html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
	$title = preg_replace('/\s+/u', ' ', trim($title));
	return is_string($title) ? $title : '';
}

function benchmark_curl_snapshot($curl_binary, $url, $output_dir, $artifact, $user_agent, $cwd) {
	$header_file = $output_dir.DIRECTORY_SEPARATOR.$artifact.'.headers.tmp';
	$body_file = $output_dir.DIRECTORY_SEPARATOR.$artifact.'.body.tmp';
	$metadata_prefix = '__XIUNO_BENCH_META__';
	$write_out = "\n".$metadata_prefix."\t%{http_code}\t%{url_effective}\t%{content_type}\t%{time_starttransfer}\t%{num_redirects}\n";
	$command = array(
		$curl_binary,
		'--silent',
		'--show-error',
		'--connect-timeout', '10',
		'--max-time', '30',
		'--max-redirs', '0',
		'--header', 'User-Agent: '.$user_agent,
		'--header', 'Accept: text/html',
		'--dump-header', $header_file,
		'--output', $body_file,
		'--write-out', $write_out,
		$url,
	);
	try {
		$exit_code = benchmark_run_process($command, $cwd, $stdout, $stderr);
		if($exit_code !== 0) benchmark_fail('curl failed for '.$url.': '.trim((string)$stderr."\n".(string)$stdout));
		$metadata = NULL;
		$lines = preg_split('/\r\n|\n|\r/', (string)$stdout);
		if(is_array($lines)) {
			foreach($lines as $line) {
				if(strncmp($line, $metadata_prefix."\t", strlen($metadata_prefix) + 1) === 0) $metadata = $line;
			}
		}
		if($metadata === NULL) benchmark_fail('curl did not return benchmark metadata for '.$url.'.');
		$fields = explode("\t", $metadata);
		if(count($fields) !== 6) benchmark_fail('curl returned malformed benchmark metadata for '.$url.'.');
		$headers = @file_get_contents($header_file);
		$body = @file_get_contents($body_file);
		if($headers === FALSE || $body === FALSE) benchmark_fail('Unable to read curl response artifacts for '.$url.'.');
		if(strlen($headers) > 1024 * 1024 || strlen($body) > 16 * 1024 * 1024) {
			benchmark_fail('Benchmark response exceeds the safe inspection limit for '.$url.'.');
		}
		$request_ids = benchmark_header_values($headers, 'X-Request-ID');
		return array(
			'url'=>$url,
			'http_status'=>intval($fields[1]),
			'effective_url'=>$fields[2],
			'content_type'=>$fields[3],
			'ttfb_ms'=>round(floatval($fields[4]) * 1000, 3),
			'redirects'=>intval($fields[5]),
			'request_ids'=>$request_ids,
			'request_id'=>count($request_ids) === 1 ? $request_ids[0] : '',
			'sha256'=>hash('sha256', $body),
			'bytes'=>strlen($body),
			'title'=>benchmark_extract_title($body),
			'body'=>$body,
		);
	} finally {
		is_file($header_file) AND @unlink($header_file);
		is_file($body_file) AND @unlink($body_file);
	}
}

function benchmark_validate_snapshot($page, $snapshot) {
	if(!is_array($snapshot)) benchmark_fail('Benchmark '.$page.' snapshot is invalid.');
	if(!isset($snapshot['http_status']) || intval($snapshot['http_status']) !== 200) {
		benchmark_fail('Benchmark '.$page.' must return direct HTTP 200; received '.(isset($snapshot['http_status']) ? $snapshot['http_status'] : 'unknown').'.');
	}
	if(!isset($snapshot['redirects']) || intval($snapshot['redirects']) !== 0) {
		benchmark_fail('Benchmark '.$page.' must not redirect.');
	}
	if(!isset($snapshot['effective_url'], $snapshot['url']) || $snapshot['effective_url'] !== $snapshot['url']) {
		benchmark_fail('Benchmark '.$page.' effective URL differs from the requested URL.');
	}
	if(!isset($snapshot['content_type']) || preg_match('/\Atext\/html(?:\s*;|\z)/i', trim((string)$snapshot['content_type'])) !== 1) {
		benchmark_fail('Benchmark '.$page.' must return Content-Type text/html.');
	}
	if(!isset($snapshot['request_ids']) || count($snapshot['request_ids']) !== 1
		|| preg_match('/\A[a-f0-9]{32}\z/D', (string)$snapshot['request_ids'][0]) !== 1) {
		benchmark_fail('Benchmark '.$page.' must return exactly one valid X-Request-ID.');
	}
	if(!isset($snapshot['body']) || $snapshot['body'] === '' || !isset($snapshot['sha256'])
		|| preg_match('/\A[a-f0-9]{64}\z/D', (string)$snapshot['sha256']) !== 1) {
		benchmark_fail('Benchmark '.$page.' returned an empty or unhashable HTML body.');
	}
	if(!isset($snapshot['title']) || trim((string)$snapshot['title']) === '') {
		benchmark_fail('Benchmark '.$page.' HTML must contain a non-empty title.');
	}
	if(!isset($snapshot['ttfb_ms']) || !is_numeric($snapshot['ttfb_ms']) || floatval($snapshot['ttfb_ms']) < 0) {
		benchmark_fail('Benchmark '.$page.' returned an invalid TTFB value.');
	}
}

function benchmark_default_semantic_match($page, $body, $config) {
	if($page === 'home') {
		return preg_match('/data-active\s*=\s*(["\'])fid-0\1/i', $body) === 1;
	}
	if($page === 'forum') {
		return strpos($body, 'forum-'.$config['fid'].'.htm') !== FALSE
			&& preg_match('/thread-create-'.preg_quote((string)$config['fid'], '/').'(?:[-.]|\b)/i', $body) === 1;
	}
	if($page === 'thread') {
		return strpos($body, 'thread-'.$config['tid'].'.htm') !== FALSE
			&& preg_match('/class\s*=\s*(["\'])[^"\']*\bcard-postlist\b[^"\']*\1/i', $body) === 1;
	}
	return FALSE;
}

function benchmark_validate_semantics($snapshots, $config) {
	$all_explicit_markers = TRUE;
	foreach(array('home', 'forum', 'thread') as $page) {
		if(!isset($snapshots[$page])) benchmark_fail('Benchmark semantic set is missing '.$page.'.');
		benchmark_validate_snapshot($page, $snapshots[$page]);
		$body = (string)$snapshots[$page]['body'];
		$marker = isset($config['markers'][$page]) ? (string)$config['markers'][$page] : '';
		if($marker !== '') {
			if(strpos($body, $marker) === FALSE) benchmark_fail('Benchmark '.$page.' is missing its explicit semantic marker.');
			foreach(array('home', 'forum', 'thread') as $other) {
				if($other !== $page && isset($snapshots[$other]) && strpos((string)$snapshots[$other]['body'], $marker) !== FALSE) {
					benchmark_fail('Benchmark '.$page.' semantic marker also appears on '.$other.'; use a page-unique marker.');
				}
			}
		} else {
			$all_explicit_markers = FALSE;
			if(!benchmark_default_semantic_match($page, $body, $config)) {
				benchmark_fail('Benchmark '.$page.' does not match the default Xiuno page semantics; provide a unique --'.$page.'-marker for this theme.');
			}
		}
	}
	$titles = array();
	$hashes = array();
	foreach(array('home', 'forum', 'thread') as $page) {
		$title_key = function_exists('mb_strtolower')
			? mb_strtolower(trim((string)$snapshots[$page]['title']), 'UTF-8')
			: strtolower(trim((string)$snapshots[$page]['title']));
		if(!$all_explicit_markers && isset($titles[$title_key])) benchmark_fail('Benchmark pages share the same HTML title and cannot be distinguished semantically.');
		$titles[$title_key] = $page;
		$hash = (string)$snapshots[$page]['sha256'];
		if(isset($hashes[$hash])) benchmark_fail('Benchmark pages returned identical HTML bodies.');
		$hashes[$hash] = $page;
	}
}

function benchmark_public_snapshot($snapshot) {
	return array(
		'http_status'=>intval($snapshot['http_status']),
		'effective_url'=>(string)$snapshot['effective_url'],
		'content_type'=>(string)$snapshot['content_type'],
		'redirects'=>intval($snapshot['redirects']),
		'request_id'=>(string)$snapshot['request_id'],
		'sha256'=>(string)$snapshot['sha256'],
		'bytes'=>intval($snapshot['bytes']),
		'title'=>(string)$snapshot['title'],
		'ttfb_ms'=>floatval($snapshot['ttfb_ms']),
	);
}

function benchmark_parse_ab_output($output, $expected_requests) {
	$output = (string)$output;
	$patterns = array(
		'complete_requests'=>'/^Complete requests:\s+([0-9]+)\s*$/mi',
		'failed_requests'=>'/^Failed requests:\s+([0-9]+)\s*$/mi',
		'requests_per_second'=>'/^Requests per second:\s+([0-9]+(?:\.[0-9]+)?)\s+\[#\/sec\]\s+\(mean\)\s*$/mi',
		'mean_request_ms'=>'/^Time per request:\s+([0-9]+(?:\.[0-9]+)?)\s+\[ms\]\s+\(mean\)\s*$/mi',
	);
	$metrics = array();
	foreach($patterns as $name=>$pattern) {
		if(!preg_match($pattern, $output, $matches)) benchmark_fail('Unable to parse ApacheBench metric: '.$name.'.');
		$metrics[$name] = strpos($name, 'requests_per_second') !== FALSE || $name === 'mean_request_ms'
			? floatval($matches[1])
			: intval($matches[1]);
	}
	$metrics['non_2xx_responses'] = preg_match('/^Non-2xx responses:\s+([0-9]+)\s*$/mi', $output, $matches)
		? intval($matches[1])
		: 0;
	if($metrics['complete_requests'] !== intval($expected_requests)) {
		benchmark_fail('ApacheBench did not complete the requested number of requests.');
	}
	if($metrics['failed_requests'] !== 0) benchmark_fail('ApacheBench reported failed requests.');
	if($metrics['non_2xx_responses'] !== 0) benchmark_fail('ApacheBench reported non-2xx responses.');
	return $metrics;
}

function benchmark_run_ab($ab_binary, $url, $requests, $concurrency, $user_agent, $cwd, &$raw_output) {
	$command = array(
		$ab_binary,
		'-n', (string)$requests,
		'-c', (string)$concurrency,
		'-s', '30',
		'-l',
		'-H', 'User-Agent: '.$user_agent,
		'-H', 'Accept: text/html',
		$url,
	);
	$exit_code = benchmark_run_process($command, $cwd, $stdout, $stderr);
	$raw_output = rtrim((string)$stdout."\n".(string)$stderr)."\n";
	if($exit_code !== 0) benchmark_fail('ApacheBench failed for '.$url.".\n".substr($raw_output, 0, 2000));
	return benchmark_parse_ab_output($raw_output, $requests);
}

function benchmark_is_absolute_path($path, $os_family = NULL) {
	$path = (string)$path;
	if(!isset($path[0])) return FALSE;
	$os_family = $os_family === NULL ? PHP_OS_FAMILY : (string)$os_family;
	if(strcasecmp($os_family, 'Windows') !== 0) return $path[0] === '/';
	return preg_match('~\A[A-Za-z]:[\\\\/]~D', $path) === 1
		|| benchmark_is_windows_unc_path($path);
}

function benchmark_is_windows_unc_path($path) {
	// A fully qualified UNC path has both a server and share. Single-root paths depend on the
	// process current drive, while device namespaces (\\?\ and \\.\) are not benchmark outputs.
	return preg_match('~\A[\\\\/]{2}(?![?.](?:[\\\\/]|$))[^\\\\/]+[\\\\/][^\\\\/]+(?:[\\\\/].*)?\z~D', (string)$path) === 1;
}

function benchmark_validate_output_path_style($path, $os_family = NULL) {
	$path = (string)$path;
	$os_family = $os_family === NULL ? PHP_OS_FAMILY : (string)$os_family;
	if(strcasecmp($os_family, 'Windows') !== 0) {
		// PHP on Unix treats backslashes and drive prefixes as ordinary filename bytes. Silently
		// accepting a copied Windows path would therefore create an unexpected directory inside
		// the repository instead of writing to that drive. WSL callers must use /mnt/<drive>/....
		if(strpos($path, '\\') !== FALSE || preg_match('~\A[A-Za-z]:~D', $path) === 1) {
			benchmark_fail('Benchmark output-dir uses Windows path syntax on a non-Windows host; use a Unix or WSL path.');
		}
	} else {
		if(preg_match('~\A[A-Za-z]:~D', $path) === 1 && !benchmark_is_absolute_path($path, 'Windows')) {
			// Bare and drive-relative paths depend on hidden per-drive current-directory state.
			benchmark_fail('Benchmark output-dir must not use a drive-relative Windows path or bare drive.');
		}
		if(isset($path[0]) && ($path[0] === '\\' || $path[0] === '/') && !benchmark_is_windows_unc_path($path)) {
			benchmark_fail('Benchmark output-dir must include an explicit drive or UNC server/share instead of a current-drive rooted path.');
		}
	}
	return $path;
}

function benchmark_prepare_output_dir($root, $configured_path) {
	$root = realpath($root);
	if($root === FALSE) benchmark_fail('Unable to resolve the benchmark repository root.');
	if($configured_path === '') {
		$parent = $root.DIRECTORY_SEPARATOR.'tmp';
		if(!is_dir($parent) && !mkdir($parent, 0755, TRUE)) benchmark_fail('Unable to create the benchmark tmp directory.');
		try {
			$suffix = substr(bin2hex(random_bytes(6)), 0, 12);
		} catch(Throwable $e) {
			$suffix = substr(hash('sha256', microtime(TRUE).getmypid()), 0, 12);
		}
		$path = $parent.DIRECTORY_SEPARATOR.'benchmark-'.gmdate('Ymd-His').'-'.$suffix;
	} else {
		$configured_path = benchmark_validate_output_path_style($configured_path);
		$path = benchmark_is_absolute_path($configured_path)
			? $configured_path
			: $root.DIRECTORY_SEPARATOR.$configured_path;
	}
	if(file_exists($path)) benchmark_fail('Benchmark output directory already exists: '.$path);
	if(!mkdir($path, 0755, TRUE)) benchmark_fail('Unable to create benchmark output directory: '.$path);
	$resolved = realpath($path);
	if($resolved === FALSE || !is_dir($resolved)) benchmark_fail('Unable to resolve benchmark output directory: '.$path);
	return $resolved;
}

function benchmark_repository_state($root) {
	$state = array('commit'=>'unknown', 'dirty'=>NULL);
	try {
		$exit = benchmark_run_process(array('git', 'rev-parse', 'HEAD'), $root, $stdout, $stderr);
		if($exit === 0 && preg_match('/\A[a-f0-9]{40}\z/D', trim($stdout))) $state['commit'] = trim($stdout);
		$exit = benchmark_run_process(array('git', 'status', '--porcelain', '--untracked-files=normal'), $root, $stdout, $stderr);
		if($exit === 0) $state['dirty'] = trim($stdout) !== '';
	} catch(Throwable $e) {
		// A source archive can still produce a useful benchmark manifest without Git metadata.
	}
	return $state;
}

function benchmark_manifest_base($config, $root, $output_dir, $started_at) {
	return array(
		'schema_version'=>1,
		'status'=>'running',
		'started_at'=>$started_at,
		'finished_at'=>NULL,
		'repository'=>benchmark_repository_state($root),
		'labels'=>array(
			'dataset'=>$config['dataset'],
			'plugin_set'=>$config['plugin_set'],
			'cache_state'=>$config['cache_state'],
		),
		'target'=>array(
			'base_url'=>$config['base_url'],
			'fid'=>$config['fid'],
			'tid'=>$config['tid'],
			'pages'=>benchmark_endpoints($config),
		),
		'load'=>array(
			'requests'=>$config['requests'],
			'detail_requests'=>$config['detail_requests'],
			'concurrency'=>$config['concurrency'],
			'ttfb_samples'=>$config['ttfb_samples'],
			'user_agent'=>$config['user_agent'],
		),
		'environment'=>array(
			'os_family'=>PHP_OS_FAMILY,
			'uname'=>php_uname(),
			'php_version'=>PHP_VERSION,
			'php_sapi'=>PHP_SAPI,
			'php_binary'=>PHP_BINARY,
		),
		'tools'=>array(),
		'output_dir'=>$output_dir,
		'pages'=>array(),
	);
}

function benchmark_manifest_json($manifest) {
	$json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	if(!is_string($json)) benchmark_fail('Unable to encode benchmark manifest JSON.');
	return $json."\n";
}

function benchmark_write_manifest($output_dir, $manifest) {
	$target = $output_dir.DIRECTORY_SEPARATOR.'benchmark-manifest.json';
	$staging = $target.'.tmp';
	$json = benchmark_manifest_json($manifest);
	if(file_put_contents($staging, $json, LOCK_EX) !== strlen($json)) benchmark_fail('Unable to write benchmark manifest staging file.');
	if(!@rename($staging, $target)) {
		@unlink($staging);
		benchmark_fail('Unable to publish benchmark manifest.');
	}
	return $target;
}

?>
