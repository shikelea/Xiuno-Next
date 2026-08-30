<?php

$root = dirname(__DIR__);
$app = $root.'/tmp/plugin_cache_atomicity_smoke_'.bin2hex(random_bytes(6)).'/';
$target = $app.'compiled.php';
$worker = $app.'worker.php';
$reader_worker = $app.'reader-worker.php';
$skips = array();

function fail($message) {
	fwrite(STDERR, "FAIL: $message\n");
	exit(1);
}

function rm_dir($dir) {
	if(is_link($dir) || is_file($dir)) {
		@unlink($dir);
		return;
	}
	if(!is_dir($dir)) return;
	$items = glob(rtrim($dir, '/').'/*') ?: array();
	$dotitems = glob(rtrim($dir, '/').'/.??*') ?: array();
	foreach(array_merge($items, $dotitems) as $item) {
		(is_dir($item) && !is_link($item)) ? rm_dir($item) : @unlink($item);
	}
	@rmdir($dir);
}

rm_dir($app);
mkdir($app, 0777, TRUE);
register_shutdown_function(function() use($app) { rm_dir($app); });
mkdir($app.'plugin/cache_mode_plugin/hook/', 0777, TRUE);
mkdir($app.'tmp/', 0777, TRUE);

$plugin_model = file_get_contents($root.'/model/plugin.func.php');
strpos($plugin_model, 'function plugin_cache_write_atomic($file, $s, $source = \'\')') !== FALSE
	|| fail('Atomic plugin cache writer is missing.');
$include_section = substr($plugin_model, strpos($plugin_model, 'function _include('), strpos($plugin_model, 'function plugin_include_src_mtime') - strpos($plugin_model, 'function _include('));
substr_count($include_section, 'plugin_cache_write_atomic($tmpfile, $s, $srcfile)') === 1
	&& strpos($include_section, '$compile_stage = plugin_cache_stage_write($tmpfile, $s);') !== FALSE
	&& strpos($include_section, '$s = plugin_compile_srcfile($compile_stage);') !== FALSE
	&& strpos($include_section, 'xn_unlink($compile_stage);') !== FALSE
	|| fail('_include() must keep its first pass private and publish only the final compiled generation.');
strpos($plugin_model, 'function plugin_cache_php_syntax_valid($s, &$detail = \'\')') !== FALSE
	&& strpos($plugin_model, 'token_get_all($s, TOKEN_PARSE);') !== FALSE
	|| fail('Generated PHP caches must pass an in-process syntax preflight before publication.');

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
	.'$iterations = isset($argv[3]) ? max(1, intval($argv[3])) : 20;'."\n"
	.'for($i = 0; $i < $iterations; $i++) {'."\n"
	."\tplugin_cache_write_atomic(\$argv[2], \$payload) !== FALSE || exit(2);\n"
	.'}'."\n";
file_put_contents($worker, $worker_source);

$reader_worker_source = '<?php'."\n"
	.'define(\'DEBUG\', 0);'."\n"
	.'define(\'APP_PATH\', '.var_export($app, TRUE).');'."\n"
	.'define(\'XIUNOPHP_PATH\', '.var_export($root.'/xiunophp/', TRUE).');'."\n"
	.'include XIUNOPHP_PATH.\'array.func.php\';'."\n"
	.'include XIUNOPHP_PATH.\'misc.func.php\';'."\n"
	.'include '.var_export($root.'/model/plugin.func.php', TRUE).';'."\n"
	.'$conf = array(\'tmp_path\'=>'.var_export($app.'tmp/', TRUE).', \'disabled_plugin\'=>1);'."\n"
	.'$_SERVER[\'conf\'] = $conf;'."\n"
	.'$leased = _include($argv[1]);'."\n"
	.'file_put_contents($argv[2], $leased) !== FALSE || exit(2);'."\n"
	.'$deadline = microtime(TRUE) + 10;'."\n"
	.'while(!is_file($argv[3]) && microtime(TRUE) < $deadline) usleep(10000);'."\n"
	.'is_file($argv[3]) || exit(3);'."\n"
	.'$value = include $leased;'."\n"
	.'file_put_contents($argv[4], (string)$value) !== FALSE || exit(4);'."\n";
file_put_contents($reader_worker, $reader_worker_source);

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

// A cache from a pre-lock release may have an arbitrarily high mtime. It must still be treated as
// stale, and a failed first compliant compile must not promote those legacy bytes merely by creating
// the sibling lock. Once the source is fixed, the next request attempt must rebuild it.
$legacy_source = $app.'legacy_cache_target.php';
$conf['disabled_plugin'] = 1;
$_SERVER['conf'] = $conf;
$legacy_cache = $conf['tmp_path'].substr(str_replace('/', '_', $legacy_source), strlen(APP_PATH)).'.safe_mode';
file_put_contents($legacy_source, "<?php\nfunction legacy_cache_broken( {\n");
file_put_contents($legacy_cache, "<?php\nreturn 'legacy-stale';\n");
@unlink($legacy_cache.'.lock');
touch($legacy_cache, time() + 3600);
clearstatcache(TRUE, $legacy_cache);
$legacy_failed = FALSE;
try {
	_include($legacy_source);
} catch(RuntimeException $e) {
	$legacy_failed = TRUE;
}
$legacy_failed || fail('Malformed regeneration must fail instead of returning a high-mtime legacy cache.');
is_file($legacy_cache.'.lock') || fail('The first compliant regeneration attempt must establish the stable cache lock.');
clearstatcache(TRUE, $legacy_cache);
$legacy_stale_mtime = filemtime($legacy_cache);
$legacy_stale_mtime !== FALSE && $legacy_stale_mtime <= 2
	|| fail('A failed regeneration must leave legacy cache bytes stale for the next request attempt.');
file_put_contents($legacy_source, "<?php\nreturn 'fresh-after-legacy';\n");
touch($legacy_source, time() + 7200);
clearstatcache(TRUE, $legacy_source);
$legacy_rebuilt = _include($legacy_source);
$legacy_rebuilt_value = include $legacy_rebuilt;
$legacy_rebuilt_value === 'fresh-after-legacy'
	|| fail('A stale locked legacy cache must rebuild after the source is repaired.');
$conf['disabled_plugin'] = 0;
$_SERVER['conf'] = $conf;

plugin_cache_write_atomic($target, $payloads[0]) === strlen($payloads[0])
	|| fail('Atomic plugin cache writer failed a complete sequential write.');
hash_file('sha256', $target) === hash('sha256', $payloads[0])
	|| fail('Atomic plugin cache writer changed sequential payload bytes.');

// A malformed generated cache must never replace the last complete generation. This uses the same
// parser as the running PHP process, records target/source context, and leaves no staging bytes.
$syntax_log = $app.'cache-syntax.log';
$old_error_log = ini_get('error_log');
ini_set('error_log', $syntax_log);
$invalid_payload = "<?php\nfunction broken( {\n";
$invalid_result = plugin_cache_write_atomic($target, $invalid_payload, 'plugin/demo/hook/broken.php');
$invalid_new_target = $app.'invalid-new.php';
$invalid_new_result = plugin_cache_write_atomic($invalid_new_target, $invalid_payload, 'plugin/demo/hook/new-broken.php');
$invalid_existing_target = $app.'invalid-existing.php';
file_put_contents($invalid_existing_target, $invalid_payload);
$invalid_existing_result = plugin_cache_write_atomic($invalid_existing_target, $invalid_payload, 'plugin/demo/hook/old-broken.php');
ini_set('error_log', $old_error_log);
$syntax_detail = '';
$invalid_result === FALSE && $invalid_new_result === FALSE && $invalid_existing_result === FALSE
	|| fail('Malformed generated PHP cache unexpectedly passed syntax preflight.');
hash_file('sha256', $target) === hash('sha256', $payloads[0])
	|| fail('Syntax preflight failure replaced the last complete cache generation.');
!is_file($invalid_new_target)
	|| fail('Syntax preflight failure published a new malformed cache target.');
!is_file($invalid_existing_target)
	|| fail('An existing cache with the same malformed bytes was treated as a valid fast-path hit.');
empty(glob($target.'.*.tmp') ?: array()) && empty(glob($invalid_new_target.'.*.tmp') ?: array())
	&& empty(glob($invalid_existing_target.'.*.tmp') ?: array())
	|| fail('Syntax preflight failure left an owned staging file behind.');
plugin_cache_php_syntax_valid($invalid_payload, $syntax_detail) === FALSE && $syntax_detail !== ''
	|| fail('Syntax preflight must return a diagnostic for malformed generated PHP.');
$syntax_log_text = is_file($syntax_log) ? file_get_contents($syntax_log) : '';
strpos($syntax_log_text, 'target='.$target) !== FALSE
	&& strpos($syntax_log_text, 'source=plugin/demo/hook/broken.php') !== FALSE
	|| fail('Syntax preflight diagnostics must identify the cache target and original compile source.');

if(function_exists('proc_open')) {
	// _include() returns a path before the caller opens it. A second request clearing caches in that
	// interval must retain the published bytes under the reader's shared lease, mark them stale for
	// the next request, and delete them only after the reader exits.
	$lease_source = $app.'lease_source.php';
	$lease_ready = $app.'lease_ready';
	$lease_go = $app.'lease_go';
	$lease_result = $app.'lease_result';
	file_put_contents($lease_source, "<?php\nreturn 'lease-stable';\n");
	$lease_command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($reader_worker)
		.' '.escapeshellarg($lease_source)
		.' '.escapeshellarg($lease_ready)
		.' '.escapeshellarg($lease_go)
		.' '.escapeshellarg($lease_result);
	$lease_process = proc_open($lease_command, array(0=>array('pipe', 'r'), 1=>array('pipe', 'w'), 2=>array('pipe', 'w')), $lease_pipes);
	is_resource($lease_process) || fail('Failed to start include-cache reader lease process.');
	fclose($lease_pipes[0]);
	$lease_deadline = microtime(TRUE) + 10;
	do {
		if(is_file($lease_ready)) break;
		$lease_status = proc_get_status($lease_process);
		if(!$lease_status['running']) break;
		usleep(10000);
	} while(microtime(TRUE) < $lease_deadline);
	is_file($lease_ready) || fail('Include-cache reader did not publish its leased path.');
	$leased_cache = file_get_contents($lease_ready);
	is_string($leased_cache) && $leased_cache !== '' && is_file($leased_cache)
		|| fail('Include-cache reader lease did not point to a published cache.');

	plugin_clear_tmp_dir();
	clearstatcache(TRUE, $leased_cache);
	is_file($leased_cache)
		|| fail('Cache cleanup deleted a path already returned to an active include reader.');
	$leased_mtime = filemtime($leased_cache);
	$leased_mtime !== FALSE && $leased_mtime <= 2
		|| fail('An active reader cache must be marked stale for the next request.');
	file_put_contents($lease_go, 'go');

	$lease_exitcode = NULL;
	$lease_deadline = microtime(TRUE) + 10;
	do {
		$lease_status = proc_get_status($lease_process);
		if(!$lease_status['running']) {
			if($lease_status['exitcode'] !== -1) $lease_exitcode = $lease_status['exitcode'];
			break;
		}
		usleep(10000);
	} while(microtime(TRUE) < $lease_deadline);
	$lease_stdout = stream_get_contents($lease_pipes[1]);
	$lease_stderr = stream_get_contents($lease_pipes[2]);
	fclose($lease_pipes[1]);
	fclose($lease_pipes[2]);
	$lease_close_code = proc_close($lease_process);
	$lease_exitcode === NULL AND $lease_exitcode = $lease_close_code;
	$lease_exitcode === 0
		|| fail('Include-cache reader failed after concurrent cleanup: '.$lease_stdout.$lease_stderr);
	file_get_contents($lease_result) === 'lease-stable'
		|| fail('Active include reader did not execute the complete leased cache bytes.');

	plugin_clear_tmp_dir();
	clearstatcache(TRUE, $leased_cache);
	!is_file($leased_cache)
		|| fail('Cache cleanup retained a stale published cache after its reader released the lease.');

	// Hold the cache lock so the child has written a complete staging file but cannot publish it.
	// Clearing tmp in this interval must keep both the shared lock inode and the fresh staging file.
	$blocked_target = $app.'tmp/concurrent_cache.php';
	$blocked_lock_path = $blocked_target.'.lock';
	$blocked_lock = fopen($blocked_lock_path, 'c');
	$blocked_lock && flock($blocked_lock, LOCK_EX)
		|| fail('Failed to hold the cache lock for the clear-during-write probe.');
	$blocked_command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($worker).' '.escapeshellarg($app.'payload_1.php').' '.escapeshellarg($blocked_target).' 1';
	$blocked_process = proc_open($blocked_command, array(0=>array('pipe', 'r'), 1=>array('pipe', 'w'), 2=>array('pipe', 'w')), $blocked_pipes);
	is_resource($blocked_process) || fail('Failed to start the blocked cache writer process.');
	fclose($blocked_pipes[0]);

	$blocked_stage = '';
	$stage_deadline = microtime(TRUE) + 10;
	do {
		$blocked_stages = glob($blocked_target.'.*.tmp') ?: array();
		if(count($blocked_stages) === 1) {
			clearstatcache(TRUE, $blocked_stages[0]);
			if(@filesize($blocked_stages[0]) === strlen($payloads[1])) {
				$blocked_stage = $blocked_stages[0];
				break;
			}
		}
		$status = proc_get_status($blocked_process);
		if(!$status['running']) break;
		usleep(10000);
	} while(microtime(TRUE) < $stage_deadline);
	$blocked_stage !== '' || fail('Blocked cache writer did not expose one complete staging file.');
	// Give the child time to leave file_put_contents_try() and block on the held publish lock.
	usleep(50000);

	plugin_clear_tmp_dir();
	$lock_survived_clear = is_file($blocked_lock_path);
	$stage_survived_clear = is_file($blocked_stage);
	$lock_probe = fopen($blocked_lock_path, 'c');
	$lock_probe_acquired = $lock_probe ? flock($lock_probe, LOCK_EX | LOCK_NB) : FALSE;
	if($lock_probe_acquired) flock($lock_probe, LOCK_UN);
	$lock_probe AND fclose($lock_probe);

	flock($blocked_lock, LOCK_UN);
	fclose($blocked_lock);
	$blocked_exitcode = NULL;
	$blocked_deadline = microtime(TRUE) + 10;
	do {
		$blocked_status = proc_get_status($blocked_process);
		if(!$blocked_status['running']) {
			if($blocked_status['exitcode'] !== -1) $blocked_exitcode = $blocked_status['exitcode'];
			break;
		}
		usleep(10000);
	} while(microtime(TRUE) < $blocked_deadline);
	$blocked_stdout = stream_get_contents($blocked_pipes[1]);
	$blocked_stderr = stream_get_contents($blocked_pipes[2]);
	fclose($blocked_pipes[1]);
	fclose($blocked_pipes[2]);
	$blocked_close_code = proc_close($blocked_process);
	$blocked_exitcode === NULL AND $blocked_exitcode = $blocked_close_code;

	$lock_survived_clear
		|| fail('plugin_clear_tmp_dir() removed an active cache lock inode.');
	!$lock_probe_acquired
		|| fail('plugin_clear_tmp_dir() replaced the held cache lock with a second lock inode.');
	$stage_survived_clear
		|| fail('plugin_clear_tmp_dir() removed a fresh cache staging file before publication.');
	$blocked_exitcode === 0
		|| fail('Cache writer failed after concurrent tmp clearing: '.$blocked_stdout.$blocked_stderr);
	hash_file('sha256', $blocked_target) === hash('sha256', $payloads[1])
		|| fail('Cache writer published incorrect bytes after concurrent tmp clearing.');
	empty(glob($blocked_target.'.*.tmp') ?: array())
		|| fail('Successful cache publication left a staging file behind.');

	$stale_stage = $blocked_target.'.9999.'.str_repeat('a', 22).'.tmp';
	file_put_contents($stale_stage, 'stale');
	touch($stale_stage, time() - 90000);
	plugin_clear_tmp_dir();
	!is_file($blocked_target)
		|| fail('plugin_clear_tmp_dir() stopped removing published cache files.');
	!is_file($stale_stage)
		|| fail('plugin_clear_tmp_dir() retained a stale cache staging file.');
	is_file($blocked_lock_path)
		|| fail('plugin_clear_tmp_dir() removed the stable cache lock after publication.');
} else {
	$skips[] = 'proc_open is unavailable; reader-lease and clear-during-write concurrency were not exercised.';
}

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

// Lifecycle cache-invalidation failure contract: acquire the visibility writer while tmp is valid,
// then make only the cache directory unavailable. Each state mutation must report failure while
// restoring both request memory and conf.json. This isolates cache failure from lock acquisition and
// config-file writability, matching the failure order seen by the admin lifecycle route.
$lifecycle_dir = 'lifecycle_failure_plugin';
$lifecycle_path = $app.'plugin/'.$lifecycle_dir.'/';
is_dir($lifecycle_path) || mkdir($lifecycle_path, 0777, TRUE)
	|| fail('Could not create lifecycle failure fixture directory.');
$valid_tmp_path = $conf['tmp_path'];
$conf['tmp_path'] = $valid_tmp_path;
$_SERVER['conf'] = $conf;
plugin_state_visibility_write_lock_start()
	|| fail('Could not acquire the valid plugin-state writer before lifecycle cache failure injection.');
$missing_tmp_path = $app.'missing-runtime-cache/';
!is_dir($missing_tmp_path)
	|| fail('Lifecycle cache failure fixture unexpectedly exists.');

$lifecycle_cases = array(
	'enable'=>array('installed'=>1, 'enable'=>0),
	'disable'=>array('installed'=>1, 'enable'=>1),
	'install'=>array('installed'=>0, 'enable'=>0),
	'unstall'=>array('installed'=>1, 'enable'=>1),
);
foreach($lifecycle_cases as $operation=>$old_state) {
	$fixture_state = array(
		'name'=>'Lifecycle failure fixture',
		'installed'=>$old_state['installed'],
		'enable'=>$old_state['enable'],
		'hooks_rank'=>array(),
		'overwrites_rank'=>array(),
	);
	file_put_contents($lifecycle_path.'conf.json', xn_json_encode($fixture_state, TRUE)) !== FALSE
		|| fail('Could not reset lifecycle fixture conf.json for '.$operation.'.');
	$plugins = array($lifecycle_dir=>$fixture_state);
	$conf['tmp_path'] = $missing_tmp_path;
	$_SERVER['conf'] = $conf;
	$result = call_user_func('plugin_'.$operation, $lifecycle_dir);
	$result === FALSE
		|| fail('plugin_'.$operation.'() reported success after runtime-cache invalidation failed.');
	isset($plugins[$lifecycle_dir])
		&& intval($plugins[$lifecycle_dir]['installed']) === $old_state['installed']
		&& intval($plugins[$lifecycle_dir]['enable']) === $old_state['enable']
		|| fail('plugin_'.$operation.'() did not restore its in-memory state after cache invalidation failed.');
	$disk_state = xn_json_decode(file_get_contents($lifecycle_path.'conf.json'));
	is_array($disk_state)
		&& intval($disk_state['installed']) === $old_state['installed']
		&& intval($disk_state['enable']) === $old_state['enable']
		|| fail('plugin_'.$operation.'() did not restore conf.json after cache invalidation failed.');
}

// Legacy bulk helpers are public model contracts. A failed child must propagate instead of being
// discarded and converted into an implicit NULL/success result.
foreach(array('install_all'=>array('installed'=>0, 'enable'=>0), 'unstall_all'=>array('installed'=>1, 'enable'=>1)) as $operation=>$old_state) {
	$fixture_state = array(
		'name'=>'Lifecycle batch failure fixture',
		'installed'=>$old_state['installed'],
		'enable'=>$old_state['enable'],
		'hooks_rank'=>array(),
		'overwrites_rank'=>array(),
	);
	file_put_contents($lifecycle_path.'conf.json', xn_json_encode($fixture_state, TRUE)) !== FALSE
		|| fail('Could not reset lifecycle batch fixture conf.json for '.$operation.'.');
	$plugins = array($lifecycle_dir=>$fixture_state);
	$conf['tmp_path'] = $missing_tmp_path;
	$_SERVER['conf'] = $conf;
	call_user_func('plugin_'.$operation) === FALSE
		|| fail('plugin_'.$operation.'() discarded its child lifecycle failure.');
	$disk_state = xn_json_decode(file_get_contents($lifecycle_path.'conf.json'));
	intval($plugins[$lifecycle_dir]['installed']) === $old_state['installed']
		&& intval($plugins[$lifecycle_dir]['enable']) === $old_state['enable']
		&& is_array($disk_state)
		&& intval($disk_state['installed']) === $old_state['installed']
		&& intval($disk_state['enable']) === $old_state['enable']
		|| fail('plugin_'.$operation.'() child failure left memory or conf.json mutated.');
}

$conf['tmp_path'] = $valid_tmp_path;
$_SERVER['conf'] = $conf;
plugin_state_visibility_write_lock_end()
	|| fail('Could not release the lifecycle failure fixture writer lock.');

rm_dir($app);
echo "OK: plugin cache atomicity checks passed for available fixtures\n";
foreach($skips as $skip) echo 'SKIP: '.$skip.PHP_EOL;

?>
