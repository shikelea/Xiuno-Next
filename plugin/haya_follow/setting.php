<?php

!defined('DEBUG') and exit('Access Denied.');

$header['title'] = '用户关注设置';

if ($method == 'GET') {
	
	$config = setting_get('haya_follow');
	
	include _include(APP_PATH.'plugin/haya_follow/view/htm/setting.htm');
	
} else {
	
	$config = array();
	
	$config['show_my_dynamic'] = param('show_my_dynamic', 0);
	$config['delete_follower'] = param('delete_follower', 0);
	$config['my_dynamic_post_num'] = param('dynamic_post_num', 20);
	$config['timeline_post_pagesize'] = param('timeline_post_pagesize', 20);
	$config['follow_user_pagesize'] = param('follow_user_pagesize', 20);
	$config['followed_life_time'] = param('followed_life_time', 86400);
	
	$config['show_user_dynamic'] = param('show_user_dynamic', 0);
	$config['user_dynamic_pagesize'] = param('user_dynamic_pagesize', 20);
	setting_set('haya_follow', $config); 
	
	message(0, jump('设置修改成功', url('plugin-setting-haya_follow')));
}

?>