<?php exit;
if($action == 'cPay'){
	$tid = param(2);
	$content_pay = db_find_one('paylist', array('tid' => $tid, 'uid' => $uid, 'type' => 1));
	if(!$content_pay){
		if(!$user) message(-2, lang('login_first'));
		$thread = thread_read($tid);
		empty($thread) AND message(-1, lang('thread_not_exists:'));
		$operation_credit_area;
		switch($thread['content_buy_type']) {
            case '1':$operation_credit_area='credits';break;
            case '2':$operation_credit_area='golds';break;
            case '3':$operation_credit_area='rmbs';break;
            default:$operation_credit_area='rmbs';break;
        }
// 		if(vip__isvip($user['vip_end']))
// 		{
// 			$thread['content_buy'] *= 0.8;
// 		}
		if($thread['content_buy']!=0 && $user[$operation_credit_area]<$thread['content_buy']){
			message(-3, str_replace(lang('credits'),'<span style="color:dodgerblue;font-weight:bold;">'.lang('credits'.$thread['content_buy_type']).'</span>',lang('credit_no_enough')));}
		db_insert('paylist',array('tid' => $tid, 'uid' => $uid, 'credit_type'=>(int)$thread['content_buy_type'],'num' => (int)$thread['content_buy'], 'type' => 1, 'paytime' => time()));
		
		$now_golds = $user[$operation_credit_area]-$thread['content_buy'];
		db_update('user', array('uid' => $uid), array($operation_credit_area => $now_golds));
		$origin = db_find_one('user', array('uid' => $thread['uid']));
		$current = $origin[$operation_credit_area]+$thread['content_buy'];
		db_update('user', array('uid' => $thread['uid']), array($operation_credit_area => $current));
        $uid AND $user['gid']>=100 AND user_update_group($uid);
        $uid AND db_insert('user_pay',array('uid'=>$uid,'status'=>1,'num'=>$thread['content_buy'],'type'=>'4','credit_type'=>$thread['content_buy_type'],'code'=>$thread['tid'].','.$thread['subject'],'time'=>time()));
        $setting_credits = setting_get("tt_credits");
        if($setting_credits['buy_push']=='1'){
            notice_send($uid, $thread['uid'], "{$user['username']}购买了您的主题【{$thread['subject']}】！", 1001);
        }
		message(0, lang('pay_success'));
	}else
		message(0, lang('pay_success'));
}elseif($action == 'sPay') {
    $tid = param(2);
    include _include(APP_PATH . 'plugin/tt_credits/view/htm/tt_buy_list.htm');
    return;
}
?>