if(empty(trim(param('message', '')))) {
http_response_code(400);
$MESSAGE_FORCE_HX_TRIGGER = true;
message(1,'请输入内容');die;
}