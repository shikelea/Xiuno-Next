$set=setting_get('tt_credits');
$credits = $set['post_exp'];$credits_op = $credits>0?'+':'';
$golds = $set['post_gold'];$golds_op = $golds>0?'+':'';
$rmbs=$set['post_rmb'];$rmbs_op = $rmbs>0?'+':'';
if(($credits<0&&($user['credits']+$credits<0))||($golds<0&&($user['golds']+$golds<0))||($rmbs<0&&($user['rmbs']+$rmbs<0)))
    {message(-1,lang('credit_no_enough'));die();}
$c_limit =$set['limit'] ; $add_credit=1;
if($c_limit!=0) {
    $todayTime = strtotime(date('Y-m-d',time()))-1;
    $todayThread = db_count('post',array('create_date' => array('>'=>$todayTime),'uid'=>$uid,'isfirst'=>'0'));
    if($c_limit<=$todayThread) $add_credit=0;
}