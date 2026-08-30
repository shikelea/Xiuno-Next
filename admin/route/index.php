<?php

!defined('DEBUG') AND exit('Access Denied.');

$action = param(1);

// hook admin_index_start.php

if($action == 'login') {

	// hook admin_index_login_get_post.php
	
	if($method == 'GET') {

		// hook admin_index_login_get_start.php
		
		$header['title'] = lang('admin_login');
		
		include _include(ADMIN_PATH."view/htm/index_login.htm");

	} else if($method == 'POST') {

		// hook admin_index_login_post_start.php
		
		$password = param('password');

		if(!user_verify_password($password, $user)) {
			xn_log('password error. uid:'.$user['uid'].' - ******'.substr($password, -6), 'admin_login_error');
			message('password', lang('password_incorrect'));
		}

		admin_token_set();

		xn_log('login successed. uid:'.$user['uid'], 'admin_login');

		// hook admin_index_login_post_end.php
		
		message(0, jump(lang('login_successfully'), '.'));

	}

} elseif ($action == 'logout') {

	$method != 'POST' AND message(-1, 'Method Not Allowed');

	// hook admin_index_logout_start.php

	admin_token_clean();

	message(0, jump(lang('logout_successfully'), './'));

} elseif ($action == 'safe_mode_exit') {

	$method != 'POST' AND message(-1, 'Method Not Allowed');
	$safe_mode_error = '';
	$safe_mode_failed_paths = array();
	$safe_mode_exited = plugin_safe_mode_exit($conf, APP_PATH, $safe_mode_error, $safe_mode_failed_paths);
	if(!$safe_mode_exited) {
		$error_keys = array(
			'locked'=>'plugin_safe_mode_exit_locked',
			'clear_failed'=>'plugin_safe_mode_exit_clear_failed',
			'unlock_failed'=>'plugin_safe_mode_exit_unlock_failed',
		);
		$error_key = isset($error_keys[$safe_mode_error]) ? $error_keys[$safe_mode_error] : 'plugin_safe_mode_exit_clear_failed';
		message(-1, lang($error_key));
	}
	message(0, jump(lang('plugin_safe_mode_exit_success'), url('index')));

} elseif ($action == 'phpinfo') {

	// 🔒 安全修复：限制 phpinfo() 仅管理员可访问，防止敏感信息泄露
	// phpinfo() 会暴露服务器配置、环境变量、数据库连接等敏感信息
	$gid != 1 AND message(-1, lang('insufficient_privilege'));

	unset($_SERVER['conf']);
	unset($_SERVER['db']);
	unset($_SERVER['cache']);
	phpinfo();
	exit;
	
} else {

	// hook admin_index_empty_start.php
	
	$header['title'] = lang('admin_page');
	
	$info = array();
	$info['disable_functions'] = ini_get('disable_functions');
	$info['allow_url_fopen'] = ini_get('allow_url_fopen') ? lang('yes') : lang('no');
	$info['plugin_safe_mode'] = plugin_safe_mode_status($conf, APP_PATH);
	$info['safe_mode'] = $info['plugin_safe_mode']['active'] ? lang('yes') : lang('no');
	empty($info['disable_functions']) && $info['disable_functions'] = lang('none');
	$info['upload_max_filesize'] = ini_get('upload_max_filesize');
	$info['post_max_size'] = ini_get('post_max_size');
	$info['memory_limit'] = ini_get('memory_limit');
	$info['max_execution_time'] = ini_get('max_execution_time');
	$info['dbversion'] = $db->version();
	$info['SERVER_SOFTWARE'] = _SERVER('SERVER_SOFTWARE');
	$info['HTTP_X_FORWARDED_FOR'] = _SERVER('HTTP_X_FORWARDED_FOR');
	$info['REMOTE_ADDR'] = _SERVER('REMOTE_ADDR');
	
	
	$stat = array();
	$stat['threads'] = thread_count();
	$stat['posts'] = post_count();
	$stat['users'] = user_count();
	$stat['attachs'] = attach_count();
	$stat['disk_free_space'] = function_exists('disk_free_space') ? humansize(disk_free_space(APP_PATH)) : lang('unknown');
	$stat['storage_spaces'] = diagnostic_storage_spaces($conf, APP_PATH);
	foreach($stat['storage_spaces'] as &$storage_space) {
		$storage_space['free'] = $storage_space['free_bytes'] === FALSE ? lang('unknown') : humansize($storage_space['free_bytes']);
	}
	unset($storage_space);
	
	$security = array();
	$security['version'] = $conf['version'];
	$security['csrf_on'] = !empty($conf['csrf_on']);
	$security['bcrypt_ready'] = function_exists('password_hash');
	$security['https'] = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
	$security['debug'] = DEBUG;
	
	$lastversion = get_last_version($stat);
	
	// hook admin_index_empty_end.php
	
	include _include(ADMIN_PATH.'view/htm/index.htm');

}

// hook admin_index_end.php

function get_last_version($stat) {
	return '';
}

?>
