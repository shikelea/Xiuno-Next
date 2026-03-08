 <?php exit;
	if(!$post['isfirst']) {
		$set=setting_get('tt_credits');
		$uid AND user_update($uid, array('credits-'=>$set['post_exp'], 'golds-'=>$set['post_gold'],'rmbs-'=>$set['post_rmb']));
        $uid AND user_update_group($uid);
	}
?>