<?php

!defined('DEBUG') and exit('Access Denied.');

$header['title'] = lang('haya_favorite').lang('setting');

if ($method == 'GET') {
	
	$config = setting_get('haya_favorite');
	
	include _include(APP_PATH.'plugin/haya_favorite/view/htm/setting.htm');
	
} else {
	
	$config = array();
	
	$config['user_favorite'] = param('user_favorite', 10);
	$config['user_favorite_count'] = param('user_favorite_count', 20);
	$config['user_favorite_sort'] = param('user_favorite_sort', 'desc');
	
	$config['thread_show_favorite'] = param('thread_show_favorite', 0);
	$config['favorite_count_type'] = param('favorite_count_type', 0);
	
	$config['show_hot_favorite'] = param('show_hot_favorite', 0);
	$config['hot_favorite_count'] = param('hot_favorite_count', 10);
	$config['hot_favorite_find_time'] = param('hot_favorite_find_time', 30);
	$config['hot_favorite_cache_time'] = param('hot_favorite_cache_time', 86400);
	
	setting_set('haya_favorite', $config); 
	
	$clear_hot_favorite = param('clear_hot_favorite', 0);
	if ($clear_hot_favorite == 1) {
		cache_delete('haya_favorite_favorites');
	}
	
	message(0, jump(lang('haya_favorite_setting_success'), url('plugin-setting-haya_favorite')));
}

?>