<?php

!defined('DEBUG') and exit('Access Denied.');

if (!isset($IS_HTMX)) {
    $IS_HTMX = isset($_SERVER['HTTP_HX_REQUEST']) || (isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'] == 'true');
}

$header['title'] = '发送私信';

if ($method == 'GET') {

    include _include(APP_PATH.'plugin/ax_notice_sx/htm/sx.htm');

} else {

    $to_uid = param('to_uid', '');
    if (empty($to_uid)) {
        message(1, jump('用户不能为空', 'back'));
    }

    $ax_message = param('ax_message', '');
    if (empty($ax_message)) {
        message(1, jump('发送信息不能为空', 'back'));
    }

    $to_user = user_read($to_uid);
    if (empty($to_user)) {
        message(1, jump('用户不存在，请确认后重试', 'back'));
    }

    $message = "<div class='comment-info'>发来了消息</div><div class='single-comment'>".$ax_message."</div>";


    notice_send($uid, $to_uid, $message, 7); 
    
    if($IS_HTMX) {
        $_SERVER['ajax'] = false;
        header('HX-Trigger-After-Swap: ' . json_encode(['closeModal' => true,], JSON_FORCE_OBJECT));
        message(0, '发送私信成功');
    } else {
        
        message(0, '发送私信成功');
    }
    


}