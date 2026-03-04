<?php

!defined('DEBUG') AND exit('Access Denied.');

// API 路由分发
$action = param(1, 'index');

// 自动加载对应的 API 文件
$api_file = APP_PATH."route/api/$action.php";

if(is_file($api_file)) {
	include $api_file;
} else {
    // 404 Not Found
    api_output(404, 'API Not Found');
}

?>
