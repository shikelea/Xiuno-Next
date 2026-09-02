<?php

!defined('DEBUG') AND exit('Access Denied.');

// 默认 API 响应
api_is_v1() AND api_method_required('GET');
api_output(0, 'Welcome to Xiuno Next API', [
    'version' => $conf['version'],
    'api_version' => isset($_SERVER['api_version']) ? $_SERVER['api_version'] : 'legacy',
    'time' => time(),
    'docs' => 'https://github.com/shikelea/Xiuno-Next'
]);

?>
