<?php

!defined('DEBUG') and exit('Access Denied.');

if (empty($user)) {
	message(1, '登录后才可以关注！');
}



// 被关注用户
$_uid = param('uid', '');
if (empty($_uid)) {
	message(1, '关注失败，请确认后重试！');
}

if ($_uid == $uid) {
	message(1, '你不能关注你自己！');
}

$follow_user = user_read($_uid);
if (empty($follow_user)) {
	message(1, '所关注的用户不存在！');
}

$haya_follow_check_user = haya_follow_find_by_uid_and_follow_uid($_uid, $uid);

$haya_follow_config = setting_get('haya_follow');

$action = param(1);

// hook plugin_haya_follow_follow_start.php

if ($action == 'create') {
	if ($method == 'GET') {
		message(-1, 'Bad Request!');
	}

	$available_positions = [
		'thread_update_before',
		'thread_user_username_after',
		'user_profile_header_right',
	];

	if (param('tid', 0) !== 0) {
		$thread = thread_read_cache(param('tid', 0));
	}
	$_user = user_read_cache(param('uid', 0));

	// hook plugin_haya_follow_follow_create_start.php

	if (!empty($haya_follow_check_user)) {
		message(1, '你已经关注过了！');
	}

	$haya_follow_status = 1;
	$haya_follow_check_follow_me = haya_follow_find_by_uid_and_follow_uid($uid, $_uid);
	if (!empty($haya_follow_check_follow_me)) {
		$haya_follow_status = 2;
		haya_follow_update_by_uid_and_follow_uid($uid, $_uid, array("status" => 2));
	}

	$create_status = haya_follow_create(array(
		'uid' => $_uid,
		'follow_uid' => $user['uid'],
		'status' => $haya_follow_status,
		'create_date' => time(),
		'create_ip' => $longip,
	));
	if ($create_status === false) {
		message(1, '关注失败！');
	}

	haya_follow_user_update_follows_by_uid($user['uid'], 1);
	haya_follow_user_update_followeds_by_uid($_uid, 1);

	// 清空缓存
	haya_follow_clear_cache_by_follow_uid($uid);

	// hook plugin_haya_follow_follow_create_end.php

	if ($IS_HTMX) {

		$position = in_array(param('from', ''), $available_positions) ? param('from', '') : 'thread_update_before';
		header('HX-Trigger-After-Settle: ' . json_encode([
			'showSnackBar' => [
				'type'    => 'success',
				'title'   => lang('tips_title'),
				'subtitle' => '',
				'content' => '关注成功！',
				'delay'   => 3000
			]
		], JSON_FORCE_OBJECT));
		include _include(APP_PATH . 'plugin/abs_theme_aether/template_parts_plugin/haya_follow/btn_follow__' . $position . '.htm');
		die;
	} else {
		message(0, '关注成功！');
	}
} elseif ($action == 'delete') {
	if ($method == 'GET') {
		message(-1, 'Bad Request!');
	}
	$available_positions = [
		'thread_update_before',
		'thread_user_username_after',
		'user_profile_header_right',
	];

	if (param('tid', 0) !== 0) {
		$thread = thread_read_cache(param('tid', 0));
	}
	$_user = user_read_cache(param('uid', 0));

	// hook plugin_haya_follow_follow_delete_start.php

	if (empty($haya_follow_check_user)) {
		message(1, '你还没有关注过Ta！');
	}

	$delete_status = haya_follow_delete_by_uid_and_follow_uid($_uid, $uid);
	if ($delete_status === false) {
		message(1, '取消关注失败！');
	}

	haya_follow_user_update_follows_by_uid($user['uid'], -1);
	haya_follow_user_update_followeds_by_uid($_uid, -1);

	$haya_follow_check_follow_me = haya_follow_find_by_uid_and_follow_uid($uid, $_uid);
	if (!empty($haya_follow_check_follow_me)) {
		haya_follow_update_by_uid_and_follow_uid($uid, $_uid, array("status" => 1));
	}

	// 清空缓存
	haya_follow_clear_cache_by_follow_uid($uid);

	// hook plugin_haya_follow_follow_delete_end.php

	if ($IS_HTMX) {

		$position = in_array(param('from', ''), $available_positions) ? param('from', '') : 'thread_update_before';
		header('HX-Trigger-After-Settle: ' . json_encode([
			'showSnackBar' => [
				'type'    => 'success',
				'title'   => lang('tips_title'),
				'subtitle' => '',
				'content' => '取消关注成功！',
				'delay'   => 3000
			]
		], JSON_FORCE_OBJECT));
		include _include(APP_PATH . 'plugin/abs_theme_aether/template_parts_plugin/haya_follow/btn_follow__' . $position . '.htm');
		die;
	} else {
		message(0, '取消关注成功！');
	}
} elseif ($action == 'remove') {
	if ($method == 'GET') {
		message(-1, 'Bad Request!');
	}
	// hook plugin_haya_follow_follow_remove_start.php

	$haya_follow_check_follow_me = haya_follow_find_by_uid_and_follow_uid($uid, $_uid);

	if (empty($haya_follow_check_follow_me)) {
		message(1, 'Ta还没有关注过你！');
	}

	if ($haya_follow_config['delete_follower'] != 1) {
		message(1, '你不能移除关注你的用户！');
	}

	$remove_status = haya_follow_delete_by_uid_and_follow_uid($uid, $_uid);
	if ($remove_status === false) {
		message(1, '移除关注我的失败！');
	}

	haya_follow_user_update_follows_by_uid($_uid, -1);
	haya_follow_user_update_followeds_by_uid($user['uid'], -1);

	$haya_follow_check_my_follow = haya_follow_find_by_uid_and_follow_uid($_uid, $uid);
	if (!empty($haya_follow_check_my_follow)) {
		haya_follow_update_by_uid_and_follow_uid($_uid, $uid, array("status" => 1));
	}

	// 清空缓存
	haya_follow_clear_cache_by_follow_uid($uid);
	haya_follow_clear_cache_by_follow_uid($_uid);

	// hook plugin_haya_follow_follow_remove_end.php

	message(0, '移除关注我的成功！');
} elseif ($action == 'remarks') {
	if ($method == 'GET') {
		$haya_follow_user = user_read_cache($_uid);

		include _include(APP_PATH . 'plugin/abs_theme_aether/template_parts_plugin/haya_follow/form_change_remarks.htm');
	} else {

		// hook plugin_haya_follow_follow_remarks_start.php

		if (empty($haya_follow_check_user)) {
			message(1, '你还没有关注过Ta！');
		}

		$follow_comment = param('remarks', '');

		haya_follow_update_by_uid($_uid, array('comment' => $follow_comment));

		// hook plugin_haya_follow_follow_remarks_end.php

		if ($IS_HTMX) {
			$MESSAGE_FORCE_HX_TRIGGER_AFTER_SWAP = true;
			header('HX-Trigger: ' . json_encode(['closeModal' => true,], JSON_FORCE_OBJECT));
			message(0, '更改备注成功！');
			die;
		} else {
			message(0, '更改备注成功！');
		}
	}
} elseif ($action == 'dynamic') {
	if ($method == 'GET') {
		message(-1, 'Bad Request!');
	}
	// hook plugin_haya_follow_follow_dynamic_start.php

	if (empty($haya_follow_check_user)) {
		message(1, '你还没有关注过Ta！');
	}

	$follow_dynamic = param('dynamic', 1);
	if ($follow_dynamic == 1) {
		$follow_dynamic = 1;
	} else {
		$follow_dynamic = 0;
	}

	haya_follow_update_by_uid($_uid, array('show_dynamic' => $follow_dynamic));

	// 清空缓存
	haya_follow_clear_cache_by_follow_uid($uid);

	// hook plugin_haya_follow_follow_dynamic_end.php

	if ($IS_HTMX) {
		$haya_follow_user = user_read_cache($_uid);
		$haya_follow_user['show_dynamic'] = !$follow_dynamic;
		$HOLD_ON_FOR_LATER_HTML = true;
		include _include(APP_PATH . 'plugin/abs_theme_aether/template_parts_plugin/haya_follow/btn_change_show_dynamic__my_follow_follows.htm');
		message(0, '将会在动态中' . ($follow_dynamic == 1 ? '隐藏' : '收到') . '来自' . $haya_follow_user['username'] . '的内容');
		die;
	} else {
		message(0, '更改关注动态状态成功！');
	}
} else {
	message(-1, '提交错误！');

}

// hook plugin_haya_follow_follow_end.php

