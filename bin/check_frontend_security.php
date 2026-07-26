<?php

$root = dirname(__DIR__) . '/';
$errors = array();

require_once $root . 'xiunophp/misc.func.php';

function script_expression_is_safe($expression) {
	$tokens = token_get_all('<?php '.$expression.';');
	$allowedFunctions = array('xn_json_encode_for_script', 'intval', 'lang', 'csrf_token', 'url', 'http_referer');
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
	$allowedFunctions = array('xn_json_encode_for_script', 'intval', 'lang', 'csrf_token', 'url', 'http_referer', 'is_dir');
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
	if (strpos($indexEntry, "stripos(\$html, '../view/js/bs4-compat') === false") === FALSE) {
		$errors[] = 'compatibility injector must not treat admin/view bs4-compat paths as valid admin assets';
	}
	if (strpos($indexEntry, "\$html .= \$body_inject;") === FALSE) {
		$errors[] = 'compatibility injector must append JS when an overwritten theme omits closing body/html tags';
	}
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
	if (strpos($bs4Compat, "ajaxMethod === 'POST' && isSameOrigin") === FALSE) {
		$errors[] = 'jQuery CSRF injector must only attach tokens to same-origin POST requests';
	}
	if (strpos($bs4Compat, 'window._csrf_ajax_setup_jquery !== jQuery') === FALSE) {
		$errors[] = 'jQuery CSRF injector must reinstall when legacy themes replace window.jQuery';
	}
	if (strpos($bs4Compat, "method === 'POST' && isSameOrigin(input)") === FALSE) {
		$errors[] = 'fetch CSRF injector must only attach tokens to same-origin POST requests';
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
}

$xiunoJs = file_get_contents($root . 'view/js/xiuno.js');
if ($xiunoJs === FALSE) {
	$errors[] = 'failed to read view/js/xiuno.js';
} else {
	if (strpos($xiunoJs, 'function xn_postdata_with_csrf(postdata)') === FALSE) {
		$errors[] = '$.xpost must keep a direct CSRF token fallback for legacy themes that override ajaxSetup';
	}
	if (strpos($xiunoJs, "postdata = xn_postdata_with_csrf(postdata);") === FALSE) {
		$errors[] = '$.xpost must attach CSRF tokens before sending POST requests';
	}
	if (strpos($xiunoJs, "postdata._token === undefined") === FALSE) {
		$errors[] = '$.xpost CSRF fallback must not overwrite explicit _token values';
	}
	if (strpos($xiunoJs, 'postdata instanceof FormData') === FALSE || strpos($xiunoJs, "postdata.has('_token')") === FALSE || strpos($xiunoJs, 'postdata.entries()') === FALSE || strpos($xiunoJs, "postdata.append('_token', token)") === FALSE) {
		$errors[] = '$.xpost CSRF fallback must support FormData and avoid duplicating explicit _token values when detectable';
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
	if (strpos($pluginModel, 'is_file($overwrite_file) && !is_link($overwrite_file)') === FALSE) {
		$errors[] = 'plugin/theme overwrite resolution must not follow symlink overwrite files';
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
