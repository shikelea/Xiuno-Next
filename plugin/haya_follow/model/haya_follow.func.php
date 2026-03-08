<?php

function haya_follow__create($arr) {
	$r = db_create('follow', $arr);
	return $r;
}

function haya_follow__count($cond = array()) {
	$n = db_count('follow', $cond);
	return $n;
}

function haya_follow__find(
	$cond = array(), 
	$orderby = array(), 
	$page = 1, 
	$pagesize = 20
) {
	$follows = db_find('follow', $cond, $orderby, $page, $pagesize);	
	return $follows;
}

function haya_follow_create($arr) {
	$r = db_create('follow', $arr);
	return $r;
}

function haya_follow_update_by_uid($uid, $arr) {
	$r = db_update('follow', array('uid' => $uid), $arr);
	return $r;
}

function haya_follow_update_by_follow_uid($follow_uid, $arr) {
	$r = db_update('follow', array('follow_uid' => $follow_uid), $arr);
	return $r;
}

function haya_follow_update_by_uid_and_follow_uid($uid, $follow_uid, $arr) {
	$r = db_update('follow', array('uid' => $uid, 'follow_uid' => $follow_uid), $arr);
	return $r;
}

function haya_follow_count($cond = array()) {
	$n = db_count('follow', $cond);
	return $n;
}

function haya_follow_count_cache($cond = array(), $life_time = 86400) {
	$life_time = intval($life_time);
	
	if ($life_time <= 0) {
		$haya_follow_count = db_count('follow', $cond);
	} else {
		$haya_follow_key = md5('haya_follow_count_'.md5(serialize($cond)));
		
		$haya_follow_count = haya_follow_cache_get($haya_follow_key);
		if ($haya_follow_count === NULL) {
			$haya_follow_count = db_count('follow', $cond);
		
			haya_follow_cache_set($haya_follow_key, $haya_follow_count, $life_time);
		}	
	}

	return $haya_follow_count;
}

function haya_follow_find(
	$cond = array(), 
	$orderby = array(), 
	$page = 1, 
	$pagesize = 20
) {
	$follows = db_find('follow', $cond, $orderby, $page, $pagesize);
	
	if (!empty($follows)) {
		foreach ($follows as & $follow) {
			$follow['follow_user'] = user_read($follow['uid']);
			$follow['follower_user'] = user_read($follow['follow_uid']);
		}
	}	
	
	return $follows;
}

function haya_follow_find_by_follow($uid, $page = 1, $pagesize = 20) {
	$haya_follows = haya_follow_find(array('uid' => $uid), array('create_date' => -1), $page, $pagesize); 
	
	return $haya_follows;
}

function haya_follow_find_by_follower($follow_uid, $page = 1, $pagesize = 20) {
	$haya_followers = haya_follow_find(array('follow_uid' => $follow_uid), array('create_date' => -1), $page, $pagesize); 
	
	return $haya_followers;
}

function haya_follow_find_by_uid_and_follow_uid($uid, $follow_uid) {
	$r = db_find('follow', array('uid' => $uid, 'follow_uid' => $follow_uid));
	return empty($r) ? false : true;
}

function haya_follow_find_uids_by_follow_uid($follow_uid, $pagesize = 2000) {
	$follow_users = haya_follow_find(array('follow_uid' => $follow_uid, 'show_dynamic' => 1), array('create_date' => -1), 1, $pagesize, '', array('uid')); 	
	
	$follow_uids = arrlist_values($follow_users, 'uid');
	
	return $follow_uids;
}

function haya_follow_find_uids_by_follow_uid_cache($follow_uid, $pagesize = 2000, $life_time = 86400) {
	$life_time = intval($life_time);
	
	$haya_follow_cond = array('follow_uid' => $follow_uid, 'show_dynamic' => 1);
	
	if ($life_time <= 0) {
		$follow_users = haya_follow_find($haya_follow_cond, array('create_date' => -1), 1, $pagesize, '', array('uid')); 	
		$uids = arrlist_values($follow_users, 'uid');
	} else {
		$haya_follow_find_key = md5('haya_follow_find_'.md5(serialize($haya_follow_cond)));
		$uids = haya_follow_cache_get($haya_follow_find_key);

		if ($uids === NULL) {
			$follow_users = haya_follow_find($haya_follow_cond, array('create_date' => -1), 1, $pagesize, '', array('uid')); 	
			$uids = arrlist_values($follow_users, 'uid');

			haya_follow_cache_set($haya_follow_find_key, $uids, $life_time);
		}	
	}
	
	return $uids;
}

function haya_follow_find_follow_uids_by_uid($uid, $pagesize = 2000) {
	$follow_users = haya_follow_find(array('uid' => $uid), array('create_date' => -1), 1, $pagesize, '', array('follow_uid')); 	
	
	$follow_uids = arrlist_values($follow_users, 'follow_uid');
	
	return $follow_uids;
}

function haya_follow_delete_by_uid($uid) {
	$r = db_delete('follow', array('uid' => $uid));
	return $r;
}

function haya_follow_delete_by_follow_uid($follow_uid) {
	$r = db_delete('follow', array('follow_uid' => $follow_uid));
	return $r;
}

function haya_follow_delete_by_uid_and_follow_uid($uid, $follow_uid) {
	$r = db_delete('follow', array('uid' => $uid, 'follow_uid' => $follow_uid));
	return $r;
}

// 清除缓存
function haya_follow_clear_cache_by_follow_uid($follow_uid) {
	$haya_follow_cond = array('follow_uid' => $follow_uid, 'show_dynamic' => 1);
	
	$haya_follow_count_key = md5('haya_follow_count_'.md5(serialize($haya_follow_cond)));
	haya_follow_cache_delete($haya_follow_count_key);
	
	$haya_follow_find_key = md5('haya_follow_find_'.md5(serialize($haya_follow_cond)));
	haya_follow_cache_delete($haya_follow_find_key);

	return true;
}

// follows + 1
function haya_follow_user_update_follows_by_uid($uid, $n = 1) {
	$r = db_update('user', array('uid' => $uid), array('follows+' => $n));
	return $r;
}

// followeds + 1
function haya_follow_user_update_followeds_by_uid($uid, $n = 1) {
	$r = db_update('user', array('uid' => $uid), array('followeds+' => $n));
	return $r;
}

// 插件自定义缓存
$g_haya_follow_cache = FALSE;
function haya_follow_cache_get($k) {
	global $g_haya_follow_cache;
	$g_haya_follow_cache === FALSE AND $g_haya_follow_cache = cache_get('haya_follow');
	empty($g_haya_follow_cache) AND $g_haya_follow_cache = array();
	return array_value($g_haya_follow_cache, $k, NULL);
}

function haya_follow_cache_set($k, $v) {
	global $g_haya_follow_cache;
	$g_haya_follow_cache === FALSE AND $g_haya_follow_cache = cache_get('haya_follow');
	empty($g_haya_follow_cache) AND $g_haya_follow_cache = array();
	$g_haya_follow_cache[$k] = $v;
	return cache_set('haya_follow', $g_haya_follow_cache);
}

function haya_follow_cache_delete($k) {
	global $g_haya_follow_cache;
	$g_haya_follow_cache === FALSE AND $g_haya_follow_cache = cache_get('haya_follow');
	empty($g_haya_follow_cache) AND $g_haya_follow_cache = array();
	if(isset($g_haya_follow_cache[$k])) unset($g_haya_follow_cache[$k]);
	cache_set('haya_follow', $g_haya_follow_cache);
	return TRUE;
}

function haya_follow_humandate($timestamp, $lan = array()) {
	$time = $_SERVER['time'];
	$lang = $_SERVER['lang'];
	
	static $custom_humandate = NULL;
	if ($custom_humandate === NULL) {
		$custom_humandate = function_exists('custom_humandate');
	}
	if ($custom_humandate) {
		return custom_humandate($timestamp, $lan);
	}
	
	$seconds = $time - $timestamp;
	
	if (empty($lan)) {
		$lan = $lang;
	}
	$haya_lan = array(
		'yesterday' => '昨天 ',
		'today' => '今天 ',
		'hour_ago' => '小时前',
		'minute_ago' => '分钟前',
		'second_ago' => '秒前',
	);
	$lan = array_merge($haya_lan, $lan);
	
	if ($seconds > 43200) {
		if (date('Y-m-d', $timestamp) == date('Y-m-d')) {
			return $lan['today'].date('H:i:s', $timestamp);
		} elseif (date('Y-m-d', $timestamp) == date('Y-m-d', strtotime("-1 day"))) {
			return $lan['yesterday'].date('H:i:s', $timestamp);
		} elseif (date('Y', $timestamp) == date('Y')) {
			return date('m-d H:i:s', $timestamp);
		} else {
			return date('Y-m-d H:i:s', $timestamp);
		}
	} elseif($seconds > 3600) {
		return floor($seconds / 3600).$lan['hour_ago'];
	} elseif($seconds > 60) {
		return floor($seconds / 60).$lan['minute_ago'];
	} else {
		return $seconds.$lan['second_ago'];
	}
}

?>
