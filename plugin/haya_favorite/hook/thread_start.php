<?php
exit;

if ($action == 'favorite') {

	$header['title'] = lang('haya_favorite') . " - " . $conf['sitename'];

	$haya_favorite_config = setting_get('haya_favorite');
	
	if ($method == 'POST') {

		$tid = param('tid');

		$thread = thread_read($tid);
		empty($thread) AND message(0, lang('thread_not_exists'));
		$haya_check_favorite = haya_favorite_find_by_uid_and_tid($uid, $tid);
		
		$haya_favorite_user_favorite_count = isset($haya_favorite_config['user_favorite_count']) ? intval($haya_favorite_config['user_favorite_count']) : 20;
		
		$haya_favorite_users = haya_favorite_find_by_tid($tid, $haya_favorite_user_favorite_count);
		
		ob_start();
		include _include(APP_PATH.'plugin/haya_favorite/view/htm/my_favorite_users.htm');	
		$haya_favorite_user_html = ob_get_clean();

		message(1, $haya_favorite_user_html);
	}
	
	message(1, lang('haya_favorite_error'));

} else


?>