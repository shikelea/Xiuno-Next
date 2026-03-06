<?php

!defined('DEBUG') AND exit('Access Denied.');

$action = param(2);

// 回帖
if($action == 'create') {
	
	if($method != 'POST') api_output(-1, 'Method Not Allowed');
	if($uid == 0) api_output(-1, lang('please_login'));
	
	$tid = param('tid', 0);
	$message = param('message');
	$doctype = param('doctype', 0);
	$quotepid = param('quotepid', 0);
	
	if(empty($tid)) api_output(-1, lang('thread_not_exists'));
	if(empty($message)) api_output(-1, lang('message_is_empty'));
	
	$thread = thread_read($tid);
	if(empty($thread)) api_output(-1, lang('thread_not_exists'));
	
	$fid = $thread['fid'];
	$forum = forum_read($fid);
	
	// 权限校验
	if(!forum_access_user($fid, $gid, 'allowpost')) {
		api_output(-1, lang('insufficient_privilege'));
	}
	
	// 帖子锁定校验
	if($thread['closed'] > 0) {
		api_output(-1, lang('thread_has_closed'));
	}
	
	$post = array(
		'tid' => $tid,
		'uid' => $uid,
		'create_date' => $time,
		'userip' => $longip,
		'message' => $message,
		'doctype' => $doctype,
		'quotepid' => $quotepid,
	);
	
	$pid = post_create($post, $fid, $gid);
	if(empty($pid)) {
		api_output(-1, lang('create_post_failed'));
	}
	
	$post = post_read($pid);
	$post['message'] = $post['message_fmt'];
	unset($post['message_fmt']);
	
	api_output(0, lang('create_post_sucessfully'), post_safe_info($post));

} else {
	api_output(-1, 'Unknown Action');
}

?>
