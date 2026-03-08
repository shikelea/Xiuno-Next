<?php

/**
 * 帖子收藏 函数文件
 */
 
function haya_favorite__find(
	$cond = array(), 
	$orderby = array(), 
	$page = 1, 
	$pagesize = 20
) {
	$favorites = db_find('haya_favorite', $cond, $orderby, $page, $pagesize);
	
	return $favorites;
}

function haya_favorite_create($arr) {
	$r = db_create('haya_favorite', $arr);
	return $r;
}

function haya_favorite_update($id, $arr) {
	$r = db_update('haya_favorite', array(
		'id' => $id
	), $arr);
	return $r;
}

function haya_favorite_maxid() {
	return db_maxid('haya_favorite', 'id');
}

function haya_favorite_count($cond = array()) {
	$n = db_count('haya_favorite', $cond);
	return $n;
}

function haya_favorite_find(
	$cond = array(), 
	$orderby = array(), 
	$page = 1, 
	$pagesize = 20
) {
	$favorites = db_find('haya_favorite', $cond, $orderby, $page, $pagesize);
	
	if (!empty($favorites)) {
		foreach ($favorites as & $favorite) {
			$favorite['favorite_uid'] = $favorite['uid'];
			$favorite['favorite_tid'] = $favorite['tid'];
			$favorite['favorite_create_date'] = $favorite['create_date'];
			$favorite['favorite_create_ip'] = $favorite['create_ip'];
			$thread = thread_read($favorite['tid']);
			$favorite = array_merge($favorite, $thread);
		}
	}	
	
	return $favorites;
}

function haya_favorite_find_by_uid_and_tid($uid, $tid) {
	$r = db_find('haya_favorite', array('uid'=>$uid,'tid'=>$tid));
	return empty($r) ? false : true;
}

function haya_favorite_find_by_tid($tid, $num = 20) {
	$haya_favorites = haya_favorite_find(array('tid' => $tid), array('create_date' => -1), 1, $num); 
	
	if (!empty($haya_favorites)) { 
		foreach($haya_favorites as & $haya_favorite){ 
			$haya_favorite['favorite_user'] = user_read($haya_favorite['favorite_uid']);
		}
	}
	
	return $haya_favorites;
}

function haya_favorite_find_tids_by_uid($uid, $pagesize = 10000) {
	$favorites = haya_favorite__find(array('uid' => $uid), array('create_date' => -1), 1, $pagesize, '', array('tid')); 	
	$tids = arrlist_values($favorites, 'tid');
	
	return $tids;
}

function haya_favorite_delete_by_tid($tid) {
	$r = db_delete('haya_favorite', array('tid' => $tid));
	return $r;
}

function haya_favorite_delete_by_uid($uid) {
	$r = db_delete('haya_favorite', array('uid' => $uid));
	return $r;
}

function haya_favorite_delete_by_tid_and_uid($tid, $uid) {
	$r = db_delete('haya_favorite', array('tid' => $tid, 'uid' => $uid));
	return $r;
}

// haya_favorites + 1
function haya_favorite_thread_user_favorites($tid, $n = 1) {	
	if ($n < 0) {
		$thread = thread__read($tid);
		if ($thread['favorites'] <= 0) {
			return true;
		}
	}

	$r = db_update('thread', array('tid' => $tid), array('favorites+' => $n));
	return $r;
}

// 依据帖子收藏数排序，过期时间 1天 = 24 * 60 * 60
function haya_favorite_hot_threads_base_user_favorite($pagesize = 10, $life_time = 86400, $find_time = 30) {
	$life_time = intval($life_time);
	$find_time = intval($find_time);
	
	if ($find_time <= 0) {
		$where = array('favorites' => array(">" => 0));
	} else {
		$where = array('favorites' => array(">" => 0), 'create_date' => array(">=" => time() - $find_time * 86400));
	}
	
	if ($life_time <= 0) {
		$favorites = thread__find($where, array('favorites' => -1), 1, $pagesize);
	} else {
		$favorites = cache_get('haya_favorite_favorites');

		if ($favorites === NULL) {
			$favorites = thread__find($where, array('favorites' => -1), 1, $pagesize);
		
			cache_set('haya_favorite_favorites', $favorites, $life_time);
		}	
	}
	
	return $favorites;	
}


?>
