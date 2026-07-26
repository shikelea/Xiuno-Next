<?php

$root = dirname(__DIR__);
$app = $root.'/tmp/plugin_cache_atomicity_smoke/';
$target = $app.'compiled.php';
$worker = $app.'worker.php';

function fail($message) {
	fwrite(STDERR, "FAIL: $message\n");
	exit(1);
}

function rm_dir($dir) {
	if(!is_dir($dir)) return;
	foreach(glob(rtrim($dir, '/').'/*') ?: array() as $item) {
		is_dir($item) ? rm_dir($item) : unlink($item);
	}
	rmdir($dir);
}

rm_dir($app);
mkdir($app, 0777, TRUE);
mkdir($app.'plugin/cache_mode_plugin/hook/', 0777, TRUE);
mkdir($app.'tmp/', 0777, TRUE);

$plugin_model = file_get_contents($root.'/model/plugin.func.php');
strpos($plugin_model, 'function plugin_cache_write_atomic($file, $s)') !== FALSE
	|| fail('Atomic plugin cache writer is missing.');
$include_section = substr($plugin_model, strpos($plugin_model, 'function _include('), strpos($plugin_model, 'function plugin_include_src_mtime') - strpos($plugin_model, 'function _include('));
substr_count($include_section, 'plugin_cache_write_atomic($tmpfile, $s)') === 2
	|| fail('_include() must publish both compilation passes through the atomic cache writer.');

$payloads = array(
	"<?php\nreturn '".str_repeat('a', 2 * 1024 * 1024)."';\n",
	"<?php\nreturn '".str_repeat('b', 2 * 1024 * 1024)."';\n",
	"<?php\nreturn '".str_repeat('c', 2 * 1024 * 1024)."';\n",
	"<?php\nreturn '".str_repeat('d', 2 * 1024 * 1024)."';\n",
);
file_put_contents($target, "<?php\nreturn 'stable';\n");
foreach($payloads as $i=>$payload) {
	$payload_file = $app.'payload_'.$i.'.php';
	file_put_contents($payload_file, $payload);
}

$worker_source = '<?php'."\n"
	.'define(\'DEBUG\', 0);'."\n"
	.'include '.var_export($root.'/xiunophp/misc.func.php', TRUE).';'."\n"
	.'include '.var_export($root.'/model/plugin.func.php', TRUE).';'."\n"
	.'$payload = file_get_contents($argv[1]);'."\n"
	.'for($i = 0; $i < 20; $i++) {'."\n"
	."\tplugin_cache_write_atomic(\$argv[2], \$payload) !== FALSE || exit(2);\n"
	.'}'."\n";
file_put_contents($worker, $worker_source);

defined('DEBUG') || define('DEBUG', 0);
include $root.'/xiunophp/array.func.php';
include $root.'/xiunophp/misc.func.php';
include $root.'/model/plugin.func.php';

defined('APP_PATH') || define('APP_PATH', $app);
file_put_contents($app.'plugin/cache_mode_plugin/conf.json', json_encode(array(
	'name'=>'cache mode plugin',
	'installed'=>1,
	'enable'=>1,
	'hooks_rank'=>array(),
	'overwrites_rank'=>array(),
)));
file_put_contents($app.'plugin/cache_mode_plugin/hook/cache_mode.php', "\$mode = 'plugin';\n");
$mode_source = $app.'cache_mode_target.php';
file_put_contents($mode_source, "<?php\n\$mode = 'core';\n// hook cache_mode.php\n");
$conf = array('tmp_path'=>$app.'tmp/', 'disabled_plugin'=>1);
$_SERVER['conf'] = $conf;
$safe_cache = _include($mode_source);
substr($safe_cache, -10) === '.safe_mode'
	|| fail('Safe mode must use a distinct include cache path.');
strpos(file_get_contents($safe_cache), "\$mode = 'plugin';") === FALSE
	|| fail('Safe-mode include caches must not compile enabled hooks.');
$conf['disabled_plugin'] = 0;
$_SERVER['conf'] = $conf;
$normal_cache = _include($mode_source);
$normal_cache !== $safe_cache
	|| fail('Normal and safe-mode include caches must not share a path.');
strpos(file_get_contents($normal_cache), "\$mode = 'plugin';") !== FALSE
	|| fail('Normal include caches must compile enabled hooks after leaving safe mode.');

plugin_cache_write_atomic($target, $payloads[0]) === strlen($payloads[0])
	|| fail('Atomic plugin cache writer failed a complete sequential write.');
hash_file('sha256', $target) === hash('sha256', $payloads[0])
	|| fail('Atomic plugin cache writer changed sequential payload bytes.');

if(DIRECTORY_SEPARATOR !== '\\' && function_exists('proc_open')) {
	$processes = array();
	foreach($payloads as $i=>$unused) {
		$command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($worker).' '.escapeshellarg($app.'payload_0.php').' '.escapeshellarg($target);
		$process = proc_open($command, array(0=>array('pipe', 'r'), 1=>array('pipe', 'w'), 2=>array('pipe', 'w')), $pipes);
		is_resource($process) || fail('Failed to start atomic cache writer process.');
		fclose($pipes[0]);
		$processes[] = array('process'=>$process, 'pipes'=>$pipes, 'exitcode'=>NULL);
	}

	unlink($target);
	$published = FALSE;
	do {
		$running = FALSE;
		foreach($processes as &$entry) {
			$status = proc_get_status($entry['process']);
			$running = $running || $status['running'];
			if(!$status['running'] && $status['exitcode'] !== -1) $entry['exitcode'] = $status['exitcode'];
		}
		unset($entry);
		$contents = is_file($target) ? file_get_contents($target) : FALSE;
		if($contents === FALSE) {
			if($published) fail('Concurrent readers observed the plugin cache disappear after publication.');
			continue;
		}
		$published = TRUE;
		if(hash('sha256', $contents) !== hash('sha256', $payloads[0])) {
			fail('Concurrent readers observed a missing or partial plugin cache file.');
		}
	} while($running);

	foreach($processes as $entry) {
		$stdout = stream_get_contents($entry['pipes'][1]);
		$stderr = stream_get_contents($entry['pipes'][2]);
		fclose($entry['pipes'][1]);
		fclose($entry['pipes'][2]);
		$close_code = proc_close($entry['process']);
		$code = $entry['exitcode'] === NULL ? $close_code : $entry['exitcode'];
		$code === 0 || fail('Atomic cache writer process failed: '.$stdout.$stderr);
	}
}

rm_dir($app);
echo "OK: plugin cache atomicity checks passed\n";

?>
