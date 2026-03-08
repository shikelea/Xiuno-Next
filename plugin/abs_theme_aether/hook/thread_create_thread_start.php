if(empty(param('subject', ''))||empty(param('message', ''))) {
http_response_code(400);
}