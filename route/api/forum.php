<?php

!defined('DEBUG') AND exit('Access Denied.');

$action = param(2);

if($action == 'list') {
	api_is_v1() AND api_method_required('GET');

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
	api_is_v1() AND api_method_required('GET');

	$fid = param('fid', 0);
	$forum = forum_read($fid);
	if(empty($forum)) api_output(-1, lang('forum_not_exists'), array(), 404);
	if(!forum_access_user($fid, $gid, 'allowread')) api_output(-1, lang('insufficient_privilege'), array(), 403);

	api_output(0, 'OK', forum_safe_info($forum));

} else {
	api_output(-1, 'Unknown Action', array(), 404);
}

?>
