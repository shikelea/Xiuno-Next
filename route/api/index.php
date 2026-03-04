<?php

!defined('DEBUG') AND exit('Access Denied.');

// 默认 API 响应
api_output(0, 'Welcome to Xiuno Next API', [
    'version' => $conf['version'],
    'time' => time(),
    'docs' => 'https://github.com/shikelea/Xiuno-Next'
]);

?>
