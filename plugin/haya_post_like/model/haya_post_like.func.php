<?php

function haya_post_like__find(
	$cond = array(), 
	$orderby = array(), 
	$page = 1, 
	$pagesize = 20
) {
	$post_likes = db_find('post_like', $cond, $orderby, $page, $pagesize);
	
	return $post_likes;
}

function haya_post_like_create($arr) {
	$r = db_create('post_like', $arr);
	return $r;
}

function haya_post_like_count($cond = array()) {
	$n = db_count('post_like', $cond);
	return $n;
}

function haya_post_like_read_by_uid_and_pid($uid, $pid) {
	$like = db_find_one('post_like', array('pid' => $pid, 'uid' => $uid));
	return $like;
}

function haya_post_like_find(
	$cond = array(), 
	$orderby = array(), 
	$page = 1, 
	$pagesize = 20
) {
	$post_likes = db_find('post_like', $cond, $orderby, $page, $pagesize);
	
	if (!empty($post_likes)) {
		foreach ($post_likes as & $post_like) {
			$post_like['post'] = post_read_cache($post_like['pid']);
			$post_like['user'] = user_read_cache($post_like['uid']);
		}
	}	
	
	return $post_likes;
}

function haya_post_like_find_by_uid_and_pid($uid, $pid) {
	$r = db_find('post_like', array('pid' => $pid, 'uid' => $uid));
	return $r;
}

function haya_post_like_find_by_uid_and_tid($uid, $tid, $num = 20) {
	$r = haya_post_like_find(array('tid' => $tid, 'uid' => $uid), array('create_date' => -1), 1, $num);
	return $r;
}

function haya_post_like_find_by_pid($pid, $num = 20) {
	$haya_post_likes = haya_post_like_find(array('pid' => $pid), array('create_date' => -1), 1, $num); 
	
	return $haya_post_likes;
}

function haya_post_like_find_by_pids($pids, $num = 1000) {
	if (!$pids) {
		return array();
	}

	$orderby = array('create_date' => -1);
	$r = db_find('post_like', array('pid' => $pids), $orderby, 1, $num, 'pid');
	return $r;
}

function haya_post_like_find_by_pids_and_uid($pids, $uid, $num = 1000) {
	if (!$pids) {
		return array();
	}

	$orderby = array('create_date' => -1);
	$r = db_find('post_like', array('pid' => $pids, 'uid' => $uid), $orderby, 1, $num, 'pid');
	return $r;
}

function haya_post_like_find_tids_by_uid($uid, $pagesize = 10000) {
	$post_likes = haya_post_like__find(array('uid' => $uid), array('create_date' => -1), 1, $pagesize, '', array('tid')); 	
	$tids = arrlist_values($post_likes, 'tid');
	
	return $tids;
}

function haya_post_like_delete_by_tid($tid) {
	$r = db_delete('post_like', array('tid' => $tid));
	return $r;
}

function haya_post_like_delete_by_pid($pid) {
	$r = db_delete('post_like', array('pid' => $pid));
	return $r;
}

function haya_post_like_delete_by_uid($uid) {
	$r = db_delete('post_like', array('uid' => $uid));
	return $r;
}

function haya_post_like_delete_by_pid_and_uid($pid, $uid) {
	$r = db_delete('post_like', array('pid' => $pid, 'uid' => $uid));
	return $r;
}

// likes + 1
function haya_post_like_loves($pid, $n = 1) {
	if ($n < 0) {
		$post = post__read($pid);
		if ($post['likes'] <= 0) {
			return true;
		}
	}
	
	$r = db_update('post', array('pid' => $pid), array('likes+' => $n));
	return $r;
}

function haya_post_like_find_hot_posts_by_tid($tid, $num = 5, $thumbup = 10) {
	$haya_post_likes = post_find(array('tid' => $tid, 'likes' => array(">=" => $thumbup)), array('likes' => -1, 'create_date' => -1), 1, $num); 
	
	return $haya_post_likes;
}

// 过期时间 1天 = 86400 = 24 * 60 * 60
function haya_post_like_find_hot_posts_by_tid_cache($tid, $num = 5, $thumbup = 10, $life_time = 86400) {
	$life_time = intval($life_time);

	$haya_post_like_hot_posts = array();
	if ($life_time <= 0) {
		$haya_post_like_hot_posts = post_find(array('tid' => $tid, 'likes' => array(">=" => $thumbup)), array('likes' => -1, 'create_date' => -1), 1, $num); 
	} else {
		$haya_post_likes = haya_post_like_cache_get($tid);

		$haya_post_like_time = time();
		if ($haya_post_likes === NULL
			|| $haya_post_like_time > $haya_post_likes['life_time']
		) {
			$haya_post_like_hot_loves = post__find(array('tid' => $tid, 'likes' => array(">=" => $thumbup)), array('likes' => -1, 'create_date' => -1), 1, $num); 
			$haya_post_like_hot_love_pids = array();
			
			if (!empty($haya_post_like_hot_loves)) {
				foreach ($haya_post_like_hot_loves as $_haya_post_like_hot_love) {
					$haya_post_like_hot_love_pids[] = $_haya_post_like_hot_love['pid'];
				}
			}
			
			$haya_post_likes = array(
				'pids' => $haya_post_like_hot_love_pids, 
				'life_time' => $haya_post_like_time + $life_time,
			);
			
			haya_post_like_cache_set($tid, $haya_post_likes);
		}
		
		$haya_post_like_hot_posts = array();
		if ( isset($haya_post_likes['pids']) && !empty($haya_post_likes['pids'])) {
			$haya_post_like_hot_posts = post_find(array('tid' => $tid, 'pid' => $haya_post_likes['pids']), array('likes' => -1, 'create_date' => -1), 1, $num); 
		}
	}
	
	return $haya_post_like_hot_posts;	
}

// 插件自定义缓存
$g_haya_post_like_cache = FALSE;
function haya_post_like_cache_get($k) {
	global $g_haya_post_like_cache;
	$g_haya_post_like_cache === FALSE AND $g_haya_post_like_cache = cache_get('haya_post_like');
	empty($g_haya_post_like_cache) AND $g_haya_post_like_cache = array();
	return array_value($g_haya_post_like_cache, $k, NULL);
}

function haya_post_like_cache_set($k, $v) {
	global $g_haya_post_like_cache;
	$g_haya_post_like_cache === FALSE AND $g_haya_post_like_cache = cache_get('haya_post_like');
	empty($g_haya_post_like_cache) AND $g_haya_post_like_cache = array();
	$g_haya_post_like_cache[$k] = $v;
	return cache_set('haya_post_like', $g_haya_post_like_cache);
}

function haya_post_like_cache_delete($k) {
	global $g_haya_post_like_cache;
	$g_haya_post_like_cache === FALSE AND $g_haya_post_like_cache = cache_get('haya_post_like');
	empty($g_haya_post_like_cache) AND $g_haya_post_like_cache = array();
	if(isset($g_haya_post_like_cache[$k])) unset($g_haya_post_like_cache[$k]);
	cache_set('haya_post_like', $g_haya_post_like_cache);
	return TRUE;
}

function haya_post_like_humandate($timestamp, $lan = array()) {
	$time = $_SERVER['time'];
	$lang = $_SERVER['lang'];
	
	static $custom_humandate = NULL;
	if ($custom_humandate === NULL) $custom_humandate = function_exists('custom_humandate');
	if ($custom_humandate) return custom_humandate($timestamp, $lan);
	
	$seconds = $time - $timestamp;
	$lan = empty($lang) ? $lan : $lang;
	$haya_lan = array(
		'today' => '今天 ',
		'hour_ago' => '小时前',
		'minute_ago' => '分钟前',
		'second_ago' => '秒前',
	);
	$lan = array_merge($haya_lan, $lan);
	
	if ($seconds > 43200) {
		if (date('Y-m-d', $timestamp) == date('Y-m-d')) {
			return $lan['today'].date('H:i', $timestamp);
		} elseif (date('Y', $timestamp) == date('Y')) {
			return date('m-d H:i', $timestamp);
		} else {
			return date('Y-m-d H:i', $timestamp);
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
