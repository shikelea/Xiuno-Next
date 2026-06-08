<?php


// 只能在当前 request 生命周期缓存，要跨进程，可以再加一层缓存： memcached/xcache/apc/
$g_static_users = array(); // 变量缓存

// hook model_user_start.php

// ------------> 最原生的 CURD，无关联其他数据。

function user__create($arr) {
	// hook model_user__create_start.php
	$r = db_insert('user', $arr);
	// hook model_user__create_end.php
	return $r;
}

function user__update($uid, $update) {
	// hook model_user__update_start.php
	$r = db_update('user', array('uid'=>$uid), $update);
	// hook model_user__update_end.php
	return $r;
}

function user__read($uid) {
	// hook model_user__read_start.php
	$user = db_find_one('user', array('uid'=>$uid));
	// hook model_user__read_end.php
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

function user_update($uid, $arr) {
	// hook model_user_update_start.php
	global $conf, $g_static_users;
	$r = user__update($uid, $arr);
	$conf['cache']['type'] != 'mysql' AND cache_delete("user-$uid");
	isset($g_static_users[$uid]) AND $g_static_users[$uid] = array_merge($g_static_users[$uid], $arr);
	
	// hook model_user_update_end.php
	return $r;
}

function user_read($uid) {
	global $g_static_users;
	if(empty($uid)) return array();
	$uid = intval($uid);
	// hook model_user_read_start.php
	$user = user__read($uid);
	user_format($user);
	$g_static_users[$uid] = $user;
	// hook model_user_read_end.php
	return $user;
}


// 从缓存中读取，避免重复从数据库取数据，主要用来前端显示，可能有延迟。重要业务逻辑不要调用此函数，数据可能不准确，因为并没有清理缓存，针对 request 生命周期有效。
function user_read_cache($uid) {
	global $conf, $g_static_users;
	if(isset($g_static_users[$uid])) return $g_static_users[$uid];
	
	// hook model_user_read_cache_start.php
	
	// 游客
	if($uid == 0) return user_guest();
	
	if($conf['cache']['type'] != 'mysql') {
		$r = cache_get("user-$uid");
		if($r === NULL) {
			$r = user_read($uid);
			cache_set("user-$uid", $r);
		}
	} else {
		$r = user_read($uid);
	}
	
	$g_static_users[$uid] = $r ? $r : user_guest();
	
	// hook model_user_read_cache_end.php
	return $g_static_users[$uid];
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

function user_read_by_email($email) {
	global $g_static_users;
	// hook model_user_read_by_email_start.php
	$user = db_find_one('user', array('email'=>$email));
	if(empty($user)) return array();
	user_format($user);
	$g_static_users[$user['uid']] = $user;
	// hook model_user_read_by_email_end.php
	return $user;
}

function user_read_by_username($username) {
	global $g_static_users;
	// hook model_user_read_by_username_start.php
	$user = db_find_one('user', array('username'=>$username));
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
	$record = cache_get($key);
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
	
	$user['create_ip_fmt']   = long2ip($user['create_ip']);
	$user['create_date_fmt'] = empty($user['create_date']) ? '0000-00-00' : date('Y-m-d', $user['create_date']);
	$user['login_ip_fmt']    = long2ip($user['login_ip']);
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
				user_update($uid, array('gid'=>$group['gid']));
				return TRUE;
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
	// hook model_user_safe_info_end.php
	return $user;
}


// 用户
function user_token_get() {
	global $time, $conf;
	$_uid = user_token_get_do();
	
	// hook model_user_token_get_start.php
	
	if(!$_uid) {
		user_token_cookie_set('', $time - 86400);
	}
	
	// hook model_user_token_get_end.php
	
	return $_uid;
}

// 用户
function user_token_get_do() {
	global $time, $ip, $conf;
	$token = param('bbs_token');
	
	// hook model_user_token_get_do_start.php
	
	if(empty($token)) return FALSE;
	$tokenkey = hash('sha256', xn_key());
	$s = xn_decrypt($token, $tokenkey);
	if(empty($s)) return FALSE;
	$arr = explode("\t", $s);
	if(count($arr) != 3 && count($arr) != 4) return FALSE;
	$_fingerprint = '';
	list($_ip, $_time, $_uid) = array_slice($arr, 0, 3);
	if(count($arr) == 4) $_fingerprint = $arr[3];
	// Token 有效期 30 天
	if($time - intval($_time) > 86400 * 30) return FALSE;
	if($_fingerprint !== '' && !hash_equals(user_token_fingerprint(), $_fingerprint)) return FALSE;
	$_uid = intval($_uid);
	if($_uid <= 0) return FALSE;

	// hook model_user_token_get_do_end.php

	return $_uid;
}

// 设置 token，防止 sid 过期后被删除
function user_token_set($uid) {
	global $time, $conf;
	if(empty($uid)) return;
	$token = user_token_gen($uid);
	user_token_cookie_set($token, $time + 8640000);
	
	// hook model_user_token_set_end.php
}

function user_token_clear() {
	global $time, $conf;
	user_token_cookie_set('', $time - 8640000);
	
	// hook model_user_token_clear_end.php
}

function user_token_gen($uid) {
	global $ip, $time, $conf;
	
	// hook model_user_token_gen_start.php
	
	$tokenkey = hash('sha256', xn_key());
	$fingerprint = user_token_fingerprint();
	$token = xn_encrypt("$ip	$time	$uid	$fingerprint", $tokenkey);
	
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
	setcookie('bbs_token', $value, array(
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
	$new_hash = user_hash_password($password);
	user_update($uid, array('password' => $new_hash));
	return $new_hash;
}

// 检测密码是否需要升级（仍为旧 MD5 格式）
function user_password_needs_upgrade($user) {
	$hash = $user['password'];
	return substr($hash, 0, 4) !== '$2y$' && substr($hash, 0, 4) !== '$2a$';
}

// hook model_user_end.php

?>
