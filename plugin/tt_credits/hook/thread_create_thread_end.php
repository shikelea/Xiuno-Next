<?php exit;
if($group['allowsell']=="1") {
    $content_num_status = param('content_num_status');
    $content_num = param('content_num');
    if($content_num < 0 ){//判断购买主题货币值小于零
    $content_num = 1;//小于零强制写为一
}
    $content_type = credits_get_content_type_by_name(param('content_type'));
    if ($content_num_status && $content_num)
        db_update('thread', array('tid' => $tid), array('content_buy' => $content_num, 'content_buy_type' => $content_type));
}
$update_array = array();
if((($add_credit==1)||($add_credit==0&& $credits<0))&&$credits!=0) $update_array['credits+']=$credits;
if((($add_credit==1)||($add_credit==0&& $golds<0))&&$golds!=0) $update_array['golds+']=$golds;
if((($add_credit==1)||($add_credit==0&& $rmbs<0))&&$rmbs!=0) $update_array['rmbs+']=$rmbs;
$uid AND $update_array AND user_update($uid, $update_array);
$uid AND $update_array AND $user['gid']>=100 AND user_update_group($uid);
$message = '';
isset($update_array['credits+']) AND $message .= lang('credits1').$credits_op.$credits.' ' ;
isset($update_array['golds+']) AND $message .= lang('credits2').$golds_op.$golds.' ' ;
isset($update_array['rmbs+']) AND $message .= lang('credits3').$rmbs_op.$rmbs ;
message(0, lang('create_thread_sucessfully').' '.$message);
?>