<?php !defined('DEBUG') AND exit('Access Denied.');
$action = param(3);
if(empty($action)) {
    if ($method == 'GET') {//设置页面
        include _include(APP_PATH . 'plugin/tt_sign/setting.htm');
    } elseif ($method == "POST") { $set=setting_get('tt_sign'); $set_arr = array('credits_from','credits_to','golds_from','golds_to','rmbs_from','rmbs_to','first_credits','first_golds','first_rmbs','con_credits','con_golds','con_rmbs','week_credits','week_golds','week_rmbs','month_credits','month_golds','month_rmbs');$ii=count($set_arr);
        for($i=0;$i<$ii;$i++)
            $set[$set_arr[$i]] = param($set_arr[$i]);
        setting_set('tt_sign',$set);
        message(0,'设置成功！');
    }
}
?>