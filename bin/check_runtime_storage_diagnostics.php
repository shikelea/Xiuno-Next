<?php

$root = dirname(__DIR__).DIRECTORY_SEPARATOR;

function fail($message) {
	fwrite(STDERR, "FAIL: $message\n");
	exit(1);
}

function remove_tree($path) {
	if(is_dir($path)) {
		foreach(scandir($path) as $entry) {
			if($entry === '.' || $entry === '..') continue;
			remove_tree($path.DIRECTORY_SEPARATOR.$entry);
		}
		@rmdir($path);
	} elseif(is_file($path)) {
		@unlink($path);
	}
}

$fixture = sys_get_temp_dir().DIRECTORY_SEPARATOR.'xiuno-storage-diagnostic-'.getmypid().'-'.bin2hex(random_bytes(4));
$app = $fixture.DIRECTORY_SEPARATOR.'app';
$external = $fixture.DIRECTORY_SEPARATOR.'external';
foreach(array($app, $app.DIRECTORY_SEPARATOR.'upload', $app.DIRECTORY_SEPARATOR.'log', $external) as $path) {
	if(!mkdir($path, 0777, TRUE) && !is_dir($path)) fail('failed to create storage diagnostic fixture');
}
register_shutdown_function(function() use ($fixture) { remove_tree($fixture); });

include $root.'model/diagnostic.func.php';

$probed = array();
$probe = function($path) use (&$probed, $external) {
	$normalized = str_replace('\\', '/', $path);
	$probed[] = $normalized;
	return strpos($normalized, str_replace('\\', '/', $external)) === 0 ? 2222 : 1111;
};
$conf = array(
	'upload_path'=>'./upload/',
	'tmp_path'=>$external,
	'log_path'=>'log/',
);
$spaces = diagnostic_storage_spaces($conf, $app, $probe);

array_keys($spaces) === array('app_path', 'upload_path', 'tmp_path', 'log_path') || fail('all runtime storage paths must be reported in a stable order');
count($probed) === 4 || fail('each configured runtime path must be probed instead of reusing APP_PATH free space');
$spaces['upload_path']['path'] === realpath($app.DIRECTORY_SEPARATOR.'upload') || fail('relative upload_path must resolve against APP_PATH');
$spaces['log_path']['path'] === realpath($app.DIRECTORY_SEPARATOR.'log') || fail('relative log_path must resolve against APP_PATH');
$spaces['tmp_path']['path'] === realpath($external) || fail('absolute external tmp_path must remain external');
$spaces['tmp_path']['free_bytes'] === 2222.0 || fail('external tmp_path must use its own disk probe result');
$spaces['app_path']['free_bytes'] === 1111.0 || fail('APP_PATH must retain its own disk probe result');
foreach($spaces as $space) {
	$space['exists'] || fail($space['key'].' fixture must be reported as existing');
	$space['writable'] || fail($space['key'].' fixture must be reported as writable');
}

$missing = diagnostic_storage_spaces(array('tmp_path'=>'missing/path'), $app, function($path) { return FALSE; });
$missing['tmp_path']['exists'] === FALSE || fail('missing runtime path must not be reported as existing');
$missing['tmp_path']['free_bytes'] === FALSE || fail('failed disk probe must remain unknown instead of becoming zero');

echo "OK: runtime storage diagnostics checks passed\n";
