if($IS_HTMX) {
    $_SERVER['ajax'] = false;
    header('HX-Trigger: ' . json_encode(['closeModal' => true,], JSON_FORCE_OBJECT));
    message(0, jump(lang('delete_completely'),url('index'),1));
}
