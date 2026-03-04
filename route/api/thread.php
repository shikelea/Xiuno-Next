<?php

!defined('DEBUG') AND exit('Access Denied.');

$action = param('action');

// 帖子列表
if($action == 'list') {
	
	$fid = param('fid', 0);
	$page = param('page', 1);
	$pagesize = param('pagesize', 20);
	
	// 参数校验
	if($pagesize > 100) $pagesize = 100;
	
	// 构建查询条件
	$cond = array();
	if($fid > 0) {
		$cond['fid'] = $fid;
		$forum = forum_read($fid);
		if(empty($forum)) api_output(-1, lang('forum_not_exists'));
		
		// 权限判断
		if($forum['accesson'] && !forum_access_user($fid, $gid, 'allowread')) {
			api_output(-1, lang('insufficient_privilege'));
		}
	}
	
	// 排序：默认按最后回复时间倒序
	$orderby = array('lastpid' => -1);
	
	// 获取帖子列表
	$threadlist = thread_find($cond, $orderby, $page, $pagesize);
	
	// 格式化数据 (移除敏感信息，增加额外字段)
	if($threadlist) {
		foreach($threadlist as &$thread) {
			$thread = thread_safe_info($thread);
			// 补充用户信息
			$thread['user'] = user_safe_info($thread['user']);
		}
	}
	
	$total = thread_count($cond);
	
	api_output(0, 'OK', array(
		'page' => $page,
		'pagesize' => $pagesize,
		'total' => $total,
		'list' => $threadlist
	));

} elseif($action == 'read') {
	
	$tid = param('tid', 0);
	$page = param('page', 1);
	$pagesize = param('pagesize', 20);
	
	if(empty($tid)) api_output(-1, lang('thread_not_exists'));
	
	$thread = thread_read($tid);
	if(empty($thread)) api_output(-1, lang('thread_not_exists'));
	
	$fid = $thread['fid'];
	$forum = forum_read($fid);
	
	// 权限判断
	if($forum['accesson'] && !forum_access_user($fid, $gid, 'allowread')) {
		api_output(-1, lang('insufficient_privilege'));
	}
	
	// 获取帖子内容 (第一楼)
	$postlist = post_find_by_tid($tid, $page, $pagesize);
	
	// 格式化
	if($postlist) {
		foreach($postlist as &$post) {
			$post = post_safe_info($post);
			$post['message'] = $post['message_fmt']; // 返回格式化后的 HTML
			unset($post['message_fmt']);
		}
	}
	
	// 增加点击数
	thread_inc_views($tid);
	
	api_output(0, 'OK', array(
		'thread' => thread_safe_info($thread),
		'posts' => $postlist
	));

} elseif($action == 'create') {
	
	// 校验登录
	if($uid == 0) api_output(-1, lang('please_login'));
	
	if($method != 'POST') api_output(-1, 'Method Not Allowed');
	
	$fid = param('fid', 0);
	$subject = param('subject');
	$message = param('message');
	$doctype = param('doctype', 0);
	
	if(empty($fid)) api_output(-1, lang('fid_is_empty'));
	if(empty($subject)) api_output(-1, lang('subject_is_empty'));
	if(empty($message)) api_output(-1, lang('message_is_empty'));
	
	$forum = forum_read($fid);
	if(empty($forum)) api_output(-1, lang('forum_not_exists'));
	
	// 权限校验
	if(!forum_access_user($fid, $gid, 'allowthread')) {
		api_output(-1, lang('insufficient_privilege'));
	}
	
	// 长度校验
	if(mb_strlen($subject, 'UTF-8') > 128) api_output(-1, lang('subject_too_long'));
	if(mb_strlen($message, 'UTF-8') > 2028000) api_output(-1, lang('message_too_long'));
	
	$thread = array(
		'fid' => $fid,
		'uid' => $uid,
		'subject' => $subject,
		'message' => $message,
		'time' => $time,
		'longip' => $longip,
		'doctype' => $doctype
	);
	
	$pid = 0;
	$tid = thread_create($thread, $pid);
	if($tid === FALSE) {
		api_output(-1, lang('create_thread_failed'));
	}
	
	$thread = thread_read($tid);
	api_output(0, lang('create_thread_sucessfully'), thread_safe_info($thread));

} else {
	api_output(-1, 'Unknown Action');
}

?>
