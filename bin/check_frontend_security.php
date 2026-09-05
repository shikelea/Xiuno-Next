<?php

$root = dirname(__DIR__) . '/';
$errors = array();

require_once $root . 'xiunophp/misc.func.php';
require_once $root . 'model/html_compat.func.php';

function script_expression_is_safe($expression) {
	$tokens = token_get_all('<?php '.$expression.';');
	$allowedFunctions = array('xn_json_encode_for_script', 'intval', 'lang', 'csrf_token', 'url', 'plugin_url', 'http_referer');
	$name = '';
	$opened = FALSE;
	$closed = FALSE;
	$depth = 0;
	$lastToken = NULL;
	$lastString = '';
	foreach($tokens as $token) {
		if(is_array($token)) {
			if(in_array($token[0], array(T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), TRUE)) continue;
			if(in_array($token[0], array(T_ECHO, T_PRINT, T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE, T_EVAL, T_EXIT, T_FUNCTION, T_FN, T_NEW, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE), TRUE)) return FALSE;
			if($name === '') {
				if($token[0] != T_STRING || !in_array($token[1], array('xn_json_encode_for_script', 'intval'), TRUE)) return FALSE;
				$name = $token[1];
				$lastToken = $token[0];
				$lastString = $token[1];
				continue;
			}
			if($closed) return FALSE;
			$lastToken = $token[0];
			$lastString = $token[0] == T_STRING ? $token[1] : '';
			continue;
		}
		if($name === '') return FALSE;
		if($token == '(' && ($lastToken == T_VARIABLE || $lastToken == ')' || ($lastToken == T_STRING && !in_array($lastString, $allowedFunctions, TRUE)))) return FALSE;
		if(!$opened) {
			if($token != '(') return FALSE;
			$opened = TRUE;
			$depth = 1;
			$lastToken = $token;
			$lastString = '';
			continue;
		}
		if($closed) {
			if($token != ';') return FALSE;
			continue;
		}
		if($token == '(') {
			$depth++;
		} elseif($token == ')' && --$depth == 0) {
			$closed = TRUE;
		}
		$lastToken = $token;
		$lastString = '';
	}
	return $name !== '' && $opened && $closed;
}

function php_script_tokens_are_safe($script) {
	$allowedFunctions = array('xn_json_encode_for_script', 'intval', 'lang', 'csrf_token', 'url', 'plugin_url', 'http_referer', 'is_dir');
	$lastToken = NULL;
	$lastString = '';
	foreach(token_get_all($script) as $token) {
		if(is_array($token)) {
			if(in_array($token[0], array(T_INLINE_HTML, T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO, T_CLOSE_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), TRUE)) continue;
			if(in_array($token[0], array(T_PRINT, T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE, T_EVAL, T_EXIT, T_FUNCTION, T_FN, T_NEW, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE), TRUE)) return FALSE;
			$lastToken = $token[0];
			$lastString = $token[0] == T_STRING ? $token[1] : '';
			continue;
		}
		if($token == '(' && ($lastToken == T_VARIABLE || $lastToken == ')' || ($lastToken == T_STRING && !in_array($lastString, $allowedFunctions, TRUE)))) return FALSE;
		$lastToken = $token;
		$lastString = '';
	}
	return TRUE;
}

function inline_script_outputs_are_safe($script) {
	if(!php_script_tokens_are_safe($script)) return FALSE;
	$tokens = token_get_all($script);
	$output = FALSE;
	$expression = '';
	$depth = 0;
	foreach($tokens as $token) {
		if(is_array($token)) {
			if($token[0] == T_ECHO || $token[0] == T_OPEN_TAG_WITH_ECHO) {
				if($output) return FALSE;
				$output = TRUE;
				$expression = '';
				$depth = 0;
				continue;
			}
			if($token[0] == T_PRINT || $token[0] == T_EXIT) return FALSE;
			if($token[0] == T_CLOSE_TAG) {
				if($output && !script_expression_is_safe(trim($expression))) return FALSE;
				$output = FALSE;
				$expression = '';
				continue;
			}
			$output AND $expression .= $token[1];
			continue;
		}
		if(!$output) continue;
		if($token == '(' || $token == '[' || $token == '{') {
			$depth++;
		} elseif(($token == ')' || $token == ']' || $token == '}') && $depth > 0) {
			$depth--;
		}
		if($token == ';' && $depth == 0) {
			if(!script_expression_is_safe(trim($expression))) return FALSE;
			$output = FALSE;
			$expression = '';
			continue;
		}
		$expression .= $token;
	}
	return !$output;
}

function inline_script_blocks($template) {
	$blocks = array();
	$inside = FALSE;
	$attributes = '';
	$body = '';
	foreach(token_get_all($template) as $token) {
		$text = is_array($token) ? $token[1] : $token;
		if(is_array($token) && $token[0] != T_INLINE_HTML) {
			$inside AND $body .= $text;
			continue;
		}
		while($text !== '') {
			if($inside) {
				if(!preg_match('#</script\s*>#i', $text, $match, PREG_OFFSET_CAPTURE)) {
					$body .= $text;
					break;
				}
				$body .= substr($text, 0, $match[0][1]);
				$blocks[] = array($attributes, $body);
				$text = substr($text, $match[0][1] + strlen($match[0][0]));
				$inside = FALSE;
				$attributes = '';
				$body = '';
				continue;
			}
			if(!preg_match('#<script\b([^>]*)>#i', $text, $match, PREG_OFFSET_CAPTURE)) break;
			$attributes = $match[1][0];
			$text = substr($text, $match[0][1] + strlen($match[0][0]));
			$inside = TRUE;
		}
	}
	if($inside) $blocks[] = array($attributes, $body);
	return $blocks;
}

$postTemplate = file_get_contents($root . 'view/htm/post.htm');
if ($postTemplate === FALSE) {
	$errors[] = 'failed to read view/htm/post.htm';
} else {
	$riskyUploadPatterns = array(
		"message.orgfilename + '</a>",
		"'<li aid=\"' + aid",
		"message.url + '\" target=\"_blank\"",
	);
	foreach ($riskyUploadPatterns as $pattern) {
		if (strpos($postTemplate, $pattern) !== FALSE) {
			$errors[] = 'attachment upload list must not build HTML by concatenating server-returned filename/url data';
			break;
		}
	}
	if (strpos($postTemplate, "document.createTextNode(' ' + orgfilename)") === FALSE) {
		$errors[] = 'attachment upload filename must be inserted as text, not HTML';
	}
	if (strpos($postTemplate, "rel: 'noopener noreferrer'") === FALSE) {
		$errors[] = 'attachment upload links opened in a new tab must set rel=noopener noreferrer';
	}
	if (strpos($postTemplate, "href', 'javascript:void(0);'") !== FALSE) {
		$errors[] = 'attachment upload delete link must not use a javascript: URL';
	}
}

$headerTemplate = file_get_contents($root . 'view/htm/header.inc.htm');
if ($headerTemplate === FALSE) {
	$errors[] = 'failed to read view/htm/header.inc.htm';
} else {
	if (strpos($headerTemplate, '<?php echo xn_html_escape($header[\'title\']);?>') === FALSE) {
		$errors[] = 'frontend title must escape header title';
	}
	if (strpos($headerTemplate, 'xn_html_escape(strip_tags($header[\'description\']))') === FALSE) {
		$errors[] = 'frontend meta description must escape stripped text';
	}
}

$adminHeaderTemplate = file_get_contents($root . 'admin/view/htm/header.inc.htm');
if ($adminHeaderTemplate === FALSE) {
	$errors[] = 'failed to read admin/view/htm/header.inc.htm';
} else {
	if (strpos($adminHeaderTemplate, '<?php echo xn_html_escape($header[\'title\']);?>') === FALSE) {
		$errors[] = 'admin title must escape header title';
	}
	if (stripos($adminHeaderTemplate, '<!DOCTYPE html>') === FALSE || stripos($adminHeaderTemplate, '<!DOCTYPE html>') > stripos($adminHeaderTemplate, '<html')) {
		$errors[] = 'admin pages must declare standards mode before the html element';
	}
}

$indexTemplate = file_get_contents($root . 'view/htm/index.htm');
if ($indexTemplate === FALSE) {
	$errors[] = 'failed to read view/htm/index.htm';
} else {
	if (strpos($indexTemplate, '<?php echo xn_html_escape($conf[\'sitename\']);?>') === FALSE) {
		$errors[] = 'site name must be escaped in homepage sidebar';
	}
	if (strpos($indexTemplate, '<?php echo xn_html_safe($conf[\'sitebrief\']);?>') === FALSE) {
		$errors[] = 'site brief must be sanitized before homepage HTML output';
	}
}

$indexEntry = file_get_contents($root . 'index.php');
if ($indexEntry === FALSE) {
	$errors[] = 'failed to read index.php';
} else {
	if (strpos($indexEntry, "parse_url(\$view_url, PHP_URL_SCHEME)") === FALSE) {
		$errors[] = 'compatibility injector view_url must reject configured URL schemes';
	}
	if (strpos($indexEntry, "substr(\$view_url, 0, 2) === '//'") === FALSE) {
		$errors[] = 'compatibility injector view_url must reject protocol-relative URLs';
	}
	if (strpos($indexEntry, '$is_admin_request = strpos($script_name, \'/admin/\') !== FALSE') === FALSE) {
		$errors[] = 'compatibility injector must detect admin requests before building relative asset URLs';
	}
	if (strpos($indexEntry, "\$view_url = '../' . \$view_url;") === FALSE) {
		$errors[] = 'compatibility injector must prefix relative view_url with ../ for admin requests';
	}
	if (strpos($indexEntry, 'function xn_compat_html_tag_attribute_values(') === FALSE
		|| strpos($indexEntry, 'function xn_compat_html_has_local_asset(') === FALSE
		|| strpos($indexEntry, 'function xn_compat_html_has_meta_name(') === FALSE) {
		$errors[] = 'compatibility injector must detect real resource/meta tags instead of page-wide filename substrings';
	}
	if (strpos($indexEntry, "\$head_inject .= '<script src=\"") === FALSE) {
		$errors[] = 'compatibility injector must load a missing compatibility runtime before body inline scripts';
	}
	if (strpos($indexEntry, 'function xn_compat_response_content_type()') === FALSE
		|| strpos($indexEntry, "array('text/html', 'application/xhtml+xml')") === FALSE
		|| strpos($indexEntry, 'function xn_compat_output_looks_like_html($html)') === FALSE) {
		$errors[] = 'compatibility injector must fail closed for explicit non-HTML responses and non-document payloads';
	}
	if (strpos($indexEntry, "ob_start('xn_compat_output_handler', 8192);") === FALSE
		|| strpos($indexEntry, 'strlen($buffer) >= 65536') === FALSE
		|| strpos($indexEntry, 'PHP_OUTPUT_HANDLER_CLEAN') === FALSE) {
		$errors[] = 'compatibility output buffering must stream explicit non-HTML responses and bound unknown-response probing';
	}

	$injector_start = strpos($indexEntry, 'function xn_compat_response_content_type()');
	$injector_end = $injector_start === FALSE ? FALSE : strpos($indexEntry, "ob_start('xn_compat_output_handler', 8192);", $injector_start);
	if($injector_start !== FALSE && $injector_end !== FALSE) {
		$injector_source = substr($indexEntry, $injector_start, $injector_end - $injector_start);
		try {
			eval($injector_source);
			if(!function_exists('csrf_token')) {
				function csrf_token() { return 'fixture-token'; }
			}
			$_SERVER['conf'] = array('view_url'=>'view/', 'static_version'=>'?v=test');
			$_SERVER['SCRIPT_NAME'] = '/index.php';
			$json_payload = '{"code":"0","message":"</head>"}';
			xn_compat_inject_output($json_payload) === $json_payload
				|| $errors[] = 'compatibility injector must leave JSON-like payloads containing </head> byte-for-byte unchanged';
			$html_payload = '<!doctype html><html><head><title>x</title></head><body>x</body></html>';
			$html_result = xn_compat_inject_output($html_payload);
			(strpos($html_result, 'name="csrf-token"') !== FALSE && strpos($html_result, 'bs4-compat.js') !== FALSE)
				|| $errors[] = 'compatibility injector must still enhance complete HTML documents';
			$compat_script = '<script src="view/js/bs4-compat.js?v=test"></script>';
			(strpos($html_result, $compat_script) !== FALSE && strpos($html_result, $compat_script) < stripos($html_result, '</head>'))
				|| $errors[] = 'compatibility injector must place a missing runtime at the end of head';

			$misleading_payload = '<!doctype html><html><head>'
				.'<!-- <meta name="csrf-token" content="comment"><link href="view/css/font-awesome.min.css"><link href="view/css/bs4-compat.min.css"><script src="view/js/bs4-compat.min.js"></script> -->'
				.'<title>bs4-compat.min.js font-awesome.min.css name="csrf-token"</title></head><body>x</body></html>';
			$misleading_result = xn_compat_inject_output($misleading_payload);
			(strpos($misleading_result, '<meta name="csrf-token" content="fixture-token">') !== FALSE
				&& strpos($misleading_result, '<link rel="stylesheet" href="view/css/font-awesome.min.css?v=test">') !== FALSE
				&& strpos($misleading_result, '<link rel="stylesheet" href="view/css/bs4-compat.css?v=test">') !== FALSE
				&& strpos($misleading_result, $compat_script) !== FALSE)
				|| $errors[] = 'comments and text that mention compatibility assets must not suppress real tag injection';

			$existing_payload = '<!doctype html><html><head>'
				.'<meta content="existing" name="csrf-token"><link href="view/css/font-awesome.min.css" rel="stylesheet">'
				.'<link href="view/css/bs4-compat.min.css" rel="stylesheet"><script defer src="view/js/bs4-compat.min.js"></script>'
				.'</head><body>x</body></html>';
			$existing_result = xn_compat_inject_output($existing_payload);
			($existing_result === $existing_payload)
				|| $errors[] = 'actual local meta/link/script tags must prevent duplicate compatibility injection';

			$_SERVER['SCRIPT_NAME'] = '/admin/index.php';
			$admin_broken_payload = '<!doctype html><html><head><script src="view/js/bs4-compat.min.js"></script></head><body>x</body></html>';
			$admin_broken_result = xn_compat_inject_output($admin_broken_payload);
			strpos($admin_broken_result, '<script src="../view/js/bs4-compat.js?v=test"></script>') !== FALSE
				|| $errors[] = 'an admin-relative view/... asset must not suppress the valid ../view compatibility runtime';
			$_SERVER['SCRIPT_NAME'] = '/index.php';
		} catch(Throwable $e) {
			$errors[] = 'compatibility injector behavior fixture could not execute: '.$e->getMessage();
		}
	}
}

$miscModel = file_get_contents($root . 'model/misc.func.php');
if ($miscModel === FALSE) {
	$errors[] = 'failed to read model/misc.func.php';
} elseif (strpos($miscModel, "if(\$ajax && !headers_sent()) header('Content-Type: application/json; charset=utf-8');") === FALSE) {
	$errors[] = 'AJAX message responses must declare JSON content type for clients and diagnostics';
}

$settingRoute = file_get_contents($root . 'admin/route/setting.php');
if ($settingRoute === FALSE) {
	$errors[] = 'failed to read admin/route/setting.php';
} else {
	if (strpos($settingRoute, '$sitename = trim(strip_tags(param(\'sitename\', \'\', FALSE)));') === FALSE) {
		$errors[] = 'admin sitename must be normalized to plain text before saving';
	}
	if (strpos($settingRoute, '$sitebrief = xn_html_safe(param(\'sitebrief\', \'\', FALSE));') === FALSE) {
		$errors[] = 'admin sitebrief must be sanitized before saving';
	}
}

$bs4Compat = file_get_contents($root . 'view/js/bs4-compat.js');
if ($bs4Compat === FALSE) {
	$errors[] = 'failed to read view/js/bs4-compat.js';
} else {
	if (strpos($bs4Compat, 'function isSameOrigin(input)') === FALSE) {
		$errors[] = 'bs4 compatibility CSRF injector must define same-origin checks';
	}
	if (strpos($bs4Compat, 'settings && settings.crossDomain') === FALSE) {
		$errors[] = 'jQuery CSRF injector must skip cross-domain requests';
	}
	if (strpos($bs4Compat, "ajaxMethod === 'POST' && sameOrigin") === FALSE) {
		$errors[] = 'jQuery CSRF injector must only attach tokens to same-origin POST requests';
	}
	if (strpos($bs4Compat, 'runtime.jquery = jq') === FALSE || strpos($bs4Compat, 'window._csrf_ajax_setup_jquery = jq') === FALSE) {
		$errors[] = 'jQuery CSRF injector must track and reinstall for each replacement jQuery identity';
	}
	if (strpos($bs4Compat, "var shouldInject = token && method === 'POST' && sameOrigin;") === FALSE) {
		$errors[] = 'fetch CSRF injector must only attach tokens to same-origin POST requests';
	}
	if (strpos($bs4Compat, "var shouldStrip = token && !sameOrigin && currentHeader === token;") === FALSE || strpos($bs4Compat, "headers.delete('X-CSRF-TOKEN')") === FALSE) {
		$errors[] = 'fetch CSRF injector must strip the current session token from cross-origin request headers';
	}
	if (strpos($bs4Compat, 'var nextInit = {};') === FALSE || strpos($bs4Compat, 'nextInit[key] = init[key];') === FALSE || strpos($bs4Compat, 'init.headers = headers;') !== FALSE) {
		$errors[] = 'fetch CSRF injector must clone RequestInit instead of mutating a caller-owned init object';
	}
	if (strpos($bs4Compat, "input && typeof input.href === 'string'") === FALSE || strpos($bs4Compat, 'target !== null && new URL(target, document.baseURI || window.location.href).origin === window.location.origin') === FALSE) {
		$errors[] = 'fetch CSRF injector must resolve URL objects explicitly and fail closed for unknown input shapes';
	}
	if (strpos($bs4Compat, "jfeedback.text(message).addClass('d-block');") === FALSE) {
		$errors[] = 'form alert helper must render visible invalid-feedback text, not only tooltips';
	}
	if (strpos($bs4Compat, "jthis.one('focus input'") !== FALSE) {
		$errors[] = 'form alert helper must not clear errors on focus before users can read them';
	}
	if (strpos($bs4Compat, "jthis.off('input.xn-alert change.xn-alert').one('input.xn-alert change.xn-alert'") === FALSE) {
		$errors[] = 'form alert helper must clear errors only after user input or change';
	}
	if (strpos($bs4Compat, "xn-alert-original-aria-describedby") === FALSE) {
		$errors[] = 'form alert helper must preserve existing aria-describedby values';
	}
	if (strpos($bs4Compat, 'function formSubmissionDetails(form, submitter)') === FALSE) {
		$errors[] = 'form CSRF injector must define an effective submitter-aware origin and method check';
	}
	if (strpos($bs4Compat, "submitter.hasAttribute('formaction') ? submitter.formAction : form.action") === FALSE || strpos($bs4Compat, "submitter.hasAttribute('formmethod') ? submitter.formMethod : form.method") === FALSE) {
		$errors[] = 'form CSRF injector must honor submit-button formaction/formmethod overrides';
	}
	if (strpos($bs4Compat, 'function removeLocalCsrf(form, token)') === FALSE || strpos($bs4Compat, "data-xn-csrf-auto") === FALSE) {
		$errors[] = 'form CSRF injector must identify and remove its local token when a form becomes cross-origin';
	}
	if (strpos($bs4Compat, "document.addEventListener('submit'") === FALSE || strpos($bs4Compat, 'event.submitter || null') === FALSE
		|| strpos($bs4Compat, "'href', 'action', 'method', 'name', 'type', 'class'") === FALSE
		|| strpos($bs4Compat, 'attributeFilter: observedAttributes') === FALSE) {
		$errors[] = 'form CSRF injector must revalidate at submit time and observe runtime action/method changes';
	}
	if (strpos($bs4Compat, "document.addEventListener('formdata'") === FALSE || strpos($bs4Compat, 'stripLocalCsrfFormData(event.formData, token, ownedValues)') === FALSE) {
		$errors[] = 'form CSRF injector must remove only local tokens from native form.submit() request bodies';
	}
	if (strpos($bs4Compat, 'var prevBeforeSend = jq.ajaxSettings && jq.ajaxSettings.beforeSend;') === FALSE || strpos($bs4Compat, 'csrfBeforeSend._xnCsrf = true;') === FALSE) {
		$errors[] = 'jQuery CSRF injector must chain an existing global beforeSend hook instead of replacing it';
	}
	if (strpos($bs4Compat, 'if (prevResult === false) return false;') === FALSE) {
		$errors[] = 'jQuery CSRF injector must honor a chained beforeSend hook that cancels the request';
	}
	if (strpos($bs4Compat, 'function stripLocalCsrfHeader(headers, token)') === FALSE || strpos($bs4Compat, "jq.ajaxPrefilter('+*', state.csrfPrefilter)") === FALSE) {
		$errors[] = 'jQuery CSRF injector must strip the current session token from cross-origin configured headers before transport';
	}
	if (strpos($bs4Compat, 'function guardLocalCsrfRequestHeader(xhr, token)') === FALSE || strpos($bs4Compat, 'guardLocalCsrfRequestHeader(xhr, token)') === FALSE) {
		$errors[] = 'jQuery CSRF injector must block direct current-token writes from third-party prefilters';
	}
	if (strpos($bs4Compat, 'function callBeforeSendWithoutCrossOriginCsrf(') === FALSE || strpos($bs4Compat, "String(name).toLowerCase() === 'x-csrf-token'") === FALSE) {
		$errors[] = 'jQuery CSRF injector must prevent chained legacy hooks from reattaching the current token across origins';
	}
	if (strpos($bs4Compat, 'headers = new Headers(headerSource);') === FALSE) {
		$errors[] = 'fetch CSRF injector must normalize HeadersInit (object, array, Headers) without dropping request headers';
	}
	if (strpos($bs4Compat, 'return window.Promise.reject(error);') === FALSE) {
		$errors[] = 'fetch CSRF injector must preserve rejected-promise semantics for an invalid HeadersInit';
	}
}

$xiunoJs = file_get_contents($root . 'view/js/xiuno.js');
if ($xiunoJs === FALSE) {
	$errors[] = 'failed to read view/js/xiuno.js';
} else {
	if (strpos($xiunoJs, 'function xn_postdata_with_csrf(postdata, same_origin)') === FALSE) {
		$errors[] = '$.xpost must keep a direct CSRF token fallback for legacy themes that override ajaxSetup';
	}
	if (strpos($xiunoJs, 'postdata = xn_postdata_with_csrf(postdata, xn_url_same_origin(url));') === FALSE) {
		$errors[] = '$.xpost must normalize CSRF tokens for every same-origin and cross-origin POST request';
	}
	if (strpos($xiunoJs, 'var copiedObject = $.extend({}, postdata);') === FALSE || strpos($xiunoJs, 'copiedObject._token = token;') === FALSE) {
		$errors[] = '$.xpost must clone plain objects and replace stale same-origin CSRF tokens';
	}
	if (strpos($xiunoJs, 'postdata instanceof FormData') === FALSE || strpos($xiunoJs, 'var copiedFormData = new FormData();') === FALSE || strpos($xiunoJs, "copiedFormData.append('_token', token)") === FALSE) {
		$errors[] = '$.xpost CSRF fallback must clone and normalize FormData without mutating caller-owned data';
	}
	if (strpos($xiunoJs, 'function xn_url_same_origin(url)') === FALSE) {
		$errors[] = '$.xpost must define a same-origin URL check for CSRF token scope';
	}
	if (strpos($xiunoJs, "return !token || (same_origin === false && String(value) !== String(token));") === FALSE) {
		$errors[] = '$.xpost must preserve caller tokens without a global token and otherwise strip only the current session token across origins';
	}
	if (strpos($xiunoJs, 'document.baseURI || window.location.href') === FALSE || strpos($xiunoJs, "typeof url.href === 'string'") === FALSE || strpos($xiunoJs, "typeof url.url === 'string'") === FALSE) {
		$errors[] = '$.xpost must resolve relative, URL, and Request-like targets using the document base URI';
	}
	if (strpos($xiunoJs, 'function xn_ajax_failure_message(method, url, xhr, type, reason)') === FALSE
		|| strpos($xiunoJs, 'function xn_ajax_localized_message(key, fallback, replacements)') === FALSE
		|| strpos($xiunoJs, "timeout: progress_callback ? 600000 : 30000") === FALSE) {
		$errors[] = 'Xiuno AJAX helpers must separate ordinary-request and upload timeouts and centralize safe failure messages';
	}
	if (strpos($xiunoJs, '"xhr.responseText:" + xhr.responseText') !== FALSE
		|| strpos($xiunoJs, 'r.match(/\\{.*\\}/)') !== FALSE
		|| strpos($xiunoJs, "'Server Response Empty!'") !== FALSE) {
		$errors[] = 'Xiuno AJAX callbacks must not expose raw HTML, guess JSON inside contaminated responses, or emit the legacy opaque empty-response text';
	}
	if (strpos($xiunoJs, "responseText.slice(0, 2048)") === FALSE || strpos($xiunoJs, "xhr.getResponseHeader('X-Request-ID')") === FALSE) {
		$errors[] = 'Xiuno AJAX diagnostics must keep bounded console evidence and preserve a safe request identifier';
	}
	if (strpos($xiunoJs, "xn_ajax_failure_message('GET', url, xhr, 'success', 'empty')") === FALSE
		|| substr_count($xiunoJs, "xn_ajax_failure_message('GET', url, xhr, 'parsererror', 'invalid-json')") < 2
		|| strpos($xiunoJs, 'success: function (r, textStatus, xhr)') === FALSE
		|| strpos($xiunoJs, "xn_ajax_failure_message('POST', url, xhr, 'success', 'empty')") === FALSE
		|| strpos($xiunoJs, "xn_ajax_failure_message('POST', url, xhr, 'parsererror', 'invalid-json')") === FALSE) {
		$errors[] = 'Xiuno AJAX empty and invalid-JSON success paths must preserve the real xhr so users receive the server request identifier';
	}
	if (strpos($xiunoJs, 'function xn_ajax_response_is_html_document(response, xhr)') === FALSE
		|| strpos($xiunoJs, "contentType.indexOf('json') !== -1") === FALSE
		|| strpos($xiunoJs, "callback(-101, r, { kind: 'html-document' })") === FALSE
		|| strpos($xiunoJs, "callback(-102, xn_ajax_failure_message('GET'") === FALSE) {
		$errors[] = '$.xget must distinguish intentional HTML documents from malformed non-JSON responses without exposing error bodies';
	}
}

$ajaxLanguageKeys = array(
	'ajax_empty_response', 'ajax_invalid_response', 'ajax_request_timeout',
	'ajax_request_forbidden', 'ajax_request_http_error', 'ajax_request_network_error', 'ajax_request_id',
);
foreach(array('zh-cn', 'zh-tw', 'en-us', 'ru-ru', 'th-th') as $locale) {
	$languageJs = file_get_contents($root.'lang/'.$locale.'/bbs.js');
	if($languageJs === FALSE) {
		$errors[] = 'failed to read '.$locale.' browser language messages';
		continue;
	}
	foreach($ajaxLanguageKeys as $languageKey) {
		if(strpos($languageJs, "'".$languageKey."':") === FALSE) {
			$errors[] = $locale.' browser language is missing '.$languageKey;
		}
	}
}

$bbsJs = file_get_contents($root . 'view/js/bbs.js');
if ($bbsJs === FALSE) {
	$errors[] = 'failed to read view/js/bbs.js';
} else {
	if (preg_match('/(^|[^\.\w$])alert\s*\(\s*message\s*\)\s*;/', $bbsJs)) {
		$errors[] = 'data-method POST failures must use $.alert() so dependency guidance can render links';
	}
	if (strpos($bbsJs, 'function xn_post_link_lock(jlink)') === FALSE || strpos($bbsJs, "jlink.data('post-pending', 1)") === FALSE) {
		$errors[] = 'data-method POST links must set a pending guard before sending';
	}
	if (strpos($bbsJs, "addClass('disabled').attr('aria-disabled', 'true')") === FALSE) {
		$errors[] = 'data-method POST links must expose a disabled state while pending';
	}
	if (strpos($bbsJs, 'function xn_post_link_unlock(jlink)') === FALSE || strpos($bbsJs, "removeData('post-pending')") === FALSE) {
		$errors[] = 'data-method POST links must restore the pending state on failure';
	}
	if (strpos($bbsJs, 'function xn_post_link_submit_form(href)') === FALSE || strpos($bbsJs, 'form.method = \'post\'') === FALSE) {
		$errors[] = 'plugin install links must support full-page POST fallback for legacy install wizards';
	}
	if (strpos($bbsJs, 'function xn_post_link_csrf_token()') === FALSE || strpos($bbsJs, 'if (!token)') === FALSE || strpos($bbsJs, 'input.name = \'_token\'') === FALSE || strpos($bbsJs, 'input.value = token') === FALSE || strpos($bbsJs, 'form.appendChild(input)') === FALSE) {
		$errors[] = 'full-page plugin install fallback must include a CSRF token';
	}
	if (strpos($bbsJs, 'plugin-install-') === FALSE || strpos($bbsJs, 'xn_post_link_should_submit_form(jlink, href)') === FALSE || strpos($bbsJs, 'xn_post_link_is_same_origin(href)') === FALSE) {
		$errors[] = 'plugin install links must bypass AJAX reload only for same-origin URLs so legacy installer HTML can render';
	}
	if (strpos($bbsJs, 'url.protocol === window.location.protocol && url.host === window.location.host') === FALSE) {
		$errors[] = 'data-method POST links must reject cross-origin targets before attaching CSRF tokens';
	}
	if (strpos($bbsJs, 'function xn_post_link_handle(jlink, href)') === FALSE || strpos($bbsJs, 'return xn_post_link_handle(jthis, href);') === FALSE) {
		$errors[] = 'data-method POST and confirm POST links must share the same safety/fallback handler';
	}
	if (strpos($bbsJs, '<?php') !== FALSE || strpos($bbsJs, "xn.url('forum-' + forumId)") === FALSE) {
		$errors[] = 'static bbs.js must build first-post deletion redirects from the published forum id instead of an unrendered PHP literal';
	}
}

$bbsMinJs = file_get_contents($root . 'view/js/bbs.min.js');
if ($bbsMinJs === FALSE) {
	$errors[] = 'failed to read view/js/bbs.min.js';
} else {
	foreach (array('xn_post_link_submit_form', '_token', 'plugin-install-', 'xn_post_link_is_same_origin', 'window.location.protocol', 'window.location.host', 'xn_post_link_handle') as $needle) {
		if (strpos($bbsMinJs, $needle) === FALSE) {
			$errors[] = 'minified bbs.js must include plugin install POST fallback and same-origin CSRF guards';
			break;
		}
	}
}

$resetPasswordTemplate = file_get_contents($root . 'view/htm/user_resetpw.htm');
if ($resetPasswordTemplate === FALSE) {
	$errors[] = 'failed to read view/htm/user_resetpw.htm';
} else {
	if (strpos($resetPasswordTemplate, 'user_create_email_on') !== FALSE) {
		$errors[] = 'password-reset navigation must not depend on the unrelated registration email setting';
	}
	foreach (array(
		'type="email"',
		'autocomplete="email"',
		'autocomplete="one-time-code"',
		'inputmode="numeric"',
		'pattern="[0-9]{6}"',
		'type="button"',
		'data-url="<?php echo url(\'user-send_code-user_resetpw\');?>"',
	) as $requiredResetContract) {
		if (strpos($resetPasswordTemplate, $requiredResetContract) === FALSE) {
			$errors[] = 'password-reset form must expose valid email/code semantics and a non-submitting send-code action';
			break;
		}
	}
}

$loginTemplate = file_get_contents($root . 'view/htm/user_login.htm');
if ($loginTemplate === FALSE) {
	$errors[] = 'failed to read view/htm/user_login.htm';
} else {
	if (strpos($loginTemplate, "lang('email');?> / <?php echo lang('username')") === FALSE) {
		$errors[] = 'login account field must tell users email or username are both accepted';
	}
	if (strpos($loginTemplate, "jform.find(':input').filter(function() { return this.name == code; }).first()") === FALSE) {
		$errors[] = 'login field error lookup must avoid selector interpolation';
	}
	if (strpos($loginTemplate, 'delay(1000).location(referer)') !== FALSE) {
		$errors[] = 'login success redirect must not keep the legacy one-second delay';
	}
}

$pluginList = file_get_contents($root . 'admin/view/htm/plugin_list.htm');
if ($pluginList === FALSE) {
	$errors[] = 'failed to read admin/view/htm/plugin_list.htm';
} else {
	foreach (array('$plugin_name_html', '$plugin_brief_html', '$plugin_version_html', '$plugin_icon_url_html', '$plugin_username_html') as $needle) {
		if (strpos($pluginList, $needle) === FALSE) {
			$errors[] = 'plugin list must escape plugin package metadata before rendering';
			break;
		}
	}
	if (strpos($pluginList, 'htmlspecialchars(lang(\'plugin_unstall_confirm_tips\'') === FALSE) {
		$errors[] = 'plugin list uninstall confirmation text must be escaped for attribute context';
	}
}

$pluginRead = file_get_contents($root . 'admin/view/htm/plugin_read.htm');
if ($pluginRead === FALSE) {
	$errors[] = 'failed to read admin/view/htm/plugin_read.htm';
} else {
	foreach (array('$plugin_name_html', '$plugin_brief_html', '$plugin_version_html', '$plugin_icon_url_html', '$plugin_bbs_version_html', '$plugin_qq_html') as $needle) {
		if (strpos($pluginRead, $needle) === FALSE) {
			$errors[] = 'plugin detail page must escape plugin package metadata before rendering';
			break;
		}
	}
	if (strpos($pluginRead, "xn_http_url_allowed(\$plugin['brief_url'])") === FALSE) {
		$errors[] = 'plugin detail page must validate external brief URLs before rendering links';
	}
	if (strpos($pluginRead, 'rel="noopener noreferrer"') === FALSE) {
		$errors[] = 'plugin detail external links opened in a new tab must set rel=noopener noreferrer';
	}
}

$postModel = file_get_contents($root . 'model/post.func.php');
if ($postModel === FALSE) {
	$errors[] = 'failed to read model/post.func.php';
} else {
	if (strpos($postModel, "xn_html_escape(\$attach['orgfilename'])") === FALSE) {
		$errors[] = 'attachment filenames must be escaped in server-rendered post file lists';
	}
	if (strpos($postModel, "\$filetype = preg_replace('#[^\\w\\-]#', '', \$attach['filetype']);") === FALSE) {
		$errors[] = 'attachment filetype class must be normalized in server-rendered post file lists';
	}
}

$pluginModel = file_get_contents($root . 'model/plugin.func.php');
if ($pluginModel === FALSE) {
	$errors[] = 'failed to read model/plugin.func.php';
} else {
	$overwriteIndexStart = strpos($pluginModel, 'function plugin_file_index_overwrite_files(');
	$overwriteIndexEnd = $overwriteIndexStart === FALSE ? FALSE : strpos($pluginModel, "\nfunction plugin_file_index()", $overwriteIndexStart);
	$overwriteIndexBody = ($overwriteIndexStart === FALSE || $overwriteIndexEnd === FALSE)
		? ''
		: substr($pluginModel, $overwriteIndexStart, $overwriteIndexEnd - $overwriteIndexStart);
	if (strpos($overwriteIndexBody, "is_link(rtrim(\$dir, '/'))") === FALSE
		|| strpos($overwriteIndexBody, 'if(is_link($item)) continue;') === FALSE) {
		$errors[] = 'plugin/theme overwrite indexing must not follow symlink overwrite files or directories';
	}
}

$scriptJsonPayload = array('payload' => "</script><script>alert('x' + \"y\")</script>&\r\n");
$scriptJson = xn_json_encode_for_script($scriptJsonPayload);
if (!is_string($scriptJson) || json_decode($scriptJson, TRUE) !== $scriptJsonPayload) {
	$errors[] = 'script JSON encoder must emit valid JSON without changing valid payloads';
} elseif (strpos($scriptJson, '<') !== FALSE || strpos($scriptJson, '>') !== FALSE || strpos($scriptJson, '&') !== FALSE || strpos($scriptJson, "'") !== FALSE) {
	$errors[] = 'script JSON encoder must not leave HTML/script delimiters or apostrophes literal';
} elseif (strpos($scriptJson, '\\u003C') === FALSE || strpos($scriptJson, '\\u0026') === FALSE || strpos($scriptJson, '\\u0027') === FALSE || strpos($scriptJson, '\\u0022') === FALSE) {
	$errors[] = 'script JSON encoder must hex-escape HTML delimiters and both quote types';
}

$invalidScriptJson = xn_json_encode_for_script(array('payload' => "\xC3\x28"));
$invalidScriptJsonDecoded = is_string($invalidScriptJson) ? json_decode($invalidScriptJson, TRUE) : NULL;
if (!is_array($invalidScriptJsonDecoded) || !isset($invalidScriptJsonDecoded['payload']) || $invalidScriptJsonDecoded['payload'] !== "\xEF\xBF\xBD(") {
	$errors[] = 'script JSON encoder must substitute invalid UTF-8 instead of emitting an empty JavaScript expression';
}

if (xn_json_encode_for_script(NAN) !== 'null' || xn_json_encode_for_script(INF) !== 'null') {
	$errors[] = 'script JSON encoder must turn non-finite numbers into a safe JavaScript null literal';
}

$scriptJsonBundleCheck = <<<'PHP'
define('DEBUG', 0);
$payload = array('payload' => "</script><script>alert('x' + \"y\")</script>&\r\n");
require $argv[1];
$json = xn_json_encode_for_script($payload);
if (!is_string($json) || json_decode($json, TRUE) !== $payload) exit(1);
if (strpos($json, '<') !== FALSE || strpos($json, '>') !== FALSE || strpos($json, '&') !== FALSE || strpos($json, "'") !== FALSE) exit(1);
if (strpos($json, '\\u003C') === FALSE || strpos($json, '\\u0026') === FALSE || strpos($json, '\\u0027') === FALSE || strpos($json, '\\u0022') === FALSE) exit(1);
$invalidJson = xn_json_encode_for_script(array('payload' => "\xC3\x28"));
$invalidDecoded = is_string($invalidJson) ? json_decode($invalidJson, TRUE) : NULL;
if (!is_array($invalidDecoded) || !isset($invalidDecoded['payload']) || $invalidDecoded['payload'] !== "\xEF\xBF\xBD(") exit(1);
if (xn_json_encode_for_script(NAN) !== 'null' || xn_json_encode_for_script(INF) !== 'null') exit(1);
PHP;
$scriptJsonBundleOutput = array();
$scriptJsonBundleStatus = 1;
$scriptJsonBundleCommand = escapeshellarg(PHP_BINARY).' -r '.escapeshellarg($scriptJsonBundleCheck).' '.escapeshellarg($root.'xiunophp/xiunophp.min.php');
exec($scriptJsonBundleCommand, $scriptJsonBundleOutput, $scriptJsonBundleStatus);
if ($scriptJsonBundleStatus !== 0) {
	$errors[] = 'generated XiunoPHP bundle must preserve script JSON encoding safety';
}

$scriptTemplates = array(
	'view/htm/footer.inc.htm' => array(
		'var forumarr = <?php echo xn_json_encode_for_script($forumarr);?>;',
		'var csrf_token = <?php echo xn_json_encode_for_script(csrf_token());?>;',
		'xn.options.water_image_url = <?php echo xn_json_encode_for_script($conf[\'logo_water_url\']);?>;',
	),
	'admin/view/htm/footer.inc.htm' => array(
		'var csrf_token = <?php echo xn_json_encode_for_script(csrf_token());?>;',
	),
	'view/htm/post.htm' => array(
		'$location = url(\'forum-__FID__\');',
		'var redirect_url = <?php echo xn_json_encode_for_script($location);?>;',
		"redirect_url = redirect_url.replace('__FID__', jfid.checked());",
		'jsubmit.button(message).delay(1000).location(redirect_url);',
		'xn_json_encode_for_script(lang(\'uploaded_attach\'))',
		'xn_json_encode_for_script(lang(\'delete\'))',
	),
	'admin/view/htm/setting_smtp.htm' => array(
		'var smtplist = <?php echo xn_json_encode_for_script($smtplist);?>;',
	),
	'admin/view/htm/thread_list.htm' => array(
		'var forumlist = <?php echo xn_json_encode_for_script($forumlist_simple);?>;',
	),
	'admin/view/htm/update.htm' => array(
		'xn_json_encode_for_script($rollback_backup[\'name\'])',
	),
);

foreach (array('view/htm/footer.inc.htm', 'admin/view/htm/footer.inc.htm') as $footerFile) {
	$footerSource = file_get_contents($root.$footerFile);
	if ($footerSource === FALSE || strpos($footerSource, '$.ajaxSetup(') !== FALSE) {
		$errors[] = $footerFile.' must delegate raw jQuery CSRF handling to the same-origin compatibility runtime';
	}
}

foreach ($scriptTemplates as $path => $needles) {
	$template = file_get_contents($root . $path);
	if ($template === FALSE) {
		$errors[] = 'failed to read ' . $path;
		continue;
	}
	foreach ($needles as $needle) {
		if (strpos($template, $needle) === FALSE) {
			$errors[] = $path . ' must use script-safe JSON encoding for dynamic JavaScript values';
			break;
		}
	}
}

foreach (array('view/htm', 'admin/view/htm') as $directory) {
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . $directory, FilesystemIterator::SKIP_DOTS));
	foreach ($iterator as $file) {
		if (!$file->isFile() || strtolower($file->getExtension()) != 'htm') continue;
		$templateFile = $file->getPathname();
		$template = file_get_contents($templateFile);
		$path = str_replace(str_replace('\\', '/', $root), '', str_replace('\\', '/', $templateFile));
		if ($template === FALSE) {
			$errors[] = 'failed to read ' . $path;
			continue;
		}
		if (preg_match('#\burl\(\s*(["\'])\s#', $template)) {
			$errors[] = $path . ' must not build url() targets with leading whitespace (breaks route matching, e.g. ?%20forum-1.htm)';
		}
		foreach (inline_script_blocks($template) as $script) {
			$attributes = strtolower($script[0]);
			if (preg_match('#(?:^|\s)src\s*=#i', $attributes) || preg_match('#\btype\s*=\s*(["\'])text/plain\1#i', $attributes)) continue;
			if (strpos($script[1], 'xn_json_encode(') !== FALSE) {
				$errors[] = $path . ' must not use the general JSON encoder in an inline script context';
				break;
			}
			if (!inline_script_outputs_are_safe($script[1])) {
				$errors[] = $path . ' must JSON-encode strings or explicitly cast numbers in executable inline scripts';
				break;
			}
		}
	}
}

if (!preg_match('#\burl\(\s*(["\'])\s#', '<a href="<?php echo url(" forum-1");?>">') || preg_match('#\burl\(\s*(["\'])\s#', '<a href="<?php echo url("forum-1");?>">')) {
	$errors[] = 'url() leading-whitespace guard must flag url(" forum-1") and accept url("forum-1")';
}

$scriptBoundaryBlocks = inline_script_blocks("<script><?php \$marker = '</script>'; echo \$unsafe; ?></script>");
$unterminatedScriptBlocks = inline_script_blocks('<script><?= $unsafe ?>');
if (!script_expression_is_safe('xn_json_encode_for_script($safe . $value)') || !script_expression_is_safe('intval($id)') || script_expression_is_safe('xn_json_encode_for_script($safe) . $unsafe') || script_expression_is_safe('intval($id), $unsafe') || script_expression_is_safe('xn_json_encode_for_script(print($unsafe))') || script_expression_is_safe('xn_json_encode_for_script(printf($unsafe))') || script_expression_is_safe('xn_json_encode_for_script(\printf($unsafe))') || script_expression_is_safe('xn_json_encode_for_script($callback($unsafe))') || script_expression_is_safe('xn_json_encode_for_script(($callback)($unsafe))') || script_expression_is_safe('xn_json_encode_for_script(exit($unsafe))') || script_expression_is_safe('intval(include($path))') || script_expression_is_safe('$unsafe') || !inline_script_outputs_are_safe('<?php ECHO xn_json_encode_for_script($safe); ?>') || !inline_script_outputs_are_safe('<?php echo/* comment */xn_json_encode_for_script($safe); ?>') || !inline_script_outputs_are_safe('<?php if ($ok) echo xn_json_encode_for_script($safe); ?>') || !inline_script_outputs_are_safe('<?= xn_json_encode_for_script($safe) ?>') || inline_script_outputs_are_safe('<?php ECHO $unsafe; ?>') || inline_script_outputs_are_safe('<?= $unsafe ?>') || inline_script_outputs_are_safe('<?php printf($unsafe); ?>') || inline_script_outputs_are_safe('<?php \printf($unsafe); ?>') || inline_script_outputs_are_safe('<?php include $path; ?>') || count($scriptBoundaryBlocks) != 1 || inline_script_outputs_are_safe($scriptBoundaryBlocks[0][1]) || count($unterminatedScriptBlocks) != 1 || inline_script_outputs_are_safe($unterminatedScriptBlocks[0][1])) {
	$errors[] = 'inline script expression guard must reject output appended after a safe encoder or numeric cast';
}

if (!empty($errors)) {
	fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
	exit(1);
}

echo "Frontend security smoke OK\n";
exit(0);
