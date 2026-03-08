<?php exit;
$return_html = param('return_html', 0);
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
if($return_html) {
		$filelist = array();ob_start();
		include _include(APP_PATH.'view/htm/post_list.inc.htm');
		$s = ob_get_clean();message(0, $s);
} else {
		$message = $message ? $message : lang('create_post_sucessfully'); message(0, $message);
}
?>