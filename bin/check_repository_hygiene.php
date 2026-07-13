<?php

$root = realpath(__DIR__ . '/..');
if ($root === false) {
	fwrite(STDERR, "Unable to locate project root.\n");
	exit(1);
}

$errors = array();
$ignore = file_get_contents($root . '/.gitignore');
if ($ignore === false || !preg_match('#^/docs/$#m', str_replace("\r", '', $ignore))) {
	$errors[] = 'The local docs directory must remain ignored.';
}

$trackedDocs = array();
$status = 0;
exec('git -C ' . escapeshellarg($root) . ' ls-files -- docs 2>&1', $trackedDocs, $status);
if ($status !== 0) {
	$errors[] = 'Unable to inspect tracked docs files.';
} elseif (!empty($trackedDocs)) {
	$errors[] = 'docs/ must not contain Git-tracked files: ' . implode(', ', $trackedDocs);
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
