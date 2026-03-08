<?php
exit;

elseif ($action == 'post_like') {

	$header['title'] = lang('haya_post_like')." - " . $conf['sitename'];
	
	if (!$uid) {
		message(0, lang('haya_post_like_login_like_tip'));
	}
	
	// hook plugin_haya_post_like_start.php
	
	if ($method == 'POST') {

		$pid = param('pid');

		$post = post_read($pid);
		empty($post) AND message(0, lang('post_not_exists'));

		if ($post['isfirst'] == 1) {
			if (isset($haya_post_like_config['open_thread'])
				&& $haya_post_like_config['open_thread'] != 1
			) {
				message(0, lang('haya_post_like_close_thread_tip'));
			}
		} else {
			if (isset($haya_post_like_config['open_post'])
				&& $haya_post_like_config['open_post'] != 1
			) {
				message(0, lang('haya_post_like_close_post_tip'));
			}
		}
	
		haya_post_like_cache_delete($post['tid']);
		
		$haya_post_like_check = haya_post_like_find_by_uid_and_pid($uid, $pid);
		
		$action2 = param(2, 'create');
		if ($action2 == 'create') {
			// hook plugin_haya_post_like_create_start.php
			
			if (!empty($haya_post_like_check)) {
				message(0, lang('haya_post_like_user_has_like_tip'));
			}
			
			haya_post_like_create(array(
				'tid' => $post['tid'], 
				'pid' => $pid, 
				'uid' => $user['uid'],
				'create_date' => time(),
				'create_ip' => $longip,
			));			
			
			if (isset($haya_post_like_config['post_like_count_type'])
				&& $haya_post_like_config['post_like_count_type'] == 1
			) {
				$haya_post_like_count = haya_post_like_count(array('pid' => $pid));
				
				post__update($post['pid'], array('likes' => $haya_post_like_count));
				
				if ($post['isfirst'] == 1) {
					thread__update($post['tid'], array('likes' => $haya_post_like_count));
				}
			} else {
				$haya_post_like_count = intval($post['likes']) + 1;
				
				haya_post_like_loves($pid, 1);
				
				if ($post['isfirst'] == 1) {
					thread__update($post['tid'], array('likes+' => 1));
				}
			}
			
			$haya_post_like_msg = array(
				'count' => intval($haya_post_like_count),
				'msg' => lang('haya_post_like_like_success_tip'),
			);
			
			// hook plugin_haya_post_like_create_end.php
			
			message(1, $haya_post_like_msg);
		} elseif ($action2 == 'delete') {
			// hook plugin_haya_post_like_delete_start.php
			
			if (isset($haya_post_like_config['like_is_delete'])
				&& $haya_post_like_config['like_is_delete'] != 1
			) {
				message(0, lang('haya_post_like_no_unlike_tip'));
			}
			
			if (empty($haya_post_like_check)) {
				message(0, lang('haya_post_like_user_no_like_tip'));
			}
			
			$post_like = haya_post_like_read_by_uid_and_pid($uid, $pid);

			$delete_time = intval($haya_post_like_config['delete_time']);
			if ($post_like['create_date'] + $delete_time > time()) {
				message(0, lang('haya_post_like_no_fast_like_tip'));
			}
			
			haya_post_like_delete_by_pid_and_uid($pid, $user['uid']);
			
			if (isset($haya_post_like_config['post_like_count_type'])
				&& $haya_post_like_config['post_like_count_type'] == 1
			) {
				$haya_post_like_count = haya_post_like_count(array('pid' => $pid));
				
				post__update($post['pid'], array('likes' => $haya_post_like_count));
				
				if ($post['isfirst'] == 1) {
					thread__update($post['tid'], array('likes' => $haya_post_like_count));
				}
			} else {
				$haya_post_like_count = MAX(0, intval($post['likes']) - 1);
				
				haya_post_like_loves($pid, -1);
				
				if ($post['isfirst'] == 1) {
					$haya_post_like_thread = thread__read($post['tid']);
					
					if ($haya_post_like_thread['likes'] > 0) {
						thread__update($post['tid'], array('likes-' => 1));
					}
				}
			}			
			
			$haya_post_like_msg = array(
				'count' => intval($haya_post_like_count),
				'msg' => lang('haya_post_like_unlike_success_tip'),
			);
			
			// hook plugin_haya_post_like_delete_end.php
			
			message(1, $haya_post_like_msg);
		}
		
		// hook plugin_haya_post_like_post_end.php
		
		message(0, lang('haya_post_like_like_error_tip'));	
	}
	
	// hook plugin_haya_post_like_end.php
	
	message(0, lang('haya_post_like_like_error_tip'));

}


?>