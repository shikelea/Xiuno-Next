<?php

!defined('DEBUG') and exit('Access Denied.');

$header['title'] = lang('haya_post_like').lang('setting');

if ($method == 'GET') {
	
	$config = setting_get('haya_post_like');
	
	include _include(APP_PATH.'plugin/haya_post_like/view/htm/setting.htm');
	
} else {
	
	$config = array();
	
	$config['open_thread'] = param('open_thread', 0);
	$config['thread_like_position'] = param('thread_like_position', 2);
	$config['open_post'] = param('open_post', 1);
	$config['post_like_position'] = param('post_like_position', 2);
	$config['post_loved_color'] = param('post_loved_color', 'secondary');
	$config['open_hot_like'] = param('open_hot_like', 1);
	$config['hot_like_post_low_count'] = param('hot_like_post_low_count', 10);
	$config['hot_like_post_size'] = param('hot_like_post_size', 5);
	$config['hot_like_isfirst'] = param('hot_like_isfirst', 0);
	$config['hot_like_life_time'] = param('hot_like_life_time', 86400);
	$config['list_show_likes'] = param('list_show_likes', 0);
	$config['like_is_delete'] = param('like_is_delete', 1);
	$config['delete_time'] = param('delete_time', 0);
	$config['open_my_post_like'] = param('open_my_post_like', 1);
	$config['my_post_like_pagesize'] = param('my_post_like_pagesize', 10);
	$config['post_like_count_type'] = param('post_like_count_type', 0);
	setting_set('haya_post_like', $config); 

	$clear_hot_like = param('clear_hot_like', 0);
	if ($clear_hot_like == 1) {
		cache_delete('haya_post_like');
	}
	
	message(0, jump(lang('haya_post_like_setting_success_tip'), url('plugin-setting-haya_post_like')));
}

?>