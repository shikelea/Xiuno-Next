<?php

$root = dirname(__DIR__) . '/';
$errors = array();

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

if (!empty($errors)) {
	fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
	exit(1);
}

echo "Frontend security smoke OK\n";
exit(0);
