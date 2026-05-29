<?php

!defined('DEBUG') AND exit('Access Denied.');

// API 路由分发。兼容旧路径 api-{controller}-{action} 与新路径 api-v1-{controller}-{action}。
$api_version = 'legacy';
$action = param(1, 'index');

if($action == 'v1') {
	$api_version = 'v1';
	$action = param(2, 'index');
	$_REQUEST[1] = $action;
	$_REQUEST[2] = param(3, 'index');
}

$_SERVER['api_version'] = $api_version;

if(!preg_match('/^\w{1,32}$/', $action) || ($api_version != 'legacy' && !preg_match('/^\w{1,32}$/', $_REQUEST[2]))) {
	api_output(404, 'API Not Found');
}

// 自动加载对应的 API 文件
$api_file = APP_PATH."route/api/$action.php";

if(is_file($api_file)) {
	include $api_file;
} else {
	// 404 Not Found
	api_output(404, 'API Not Found');
}

?>
