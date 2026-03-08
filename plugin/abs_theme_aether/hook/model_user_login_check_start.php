
global $IS_HTMX, $HTMX_TARGET, $SHEET_MODE, $user;
if($IS_HTMX && empty($user)) {
    $HTMX_TARGET = 'S6-Body';
    $SHEET_MODE = 'S6_BODY_ONLY';
    header('HX-Retarget: #S6-Body');
    include _include(APP_PATH.'plugin/abs_theme_aether/overwrite/view/htm/user_login.htm');
    exit;
}
