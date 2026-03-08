
    if ($IS_HTMX && $_SERVER['HTTP_HX_TARGET'] === 'S4') {
        include _include(APP_PATH . 'plugin/abs_theme_aether/template_parts/user/content/user_info_s4.htm');
        die;
    }
