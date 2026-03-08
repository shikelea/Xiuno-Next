<?php
exit;

elseif ($action == 'post_like') {
	
	if (isset($haya_post_like_config['open_my_post_like'])
		&& $haya_post_like_config['open_my_post_like'] != 1
	) {
		message(-1, lang('haya_post_like_my_no_post_like_tip'));
	}

	$pagesize = intval($haya_post_like_config['my_post_like_pagesize']);
	$page = param(2, 1);
	$cond['uid'] = $uid; 
	
	$haya_post_like_count = haya_post_like_count($cond);
	$haya_post_like_post_list = haya_post_like_find($cond, array('create_date' => -1), $page, $pagesize);
	if (!empty($haya_post_like_post_list)) {
		foreach ($haya_post_like_post_list as & $haya_post_like_post_value) {
			$haya_post_like_post_value['thread'] = thread_read_cache($haya_post_like_post_value['tid']);
		}
	}
	
	$pagination = pagination(url("my-post_like-{page}"), $haya_post_like_count, $page, $pagesize);
	
	include _include(APP_PATH.'plugin/haya_post_like/view/htm/my_post_like.htm');
}



?>