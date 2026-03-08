elseif($action == 'credits') {
    if($method == 'GET')
        include _include(APP_PATH.'plugin/tt_credits/view/htm/my_credits.htm');
}elseif($action == 'purchased') {
    if($method == 'GET'){
        $pagesize = 20;
        $page = param(2, 1);
        $cond = array('uid'=>$uid);
        $threadlist = credits_thread_purchased_find_by_uid($uid, $page, $pagesize);
        $pagination = pagination(url("my-purchased-{page}"), credits_purchased_count($cond), $page, $pagesize);
        include _include(APP_PATH.'plugin/tt_credits/view/htm/my_purchased.htm');
    }
}elseif($action == 'trade') {
    if($method == 'GET')
        include _include(APP_PATH.'plugin/tt_credits/view/htm/my_trade.htm');
    elseif($method=='POST'){
        $op = param('op');
        if($op=='n'){
            $set=setting_get('tt_credits');$e_rmb = param('e_rmb'); $my_rmbs=$user['rmbs'];$my_golds=$user['golds'];$min=$set['min'];$e_rmb_raw=$e_rmb;
            $e_rmb *= $set['exchange_n'];
            if($e_rmb<$min) {message(-1, '最低兑换金额：¥'.($min/100.0).'，您兑换的金额不足。');die();}
            if($e_rmb<=0 ) {message(-1, lang('ERROR'));die();}
            preg_replace('/[^0-9-]+/','',$e_rmb);
            if($my_rmbs<$e_rmb) {message(-1, lang('credit_no_enough'));die();}
            if(empty($uid)||empty($e_rmb)){message(-1, "ERROR");die();}
            $recent_query = db_find_one('user_pay',array('uid'=>$uid,'type'=>'6'),array('time'=>-1));
            $now_time = time();
            if($now_time-$recent_query['time']<=600) {message(-1, "每10分钟只能兑换一次，您兑换过于频繁！");die();}
            $my_golds+= $e_rmb_raw;
            $my_rmbs -= $e_rmb;
            db_insert('user_pay',array('uid'=>$uid,'status'=>1,'num'=>$e_rmb,'type'=>'6','credit_type'=>'3','code'=>'','time'=>time()));
            user_update($user['uid'],array('rmbs'=>$my_rmbs,'golds'=>$my_golds));
            user_update_group($user['uid']);
            message(0, lang('update_successfully'));
        }elseif($op=='c'){
            $set=setting_get('tt_credits');$e_golds=param('e_golds_c'); $my_golds = $user['golds'];$my_rmbs=$user['rmbs'];$min=$set['min'];$e_golds_raw=$e_golds;
            $e_golds*= $set['exchange_c'];
            if(empty($uid)||empty($e_golds)){message(-1, "ERROR");die();}
            if($e_golds<$min*$set['exchange_c']) {message(-1, '最低兑换金币：'.($min*$set['exchange_c']).'，您兑换的金额不足。');die();}
            if($e_golds<=0 ){message(-1, lang('ERROR'));die();}
            preg_replace('/[^0-9-]+/','',$e_golds);
            if($my_golds<$e_golds){message(-1, lang('credit_no_enough'));die();}
            $recent_query = db_find_one('user_pay',array('uid'=>$uid,'type'=>'6'),array('time'=>-1));
            $now_time = time();
            if($now_time-$recent_query['time']<=600) {message(-1, "每10分钟只能兑换一次，您兑换过于频繁！");die();}
            $my_golds-=$e_golds;
            $my_rmbs+=$e_golds_raw;
            db_insert('user_pay',array('uid'=>$uid,'status'=>1,'num'=>$e_golds,'type'=>'6','credit_type'=>'2','code'=>'','time'=>time()));
            user_update($user['uid'],array('rmbs'=>$my_rmbs,'golds'=>$my_golds));
            user_update_group($user['uid']);
            message(0, lang('update_successfully'));
        }elseif($op=='t'){
            $to_username=param('trans_username'); $to_num=param('trans_num'); $credits_type=param('trans_credits');
            if($to_num<=0) {message(-1, "请输入正数!");die();}
            if(empty($to_username)) {message(-1, "用户名不能为空!");die();}
            if(empty($credits_type)) {message(-1, "积分段为空!");die();}
            if(db_count('user',array('username'=>$to_username))<=0){message(-1, "用户不存在!");die();}
            if($user['username']==$to_username) {message(-1, "不能自己给自己转账！");die();}
            $credits_name =get_credits_name_by_type($credits_type);
            $to_user = db_find_one('user',array('username'=>$to_username));
            if($user[$credits_name]<$to_num) {message(-1, "您的余额不足,请充值!");die();}
            db_update('user',array('username'=>$to_username),array($credits_name.'+'=>$to_num));
            db_update('user',array('uid'=>$uid),array($credits_name.'-'=>$to_num));
            db_insert('user_pay',array('uid'=>$to_user['uid'],'status'=>1,'num'=>$to_num,'type'=>13,'credit_type'=>$credits_type,'time'=>time(),'code'=>''));
            db_insert('user_pay',array('uid'=>$uid,'status'=>1,'num'=>$to_num,'type'=>12,'credit_type'=>$credits_type,'time'=>time(),'code'=>''));
            message(0,'转账成功!');
        }
    }
}
elseif($action == 'record') {
    if($method == 'GET')
        include _include(APP_PATH.'plugin/tt_credits/view/htm/my_record.htm');
}