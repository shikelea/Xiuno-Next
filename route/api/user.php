<?php

!defined('DEBUG') AND exit('Access Denied.');

$action = param('action');

// 用户登录
if($action == 'login') {
	
	if($method == 'POST') {
		
		$email = param('email');
		$password = param('password');
		
		if(empty($email)) api_output(-1, lang('email_is_empty'));
		if(empty($password)) api_output(-1, lang('password_is_empty'));
		
		$user = user_read_by_email($email);
		if(empty($user)) {
			$user = user_read_by_username($email);
		}
		
		if(empty($user)) api_output(-1, lang('user_not_exists'));
		
		if(!user_verify_password($password, $user)) {
			api_output(-1, lang('password_incorrect'));
		}
		
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
		
	} else {
		api_output(-1, 'Method Not Allowed');
	}

} elseif($action == 'read') {
	
	// 获取用户信息
	$uid = param('uid');
	if(empty($uid)) {
		// 如果未传 uid，尝试获取当前登录用户
		$token = param('token');
		if($token) {
			$uid = user_token_get_do($token);
		}
	}
	
	if(empty($uid)) api_output(-1, lang('user_not_exists'));
	
	$user = user_read($uid);
	if(empty($user)) api_output(-1, lang('user_not_exists'));
	
	api_output(0, 'OK', user_safe_info($user));

} else {
	api_output(-1, 'Unknown Action');
}

?>
