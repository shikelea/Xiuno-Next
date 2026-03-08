<?php
exit;

$haya_follow_config = setting_get('haya_follow');

if ($action == 'follow') {
	$action2 = param(2, 'follows');

	$_uid = param(3, 0);
	$_user = user_read($_uid);
	empty($_user) and message(-1, lang('user_not_exists'));

	$header['title'] = $_user['username'] . "的关注";


	if ($action2 == 'follows') {
		$pagesize = intval($haya_follow_config['follow_user_pagesize']);
		$page = param(4, 1);

		$haya_follow_count = haya_follow_count(array('follow_uid' => $_uid));
		$haya_follow_follows = haya_follow_find(array('follow_uid' => $_uid), array('create_date' => -1), $page, $pagesize);
		$haya_follow_pagination = pagination(url("user-follows-{page}"), $haya_follow_count, $page, $pagesize);

		include _include(APP_PATH . 'plugin/haya_follow/view/htm/user_follow_follows.htm');
	} elseif ($action2 == 'fans') {
		$pagesize = intval($haya_follow_config['follow_user_pagesize']);
		$page = param(4, 1);

		$haya_follow_count = haya_follow_count(array('uid' => $_uid));
		$haya_follow_followeds = haya_follow_find(array('uid' => $_uid), array('create_date' => -1), $page, $pagesize);
		$haya_follow_pagination = pagination(url("user-fans-{page}"), $haya_follow_count, $page, $pagesize);

		include _include(APP_PATH . 'plugin/haya_follow/view/htm/user_follow_fans.htm');
	} elseif ($action2 == 'timeline') {
		if (
			isset($haya_follow_config['show_user_dynamic'])
			&& $haya_follow_config['show_user_dynamic'] == 1
		) {
			$haya_follow_dynamic_page = param(4, 1);
			$haya_follow_dynamic_post_pagesize = intval($haya_follow_config['user_dynamic_pagesize']);
			$haya_follow_user_post_count = post_count(array('uid' => $_uid));
			$haya_follow_user_post_list = post_find(array('uid' => $_uid), array('pid' => -1), $haya_follow_dynamic_page, $haya_follow_dynamic_post_pagesize);
			$haya_follow_dynamic_pagination = pagination(url("user-{$_uid}-{page}"), $haya_follow_user_post_count, $haya_follow_dynamic_page, $haya_follow_dynamic_post_pagesize);

			if (!empty($haya_follow_user_post_list)) {
				foreach ($haya_follow_user_post_list as &$haya_follow_user_post) {
					$haya_follow_user_post['thread'] = thread_read_cache($haya_follow_user_post['tid']);
				}
			}
			include _include(APP_PATH . 'plugin/abs_theme_aether/template_parts_plugin/haya_follow/timeline_user.htm');
		} else {
			message(-1, lang('user_dynamic_not_exists'));
		}
	} else {
		message(-1, lang('action_not_exists'));
	}
}
