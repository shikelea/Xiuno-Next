<?php

!defined('DEBUG') AND exit('Forbidden');

// 可以合并成一个文件，加快速度
// merge to one file.

// hook model_inc_start.php

$include_model_files = array (
	APP_PATH.'model/kv.func.php',
	APP_PATH.'model/queue.func.php',
	APP_PATH.'model/group.func.php',
	APP_PATH.'model/user.func.php',
	APP_PATH.'model/forum.func.php',
	APP_PATH.'model/forum_access.func.php',
	APP_PATH.'model/thread.func.php',
	APP_PATH.'model/thread_top.func.php',
	APP_PATH.'model/post.func.php',
	APP_PATH.'model/attach.func.php',
	APP_PATH.'model/check.func.php',
	APP_PATH.'model/mythread.func.php',
	APP_PATH.'model/runtime.func.php',
	APP_PATH.'model/table_day.func.php',
	APP_PATH.'model/cron.func.php',
	APP_PATH.'model/form.func.php',
	APP_PATH.'model/locale.func.php',
	APP_PATH.'model/misc.func.php',
	APP_PATH.'model/session.func.php',
	APP_PATH.'model/diagnostic.func.php',
	
	// hook model_inc_file.php
	
);

// hook model_inc_include_before.php

if(DEBUG) {
	foreach ($include_model_files as $model_files) {
		include _include($model_files);
	}
} else {
	
	$model_min_file = $conf['tmp_path'].(empty($conf['disabled_plugin']) ? 'model.min.php' : 'model.safe.min.php');
	$isfile = is_file($model_min_file);
	$legacy_unlocked_cache = $isfile && !is_file($model_min_file.'.lock');
	if($legacy_unlocked_cache) {
		// Aggregates published before the stable reader/writer lock protocol have no generation proof.
		// Persist the stale marker before a writer or reader creates the sibling lock, so a failed first
		// rebuild cannot promote future-dated legacy bytes to a trusted cache on the next request.
		if(!@touch($model_min_file, 1)) throw new RuntimeException('Failed to mark legacy model cache stale: '.$model_min_file);
		clearstatcache(TRUE, $model_min_file);
	}
	if($isfile) {
		$model_min_mtime = filemtime($model_min_file);
		if($legacy_unlocked_cache || filemtime(__FILE__) > $model_min_mtime) {
			$isfile = FALSE;
		}
		if($isfile) {
			foreach($include_model_files as $model_files) {
				$model_file = _include($model_files);
				if(is_file($model_file) && filemtime($model_file) > $model_min_mtime) {
					$isfile = FALSE;
					break;
				}
			}
		}
	}
	$model_min_rebuilt = FALSE;
	if(!$isfile) {
		$s = '';
		foreach($include_model_files as $model_files) {
			
			// 压缩后不利于调试，有时候碰到未结束的 php 标签，会暴 500 错误
			//$s .= php_strip_whitespace(_include($model_files));

			$t = file_get_contents(_include($model_files));
			$t = trim($t);
			$t = ltrim($t, '<?php');
			$t = rtrim($t, '?>');
			$s .= "<?php\r\n".$t."\r\n?>";

		}
		$r = plugin_cache_write_atomic($model_min_file, $s, APP_PATH.'model.inc.php');
		if($r === FALSE) throw new RuntimeException('Failed to write model cache: '.$model_min_file);
		$model_min_rebuilt = $r !== FALSE;
		unset($s);
	}
	if(plugin_include_cache_reader_hold($model_min_file) === FALSE) {
		throw new RuntimeException('Failed to lease model cache for include: '.$model_min_file);
	}
	try {
		// The atomic writer intentionally keeps identical bytes in place. Promote a successfully
		// validated legacy generation while its shared lease prevents cleanup/replacement races.
		if($legacy_unlocked_cache && $model_min_rebuilt) {
			if(!@touch($model_min_file)) throw new RuntimeException('Failed to promote rebuilt legacy model cache: '.$model_min_file);
			clearstatcache(TRUE, $model_min_file);
		}
		include $model_min_file;
	} finally {
		plugin_include_cache_reader_release($model_min_file);
	}
}

// hook model_inc_end.php









/*
function xn_php_strip_whitespace($file) {
	$s = php_strip_whitespace($file);
	if(substr($s, 0, 5) == '<?php') {
		$s = substr($s, 5);
	}
	if(substr($s, -2) == '?>') {
		$s = substr($s, 0, -2);
	}
	return $s;
}*/

?>
