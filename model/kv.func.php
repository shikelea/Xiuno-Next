<?php

// 如果环境支持，可以直接改为 redis get() set() 持久存储相关 API，提高速度。


// 无缓存
function kv__get($k, $primary = FALSE) {
	$arr = $primary
		? db_find_one_master('kv', array('k'=>$k))
		: db_find_one('kv', array('k'=>$k));
	if($arr === FALSE) return FALSE;
	if(!$arr) return NULL;
	$value = xn_json_decode($arr['v']);
	return json_last_error() === JSON_ERROR_NONE ? $value : FALSE;
}
function kv_get($k) {
	static $static = array();
	strlen($k) > 32 AND $k = md5($k);
	if(!isset($static[$k])) {
		$static[$k] = kv__get($k);
	}
	return $static[$k];
}
function kv_json_encode($v) {
	try {
		$json = xn_json_encode($v);
	} catch(Throwable $e) {
		return FALSE;
	}
	if(!is_string($json) || $json === '') return FALSE;
	json_decode($json, TRUE);
	return json_last_error() === JSON_ERROR_NONE ? $json : FALSE;
}
function kv_set($k, $v, $life = 0) {
	strlen($k) > 32 AND $k = md5($k);
	$json = kv_json_encode($v);
	if($json === FALSE) return FALSE;
	$arr = array(
		'k'=>$k,
		'v'=>$json,
	);
	$r = db_replace('kv', $arr);
	return $r;
}
function kv_delete($k) {
	strlen($k) > 32 AND $k = md5($k);
	$r = db_delete('kv', array('k'=>$k));
	return $r;
}



// --------------------> kv + cache
function kv_cache_get($k) {
	$r = cache_get($k);
	if($r === NULL) {
		$r = kv_get($k);
	}
	return $r;
}
function kv_cache_set($k, $v, $life = 0) {
	$r = kv_set($k, $v);
	if($r === FALSE) return FALSE;
	$cache_r = cache_set($k, $v, $life);
	$cache_r === FALSE AND cache_delete($k);
	return $r;
}
function kv_cache_delete($k) {
	cache_delete($k);
	$r = kv_delete($k);
	return $r;
}



// ------------> kv + cache + setting
$g_setting = FALSE;
function setting_get_raw($k) {
	global $g_setting;
	function_exists('plugin_setting_capture_key') AND plugin_setting_capture_key($k, 'read');
	$g_setting === FALSE AND $g_setting = kv_cache_get('setting', $g_setting);
	empty($g_setting) AND $g_setting = array();
	return array_value($g_setting, $k, NULL);
}
function setting_get($k) {
	function_exists('plugin_setting_capture_key') AND plugin_setting_capture_key($k, 'read');
	$value = setting_get_raw($k);
	return function_exists('plugin_setting_apply_registered_defaults') ? plugin_setting_apply_registered_defaults($k, $value) : $value;
}
// 全站的设置，全局变量 $g_setting = array();
function setting_write_lock_start() {
	global $conf;
	$tmp_path = is_array($conf) && isset($conf['tmp_path']) ? $conf['tmp_path'] : '';
	if(!is_string($tmp_path) || $tmp_path === '' || !is_dir($tmp_path)) return FALSE;
	$lockfile = rtrim($tmp_path, '/\\').DIRECTORY_SEPARATOR.'lock_setting_write.lock';
	$fp = @fopen($lockfile, 'c');
	if(!$fp) return FALSE;
	if(!flock($fp, LOCK_EX)) {
		fclose($fp);
		return FALSE;
	}
	return $fp;
}

function setting_write_lock_end($fp) {
	if(!is_resource($fp)) return FALSE;
	$r = flock($fp, LOCK_UN);
	fclose($fp);
	return $r;
}

function setting_publish_current($setting) {
	global $g_setting;
	$cache_r = cache_set('setting', $setting);
	$cache_r === FALSE AND cache_delete('setting');
	$g_setting = $setting;
	return TRUE;
}

// `setting` is one KV row. Whole-row mutations let compatibility metadata and its related value
// commit in the same MyISAM-safe db_replace() instead of pretending two independent KV writes form
// a transaction. The callback receives the latest primary value under the cross-process lock.
function setting_row_mutate_locked($mutator) {
	if(!is_callable($mutator)) return FALSE;
	$lock = setting_write_lock_start();
	if($lock === FALSE) return FALSE;
	try {
		$setting = kv__get('setting', TRUE);
		if($setting === FALSE) return FALSE;
		!is_array($setting) AND $setting = array();
		$mutation = call_user_func($mutator, $setting);
		if(!is_array($mutation) || !array_key_exists('write', $mutation)) return FALSE;
		if(empty($mutation['write'])) return setting_publish_current($setting);
		if(!isset($mutation['value']) || !is_array($mutation['value'])) return FALSE;
		$setting = $mutation['value'];
		$r = kv_set('setting', $setting);
		if($r === FALSE) return FALSE;
		setting_publish_current($setting);
		return $r;
	} finally {
		setting_write_lock_end($lock);
	}
}

function setting_row_update_atomic($mutator) {
	if(!is_callable($mutator)) return FALSE;
	return setting_row_mutate_locked(function($setting) use ($mutator) {
		$value = call_user_func($mutator, $setting);
		if(!is_array($value)) return FALSE;
		return array('write'=>$value !== $setting, 'value'=>$value);
	});
}

// Transform one key while preserving the same whole-row primary-read and lock contract.
function setting_mutate_locked($k, $mutator) {
	if(!is_callable($mutator)) return FALSE;
	return setting_row_mutate_locked(function($setting) use ($k, $mutator) {
		$exists = array_key_exists($k, $setting);
		$mutation = call_user_func($mutator, $exists ? $setting[$k] : NULL, $exists);
		if(!is_array($mutation) || !array_key_exists('write', $mutation)) return FALSE;
		if(empty($mutation['write'])) return array('write'=>FALSE, 'value'=>$setting);
		$delete = !empty($mutation['delete']);
		if($delete) {
			if(array_key_exists($k, $setting)) unset($setting[$k]);
		} else {
			if(!array_key_exists('value', $mutation)) return FALSE;
			$setting[$k] = $mutation['value'];
		}
		return array('write'=>TRUE, 'value'=>$setting);
	});
}

function setting_update($k, $v = NULL, $delete = FALSE) {
	return setting_mutate_locked($k, function($current, $exists) use ($v, $delete) {
		return array('write'=>TRUE, 'delete'=>$delete, 'value'=>$v);
	});
}

// Atomically transform one setting key from the latest database row. An unchanged value refreshes
// request/cache state without performing a redundant database write.
function setting_update_atomic($k, $mutator) {
	if(!is_callable($mutator)) return FALSE;
	return setting_mutate_locked($k, function($current, $exists) use ($mutator) {
		$value = call_user_func($mutator, $current, $exists);
		return array('write'=>!$exists || $value !== $current, 'delete'=>FALSE, 'value'=>$value);
	});
}

function setting_set($k, $v) {
	$r = setting_update($k, $v, FALSE);
	if($r !== FALSE && function_exists('plugin_setting_capture_key')) plugin_setting_capture_key($k, 'write');
	return $r;
}
function setting_delete($k) {
	return setting_update($k, NULL, TRUE) !== FALSE;
}

?>
