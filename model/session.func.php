<?php 

/*
	php 默认的 session 采用文件存储，并且使用 flock() 文件锁避免并发访问不出问题（实际上还是无法解决业务层的并发读后再写入）
	自定义的 session 采用数据表来存储，同样无法解决业务层并发请求问题。
	xiuno.js $.each_sync() 串行化并发请求，可以避免客户端并发访问导致的 session 写入问题。
*/

$sid = '';
$g_session = array();	
$g_session_invalid = FALSE; // 0: 有效， 1：无效
$g_session_new_failed = FALSE;
$g_session_revoked = FALSE;
$g_session_main_data = ''; // 主 session 行实际保存的 inline data 或 immutable auxiliary pointer
$g_session_data_sid = ''; // 当前 bigdata 快照对应的 session_data 主键
$g_session_data_last_date = 0; // 当前 auxiliary 代的生命周期时间
$g_online_member_snapshot = NULL;

// 可以指定独立的 session 服务器，在系统压力巨大的时候可以考虑优化
//$g_sess_db = $db;

// 如果是管理员, sid, 与 ip 绑定，一旦 IP 发生变化，则需要重新登录。管理员采用 token (绑定IP) 双重验证，避免 sid 被中间窃取。

function sess_open($save_path, $session_name) { 
	//echo "sess_open($save_path,$session_name) \r\n";
	return true;
}

// 关闭句柄，清理资源，这里 $sid 已经为空，
function sess_close() {
	return true;
}

function sess_data_pointer_valid($pointer) {
	return is_string($pointer) && preg_match('/^[a-f0-9]{32}$/D', $pointer) === 1;
}

function sess_data_pointer_new() {
	try {
		return bin2hex(random_bytes(16));
	} catch(Throwable $e) {
		return md5(uniqid('', TRUE).mt_rand());
	}
}

// Legacy bigdata rows kept an empty main-row data field and keyed session_data by the Session ID.
// New rows use the same field as an immutable auxiliary pointer, avoiding cross-table rollback.
function sess_data_pointer_from_main($sid, $main_data) {
	if($main_data === '') return $sid;
	return sess_data_pointer_valid($main_data) ? $main_data : FALSE;
}

// 如果 cookie 中没有 bbs_sid, php 会自动生成 sid，作为参数
function sess_read($sid) { 
	global $g_session, $g_session_revoked, $g_session_new_failed, $g_session_main_data, $g_session_data_sid, $g_session_data_last_date, $longip, $time;
	$g_session_main_data = '';
	$g_session_data_sid = '';
	$g_session_data_last_date = 0;
	//echo "sess_read() sid: $sid <br>\r\n";
	if(empty($sid)) {
		// 查找刚才是不是已经插入一条了？  如果相隔时间特别短，并且 data 为空，则删除。
		// 测试是否支持 cookie，如果不支持 cookie，则不生成 sid
		$sid = session_id();
		sess_new($sid);
		return isset($g_session['data']) ? $g_session['data'] : '';
	}
	$arr = sess_find_one_primary($sid);
	if($arr === FALSE) {
		$g_session_new_failed = TRUE;
		return '';
	}
	if(empty($arr)) {
		sess_new($sid);
		return isset($g_session['data']) ? $g_session['data'] : '';
	}
	if($arr['bigdata'] == 2) {
		$g_session_revoked = TRUE;
		return '';
	}
	$g_session_main_data = isset($arr['data']) ? (string)$arr['data'] : '';
	if($arr['bigdata'] == 1) {
		$g_session_data_sid = sess_data_pointer_from_main($sid, $g_session_main_data);
		if($g_session_data_sid === FALSE) {
			$g_session_new_failed = TRUE;
			$g_session = array();
			return '';
		}
		$arr2 = sess_find_one_primary($g_session_data_sid, 'session_data');
		if($arr2 === FALSE || !is_array($arr2) || !array_key_exists('data', $arr2)) {
			$g_session_new_failed = TRUE;
			$g_session = array();
			return '';
		}
		$g_session_data_last_date = isset($arr2['last_date']) ? intval($arr2['last_date']) : 0;
		$arr['data'] = isset($arr2['data']) ? $arr2['data'] : '';
	}
	$g_session = $arr;
	// 在 php 5.6.29 版本，需要返回 session_decode()
	//return $arr ? session_decode($arr['data']) : '';
	return $arr ? $arr['data'] : '';
}

function sess_new($sid) {
	global $time, $longip, $conf, $g_session, $g_session_invalid, $g_session_new_failed, $g_session_revoked, $g_session_main_data, $g_session_data_sid, $g_session_data_last_date;
	
	$agent = _SERVER('HTTP_USER_AGENT');
	
	// 干掉同 ip 的 sid，仅仅在遭受攻击的时候
	//db_delete('session', array('ip'=>$longip));
	
	$cookie_test = _COOKIE('cookie_test');
	if($cookie_test) {
		$cookie_test_decode = xn_decrypt($cookie_test, $conf['auth_key']);
		$g_session_invalid = ($cookie_test_decode != md5($agent.$longip));
		xn_setcookie('cookie_test', '', $time - 86400, '/');
	} else {
		$cookie_test = xn_encrypt(md5($agent.$longip), $conf['auth_key']);
		xn_setcookie('cookie_test', $cookie_test, $time + 86400, '/');
		$g_session_invalid = FALSE;
	}
	
	// 可能会暴涨
	$url = _SERVER('REQUEST_URI_NO_PATH');
	
	$arr = array(
		'sid'=>$sid,
		'uid'=>0,
		'fid'=>0,
		'url'=>$url,
		'last_date'=>$time,
		'data'=> '',
		'ip'=> $longip,
		'useragent'=> $agent,
		'bigdata'=> 0,
	);
	if(db_insert('session', $arr) === FALSE) {
		$existing = sess_find_one_primary($sid);
		if($existing === FALSE) {
			$g_session_new_failed = TRUE;
			return FALSE;
		}
		if(!empty($existing) && intval($existing['bigdata']) <= 1) {
			$g_session_main_data = isset($existing['data']) ? (string)$existing['data'] : '';
			$g_session_data_sid = '';
			$g_session_data_last_date = 0;
			if($existing['bigdata'] == 1) {
				$g_session_data_sid = sess_data_pointer_from_main($sid, $g_session_main_data);
				if($g_session_data_sid === FALSE) {
					$g_session_new_failed = TRUE;
					return FALSE;
				}
				$arr2 = sess_find_one_primary($g_session_data_sid, 'session_data');
				if($arr2 === FALSE || !is_array($arr2) || !array_key_exists('data', $arr2)) {
					$g_session_new_failed = TRUE;
					return FALSE;
				}
				$g_session_data_last_date = isset($arr2['last_date']) ? intval($arr2['last_date']) : 0;
				$existing['data'] = isset($arr2['data']) ? $arr2['data'] : '';
			}
			$g_session_new_failed = FALSE;
			$g_session = $existing;
			return TRUE;
		}
		if(!empty($existing) && intval($existing['bigdata']) == 2) $g_session_revoked = TRUE;
		$g_session_new_failed = TRUE;
		return FALSE;
	}
	$g_session_new_failed = FALSE;
	$g_session_main_data = '';
	$g_session_data_sid = '';
	$g_session_data_last_date = 0;
	$g_session = $arr;
	return TRUE;
}

// 重新启动 session，降低并发写入数据的问题，这回抛弃前面的 _SESSION 数据
function sess_restart() {
	global $sid;
	$data = sess_read($sid);
	session_decode($data); // 直接存入了 $_SESSION
}

// 将当前的 _SESSION 变量保存
function sess_save() {
	global $sid;
	sess_write($sid, TRUE);
}

// 模拟加锁，如果发现写入的时候数据已经发生改变，则读取后，合并数据，重新写入（合并总比删除安全一点）。
function sess_write($sid, $data) {
	global $g_session, $g_session_main_data, $g_session_data_sid, $g_session_data_last_date, $time, $longip, $g_session_invalid, $conf;
	
	//echo "sess_write($sid, $data)";
	//if($g_session_invalid) return TRUE;
	
	$uid = _SESSION('uid');
	$fid = _SESSION('fid');
	unset($_SESSION['uid']);
	unset($_SESSION['fid']);
	
	if($data) {
		//$arr = session_decode($data);
		//unset($_SESSION['uid']);
		//unset($_SESSION['fid']);
		$data = session_encode();
	}
	
	function_exists('chdir') AND chdir(APP_PATH);
	
	$url = _SERVER('REQUEST_URI_NO_PATH');
	$agent = _SERVER('HTTP_USER_AGENT');
	$arr = array(
		'uid'=>$uid,
		'fid'=>$fid,
		'url'=>$url,
		'last_date'=>$time,
		'data'=> $data,
		'ip'=> $longip,
		'useragent'=> $agent,
		'bigdata'=> 0,
	);
	
	// 开启 session 延迟更新，减轻压力，会导致不重要的数据(useragent,url)显示有些延迟，单位为秒。
	$session_delay_update_on = !empty($conf['session_delay_update']) && $time - $g_session['last_date'] < $conf['session_delay_update'];
	if($session_delay_update_on) {
		unset($arr['fid']);
		unset($arr['url']);
		unset($arr['last_date']);
	}
	
	// 判断数据是否超长
	$len = strlen($data);
	$session_condition = array(
		'sid'=>$sid,
		'last_date'=>array('>'=>0),
		'bigdata'=>intval($g_session['bigdata']),
		'data'=>(string)$g_session_main_data,
	);
	if($len <= 255) {
		$update = array_diff_value($arr, $g_session);
		// Do not recreate or overwrite a revoked/deleted SID after concurrent rotation.
		$session_write_result = $update ? db_update('session', $session_condition, $update) : TRUE;
		if($session_write_result === FALSE || ($update && intval($session_write_result) < 1)) return FALSE;
		if(intval($session_write_result) > 0 && online_member_state_changed($g_session, $arr)) online_member_snapshot_invalidate();
		$g_session = array_merge($g_session, $arr);
		$g_session_main_data = (string)$arr['data'];
		$g_session_data_sid = '';
		$g_session_data_last_date = 0;
	} else {
		$large_data_unchanged = intval($g_session['bigdata']) === 1
			&& sess_data_pointer_valid($g_session_main_data)
			&& $g_session_data_sid === $g_session_main_data
			&& is_string($g_session['data'])
			&& hash_equals($g_session['data'], $data);
		if($large_data_unchanged && !$session_delay_update_on && intval($g_session_data_last_date) !== intval($time)) {
			// The auxiliary payload is immutable, but its lifetime follows the main Session heartbeat.
			// Refresh it before the main-row CAS so GC cannot remove a still-referenced unchanged
			// generation. A concurrent winner may leave this old generation alive longer; that is safe.
			$refresh_result = db_update('session_data', array('sid'=>$g_session_data_sid), array('last_date'=>$time));
			if($refresh_result === FALSE) return FALSE;
			if(intval($refresh_result) > 0) {
				$g_session_data_last_date = intval($time);
			} else {
				// The observed auxiliary disappeared between read and refresh. Re-publish the complete
				// in-memory payload under a new pointer instead of leaving a dangling main row.
				$large_data_unchanged = FALSE;
			}
		}
		if($large_data_unchanged) {
			$arr['data'] = $g_session_main_data;
			$arr['bigdata'] = 1;
			$main_snapshot = $g_session;
			$main_snapshot['data'] = $g_session_main_data;
			$update = array_diff_value($arr, $main_snapshot);
			$session_write_result = $update ? db_update('session', $session_condition, $update) : TRUE;
			if($session_write_result === FALSE || ($update && intval($session_write_result) < 1)) return FALSE;
			if(intval($session_write_result) > 0 && online_member_state_changed($g_session, $arr)) online_member_snapshot_invalidate();
			$g_session = array_merge($g_session, $arr);
			$g_session['data'] = $data;
			return TRUE;
		}

		$new_data_sid = '';
		for($attempt = 0; $attempt < 3; $attempt++) {
			$candidate = sess_data_pointer_new();
			$session_data_result = db_insert('session_data', array('sid'=>$candidate, 'data'=>$data, 'last_date'=>$time));
			if($session_data_result !== FALSE) {
				$new_data_sid = $candidate;
				break;
			}
		}
		if($new_data_sid === '') return FALSE;

		$arr['data'] = $new_data_sid;
		$arr['bigdata'] = 1;
		$update = array_diff_value($arr, $g_session);
		$session_write_result = $update ? db_update('session', $session_condition, $update) : TRUE;
		if($session_write_result === FALSE) {
			// A transport failure can be reported after the server committed the row. Re-read both
			// sides on the primary before deciding whether this unique candidate is unpublished.
			$published_main = sess_find_one_primary($sid);
			if($published_main === FALSE) {
				function_exists('xn_log') AND xn_log('Unable to confirm Session pointer publication; retained auxiliary generation '.$new_data_sid, 'session_error');
				return FALSE;
			}
			$points_to_candidate = !empty($published_main)
				&& intval($published_main['bigdata']) === 1
				&& isset($published_main['data'])
				&& hash_equals($new_data_sid, (string)$published_main['data']);
			if($points_to_candidate) {
				$published_data = sess_find_one_primary($new_data_sid, 'session_data');
				if($published_data === FALSE || !is_array($published_data)
					|| !array_key_exists('data', $published_data)
					|| !is_string($published_data['data'])
					|| !hash_equals($data, $published_data['data'])) {
					function_exists('xn_log') AND xn_log('Session pointer publication is incomplete; retained auxiliary generation '.$new_data_sid, 'session_error');
					return FALSE;
				}
				$session_write_result = 1;
			} else {
				if(!sess_data_delete_unreferenced($new_data_sid) && function_exists('xn_log')) {
					xn_log('Unable to remove unpublished Session auxiliary generation '.$new_data_sid, 'session_error');
				}
				return FALSE;
			}
		} elseif($update && intval($session_write_result) < 1) {
			if(!sess_data_delete_unreferenced($new_data_sid) && function_exists('xn_log')) {
				xn_log('Unable to remove losing Session auxiliary generation '.$new_data_sid, 'session_error');
			}
			return FALSE;
		}
		if(intval($session_write_result) > 0 && online_member_state_changed($g_session, $arr)) online_member_snapshot_invalidate();
		$g_session = array_merge($g_session, $arr);
		$g_session['data'] = $data;
		$g_session_main_data = $new_data_sid;
		$g_session_data_sid = $new_data_sid;
		$g_session_data_last_date = intval($time);
	}
	return TRUE;
}

function sess_find_one_primary($sid, $table = 'session') {
	return sess_find_one_condition_primary($table, array('sid'=>$sid));
}

function sess_find_one_condition_primary($table, $condition) {
	global $_SERVER;
	$db = isset($_SERVER['db']) ? $_SERVER['db'] : NULL;
	if($db && method_exists($db, 'find_one_master') && function_exists('db_find_one_master')) {
		// FALSE now means a real primary-read failure. Falling through to a replica here can
		// resurrect the pre-tombstone row and must remain distinguishable from an unsupported API.
		return db_find_one_master($table, $condition, array(), array(), $db);
	}
	return db_find_one($table, $condition);
}

function sess_data_atomic_cleanup_context() {
	global $_SERVER;
	$db = isset($_SERVER['db']) ? $_SERVER['db'] : NULL;
	$tablepre = is_object($db) && isset($db->tablepre) ? (string)$db->tablepre : NULL;
	if(!is_object($db) || !method_exists($db, 'exec') || !is_callable(array($db, 'exec')) || $tablepre === NULL || preg_match('/^[A-Za-z0-9_]{0,32}$/D', $tablepre) !== 1 || !function_exists('db_exec')) {
		function_exists('xn_log') AND xn_log('Unable to prove an atomic Session auxiliary cleanup for the configured database driver.', 'session_error');
		return FALSE;
	}
	return array($db, $tablepre);
}

function sess_data_delete_unreferenced_where($where) {
	$context = sess_data_atomic_cleanup_context();
	if($context === FALSE) return FALSE;
	list($db, $tablepre) = $context;
	$session_table = '`'.$tablepre.'session`';
	$data_table = '`'.$tablepre.'session_data`';
	$sql = 'DELETE FROM '.$data_table.' WHERE '.$where.' AND NOT EXISTS ('
		.'SELECT 1 FROM '.$session_table.' AS `session_ref` '
		.'WHERE `session_ref`.`bigdata`=1 AND ('
		.'(`session_ref`.`data`=\'\' AND `session_ref`.`sid`='.$data_table.'.`sid`) OR '
		.'(`session_ref`.`data`<>\'\' AND `session_ref`.`data`='.$data_table.'.`sid`)'
		.'))';
	try {
		return db_exec($sql, $db) !== FALSE;
	} catch(Throwable $e) {
		function_exists('xn_log') AND xn_log('Atomic Session auxiliary cleanup failed; retained candidate/orphan data.', 'session_error');
		return FALSE;
	}
}

function sess_data_delete_unreferenced($data_sid) {
	if(!sess_data_pointer_valid($data_sid)) return FALSE;
	return sess_data_delete_unreferenced_where("`sid`='".$data_sid."'");
}

function sess_data_gc_orphans($expiry) {
	return sess_data_delete_unreferenced_where('`last_date` < '.intval($expiry));
}

function sess_tombstone($sid, &$data_sid = NULL) {
	global $time;
	$before = sess_find_one_primary($sid);
	if($before === FALSE) return FALSE;
	$data_sid = !empty($before) && intval($before['bigdata']) == 1
		? sess_data_pointer_from_main($sid, isset($before['data']) ? (string)$before['data'] : '')
		: NULL;
	$data_sid === FALSE AND $data_sid = NULL;
	$tombstone = array('uid'=>0, 'fid'=>0, 'data'=>'', 'bigdata'=>2, 'last_date'=>$time);
	if(db_update('session', array('sid'=>$sid), $tombstone) === FALSE) return FALSE;
	try {
		$arr = sess_find_one_primary($sid);
		if($arr === FALSE) return FALSE;
		if(empty($arr)) {
			$arr = array('sid'=>$sid, 'url'=>'', 'ip'=>0, 'useragent'=>'') + $tombstone;
			if(db_insert('session', $arr) === FALSE) {
				$arr = sess_find_one_primary($sid);
				if($arr === FALSE) return FALSE;
			}
		}
		if(!empty($arr) && (intval($arr['bigdata']) != 2 || intval($arr['uid']) != 0 || intval($arr['fid']) != 0)) {
			if(db_update('session', array('sid'=>$sid), $tombstone) === FALSE) return FALSE;
			$arr = sess_find_one_primary($sid);
			if($arr === FALSE) return FALSE;
		}
		if(empty($arr) || intval($arr['bigdata']) != 2 || intval($arr['uid']) != 0 || intval($arr['fid']) != 0) return FALSE;
		return TRUE;
	} finally {
		// The pre-read is not a lock. Another request may authenticate this SID and publish a
		// member snapshot before our write. Any write-attempt that reached persistent state must
		// invalidate, even when a later verification read fails and the function returns FALSE.
		online_member_snapshot_invalidate();
	}
}

function sess_destroy($sid) { 
	// Keep every explicitly destroyed ID unavailable long enough for stale requests to finish.
	$data_sid = NULL;
	if(!sess_tombstone($sid, $data_sid)) return FALSE;
	return $data_sid === NULL || db_delete('session_data', array('sid'=>$data_sid)) !== FALSE;
}

function sess_gc($maxlifetime) {
	global $time, $_SERVER;
	// echo "sess_gc($maxlifetime) \r\n";
	$expiry = $time - $maxlifetime;
	// Prove the final atomic cleanup before changing either Session table. Unsupported
	// drivers retain all rows and report failure instead of leaving a partial GC pass.
	if(sess_data_atomic_cleanup_context() === FALSE) return FALSE;
	$delete_attempted = FALSE;
	$gc_result = FALSE;
	try {
		$delete_attempted = TRUE;
		$active_delete_result = db_delete('session', array('last_date'=>array('<'=>$expiry), 'bigdata'=>array('<='=>1)));
		if($active_delete_result !== FALSE) {
			$tombstone_delete_result = db_delete('session', array('last_date'=>array('<='=>$time - 86400), 'bigdata'=>2));
			if($tombstone_delete_result !== FALSE) $gc_result = sess_data_gc_orphans($expiry);
		}
	} catch(Throwable $e) {
		function_exists('xn_log') AND xn_log('Session garbage collection failed after cleanup started.', 'session_error');
		$gc_result = FALSE;
	} finally {
		// A failed delete may still have reached persistent state. Once GC starts writing,
		// always advance the population generation while preserving the original result.
		if($delete_attempted) {
			try {
				$invalidation_result = online_member_snapshot_invalidate();
				if($invalidation_result === FALSE && !empty($_SERVER['cache'])) {
					function_exists('xn_log') AND xn_log('Configured cache rejected the online-member generation update after Session garbage collection.', 'session_error');
					$gc_result = FALSE;
				}
			} catch(Throwable $e) {
				function_exists('xn_log') AND xn_log('Unable to invalidate the online-member generation after Session garbage collection.', 'session_error');
				$gc_result = FALSE;
			}
		}
	}
	return $gc_result;
}

function sess_start() {
	global $conf, $sid, $g_session, $g_session_new_failed, $g_session_revoked, $g_session_main_data, $g_session_data_sid, $g_session_data_last_date, $time;
	$g_session_new_failed = FALSE;
	$g_session_revoked = FALSE;
	ini_set('session.name', 'bbs_sid');
	
	ini_set('session.use_cookies', 'On');
	ini_set('session.use_only_cookies', 'On');
	ini_set('session.cookie_domain', '');
	ini_set('session.cookie_path', '/');
	ini_set('session.cookie_secure', xn_cookie_secure() ? 'On' : 'Off'); // HTTPS 下自动启用 Secure 标志
	ini_set('session.cookie_lifetime', 86400);
	ini_set('session.cookie_httponly', 'On'); // 打开后 js 获取不到 HTTP 设置的 cookie, 有效防止 XSS，这个对于安全很重要，除非有 BUG，否则不要关闭。
	ini_set('session.cookie_samesite', 'Lax');
	
	ini_set('session.gc_maxlifetime', $conf['online_hold_time']);	// 活动时间 $conf['online_hold_time']
	ini_set('session.gc_probability', 1); 	// 垃圾回收概率 = gc_probability/gc_divisor
	ini_set('session.gc_divisor', 500); 	// 垃圾回收时间 5 秒，在线人数 * 10 
	
	@session_set_save_handler('sess_open', 'sess_close', 'sess_read', 'sess_write', 'sess_destroy', 'sess_gc'); 
	
	// register_shutdown_function 会丢失当前目录，需要 chdir(APP_PATH)
	
	// 这个比须有，否则 ZEND 会提前释放 $db 资源
	register_shutdown_function('session_write_close');

	$script_name = str_replace('\\', '/', _SERVER('SCRIPT_NAME'));
	$admin_pos = strpos($script_name, '/admin/');
	if($admin_pos !== FALSE) {
		$admin_cookie_path = substr($script_name, 0, $admin_pos + 6);
		xn_setcookie('bbs_sid', '', $time - 86400, $admin_cookie_path);
		xn_setcookie('cookie_test', '', $time - 86400, $admin_cookie_path);
	}
	
	if(!session_start() || $g_session_new_failed || $g_session_revoked) {
		@session_abort();
		$g_session_revoked AND xn_setcookie('bbs_sid', '', $time - 86400, '/');
		$_SESSION = array();
		$g_session = array();
		$g_session_main_data = '';
		$g_session_data_sid = '';
		$g_session_data_last_date = 0;
		$sid = '';
		return FALSE;
	}
	
	$sid = session_id();
	// Generate the CSRF token while the custom session is still writable.
	// Themes that replace the core header receive the visible meta tag later
	// through the output compatibility injector.
	function_exists('csrf_token') AND csrf_token();
	
	//$_SESSION['uid'] = $g_session['uid'];
	//$_SESSION['fid'] = $g_session['fid'];
	
	//echo "sess_start() sid: $sid <br>\r\n";
	//print_r(db_find('session'));
	return $sid;
}

function sess_regenerate_id() {
	global $sid, $g_session, $g_session_new_failed, $g_session_main_data, $g_session_data_sid, $g_session_data_last_date;

	if(session_status() !== PHP_SESSION_ACTIVE) return FALSE;
	$g_session_new_failed = FALSE;
	$rotated = @session_regenerate_id(TRUE);
	if(!$rotated || $g_session_new_failed) {
		@session_abort();
		$_SESSION = array();
		$g_session = array();
		$g_session_main_data = '';
		$g_session_data_sid = '';
		$g_session_data_last_date = 0;
		$sid = '';
		return FALSE;
	}

	$sid = session_id();
	return !empty($sid);
}

function online_count() {
	$snapshot = online_member_snapshot();
	return isset($snapshot['count']) ? max(0, intval($snapshot['count'])) : 0;
}

function online_find_cache() {
	$snapshot = online_member_snapshot();
	return isset($snapshot['rows']) && is_array($snapshot['rows']) ? $snapshot['rows'] : array();
}

function online_list_cache() {
	return online_find_cache();
}

// A member is online only while at least one authenticated, non-revoked Session is inside
// the configured activity window. The lower bound is deliberately part of every count/list
// query; relying on five-minute GC made expired rows visible between cron runs.
function online_member_condition() {
	global $conf, $time;
	$hold_time = isset($conf['online_hold_time']) ? max(0, intval($conf['online_hold_time'])) : 0;
	return array(
		'uid'=>array('>'=>0),
		'bigdata'=>array('<='=>1),
		'last_date'=>array('>='=>$time - $hold_time),
	);
}

function online_member_row_is_active($row) {
	if(!is_array($row)) return FALSE;
	$condition = online_member_condition();
	return isset($row['uid'], $row['bigdata'], $row['last_date'])
		&& intval($row['uid']) > intval($condition['uid']['>'])
		&& intval($row['bigdata']) <= intval($condition['bigdata']['<='])
		&& intval($row['last_date']) >= intval($condition['last_date']['>=']);
}

// Routine heartbeats do not need to invalidate the shared snapshot. The old expiry bound will
// force a rebuild before a renewed Session could disappear. Login, logout, revocation and a
// first request after expiry do change membership and therefore advance the generation.
function online_member_state_changed($before, $after) {
	if(is_array($before) && is_array($after)) $after = array_merge($before, $after);
	$before_active = online_member_row_is_active($before);
	$after_active = online_member_row_is_active($after);
	if($before_active != $after_active) return TRUE;
	if(!$before_active) return FALSE;
	return intval($before['uid']) !== intval($after['uid']);
}

function online_member_snapshot_generation_new() {
	global $time;
	try {
		$random = function_exists('random_bytes') ? bin2hex(random_bytes(12)) : md5(uniqid('', TRUE).mt_rand());
	} catch(Throwable $e) {
		$random = md5(uniqid('', TRUE).mt_rand());
	}
	return intval($time).'-'.$random;
}

function online_member_snapshot_generation_read() {
	if(!function_exists('cache_get')) return FALSE;
	$generation = function_exists('cache_get_primary')
		? cache_get_primary('online_member_generation')
		: cache_get('online_member_generation');
	return (is_string($generation) || is_int($generation)) && (string)$generation !== ''
		? (string)$generation
		: FALSE;
}

function online_member_snapshot_generation() {
	$generation = online_member_snapshot_generation_read();
	if($generation !== FALSE) return $generation;

	$generation = online_member_snapshot_generation_new();
	if(!function_exists('cache_set') || cache_set('online_member_generation', $generation) === FALSE) return FALSE;
	return online_member_snapshot_generation_read();
}

function online_member_snapshot_invalidate() {
	global $g_online_member_snapshot;
	$g_online_member_snapshot = NULL;
	if(!function_exists('cache_set')) return FALSE;
	$generation = online_member_snapshot_generation_new();
	$r = cache_set('online_member_generation', $generation);
	// These deletes are best-effort: generation comparison, not deletion, is the stale-write guard.
	if(function_exists('cache_delete')) {
		cache_delete('online_member_snapshot');
		cache_delete('online_list');
	}
	return $r !== FALSE;
}

function online_member_snapshot_valid($snapshot, $generation, $require_available = TRUE) {
	global $time;
	return is_array($snapshot)
		&& isset($snapshot['version'], $snapshot['generation'], $snapshot['expires_at'], $snapshot['count'], $snapshot['rows'], $snapshot['available'])
		&& intval($snapshot['version']) === 3
		&& (string)$snapshot['generation'] === (string)$generation
		&& intval($snapshot['expires_at']) >= intval($time)
		&& is_array($snapshot['rows'])
		&& (!$require_available || !empty($snapshot['available']));
}

function online_member_snapshot_unavailable($generation) {
	global $time;
	return array(
		'version'=>3,
		'generation'=>(string)$generation,
		'generated_at'=>intval($time),
		'expires_at'=>intval($time),
		'available'=>FALSE,
		'count'=>0,
		'rows'=>array(),
	);
}

function online_member_snapshot() {
	global $g_online_member_snapshot;
	// A request keeps one coherent generation even if another request mutates Sessions midway.
	// Mutations performed by this request call online_member_snapshot_invalidate() and clear it.
	if(online_member_snapshot_valid($g_online_member_snapshot, isset($g_online_member_snapshot['generation']) ? $g_online_member_snapshot['generation'] : '', FALSE)) {
		return $g_online_member_snapshot;
	}

	for($attempt = 0; $attempt < 3; $attempt++) {
		$generation = online_member_snapshot_generation();
		if($generation === FALSE) {
			$snapshot = online_member_snapshot_build('uncached');
			$g_online_member_snapshot = $snapshot === FALSE ? online_member_snapshot_unavailable('uncached') : $snapshot;
			return $g_online_member_snapshot;
		}

		$cached = cache_get('online_member_snapshot');
		if(online_member_snapshot_valid($cached, $generation)) {
			$g_online_member_snapshot = $cached;
			return $g_online_member_snapshot;
		}

		$snapshot = online_member_snapshot_build($generation);
		if($snapshot === FALSE) {
			$g_online_member_snapshot = online_member_snapshot_unavailable($generation);
			return $g_online_member_snapshot;
		}
		// A Session mutation between either SQL query and publication changes the generation.
		// Publishing an older generation is harmless, but retrying avoids avoidable cache misses.
		if(online_member_snapshot_generation_read() !== $generation) continue;
		cache_set('online_member_snapshot', $snapshot, 300);
		$g_online_member_snapshot = $snapshot;
		return $g_online_member_snapshot;
	}

	$generation = online_member_snapshot_generation();
	$generation = $generation === FALSE ? 'uncached' : $generation;
	$snapshot = online_member_snapshot_build($generation);
	$g_online_member_snapshot = $snapshot === FALSE ? online_member_snapshot_unavailable($generation) : $snapshot;
	return $g_online_member_snapshot;
}

function online_member_snapshot_build($generation) {
	global $_SERVER, $conf, $time;
	$condition = online_member_condition();
	$count = NULL;
	$min_last_date = NULL;
	$rows = NULL;
	$db = isset($_SERVER['db']) ? $_SERVER['db'] : NULL;
	$master_find_one = $db && method_exists($db, 'sql_find_one_master');
	$master_find_many = $db && method_exists($db, 'sql_find_master');
	$master_supported = $master_find_one && $master_find_many;

	// A partially exposed primary API is a broken consistency contract, not an unsupported
	// single-endpoint driver. Only drivers with neither API may use the complete-page fallback.
	if($master_find_one != $master_find_many) return FALSE;
	if($master_supported) {
		if(!isset($db->tablepre) || !function_exists('db_sql_find_one_master') || !function_exists('db_sql_find_master')) return FALSE;
		$table = $db->tablepre.'session';
		if(!preg_match('/^[A-Za-z0-9_]+$/D', $table)) return FALSE;
		$since = intval($condition['last_date']['>=']);
		$population = '`uid` > 0 AND `bigdata` <= 1 AND `last_date` >= '.$since;
		$count_sql = 'SELECT COUNT(*) AS num, MIN(`member_last_date`) AS min_last_date FROM ('
			.'SELECT `uid`, MAX(`last_date`) AS member_last_date FROM `'.$table.'` WHERE '.$population.' GROUP BY `uid`'
			.') AS `online_members`';
		$count_row = db_sql_find_one_master($count_sql, $db);
		if(!is_array($count_row) || !isset($count_row['num'])) return FALSE;
		$rows_sql = 'SELECT `s`.* FROM `'.$table.'` AS `s` '
			.'WHERE `s`.`uid` > 0 AND `s`.`bigdata` <= 1 AND `s`.`last_date` >= '.$since.' '
			.'AND NOT EXISTS (SELECT 1 FROM `'.$table.'` AS `newer` '
			.'WHERE `newer`.`uid` = `s`.`uid` AND `newer`.`bigdata` <= 1 AND `newer`.`last_date` >= '.$since.' '
			.'AND (`newer`.`last_date` > `s`.`last_date` OR (`newer`.`last_date` = `s`.`last_date` AND `newer`.`sid` > `s`.`sid`))) '
			.'ORDER BY `s`.`last_date` DESC, `s`.`uid` ASC, `s`.`sid` DESC LIMIT 500';
		$sql_rows = db_sql_find_master($rows_sql, NULL, $db);
		if(!is_array($sql_rows)) return FALSE;
		$count = max(0, intval($count_row['num']));
		$min_last_date = isset($count_row['min_last_date']) ? intval($count_row['min_last_date']) : NULL;
		$rows = $sql_rows;
	}

	if(!$master_supported) {
		$fallback = online_member_snapshot_fallback($condition);
		if($fallback === FALSE) return FALSE;
		$count = count($fallback);
		$rows = array_slice($fallback, 0, 500);
		foreach($fallback as $row) {
			$last_date = isset($row['last_date']) ? intval($row['last_date']) : 0;
			if($min_last_date === NULL || $last_date < $min_last_date) $min_last_date = $last_date;
		}
	}

	$rows = online_member_snapshot_format_rows($rows);
	$hold_time = isset($conf['online_hold_time']) ? max(0, intval($conf['online_hold_time'])) : 0;
	$expires_at = $count > 0 && $min_last_date !== NULL
		? intval($min_last_date) + $hold_time
		: intval($time) + 300;

	return array(
		'version'=>3,
		'generation'=>(string)$generation,
		'generated_at'=>intval($time),
		'expires_at'=>$expires_at,
		'available'=>TRUE,
		'count'=>$count,
		'rows'=>$rows,
	);
}

// Non-SQL test adapters and old custom database drivers retain exact semantics by scanning
// complete pages before limiting people. Limiting Session rows first is the original defect.
function online_member_snapshot_fallback($condition) {
	$unique = array();
	$page = 1;
	$pagesize = 1000;
	do {
		$rows = db_find('session', $condition, array('last_date'=>-1, 'sid'=>-1), $page, $pagesize);
		if(!is_array($rows)) return FALSE;
		foreach($rows as $row) {
			$uid = isset($row['uid']) ? intval($row['uid']) : 0;
			if($uid <= 0 || isset($unique[$uid]) || !online_member_row_is_active($row)) continue;
			$unique[$uid] = $row;
		}
		$page++;
	} while(count($rows) === $pagesize);
	return array_values($unique);
}

function online_member_snapshot_format_rows($rows) {
	$uids = array();
	$active_rows = array();
	foreach($rows as $online) {
		if(!online_member_row_is_active($online)) continue;
		$uid = isset($online['uid']) ? intval($online['uid']) : 0;
		if($uid <= 0) continue;
		$uids[] = $uid;
		$active_rows[] = $online;
	}
	$formatted = array();
	user_read_cache_batch($uids, function($uid, $user, $index) use (&$formatted, $active_rows) {
		$online = $active_rows[$index];
		$online['username'] = is_array($user) && isset($user['username']) ? $user['username'] : '';
		$online['gid'] = is_array($user) && isset($user['gid']) ? intval($user['gid']) : 0;
		$online['ip_fmt'] = long2ip(isset($online['ip']) ? intval($online['ip']) : 0);
		$online['last_date_fmt'] = date('Y-n-j H:i', intval($online['last_date']));
		$formatted[] = $online;
	});
	return $formatted;
}

// Keep the helper names introduced by the earlier compatibility layer callable for legacy Hooks.
// Core consumers use online_member_snapshot() directly so count and presentation stay generation-aligned.
function online_member_unique_rows($pagesize = 500) {
	$rows = online_find_cache();
	return array_slice($rows, 0, max(0, intval($pagesize)));
}

function online_member_unique_rows_from_cache($rows) {
	$unique = array();
	foreach((array)$rows as $row) {
		$uid = isset($row['uid']) ? intval($row['uid']) : 0;
		if($uid <= 0 || isset($unique[$uid]) || !online_member_row_is_active($row)) continue;
		$unique[$uid] = $row;
	}
	return array_values($unique);
}

?>
