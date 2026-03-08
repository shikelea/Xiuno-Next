
elseif ($action == 's_customizer') {
        if ($method == 'GET') {
            if(!isset($abs_theme_aether_setting)) {
                $abs_theme_aether_setting = setting_get('abs_theme_aether_setting');
            }
            include _include(APP_PATH . 'plugin/abs_theme_aether/template_parts/my/content/customizer_s3.htm');
            /*
            if($abs_theme_aether_setting['ui_tweek']['user']['show_customizer']){
            } else {
                message(1,'暂不可用 | Unavailable');
            }
            */
        } else {
            message(1,"Bad Request");
        }
    }