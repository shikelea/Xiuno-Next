<?php

function repository_hygiene_git($root, $arguments, &$output, &$status) {
	$command = 'git -C ' . escapeshellarg($root);
	foreach ($arguments as $argument) {
		$command .= ' ' . escapeshellarg($argument);
	}
	$command .= ' 2>&1';

	$output = array();
	$status = 0;
	exec($command, $output, $status);
}

function repository_hygiene_gitignore_source($line) {
	if (!preg_match('/^(.*):\d+:[^\t]*\t/', $line, $matches)) {
		return '';
	}

	return str_replace('\\', '/', $matches[1]);
}

function repository_hygiene_is_root_gitignore_source($source, $root) {
	$source = str_replace('\\', '/', $source);
	$rootIgnore = rtrim(str_replace('\\', '/', $root), '/') . '/.gitignore';

	return $source === '.gitignore' || strcasecmp($source, $rootIgnore) === 0;
}

function repository_hygiene_is_forbidden_local_path($path) {
	$path = str_replace('\\', '/', $path);
	if (strncmp($path, './', 2) === 0) {
		$path = substr($path, 2);
	}
	$path = strtolower($path);

	foreach (array(
		'.agents/',
		'.codex/',
		'.skills/',
		'skills/',
		'.playwright-cli/',
		'.ocr/',
		'.open-code-review/',
	) as $prefix) {
		if (strpos($path, $prefix) === 0) {
			return true;
		}
	}

	return strpos($path, '.ocr.') === 0
		|| strpos($path, 'ocr.local.') === 0
		|| strpos($path, 'open-code-review.local.') === 0;
}

$root = realpath(__DIR__ . '/..');
if ($root === false) {
	fwrite(STDERR, "Unable to locate project root.\n");
	exit(1);
}

$errors = array();
$ignore = file_get_contents($root . '/.gitignore');
$ignoreLines = $ignore === false ? array() : explode("\n", str_replace("\r", '', $ignore));
$requiredIgnoreRules = array(
	'/docs/',
	'/.agents/',
	'/.codex/',
	'/.skills/',
	'/skills/',
	'/.playwright-cli/',
	'/.ocr/',
	'/.ocr.*',
	'/.open-code-review/',
	'/ocr.local.*',
	'/open-code-review.local.*',
);
foreach ($requiredIgnoreRules as $rule) {
	if (!in_array($rule, $ignoreLines, true)) {
		$errors[] = 'Missing required repository ignore rule: ' . $rule;
	}
}

$ignoredExamples = array(
	'.agents/skills/local/SKILL.md',
	'.codex/skills/local/SKILL.md',
	'.skills/local/SKILL.md',
	'skills/local/SKILL.md',
	'.playwright-cli/page.yml',
	'.ocr/config.json',
	'.ocr.yaml',
	'.open-code-review/config.json',
	'ocr.local.json',
	'open-code-review.local.json',
);
foreach ($ignoredExamples as $path) {
	repository_hygiene_git($root, array('check-ignore', '--no-index', '-v', '--', $path), $output, $status);
	if ($status !== 0) {
		$errors[] = 'Local-only path is not ignored by the repository: ' . $path;
		continue;
	}

	$source = isset($output[0]) ? repository_hygiene_gitignore_source($output[0]) : '';
	if (!repository_hygiene_is_root_gitignore_source($source, $root)) {
		$errors[] = 'Local-only path must be ignored by the repository .gitignore: ' . $path;
	}
}

$sourceExamples = array(
	'src/Skills/Registry.php',
	'bin/check_ocr_contract.php',
	'view/js/playwright-adapter.js',
);
foreach ($sourceExamples as $path) {
	repository_hygiene_git($root, array('check-ignore', '--no-index', '-v', '--', $path), $output, $status);
	$source = isset($output[0]) ? repository_hygiene_gitignore_source($output[0]) : '';
	if ($status === 0 && repository_hygiene_is_root_gitignore_source($source, $root)) {
		$errors[] = 'Repository ignore rules are too broad and hide normal source: ' . $path;
	} elseif ($status !== 0 && $status !== 1) {
		$errors[] = 'Unable to inspect repository ignore behavior for: ' . $path;
	}
}

repository_hygiene_git($root, array('-c', 'core.quotePath=false', 'ls-files', '--cached'), $indexedFiles, $status);
if ($status !== 0) {
	$errors[] = 'Unable to inspect files in the Git index.';
} else {
	$trackedDocs = array();
	$indexedLocalFiles = array();
	foreach ($indexedFiles as $path) {
		$normalizedPath = str_replace('\\', '/', $path);
		if (strpos($normalizedPath, 'docs/') === 0) {
			$trackedDocs[] = $path;
		}
		if (repository_hygiene_is_forbidden_local_path($normalizedPath)) {
			$indexedLocalFiles[] = $path;
		}
	}
	if ($trackedDocs) {
		$errors[] = 'docs/ must not contain Git-tracked files: ' . implode(', ', $trackedDocs);
	}
	if ($indexedLocalFiles) {
		$errors[] = 'Local agent, skill, browser, or OCR state must not be present in the Git index: ' . implode(', ', $indexedLocalFiles);
	}
}

foreach (array('README.md', 'CONTRIBUTING.md') as $file) {
	$content = file_get_contents($root . '/' . $file);
	if ($content !== false && preg_match('#\]\(docs(?:/|\))#', $content)) {
		$errors[] = "$file must not link to local-only docs/.";
	}
}

if ($errors) {
	fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
	exit(1);
}

echo "Repository hygiene checks passed\n";
