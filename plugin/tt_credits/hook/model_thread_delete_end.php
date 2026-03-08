 <?php exit;
$set=setting_get('tt_credits');
user_update($uid, array('credits-'=>$set['thread_exp'], 'golds-'=>$set['thread_gold'],'rmbs-'=>$set['thread_rmb']));
$uid AND user_update_group($uid);
?>