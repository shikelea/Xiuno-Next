<?php

// Shared implementation for bin/run_checks.php and its behavior guard.

function check_runner_parse_argv($argv) {
	if(!is_array($argv) || empty($argv)) throw new RuntimeException('Check runner argv must be a non-empty array.');
	$spec = array(
		'profile'=>TRUE,
		'check'=>TRUE,
		'env-file'=>TRUE,
		'fail-on-skip'=>FALSE,
		'list'=>FALSE,
		'help'=>FALSE,
	);
	$options = array();
	$count = count($argv);
	for($i = 1; $i < $count; $i++) {
		$argument = $argv[$i];
		if(!is_string($argument) || substr($argument, 0, 2) !== '--') {
			throw new RuntimeException('Unexpected positional or short argument: '.(is_scalar($argument) ? (string)$argument : '<non-string>').'.');
		}
		$body = substr($argument, 2);
		$separator = strpos($body, '=');
		$has_inline_value = $separator !== FALSE;
		$name = $has_inline_value ? substr($body, 0, $separator) : $body;
		$value = $has_inline_value ? substr($body, $separator + 1) : NULL;
		if(!array_key_exists($name, $spec)) throw new RuntimeException('Unknown check runner option: --'.$name.'.');
		if(array_key_exists($name, $options)) throw new RuntimeException('Duplicate check runner option: --'.$name.'.');
		if($spec[$name]) {
			if(!$has_inline_value) {
				if(!isset($argv[$i + 1]) || !is_string($argv[$i + 1]) || substr($argv[$i + 1], 0, 2) === '--') {
					throw new RuntimeException('--'.$name.' requires one explicit value.');
				}
				$value = $argv[++$i];
			}
			if($value === '') throw new RuntimeException('--'.$name.' requires one explicit value.');
			$options[$name] = $value;
		} else {
			if($has_inline_value) throw new RuntimeException('--'.$name.' does not accept a value.');
			$options[$name] = TRUE;
		}
	}
	return $options;
}

function check_runner_relative_path($path) {
	if(!is_string($path) || $path === '' || strpos($path, "\0") !== FALSE) {
		throw new RuntimeException('Check manifest paths must be non-empty strings.');
	}
	$path = str_replace('\\', '/', $path);
	if($path[0] === '/' || preg_match('/^[A-Za-z]:\//', $path)) {
		throw new RuntimeException('Check manifest paths must be repository-relative: '.$path);
	}
	$parts = explode('/', $path);
	foreach($parts as $part) {
		if($part === '' || $part === '.' || $part === '..') {
			throw new RuntimeException('Check manifest paths must not contain empty, dot, or parent segments: '.$path);
		}
	}
	return implode('/', $parts);
}

function check_runner_resolve_file($root, $relative) {
	$relative = check_runner_relative_path($relative);
	$resolved = realpath($root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative));
	if($resolved === FALSE || !is_file($resolved)) {
		throw new RuntimeException('Check manifest file does not exist: '.$relative);
	}
	$root_normalized = rtrim(str_replace('\\', '/', realpath($root)), '/').'/';
	$resolved_normalized = str_replace('\\', '/', $resolved);
	$inside = DIRECTORY_SEPARATOR === '\\'
		? strncasecmp($resolved_normalized, $root_normalized, strlen($root_normalized)) === 0
		: strncmp($resolved_normalized, $root_normalized, strlen($root_normalized)) === 0;
	if(!$inside) throw new RuntimeException('Check manifest path escapes the repository: '.$relative);
	return $resolved;
}

function check_runner_load_manifest($path) {
	if(!is_file($path)) throw new RuntimeException('Check manifest is missing: '.$path);
	$manifest = require $path;
	if(!is_array($manifest)) throw new RuntimeException('Check manifest must return an array: '.$path);
	return $manifest;
}

function check_runner_parse_env_file($path) {
	if(!is_string($path) || $path === '' || strpos($path, "\0") !== FALSE) {
		throw new RuntimeException('The explicit environment file path must be a non-empty string.');
	}
	$resolved = realpath($path);
	if($resolved === FALSE || !is_file($resolved)) {
		throw new RuntimeException('Explicit environment file does not exist: '.$path);
	}
	$size = filesize($resolved);
	if($size === FALSE || $size > 1024 * 1024) {
		throw new RuntimeException('Explicit environment file must not exceed 1 MiB: '.$resolved);
	}
	$contents = file_get_contents($resolved);
	if($contents === FALSE) throw new RuntimeException('Unable to read explicit environment file: '.$resolved);
	if(substr($contents, 0, 3) === "\xEF\xBB\xBF") $contents = substr($contents, 3);
	if(strpos($contents, "\0") !== FALSE) {
		throw new RuntimeException('Explicit environment file contains a NUL byte: '.$resolved);
	}

	$variables = array();
	$lines = preg_split('/\r\n|\n|\r/', $contents);
	if(!is_array($lines)) throw new RuntimeException('Unable to parse explicit environment file: '.$resolved);
	foreach($lines as $index=>$line) {
		$trimmed = trim($line);
		if($trimmed === '' || $trimmed[0] === '#') continue;
		if(!preg_match('/^(XIUNO_[A-Z0-9_]+)=(.*)$/D', $line, $matches)) {
			throw new RuntimeException(
				'Invalid explicit environment entry on line '.($index + 1)
				.'. Use literal XIUNO_* KEY=VALUE lines only.'
			);
		}
		$key = $matches[1];
		if(array_key_exists($key, $variables)) {
			throw new RuntimeException('Duplicate explicit environment key on line '.($index + 1).': '.$key);
		}
		// Values are deliberately opaque: no quote removal, escaping, variable expansion, or commands.
		$variables[$key] = $matches[2];
	}
	return $variables;
}

function check_runner_child_environment($overrides) {
	if(!is_array($overrides)) throw new RuntimeException('Check environment overrides must be an array.');
	$environment = getenv();
	if(!is_array($environment)) $environment = array();
	foreach($overrides as $key=>$value) {
		if(!is_string($key) || !preg_match('/^XIUNO_[A-Z0-9_]+$/D', $key) || !is_string($value)) {
			throw new RuntimeException('Check environment overrides must be string XIUNO_* KEY=VALUE pairs.');
		}
		if(strpos($value, "\0") !== FALSE) {
			throw new RuntimeException('Check environment override contains a NUL byte: '.$key);
		}
		if(DIRECTORY_SEPARATOR === '\\') {
			foreach(array_keys($environment) as $existing_key) {
				if(strcasecmp($existing_key, $key) === 0) unset($environment[$existing_key]);
			}
		}
		$environment[$key] = $value;
	}
	return $environment;
}

function check_runner_build_groups($root, $manifest, $php_binary, $platform = NULL) {
	$root = realpath($root);
	if($root === FALSE) throw new RuntimeException('Unable to resolve the check runner repository root.');
	if(!is_array($manifest) || !isset($manifest['version']) || $manifest['version'] !== 1) {
		throw new RuntimeException('Check manifest version must be exactly 1.');
	}
	if(!isset($manifest['checks']) || !is_array($manifest['checks'])) {
		throw new RuntimeException('Check manifest must contain a checks array.');
	}
	if(!is_string($php_binary) || $php_binary === '') {
		throw new RuntimeException('The parent PHP_BINARY is required to build check commands.');
	}
	$platform = $platform === NULL ? (DIRECTORY_SEPARATOR === '\\' ? 'windows' : 'unix') : strtolower((string)$platform);
	if(!in_array($platform, array('windows', 'unix'), TRUE)) {
		throw new RuntimeException('Unknown check runner platform: '.$platform);
	}

	$groups = array(
		'deterministic'=>array(),
		'database'=>array(),
		'browser'=>array(),
		'docker'=>array(),
	);
	$known_names = array();
	$classed_php_checks = array();
	$classed_platform_checks = array();
	$default_timeouts = array(
		'deterministic'=>300,
		'database'=>600,
		'browser'=>180,
		'docker'=>900,
	);
	$default_termination_grace_seconds = 1;
	foreach($manifest['checks'] as $index=>$metadata) {
		if(!is_array($metadata)) throw new RuntimeException('Check manifest entry '.$index.' must be an array.');
		$name = isset($metadata['name']) ? (string)$metadata['name'] : '';
		$group = isset($metadata['group']) ? (string)$metadata['group'] : '';
		$kind = isset($metadata['kind']) ? (string)$metadata['kind'] : '';
		if(!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/D', $name)) {
			throw new RuntimeException('Check manifest entry '.$index.' has an invalid name.');
		}
		if(isset($known_names[$name])) throw new RuntimeException('Duplicate check manifest name: '.$name);
		if(!isset($groups[$group])) throw new RuntimeException('Check '.$name.' has an unknown group: '.$group);
		if(!in_array($kind, array('php', 'browser', 'docker'), TRUE)) {
			throw new RuntimeException('Check '.$name.' has an unknown command kind: '.$kind);
		}
		if($kind === 'browser' && $group !== 'browser') throw new RuntimeException('Browser check '.$name.' must use the browser group.');
		if($kind === 'docker' && $group !== 'docker') throw new RuntimeException('Docker check '.$name.' must use the docker group.');
		$timeout_seconds = isset($metadata['timeout_seconds']) ? $metadata['timeout_seconds'] : $default_timeouts[$group];
		if(!is_int($timeout_seconds) || $timeout_seconds < 1 || $timeout_seconds > 86400) {
			throw new RuntimeException('Check '.$name.' timeout_seconds must be an integer from 1 to 86400.');
		}
		$termination_grace_seconds = isset($metadata['termination_grace_seconds'])
			? $metadata['termination_grace_seconds']
			: $default_termination_grace_seconds;
		if(!is_int($termination_grace_seconds) || $termination_grace_seconds < 1 || $termination_grace_seconds > 300) {
			throw new RuntimeException('Check '.$name.' termination_grace_seconds must be an integer from 1 to 300.');
		}

		$known_names[$name] = TRUE;
		$command = array();
		if($kind === 'php') {
			if(!isset($metadata['path'])) throw new RuntimeException('PHP check '.$name.' is missing its path.');
			$relative = check_runner_relative_path($metadata['path']);
			$path = check_runner_resolve_file($root, $relative);
			$arguments = isset($metadata['arguments']) ? $metadata['arguments'] : array();
			if(!is_array($arguments)) throw new RuntimeException('PHP check '.$name.' arguments must be an array.');
			$command = array($php_binary, $path);
			foreach($arguments as $argument) {
				if(!is_string($argument)) throw new RuntimeException('PHP check '.$name.' arguments must be strings.');
				$command[] = $argument;
			}
			if(preg_match('~^bin/check_[^/]+\.php$~D', $relative)) {
				$classed_php_checks[basename($relative)] = TRUE;
			}
		} elseif($kind === 'browser') {
			$windows_relative = check_runner_relative_path(isset($metadata['windows_path']) ? $metadata['windows_path'] : '');
			$unix_relative = check_runner_relative_path(isset($metadata['unix_path']) ? $metadata['unix_path'] : '');
			$windows = check_runner_resolve_file($root, $windows_relative);
			$unix = check_runner_resolve_file($root, $unix_relative);
			foreach(array($windows_relative, $unix_relative) as $relative) {
				if(preg_match('~^bin/check_[^/]+\.(?:ps1|sh)$~D', $relative)) $classed_platform_checks[basename($relative)] = TRUE;
			}
			$command = $platform === 'windows'
				? array('powershell.exe', '-NoLogo', '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-File', $windows, '-PhpBinary', $php_binary)
				: array('bash', $unix, '--php-binary', $php_binary);
		} else {
			$windows_relative = check_runner_relative_path(isset($metadata['windows_path']) ? $metadata['windows_path'] : '');
			$unix_relative = check_runner_relative_path(isset($metadata['unix_path']) ? $metadata['unix_path'] : '');
			$windows = check_runner_resolve_file($root, $windows_relative);
			$unix = check_runner_resolve_file($root, $unix_relative);
			foreach(array($windows_relative, $unix_relative) as $relative) {
				if(preg_match('~^bin/check_[^/]+\.(?:ps1|sh)$~D', $relative)) $classed_platform_checks[basename($relative)] = TRUE;
			}
			$command = $platform === 'windows'
				? array('powershell.exe', '-NoLogo', '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-File', $windows)
				: array('bash', $unix);
		}
		$groups[$group][] = array(
			'name'=>$name,
			'command'=>$command,
			'timeout_seconds'=>$timeout_seconds,
			'termination_grace_seconds'=>$termination_grace_seconds,
		);
	}

	$discovered_names = array();
	foreach(array('php', 'ps1', 'sh') as $extension) {
		$discovered = glob($root.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'check_*.'.$extension);
		if(!is_array($discovered)) continue;
		foreach($discovered as $path) $discovered_names[basename($path)] = TRUE;
	}
	$classed_checks = $classed_php_checks + $classed_platform_checks;
	$unknown = array_keys(array_diff_key($discovered_names, $classed_checks));
	$missing = array_keys(array_diff_key($classed_php_checks, $discovered_names));
	sort($unknown, SORT_STRING);
	sort($missing, SORT_STRING);
	if(!empty($unknown)) {
		throw new RuntimeException(
			'Unclassified check scripts: '.implode(', ', $unknown)
			.'. Add explicit entries to bin/checks.manifest.php before running the suite.'
		);
	}
	if(!empty($missing)) {
		throw new RuntimeException('Check manifest entries reference missing check scripts: '.implode(', ', $missing).'.');
	}
	return $groups;
}

function check_runner_process($command, $cwd, &$output, &$exit_code, $environment = NULL, $timeout_seconds = 300, $termination_grace_seconds = 1) {
	if(!is_int($timeout_seconds) || $timeout_seconds < 1 || $timeout_seconds > 86400) {
		throw new RuntimeException('Check process timeout must be an integer from 1 to 86400.');
	}
	if(!is_int($termination_grace_seconds) || $termination_grace_seconds < 1 || $termination_grace_seconds > 300) {
		throw new RuntimeException('Check process termination grace must be an integer from 1 to 300.');
	}
	$output_file = tempnam(sys_get_temp_dir(), 'xiuno-check-output-');
	if($output_file === FALSE) {
		$output = 'Unable to create isolated child output storage.';
		$exit_code = 127;
		return;
	}
	$descriptor = array(
		0=>array('pipe', 'r'),
		1=>array('file', $output_file, 'wb'),
		2=>array('redirect', 1),
	);
	$pipes = array();
	$process = @proc_open($command, $descriptor, $pipes, $cwd, $environment, array('bypass_shell'=>TRUE));
	if(!is_resource($process)) {
		@unlink($output_file);
		$output = 'Unable to start child process.';
		$exit_code = 127;
		return;
	}
	fclose($pipes[0]);
	$deadline = microtime(TRUE) + $timeout_seconds;
	$observed_exit = -1;
	$timed_out = FALSE;
	do {
		$status = proc_get_status($process);
		if(empty($status['running'])) {
			$observed_exit = isset($status['exitcode']) ? intval($status['exitcode']) : -1;
			break;
		}
		if(microtime(TRUE) >= $deadline) {
			$timed_out = TRUE;
			@proc_terminate($process);
			$terminate_deadline = microtime(TRUE) + $termination_grace_seconds;
			do {
				usleep(20000);
				$status = proc_get_status($process);
			} while(!empty($status['running']) && microtime(TRUE) < $terminate_deadline);
			if(!empty($status['running'])) @proc_terminate($process, 9);
			break;
		}
		usleep(20000);
	} while(TRUE);
	$closed_exit = proc_close($process);
	$output = file_get_contents($output_file);
	if(!is_string($output)) $output = '';
	@unlink($output_file);
	if($timed_out) {
		if($output !== '' && substr($output, -1) !== "\n") $output .= "\n";
		$output .= 'FAIL: check timed out after '.$timeout_seconds.' seconds; cleanup received up to '
			.$termination_grace_seconds." seconds before forced termination.\n";
		$exit_code = 124;
	} else {
		$exit_code = $observed_exit >= 0 ? $observed_exit : $closed_exit;
	}
}

function check_runner_classify($exit_code, $output) {
	if(intval($exit_code) !== 0) return 'FAIL';
	if(preg_match('/(?:^|\R)\s*FAIL:/i', (string)$output)) return 'FAIL';
	if(trim((string)$output) === '') return 'FAIL';
	return preg_match('/(?:^|\R)\s*SKIP:/i', (string)$output) ? 'SKIP' : 'PASS';
}

function check_runner_summary_line($output) {
	$lines = preg_split('/\R/', trim((string)$output));
	if(!is_array($lines)) return '';
	for($index = count($lines) - 1; $index >= 0; $index--) {
		$line = trim($lines[$index]);
		if($line === '') continue;
		return strlen($line) > 180 ? substr($line, 0, 177).'...' : $line;
	}
	return '';
}

?>
