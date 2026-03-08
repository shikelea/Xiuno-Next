<?php
exit;

elseif ($action == 'favorite') {

	$header['title'] = lang('haya_favorite_my_favorite') . " - " . $conf['sitename'];

	$haya_favorite_config = setting_get('haya_favorite');
	
	// hook plugin_haya_favorite_favorite_start.php
	
	if ($method == 'GET') {
		// hook plugin_haya_favorite_favorite_detail_start.php
		
		$pagesize = intval($haya_favorite_config['user_favorite']);
		$page = param(2, 1);
		$cond['uid'] = $uid; 
		
		$haya_favorite_count = haya_favorite_count($cond);
		$threadlist = haya_favorite_find($cond, array('create_date' => -1), $page, $pagesize);
		$pagination = pagination(url("my-favorite-{page}"), $haya_favorite_count, $page, $pagesize);
		
		// hook plugin_haya_favorite_favorite_detail_end.php
		
		include _include(APP_PATH.'plugin/haya_favorite/view/htm/my_favorite.htm');

	} else {

		$action = param(2, 'add');
		$tid = param('tid');
		if (!$user) {
			message(0, lang('haya_favorite_user_favorite_error_tip'));
		}

		$thread = thread_read($tid);
		empty($thread) AND message(0, lang('thread_not_exists'));
		$haya_check_favorite = haya_favorite_find_by_uid_and_tid($uid, $tid);
		
		$haya_favorite_user_favorite_count = isset($haya_favorite_config['user_favorite_count']) ? intval($haya_favorite_config['user_favorite_count']) : 20;
		
		// hook plugin_haya_favorite_favorite_post_start.php
		
		if ($action == 'create') {
			// hook plugin_haya_favorite_favorite_create_start.php
			
			if (!empty($haya_check_favorite)) {
				message(0, lang('haya_favorite_user_have_favorite_tip'));
			}
			
			haya_favorite_create(array(
				'tid' => $tid, 
				'uid' => $user['uid'],
				'create_date' => time(),
				'create_ip' => $longip,
			));
			
			if (isset($haya_favorite_config['favorite_count_type']) 
				&& $haya_favorite_config['favorite_count_type'] == 1
			) {
				$haya_favorite_count = haya_favorite_count(array('tid' => $tid));
				
				thread__update($tid, array('favorites' => $haya_favorite_count));
			} else {
				$haya_favorite_count = $thread['favorites'] + 1;
				
				haya_favorite_thread_user_favorites($tid, 1);
			}
			
			// 更新当前用户收藏数
			$haya_favorite_user_now_favorite_count = haya_favorite_count(array('uid' => $uid));
			user__update($uid, array('favorites' => $haya_favorite_user_now_favorite_count));

			$haya_favorite_users = haya_favorite_find_by_tid($tid, $haya_favorite_user_favorite_count);
			ob_start();
			include _include(APP_PATH.'plugin/haya_favorite/view/htm/my_favorite_users.htm');	
			$haya_favorite_user_html = ob_get_clean();
			
			$haya_favorite_msg = array(
				'count' => $haya_favorite_count,
				'users' => $haya_favorite_user_html,
				'msg' => lang('haya_favorite_user_favorite_success_tip'),
			);
			
			// hook plugin_haya_favorite_favorite_create_end.php
			
			message(1, $haya_favorite_msg);
		} elseif ($action == 'delete') {
			// hook plugin_haya_favorite_favorite_delete_start.php
			
			if (empty($haya_check_favorite)) {
				message(0, lang('haya_favorite_user_no_favorite_error_tip'));
			}
			
			haya_favorite_delete_by_tid_and_uid($tid, $user['uid']);
			
			if (isset($haya_favorite_config['favorite_count_type']) 
				&& $haya_favorite_config['favorite_count_type'] == 1
			) {
				$haya_favorite_count = haya_favorite_count(array('tid' => $tid));
				
				thread__update($tid, array('favorites' => $haya_favorite_count));
			} else {
				$haya_favorite_count = MAX(0, $thread['favorites'] - 1);
				
				haya_favorite_thread_user_favorites($tid, -1);
			}

			// 更新当前用户收藏数
			$haya_favorite_user_now_favorite_count = haya_favorite_count(array('uid' => $uid));
			user__update($uid, array('favorites' => $haya_favorite_user_now_favorite_count));
			
			$haya_favorite_users = haya_favorite_find_by_tid($tid, $haya_favorite_user_favorite_count);
			ob_start();
			include _include(APP_PATH.'plugin/haya_favorite/view/htm/my_favorite_users.htm');	
			$haya_favorite_user_html = ob_get_clean();
			
			$haya_favorite_msg = array(
				'count' => $haya_favorite_count,
				'users' => $haya_favorite_user_html,
				'msg' => lang('haya_favorite_user_delete_favorite_success_tip'),
			);
			
			// hook plugin_haya_favorite_favorite_delete_end.php
			
			message(1, $haya_favorite_msg);
		}
		
	}

} elseif ($action == 'favorites') {
	
	$header['title'] = lang('haya_favorite_my_favorite') . " - " . $conf['sitename'];
	
	$haya_favorite_config = setting_get('haya_favorite');
	
	if (strtolower($haya_favorite_config['user_favorite_sort']) == 'asc') {
		$user_favorite_sort = 'asc';
	} else {
		$user_favorite_sort = 'desc';
	}
	
	$orderby = param('orderby', $user_favorite_sort);
	if (strtolower($orderby) == 'asc') {
		$orderby_config = array('create_date' => 1);
	} else {
		$orderby_config = array('create_date' => -1);
	}
	
	$pagesize = intval($haya_favorite_config['user_favorite']);
	$page = param(2, 1);
	$cond['uid'] = $uid; 
	
	$haya_favorite_count = haya_favorite_count($cond);
	$threadlist = haya_favorite_find($cond, $orderby_config, $page, $pagesize);
	$pagination = pagination(url("my-favorites-{page}", array("orderby" => $orderby)), $haya_favorite_count, $page, $pagesize);
	
	include _include(APP_PATH.'plugin/haya_favorite/view/htm/my_favorites.htm');	
}


?>