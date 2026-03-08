<?php
exit;

if (function_exists("notice_send")) {
	
	// hook plugin_haya_favorite_notice_send_before.php
	
	$thread = thread_read($thread['tid']);
	$thread['subject'] = notice_substr($thread['subject'], 20);
	
	$notice_thread_subject = $thread['subject'];
	$notice_thread_substr_subject = htmlspecialchars(strip_tags($thread['subject']));
	$notice_thread_substr_subject = notice_substr($notice_thread_substr_subject, 20);
	$notice_thread_url = url('thread-'.$thread['tid']);
	$notice_thread = '<a target="_blank" href="'.$notice_thread_url.'">《'.$notice_thread_subject.'》</a>';
	
	$notice_user_url = url('user-'.$user['uid']);
	$notice_user_avatar_url = $user['avatar_url'];
	$notice_user_username = $user['username'];
	$notice_user = '<a href="'.$notice_user_url.'" target="_blank"><img class="avatar-1" src="'.$notice_user_avatar_url.'"> '.$notice_user_username.'</a>';
	
	// hook plugin_haya_favorite_notice_send.php
	
	$notice_msg = str_replace(
		array(
			'{thread_subject}', '{thread_substr_subject}', '{thread_url}', '{thread}', 
			'{user_url}', '{user_avatar_url}', '{user_username}', '{user}'
		),
		array(
			$notice_thread_subject, $notice_thread_substr_subject, $notice_thread_url, $notice_thread, 
			$notice_user_url, $notice_user_avatar_url, $notice_user_username, $notice_user
		),
		lang('haya_favorite_send_notice_for_thread')
	);
	notice_send($user['uid'], $thread['uid'], $notice_msg, 155);

	// hook plugin_haya_favorite_notice_send_end.php				
}

?>