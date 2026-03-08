<?php exit;
if ($offer_status&& $offer_num>0){
    db_update('user',array('uid'=>$uid),array(get_credits_name_by_type($set_offer['credits_type']).'-'=>$offer_num));
    db_insert('user_pay',array('uid'=>$uid,'status'=>1,'num'=>$offer_num,'type'=>16,'credit_type'=>$set_offer['credits_type'],'time'=>time(),'code'=>''));
    db_update('thread', array('tid' => $tid), array('offerNum' =>$offer_num,'offerStatus'=>0));
}
?>