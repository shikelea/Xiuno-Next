<?php

!defined('DEBUG') AND exit('Access Denied.');

$action = param(2);

// 回帖
if($action == 'create') {
	
	api_method_required('POST');
	api_login_required();
	
	$tid = param('tid', 0);
	$message = param('message', '', FALSE);
	$doctype = api_post_doctype();
	$quotepid = param('quotepid', 0);
	
	if(empty($tid)) api_output(-1, lang('thread_not_exists'), array(), 404);
	if(empty($message)) api_output(-1, lang('message_is_empty'), array(), 422);
	
	$thread = thread_read($tid);
	if(empty($thread)) api_output(-1, lang('thread_not_exists'), array(), 404);
	
	$fid = $thread['fid'];
	$forum = forum_read($fid);
	
	// 权限校验
	if(!forum_access_user($fid, $gid, 'allowpost')) {
		api_output(-1, lang('insufficient_privilege'), array(), 403);
	}
	
	// 帖子锁定校验
	if($thread['closed'] > 0) {
		api_output(-1, lang('thread_has_closed'), array(), 409);
	}

	if(!empty($quotepid)) {
		$quotepost = post__read($quotepid);
		if(empty($quotepost) || $quotepost['tid'] != $tid) $quotepid = 0;
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
		api_output(-1, lang('create_post_failed'), array(), 500);
	}
	
	$post = post_read($pid);
	$post['message'] = $post['message_fmt'];
	unset($post['message_fmt']);
	
	api_output(0, lang('create_post_sucessfully'), post_safe_info($post));

} else {
	api_output(-1, 'Unknown Action', array(), 404);
}

?>
