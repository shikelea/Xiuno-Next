<?php

!defined('DEBUG') AND exit('Access Denied.');

if (empty($user)) {
	message(0, '登录后才可以关注！');
}

if ($method == 'GET') {
	message(0, '提交错误！');
}

// 被关注用户
$_uid = param('uid', ''); 
if (empty($_uid)) {
	message(0, '关注失败，请确认后重试！');
}

if ($_uid == $uid) {
	message(0, '你不能关注你自己！');
}

$follow_user = user_read($_uid);
if (empty($follow_user)) {
	message(0, '所关注的用户不存在！');
}

$haya_follow_check_user = haya_follow_find_by_uid_and_follow_uid($_uid, $uid);

$haya_follow_config = setting_get('haya_follow');

$action = param(1);

// hook plugin_haya_follow_follow_start.php

if ($action == 'create') {
	
	// hook plugin_haya_follow_follow_create_start.php
	
	if (!empty($haya_follow_check_user)) {
		message(0, '你已经关注过了！');
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
		message(0, '关注失败！');
	}
	
	haya_follow_user_update_follows_by_uid($user['uid'], 1);	
	haya_follow_user_update_followeds_by_uid($_uid, 1);	

	// 清空缓存
	haya_follow_clear_cache_by_follow_uid($uid);
	
	// hook plugin_haya_follow_follow_create_end.php
	
	message(1, '关注成功！');
	
} elseif ($action == 'delete') {
	
	// hook plugin_haya_follow_follow_delete_start.php
	
	if (empty($haya_follow_check_user)) {
		message(0, '你还没有关注过Ta！');
	}	
	
	$delete_status = haya_follow_delete_by_uid_and_follow_uid($_uid, $uid);
	if ($delete_status === false) {
		message(0, '取消关注失败！');
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
	
	message(1, '取消关注成功！');
	
} elseif ($action == 'remove') {
	
	// hook plugin_haya_follow_follow_remove_start.php
	
	$haya_follow_check_follow_me = haya_follow_find_by_uid_and_follow_uid($uid, $_uid);

	if (empty($haya_follow_check_follow_me)) {
		message(0, 'Ta还没有关注过你！');
	}	
	
	if ($haya_follow_config['delete_follower'] != 1) {
		message(0, '你不能移除关注你的用户！');
	}
	
	$remove_status = haya_follow_delete_by_uid_and_follow_uid($uid, $_uid);
	if ($remove_status === false) {
		message(0, '移除关注我的失败！');
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
	
	message(1, '移除关注我的成功！');
	
} elseif ($action == 'remarks') {
	
	// hook plugin_haya_follow_follow_remarks_start.php
	
	if (empty($haya_follow_check_user)) {
		message(0, '你还没有关注过Ta！');
	}	
	
	$follow_comment = param('remarks', '');
	
	haya_follow_update_by_uid($_uid, array('comment' => $follow_comment));
	
	// hook plugin_haya_follow_follow_remarks_end.php
	
	message(1, '更改备注成功！');
	
} elseif ($action == 'dynamic') {
	
	// hook plugin_haya_follow_follow_dynamic_start.php
	
	if (empty($haya_follow_check_user)) {
		message(0, '你还没有关注过Ta！');
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
	
	message(1, '更改关注动态成功成功！');
	
}

// hook plugin_haya_follow_follow_end.php

message(0, '提交错误！');

?>