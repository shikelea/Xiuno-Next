//<?php
	if ($action == 's_pm') {
		if ($method == 'POST') {
			// hook user_pm_post_start.php

			//鉴权-只有登录用户才能发
			if ($uid && param('sid') != session_id()) {
				message(-1, 'Forbidden');
			}
			//鉴权-用户组
			if ($gid == 7 || $uid == 0) {
				message(-1, lang('insufficient_privilege'));
			}

			$return_html = boolval(param('return_html', 0));

			// 发往用户的UID
			$pm_to_uid = param('pm_to_uid', '');
			if (empty($pm_to_uid)) {
				message(-1, lang('user_not_exists'));
			}
			if (!function_exists('is_chatroom_plugin_installed')) {
				function is_chatroom_plugin_installed() {
					return file_exists(APP_PATH . 'plugin/msto_chat/route/app/MSG.txt');
				}
			}


			if (is_chatroom_plugin_installed() && $pm_to_uid === 'chatroom89757') {
				// 往聊天室发送内容
				include _include(APP_PATH . 'plugin/msto_chat/route/app/app.php');
				$pm_message = param('pm_message', '');
				$arr = [
					'msg' => $pm_message,
					'name' => $user['username'],
					'key' => strip_tags(urldecode($_COOKIE[KEYS . '_key'])),
					'pic' => $user['avatar_url'],
					'type' => 'msg',
				];
				if (strlen($pm_message) === 0) {
					message(1, '请输入内容');
				} else {
					$str = json_encode($arr, JSON_UNESCAPED_UNICODE);
					file_put_contents(APP_PATH . 'plugin/msto_chat/route/' . MSGFILE, $str . "\n", FILE_APPEND | LOCK_EX);

					if ($return_html) {
						$temp_notice = array(
							'nid' => 0,
							'from_myself' => true,
							'isread' => true,
							'create_date' => time() - 1,
							'fromuid' => $uid,
							'from_username' => $user['username'],
							'recvuid' => 0,
							'message' => $pm_message,
							'type' => 7,
							'from_myself' => true,
						);
						notice_format($temp_notice);
						$temp_notice['name'] = $user['username'];
						$noticelist = array($temp_notice);
						ob_start();
						include _include(APP_PATH . 'plugin/abs_theme_aether/template_parts_plugin/huux_notice/my_notice_list_new.inc.htm');
						$pm_message_result_html = ob_get_clean();
						$pm_message_result_html = str_replace('chat-message-meta', 'chat-message-meta d-none', $pm_message_result_html);

						message(0, array(
							'message' => lang('user_send_sucessfully'),
							'html' => $pm_message_result_html,
						));
					} else {
						message(0, lang('user_send_sucessfully'));
					}
				}
			} else {
				$pm_to_uid = intval($pm_to_uid);
				// 普通私信内容
				if (user_read_cache($pm_to_uid)['uid'] == 0) {
					message(-1, lang('user_not_exists'));
				}
				$pm_to_user = user_read_cache($pm_to_uid);

				// 发送的信息
				$pm_message = param('pm_message', '');
				if (empty($pm_message)) {
					message(-1, lang('data_is_empty'));
				}

				$pm_message = trim($pm_message);

				// hook user_pm_post_notice_send_before.php

				$r = notice_send($uid, $pm_to_uid, $pm_message, 7);
				if (boolval($r) === TRUE) {
					if ($return_html) {
						$temp_notice = array(
							'from_myself' => true,
							'isread' => true,
							'create_date' => time() - 1,
							'fromuid' => $uid,
							'from_username' => $user['username'],
							'recvuid' => $pm_to_uid,
							'message' => $pm_message,
							'type' => 7
						);
						notice_format($temp_notice);
						$temp_notice['name'] = $user['username'];
						$noticelist = array($temp_notice);
						ob_start();
						include _include(APP_PATH . 'plugin/abs_theme_aether/template_parts_plugin/huux_notice/my_notice_list_new.inc.htm');
						$pm_message_result_html = ob_get_clean();


						if ($IS_HTMX) {
							$HOLD_ON_FOR_LATER_HTML = true;
							echo $pm_message_result_html;
							message(0, lang('user_send_sucessfully'));
							die;
						} else {
							message(0, array(
								'message' => lang('user_send_sucessfully'),
								'html' => $pm_message_result_html,
							));
						}
					} else {
						message(0, lang('user_send_sucessfully'));
					}
				} else {
					message(1, 'Something wrong...');
				}

				// hook user_pm_post_end.php

				message(0, lang('user_send_sucessfully'));
			}
		} else {
			message(1, 'Bad Request');
		}
	}
