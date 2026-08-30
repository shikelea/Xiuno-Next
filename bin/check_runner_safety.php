<?php

$root = realpath(__DIR__.'/..');
if($root === FALSE) {
	fwrite(STDERR, "FAIL: unable to resolve repository root\n");
	exit(1);
}
require_once __DIR__.'/run_checks.func.php';

function fail_runner_guard($message) {
	fwrite(STDERR, 'FAIL: '.$message."\n");
	exit(1);
}

function expect_runner_guard_throw($callback, $needle, $message) {
	try {
		$callback();
	} catch(Throwable $e) {
		strpos($e->getMessage(), $needle) !== FALSE
			|| fail_runner_guard($message.' Wrong error: '.$e->getMessage());
		return;
	}
	fail_runner_guard($message.' No exception was thrown.');
}

function remove_runner_guard_fixture($path) {
	if(!is_dir($path)) return;
	$items = scandir($path);
	if(!is_array($items)) return;
	foreach($items as $item) {
		if($item === '.' || $item === '..') continue;
		$child = $path.DIRECTORY_SEPARATOR.$item;
		is_dir($child) ? remove_runner_guard_fixture($child) : unlink($child);
	}
	rmdir($path);
}

$parsed_argv = check_runner_parse_argv(array(
	'bin/run_checks.php', '--profile=db', '--check', 'check_demo.php', '--env-file=F:/fixture.env', '--fail-on-skip', '--list'
));
$parsed_argv === array(
	'profile'=>'db',
	'check'=>'check_demo.php',
	'env-file'=>'F:/fixture.env',
	'fail-on-skip'=>TRUE,
	'list'=>TRUE,
) || fail_runner_guard('strict argv parsing must support explicit inline and separate values without coercion.');
$invalid_argv_cases = array(
	array(array('bin/run_checks.php', '--profiel=db'), 'Unknown check runner option'),
	array(array('bin/run_checks.php', 'db'), 'Unexpected positional or short argument'),
	array(array('bin/run_checks.php', '-l'), 'Unexpected positional or short argument'),
	array(array('bin/run_checks.php', '--profile'), 'requires one explicit value'),
	array(array('bin/run_checks.php', '--profile='), 'requires one explicit value'),
	array(array('bin/run_checks.php', '--list=yes'), 'does not accept a value'),
	array(array('bin/run_checks.php', '--list', '--list'), 'Duplicate check runner option'),
	array(array('bin/run_checks.php', '--profile=db', '--profile=browser'), 'Duplicate check runner option'),
);
foreach($invalid_argv_cases as $case) {
	expect_runner_guard_throw(
		function() use ($case) { check_runner_parse_argv($case[0]); },
		$case[1],
		'invalid or ambiguous CLI arguments must fail closed.'
	);
}

$fixture = rtrim(sys_get_temp_dir(), '/\\').DIRECTORY_SEPARATOR.'xiuno-check-runner-'.bin2hex(random_bytes(8));
mkdir($fixture.DIRECTORY_SEPARATOR.'bin', 0755, TRUE)
	|| fail_runner_guard('unable to create isolated runner fixture');
register_shutdown_function(function() use ($fixture) { remove_runner_guard_fixture($fixture); });

$fixture_files = array(
	'bin/check_known.php'=>"<?php echo \"OK: known\\n\";\n",
	'bin/check_unknown.php'=>"<?php echo \"OK: unknown\\n\";\n",
	'bin/check_unknown.sh'=>"#!/usr/bin/env bash\nexit 0\n",
	'bin/browser.ps1'=>"Write-Output 'fixture'\n",
	'bin/browser.sh'=>"#!/usr/bin/env bash\nexit 0\n",
	'bin/docker.ps1'=>"Write-Output 'fixture'\n",
	'bin/docker.sh'=>"#!/usr/bin/env bash\nexit 0\n",
	'test.env'=>"# literal fixture\nXIUNO_DB_NAME=xiuno_runner_test\nXIUNO_LITERAL=\$HOME\nXIUNO_EMPTY=\n",
);
foreach($fixture_files as $relative=>$contents) {
	$path = $fixture.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
	file_put_contents($path, $contents) === strlen($contents)
		|| fail_runner_guard('unable to write fixture file '.$relative);
}

$fixture_manifest = array(
	'version'=>1,
	'checks'=>array(
		array('name'=>'known', 'group'=>'deterministic', 'kind'=>'php', 'path'=>'bin/check_known.php'),
		array('name'=>'browser', 'group'=>'browser', 'kind'=>'browser', 'windows_path'=>'bin/browser.ps1', 'unix_path'=>'bin/browser.sh'),
		array(
			'name'=>'docker',
			'group'=>'docker',
			'kind'=>'docker',
			'windows_path'=>'bin/docker.ps1',
			'unix_path'=>'bin/docker.sh',
			'timeout_seconds'=>900,
			'termination_grace_seconds'=>60,
		),
	),
);
expect_runner_guard_throw(
	function() use ($fixture, $fixture_manifest) { check_runner_build_groups($fixture, $fixture_manifest, '/explicit/php', 'unix'); },
	'Unclassified check scripts: check_unknown.php, check_unknown.sh',
	'unknown check scripts must fail with an actionable manifest diagnostic.'
);

unlink($fixture.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'check_unknown.php')
	|| fail_runner_guard('unable to remove the intentional unknown fixture');
unlink($fixture.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'check_unknown.sh')
	|| fail_runner_guard('unable to remove the intentional unknown platform fixture');
$unix_groups = check_runner_build_groups($fixture, $fixture_manifest, '/explicit/php', 'unix');
$windows_groups = check_runner_build_groups($fixture, $fixture_manifest, 'X:\\explicit\\php.exe', 'windows');
$unix_groups['deterministic'][0]['command'] === array('/explicit/php', realpath($fixture.'/bin/check_known.php'))
	&& $unix_groups['deterministic'][0]['timeout_seconds'] === 300
	&& $unix_groups['deterministic'][0]['termination_grace_seconds'] === 1
	|| fail_runner_guard('PHP checks must inherit the parent PHP_BINARY exactly.');
$unix_groups['browser'][0]['command'] === array('bash', realpath($fixture.'/bin/browser.sh'), '--php-binary', '/explicit/php')
	|| fail_runner_guard('Unix browser dispatch must pass the parent PHP binary explicitly.');
$windows_browser = $windows_groups['browser'][0]['command'];
$php_argument = array_search('-PhpBinary', $windows_browser, TRUE);
$php_argument !== FALSE && isset($windows_browser[$php_argument + 1]) && $windows_browser[$php_argument + 1] === 'X:\\explicit\\php.exe'
	|| fail_runner_guard('Windows browser dispatch must pass the parent PHP binary explicitly.');
$unix_groups['docker'][0]['command'] === array('bash', realpath($fixture.'/bin/docker.sh'))
	&& $unix_groups['docker'][0]['timeout_seconds'] === 900
	&& $unix_groups['docker'][0]['termination_grace_seconds'] === 60
	|| fail_runner_guard('Unix Docker dispatch must use the Bash smoke entry point.');
in_array('-File', $windows_groups['docker'][0]['command'], TRUE)
	&& in_array(realpath($fixture.'/bin/docker.ps1'), $windows_groups['docker'][0]['command'], TRUE)
	|| fail_runner_guard('Windows Docker dispatch must use the PowerShell platform wrapper.');

$stale_manifest = $fixture_manifest;
$stale_manifest['checks'][] = array('name'=>'missing', 'group'=>'deterministic', 'kind'=>'php', 'path'=>'bin/check_missing.php');
expect_runner_guard_throw(
	function() use ($fixture, $stale_manifest) { check_runner_build_groups($fixture, $stale_manifest, '/explicit/php', 'unix'); },
	'Check manifest file does not exist: bin/check_missing.php',
	'stale manifest entries must fail before the suite runs.'
);
$invalid_timeout_manifest = $fixture_manifest;
$invalid_timeout_manifest['checks'][0]['timeout_seconds'] = 0;
expect_runner_guard_throw(
	function() use ($fixture, $invalid_timeout_manifest) { check_runner_build_groups($fixture, $invalid_timeout_manifest, '/explicit/php', 'unix'); },
	'timeout_seconds must be an integer from 1 to 86400',
	'check timeouts must fail closed outside the bounded manifest contract.'
);
$invalid_grace_manifest = $fixture_manifest;
$invalid_grace_manifest['checks'][0]['termination_grace_seconds'] = 301;
expect_runner_guard_throw(
	function() use ($fixture, $invalid_grace_manifest) { check_runner_build_groups($fixture, $invalid_grace_manifest, '/explicit/php', 'unix'); },
	'termination_grace_seconds must be an integer from 1 to 300',
	'check termination grace must fail closed outside the bounded manifest contract.'
);
expect_runner_guard_throw(
	function() use ($fixture, $fixture_manifest) {
		$invalid = $fixture_manifest;
		$invalid['checks'][0]['path'] = '../outside.php';
		check_runner_build_groups($fixture, $invalid, '/explicit/php', 'unix');
	},
	'must not contain empty, dot, or parent segments',
	'manifest paths must not escape the repository.'
);

check_runner_classify(0, "OK: complete\n") === 'PASS'
	|| fail_runner_guard('zero-exit ordinary output must classify as PASS.');
check_runner_classify(0, '') === 'FAIL' && check_runner_classify(0, " \r\n\t") === 'FAIL'
	|| fail_runner_guard('a zero-exit check without an explicit diagnostic must fail closed.');
check_runner_classify(0, "setup\nSKIP: database unavailable\n") === 'SKIP'
	|| fail_runner_guard('zero-exit SKIP output must classify as SKIP.');
check_runner_classify(0, "setup\nFAIL: legacy guard reported a failure\n") === 'FAIL'
	|| fail_runner_guard('a zero-exit child that emits a line-level FAIL marker must fail closed.');
check_runner_classify(0, "SKIP: misleading child output\nFAIL: later failure\n") === 'FAIL'
	|| fail_runner_guard('a line-level FAIL marker must take precedence over SKIP output.');
check_runner_classify(3, "SKIP: misleading child output\n") === 'FAIL'
	|| fail_runner_guard('a non-zero child must classify as FAIL even if it prints SKIP.');

$parsed_environment = check_runner_parse_env_file($fixture.DIRECTORY_SEPARATOR.'test.env');
$parsed_environment === array(
	'XIUNO_DB_NAME'=>'xiuno_runner_test',
	'XIUNO_LITERAL'=>'$HOME',
	'XIUNO_EMPTY'=>'',
) || fail_runner_guard('explicit XIUNO_* environment values must remain literal, including empty values.');
$invalid_environment_cases = array(
	'non-xiuno.env'=>"PATH=/tmp\n",
	'export.env'=>"export XIUNO_DB_NAME=xiuno_test\n",
	'duplicate.env'=>"XIUNO_DB_NAME=xiuno_one\nXIUNO_DB_NAME=xiuno_two\n",
);
foreach($invalid_environment_cases as $name=>$source) {
	$path = $fixture.DIRECTORY_SEPARATOR.$name;
	file_put_contents($path, $source) === strlen($source)
		|| fail_runner_guard('unable to write invalid environment fixture '.$name);
	expect_runner_guard_throw(
		function() use ($path) { check_runner_parse_env_file($path); },
		$name === 'duplicate.env' ? 'Duplicate explicit environment key' : 'literal XIUNO_* KEY=VALUE',
		'invalid environment syntax must fail closed for '.$name.'.'
	);
}

$environment_child = $fixture.DIRECTORY_SEPARATOR.'environment-child.php';
$environment_source = <<<'PHP'
<?php
echo getenv('XIUNO_LITERAL')."\n";
$empty = getenv('XIUNO_EMPTY');
echo $empty === '' || $empty === FALSE ? "EMPTY\n" : "NOT_EMPTY\n";
?>
PHP;
file_put_contents($environment_child, $environment_source) === strlen($environment_source)
	|| fail_runner_guard('unable to write environment child fixture.');
$child_environment = check_runner_child_environment($parsed_environment);
check_runner_process(array(PHP_BINARY, $environment_child), $fixture, $environment_output, $environment_exit, $child_environment);
$environment_exit === 0 && $environment_output === '$HOME'."\nEMPTY\n"
	|| fail_runner_guard('explicit environment values must reach children without interpolation.');

foreach(array('pass'=>0, 'skip'=>0, 'fail_zero'=>0, 'fail'=>7) as $role=>$child_exit) {
	$child = $fixture.DIRECTORY_SEPARATOR.'child-'.$role.'.php';
	$output_line = $role === 'skip' ? 'SKIP: fixture' : (strpos($role, 'fail') === 0 ? 'FAIL: fixture' : 'PASS: fixture');
	$source = '<?php echo '.var_export($output_line."\n", TRUE).'; exit('.$child_exit.');';
	file_put_contents($child, $source) === strlen($source)
		|| fail_runner_guard('unable to write child process fixture '.$role);
	check_runner_process(array(PHP_BINARY, $child), $fixture, $child_output, $exit_code);
	$expected = $role === 'pass' ? 'PASS' : ($role === 'skip' ? 'SKIP' : 'FAIL');
	check_runner_classify($exit_code, $child_output) === $expected
		|| fail_runner_guard('real child process classification failed for '.$role.'.');
}

$timeout_child = $fixture.DIRECTORY_SEPARATOR.'child-timeout.php';
$timeout_source = "<?php echo \"started\\n\"; usleep(3000000); echo \"finished\\n\";\n";
file_put_contents($timeout_child, $timeout_source) === strlen($timeout_source)
	|| fail_runner_guard('unable to write timeout child fixture.');
$timeout_started = microtime(TRUE);
check_runner_process(array(PHP_BINARY, $timeout_child), $fixture, $timeout_output, $timeout_exit, NULL, 1);
$timeout_elapsed = microtime(TRUE) - $timeout_started;
$timeout_exit === 124 && strpos($timeout_output, 'started') !== FALSE && strpos($timeout_output, 'finished') === FALSE
	&& strpos($timeout_output, 'timed out after 1 seconds') !== FALSE && $timeout_elapsed < 2.5
	|| fail_runner_guard('silent or partially producing child checks must stop at the manifest timeout with an actionable failure.');

$runner_source = file_get_contents(__DIR__.'/run_checks.php');
is_string($runner_source) && strpos($runner_source, 'check_runner_parse_argv($argv)') !== FALSE && strpos($runner_source, 'unknown manifest check') !== FALSE
	|| fail_runner_guard('the public runner must expose exact manifest-name reproduction through --check.');

$runner_path = __DIR__.DIRECTORY_SEPARATOR.'run_checks.php';
foreach(array('--help'=>'Usage:', '--list'=>'Profile: deterministic') as $runner_arg=>$expected_output) {
	$command = escapeshellarg(PHP_BINARY).' -d disable_functions=proc_open '.escapeshellarg($runner_path).' '.$runner_arg.' 2>&1';
	$lines = array();
	exec($command, $lines, $code);
	$output = implode("\n", $lines);
	$code === 0 && strpos($output, $expected_output) !== FALSE
		|| fail_runner_guard($runner_arg.' must remain available when only execution-stage proc_open is unavailable. Output: '.$output);
}
$lines = array();
exec(escapeshellarg(PHP_BINARY).' -d disable_functions=proc_open '.escapeshellarg($runner_path).' --check=check_version.php 2>&1', $lines, $code);
$output = implode("\n", $lines);
$code === 2 && strpos($output, 'proc_open() is required') !== FALSE
	|| fail_runner_guard('executing a check without proc_open must fail closed after read-only argument and manifest handling.');

$repository_manifest = check_runner_load_manifest(__DIR__.'/checks.manifest.php');
$repository_groups = check_runner_build_groups($root, $repository_manifest, PHP_BINARY);
$database_names = array_column($repository_groups['database'], 'name');
foreach(array('check_install_schema.php', 'check_legacy_upgrade_smoke.php', 'check_plugin_install_sql_smoke.php') as $required_database_check) {
	in_array($required_database_check, $database_names, TRUE)
		|| fail_runner_guard('the explicit repository manifest is missing database check '.$required_database_check.'.');
}
count($repository_groups['browser']) === 1 && count($repository_groups['docker']) === 1
	|| fail_runner_guard('the explicit repository manifest must retain browser and Docker checks.');
$repository_docker = $repository_groups['docker'][0];
$repository_docker['timeout_seconds'] === 900 && $repository_docker['termination_grace_seconds'] === 60
	|| fail_runner_guard('Docker HTTP smoke must time out before the CI job while retaining an explicit cleanup grace.');

echo "OK: explicit check manifest and platform runner behavior checks passed\n";

?>
