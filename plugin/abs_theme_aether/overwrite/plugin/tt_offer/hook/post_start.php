<?php exit;
if($action == 'offer_select' && $method=='POST'){
    if(empty($uid)||empty($user)) { message(-1,'拉取信息失败！');die();}
    $_pid = param(2,0);
    if(empty($_pid)){ message(-1,'数据错误！不合法！');die();}
    $_post = post_read($_pid);
    $_post_uid = $_post['uid'];
    if(empty($_post)){ message(-1,'数据错误！不合法！');die();}
    $_thread = thread_read($_post['tid']);
    if(empty($_thread)){ message(-1,'数据错误！不合法！');die();}
    if($_thread['offerStatus']!='0') { message(-1,'已经悬赏过该主题！');die();}
    $_thread_uid = $_thread['uid'];
    if($uid!=$_thread_uid && $user['gid']!=1){ message(-1,'无操作权限！');die();}
    if($uid==$_post_uid){ message(-1,'您不能悬赏自己的回帖！');die();}
    $set_offer=setting_get('tt_offer');
    db_update('thread',array('tid'=>$_post['tid']),array('offerStatus'=>$_pid));
    db_update('user',array('uid'=>$_post_uid),array(get_credits_name_by_type($set_offer['credits_type']).'+'=>$_thread['offerNum']));
    db_insert('user_pay',array('uid'=>$_post_uid,'status'=>1,'num'=>$_thread['offerNum'],'type'=>17,'credit_type'=>$set_offer['credits_type'],'time'=>time(),'code'=>$_thread['subject']));
    message(0,'悬赏成功！');
}
?>