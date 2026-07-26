<?php

$root = dirname(__DIR__) . '/';
$session = file_get_contents($root . 'model/session.func.php');
$misc = file_get_contents($root . 'model/misc.func.php');
$plugin = file_get_contents($root . 'model/plugin.func.php');
$errors = array();

if($session === FALSE) {
	$errors[] = 'failed to read model/session.func.php';
} else {
	if(!preg_match('#function\s+sess_new\s*\(\s*\$sid\s*\)(.*?)(?=^function\s+\w|\z)#ms', $session, $m)) {
		$errors[] = 'sess_new() must exist.';
	} else {
		$sessNew = $m[1];
		if(strpos($sessNew, "xn_setcookie('cookie_test', '', \$time - 86400, '/');") === FALSE) {
			$errors[] = 'sess_new() must clear the cookie capability probe on the site-wide path.';
		}
		if(strpos($sessNew, "xn_setcookie('cookie_test', \$cookie_test, \$time + 86400, '/');") === FALSE) {
			$errors[] = 'sess_new() must keep the cookie capability probe on the site-wide path.';
		}
		if(preg_match('#else\s*\{(?:(?!^\s*\}).)*xn_setcookie\s*\(\s*\'cookie_test\'\s*,\s*\$cookie_test\s*,\s*\$time\s*\+\s*86400\s*(?:,\s*\'/\'\s*)?\)(?:(?!^\s*\}).)*\breturn\s*;#ms', $sessNew)) {
			$errors[] = 'first page views must still create a session row so generated CSRF tokens persist for the next POST.';
		}
		$insertFailurePos = strpos($sessNew, "if(db_insert('session', \$arr) === FALSE)");
		$existingReadPos = strpos($sessNew, "\$existing = db_find_one('session', array('sid'=>\$sid));");
		$reusePos = strpos($sessNew, "if(!empty(\$existing) && intval(\$existing['bigdata']) <= 1)");
		$reuseStatePos = strpos($sessNew, '$g_session = $existing;');
		$revokedPos = strpos($sessNew, "intval(\$existing['bigdata']) == 2");
		$failurePos = strrpos($sessNew, '$g_session_new_failed = TRUE;');
		$failureReturnPos = strrpos($sessNew, 'return FALSE;');
		$createdStatePos = strrpos($sessNew, '$g_session = $arr;');
		if($insertFailurePos === FALSE || $existingReadPos === FALSE || $reusePos === FALSE || $reuseStatePos === FALSE || $revokedPos === FALSE || $failurePos === FALSE || $failureReturnPos === FALSE || $createdStatePos === FALSE || !($insertFailurePos < $existingReadPos && $existingReadPos < $reusePos && $reusePos < $reuseStatePos && $reuseStatePos < $revokedPos && $revokedPos < $failurePos && $failurePos < $failureReturnPos && $failureReturnPos < $createdStatePos)) {
			$errors[] = 'sess_new() may reuse only a confirmed ordinary row from an insert race and must fail closed otherwise.';
		}
	}

	if(!preg_match('#function\s+sess_start\s*\(\s*\)(.*?)(?=^function\s+\w|\z)#ms', $session, $m)) {
		$errors[] = 'sess_start() must exist.';
	} else {
		$sessStart = $m[1];
		if(strpos($sessStart, "ini_set('session.cookie_path', '/');") === FALSE) {
			$errors[] = 'sess_start() must use the site-wide session cookie path.';
		}
		if(strpos($sessStart, "\$admin_cookie_path = substr(\$script_name, 0, \$admin_pos + 6);") === FALSE) {
			$errors[] = 'sess_start() must compute the stale admin-scoped cookie path from SCRIPT_NAME.';
		}
		if(strpos($sessStart, "xn_setcookie('bbs_sid', '', \$time - 86400, \$admin_cookie_path);") === FALSE) {
			$errors[] = 'sess_start() must expire stale admin-scoped session cookies.';
		}
		if(strpos($sessStart, "xn_setcookie('cookie_test', '', \$time - 86400, \$admin_cookie_path);") === FALSE) {
			$errors[] = 'sess_start() must expire stale admin-scoped cookie probe cookies.';
		}
	}
}

if($misc === FALSE || $plugin === FALSE) {
	$errors[] = 'failed to read model/misc.func.php or model/plugin.func.php';
} else {
	$injector = '';
	$actionHelper = '';
	if(!preg_match('#function\s+plugin_compat_form_action_is_local\s*\(\s*\$action\s*\)(.*?)(?=^function\s+\w|\z)#ms', $plugin, $m)) {
		$errors[] = 'plugin compatibility layer must keep a same-origin form action helper.';
	} else {
		$actionHelper = $m[1];
	}
	if(!preg_match('#function\s+plugin_compat_inject_csrf_forms\s*\(\s*\$message\s*\)(.*?)(?=^function\s+\w|\z)#ms', $plugin, $m)) {
		$errors[] = 'plugin compatibility layer must keep a CSRF injector for legacy POST forms.';
	} else {
		$injector = $m[1];
	}
	if(!preg_match('#function\s+message\s*\(\s*\$code\s*,\s*\$message\s*,\s*\$extra\s*=\s*array\s*\(\s*\)\s*\)(.*?)(?=^function\s+\w|\z)#ms', $misc, $m)) {
		$errors[] = 'message() must exist.';
	} else {
		$messageBody = $m[1];
		$injectPos = strpos($messageBody, '$message = plugin_compat_inject_csrf_forms($message);');
		$arrMessagePos = strpos($messageBody, '$arr[\'message\'] = $message;');
		if($injectPos === FALSE) {
			$errors[] = 'message() must run the CSRF form compatibility injector before output.';
		} elseif($arrMessagePos !== FALSE && $injectPos > $arrMessagePos) {
			$errors[] = 'message() must run the CSRF form compatibility injector before message is copied into the response payload.';
		}
	}
	if($injector !== '' && strpos($injector, 'stripos($message, \'<form\')') === FALSE) {
		$errors[] = 'CSRF fragment injector must skip non-form messages cheaply.';
	}
	if($injector !== '' && strpos($injector, 'function_exists(\'csrf_token\')') === FALSE) {
		$errors[] = 'CSRF fragment injector must require csrf_token() before generating hidden fields.';
	}
	if($injector !== '' && (strpos($injector, '\\smethod\\s*=\\s*') === FALSE || strpos($injector, 'post\\1(?=[\\s>/])') === FALSE)) {
		$errors[] = 'CSRF fragment injector must only inject hidden _token fields into real POST forms with an attribute-value boundary.';
	}
	$replaceNeedle = <<<'PHP'
preg_replace_callback('~(<form\b(?=[^>]*\smethod\s*=\s*([\'"]?)post\2(?=[\s>/]))
PHP;
	if($injector !== '' && strpos($injector, $replaceNeedle) === FALSE) {
		$errors[] = 'CSRF fragment injector must inject only through a POST-form-matching replacement path with an attribute-value boundary.';
	}
	if($injector !== '' && strpos($injector, '<input type="hidden" name="_token" value="') === FALSE) {
		$errors[] = 'CSRF fragment injector must add hidden _token fields to legacy POST forms.';
	}
	if($injector !== '' && (strpos($injector, '$body = preg_replace(') === FALSE || strpos($injector, '_token\1(?=[\s>/])') === FALSE || strpos($injector, "name=\"_token\" value=\"") === FALSE)) {
		$errors[] = 'CSRF fragment injector must replace existing exact-name _token fields with one current session token.';
	}
	if($injector !== '' && strpos($injector, 'html_entity_decode(trim($action), ENT_QUOTES, \'UTF-8\')') === FALSE) {
		$errors[] = 'CSRF fragment injector must decode form actions before external-action checks.';
	}
	if($injector !== '' && strpos($injector, 'plugin_compat_form_action_is_local($action)') === FALSE) {
		$errors[] = 'CSRF fragment injector must validate decoded actions through the same-origin helper.';
	}
	if($actionHelper !== '' && strpos($actionHelper, 'preg_match(\'~^//~\', $action)') === FALSE) {
		$errors[] = 'CSRF fragment injector must skip protocol-relative form actions to avoid token disclosure.';
	}
	if($actionHelper !== '' && strpos($actionHelper, 'in_array($scheme, array(\'http\', \'https\'), TRUE)') === FALSE) {
		$errors[] = 'CSRF fragment injector must allow same-site http/https absolute form actions only.';
	}
	if($actionHelper !== '' && strpos($actionHelper, "xn_cookie_secure() ? 'https' : 'http'") === FALSE) {
		$errors[] = 'CSRF fragment injector must compare absolute actions against the current request scheme.';
	}
	if($actionHelper !== '' && (strpos($actionHelper, '$scheme === $current_scheme') === FALSE || strpos($actionHelper, '$action_host === $current_host') === FALSE || strpos($actionHelper, '$action_port === $current_port') === FALSE)) {
		$errors[] = 'CSRF fragment injector must require matching scheme, host, and port before disclosing a token.';
	}
}

if(!empty($errors)) {
	fwrite(STDERR, implode(PHP_EOL, $errors).PHP_EOL);
	exit(1);
}

echo "OK: CSRF session safety checks passed\n";
exit(0);

?>
