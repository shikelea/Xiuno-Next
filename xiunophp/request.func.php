<?php

function xn_runtime_is_command() {
	return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
}

function xn_request_id_is_valid($value) {
	return is_string($value) && preg_match('/\A[a-f0-9]{32}\z/D', $value) === 1;
}

function xn_request_id_generate() {
	try {
		$value = bin2hex(random_bytes(16));
	} catch(Throwable $e) {
		// Request IDs are diagnostic correlation values, not authentication secrets. Keep an unusual
		// runtime traceable without ever accepting the client-supplied HTTP_X_REQUEST_ID value.
		$value = substr(hash('sha256', microtime(TRUE)."\0".getmypid()."\0".uniqid('', TRUE)), 0, 32);
	}
	return xn_request_id_is_valid($value) ? $value : '';
}

function xn_request_id_current() {
	$value = isset($_SERVER['request_id']) ? $_SERVER['request_id'] : '';
	return xn_request_id_is_valid($value) ? $value : '';
}

function xn_request_id_support_html() {
	$value = xn_request_id_current();
	if($value === '') return '';
	return '<span class="request-id">Request ID: <code data-request-id="'.$value.'">'.$value.'</code></span>';
}

function xn_request_id_init() {
	if(defined('XN_REQUEST_ID_INITIALIZED')) {
		return xn_request_id_current();
	}
	define('XN_REQUEST_ID_INITIALIZED', TRUE);
	$_SERVER['request_id'] = '';
	if(xn_runtime_is_command()) return '';

	$value = xn_request_id_generate();
	if($value !== '') {
		$_SERVER['request_id'] = $value;
		!headers_sent() AND header('X-Request-ID: '.$value, TRUE);
	}
	return $value;
}

?>
