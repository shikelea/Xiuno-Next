<?php

$root = dirname(__DIR__) . '/';
$modelInc = file_get_contents($root . 'model.inc.php');
$errors = array();

if($modelInc === FALSE) {
	$errors[] = 'failed to read model.inc.php';
} else {
	$tokens = token_get_all($modelInc);
	$modelForCheck = '';
	foreach($tokens as $token) {
		if(is_array($token)) {
			if($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) continue;
			$modelForCheck .= $token[1];
		} else {
			$modelForCheck .= $token;
		}
	}
	$modelCompact = preg_replace('/\s+/', '', $modelForCheck);
	if(strpos($modelForCheck, '$model_min_mtime = filemtime($model_min_file);') === FALSE) {
		$errors[] = 'model.min.php cache must record its mtime.';
	}
	if(strpos($modelForCheck, '$legacy_unlocked_cache = $isfile && !is_file($model_min_file.\'.lock\');') === FALSE
		|| strpos($modelForCheck, '@touch($model_min_file, 1)') === FALSE
		|| strpos($modelForCheck, 'if($legacy_unlocked_cache || filemtime(__FILE__) > $model_min_mtime)') === FALSE
		|| strpos($modelForCheck, 'if($legacy_unlocked_cache && $model_min_rebuilt)') === FALSE) {
		$errors[] = 'Legacy aggregate caches without a sibling lock must be marked stale before forced regeneration.';
	}
	if(strpos($modelForCheck, 'filemtime(__FILE__) > $model_min_mtime') === FALSE) {
		$errors[] = 'model.min.php cache must rebuild when model.inc.php changes.';
	}
	if(strpos($modelForCheck, 'filemtime($model_file) > $model_min_mtime') === FALSE) {
		$errors[] = 'model.min.php cache must rebuild when source model files are newer.';
	}
	if(strpos($modelForCheck, '$isfile = FALSE;') === FALSE) {
		$errors[] = 'model.min.php cache must mark stale cache as missing before regeneration.';
	}
	if(strpos($modelCompact, "plugin_cache_write_atomic(\$model_min_file,\$s,APP_PATH.'model.inc.php')") === FALSE) {
		$errors[] = 'model.min.php cache must be published through the atomic plugin cache writer.';
	}
	if(strpos($modelCompact, "empty(\$conf['disabled_plugin'])?'model.min.php':'model.safe.min.php'") === FALSE) {
		$errors[] = 'Safe mode must not overwrite the normal combined model cache.';
	}
	if(strpos($modelCompact, 'file_put_contents($model_min_file,$s)') !== FALSE) {
		$errors[] = 'model.min.php cache must not be exposed through a direct truncating write.';
	}
}

if(!empty($errors)) {
	fwrite(STDERR, implode(PHP_EOL, $errors).PHP_EOL);
	exit(1);
}

function model_cache_safety_assert($condition, $message) {
	if(!$condition) throw new RuntimeException($message);
}

function model_cache_safety_rm_dir($dir) {
	if(is_link($dir) || is_file($dir)) {
		@unlink($dir);
		return;
	}
	if(!is_dir($dir)) return;
	$items = scandir($dir);
	if(!is_array($items)) return;
	foreach($items as $item) {
		if($item === '.' || $item === '..') continue;
		$path = $dir.'/'.$item;
		(is_dir($path) && !is_link($path)) ? model_cache_safety_rm_dir($path) : @unlink($path);
	}
	@rmdir($dir);
}

function model_cache_safety_execute($root, $conf) {
	include $root.'model.inc.php';
}

// These stubs isolate model.inc.php's aggregate-cache state machine while preserving the important
// writer side effect: the first compliant publication attempt creates the sibling lock even when it
// fails. The real writer and reader-lock implementation have their own executable concurrency guard.
function _include($file) {
	return $file;
}

function plugin_cache_write_atomic($file, $s, $source = '') {
	$GLOBALS['model_cache_safety_write_calls'][] = array($file, $source);
	$lock = @fopen($file.'.lock', 'c+b');
	if(!$lock) return FALSE;
	fclose($lock);
	if(!empty($GLOBALS['model_cache_safety_fail_writes'])) {
		$GLOBALS['model_cache_safety_fail_writes']--;
		return FALSE;
	}
	return file_put_contents($file, $s);
}

function plugin_include_cache_reader_hold($file) {
	$GLOBALS['model_cache_safety_reader_holds'][] = $file;
	$lock = @fopen($file.'.lock', 'c+b');
	if(!$lock) return FALSE;
	fclose($lock);
	clearstatcache(TRUE, $file);
	return is_file($file);
}

function plugin_include_cache_reader_release($file) {
	$GLOBALS['model_cache_safety_reader_releases'][] = $file;
	return TRUE;
}

$behavior_error = '';
$app = $root.'tmp/model_cache_safety_'.bin2hex(random_bytes(6)).'/';
try {
	model_cache_safety_assert(mkdir($app.'model', 0777, TRUE), 'failed to create isolated model-cache fixture');
	model_cache_safety_assert(mkdir($app.'tmp', 0777, TRUE), 'failed to create isolated aggregate-cache directory');
	model_cache_safety_assert(
		preg_match_all("~APP_PATH\\.'(model/[^']+)'~", $modelInc, $source_matches) > 0,
		'failed to discover aggregate model source files'
	);
	$model_sources = array_values(array_unique($source_matches[1]));
	foreach($model_sources as $relative) {
		$source = $app.$relative;
		model_cache_safety_assert(
			file_put_contents($source, "<?php\n\$GLOBALS['model_cache_safety_markers'][] = ".var_export($relative, TRUE).";\n?>\n") !== FALSE,
			'failed to write isolated model source: '.$relative
		);
	}

	defined('DEBUG') || define('DEBUG', 0);
	defined('APP_PATH') || define('APP_PATH', $app);

	foreach(array('normal'=>0, 'safe'=>1) as $mode=>$disabled_plugin) {
		$conf = array('tmp_path'=>$app.'tmp/', 'disabled_plugin'=>$disabled_plugin);
		$target = $conf['tmp_path'].($disabled_plugin ? 'model.safe.min.php' : 'model.min.php');

		// A lockless cache with a future mtime is from an unproven legacy generation and must never run.
		$legacy_future = "<?php\n\$GLOBALS['model_cache_safety_markers'][] = 'legacy-future-".$mode."';\n?>\n";
		model_cache_safety_assert(file_put_contents($target, $legacy_future) !== FALSE, 'failed to seed '.$mode.' legacy cache');
		@unlink($target.'.lock');
		model_cache_safety_assert(touch($target, time() + 86400), 'failed to future-date '.$mode.' legacy cache');
		clearstatcache(TRUE, $target);
		$GLOBALS['model_cache_safety_write_calls'] = array();
		$GLOBALS['model_cache_safety_reader_holds'] = array();
		$GLOBALS['model_cache_safety_reader_releases'] = array();
		$GLOBALS['model_cache_safety_fail_writes'] = 0;
		$GLOBALS['model_cache_safety_markers'] = array();
		model_cache_safety_execute($root, $conf);
		model_cache_safety_assert(count($GLOBALS['model_cache_safety_write_calls']) === 1, $mode.' future-dated legacy cache was trusted without regeneration');
		model_cache_safety_assert(count($GLOBALS['model_cache_safety_markers']) === count($model_sources), $mode.' aggregate did not execute the rebuilt source generation');
		model_cache_safety_assert(strpos(file_get_contents($target), 'legacy-future-'.$mode) === FALSE, $mode.' legacy aggregate bytes survived successful regeneration');

		// Identical legacy bytes take the writer's no-rename fast path. A successful validation must
		// still promote their stale mtime, otherwise every later request recompiles the same aggregate.
		$identical_legacy = file_get_contents($target);
		model_cache_safety_assert(is_string($identical_legacy), 'failed to read '.$mode.' rebuilt aggregate');
		@unlink($target.'.lock');
		model_cache_safety_assert(touch($target, time() + 86400), 'failed to future-date '.$mode.' identical legacy cache');
		clearstatcache(TRUE, $target);
		$GLOBALS['model_cache_safety_write_calls'] = array();
		$GLOBALS['model_cache_safety_markers'] = array();
		model_cache_safety_execute($root, $conf);
		model_cache_safety_assert(count($GLOBALS['model_cache_safety_write_calls']) === 1, $mode.' identical legacy cache skipped mandatory validation');
		clearstatcache(TRUE, $target);
		$promoted_mtime = filemtime($target);
		model_cache_safety_assert($promoted_mtime !== FALSE && $promoted_mtime > 2, $mode.' validated identical legacy cache remained permanently stale');
		$GLOBALS['model_cache_safety_write_calls'] = array();
		$GLOBALS['model_cache_safety_markers'] = array();
		model_cache_safety_execute($root, $conf);
		model_cache_safety_assert(count($GLOBALS['model_cache_safety_write_calls']) === 0, $mode.' validated identical legacy cache was recompiled again');

		// If the first compliant write fails after creating the lock, the complete old generation stays
		// available but its stale mtime must survive. The next request therefore retries and replaces it.
		$legacy_failure = "<?php\n\$GLOBALS['model_cache_safety_markers'][] = 'legacy-failure-".$mode."';\n?>\n";
		model_cache_safety_assert(file_put_contents($target, $legacy_failure) !== FALSE, 'failed to seed '.$mode.' failure cache');
		@unlink($target.'.lock');
		model_cache_safety_assert(touch($target, time() + 86400), 'failed to future-date '.$mode.' failure cache');
		clearstatcache(TRUE, $target);
		$GLOBALS['model_cache_safety_write_calls'] = array();
		$GLOBALS['model_cache_safety_fail_writes'] = 1;
		$GLOBALS['model_cache_safety_markers'] = array();
		$failed = FALSE;
		try {
			model_cache_safety_execute($root, $conf);
		} catch(RuntimeException $e) {
			$failed = TRUE;
		}
		model_cache_safety_assert($failed, $mode.' failed regeneration unexpectedly executed the legacy aggregate');
		model_cache_safety_assert(file_get_contents($target) === $legacy_failure, $mode.' failed regeneration did not preserve the complete previous aggregate');
		model_cache_safety_assert(is_file($target.'.lock'), $mode.' failed compliant publication did not establish the sibling lock');
		clearstatcache(TRUE, $target);
		$stale_mtime = filemtime($target);
		model_cache_safety_assert($stale_mtime !== FALSE && $stale_mtime <= 2, $mode.' failed regeneration promoted future-dated legacy bytes to a trusted locked cache');

		$GLOBALS['model_cache_safety_markers'] = array();
		model_cache_safety_execute($root, $conf);
		model_cache_safety_assert(count($GLOBALS['model_cache_safety_write_calls']) === 2, $mode.' locked stale aggregate was not retried after the first write failure');
		model_cache_safety_assert(count($GLOBALS['model_cache_safety_markers']) === count($model_sources), $mode.' retry did not execute the rebuilt source generation');
		model_cache_safety_assert(strpos(file_get_contents($target), 'legacy-failure-'.$mode) === FALSE, $mode.' retry left legacy aggregate bytes published');
	}
} catch(Throwable $e) {
	$behavior_error = $e->getMessage();
} finally {
	model_cache_safety_rm_dir($app);
}

if($behavior_error !== '') {
	fwrite(STDERR, $behavior_error.PHP_EOL);
	exit(1);
}

echo "OK: model cache safety checks passed\n";
exit(0);

?>
