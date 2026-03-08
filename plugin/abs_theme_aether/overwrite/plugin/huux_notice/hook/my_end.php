
<?php exit;
if (false) {
} elseif ($action == 'notice') {
	$ajax = param('ajax', 0);
	$return_html = param('return_html', 0);
	$type = param(2, 0);
	$page = param(3, 1);
	$pagesize = 20;
	$notice_menu = include _include(APP_PATH . 'plugin/huux_notice/conf/notice_menu.conf.php');

	if (!isset($IS_HTMX)) {
		$IS_HTMX = isset($_SERVER['HTTP_HX_REQUEST']) || (isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'] == 'true');
	}

	/*=============================================
	=                   获取最新消息轮询                   =
	=============================================*/

	if ($IS_HTMX && param(2, '') === 'getnew') {

		function notice_find_in_time_range($cond = array(), $start_timestamp = 0, $end_timestamp = 0, $limit = 100) {
			// hook model_notice_find_in_time_range_start.php
			if (DEBUG) {
				if ($start_timestamp === 0 || $end_timestamp === 0) {

					// * 是的，生产环境里连Exception都不能扔出去
					throw new Exception('请确保在调用 notice_find_in_time_range 函数时提供了 start_timestamp和end_timestamp');
				}
			} else {
				if ($start_timestamp === 0 || $end_timestamp === 0) {
					// * 纯粹用来擦屁股的逻辑，因为你们被PHP 7.2惯坏了
					trigger_error('请确保在调用 notice_find_in_time_range 函数时提供了 start_timestamp和end_timestamp', E_USER_NOTICE);
					$start_timestamp = time() - 60;
					$end_timestamp = time();
				}
			}
			$time_range_cond = ['create_date' => ['>' => $start_timestamp, '<=' => $end_timestamp]];
			$real_cond = array_merge($cond, $time_range_cond);
			$threadlist = notice_find($real_cond, 1, $limit);
			// hook model_notice_find_in_time_range_end.php

			return $threadlist;
		}

		$all_toasts = [];
		/**
		 * @var array $notice_type_to_color 通知类型到Bootstrap颜色的映射
		 */
		$notice_type_to_color = [
			// * 重要通知 - warning (黄色)
			1 => 'warning',   // 公告
			66 => 'warning',  // 举报/反馈

			// * 互动通知 - primary (蓝色)
			2 => 'primary',   // 评论
			150 => 'primary', // 点赞
			155 => 'primary', // 收藏

			// * 提及通知 - info (青色)
			7 => 'info',      // 私信
			156 => 'info',    // @提及

			// * 系统通知 - success (绿色)
			3 => 'success',   // 系统通知
			39 => 'success',  // 任务
			233 => 'success', // 勋章

			// * 默认/其他 - secondary (灰色)
			99 => 'secondary', // 其他
			'default' => 'secondary'
		];
		/**
		 * @var array $title_map 通知标题映射
		 */
		$title_map = [
			0 => '📋 新通知',
			1 => '📢 公告通知',
			2 => '💬 新评论',
			3 => '⚙️ 系统通知',
			7 => '✉️ 新私信',
			39 => '✅ 任务通知',
			66 => '🚨 举报通知',
			99 => '📋 其他通知',
			150 => '👍 收到赞',
			155 => '⭐ 收藏通知',
			156 => '@ 提及',
			233 => '🎖️ 勋章获得'
		];

		if (!isset($_SESSION['htmx_last_noticelist_check'])) {
		}

		$start = $_SESSION['htmx_last_noticelist_check'] ?? time();
		$end = time();

		// ? 只在确定有未读消息的时候才查数据库，节约很多服务器资源
		if ($user['unread_notices'] != 0) {
			$new_notices_list = notice_find_in_time_range(['recvuid' => $uid, 'isread' => 0], $start, $end, 5);

			if (count($new_notices_list) !== 0) {
				foreach ($new_notices_list as $notice) {

					$base_title = $title_map[$notice['type']] ?? $title_map[0];

					$new_notice_color = $notice_type_to_color[$notice['type']] ?? $notice_type_to_color['default'];

					$all_toasts[] = [
						'type' => $new_notice_color,
						'title' => $notice['from_username'] . ' · ' . $base_title,
						'subtitle' => $notice['create_date_fmt'],
						'content' => $notice['message'],
						'delay' => 7000
					];
				}
				// ? 一次性发送所有toast数据
				header('HX-Trigger-After-Settle: ' . json_encode([
					'showToastMulti' => $all_toasts
				]));
			} else {
				// ? 有未读计数但查询不到消息，也许最新的消息还没到来，而现在就有过往的未读消息
				http_response_code(204);
			}
		} else {
			// ? 没有未读消息，直接返回204
			http_response_code(204);
		}
		// 最后将最后检查时间挪到现在
		$_SESSION['htmx_last_noticelist_check'] = time();

		die;
	}
	/*============  End of 获取最新消息轮询  =============*/


	if ($method == 'GET') {

		$active = 'notice';
		$notices = $user['notices'];

		$noticelist = notice_find_by_recvuid($uid, $page, $pagesize, $type);
		if ($type != 0) {
			$notices = notice_count(array('recvuid' => $uid, 'type' => $type));
		}

		$pagination = pagination(url("my-notice-$type-{page}"), $notices, $page, $pagesize);

		$header['title'] = lang('notice');
		$header['mobile_title'] = lang('notice');

		header("Hx-Trigger: " . json_encode(['updatePagination' => process_pagination_to_htmx_trigger($pagination, 'notice')]));

		include _include(APP_PATH . 'plugin/huux_notice/view/htm/my_notice.htm');
	} elseif ($method == 'POST') {
		$act = param('act');
		if ($act == 'readall') {
			// 全部已读
			$r = notice_update_by_recvuid($uid, array('isread' => 1));
			if ($r === FALSE) {
				message(-1, lang('notice_my_update_failed'));
			}
			if ($IS_HTMX && $return_html) {
				$noticelist = notice_find_by_recvuid($uid, $page, $pagesize, $type);
				header('HX-Trigger-After-Swap: ' . json_encode([
					'showToast' => [
						'type'    => 'success',
						'title'   => lang('tips_title'),
						'subtitle' => '',
						'content' => lang('notice_my_update_allread'),
						'delay'   => 3000
					]
				], JSON_FORCE_OBJECT));
				include _include(APP_PATH . 'plugin/huux_notice/view/htm/my_notice_list.inc.htm');
				//message(0, lang('notice_my_update_allread'));
				die;
			} else {
				message(0, array('a' => lang('notice_my_update_readed'), 'b' => lang('notice_my_update_allread')));
			}
		} elseif ($act == 'readone') {
			// 设置已读
			$nid = param('nid');
			$notice = notice__read($nid);
			if ($notice['isread'] == 1) {
				message(-1, lang('notice_my_update_readed'));
			}
			if ($notice['recvuid'] != $uid) {
				message(-1, lang('notice_my_error'));
			}

			$r = notice_update($nid, array('isread' => 1));

			if ($r === FALSE) {
				message(-1, lang('notice_my_update_failed'));
			}
			//message(0, lang('notice_my_update_readed'));
			http_response_code(204);
			die;
		} elseif ($act == 'delete') {
			// 单条删除
			$nid = param('nid');
			$notice = notice__read($nid);
			if ($notice['recvuid'] != $uid) {
				message(-1, lang('notice_my_error'));
			}

			$r = notice_delete($nid);
			if ($r === FALSE) {
				message(-1, lang('notice_my_update_failed'));
			}
			message(0, lang('notice_delete_notice_sucessfully'));
		} elseif ($act == 'deletearr') {
			// 多条删除
			$nidarr = param('nidarr', array(0));
			if (empty($nidarr)) {
				message(-1, '没有需要删除的消息');
			}

			$noticelist = notice_find_by_nids($nidarr);

			foreach ($noticelist as &$notice) {
				$nid = $notice['nid'];
				$recvuid = $notice['recvuid'];
				if ($uid == $recvuid) {
					notice_delete($nid);
				}
			}

			if ($IS_HTMX && $return_html) {

				$type = 0;
				$noticelist = notice_find_by_recvuid($uid, $page, $pagesize, 0);
				$notices = notice_count(array('recvuid' => $uid, 'type' => $type));
				$pagination = pagination(url("my-notice-$type-{page}"), $notices, $page, $pagesize);

				header("Hx-Trigger: " . json_encode(['updatePagination' => process_pagination_to_htmx_trigger($pagination, 'notice')]));
				header('HX-Trigger-After-Swap: ' . json_encode([
					'showToast' => [
						'type'    => 'success',
						'title'   => lang('tips_title'),
						'subtitle' => '',
						'content' => lang('notice_delete_notice_sucessfully'),
						'delay'   => 3000
					]
				], JSON_FORCE_OBJECT));
				include _include(APP_PATH . 'plugin/huux_notice/view/htm/my_notice_list.inc.htm');
				die;
			} else {
				message(0, lang('notice_delete_notice_sucessfully'));
			}
		} else {
			// 清空所有暂时不添加
			message(-1, lang('notice_my_error'));
		}
	}
}
