<?php

!defined('DEBUG') AND exit('Access Denied.');

$action = param(2);

if($action == 'thread') {
	api_is_v1() AND api_method_required('GET');

	$keyword = trim(param('keyword', '', FALSE));
	if($keyword === '') $keyword = trim(param('q', '', FALSE));
	$keyword = trim(str_replace(array('%', '_'), '', $keyword));
	if($keyword === '') api_output(-1, 'Keyword is empty', array(), 422);
	if(xn_strlen($keyword) < 2) api_output(-1, 'Keyword is too short', array(), 422);
	if(xn_strlen($keyword) > 64) $keyword = xn_substr($keyword, 0, 64);

	list($page, $pagesize) = api_page_params(20, 50);
	$result = thread_search_by_subject($keyword, $gid, $page, $pagesize);
	if(api_is_v1()) $result['list'] = array_values($result['list']);

	api_output(0, 'OK', array(
		'keyword' => $keyword,
		'page' => $page,
		'pagesize' => $pagesize,
		'total' => $result['total'],
		'list' => $result['list'],
	));

} else {
	api_output(-1, 'Unknown Action', array(), 404);
}

?>
