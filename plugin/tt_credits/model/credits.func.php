<?php
function credits_get_content_type_by_name($name){
    if($name==lang('credits1')) return '1';
    elseif ($name==lang('credits2')) return '2';
    elseif ($name==lang('credits3')) return '3';
    else return '1';
}
function credits_thread_purchased_find_by_uid($uid, $page = 1, $pagesize = 20) {
    $tids = db_find('paylist', array('uid'=>$uid), array('paytime'=>-1), $page, $pagesize, 'tid');
    $threadlist = array();
    foreach($tids as $_tid)
        $threadlist[$_tid['tid']] = thread_read_cache($_tid['tid']);
    if($threadlist) foreach($threadlist as &$thread) thread_format($thread);
    return $threadlist;
}
function credits_purchased_count($cond = array()) {
    $n = db_count('paylist', $cond);
    return $n;
}
function get_credits_name_by_type($type){
    switch($type) {
        default:return 'credits';break;
        case '1':return 'credits';break;
        case '2':return 'golds';break;
        case '3':return 'rmbs';break;
    }
}
function credits_get_text($type,$credits,$process=1){
    $rtn=lang('credits'.$type).' ';
    if($process&&$type=='3') $rtn.= sprintf("%.2f",$credits/100.0);
    else $rtn.=$credits;
    return $rtn;
}
function credits_get_icon_html($type){
    switch($type){
        case 1:$rtn='<i class="icon-flask" aria-hidden="true"></i>';break;
        case 2:$rtn='<i class="icon-diamond" aria-hidden="true"></i>';break;
        case 3:$rtn='<i class="icon-jpy" aria-hidden="true"></i>';break;
        default: $rtn='<i class="icon-diamond" aria-hidden="true"></i>';break;
    }
    return $rtn;
}
$g_credits_item_array = array('thread_exp','post_exp','down_exp','digest1_exp','digest2_exp','digest3_exp','thread_gold','post_gold','down_gold','digest1_gold','digest2_gold','digest3_gold','thread_rmb','post_rmb','down_rmb','digest1_rmb','digest2_rmb','digest3_rmb');
$g_credits_type_array=array('支付宝充值','微信充值','提现','充值审核','购买主题','使用卡密','兑换','VIP充值','购买邀请码','领取红包','发送红包','撤回红包','发送转账','收到转账','打赏','收到打赏','悬赏','收到悬赏','购买勋章','QQ充值','视频购买');
$g_credits_status_array=array('失败','审核中','成功','等待充值');
?>