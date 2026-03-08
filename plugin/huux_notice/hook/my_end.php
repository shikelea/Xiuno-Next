<?php exit;
elseif($action == 'notice') {

	if($method == 'GET') {

		$page = param(3, 1);
		$pagesize = 20;
		$active = 'notice';
		$notices = $user['notices'];
		$type = param(2, 0);

		$notice_menu = include _include(APP_PATH.'plugin/huux_notice/conf/notice_menu.conf.php');
		
		$noticelist = notice_find_by_recvuid($uid, $page, $pagesize, $type);
		$type != 0 AND $notices = notice_count(array('recvuid'=>$uid, 'type'=>$type));

		$pagination = pagination(url("my-notice-$type-{page}"), $notices, $page, $pagesize);
		
		$header['title'] = lang('notice');
		$header['mobile_title'] = lang('notice');

		include _include(APP_PATH.'plugin/huux_notice/view/htm/my_notice.htm');

	} elseif($method == 'POST') {
		$act = param('act');
		if($act == 'readall') {
			// 全部已读
		   	$r = notice_update_by_recvuid($uid, array('isread'=>1));
	    	$r === FALSE AND message(-1, lang('notice_my_update_failed'));
	    	message(0, array('a' => lang('notice_my_update_readed'),'b' => lang('notice_my_update_allread')));
		    
		} elseif($act == 'readone') {
           	// 设置已读
			$nid = param('nid');
			$notice = notice__read($nid);
			$notice['isread'] == 1 AND message(-1, lang('notice_my_update_readed'));
			$notice['recvuid'] != $uid AND message(-1, lang('notice_my_error'));

			$r = notice_update($nid, array('isread'=>1));

			$r === FALSE AND message(-1, lang('notice_my_update_failed'));
			message(0, lang('notice_my_update_readed'));

		} elseif($act == 'delete') {
			// 单条删除
			$nid = param('nid');
			$notice = notice__read($nid);
			$notice['recvuid'] != $uid AND message(-1, lang('notice_my_error'));

			$r = notice_delete($nid);
			$r === FALSE AND message(-1, lang('notice_my_update_failed'));
			message(0, lang('notice_delete_notice_sucessfully'));

		} elseif($act == 'deletearr') {
			// 多条删除
			$nidarr = param('nidarr', array(0));
			empty($nidarr) AND message(-1, lang('notice_my_error'));

			$noticelist = notice_find_by_nids($nidarr);

			foreach($noticelist as &$notice) {
				$nid = $notice['nid'];
				$recvuid = $notice['recvuid'];
				$uid == $recvuid AND notice_delete($nid);
			}
			message(0, lang('notice_delete_notice_sucessfully'));

		} else {
			// 清空所有暂时不添加
			message(-1, lang('notice_my_error'));

		}
	}	
}
?>