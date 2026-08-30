<?php


// 只能在当前 request 生命周期缓存，要跨进程，可以再加一层缓存： memcached/xcache/apc/
$g_static_users = array(); // 变量缓存
$g_user_read_prefetch_context = NULL; // 批量展示读取的单次调用原始行预取
$g_user_cache_primary_only = array(); // 已提交写入后的请求级主库代际；NULL 表示需要重试主库

// hook model_user_start.php

// ------------> 最原生的 CURD，无关联其他数据。

function user__create($arr) {
	// hook model_user__create_start.php
	$r = db_insert('user', $arr);
	// hook model_user__create_end.php
	return $r;
}

function user__update($uid, $update, $expected_auth_epoch = NULL, &$db_result = NULL, &$db_uid = NULL) {
	// hook model_user__update_start.php
	$committed_uid = intval($uid);
	$cond = array('uid'=>$committed_uid);
	$expected_auth_epoch !== NULL AND $cond['auth_epoch'] = intval($expected_auth_epoch);
	$raw_db_result = db_update('user', $cond, $update);
	user_update_db_result_evidence('push', array('result'=>$raw_db_result, 'uid'=>$committed_uid));
	$r = $raw_db_result;
	$db_result = $raw_db_result;
	$db_uid = $committed_uid;
	unset($raw_db_result);
	try {
		// hook model_user__update_end.php
	} finally {
		// Preserve the legacy Hook contract for $r while keeping the caller's commit evidence in a
		// re-entrant core stack outside the Hook's local-variable namespace. End Hooks may override
		// their public return, not the database operation that authentication/cache callers observed.
		$evidence = user_update_db_result_evidence('pop');
		$db_result = is_array($evidence) && array_key_exists('result', $evidence) ? $evidence['result'] : NULL;
		$db_uid = is_array($evidence) && array_key_exists('uid', $evidence) ? intval($evidence['uid']) : NULL;
	}
	return $r;
}

function user_update_db_result_evidence($action, $value = NULL) {
	static $stack = array();
	if($action === 'push') {
		$stack[] = $value;
		return TRUE;
	}
	if($action === 'pop') return empty($stack) ? NULL : array_pop($stack);
	return NULL;
}

function user__read($uid, $primary = FALSE, &$db_evidence = NULL) {
	// hook model_user__read_start.php
	$prefetch_hit = FALSE;
	if(isset($GLOBALS['g_user_read_prefetch_context'])) {
		$prefetched = user_read_prefetch_row($uid, $primary, $prefetch_hit);
	}
	$actual_uid = intval($uid);
	$actual_primary = (bool)$primary;
	$user = $prefetch_hit
		? $prefetched
		: ($actual_primary
			? db_find_one_master('user', array('uid'=>$actual_uid))
			: db_find_one('user', array('uid'=>$actual_uid)));
	user_read_db_result_evidence('push', array(
		'uid'=>$actual_uid,
		'primary'=>$actual_primary,
		'row'=>$user,
	));
	try {
		// hook model_user__read_end.php
	} finally {
		// End Hooks retain their public $user return contract, but cannot rewrite the raw database
		// row, actual target or endpoint used by authentication and cache-consistency callers.
		$db_evidence = user_read_db_result_evidence('pop');
	}
	return $user;
}

function user_read_db_result_evidence($action, $value = NULL) {
	static $stack = array();
	if($action === 'push') {
		$stack[] = $value;
		return TRUE;
	}
	if($action === 'pop') return empty($stack) ? NULL : array_pop($stack);
	return NULL;
}

// Security and post-write consistency paths need the raw row actually returned by the primary
// endpoint for the requested UID. Ordinary user__read() callers still receive every legacy Hook
// mutation; this helper deliberately accepts only the independently preserved database evidence.
function user__read_primary_proven($uid) {
	$uid = intval($uid);
	if($uid <= 0) return FALSE;
	$evidence = NULL;
	user__read($uid, TRUE, $evidence);
	if(!is_array($evidence)
		|| !isset($evidence['uid'], $evidence['primary'])
		|| !array_key_exists('row', $evidence)
		|| intval($evidence['uid']) !== $uid
		|| $evidence['primary'] !== TRUE) return FALSE;
	$row = $evidence['row'];
	if($row === FALSE) return FALSE;
	if($row === NULL || $row === array()) return array();
	if(!is_array($row) || !isset($row['uid']) || intval($row['uid']) !== $uid) return FALSE;
	return $row;
}

function user_read_primary_proven($uid) {
	global $g_static_users;
	$uid = intval($uid);
	$user = user__read_primary_proven($uid);
	if(!is_array($user) || empty($user)) return $user;
	user_format($user);
	// Formatting Hooks may add compatibility fields, but a row retargeted to another UID must never
	// become the authenticated or request-cached identity for the original key.
	if(!isset($user['uid']) || intval($user['uid']) !== $uid) return FALSE;
	$g_static_users[$uid] = $user;
	return $user;
}

function user__delete($uid) {
	// hook model_user__delete_start.php
	$r = db_delete('user', array('uid'=>$uid));
	// hook model_user__delete_end.php
	return $r;
}

// ------------> 关联 CURD，主要是强相关的数据，比如缓存。弱相关的大量数据需要另外处理。

function user_create($arr) {
	// hook model_user_create_start.php
	global $conf;
	$r = user__create($arr);
	
	// 全站统计
	runtime_set('users+', 1);
	runtime_set('todayusers+', 1);
	
	// hook model_user_create_end.php
	return $r;
}

function user_cache_reconcile_after_write($uid, $canonical = NULL, $canonical_known = FALSE) {
	global $conf, $g_static_users, $g_user_cache_primary_only;
	$uid = intval($uid);
	if($uid <= 0) return FALSE;
	// A committed generation must never fall back to this request's pre-commit static/shared/replica
	// state. NULL means the next cache read must retry the primary; an array is a proven primary row.
	$g_user_cache_primary_only[$uid] = NULL;
	if(isset($g_static_users[$uid])) unset($g_static_users[$uid]);
	$cache_type = isset($conf['cache']['type']) ? $conf['cache']['type'] : '';
	if($cache_type === 'mysql') return TRUE;

	$key = "user-$uid";
	if(user_cache_delete_or_absent($key)) return TRUE;
	if(!$canonical_known) $canonical = user__read_primary_proven($uid);
	if(is_array($canonical) && !empty($canonical)) {
		user_format($canonical);
		if(isset($canonical['uid']) && intval($canonical['uid']) === $uid) {
			// The shared cache has no compare-and-swap generation. Writing this snapshot after a failed
			// delete could overwrite a newer concurrent commit, so retain it only for this request.
			$g_static_users[$uid] = $canonical;
			$g_user_cache_primary_only[$uid] = $canonical;
		} else {
			$canonical = FALSE;
		}
	}
	// A transient backend failure may clear on retry. Never replace the key with an unlocked row
	// snapshot: a second delete is monotonic while read-then-set is not.
	if(user_cache_delete_or_absent($key)) return TRUE;
	if(function_exists('xn_log')) {
		$primary_status = is_array($canonical) && !empty($canonical)
			? 'a primary row is retained for this request.'
			: 'the primary row is unavailable; this request will keep bypassing shared cache and replicas.';
		xn_log('Unable to invalidate the shared user cache after a committed database update; '.$primary_status, 'user_error');
	}
	return FALSE;
}

function user_cache_delete_or_absent($key) {
	try {
		if(cache_delete($key) !== FALSE) return TRUE;
		// Core cache drivers normalize a definite miss to NULL and reserve FALSE for an unavailable
		// backend. Only a proven miss is equivalent to a successful idempotent invalidation.
		return cache_get_primary($key) === NULL;
	} catch(Throwable $e) {
		return FALSE;
	}
}

function user_update($uid, $arr, $expected_auth_epoch = NULL, &$db_result = NULL, &$committed_uid = NULL) {
	// hook model_user_update_start.php
	$db_uid = NULL;
	$r = user__update($uid, $arr, $expected_auth_epoch, $db_result, $db_uid);
	$committed_uid = $db_uid;
	// Only the raw database result proves that a new row generation exists. Do not synthesize an
	// updated request-local user from the input: operators such as `logins+`, model Hooks, and
	// formatted group fields make that guessed shape different from the committed row.
	// Cache repair is best effort after an irreversible commit and must not masquerade as rollback.
	if($db_result === 1) user_cache_reconcile_after_write($db_uid);
	user_update_wrapper_result_evidence('push', array('result'=>$db_result, 'uid'=>$db_uid));
	try {
		// hook model_user_update_end.php
	} finally {
		// Wrapper Hooks may keep overriding the public $r return, but nested or throwing Hooks cannot
		// forge the database result/target consumed by credential and cache-consistency callers.
		$evidence = user_update_wrapper_result_evidence('pop');
		$db_result = is_array($evidence) && array_key_exists('result', $evidence) ? $evidence['result'] : NULL;
		$committed_uid = is_array($evidence) && array_key_exists('uid', $evidence) ? intval($evidence['uid']) : NULL;
	}
	return $r;
}

function user_update_wrapper_result_evidence($action, $value = NULL) {
	static $stack = array();
	if($action === 'push') {
		$stack[] = $value;
		return TRUE;
	}
	if($action === 'pop') return empty($stack) ? NULL : array_pop($stack);
	return NULL;
}

function user_read($uid, $primary = FALSE) {
	global $g_static_users;
	if(empty($uid)) return array();
	$uid = intval($uid);
	// hook model_user_read_start.php
	$user = user__read($uid, $primary);
	user_format($user);
	$g_static_users[$uid] = $user;
	// hook model_user_read_end.php
	return $user;
}


// 从缓存中读取，避免重复从数据库取数据，主要用来前端显示，可能有延迟。重要业务逻辑不要调用此函数，数据可能不准确，因为并没有清理缓存，针对 request 生命周期有效。
function user_read_cache($uid) {
	global $conf, $g_static_users, $g_user_cache_primary_only;
	$uid = intval($uid);
	$primary_only = array_key_exists($uid, $g_user_cache_primary_only);
	if($primary_only && is_array($g_user_cache_primary_only[$uid]) && !empty($g_user_cache_primary_only[$uid])) {
		$g_static_users[$uid] = $g_user_cache_primary_only[$uid];
		return $g_static_users[$uid];
	}
	if(!$primary_only && isset($g_static_users[$uid])) return $g_static_users[$uid];
	
	user_read_cache_primary_boundary_evidence('push', array('uid'=>$uid, 'primary_only'=>$primary_only));
	try {
		// hook model_user_read_cache_start.php
	} finally {
		$primary_boundary = user_read_cache_primary_boundary_evidence('pop');
	}
	if(is_array($primary_boundary) && !empty($primary_boundary['primary_only']) && isset($primary_boundary['uid'])) {
		$uid = intval($primary_boundary['uid']);
		$primary_only = TRUE;
	}
	
	// 游客
	if($uid == 0) return user_guest();
	
	if($primary_only) {
		// Cache deletion may have failed and a configured replica may lag. Retry only the primary and
		// never publish this unlocked snapshot to a shared cache that could contain a concurrent winner.
		$primary_proven = FALSE;
		$r = user_read_primary_proven($uid);
		if(!is_array($r) || empty($r)) {
			unset($g_static_users[$uid]);
			$r = user_guest();
		} else {
			$primary_proven = TRUE;
			$g_user_cache_primary_only[$uid] = $r;
		}
	} elseif($conf['cache']['type'] != 'mysql') {
		$r = cache_get("user-$uid");
		if($r === NULL) {
			$r = user_read($uid);
			cache_set("user-$uid", $r);
		}
	} else {
		$r = user_read($uid);
	}
	
	$g_static_users[$uid] = $r ? $r : user_guest();
	
	user_read_cache_primary_boundary_evidence('push', array(
		'uid'=>$uid,
		'primary_only'=>$primary_only,
		'primary_proven'=>$primary_only ? $primary_proven : FALSE,
	));
	try {
		// hook model_user_read_cache_end.php
	} finally {
		$primary_boundary = user_read_cache_primary_boundary_evidence('pop');
	}
	$result_uid = is_array($primary_boundary) && isset($primary_boundary['uid'])
		? intval($primary_boundary['uid'])
		: $uid;
	$result = isset($g_static_users[$result_uid]) ? $g_static_users[$result_uid] : user_guest();
	if(is_array($primary_boundary) && !empty($primary_boundary['primary_only'])) {
		$proved_same_uid = !empty($primary_boundary['primary_proven'])
			&& is_array($result)
			&& isset($result['uid'])
			&& intval($result['uid']) === $result_uid;
		if($proved_same_uid) {
			$g_user_cache_primary_only[$result_uid] = $result;
		} else {
			// Return the legacy Hook-shaped result for this call, but keep retrying the primary and do
			// not cache a guest or retargeted identity as a proven committed generation.
			$g_user_cache_primary_only[$result_uid] = NULL;
			unset($g_static_users[$result_uid]);
		}
	}
	return $result;
}

function user_read_cache_primary_boundary_evidence($action, $value = NULL) {
	static $stack = array();
	if($action === 'push') {
		$stack[] = $value;
		return TRUE;
	}
	if($action === 'pop') return empty($stack) ? NULL : array_pop($stack);
	return NULL;
}

// 只在批量展示调用期间向 user__read() 提供一条完整原始行。检查发生在
// model_user__read_start Hook 之后：Hook 改写 uid、读端点或缓存/数据库上下文时，必须回到原路径。
function user_read_prefetch_row($uid, $primary, &$hit) {
	global $conf, $g_user_read_prefetch_context;
	$hit = FALSE;
	$current_db = isset($_SERVER['db']) ? $_SERVER['db'] : NULL;
	$current_cache_type = isset($conf['cache']['type']) ? $conf['cache']['type'] : '';
	if($primary || !is_array($g_user_read_prefetch_context)
		|| !isset($g_user_read_prefetch_context['uid'], $g_user_read_prefetch_context['row'], $g_user_read_prefetch_context['cache_type'])
		|| !array_key_exists('db', $g_user_read_prefetch_context)
		|| intval($g_user_read_prefetch_context['uid']) !== intval($uid)
		|| $g_user_read_prefetch_context['db'] !== $current_db
		|| $g_user_read_prefetch_context['cache_type'] !== $current_cache_type
		|| !is_array($g_user_read_prefetch_context['row'])
		|| !isset($g_user_read_prefetch_context['row']['uid'])
		|| intval($g_user_read_prefetch_context['row']['uid']) !== intval($uid)) return NULL;
	$hit = TRUE;
	return $g_user_read_prefetch_context['row'];
}

// 批量查询只减少 MySQL cache 模式中本来必然发生的逐用户数据库读取。每个用户仍
// 逐一经过 user_read_cache -> user_read -> user__read -> user_format 的全部既有 Hook。
// 批量查询失败、返回缺行或异常形状时不制造“空用户”，而是让该用户走原单条读取。
function user_read_cache_batch($uids, $consumer = NULL) {
	global $conf, $g_static_users, $g_user_read_prefetch_context;
	if(!is_array($uids)) $uids = array($uids);
	$normalized = array();
	$sequence = array();
	foreach($uids as $uid) {
		$uid = intval($uid);
		if($uid <= 0) continue;
		$sequence[] = $uid;
		if(!isset($normalized[$uid])) $normalized[$uid] = $uid;
	}
	if(empty($normalized)) return array();

	$prefetched = array();
	$cache_type = isset($conf['cache']['type']) ? $conf['cache']['type'] : '';
	$prefetch_db = isset($_SERVER['db']) ? $_SERVER['db'] : NULL;
	if($cache_type === 'mysql') {
		$missing = array();
		foreach($normalized as $uid) {
			if(!isset($g_static_users[$uid])) $missing[] = $uid;
		}
		if(count($missing) > 1) {
			$rows = db_find('user', array('uid'=>$missing), array(), 1, count($missing), 'uid');
			if(is_array($rows)) foreach($rows as $row) {
				if(!is_array($row) || !isset($row['uid'])) continue;
				$uid = intval($row['uid']);
				if($uid > 0 && isset($normalized[$uid]) && isset($g_static_users[$uid]) === FALSE) {
					$prefetched[$uid] = $row;
				}
			}
		}
	}

	$users = array();
	$consume = is_callable($consumer);
	$previous_context = $g_user_read_prefetch_context;
	try {
		foreach($sequence as $index=>$uid) {
			$g_user_read_prefetch_context = isset($prefetched[$uid])
				? array('uid'=>$uid, 'row'=>$prefetched[$uid], 'db'=>$prefetch_db, 'cache_type'=>$cache_type)
				: NULL;
			$users[$uid] = user_read_cache($uid);
			$consume AND $consumer($uid, $users[$uid], $index);
		}
	} finally {
		$g_user_read_prefetch_context = $previous_context;
	}
	return $users;
}

function user_delete($uid) {
	global $conf, $g_static_users;
	// hook model_user_delete_start.php
	
	// 清理主题帖
	$threadlist = mythread_find_by_uid($uid, 1, 1000);
	foreach($threadlist as $thread) {
		thread_delete($thread['tid']);
	}
	
	// 清理回帖
	post_delete_by_uid($uid);
	
	// 清理附件
	attach_delete_by_uid($uid);
	
	$r = user__delete($uid);
	
	$conf['cache']['type'] != 'mysql' AND cache_delete("user-$uid");
	if(isset($g_static_users[$uid])) unset($g_static_users[$uid]);
	
	// 全站统计
	runtime_set('users-', 1);
	
	// hook model_user_delete_end.php
	return $r;
}

function user_find($cond = array(), $orderby = array(), $page = 1, $pagesize = 20) {
	global $g_static_users;
	// hook model_user_find_start.php
	$userlist = db_find('user', $cond, $orderby, $page, $pagesize);
	if($userlist) foreach ($userlist as &$user) {
		$g_static_users[$user['uid']] = $user;
		user_format($user);
	}
	// hook model_user_find_end.php
	return $userlist;
}

// ------------> 其他方法

function user_read_by_email($email, $primary = FALSE) {
	global $g_static_users;
	// hook model_user_read_by_email_start.php
	$user = $primary
		? db_find_one_master('user', array('email'=>$email))
		: db_find_one('user', array('email'=>$email));
	if(empty($user)) return array();
	user_format($user);
	$g_static_users[$user['uid']] = $user;
	// hook model_user_read_by_email_end.php
	return $user;
}

function user_read_by_username($username, $primary = FALSE) {
	global $g_static_users;
	// hook model_user_read_by_username_start.php
	$user = $primary
		? db_find_one_master('user', array('username'=>$username))
		: db_find_one('user', array('username'=>$username));
	if(empty($user)) return array();
	user_format($user);
	$g_static_users[$user['uid']] = $user;
	// hook model_user_read_by_username_end.php
	return $user;
}

function user_count($cond = array()) {
	// hook model_user_count_start.php
	$n = db_count('user', $cond);
	// hook model_user_count_end.php
	return $n;
}

function user_maxid($cond = array()) {
	// hook model_user_maxid_start.php
	$n = db_maxid('user', 'uid');
	// hook model_user_maxid_end.php
	return $n;
}

function user_login_rate_key($account) {
	global $longip;
	$account = strtolower(trim((string)$account));
	$account_hash = hash_hmac('sha256', $account, xn_key());
	return 'user_login_rate_'.substr(hash('sha256', intval($longip).'|'.$account_hash), 0, 16);
}

function user_login_rate_read($account) {
	global $time;
	$key = user_login_rate_key($account);
	$record = cache_get_primary($key);
	if($record === FALSE) {
		return array('count'=>10, 'time'=>$time);
	}
	if(!is_array($record)) {
		return array('count'=>0, 'time'=>$time);
	}
	$count = isset($record['count']) ? intval($record['count']) : 0;
	$start = isset($record['time']) ? intval($record['time']) : $time;
	return array('count'=>max(0, $count), 'time'=>$start);
}

function user_login_rate_limited($account) {
	$record = user_login_rate_read($account);
	return $record['count'] >= 10;
}

function user_login_rate_lockname($key) {
	return 'login_rate_'.substr(hash('sha256', $key), 0, 16);
}

function user_login_rate_fail($account) {
	global $time;
	$key = user_login_rate_key($account);
	$lockname = user_login_rate_lockname($key);
	$locked = FALSE;
	if(function_exists('xn_lock_start')) {
		$locked = xn_lock_start($lockname, 5);
		if(!$locked) {
			return cache_set($key, array('count'=>10, 'time'=>$time), 900);
		}
	}
	try {
		$record = user_login_rate_read($account);
		$count = $record['count'] + 1;
		return cache_set($key, array('count'=>$count, 'time'=>$time), 900);
	} finally {
		$locked AND xn_lock_end($lockname);
	}
}

function user_login_rate_clear($account) {
	return cache_delete(user_login_rate_key($account));
}

function user_format(&$user) {
	global $conf, $grouplist;
	if(empty($user)) return;

	// hook model_user_format_start.php
	
	$user['create_ip_fmt']   = long2ip((int)$user['create_ip']);
	$user['create_date_fmt'] = empty($user['create_date']) ? '0000-00-00' : date('Y-m-d', $user['create_date']);
	$user['login_ip_fmt']    = long2ip((int)$user['login_ip']);
	$user['login_date_fmt'] = empty($user['login_date']) ? '0000-00-00' : date('Y-m-d', $user['login_date']);
	
	$user['groupname'] = group_name($user['gid']);
	
	$dir = substr(sprintf("%09d", $user['uid']), 0, 3);
	// hook model_user_format_avatar_url_before.php
	$user['avatar_url'] = $user['avatar'] ? $conf['upload_url']."avatar/$dir/$user[uid].png?".$user['avatar'] : 'view/img/avatar.png';

	$user['online_status'] = 1;
	// hook model_user_format_end.php
}


function user_guest() {
	global $conf;
	static $guest = NULL;
	// hook model_user_guest_start.php
	
	if($guest) return $guest; // 返回引用，节省内存。
	$guest = array (
		'uid' => 0,
		'gid' => 0,
		'groupname' => lang('guest_group'),
		'username' => lang('guest'),
		'avatar_url' => 'view/img/avatar.png',
		'create_ip_fmt' => '',
		'create_date_fmt' => '',
		'login_date_fmt' => '',
		'email' => '',
		
		'threads' => 0,
		'posts' => 0,
	);
	
	// hook model_user_guest_end.php
	return $guest; // 防止内存拷贝
}

// 根据积分来调整用户组
function user_update_group($uid) {
	global $conf, $grouplist;
	$user = user_read_cache($uid);
	if($user['gid'] < 100) return FALSE;
	
	// hook model_user_update_group_start.php
	
	// 遍历 credits 范围，调整用户组
	foreach($grouplist as $group) {
		if($group['gid'] < 100) continue;
		$n = $user['posts'] + $user['threads']; // 根据发帖数
		// hook model_user_update_group_policy_start.php
		if($n > $group['creditsfrom'] && $n < $group['creditsto']) {
			if($user['gid'] != $group['gid']) {
				$db_result = NULL;
				user_update($uid, array('gid'=>$group['gid']), NULL, $db_result);
				if($db_result === 1) return TRUE;
				if($db_result !== 0) return FALSE;

				// Zero affected rows is ambiguous: a concurrent writer may already have published the
				// target group, or the user may have disappeared. Only a primary reread can distinguish it.
				$canonical = user__read_primary_proven($uid);
				if($canonical === FALSE) return FALSE;
				$target_reached = !empty($canonical)
					&& isset($canonical['gid'])
					&& intval($canonical['gid']) === intval($group['gid']);
				user_cache_reconcile_after_write($uid, $canonical, TRUE);
				return $target_reached;
			}
		}
	}
	
	// hook model_user_update_group_end.php
	return FALSE;
}

// uids: 1,2,3,4 -> array()
function user_find_by_uids($uids) {
	// hook model_user_find_by_uids_start.php
	$uids = trim($uids);
	if(empty($uids)) return array();
	$arr = explode(',', $uids);
	$r = array();
	foreach($arr as $_uid) {
		$user = user_read_cache($_uid);
		if(empty($user)) continue;
		$r[$user['uid']] = $user;
	}
	// hook model_user_find_by_uids_end.php
	return $r;
}

// 获取用户安全信息
function user_safe_info($user) {
	// hook model_user_safe_info_start.php
	unset($user['password']);
	unset($user['email']);
	unset($user['salt']);
	unset($user['password_sms']);
	unset($user['idnumber']);
	unset($user['realname']);
	unset($user['qq']);
	unset($user['mobile']);
	unset($user['create_ip']);
	unset($user['create_ip_fmt']);
	unset($user['create_date']);
	unset($user['create_date_fmt']);
	unset($user['login_ip']);
	unset($user['login_date']);
	unset($user['login_ip_fmt']);
	unset($user['login_date_fmt']);
	unset($user['logins']);
	unset($user['auth_epoch']);
	// hook model_user_safe_info_end.php
	return $user;
}


// 用户凭证代际。旧站在迁移前没有该字段，统一视为第 0 代；第一次受控改密后
// password 与 auth_epoch 在同一条 UPDATE 中提交，旧 Session/长期 token 随即失效。
function user_auth_epoch($user) {
	if(!is_array($user) || !isset($user['auth_epoch'])) return 0;
	$epoch = intval($user['auth_epoch']);
	return $epoch > 0 ? $epoch : 0;
}

function user_auth_epoch_matches($user, $epoch) {
	if(empty($user) || !is_array($user)) return FALSE;
	if(!is_int($epoch) && !(is_string($epoch) && preg_match('/^\d+$/D', $epoch))) return FALSE;
	$epoch = intval($epoch);
	return $epoch >= 0 && user_auth_epoch($user) === $epoch;
}

function user_session_auth_bind($uid, $epoch) {
	$uid = intval($uid);
	$epoch = intval($epoch);
	if($uid <= 0 || $epoch < 0) return FALSE;
	$_SESSION['uid'] = $uid;
	$_SESSION['auth_epoch'] = $epoch;
	return TRUE;
}

function user_session_auth_matches($user) {
	$session_epoch = isset($_SESSION['auth_epoch']) ? $_SESSION['auth_epoch'] : 0;
	return user_auth_epoch_matches($user, $session_epoch);
}

function user_password_commit_locked($uid, $password_hash, $update = array(), $expected_auth_epoch = NULL) {
	global $conf, $g_static_users;
	$uid = intval($uid);
	if($uid <= 0 || !is_string($password_hash) || $password_hash === '' || !is_array($update)) return FALSE;

	$before = user__read_primary_proven($uid);
	if(empty($before)) return FALSE;
	$before_epoch = user_auth_epoch($before);
	if($expected_auth_epoch !== NULL && $before_epoch !== intval($expected_auth_epoch)) return FALSE;
	unset($update['password'], $update['auth_epoch'], $update['auth_epoch+'], $update['auth_epoch-']);
	$update['password'] = $password_hash;
	$update['auth_epoch+'] = 1;

	// Preserve the established model_user_update Hook contract while making password+epoch one
	// conditional SQL write. The epoch condition is the final guard against a concurrent credential
	// change that did not participate in the reset-grant lock.
	$db_result = NULL;
	$committed_uid = NULL;
	$r = user_update($uid, $update, $before_epoch, $db_result, $committed_uid);
	// A legacy start Hook may retarget the public write. That already-committed row cannot be rolled
	// back here, but it must never authorize, revoke grants for, or bind the originally locked user.
	if($db_result !== 1 || $committed_uid !== $uid) return FALSE;
	if(isset($g_static_users[$uid])) unset($g_static_users[$uid]);
	if(isset($conf['cache']['type']) && $conf['cache']['type'] != 'mysql' && function_exists('cache_delete')) {
		cache_delete("user-$uid");
	}
	// The conditional password+epoch UPDATE targets one primary-key row. Exactly one affected row is
	// the commit proof; a write followed by a slave read can report a false failure during lag.
	$after_epoch = $before_epoch + 1;

	// A password change invalidates every older grant through auth_epoch even if stale KV cleanup
	// itself fails after the irreversible password write. Best-effort deletion prevents dead records
	// from lingering without turning a successful credential change into a misleading failure.
	if(!user_reset_grant_revoke_locked($uid) && function_exists('xn_log')) {
		xn_log('Unable to delete a stale password-reset grant after credential epoch change for uid '.$uid, 'user_auth_error');
	}
	return $after_epoch;
}

function user_password_commit($uid, $password_hash, $update = array()) {
	$uid = intval($uid);
	if($uid <= 0) return FALSE;
	$lockname = user_reset_grant_lock_name($uid);
	if(!xn_lock_start($lockname, 30)) return FALSE;
	try {
		return user_password_commit_locked($uid, $password_hash, $update);
	} finally {
		xn_lock_end($lockname);
	}
}

// A password proof must be checked again while holding the same per-user lock used by every
// credential change. The caller receives only the generation that this proof actually authorized;
// a concurrent password change can invalidate the result, but can never be inherited.
function user_login_credentials_refresh($uid, $password) {
	$uid = intval($uid);
	if($uid <= 0 || !is_string($password) || $password === '') return FALSE;
	$lockname = user_reset_grant_lock_name($uid);
	if(!xn_lock_start($lockname, 30)) return FALSE;
	try {
		$user = user__read_primary_proven($uid);
		if(empty($user) || !user_verify_password($password, $user)) return FALSE;
		if(!user_password_needs_upgrade($user)) return $user;

		$new_hash = user_hash_password($password);
		if(!is_string($new_hash) || $new_hash === '') return FALSE;
		$new_epoch = user_password_commit_locked($uid, $new_hash, array(), user_auth_epoch($user));
		if($new_epoch === FALSE) return FALSE;

		$refreshed = user__read_primary_proven($uid);
		if(empty($refreshed)
			|| !user_auth_epoch_matches($refreshed, $new_epoch)
			|| !isset($refreshed['password'])
			|| !hash_equals($new_hash, (string)$refreshed['password'])) return FALSE;
		return $refreshed;
	} finally {
		xn_lock_end($lockname);
	}
}

// Self-service password changes are conditional on the old password as observed inside the
// credential lock. Administrator replacement remains the explicit unconditional last-writer path.
function user_password_change_verified($uid, $password_old, $password_hash, $update = array()) {
	$uid = intval($uid);
	if($uid <= 0 || !is_string($password_old) || $password_old === ''
		|| !is_string($password_hash) || $password_hash === '' || !is_array($update)) return FALSE;
	$lockname = user_reset_grant_lock_name($uid);
	if(!xn_lock_start($lockname, 30)) return FALSE;
	try {
		$user = user__read_primary_proven($uid);
		if(empty($user) || !user_verify_password($password_old, $user)) return FALSE;
		return user_password_commit_locked($uid, $password_hash, $update, user_auth_epoch($user));
	} finally {
		xn_lock_end($lockname);
	}
}

function user_reset_grant_ttl() {
	return 300;
}

function user_reset_grant_email($email) {
	return strtolower(trim((string)$email));
}

function user_reset_grant_key($uid) {
	return 'user_reset_grant_'.intval($uid);
}

function user_reset_grant_lock_name($uid) {
	return 'user_reset_grant_'.intval($uid);
}

function user_reset_grant_email_hash($email) {
	return hash_hmac('sha256', user_reset_grant_email($email), xn_key());
}

function user_reset_grant_shape_valid($grant) {
	if(!is_array($grant) || !isset($grant['uid'], $grant['email'], $grant['iat'], $grant['nonce'], $grant['auth_epoch'])) return FALSE;
	if(intval($grant['uid']) <= 0 || intval($grant['iat']) <= 0) return FALSE;
	if(!is_int($grant['auth_epoch']) && !(is_string($grant['auth_epoch']) && preg_match('/^\d+$/D', $grant['auth_epoch']))) return FALSE;
	if(intval($grant['auth_epoch']) < 0) return FALSE;
	if(user_reset_grant_email($grant['email']) === '') return FALSE;
	return is_string($grant['nonce']) && preg_match('/^[a-f0-9]{64}$/D', $grant['nonce']) === 1;
}

function user_reset_grant_record($grant) {
	return array(
		'uid'=>intval($grant['uid']),
		'email_hash'=>user_reset_grant_email_hash($grant['email']),
		'iat'=>intval($grant['iat']),
		'nonce'=>$grant['nonce'],
		'auth_epoch'=>intval($grant['auth_epoch']),
	);
}

function user_reset_grant_record_matches($grant, $stored) {
	if(!user_reset_grant_shape_valid($grant) || !is_array($stored)) return FALSE;
	$expected = user_reset_grant_record($grant);
	if(!isset($stored['uid'], $stored['email_hash'], $stored['iat'], $stored['nonce'], $stored['auth_epoch'])) return FALSE;
	return intval($stored['uid']) === $expected['uid']
		&& intval($stored['iat']) === $expected['iat']
		&& intval($stored['auth_epoch']) === $expected['auth_epoch']
		&& is_string($stored['email_hash']) && hash_equals($expected['email_hash'], $stored['email_hash'])
		&& is_string($stored['nonce']) && hash_equals($expected['nonce'], $stored['nonce']);
}

function user_reset_grant_time_valid($grant) {
	global $time;
	if(!user_reset_grant_shape_valid($grant)) return FALSE;
	$iat = intval($grant['iat']);
	$now = intval($time);
	return $now > 0 && $iat <= $now + 5 && $now - $iat <= user_reset_grant_ttl();
}

function user_reset_grant_issue($uid, $email) {
	global $time;
	$uid = intval($uid);
	$email = user_reset_grant_email($email);
	if($uid <= 0 || $email === '' || intval($time) <= 0) return FALSE;
	$lockname = user_reset_grant_lock_name($uid);
	if(!xn_lock_start($lockname, 30)) return FALSE;
	try {
		$_user = user__read_primary_proven($uid);
		if(empty($_user) || user_reset_grant_email($_user['email']) !== $email) return FALSE;
		try {
			$nonce = bin2hex(random_bytes(32));
		} catch(Throwable $e) {
			return FALSE;
		}
		$grant = array(
			'uid'=>$uid,
			'email'=>$email,
			'iat'=>intval($time),
			'nonce'=>$nonce,
			'auth_epoch'=>user_auth_epoch($_user),
		);
		if(kv_set(user_reset_grant_key($uid), user_reset_grant_record($grant)) === FALSE) return FALSE;
		$stored = kv__get(user_reset_grant_key($uid), TRUE);
		if(!user_reset_grant_record_matches($grant, $stored)) return FALSE;
		$_SESSION['resetpw_grant'] = $grant;
		return $grant;
	} finally {
		xn_lock_end($lockname);
	}
}

function user_reset_grant_current() {
	$grant = isset($_SESSION['resetpw_grant']) ? $_SESSION['resetpw_grant'] : NULL;
	if(!user_reset_grant_time_valid($grant)) {
		unset($_SESSION['resetpw_grant']);
		return FALSE;
	}
	$uid = intval($grant['uid']);
	$lockname = user_reset_grant_lock_name($uid);
	if(!xn_lock_start($lockname, 30)) return FALSE;
	try {
		$stored = kv__get(user_reset_grant_key($uid), TRUE);
		if($stored === FALSE) return FALSE;
		if(!user_reset_grant_record_matches($grant, $stored)) {
			unset($_SESSION['resetpw_grant']);
			return FALSE;
		}
		$_user = user__read_primary_proven($uid);
		if(empty($_user)
			|| !user_auth_epoch_matches($_user, $grant['auth_epoch'])
			|| user_reset_grant_email($_user['email']) !== user_reset_grant_email($grant['email'])) {
			user_reset_grant_revoke_locked($uid);
			return FALSE;
		}
		return $grant;
	} finally {
		xn_lock_end($lockname);
	}
}

function user_reset_grant_commit_password($password_hash) {
	$grant = isset($_SESSION['resetpw_grant']) ? $_SESSION['resetpw_grant'] : NULL;
	if(!is_string($password_hash) || $password_hash === '' || !user_reset_grant_time_valid($grant)) {
		unset($_SESSION['resetpw_grant']);
		return NULL;
	}
	$uid = intval($grant['uid']);
	$lockname = user_reset_grant_lock_name($uid);
	if(!xn_lock_start($lockname, 30)) return FALSE;
	try {
		$stored = kv__get(user_reset_grant_key($uid), TRUE);
		if($stored === FALSE) return FALSE;
		if(!user_reset_grant_record_matches($grant, $stored)) {
			unset($_SESSION['resetpw_grant']);
			return NULL;
		}
		$_user = user__read_primary_proven($uid);
		if(empty($_user)
			|| !user_auth_epoch_matches($_user, $grant['auth_epoch'])
			|| user_reset_grant_email($_user['email']) !== user_reset_grant_email($grant['email'])) {
			user_reset_grant_revoke_locked($uid);
			return NULL;
		}
		if(kv_delete(user_reset_grant_key($uid)) === FALSE) return FALSE;
		if(kv__get(user_reset_grant_key($uid), TRUE) !== NULL) return FALSE;
		unset($_SESSION['resetpw_grant']);
		// Keep the grant consumed when the password write fails. The user must prove email ownership
		// again; a failed commit must never resurrect a one-time authorization.
		return user_password_commit_locked($uid, $password_hash, array(), intval($grant['auth_epoch']));
	} finally {
		xn_lock_end($lockname);
	}
}

function user_reset_grant_revoke_locked($uid) {
	$uid = intval($uid);
	if($uid <= 0) return FALSE;
	if(isset($_SESSION['resetpw_grant']) && is_array($_SESSION['resetpw_grant'])
		&& isset($_SESSION['resetpw_grant']['uid']) && intval($_SESSION['resetpw_grant']['uid']) === $uid) {
		unset($_SESSION['resetpw_grant']);
	}
	if(kv_delete(user_reset_grant_key($uid)) === FALSE) return FALSE;
	if(kv__get(user_reset_grant_key($uid), TRUE) !== NULL) return FALSE;
	return TRUE;
}

function user_reset_grant_revoke_uid($uid) {
	$uid = intval($uid);
	if($uid <= 0) return FALSE;
	$lockname = user_reset_grant_lock_name($uid);
	if(!xn_lock_start($lockname, 30)) return FALSE;
	try {
		return user_reset_grant_revoke_locked($uid);
	} finally {
		xn_lock_end($lockname);
	}
}

function user_email_code_rate_key($prefix, $dimension, $value) {
	$prefix = strtolower(trim((string)$prefix));
	$dimension = strtolower(trim((string)$dimension));
	$value = strtolower(trim((string)$value));
	if(!preg_match('/^[a-z0-9_]{1,32}$/D', $prefix)) return FALSE;
	if(!in_array($dimension, array('email', 'ip'), TRUE) || $value === '') return FALSE;
	$digest = hash_hmac('sha256', $prefix."\0".$dimension."\0".$value, xn_key());
	// Leave room for the configured cache prefix in MySQL's historical char(32) cache key.
	return 'uecr_'.substr($digest, 0, 19);
}

// Return 1 when both dimensions were charged, 0 when either dimension is limited, and -1 when
// shared state or its stable locks are unavailable. Failures are deliberately fail-closed.
function user_email_code_rate_take($prefix, $email, $client_ip, $now) {
	$email_key = user_email_code_rate_key($prefix, 'email', user_reset_grant_email($email));
	$ip_key = user_email_code_rate_key('all', 'ip', $client_ip);
	$now = intval($now);
	if($email_key === FALSE || $ip_key === FALSE || $now <= 0) return -1;

	// Keep the account-target limit strict while allowing a larger IP budget for offices, schools
	// and carrier NAT. The IP dimension still stops broad address rotation across actions.
	$limits = array($email_key=>5, $ip_key=>20);
	ksort($limits, SORT_STRING);
	$keys = array_keys($limits);
	$locked = array();
	foreach($keys as $key) {
		$lockname = 'email_code_rate_'.$key;
		if(!xn_lock_start($lockname, 10)) {
			foreach(array_reverse($locked) as $locked_name) xn_lock_end($locked_name);
			return -1;
		}
		$locked[] = $lockname;
	}

	$result = 1;
	$updates = array();
	try {
		foreach($keys as $key) {
			$record = cache_get_primary($key);
			if($record === FALSE) {
				$result = -1;
				break;
			}
			$window_start = is_array($record) && isset($record['window_start']) ? intval($record['window_start']) : 0;
			$send_count = is_array($record) && isset($record['send_count']) ? intval($record['send_count']) : 0;
			if($window_start <= 0 || $window_start > $now || $now - $window_start > 3600) {
				$window_start = $now;
				$send_count = 0;
			}
			if($send_count >= $limits[$key]) {
				$result = 0;
				break;
			}
			$updates[$key] = array('window_start'=>$window_start, 'send_count'=>$send_count + 1);
		}
		if($result === 1) {
			foreach($updates as $key=>$record) {
				if(cache_set($key, $record, 3700) === FALSE || cache_get_primary($key) !== $record) {
					$result = -1;
					break;
				}
			}
		}
	} finally {
		foreach(array_reverse($locked) as $lockname) xn_lock_end($lockname);
	}
	return $result;
}

function user_email_code_rate_limit($prefix, $email) {
	global $time, $ip;
	$result = user_email_code_rate_take($prefix, $email, (string)$ip, $time);
	$result === 0 AND message('email', lang('verify_code_try_too_frequently', array('times'=>5)));
	$result !== 1 AND message(-1, 'Unable to enforce the verification-code rate limit. Please try again.');
	return TRUE;
}

// 用户
function user_token_get(&$auth_epoch = NULL) {
	global $time, $conf;
	$auth_epoch = NULL;
	$_uid = user_token_get_do($auth_epoch);
	
	// hook model_user_token_get_start.php
	
	if(!$_uid) {
		user_token_cookie_set('', $time - 86400);
	}
	
	// hook model_user_token_get_end.php
	
	return $_uid;
}

// 用户
function user_token_get_do(&$auth_epoch = NULL) {
	global $time, $ip, $conf;
	$auth_epoch = NULL;
	$token = param('bbs_token');
	
	// hook model_user_token_get_do_start.php
	
	if(empty($token)) return FALSE;
	$tokenkey = hash('sha256', xn_key());
	$s = xn_decrypt($token, $tokenkey);
	if(empty($s)) return FALSE;
	$arr = explode("\t", $s);
	if(count($arr) != 3 && count($arr) != 4 && count($arr) != 5) return FALSE;
	$_fingerprint = '';
	$_auth_epoch = 0;
	list($_ip, $_time, $_uid) = array_slice($arr, 0, 3);
	if(count($arr) >= 4) $_fingerprint = $arr[3];
	if(count($arr) == 5) {
		if(!preg_match('/^\d+$/D', (string)$arr[4])) return FALSE;
		$_auth_epoch = intval($arr[4]);
	}
	// Token 有效期 30 天
	if($time - intval($_time) > 86400 * 30) return FALSE;
	if($_fingerprint !== '' && !hash_equals(user_token_fingerprint(), $_fingerprint)) return FALSE;
	$_uid = intval($_uid);
	if($_uid <= 0) return FALSE;

	// hook model_user_token_get_do_end.php
	$_user = user__read_primary_proven($_uid);
	if(empty($_user) || !user_auth_epoch_matches($_user, $_auth_epoch)) return FALSE;
	$auth_epoch = $_auth_epoch;

	return $_uid;
}

// 设置 token，防止 sid 过期后被删除
function user_token_set($uid, $expected_auth_epoch = NULL) {
	global $time, $conf;
	if(empty($uid)) return FALSE;
	$token = user_token_gen($uid, $expected_auth_epoch);
	if($token === FALSE) return FALSE;
	$r = user_token_cookie_set($token, $time + 8640000);
	
	// hook model_user_token_set_end.php
	return $r;
}

function user_token_clear() {
	global $time, $conf;
	$r = user_token_cookie_set('', $time - 8640000);
	
	// hook model_user_token_clear_end.php
	return $r;
}

function user_token_gen($uid, $expected_auth_epoch = NULL) {
	global $ip, $time, $conf;
	
	// hook model_user_token_gen_start.php
	
	$tokenkey = hash('sha256', xn_key());
	$fingerprint = user_token_fingerprint();
	$_user = user__read_primary_proven($uid);
	if(empty($_user)) return FALSE;
	$auth_epoch = user_auth_epoch($_user);
	if($expected_auth_epoch !== NULL && !user_auth_epoch_matches($_user, $expected_auth_epoch)) return FALSE;
	$token = xn_encrypt("$ip	$time	$uid	$fingerprint	$auth_epoch", $tokenkey);
	
	// hook model_user_token_gen_end.php
	
	return $token;
}

function user_token_fingerprint() {
	$useragent = _SERVER('HTTP_USER_AGENT');
	return hash_hmac('sha256', $useragent, xn_key());
}

function user_token_cookie_set($value, $expires) {
	global $conf;
	$path = isset($conf['cookie_path']) ? $conf['cookie_path'] : '';
	return setcookie('bbs_token', $value, array(
		'expires'=>$expires,
		'path'=>$path,
		'secure'=>user_cookie_secure(),
		'httponly'=>TRUE,
		'samesite'=>'Lax',
	));
}

function user_cookie_secure() {
	$https = strtolower(_SERVER('HTTPS', 'off'));
	$proto = strtolower(_SERVER('HTTP_X_FORWARDED_PROTO', ''));
	return $https == 'on' || $https == '1' || $proto == 'https' || intval(_SERVER('SERVER_PORT', 0)) == 443;
}

// 登录、注册成功后的返回地址只能落在当前 Origin。绝对同源 URL 会收敛为站内路径，
// 避免前端再次按不同 URL 解析规则处理；协议相对、反斜杠和控制字符一律拒绝。
function user_return_url_normalize($url) {
	if(!is_string($url)) return './';
	$url = trim($url);
	if($url === '' || substr($url, 0, 2) === '//' || preg_match('~[\x00-\x20\x7f\\\\]~', $url)) return './';

	$parts = @parse_url($url);
	if($parts === FALSE || isset($parts['user']) || isset($parts['pass'])) return './';
	$has_authority = isset($parts['scheme']) || isset($parts['host']) || isset($parts['port']);
	if($has_authority) {
		$scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : '';
		$host = isset($parts['host']) ? strtolower($parts['host']) : '';
		if(!in_array($scheme, array('http', 'https'), TRUE) || $host === '') return './';

		$https = strtolower(isset($_SERVER['HTTPS']) ? (string)$_SERVER['HTTPS'] : 'off');
		$forwarded_proto = strtolower(isset($_SERVER['HTTP_X_FORWARDED_PROTO']) ? trim((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) : '');
		$server_port = isset($_SERVER['SERVER_PORT']) ? intval($_SERVER['SERVER_PORT']) : 0;
		$current_scheme = ($https === 'on' || $https === '1' || $forwarded_proto === 'https' || $server_port === 443) ? 'https' : 'http';
		$authority = @parse_url($current_scheme.'://'.(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : ''));
		$current_host = is_array($authority) && isset($authority['host']) ? strtolower($authority['host']) : '';
		$current_port = is_array($authority) && isset($authority['port']) ? intval($authority['port']) : ($current_scheme === 'https' ? 443 : 80);
		$port = isset($parts['port']) ? intval($parts['port']) : ($scheme === 'https' ? 443 : 80);
		if($current_host === '' || $scheme !== $current_scheme || $host !== $current_host || $port !== $current_port) return './';

		$url = isset($parts['path']) && $parts['path'] !== '' ? $parts['path'] : '/';
		isset($parts['query']) AND $url .= '?'.$parts['query'];
		isset($parts['fragment']) AND $url .= '#'.$parts['fragment'];
	}

	foreach(array('user-login.htm', 'user-logout.htm', 'user-create.htm', 'user-setpw.htm', 'user-resetpw_complete.htm') as $blocked_route) {
		if(stripos($url, $blocked_route) !== FALSE) return './';
	}
	return $url;
}


// 前台登录验证
function user_login_check() {
	global $user;
	
	// hook model_user_login_check_start.php
	
	empty($user) AND http_location(url('user-login'));
	
	// hook model_user_login_check_end.php
}

// 密码哈希：使用 bcrypt 生成密码哈希
function user_hash_password($password) {
	return password_hash($password, PASSWORD_BCRYPT);
}

// 密码校验：支持 bcrypt（新）和 md5+salt（旧）双模式
function user_verify_password($password, $user) {
	$hash = $user['password'];
	if (substr($hash, 0, 4) === '$2y$' || substr($hash, 0, 4) === '$2a$') {
		return password_verify($password, $hash);
	}
	return md5($password . $user['salt']) === $hash;
}

// 密码升级：将旧 MD5 哈希升级为 bcrypt 并写入数据库
function user_upgrade_password($uid, $password) {
	$user = user_login_credentials_refresh($uid, $password);
	return empty($user) || !isset($user['password']) ? FALSE : $user['password'];
}

// 检测密码是否需要升级（仍为旧 MD5 格式）
function user_password_needs_upgrade($user) {
	$hash = $user['password'];
	return substr($hash, 0, 4) !== '$2y$' && substr($hash, 0, 4) !== '$2a$';
}

// hook model_user_end.php

?>
