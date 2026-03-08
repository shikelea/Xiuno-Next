if($IS_HTMX) {
    $_SERVER['ajax'] = false;
    message(0, lang('haya_favorite_user_delete_favorite_success_tip'));
}