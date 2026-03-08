<?php exit;
$spay_url = url('thread-sPay-'.$tid);
$mycredits_url = url('my-credits');
if($thread['content_buy_type']=='3') {$thread['content_buy']/=100.0;}

if($route=='mip')
    $html_pay='<strong>您好，本帖含有付费内容，请您点击下方“查看完整版网页”获取！</strong>';
else
    
$html_pay='<div class="alert alert-secondary" role="alert"><span class="text-center d-block"> '.lang("have_pay").'</span><span style="text-align:center;display: block;padding: 20px 0px;">'.lang("pay_price").'<span style="font-weight: bold;">'.$thread['content_buy'].lang('credits'.$thread['content_buy_type']).'</span></span><div class="w-100 text-center"><button id="b_p" type="submit"  class="btn btn-warning text-white" data-loading-text="'.lang('submiting').'..." data-active="'.url('thread-cPay-'.$tid).'">'.lang("purchase").'</button></div><div style="clear:both;"></div></div>';
$preg_pay = preg_match_all('/\[ttPay\](.*?)\[\/ttPay\]/i',$first['message_fmt'],$array);
$first['purchased']='1';
$content_pay = db_find_one('paylist', array('tid' => $tid, 'uid' => $uid, 'type' => 1)); $is_set=0;
if($thread['content_buy']){
	if($preg_pay){
		$array_count = count($array[0]);
		for($i=0;$i<$array_count;$i++){
			$a = $array[0][$i];
			$b = '<div class="alert alert-success" role="alert"> <i class="icon-shopping-cart" style="color:green;" aria-hidden="true" title="ttPay"></i> '.lang("see_paid").'<div style="float:right;"><a href="'.$spay_url.'">查看购买记录</a></div><hr/>'.$array[1][$i].'</div>';
            // hook credits_verify_start.php
			if($content_pay||$thread['uid']==$uid) $first['message_fmt'] = str_replace($a,$b,$first['message_fmt']);
            // hook credits_verify_end.php
			else $first['message_fmt'] = str_replace($a,$is_set==0?$html_pay:'',$first['message_fmt']); $is_set=1;$first['purchased']='0';
		}
	}
}else{
        $first['message_fmt'] = str_replace('[ttPay]','',$first['message_fmt']);
        $first['message_fmt'] = str_replace('[/ttPay]','',$first['message_fmt']);
}
?>