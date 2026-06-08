<?php

!defined('DEBUG') AND exit('Access Denied.');

$action = param(2);

// 用户登录
if($action == 'login') {
	
	api_method_required('POST');

	$email = param('email');
	$password = param('password');

	if(empty($email)) api_output(-1, lang('email_is_empty'));
	if(empty($password)) api_output(-1, lang('password_is_empty'));
	user_login_rate_limited($email) AND api_output(-1, 'Please try again later');

	// API 客户端发送原始密码（无浏览器端 JS MD5），服务端需对齐浏览器行为
	$password = md5($password);

	$user = user_read_by_email($email);
	if(empty($user)) {
		$user = user_read_by_username($email);
	}

	if(empty($user)) {
		user_login_rate_fail($email);
		api_output(-1, 'Email or password is incorrect');
	}

	if(!user_verify_password($password, $user)) {
		user_login_rate_fail($email);
		api_output(-1, 'Email or password is incorrect');
	}

	user_login_rate_clear($email);
	user_password_needs_upgrade($user) AND user_upgrade_password($user['uid'], $password);

	$token = user_token_gen($user['uid']);

	user_update($user['uid'], array(
		'login_ip' => $longip,
		'login_date' => $time,
		'logins+' => 1
	));

	// 返回用户信息（过滤敏感字段）
	$user_safe = user_safe_info($user);
	$user_safe['token'] = $token;

	api_output(0, 'Login Success', $user_safe);

} elseif($action == 'read') {
	
	// 获取用户信息
	$uid = param('uid');
	if(empty($uid)) $uid = api_auth_uid(FALSE);
	
	if(empty($uid)) api_output(-1, lang('user_not_exists'));
	
	$user = user_read($uid);
	if(empty($user)) api_output(-1, lang('user_not_exists'));
	
	api_output(0, 'OK', user_safe_info($user));

} elseif($action == 'threads') {

	$_uid = param('uid', 0);
	if(empty($_uid)) $_uid = api_auth_uid(FALSE);

	if(empty($_uid)) api_output(-1, lang('user_not_exists'));

	$_user = user_read($_uid);
	if(empty($_user)) api_output(-1, lang('user_not_exists'));

	list($page, $pagesize) = api_page_params();
	$result = mythread_find_visible_by_uid($_uid, $gid, $page, $pagesize);

	api_output(0, 'OK', array(
		'user' => user_safe_info($_user),
		'page' => $page,
		'pagesize' => $pagesize,
		'total' => $result['total'],
		'list' => $result['list'],
	));

} else {
	api_output(-1, 'Unknown Action');
}

?>
