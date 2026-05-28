<?php

$root = realpath(__DIR__ . '/..');
if ($root === false) {
	fwrite(STDERR, "Unable to locate project root.\n");
	exit(1);
}

$samples = array_slice($argv, 1);
if (empty($samples)) {
	fwrite(STDERR, "Usage: php bin/smoke_ecosystem_sample.php <sample> [sample...]\n");
	exit(1);
}

if (count($samples) > 1) {
	$failed = false;
	foreach ($samples as $sample) {
		$cmd = 'php ' . escapeshellarg(__FILE__) . ' ' . escapeshellarg($sample) . ' 2>&1';
		exec($cmd, $output, $code);
		echo implode(PHP_EOL, $output) . PHP_EOL;
		$output = array();
		if ($code !== 0) {
			$failed = true;
		}
	}
	exit($failed ? 1 : 0);
}

$sample = $samples[0];
if (!preg_match('/^[A-Za-z0-9_-]+$/', $sample)) {
	fwrite(STDERR, "Invalid sample name: $sample\n");
	exit(1);
}

$sampleDir = $root . '/plugin/' . $sample;
$confFile = $sampleDir . '/conf.json';
if (!is_dir($sampleDir) || !is_file($confFile)) {
	fwrite(STDERR, "Sample not found or missing conf.json: $sample\n");
	exit(1);
}

$originalConf = (string)file_get_contents($confFile);
$confJson = json_decode($originalConf, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
if (!is_array($confJson)) {
	fwrite(STDERR, "Invalid sample conf.json for $sample: " . json_last_error_msg() . "\n");
	exit(1);
}

$hookFiles = glob($sampleDir . '/hook/*.*') ?: array();
if (empty($hookFiles)) {
	fwrite(STDERR, "Sample has no hook files: $sample\n");
	exit(1);
}

$hookNames = array_map('basename', $hookFiles);
$sources = find_hook_sources($root, $hookNames);
$missingHooks = array_values(array_diff($hookNames, array_keys($sources['by_hook'])));
if (!empty($missingHooks)) {
	fwrite(STDERR, "No core hook source found for $sample: " . implode(', ', $missingHooks) . "\n");
	exit(1);
}

define('DEBUG', 0);
define('APP_PATH', $root . '/');
defined('ADMIN_PATH') || define('ADMIN_PATH', APP_PATH . 'admin/');
defined('XIUNOPHP_PATH') || define('XIUNOPHP_PATH', APP_PATH . 'xiunophp/');

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/ecosystem-sample-smoke';
$_SERVER['REQUEST_METHOD'] = 'GET';

$conf = include APP_PATH . 'conf/conf.default.php';
$conf['tmp_path'] = APP_PATH . 'tmp/ecosystem_sample_smoke/';
$conf['log_path'] = APP_PATH . 'tmp/ecosystem_sample_smoke_log/';
$conf['disabled_plugin'] = 0;
$_SERVER['conf'] = $conf;

include APP_PATH . 'xiunophp/xiunophp.php';
include APP_PATH . 'model/plugin.func.php';

$updatedConf = $confJson;
$updatedConf['installed'] = 1;
$updatedConf['enable'] = 1;

$encodedConf = json_encode($updatedConf, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
if ($encodedConf === false) {
	fwrite(STDERR, "Unable to encode temporary conf.json for $sample: " . json_last_error_msg() . "\n");
	exit(1);
}

$errors = array();
try {
	file_put_contents($confFile, $encodedConf . PHP_EOL);
	plugin_init();

	$compiledFiles = array();
	foreach ($sources['files'] as $sourceFile) {
		$compiled = plugin_compile_srcfile($sourceFile);
		$relative = substr(str_replace('\\', '/', $sourceFile), strlen(str_replace('\\', '/', APP_PATH)));
		$outFile = $conf['tmp_path'] . str_replace(array('/', '\\'), '_', $relative);
		$dir = dirname($outFile);
		if (!is_dir($dir)) {
			mkdir($dir, 0755, true);
		}
		file_put_contents($outFile, $compiled);
		$compiledFiles[] = $relative;

		if (strtolower(pathinfo($sourceFile, PATHINFO_EXTENSION)) === 'php') {
			$lint = lint_php($outFile);
			if ($lint !== null) {
				$errors[] = "$relative failed compiled PHP lint: $lint";
			}
		}
	}
} finally {
	file_put_contents($confFile, $originalConf);
}

if (!empty($errors)) {
	fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
	exit(1);
}

echo sprintf(
	"Ecosystem sample smoke OK: %s (%d hooks, %d core sources compiled)\n",
	$sample,
	count($hookNames),
	count($compiledFiles)
);

exit(0);

function find_hook_sources(string $root, array $hookNames): array
{
	$roots = array(
		$root . '/model',
		$root . '/route',
		$root . '/view/htm',
		$root . '/admin/route',
		$root . '/admin/view/htm',
		$root . '/lang',
	);
	$files = array(
		$root . '/model.inc.php',
		$root . '/index.inc.php',
		$root . '/admin/index.inc.php',
	);

	foreach ($roots as $dir) {
		if (!is_dir($dir)) {
			continue;
		}
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
		foreach ($iterator as $file) {
			if ($file->isFile()) {
				$files[] = $file->getPathname();
			}
		}
	}

	$byHook = array();
	$sourceSet = array();
	foreach ($files as $file) {
		$content = file_get_contents($file);
		if ($content === false) {
			continue;
		}
		foreach ($hookNames as $hookName) {
			if (strpos($content, $hookName) !== false) {
				$byHook[$hookName][] = $file;
				$sourceSet[$file] = $file;
			}
		}
	}

	return array(
		'by_hook' => $byHook,
		'files' => array_values($sourceSet),
	);
}

function lint_php(string $path): ?string
{
	$cmd = 'php -l ' . escapeshellarg($path) . ' 2>&1';
	exec($cmd, $output, $code);
	if ($code === 0) {
		return null;
	}

	return trim(implode("\n", $output));
}
