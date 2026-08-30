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
		$existingReadPos = strpos($sessNew, '$existing = sess_find_one_primary($sid);');
		$reusePos = strpos($sessNew, "if(!empty(\$existing) && intval(\$existing['bigdata']) <= 1)");
		$reuseStatePos = strpos($sessNew, '$g_session = $existing;');
		$revokedPos = strpos($sessNew, "intval(\$existing['bigdata']) == 2");
		$failurePos = strrpos($sessNew, '$g_session_new_failed = TRUE;');
		$failureReturnPos = strrpos($sessNew, 'return FALSE;');
		$createdStatePos = strrpos($sessNew, '$g_session = $arr;');
		if($insertFailurePos === FALSE || $existingReadPos === FALSE || $reusePos === FALSE || $reuseStatePos === FALSE || $revokedPos === FALSE || $failurePos === FALSE || $failureReturnPos === FALSE || $createdStatePos === FALSE || !($insertFailurePos < $existingReadPos && $existingReadPos < $reusePos && $reusePos < $reuseStatePos && $reuseStatePos < $revokedPos && $revokedPos < $failurePos && $failurePos < $failureReturnPos && $failureReturnPos < $createdStatePos)) {
			$errors[] = 'sess_new() may reuse only a primary-confirmed ordinary row from an insert race and must fail closed otherwise.';
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
	if(!preg_match('#function\s+plugin_compat_form_action_is_local\s*\(\s*\$action\s*,\s*\$base_href\s*=\s*NULL\s*\)(.*?)(?=^function\s+\w|\z)#ms', $plugin, $m)) {
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
	if($injector !== '' && strpos($injector, '<input type="hidden" name="_token" value="') === FALSE) {
		$errors[] = 'CSRF fragment injector must add hidden _token fields to legacy POST forms.';
	}
	if($injector !== '' && (strpos($plugin, 'function plugin_compat_html_tag_end(') === FALSE
		|| strpos($plugin, 'function plugin_compat_html_tag_attribute(') === FALSE
		|| strpos($plugin, 'function plugin_compat_html_remove_token_inputs(') === FALSE)) {
		$errors[] = 'CSRF fragment injector must use quote-aware tag and attribute parsing helpers.';
	}
	// The concrete same-origin parser lives in model/html_compat.func.php and is exercised by the
	// behavior cases below. Keep this guard coupled only to the public delegation boundary instead
	// of duplicating the helper's internal source shape here.
	if($actionHelper !== '' && strpos($actionHelper, 'return xn_html_form_action_is_local($action, $base_href);') === FALSE) {
		$errors[] = 'CSRF fragment injector must delegate action classification to the shared HTML compatibility helper.';
	}
	if($injector !== '' && strpos($injector, 'plugin_compat_form_action_is_local($action)') === FALSE) {
		if(strpos($injector, 'plugin_compat_form_action_is_local($action, $base_found ? $base_href : NULL)') === FALSE) {
			$errors[] = 'CSRF fragment injector must validate decoded actions and the active document base through the same-origin helper.';
		}
	}
	if($injector !== '' && strpos($injector, 'plugin_compat_html_base_href($message, $base_found)') === FALSE) {
		$errors[] = 'CSRF fragment injector must resolve the first active base href before classifying explicit relative actions.';
	}
}

if(!defined('DEBUG')) define('DEBUG', 0);
if(!defined('APP_PATH')) define('APP_PATH', $root);
if(!defined('ADMIN_PATH')) define('ADMIN_PATH', $root.'admin/');
if(!defined('XIUNOPHP_PATH')) define('XIUNOPHP_PATH', $root.'xiunophp/');
if(!function_exists('csrf_token')) {
	function csrf_token() { return 'csrf-fixture'; }
}
if(!function_exists('xn_cookie_secure')) {
	function xn_cookie_secure() { return FALSE; }
}
$_SERVER['HTTP_HOST'] = 'forum.test';
require_once $root.'model/plugin.func.php';

$quoted_form = 'before<form data-label="step > one" method = "POST" action="/save?x=1&gt;0">'
	.'<input value="old > token" name="_token" type="hidden">'
	.'<input name="_token_extra" value="keep > marker"><span>body</span></form>after';
$quoted_expected = 'before<form data-label="step > one" method = "POST" action="/save?x=1&gt;0">'
	.'<input type="hidden" name="_token" value="csrf-fixture">'
	.'<input name="_token_extra" value="keep > marker"><span>body</span></form>after';
$quoted_actual = plugin_compat_inject_csrf_forms($quoted_form);
if($quoted_actual !== $quoted_expected) {
	$errors[] = 'CSRF fragment injector must preserve quoted > bytes, replace only the exact _token input, and avoid rendering tag residue.';
}

$external_form = '<form method="post" data-label="external > form" action="https://evil.test/save?x=1&gt;0"><span>external</span></form>';
if(plugin_compat_inject_csrf_forms($external_form) !== $external_form) {
	$errors[] = 'CSRF fragment injector must leave a quoted cross-origin POST form byte-for-byte unchanged.';
}
$encoded_external_action = '<form method="post" action="https&colon;//evil.test/save"><span>encoded external</span></form>';
if(plugin_compat_inject_csrf_forms($encoded_external_action) !== $encoded_external_action) {
	$errors[] = 'CSRF fragment injector must decode HTML5 named references before classifying a form action.';
}
$numeric_external_action = '<form method="post" action="https&#58//evil.test/save"><span>numeric external</span></form>';
if(plugin_compat_inject_csrf_forms($numeric_external_action) !== $numeric_external_action) {
	$errors[] = 'CSRF fragment injector must decode browser-compatible numeric references without semicolons before classifying a form action.';
}
$backslash_action = str_repeat('\\', 2).'evil.test/collect';
$backslash_form = '<form method="post" action="'.$backslash_action.'"><span>browser-special external</span></form>';
if(plugin_compat_inject_csrf_forms($backslash_form) !== $backslash_form) {
	$errors[] = 'CSRF fragment injector must leave a browser-normalized backslash cross-origin POST form byte-for-byte unchanged.';
}
$get_form = '<form data-label="method=post > decoy" method="get"><span>get</span></form>';
if(plugin_compat_inject_csrf_forms($get_form) !== $get_form) {
	$errors[] = 'CSRF fragment injector must not infer POST from text inside another quoted attribute.';
}
$multiple_forms = '<form method=post><b>one</b></form><form method="POST" action=""><b>two</b></form>';
$multiple_actual = plugin_compat_inject_csrf_forms($multiple_forms);
if(substr_count($multiple_actual, '<input type="hidden" name="_token" value="csrf-fixture">') !== 2) {
	$errors[] = 'CSRF fragment injector must independently process every local POST form in a message fragment.';
}
$nested_external_form = '<form method="post" action="https://evil.test/collect"><form method="post" action="/local"><b>nested</b></form>';
if(plugin_compat_inject_csrf_forms($nested_external_form) !== $nested_external_form) {
	$errors[] = 'CSRF fragment injector must fail closed when malformed nested form markup could place a local injection inside a cross-origin browser form.';
}
$encoded_method_form = '<form method="p&#111;st"><input name="&lowbar;token" value="old"><b>encoded attributes</b></form>';
$encoded_method_actual = plugin_compat_inject_csrf_forms($encoded_method_form);
if(substr_count($encoded_method_actual, '<input type="hidden" name="_token" value="csrf-fixture">') !== 1
	|| strpos($encoded_method_actual, '&lowbar;token') !== FALSE) {
	$errors[] = 'CSRF fragment injector must decode method and input-name attributes, replace the browser-equivalent token, and avoid duplicate CSRF values.';
}

$external_base_form = '<base href="https://evil.test/flow/"><form method="post" action="save"><b>external base</b></form>';
if(plugin_compat_inject_csrf_forms($external_base_form) !== $external_base_form) {
	$errors[] = 'CSRF fragment injector must not disclose a token to an explicit relative action resolved by a cross-origin base href.';
}
$encoded_external_base_form = '<base href="https&colon;//evil.test/flow/"><form method="post" action="save"><b>encoded external base</b></form>';
if(plugin_compat_inject_csrf_forms($encoded_external_base_form) !== $encoded_external_base_form) {
	$errors[] = 'CSRF fragment injector must decode an HTML5 entity in the active base URL before resolving relative actions.';
}
$backslash_base_form = '<base href="'.$backslash_action.'"><form method="post" action="save"><b>backslash base</b></form>';
if(plugin_compat_inject_csrf_forms($backslash_base_form) !== $backslash_base_form) {
	$errors[] = 'CSRF fragment injector must reject a relative action resolved through a browser-normalized backslash base URL.';
}
$local_base_form = '<base href="/admin/flow/"><form method="post" action="save"><b>local base</b></form>';
$local_base_actual = plugin_compat_inject_csrf_forms($local_base_form);
if(substr_count($local_base_actual, '<input type="hidden" name="_token" value="csrf-fixture">') !== 1) {
	$errors[] = 'CSRF fragment injector must keep explicit relative actions resolved by a local base href.';
}
$external_base_current_document = '<base href="https://evil.test/flow/"><form method="post" action=""><b>empty action</b></form><form method="post"><b>actionless</b></form>';
$external_base_current_actual = plugin_compat_inject_csrf_forms($external_base_current_document);
if(substr_count($external_base_current_actual, '<input type="hidden" name="_token" value="csrf-fixture">') !== 2) {
	$errors[] = 'CSRF fragment injector must keep empty and actionless forms bound to the current document despite an external base href.';
}
$first_active_base_form = '<base><template><base href="https://evil.test/inert/"></template><base href="/first/"><base href="https://evil.test/later/"><form method="post" action="save"><b>first active href</b></form>';
$first_active_base_actual = plugin_compat_inject_csrf_forms($first_active_base_form);
if(substr_count($first_active_base_actual, '<input type="hidden" name="_token" value="csrf-fixture">') !== 1) {
	$errors[] = 'CSRF fragment injector must use the first active base element that actually has an href and ignore inert/later bases.';
}

$inactive_forms = '<!-- <form method="post"><input name="_token" value="comment"></form> -->'
	.'<script>var sample = \'<form method="post"><input name="_token" value="script"></form>\';</script>'
	.'<style>.sample::after{content:"<form method=post>"}</style>'
	.'<textarea><form method="post"><input name="_token" value="textarea"></form></textarea>'
	.'<template><form method="post"><input name="_token" value="template"></form></template>';
if(plugin_compat_inject_csrf_forms($inactive_forms) !== $inactive_forms) {
	$errors[] = 'CSRF fragment injector must leave comments, script/style, textarea and template code examples byte-for-byte unchanged.';
}
$mixed_forms = $inactive_forms.'<form method="post"><input name="_token" value="real-old"><b>real</b></form>';
$mixed_actual = plugin_compat_inject_csrf_forms($mixed_forms);
if(substr_count($mixed_actual, '<input type="hidden" name="_token" value="csrf-fixture">') !== 1 || strpos($mixed_actual, $inactive_forms) !== 0) {
	$errors[] = 'CSRF fragment injector must update only the active form when inert form-like text is present.';
}

if(!empty($errors)) {
	fwrite(STDERR, implode(PHP_EOL, $errors).PHP_EOL);
	exit(1);
}

echo "OK: CSRF session safety checks passed\n";
exit(0);

?>
