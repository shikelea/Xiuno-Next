<?php
exit;

if (function_exists("notice_send")) {
	// hook plugin_haya_post_like_notice_send_start.php
	
	$notice_user = '<a href="'.url('user-'.$user['uid']).'" target="_blank"><img class="avatar-1" src="'.$user['avatar_url'].'"> '.$user['username'].'</a>';

	$thread = thread_read($post['tid']);
	$thread['subject'] = notice_substr($thread['subject'], 20);
	$notice_thread = '<a target="_blank" href="'.url('thread-'.$post['tid']).'">《'.$thread['subject'].'》</a>';

	$post['message'] = htmlspecialchars(strip_tags($post['message']));
	$post['message'] = notice_substr($post['message'], 40);
	$notice_post = '<a target="_blank" href="'.url('thread-'.$post['tid'].'-1').'#'.$post['pid'].'">【'.$post['message'].'】</a>';
	
	if ($post['isfirst'] == 1) {
		$notice_msg_tpl = lang('haya_post_like_send_notice_for_thread');
		
		// hook plugin_haya_post_like_notice_send_thread.php
	} else {
		$notice_msg_tpl = lang('haya_post_like_send_notice_for_post');
		
		// hook plugin_haya_post_like_notice_send_post.php
	}
	
	$notice_msg = str_replace(
		array('{thread}', '{post}', '{user}'),
		array($notice_thread, $notice_post, $notice_user),
		$notice_msg_tpl
	);

	notice_send($user['uid'], $post['uid'], $notice_msg, 150);
	
	// hook plugin_haya_post_like_notice_send_end.php
}

?>