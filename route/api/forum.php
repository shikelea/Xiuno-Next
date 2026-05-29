<?php

!defined('DEBUG') AND exit('Access Denied.');

$action = param(2);

if($action == 'list') {

	$forumlist_allow = forum_list_access_filter($forumlist, $gid, 'allowread');
	$list = array();
	if($forumlist_allow) {
		foreach($forumlist_allow as $forum) {
			$list[] = forum_safe_info($forum);
		}
	}

	api_output(0, 'OK', array(
		'total' => count($list),
		'list' => $list,
	));

} elseif($action == 'read') {

	$fid = param('fid', 0);
	$forum = forum_read($fid);
	if(empty($forum)) api_output(-1, lang('forum_not_exists'));
	if(!forum_access_user($fid, $gid, 'allowread')) api_output(-1, lang('insufficient_privilege'));

	api_output(0, 'OK', forum_safe_info($forum));

} else {
	api_output(-1, 'Unknown Action');
}

?>
