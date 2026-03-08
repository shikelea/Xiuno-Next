<?php
exit;

if ($action == 'follow') {
	$action2 = param(2, 'follows');
	
	$_uid = param(3, 0);
	$_user = user_read($_uid);
	empty($_user) AND message(-1, lang('user_not_exists'));

	$header['title'] = $_user['username'] . "的关注";

	$haya_follow_config = setting_get('haya_follow');

	if ($action2 == 'follows') {		
		$pagesize = intval($haya_follow_config['follow_user_pagesize']);
		$page = param(4, 1);

		$haya_follow_count = haya_follow_count(array('follow_uid' => $_uid));
		$haya_follow_follows = haya_follow_find(array('follow_uid' => $_uid), array('create_date' => -1), $page, $pagesize);
		$haya_follow_pagination = pagination(url("user-follows-{page}"), $haya_follow_count, $page, $pagesize);
		
		include _include(APP_PATH.'plugin/haya_follow/view/htm/user_follow_follows.htm');	
	} elseif ($action2 == 'fans') {		
		$pagesize = intval($haya_follow_config['follow_user_pagesize']);
		$page = param(4, 1);

		$haya_follow_count = haya_follow_count(array('uid' => $_uid));
		$haya_follow_followeds = haya_follow_find(array('uid' => $_uid), array('create_date' => -1), $page, $pagesize);
		$haya_follow_pagination = pagination(url("user-fans-{page}"), $haya_follow_count, $page, $pagesize);

		include _include(APP_PATH.'plugin/haya_follow/view/htm/user_follow_fans.htm');	
	}

} else 
	
?>