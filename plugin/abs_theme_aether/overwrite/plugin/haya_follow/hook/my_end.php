<?php
exit;

elseif ($action == 'follow') {
	$action2 = param(2, 'timeline');
	$my_follow_site = ' - 关注';
	
	$haya_follow_config = setting_get('haya_follow');
	
	if ($action2 == 'follows') {
		$header['title'] = '我关注的' . $my_follow_site;
		
		$pagesize = intval($haya_follow_config['follow_user_pagesize']);
		$page = param(3, 1);

		$haya_follow_count = haya_follow_count(array('follow_uid' => $uid));
		$haya_follow_follows = haya_follow_find(array('follow_uid' => $uid), array('create_date' => -1), $page, $pagesize);
		$haya_follow_pagination = pagination(url("user-fans-{page}"), $haya_follow_count, $page, $pagesize);

		include _include(APP_PATH.'plugin/haya_follow/view/htm/my_follow_follows.htm');	
	} elseif ($action2 == 'fans') {
		$header['title'] = '关注我的' . $my_follow_site;
		
		$pagesize = intval($haya_follow_config['follow_user_pagesize']);
		$page = param(3, 1);

		$haya_follow_count = haya_follow_count(array('uid' => $uid));
		$haya_follow_followeds = haya_follow_find(array('uid' => $uid), array('create_date' => -1), $page, $pagesize);
		$haya_follow_pagination = pagination(url("user-fans-{page}"), $haya_follow_count, $page, $pagesize);

		include _include(APP_PATH.'plugin/haya_follow/view/htm/my_follow_fans.htm');	
	} else {
		$header['title'] = '时间线' . $my_follow_site;
		
		$action3 = param(3, 'all');
		
		$pagesize = intval($haya_follow_config['timeline_post_pagesize']);
		$page = param(4, 1);
		
		$haya_follow_life_time = intval($haya_follow_config['followed_life_time']);
		$haya_follow_my_follow_user_count = haya_follow_count_cache(array('follow_uid' => $uid, 'show_dynamic' => 1), $haya_follow_life_time);
		$haya_follow_my_follow_uids = haya_follow_find_uids_by_follow_uid_cache($uid, $haya_follow_my_follow_user_count, $haya_follow_life_time);
		$haya_follow_my_follow_uids[] = $uid;		
		
		$action3 = strtolower($action3);
		if ($action3 == 'thread') {
			$follow_where = array('uid' => $haya_follow_my_follow_uids, 'isfirst' => 1);
		} elseif ($action3 == 'post') {
			$follow_where = array('uid' => $haya_follow_my_follow_uids, 'isfirst' => 0);
		} else {
			$follow_where = array('uid' => $haya_follow_my_follow_uids);
			$action3 = 'all';
		}
		
		$haya_follow_user_post_count = post_count($follow_where);
		$haya_follow_user_post_list = post_find($follow_where, array('pid' => -1), $page, $pagesize);
		$haya_follow_user_post_pagination = pagination(url("my-follow-timeline-{$action3}-{page}"), $haya_follow_user_post_count, $page, $pagesize);		
		
		$haya_follow_my_follow_user_remarks_uids = array();
		if (!empty($haya_follow_user_post_list)) {
			foreach ($haya_follow_user_post_list as & $haya_follow_user_post) {
				$haya_follow_user_post['thread'] = thread_read_cache($haya_follow_user_post['tid']);
				
				if (!in_array($haya_follow_user_post['uid'], $haya_follow_my_follow_user_remarks_uids)) {
					$haya_follow_my_follow_user_remarks_uids[] = $haya_follow_user_post['uid'];
				}
			}
		}
		
		$haya_follow_my_follow_user_list = haya_follow__find(array('follow_uid' => $uid, 'uid' => $haya_follow_my_follow_user_remarks_uids), array('create_date' => -1), 1, count($haya_follow_my_follow_user_remarks_uids));
		
		$haya_follow_my_follow_user_remarks_list = array();
		
		if (!empty($haya_follow_my_follow_user_list)) {
			foreach ($haya_follow_my_follow_user_list as $haya_follow_my_follow_user) {
				$haya_follow_my_follow_user_remarks_list[$haya_follow_my_follow_user['uid']] = $haya_follow_my_follow_user;
			}
		}
	
		include _include(APP_PATH.'plugin/haya_follow/view/htm/my_follow_timeline.htm');	
	}
	
} 

?>