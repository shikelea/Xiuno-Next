<?php

!defined('DEBUG') AND exit('Access Denied.');

$action = param(2);

if($action == 'thread') {

	$keyword = trim(param('keyword', '', FALSE));
	if($keyword === '') $keyword = trim(param('q', '', FALSE));
	$keyword = trim(str_replace(array('%', '_'), '', $keyword));
	if($keyword === '') api_output(-1, 'Keyword is empty');
	if(xn_strlen($keyword) < 2) api_output(-1, 'Keyword is too short');
	if(xn_strlen($keyword) > 64) $keyword = xn_substr($keyword, 0, 64);

	list($page, $pagesize) = api_page_params(20, 50);
	$result = thread_search_by_subject($keyword, $gid, $page, $pagesize);

	api_output(0, 'OK', array(
		'keyword' => $keyword,
		'page' => $page,
		'pagesize' => $pagesize,
		'total' => $result['total'],
		'list' => $result['list'],
	));

} else {
	api_output(-1, 'Unknown Action');
}

?>
