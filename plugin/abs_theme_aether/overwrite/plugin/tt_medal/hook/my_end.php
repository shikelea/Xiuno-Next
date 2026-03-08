<?php exit;
elseif($action == 'medal') {
    if($method == 'GET')
        include _include(APP_PATH.'plugin/tt_medal/view/htm/my_medal.htm');
    elseif($method=='POST'){
    $MESSAGE_FORCE_HX_TRIGGER_AFTER_SWAP = true;
    $subaction = param('do','');

        header('HX-Trigger: ' . json_encode(['closeModal' => true,], JSON_FORCE_OBJECT));
        switch($subaction){
            case 'buy':
        $_SERVER['ajax'] = false;
        $mid = param('mid',0);
                if($mid==0) {message(1,'勋章不存在！');}
                $r = medal_buy($uid,$mid);
                if($r==1) {message(0,'购买成功！');}
                elseif($r==-1) {message(-1,'勋章不存在！');}
                elseif($r==-2) {message(1,'用户不存在！');}
                elseif($r==-3) {message(1,'积分不足！');}
                elseif($r==-4) {message(1,'勋章已购买过！');}
            default:
                message(1,'Bad Request');
                break;
        }


    }
}elseif($action == 'medal_my'){

    if ($method == 'GET'){
    $subaction = param('do','');
    switch($subaction){
        case 'kill':
            $mid = param('mid',0);
            if($mid==0) {message(1,'勋章不存在！');}
            include _include(APP_PATH . 'plugin/tt_medal/view/htm/medal_kill.htm');
            break;
        default:
            include _include(APP_PATH . 'plugin/tt_medal/view/htm/my_medal_my.htm');
            break;
    }
}elseif($method=='POST'){
    $MESSAGE_FORCE_HX_TRIGGER_AFTER_SWAP = true;
    $subaction = param('do','');
        
            header('HX-Trigger: ' . json_encode(['closeModal' => true,], JSON_FORCE_OBJECT));

        switch($subaction){
            case 'kill':
        $_SERVER['ajax'] = false;
        $mid = param('mid',0);
                if($mid==0) {message(1,'勋章不存在！');}
                $k = medal_kill($uid,$mid);
                if($k==1) {message(0,'勋章已销毁');}
                elseif($k==-1) {message(1,'勋章不存在！');}
                elseif($k==-2) {message(1,'用户不存在！');}
                elseif($k==-3) {message(1,'该勋章不可销毁！');	}
                break;
            default:
                message(1,'Bad Request');
                break;
        }

	}
}elseif($action == 'medal_apply'){
    if($method == 'GET'){

        $mid = param('mid',0);
        $medal = medal_read($mid);
        if(!$medal) {
            message(-1,'勋章不存在！');
        }
        include _include(APP_PATH.'plugin/tt_medal/view/htm/medal_open.htm');
    }elseif($method == 'POST'){
    $MESSAGE_FORCE_HX_TRIGGER_AFTER_SWAP = true;
    $mid = param('mid',0);
        $reason = param('reason','');

        $_SERVER['ajax'] = false;
        header('HX-Trigger: ' . json_encode(['closeModal' => true,], JSON_FORCE_OBJECT)); 

        if($mid==0) {message(-1,'勋章不存在！');}
        if(empty(trim($reason))) {message(1,'申请理由不能为空！');}
        $a=medal_apply($uid,$mid,$reason);
        if($a==1) {message(0,'申请成功');}
        elseif($action==-1){ message(-1,'勋章不存在！');}
        elseif($a==-2) {message(1,'用户不存在！');}
        elseif($a==-3) {message(2,'申请正在等待审核，请耐心等待');}
    }
}
?>