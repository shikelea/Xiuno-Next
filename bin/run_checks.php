<?php

$root = realpath(__DIR__.'/..');
if($root === FALSE) {
	fwrite(STDERR, "FAIL: unable to locate the repository root.\n");
	exit(2);
}
function check_runner_usage() {
	echo "Xiuno Next check runner\n\n";
	echo "Usage: php bin/run_checks.php [--profile=deterministic|browser|db|docker|full] [--check=<manifest-name>] [--env-file=<path>] [--fail-on-skip] [--list]\n\n";
	echo "Every bin/check_*.php file must be classified explicitly in bin/checks.manifest.php.\n";
	echo "The default deterministic profile never starts database, browser, or Docker integration tests.\n";
	echo "Use --check with an exact manifest name to reproduce one check with the same platform dispatch and timeout.\n";
	echo "Environment files are never auto-loaded. An explicit file accepts literal XIUNO_* KEY=VALUE lines only; values are not unquoted, expanded, or executed.\n";
}

require_once __DIR__.'/run_checks.func.php';
try {
	$options = check_runner_parse_argv($argv);
} catch(Throwable $e) {
	fwrite(STDERR, 'FAIL: '.$e->getMessage()."\n");
	check_runner_usage();
	exit(2);
}
$profile = isset($options['profile']) ? strtolower((string)$options['profile']) : 'deterministic';
$check_name = isset($options['check']) && is_string($options['check']) ? $options['check'] : '';
$fail_on_skip = array_key_exists('fail-on-skip', $options);
if(isset($options['help'])) {
	check_runner_usage();
	exit(0);
}

try {
	$environment = NULL;
	if(array_key_exists('env-file', $options)) {
		if(!is_string($options['env-file']) || $options['env-file'] === '') {
			throw new RuntimeException('--env-file requires one explicit file path.');
		}
		$environment = check_runner_child_environment(check_runner_parse_env_file($options['env-file']));
	}
	$manifest = check_runner_load_manifest(__DIR__.'/checks.manifest.php');
	$groups = check_runner_build_groups($root, $manifest, PHP_BINARY);
} catch(Throwable $e) {
	fwrite(STDERR, 'FAIL: '.$e->getMessage()."\n");
	exit(2);
}

$profile_groups = array(
	'deterministic'=>array('deterministic'),
	'browser'=>array('browser'),
	'db'=>array('database'),
	'docker'=>array('docker'),
	'full'=>array('deterministic', 'database', 'browser', 'docker'),
);
if(!isset($profile_groups[$profile])) {
	fwrite(STDERR, 'FAIL: unknown check profile: '.$profile."\n");
	check_runner_usage();
	exit(2);
}

$checks = array();
	if(array_key_exists('check', $options)) {
		if($check_name === '') {
			fwrite(STDERR, "FAIL: --check requires one exact manifest name.\n");
			exit(2);
		}
		foreach($groups as $group=>$group_checks) {
			foreach($group_checks as $check) {
				if($check['name'] !== $check_name) continue;
				$check['group'] = $group;
				$checks[] = $check;
			}
		}
		if(empty($checks)) {
			fwrite(STDERR, 'FAIL: unknown manifest check: '.$check_name."\n");
			exit(2);
		}
	} else {
		foreach($profile_groups[$profile] as $group) {
			foreach($groups[$group] as $check) {
				$check['group'] = $group;
				$checks[] = $check;
			}
	}
}

if(isset($options['list'])) {
	echo 'Profile: '.$profile."\n";
	foreach($checks as $check) echo '  ['.$check['group'].'] '.$check['name']."\n";
	exit(0);
}

// Help and manifest listing are read-only diagnostics. Require process creation only once the
// caller actually asks to execute a check, so constrained hosts can still discover exact commands.
if(!function_exists('proc_open')) {
	fwrite(STDERR, "FAIL: proc_open() is required to run the check suite.\n");
	exit(2);
}

$counts = array('PASS'=>0, 'SKIP'=>0, 'FAIL'=>0);
$results = array();
$suite_started = microtime(TRUE);

echo ($check_name !== '' ? 'Running check: '.$check_name : 'Running profile: '.$profile).' ('.count($checks)." checks)\n";
foreach($checks as $index=>$check) {
	$position = $index + 1;
	echo sprintf('[%d/%d] RUN  %-13s %s', $position, count($checks), $check['group'], $check['name'])."\n";
	function_exists('flush') AND flush();
	$started = microtime(TRUE);
	check_runner_process(
		$check['command'],
		$root,
		$output,
		$exit_code,
		$environment,
		$check['timeout_seconds'],
		$check['termination_grace_seconds']
	);
	$elapsed = microtime(TRUE) - $started;
	$status = check_runner_classify($exit_code, $output);
	$counts[$status]++;
	$summary = check_runner_summary_line($output);
	echo sprintf('       %-4s %8.2fs', $status, $elapsed);
	if($summary !== '') echo '  '.$summary;
	echo "\n";
	if($status === 'FAIL') {
		echo '----- child output: '.$check['name']." -----\n";
		echo rtrim((string)$output)."\n";
		echo "----- end child output -----\n";
	}
	$results[] = array(
		'name'=>$check['name'],
		'group'=>$check['group'],
		'status'=>$status,
		'seconds'=>$elapsed,
	);
}

$suite_elapsed = microtime(TRUE) - $suite_started;
echo "\nSummary\n";
echo '  PASS: '.$counts['PASS']."\n";
echo '  SKIP: '.$counts['SKIP']."\n";
echo '  FAIL: '.$counts['FAIL']."\n";
echo '  Time: '.number_format($suite_elapsed, 2)."s\n";
if($counts['SKIP'] > 0) {
	echo "  Skipped checks:\n";
	foreach($results as $result) {
		if($result['status'] === 'SKIP') echo '    - ['.$result['group'].'] '.$result['name']."\n";
	}
}

if($counts['FAIL'] > 0 || ($fail_on_skip && $counts['SKIP'] > 0)) exit(1);
exit(0);

?>
