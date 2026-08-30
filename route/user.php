<?php

!defined('DEBUG') AND exit('Access Denied.');

include _include(XIUNOPHP_PATH.'xn_send_mail.func.php');

$action = param(1);

is_numeric($action) AND $action = '';

// hook user_start.php

if(empty($action)) {

        // hook user_index_start.php

        $_uid = param(1, 0);
        empty($_uid) AND $_uid = $uid;
        $_user = user_read($_uid);

        empty($_user) AND message(-1, lang('user_not_exists'));
        $header['title'] = $_user['username'];
        $header['mobile_title'] = $_user['username'];

        // hook user_index_end.php
        
	include _include(APP_PATH.'view/htm/user.htm');
	
} elseif($action == 'thread') {

        // hook user_thread_start.php

        $_uid = param(2, 0);
        empty($_uid) AND $_uid = $uid;
        $_user = user_read($_uid);
        
        empty($_user) AND message(-1, lang('user_not_exists'));
        $header['title'] = $_user['username'];
        $header['mobile_title'] = $_user['username'];

        $page = param(3, 1);
        $pagesize = 20;
        $totalnum = $_user['threads'];
        $pagination = pagination(url("user-thread-$_uid-{page}"), $totalnum, $page, $pagesize);
        $threadlist = mythread_find_by_uid($_uid, $page, $pagesize);
        thread_list_access_filter($threadlist, $gid);

        // hook user_thread_end.php
       
	include _include(APP_PATH.'view/htm/user_thread.htm');
	
} elseif($action == 'login') {

	// hook user_login_get_post.php
	
	if($method == 'GET') {

		// hook user_login_get_start.php
		
		$referer = user_http_referer();
			
		$header['title'] = lang('user_login');
		
		// hook user_login_get_end.php
		
		include _include(APP_PATH.'view/htm/user_login.htm');

	} else if($method == 'POST') {

		// hook user_login_post_start.php

		$email = param('email');			// 邮箱或者用户名 / email or username
		$password = param('password');
		empty($email) AND message('email', lang('email_is_empty'));

		$login_identifier_type = user_login_identifier_type($email, $err);
		empty($login_identifier_type) AND message('email', $err);

		user_login_rate_limited($email) AND message('email', 'Please try again later');
		if($login_identifier_type == 'email') {
			$_user = user_read_by_email($email, TRUE);
			if(empty($_user)) {
				user_login_rate_fail($email);
				message('email', lang('email_not_exists'));
			}
		} else {
			$_user = user_read_by_username($email, TRUE);
			if(empty($_user)) {
				user_login_rate_fail($email);
				message('email', lang('username_not_exists'));
			}
		}

		if(!is_password($password, $err)) {
			user_login_rate_fail($email);
			message('password', $err);
		}
		if(!user_verify_password($password, $_user)) {
			user_login_rate_fail($email);
			message('password', lang('password_incorrect'));
		}

		// 登录成功后轮换 Session ID，防止会话固定攻击。
		// helper 同时同步过程式代码后续会使用的全局 $sid。
		sess_regenerate_id() OR message(-1, 'Unable to renew session. Please try again.');

		// Re-verify under the credential lock. A concurrently replaced password must not let the
		// already-presented old password inherit the replacement credential generation.
		$_user = user_login_credentials_refresh($_user['uid'], $password);
		empty($_user) AND message(-1, 'Account credentials changed during sign-in. Please try again.');
		user_format($_user);

		user_update($_user['uid'], array('login_ip'=>$longip, 'login_date' =>$time , 'logins+'=>1));
		user_login_rate_clear($email);

		// 全局变量 $uid 会在结束后，在函数 register_shutdown_function() 中存入 session (文件: model/session.func.php)
		// global variable $uid will save to session in register_shutdown_function() (file: model/session.func.php)
		$uid = $_user['uid'];

		$login_auth_epoch = user_auth_epoch($_user);
		user_token_set($_user['uid'], $login_auth_epoch)
			OR message(-1, 'Unable to issue login credentials. Please try again.');
		user_session_auth_bind($uid, $login_auth_epoch)
			OR message(-1, 'Unable to bind the authenticated session. Please try again.');

		// hook user_login_post_end.php

		// 设置 token，下次自动登陆。

		message(0, lang('user_login_successfully'));

	}

} elseif($action == 'create') {

	// hook user_create_get_post.php
	
	empty($conf['user_create_on']) AND message(-1, lang('user_create_not_on'));
	
	if($method == 'GET') {
		
		// hook user_create_get_start.php
		
		$referer = user_http_referer();
		$header['title'] = lang('create_user');
		
		// hook user_create_get_end.php
		
		include _include(APP_PATH.'view/htm/user_create.htm');

	} else if($method == 'POST') {
				
		// hook user_create_post_start.php
		
		$email = param('email');
		$username = param('username');
		$password = param('password');
		$code = param('code');
		empty($email) AND message('email', lang('please_input_email'));
		empty($username) AND message('username', lang('please_input_username'));
		empty($password) AND message('password', lang('please_input_password'));
		
		if($conf['user_create_email_on']) {
			user_email_code_verify('user_create', $email, $code);
		}
		
		!is_email($email, $err) AND message('email', $err);
		$_user = user_read_by_email($email, TRUE);
		$_user AND message('email', lang('email_is_in_use'));
		
		!is_username($username, $err) AND message('username', $err);
		$_user = user_read_by_username($username, TRUE);
		$_user AND message('username', lang('username_is_in_use'));
		
		!is_password($password, $err) AND message('password', $err);
		
		$pwd = user_hash_password($password);
		$gid = 101;
		$_user = array (
			'username' => $username,
			'email' => $email,
			'password' => $pwd,
			'salt' => '',
			'gid' => $gid,
			'create_ip' => $longip,
			'create_date' => $time,
			'logins' => 1,
			'login_date' => $time,
			'login_ip' => $longip,
		);

		sess_regenerate_id() OR message(-1, 'Unable to renew session. Please try again.');
		$uid = user_create($_user);
		$uid === FALSE AND message('email', lang('user_create_failed'));
		$user = user_read_primary_proven($uid);
	
		// 更新 session
		
		user_email_code_clear('user_create');
		$create_auth_epoch = user_auth_epoch($user);
		$create_session_bound = user_session_auth_bind($uid, $create_auth_epoch);
		$create_token_set = $create_session_bound && user_token_set($uid, $create_auth_epoch);
		if(!$create_session_bound || !$create_token_set) {
			$uid = 0;
			$user = array();
			$_SESSION['uid'] = 0;
			unset($_SESSION['auth_epoch']);
			user_token_clear();
			message(-1, lang('user_create_login_failed'), array(
				'account_created'=>1,
				'login_url'=>url('user-login'),
			));
		}
		
		$extra = array('token'=>user_token_gen($uid, $create_auth_epoch));
		
		// hook user_create_post_end.php
		
		message(0, lang('user_create_sucessfully'), $extra);
	}
	
} elseif($action == 'logout') {

	if($method == 'GET') {
		// Legacy themes still emit a plain logout link. Keep that URL usable, but require an
		// explicit CSRF-protected POST before changing Session or long-lived token state.
		$referer = user_http_referer();
		$header['title'] = lang('logout');
		include _include(APP_PATH.'view/htm/user_logout.htm');
	} elseif($method == 'POST') {
		// hook user_logout_start.php

		$uid = 0;
		$_SESSION['uid'] = $uid;
		unset($_SESSION['auth_epoch']);
		user_token_clear();

		// hook user_logout_end.php

		message(0, jump(lang('logout_successfully'), user_http_referer(), 1));
	} else {
		message(-1, 'Method Not Allowed');
	}
	
// 重设密码第 1 步 | reset password first step
} elseif($action == 'resetpw') {
	
	// hook user_resetpw_get_post.php
	
	!$conf['user_resetpw_on'] AND message(-1, lang('resetpw_not_on'));
		
	if($method == 'GET') {

		// hook user_resetpw_get_start.php
		
		$header['title'] = lang('resetpw');
		
		// hook user_resetpw_get_end.php
		
		include _include(APP_PATH.'view/htm/user_resetpw.htm');

	} else if($method == 'POST') {
		
		// hook user_resetpw_post_start.php
		
		$email = param('email');
		empty($email) AND message('email', lang('please_input_email'));
		!is_email($email, $err) AND message('email', $err);
		
		$_user = user_read_by_email($email, TRUE);
		!$_user AND message('email', lang('email_is_not_in_use'));

		$code = param('code');
		empty($code) AND message('code', lang('please_input_verify_code'));
		
		user_email_code_verify('user_resetpw', $email, $code);
		user_reset_grant_issue($_user['uid'], $email) === FALSE
			AND message(-1, 'Unable to issue a password-reset authorization. Please try again.');
		user_email_code_clear('user_resetpw');
		
		// hook user_resetpw_post_end.php
		
		message(0, lang('check_ok_to_next_step'));
	}

// 重设密码第 3 步 | reset password step 3
} elseif($action == 'resetpw_complete') {
	
	// hook user_resetpw_get_post.php
	
	// 校验数据
	$resetpw_grant = user_reset_grant_current();
	empty($resetpw_grant) AND message(-1, lang('data_empty_to_last_step'));
	$_uid = intval($resetpw_grant['uid']);
	$_user = user_read_primary_proven($_uid);
	empty($_user) AND message(-1, lang('email_not_exists'));
	user_reset_grant_email($_user['email']) !== user_reset_grant_email($resetpw_grant['email'])
		AND message(-1, lang('data_empty_to_last_step'));
	$email = $_user['email'];
	
	if($method == 'GET') {

		// hook user_resetpw_get_start.php
		
		$header['title'] = lang('resetpw');
		
		// hook user_resetpw_get_end.php
		
		include _include(APP_PATH.'view/htm/user_resetpw_complete.htm');

	} else if($method == 'POST') {
		
		// hook user_resetpw_post_start.php
		
		$password = param('password');
		empty($password) AND message('password', lang('please_input_password'));
		
		!is_password($password, $err) AND message('password', $err);
		
		$password = user_hash_password($password);
		$password_reset_result = user_reset_grant_commit_password($password);
		$password_reset_result === NULL AND message(-1, lang('link_has_expired'));
		$password_reset_result === FALSE AND message(-1, lang('password_modify_failed'));

		user_email_code_clear('user_resetpw');
		
		// hook user_resetpw_post_end.php
		
		message(0, lang('modify_successfully'));
		
	}

// 发送验证码
} elseif($action == 'send_code') {
	
	$method != 'POST' AND message(-1, lang('method_error'));
	
	// hook user_sendcode_start.php
	
	$action2 = param(2);
	
	// 创建用户
	if($action2 == 'user_create') {
		
		$email = param('email');
		
		empty($email) AND message('email', lang('please_input_email'));
		!is_email($email, $err) AND message('email', $err);
		empty($conf['user_create_email_on']) AND message(-1, lang('email_verify_not_on'));
		$_user = user_read_by_email($email, TRUE);
		!empty($_user) AND message('email', lang('email_is_in_use'));
		
		$code = user_email_code_issue('user_create', $email);
		
	
	// 重置密码，往老地址发送
	} elseif($action2 == 'user_resetpw') {
		
		$email = param('email');
		
		empty($email) AND message('email', lang('please_input_email'));
		!is_email($email, $err) AND message('email', $err);
		$_user = user_read_by_email($email, TRUE);
		empty($_user) AND message('email', lang('email_is_not_in_use'));
		
		empty($conf['user_resetpw_on']) AND message(-1, lang('resetpw_not_on'));
		
		$code = user_email_code_issue('user_resetpw', $email, $_user['uid']);

	} else {
		message(-1, 'action2 error');
	}
	
	
	$subject = lang('send_code_template', array('rand'=>$code, 'sitename'=>$conf['sitename']));
	$message = $subject;
	
	$smtplist = include _include(APP_PATH.'conf/smtp.conf.php');
	$n = array_rand($smtplist);
	$smtp = $smtplist[$n];
	
	// hook user_send_code_before.php
	$r = xn_send_mail($smtp, $conf['sitename'], $email, $subject, $message);
	// hook user_send_code_after.php
	
	if($r === TRUE) {
		message(0, lang('send_successfully'));
	} else {
		xn_log($errstr, 'send_mail_error');
		message(-1, $errstr);
	}

// 简单的同步登陆实现：| sync login implement simply
/* 
	将用户信息通过 token 传递给其他系统 | send user information to other system by token
	两边系统将 auth_key 设置为一致，用 xn_encrypt() xn_decrypt() 加密解密。all subsystem set auth_key to correct by xn_encrypt() xn_decrypt()
*/
} elseif($action == 'synlogin') {

	// 检查过来的 token | check token
	$token = param('token', '', FALSE);
	$return_url = user_synlogin_return_url(param('return_url', '', FALSE));
	empty($return_url) AND message(-1, lang('unauthorized_access'));

	$s = xn_decrypt($token);
	!$s AND message(-1, lang('unauthorized_access'));
	$token_parts = explode("\t", $s);
	count($token_parts) != 2 AND message(-1, lang('unauthorized_access'));
	list($_time, $_useragent) = $token_parts;

	// 🔒 安全修复：缩短 token 有效期从 300 秒到 60 秒，减少重放攻击窗口
	// 原 5 分钟窗口过大，攻击者截获 URL 后有充足时间利用
	abs($time - intval($_time)) > 60 AND message(-1, lang('link_has_expired'));

	$useragent != $_useragent AND message(-1, lang('authorized_get_failed'));
	
	$_SESSION['return_url'] = $return_url;
	if(!$uid) {
		http_location(url('user-login'));
	} else {
		$return_url = _SESSION('return_url');
		
		empty($return_url) AND message(-1, lang('request_synlogin_again'));
		unset($_SESSION['return_url']);
		
		$arr = array(
			'uid'=>$user['uid'],
			'gid'=>$user['gid'],
			'username'=>$user['username'],
			'avatar_url'=>$user['avatar_url'],
			'email'=>$user['email'],
			'mobile'=>$user['mobile'],
		);
		$s = xn_json_encode($arr);
		$s = xn_encrypt($s);
		
		// 将 token 附加到 URL，跳转回去 | add token into URL, jump back
		$url = user_synlogin_append_token($return_url, $s);
		http_location($url);
	}

} else {
	
}

// hook user_end.php

// 获取用户来路
function user_http_referer() {
	// hook user_http_referer_start.php
	$referer = param('referer', '', FALSE); // 优先从参数获取 | GET is priority
	empty($referer) AND $referer = array_value($_SERVER, 'HTTP_REFERER', '');
	$referer = user_return_url_normalize($referer);
	// hook user_http_referer_end.php
	return user_return_url_normalize($referer);
}

function user_email_code_issue($prefix, $email, $uid = 0) {
	global $time;
	user_email_code_rate_limit($prefix, $email);
	if($prefix == 'user_resetpw') {
		$uid = intval($uid);
		if($uid <= 0) {
			$_reset_user = user_read_by_email($email, TRUE);
			$uid = empty($_reset_user) ? 0 : intval($_reset_user['uid']);
		}
		($uid <= 0 || !user_reset_grant_revoke_uid($uid))
			AND message(-1, 'Unable to revoke an earlier password-reset authorization. Please try again.');
		unset($_SESSION['resetpw_grant']);
	}
	$code = (string)random_int(100000, 999999);
	$_SESSION[$prefix.'_email'] = $email;
	$_SESSION[$prefix.'_code'] = $code;
	$_SESSION[$prefix.'_code_time'] = $time;
	$_SESSION[$prefix.'_code_attempts'] = 0;
	return $code;
}

function user_email_code_verify($prefix, $email, $code) {
	global $time;
	$sess_email = _SESSION($prefix.'_email');
	$sess_code = (string)_SESSION($prefix.'_code');
	$sess_time = intval(_SESSION($prefix.'_code_time'));
	$attempts = intval(_SESSION($prefix.'_code_attempts'));
	empty($sess_code) AND message('code', lang('click_to_get_verify_code'));
	empty($sess_email) AND message('code', lang('click_to_get_verify_code'));
	if($sess_time <= 0 || $time - $sess_time > 300) {
		user_email_code_clear($prefix);
		message('code', lang('link_has_expired'));
	}
	$attempts >= 5 AND message('code', lang('verify_code_try_too_frequently', array('times'=>5)));
	if($email != $sess_email || !hash_equals($sess_code, (string)$code)) {
		$_SESSION[$prefix.'_code_attempts'] = $attempts + 1;
		message('code', lang('verify_code_incorrect'));
	}
}

function user_email_code_clear($prefix) {
	unset($_SESSION[$prefix.'_email']);
	unset($_SESSION[$prefix.'_code']);
	unset($_SESSION[$prefix.'_code_time']);
	unset($_SESSION[$prefix.'_code_attempts']);
}

function user_synlogin_return_url($return_url) {
	$raw = (string)$return_url;
	$candidates = array(trim($raw), trim(xn_urldecode($raw)));
	foreach($candidates as $url) {
		if($url === '' || preg_match('/[\x00-\x1F\x7F]/', $url)) continue;
		$hash_pos = strpos($url, '#');
		if($hash_pos !== FALSE) $url = substr($url, 0, $hash_pos);
		$parts = parse_url($url);
		if(empty($parts['scheme']) || !in_array(strtolower($parts['scheme']), array('http', 'https'), TRUE)) continue;
		if(empty($parts['host']) || !user_synlogin_public_host($parts['host'])) continue;
		if(!empty($parts['user']) || !empty($parts['pass'])) continue;
		return $url;
	}
	return '';
}

function user_synlogin_public_host($host) {
	$host = strtolower(trim((string)$host, "[] \t\r\n"));
	if($host === '' || $host === 'localhost' || substr($host, -10) === '.localhost' || substr($host, -6) === '.local') return FALSE;
	if(filter_var($host, FILTER_VALIDATE_IP)) {
		$flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
		return filter_var($host, FILTER_VALIDATE_IP, $flags) !== FALSE;
	}
	return preg_match('/^[a-z0-9.-]+$/i', $host) && strpos($host, '.') !== FALSE;
}

function user_synlogin_append_token($return_url, $token) {
	$separator = strpos($return_url, '?') === FALSE ? '?' : '&';
	return $return_url.$separator.http_build_query(array('token'=>$token));
}

function user_auth_check($token) {
	// hook user_auth_check_start.php
	global $time;
	$auth = param(2);
	$s = decrypt($auth);
	empty($s) AND message(-1, lang('decrypt_failed'));
	$arr = explode('-', $s);
	count($arr) != 3 AND message(-1, lang('encrypt_failed'));
	list($_ip, $_time, $_uid) = $arr;
	$_user = user_read_primary_proven($_uid);
	empty($_user) AND message(-1, lang('user_not_exists'));
	$time - $_time > 3600 AND message(-1, lang('link_has_expired'));
	// hook user_auth_check_end.php
	return $_user;
}

?>
