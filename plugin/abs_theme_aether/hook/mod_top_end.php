if($IS_HTMX) {
    $_SERVER['ajax'] = false;
    header('HX-Trigger: ' . json_encode(['closeModal' => true,], JSON_FORCE_OBJECT));
    message(0, jump(lang('set_completely'),"'; window.location.reload(); var a='",1));
}
