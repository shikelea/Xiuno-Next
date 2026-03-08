$before_digest = $arr['digest']; $credits_set = setting_get('tt_credits');
if($before_digest==0) $before_credits = array('credits'=>0,'golds'=>0,'rmbs'=>0 );
else $before_credits = array('credits'=>$credits_set['digest'.$before_digest.'_exp'] ,'golds'=>$credits_set['digest'.$before_digest.'_gold'] ,'rmbs'=>$credits_set['digest'.$before_digest.'_rmb'] );