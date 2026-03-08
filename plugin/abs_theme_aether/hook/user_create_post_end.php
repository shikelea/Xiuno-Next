
if($IS_HTMX) {
    header('HX-Retarget: #form');
    include _include(APP_PATH.'plugin/abs_theme_aether/template_parts/auth/content/register_success_s6.htm');
    die;
}