<?php

!defined('DEBUG') AND exit('Access Denied.');

$action = param(1);

// hook admin_thread_start.php

$pagesize = 100;

if(empty($action) || $action == 'list') {

	$header['title'] = lang('thread_admin');
	$header['mobile_title'] = lang('thread_admin');
		
	// hook admin_thread_list_start.php
	
	// ajax 扫描全表
	$threads = $runtime['threads'];
	$page = 1; // 从第一页开始
	$totalpage = ceil($threads / $pagesize);
	
	$queueid = thread_queue_create();

	$forumlist_simple = array();
	foreach($forumlist as $k=>$v) {
		$forumlist_simple[$k] = array(
			'name'=>$v['name'],
			'threads'=>$v['threads'],
		);
	}
	
	//$queue_count = queue_count($queueid);
	
	// hook admin_thread_list_end.php
	
	include _include(ADMIN_PATH."view/htm/thread_list.htm");
	
// 全表扫描，每次扫描 1000 条记录
/*
	搜索条件，并且关系：
	create_date (start, end) 
	last_date (start, end) 
	fid = 
	uid =
	userip =
	views (start, end)
	subject like '%keyword%'
*/
} elseif($action == 'scan') {
	
	$method != 'POST' AND message(-1, 'Method Not Allowed');

	$queueid = thread_queue_require(param('queueid', 0));
	
	$fid = param('fid', 0);
	$cond = array();
	$cond['fid'] = $fid;
	$cond['create_date_start'] = strtotime(param('create_date_start'));
	$cond['create_date_end'] = strtotime(param('create_date_end'));
	$cond['uid'] = param('uid', 0);
	$userip = param('userip');
	$cond['userip'] = $userip ? ip2long($userip) : 0;
	$cond['keyword'] = param('keyword');
	$cond['page'] = param('page', 1);
	
	$page = $cond['page'];
	$threads = $cond['fid'] ? $forumlist[$fid]['threads'] : $runtime['threads'];
	$totalpage = ceil($threads / $pagesize);
	
	// hook admin_thread_scan_start.php
	$threadlist = thread_find_by_fid($fid, $page, $pagesize);
	
	if($page == 1) queue_destory($queueid);
	
	$tids = array();
	// 查找到的数据存到 cache，并且返回
	foreach($threadlist as $thread) {
		
		if($cond['fid'] && $thread['fid'] != $cond['fid']) continue; 
		if($cond['create_date_start'] && $thread['create_date'] < $cond['create_date_start']) continue; 
		if($cond['create_date_end'] && $thread['create_date'] > $cond['create_date_end']) continue; 
		if($cond['uid'] && $thread['uid'] != $cond['uid']) continue; 
		if($cond['userip'] && $thread['userip'] != $cond['userip']) continue; 
		//if($cond['views_start'] && $thread['views'] > $cond['views_start']) continue; 
		//if($cond['views_end'] && $thread['views'] > $cond['views_end']) continue; 
		//if($cond['posts_start'] && $thread['posts'] > $cond['posts_start']) continue; 
		//if($cond['posts_end'] && $thread['posts'] > $cond['posts_end']) continue; 
		if($cond['keyword'] && stripos($thread['subject'], $cond['keyword']) === FALSE) continue; 
		
		// hook admin_thread_scan_for.php
		
		$tids[] = $thread['tid'];
		queue_push($queueid, $thread['tid'], 86400);
	}
	
	// hook admin_thread_scan_end.php
	message(0, $tids);
	
// 操作
} elseif($action == 'operation') {
		
	$method != 'POST' AND message(-1, 'Method Not Allowed');

	$queueid = thread_queue_require(param('queueid', 0));
	
	$op = param(2);
	!in_array($op, array('delete', 'close', 'open'), TRUE) AND message(-1, 'Operation Not Allowed');
	$tids = array();
	// hook admin_thread_operation_start.php
	for($i = 0; $i <= $pagesize; $i++) {
		$tid = queue_pop($queueid);
		if(!$tid) {
			thread_queue_destroy($queueid);
			break;
			//message(0, '删除全部完成');
		}
		if($op == 'delete') {
			thread_delete($tid);
		} elseif($op == 'close') {
			thread_update($tid, array('closed'=>1));
		} elseif($op == 'open') {
			thread_update($tid, array('closed'=>0));
		}
		// hook admin_thread_operation_for.php
		$tids[] = $tid;
	}
	// hook admin_thread_operation_end.php
	message(0, $tids);
	
// 操作
} elseif($action == 'found') {	

	$queueid = thread_queue_require(param(2, 0));
	
	$page = param(3, 1);
	$total = queue_count($queueid);
	$pagination = pagination(url("thread-found-$queueid-{page}"), $total, $page, $pagesize);
	// hook admin_thread_found_start.php
	$tids = queue_find($queueid, $page, $pagesize);
	$threadlist = thread_find_by_tids($tids);
	
	// hook admin_thread_found_end.php
	include _include(ADMIN_PATH."view/htm/thread_found.htm");
}

function thread_queue_create() {
	global $time;
	if(empty($_SESSION['thread_find_queueids']) || !is_array($_SESSION['thread_find_queueids'])) {
		$_SESSION['thread_find_queueids'] = array();
	}
	$queueid = 0;
	for($i = 0; $i < 10; $i++) {
		$queueid = mt_rand(1, 2147483647);
		if(!isset($_SESSION['thread_find_queueids'][$queueid])) break;
	}
	$_SESSION['thread_find_queueids'][$queueid] = $time;
	$_SESSION['thread_find_queueid'] = $queueid;
	return $queueid;
}

function thread_queue_require($queueid) {
	$queueid = intval($queueid);
	$queueids = isset($_SESSION['thread_find_queueids']) && is_array($_SESSION['thread_find_queueids']) ? $_SESSION['thread_find_queueids'] : array();
	if(empty($queueid) || !isset($queueids[$queueid])) {
		message(-1, lang('thread_queue_not_exists'));
	}
	return $queueid;
}

function thread_queue_destroy($queueid) {
	$queueid = intval($queueid);
	if(empty($queueid)) return;
	queue_destory($queueid);
	if(isset($_SESSION['thread_find_queueids']) && is_array($_SESSION['thread_find_queueids'])) {
		unset($_SESSION['thread_find_queueids'][$queueid]);
	}
	if(isset($_SESSION['thread_find_queueid']) && $_SESSION['thread_find_queueid'] == $queueid) {
		unset($_SESSION['thread_find_queueid']);
	}
}

// hook admin_thread_start.php

?>
