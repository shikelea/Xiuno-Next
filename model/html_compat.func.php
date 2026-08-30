<?php

// Lightweight HTML token helpers for compatibility decisions. This is intentionally not a DOM
// normalizer: callers receive byte offsets and keep the original package/theme markup unchanged.
function xn_html_tag_boundary($html, $start, $name) {
	$offset = $start + 1 + strlen($name);
	if($offset >= strlen($html)) return TRUE;
	return strpos(" \t\r\n\f/>", $html[$offset]) !== FALSE;
}

function xn_html_tag_end($html, $start) {
	$length = strlen($html);
	$quote = '';
	for($i = $start; $i < $length; $i++) {
		$char = $html[$i];
		if($quote !== '') {
			if($char === $quote) $quote = '';
			continue;
		}
		if($char === '"' || $char === "'") {
			$quote = $char;
			continue;
		}
		if($char === '>') return $i + 1;
	}
	return FALSE;
}

function xn_html_tag_attribute($tag, $wanted, &$found = NULL) {
	$found = FALSE;
	$length = strlen($tag);
	$i = 1;
	if($i < $length && $tag[$i] === '/') $i++;
	while($i < $length && strpos(" \t\r\n\f/>", $tag[$i]) === FALSE) $i++;
	while($i < $length) {
		while($i < $length && strpos(" \t\r\n\f", $tag[$i]) !== FALSE) $i++;
		if($i >= $length || $tag[$i] === '>') break;
		if($tag[$i] === '/') {
			$i++;
			continue;
		}
		$name_start = $i;
		while($i < $length && strpos(" \t\r\n\f=/>", $tag[$i]) === FALSE) $i++;
		if($i === $name_start) {
			$i++;
			continue;
		}
		$name = strtolower(substr($tag, $name_start, $i - $name_start));
		while($i < $length && strpos(" \t\r\n\f", $tag[$i]) !== FALSE) $i++;
		$value = '';
		if($i < $length && $tag[$i] === '=') {
			$i++;
			while($i < $length && strpos(" \t\r\n\f", $tag[$i]) !== FALSE) $i++;
			if($i < $length && ($tag[$i] === '"' || $tag[$i] === "'")) {
				$quote = $tag[$i++];
				$value_start = $i;
				while($i < $length && $tag[$i] !== $quote) $i++;
				$value = substr($tag, $value_start, $i - $value_start);
				if($i < $length) $i++;
			} else {
				$value_start = $i;
				while($i < $length && strpos(" \t\r\n\f>", $tag[$i]) === FALSE) $i++;
				$value = substr($tag, $value_start, $i - $value_start);
			}
		}
		if($name === strtolower($wanted)) {
			$found = TRUE;
			return $value;
		}
	}
	return NULL;
}

// Decode one browser HTML-attribute parsing pass. PHP's HTML5 decoder handles named references,
// but requires a semicolon for numeric references that browsers also accept without one.
function xn_html_attribute_value_decode($value) {
	if(!is_string($value) || $value === '') return is_string($value) ? $value : '';
	$value = preg_replace_callback('/&#(?:x[0-9a-f]+|[0-9]+);?/i', function($match) {
		$entity = rtrim($match[0], ';').';';
		return html_entity_decode($entity, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
	}, $value);
	if(!is_string($value)) return '';
	return html_entity_decode($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

function xn_html_find_closing_tag($html, $name, $offset) {
	$needle = '</'.strtolower($name);
	$search = max(0, intval($offset));
	while(($start = stripos($html, $needle, $search)) !== FALSE) {
		if(xn_html_tag_boundary($html, $start, '/'.$name)) {
			$end = xn_html_tag_end($html, $start);
			if($end !== FALSE) return array('start'=>$start, 'end'=>$end, 'tag'=>substr($html, $start, $end - $start), 'closing'=>TRUE, 'name'=>strtolower($name));
			return FALSE;
		}
		$search = $start + strlen($needle);
	}
	return FALSE;
}

// Scan active markup only. Comments and raw/inert containers can contain code examples that look
// like tags; treating those bytes as forms or assets caused token injection and duplicate scripts.
function xn_html_scan_tags($html, $wanted = '', $offset = 0) {
	if(!is_string($html)) return array();
	$wanted = strtolower((string)$wanted);
	if($wanted !== '' && !preg_match('/^[a-z][a-z0-9:-]*$/D', $wanted)) return array();
	$length = strlen($html);
	$cursor = max(0, intval($offset));
	$tokens = array();
	// HTML raw-text/RCDATA elements only recognize their own closing tag. Template is included as
	// an intentionally inert container for compatibility decisions, even though its contents are
	// parsed into a separate document fragment by a browser.
	$raw_names = array(
		'script'=>1, 'style'=>1, 'title'=>1, 'textarea'=>1,
		'xmp'=>1, 'iframe'=>1, 'noembed'=>1, 'noframes'=>1,
		'template'=>1,
	);
	while($cursor < $length && ($start = strpos($html, '<', $cursor)) !== FALSE) {
		if(substr($html, $start, 4) === '<!--') {
			$comment_end = strpos($html, '-->', $start + 4);
			if($comment_end === FALSE) break;
			$cursor = $comment_end + 3;
			continue;
		}
		$i = $start + 1;
		$closing = FALSE;
		if($i < $length && $html[$i] === '/') {
			$closing = TRUE;
			$i++;
		}
		if($i >= $length || $html[$i] === '!' || $html[$i] === '?') {
			$end = xn_html_tag_end($html, $start);
			if($end === FALSE) break;
			$cursor = $end;
			continue;
		}
		$name_start = $i;
		while($i < $length && preg_match('/[A-Za-z0-9:-]/', $html[$i])) $i++;
		if($i === $name_start) {
			$cursor = $start + 1;
			continue;
		}
		$name = strtolower(substr($html, $name_start, $i - $name_start));
		$end = xn_html_tag_end($html, $start);
		if($end === FALSE) break;
		$token = array('start'=>$start, 'end'=>$end, 'tag'=>substr($html, $start, $end - $start), 'closing'=>$closing, 'name'=>$name);
		if($wanted === '' || $wanted === $name) $tokens[] = $token;
		$cursor = $end;
		// The obsolete plaintext element has no effective closing tag: every remaining byte belongs
		// to its text. Stopping here prevents tag-looking examples from becoming active markup.
		if(!$closing && $name === 'plaintext') break;
		if(!$closing && isset($raw_names[$name])) {
			$close = xn_html_find_closing_tag($html, $name, $end);
			if($close === FALSE) break;
			if($wanted === $name) $tokens[] = $close;
			$cursor = $close['end'];
		}
	}
	return $tokens;
}

// Return the first active document base. Raw text, comments, and template contents are already
// excluded by xn_html_scan_tags(), so package examples cannot change compatibility decisions.
function xn_html_base_href($html, &$found = NULL) {
	$found = FALSE;
	foreach(xn_html_scan_tags($html, 'base') as $base) {
		if(!empty($base['closing'])) continue;
		$href = xn_html_tag_attribute($base['tag'], 'href', $href_found);
		if(!$href_found) continue;
		$found = TRUE;
		return trim(xn_html_attribute_value_decode($href));
	}
	return NULL;
}

// Decide whether a browser will submit a form to the current origin. Empty actions target the
// current document even when a base exists; non-empty relative actions inherit the first base.
function xn_html_form_action_is_local($action, $base_href = NULL) {
	$action = trim((string)$action);
	if($action === '') return TRUE;
	// WHATWG special URLs normalize backslashes as path separators. Reject them before parse_url(),
	// which otherwise disagrees with the browser for protocol-relative cross-origin values.
	if(preg_match('/[\x00-\x20\x7F\\\\]/', $action)) return FALSE;
	if(preg_match('~^//~', $action)) return FALSE;
	if(!preg_match('~^[a-z][a-z0-9+.-]*:~i', $action)) {
		if($base_href === NULL || trim((string)$base_href) === '') return TRUE;
		return xn_html_form_action_is_local($base_href, NULL);
	}

	$parts = @parse_url($action);
	if(!is_array($parts) || !empty($parts['user']) || !empty($parts['pass'])) return FALSE;
	$scheme = isset($parts['scheme']) ? strtolower((string)$parts['scheme']) : '';
	$action_host = isset($parts['host']) ? strtolower((string)$parts['host']) : '';
	if(!in_array($scheme, array('http', 'https'), TRUE) || $action_host === '') return FALSE;
	$action_port = isset($parts['port']) ? intval($parts['port']) : ($scheme === 'https' ? 443 : 80);

	$current_scheme = function_exists('xn_cookie_secure') && xn_cookie_secure() ? 'https' : 'http';
	if(!function_exists('xn_cookie_secure')) {
		$https = strtolower(isset($_SERVER['HTTPS']) ? (string)$_SERVER['HTTPS'] : '');
		$forwarded = strtolower(isset($_SERVER['HTTP_X_FORWARDED_PROTO']) ? (string)$_SERVER['HTTP_X_FORWARDED_PROTO'] : '');
		$forwarded = trim(explode(',', $forwarded, 2)[0]);
		if($https === 'on' || $https === '1' || $forwarded === 'https' || intval(isset($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT'] : 0) === 443) {
			$current_scheme = 'https';
		}
	}
	$host_header = trim(isset($_SERVER['HTTP_HOST']) ? (string)$_SERVER['HTTP_HOST'] : '');
	if($host_header === '' || preg_match('/[\x00-\x20\x7F\\\\\/?#@]/', $host_header)) return FALSE;
	$current = @parse_url($current_scheme.'://'.$host_header);
	if(!is_array($current) || empty($current['host']) || isset($current['user']) || isset($current['pass'])) return FALSE;
	$current_host = strtolower((string)$current['host']);
	$current_port = isset($current['port']) ? intval($current['port']) : ($current_scheme === 'https' ? 443 : 80);
	return $scheme === $current_scheme && $action_host === $current_host && $action_port === $current_port;
}

function xn_html_post_form_action_matches($action, $route, $route_action) {
	$action = trim((string)$action);
	// An actionless form posts back to the already verified current route.
	if($action === '') return TRUE;
	$expected = '';
	if($route === 'thread' && $route_action === 'create') {
		$expected = 'thread-create';
	} elseif($route === 'post' && $route_action === 'create') {
		$expected = 'post-create';
	} elseif($route === 'post' && $route_action === 'update') {
		$expected = 'post-update';
	} else {
		return FALSE;
	}

	$parts = @parse_url($action);
	if(!is_array($parts)) return FALSE;
	$candidates = array();
	$path = isset($parts['path']) ? trim(str_replace('\\', '/', (string)$parts['path']), '/') : '';
	if($path !== '') {
		$slash = strrpos($path, '/');
		$candidates[] = $slash === FALSE ? $path : substr($path, $slash + 1);
	}
	if(isset($parts['query']) && $parts['query'] !== '') {
		$query_route = explode('&', (string)$parts['query'], 2)[0];
		if(strpos($query_route, '=') === FALSE) $candidates[] = $query_route;
	}
	$needs_identifier = $expected !== 'thread-create';
	$pattern = '~^'.preg_quote($expected, '~')
		.($needs_identifier ? '(?:-[0-9]+)+' : '(?:-[0-9]+)*')
		.'(?:\\.htm)?$~i';
	foreach($candidates as $candidate) {
		if(preg_match($pattern, $candidate)) return TRUE;
	}
	return FALSE;
}

function xn_html_tag_is_form_submitter($tag, $tag_name) {
	$tag_name = strtolower((string)$tag_name);
	$type = xn_html_tag_attribute($tag, 'type', $type_found);
	$type = $type_found ? strtolower(trim(xn_html_attribute_value_decode($type))) : '';
	if($tag_name === 'input') return $type === 'submit' || $type === 'image';
	if($tag_name !== 'button') return FALSE;
	// Missing and invalid button types both use the HTML submit-button state.
	return $type !== 'button' && $type !== 'reset';
}

function xn_html_has_active_id($html, $wanted) {
	foreach(xn_html_scan_tags($html) as $token) {
		if(!empty($token['closing'])) continue;
		$id = xn_html_tag_attribute($token['tag'], 'id', $found);
		if($found && xn_html_attribute_value_decode($id) === $wanted) return TRUE;
	}
	return FALSE;
}

function xn_html_form_control_names($body) {
	$names = array();
	foreach(xn_html_scan_tags($body) as $token) {
		if(!empty($token['closing']) || !in_array($token['name'], array('input', 'select', 'textarea', 'button'), TRUE)) continue;
		$name = xn_html_tag_attribute($token['tag'], 'name', $found);
		if(!$found) continue;
		$name = xn_html_attribute_value_decode($name);
		if($name !== '') $names[$name] = TRUE;
	}
	return $names;
}

// Normalize the browser-side subject limit and insert a visible submitter only for one unambiguous
// legacy core post form. The returned document otherwise remains byte-for-byte unchanged; callers
// provide the shared subject limit and already escaped trusted button HTML.
function xn_html_inject_post_submit_fallback($html, $route, $route_action, $button_html, $subject_maxlength = NULL) {
	if(!is_string($html) || !is_string($button_html) || $button_html === '') return $html;
	if(!(($route === 'thread' && $route_action === 'create')
		|| ($route === 'post' && in_array($route_action, array('create', 'update'), TRUE)))) return $html;

	$form_tokens = xn_html_scan_tags($html, 'form');
	$ranges = array();
	$open = NULL;
	foreach($form_tokens as $token) {
		if(empty($token['closing'])) {
			// Nested or overlapping form markup is browser-error-recovery territory; fail closed.
			if($open !== NULL) return $html;
			$open = $token;
			continue;
		}
		if($open === NULL) return $html;
		$ranges[] = array('open'=>$open, 'close'=>$token);
		$open = NULL;
	}
	if($open !== NULL) return $html;

	$base_href = xn_html_base_href($html, $base_found);
	$candidates = array();
	foreach($ranges as $range) {
		$open_tag = $range['open']['tag'];
		$id = xn_html_tag_attribute($open_tag, 'id', $id_found);
		$id = $id_found ? xn_html_attribute_value_decode($id) : '';
		if($id !== 'form') continue;
		$method = xn_html_tag_attribute($open_tag, 'method', $method_found);
		$method = $method_found ? trim(xn_html_attribute_value_decode($method)) : '';
		if(strcasecmp($method, 'post') !== 0) continue;
		$form_action = xn_html_tag_attribute($open_tag, 'action', $action_found);
		$form_action = $action_found ? trim(xn_html_attribute_value_decode($form_action)) : '';
		if(!xn_html_form_action_is_local($form_action, $base_found ? $base_href : NULL)
			|| !xn_html_post_form_action_matches($form_action, $route, $route_action)) continue;

		$body_start = intval($range['open']['end']);
		$body_end = intval($range['close']['start']);
		$body = substr($html, $body_start, $body_end - $body_start);
		$names = xn_html_form_control_names($body);
		$required = array('message', 'doctype');
		if($route === 'thread') $required = array_merge($required, array('subject', 'fid'));
		$complete = TRUE;
		foreach($required as $name) {
			if(!isset($names[$name])) {
				$complete = FALSE;
				break;
			}
		}
		if(!$complete) continue;

		$has_submitter = FALSE;
		$subject_input = NULL;
		foreach(xn_html_scan_tags($body) as $control) {
			if(!empty($control['closing']) || !in_array($control['name'], array('input', 'button'), TRUE)) continue;
			$form_owner = xn_html_tag_attribute($control['tag'], 'form', $owner_found);
			if($owner_found && xn_html_attribute_value_decode($form_owner) !== 'form') continue;
			$name = xn_html_tag_attribute($control['tag'], 'name', $name_found);
			$name = $name_found ? xn_html_attribute_value_decode($name) : '';
			if($route === 'thread' && $control['name'] === 'input' && $name === 'subject') {
				if($subject_input !== NULL) {
					$subject_input = FALSE;
					break;
				}
				$subject_input = $control;
			}
			if(xn_html_tag_is_form_submitter($control['tag'], $control['name'])) {
				$has_submitter = TRUE;
			}
		}
		if($route === 'thread' && !is_array($subject_input)) continue;
		$candidates[] = array('range'=>$range, 'has_submitter'=>$has_submitter, 'subject_input'=>$subject_input);
	}
	if(count($candidates) !== 1) return $html;

	$candidate = $candidates[0];
	$needs_submitter = empty($candidate['has_submitter']) && !xn_html_has_active_id($html, 'submit');
	if($needs_submitter) {
		// A submitter outside the form can still own it through form="form".
		foreach(xn_html_scan_tags($html) as $control) {
			if(!empty($control['closing']) || !in_array($control['name'], array('input', 'button'), TRUE)) continue;
			$form_owner = xn_html_tag_attribute($control['tag'], 'form', $owner_found);
			if(!$owner_found || xn_html_attribute_value_decode($form_owner) !== 'form') continue;
			if(xn_html_tag_is_form_submitter($control['tag'], $control['name'])) {
				$needs_submitter = FALSE;
				break;
			}
		}
	}

	$offset_delta = 0;
	if($route === 'thread' && intval($subject_maxlength) > 0) {
		$subject_tag = $candidate['subject_input']['tag'];
		xn_html_tag_attribute($subject_tag, 'maxlength', $maxlength_found);
		if(!$maxlength_found) {
			$subject_tag_new = preg_replace('~(?=\s*/?>$)~', ' maxlength="'.intval($subject_maxlength).'"', $subject_tag, 1);
			if(is_string($subject_tag_new) && $subject_tag_new !== $subject_tag) {
				$subject_offset = intval($candidate['range']['open']['end']) + intval($candidate['subject_input']['start']);
				$html = substr($html, 0, $subject_offset).$subject_tag_new.substr($html, $subject_offset + strlen($subject_tag));
				$offset_delta = strlen($subject_tag_new) - strlen($subject_tag);
			}
		}
	}

	if(!$needs_submitter) return $html;
	$offset = intval($candidate['range']['close']['start']) + $offset_delta;
	return substr($html, 0, $offset).$button_html.substr($html, $offset);
}

?>
