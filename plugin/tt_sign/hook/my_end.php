<?php exit;
elseif($action == 'sign') {
    if($method == 'POST') {
        $set = setting_get('tt_sign'); $msg2='';$update_list = array('credits+'=>0,'golds+'=>0,'rmbs+'=>0);
        if(empty($uid)){message(-1,"拉取用户信息失败");die();}
        $today0 = strtotime(date('Y-m-d',time()))-1;
        $user_signed = db_count('sign',array('uid'=>$uid,'time'=>array('>'=>$today0)));
        if($user_signed ) {message(-1,"您已经签到过了！");die();}
        $signed = db_count('sign',array('time'=>array('>'=>$today0)));
        if($signed===FALSE) $signed=0;
        $msg2 .= '您是第'.($signed+1).'名签到！<br>';
        if($signed==0){ $update_list['credits+']+= $set['first_credits'];$update_list['golds+']+= $set['first_golds'];$msg2.='[首签奖励]';}
        $beginYesterday=mktime(0,0,0,date('m'),date('d')-1,date('Y'))-1;
        $endYesterday=mktime(0,0,0,date('m'),date('d'),date('Y'));
        $signed_yesterday = db_count('sign',array('uid'=>$uid,'time'=>array('>'=>$beginYesterday,'<'=>$endYesterday)));
        if($signed_yesterday){ $update_list['credits+']+= $set['con_credits'];$update_list['golds+']+= $set['con_golds'];$msg2.='[连签奖励]';}
        $update_list['credits+']+= $set['credits_from']==$set['credits_to']? $set['credits_to']: mt_rand($set['credits_from'],$set['credits_to']);
        $update_list['golds+']+= $set['golds_from']==$set['golds_to']? $set['golds_to']: mt_rand($set['golds_from'],$set['golds_to']);
        if(date('d')==1)
        {$beginLastmonth = strtotime(date('Y-m-01', strtotime('-1 month')))-1; $endLastmonth = strtotime(date('Y-m-t', strtotime('-1 month')))+1;
            $days = date('t', strtotime('-1 month'));$_sql_month = db_count('sign',array('uid'=>$uid,'time'=>array('>'=> $beginLastmonth, '<'=>$endLastmonth)));if($_sql_month===FALSE) $_sql_month=0;
            if($_sql_month==$days) {$msg2.='[月满勤奖励]';$update_list['credits+']+= $set['month_credits'];$update_list['golds+']+= $set['month_golds'];}}
        if(date('w')==6)
        {
            $beginWeek = strtotime(date("Y-m-d H:i:s",mktime(0, 0 , 0,date("m"),date("d")-date("w"),date("Y"))))-1;
            $endWeek = strtotime(date("Y-m-d H:i:s",mktime(23,59,59,date("m"),date("d")-date("w")+6,date("Y"))))+1;
            $_sql_week = db_count('sign',array('uid'=>$uid,'time'=>array('>'=> $beginWeek, '<'=>$endWeek)));
            if($_sql_week===FALSE) $_sql_week=0;
            if($_sql_week==7) {$msg2.='[周满勤奖励]';$update_list['credits+']+= $set['week_credits'];$update_list['golds+']+= $set['week_golds'];}
        }
        db_insert('sign',array('uid'=>$uid,'credits'=>$update_list['credits+'],'golds'=>$update_list['golds+'],'rmbs'=>$update_list['rmbs+'],'time'=>time()));
        if(isset($update_list['credits+'])) $msg[lang('credits1')] = $update_list['credits+'];
        if(isset($update_list['golds+'])) $msg[lang('credits2')] = $update_list['golds+'];
        $msg1='';$flag=0;
        foreach($msg as $k=>$v) {if($flag==1)$msg1.='、';$msg1.=$k.':'.$v;$flag=1;}
        $last_login_date = $user['login_date'];
        $update_list['login_date']=time();
        user_update($uid,$update_list);
        // hook tt_sign_user_update_after.php
        message('0','签到成功！'.$msg2.'<br>'.$msg1);
    }
}
?>