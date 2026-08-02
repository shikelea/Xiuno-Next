<?php

$root = dirname(__DIR__) . '/';
$errors = array();

function require_contains($content, $needle, $message) {
	global $errors;
	if(strpos($content, $needle) === FALSE) $errors[] = $message;
}

$css = file_get_contents($root . 'view/css/bs4-compat.css');
$css_min = file_get_contents($root . 'view/css/bs4-compat.min.css');
$js = file_get_contents($root . 'view/js/bs4-compat.js');
$js_min = file_get_contents($root . 'view/js/bs4-compat.min.js');
$font_awesome = file_get_contents($root . 'view/css/font-awesome.min.css');
$index = file_get_contents($root . 'index.php');

if($css === FALSE) $errors[] = 'failed to read view/css/bs4-compat.css';
if($css_min === FALSE) $errors[] = 'failed to read view/css/bs4-compat.min.css';
if($js === FALSE) $errors[] = 'failed to read view/js/bs4-compat.js';
if($js_min === FALSE) $errors[] = 'failed to read view/js/bs4-compat.min.js';
if($font_awesome === FALSE) $errors[] = 'failed to read view/css/font-awesome.min.css';
if($index === FALSE) $errors[] = 'failed to read index.php';

if(empty($errors)) {
	$css_selectors = array(
		'.form-group'=>'form-group spacing',
		'.custom-select'=>'custom-select styling',
		'.custom-control'=>'custom-control wrapper',
		'.custom-control-input'=>'custom-control input',
		'.custom-control-label'=>'custom-control label',
		'.custom-checkbox'=>'custom checkbox variant',
		'.custom-switch .custom-control-label::before'=>'custom switch track',
		'.custom-file'=>'custom-file wrapper',
		'.custom-file-input'=>'custom-file input',
		'.custom-file-label'=>'custom-file label',
		'.form-row'=>'form-row layout',
		'.badge-primary'=>'contextual badge colors',
		'.badge-secondary'=>'contextual secondary badge color',
		'.badge-success'=>'contextual success badge color',
		'.badge-danger'=>'contextual danger badge color',
		'.badge-warning'=>'contextual warning badge color',
		'.badge-info'=>'contextual info badge color',
		'.badge-light'=>'contextual light badge color',
		'.badge-dark'=>'contextual dark badge color',
		'.badge-pill'=>'badge pill shape',
		'.float-left'=>'legacy float-left utility',
		'.float-right'=>'legacy float-right utility',
		'.text-left'=>'legacy text-left utility',
		'.text-right'=>'legacy text-right utility',
		'.sr-only'=>'screen-reader utility',
		'.btn-block'=>'block button utility',
		'.dropdown-menu-right'=>'dropdown right alignment',
		'.dropdown-menu-left'=>'dropdown left alignment',
		'.close'=>'legacy close button',
		'.media'=>'media object layout',
		'.jumbotron'=>'jumbotron component',
		'.card-deck'=>'card deck layout',
		'.card-columns'=>'card columns layout',
		'.embed-responsive'=>'responsive embed wrapper',
		'.input-group-prepend'=>'input group prepend wrapper',
		'.input-group-append'=>'input group append wrapper',
		'.ml-1'=>'left margin utility',
		'.mr-1'=>'right margin utility',
		'.pl-1'=>'left padding utility',
		'.pr-1'=>'right padding utility',
		'.border-right'=>'right border utility',
		'.rounded-right'=>'right radius utility',
		'.las'=>'Line Awesome fallback base class',
		'.la-calendar'=>'Line Awesome calendar icon fallback',
		'.la-user'=>'Line Awesome user icon fallback',
		'.la-chart-bar'=>'Line Awesome chart icon fallback',
		'.la-calendar-check'=>'Line Awesome calendar-check icon fallback',
		'.icon-magic'=>'Font Awesome 4 magic icon fallback',
		'.icon-check'=>'Font Awesome 4 check icon fallback',
		'.btn-group-toggle'=>'button toggle group',
	);
	foreach($css_selectors as $selector=>$label) {
		require_contains($css, $selector, "bs4-compat.css must keep $label ($selector).");
	}

	foreach(array('.form-group', '.btn-block', '.custom-file', '.custom-control-label', '.badge-danger', '.input-group-prepend', '.btn-group-toggle', '.las', '.la-calendar', '.la-user', '.la-chart-bar', '.la-calendar-check', '.icon-magic', '.icon-check') as $selector) {
		require_contains($css_min, $selector, "bs4-compat.min.css must include $selector.");
	}

	if(!preg_match('/\[class\^="icon-"\]\s*,\s*\[class\*=" icon-"\]\s*\{([^}]+)\}/', $css, $legacy_icon_rule)) {
		$errors[] = 'bs4-compat.css must define the generic legacy icon base rule.';
	} else {
		require_contains($legacy_icon_rule[1], 'FontAwesome', 'legacy icon base rule must select the FontAwesome font');
		require_contains($legacy_icon_rule[1], 'font-size: inherit', 'legacy icon base rule must preserve inherited icon sizing');
	}
	require_contains($font_awesome, "font-family:'FontAwesome'", 'Font Awesome asset must embed the FontAwesome font face');
	require_contains($font_awesome, '.fa-check:before{content:"\\f00c"}', 'Font Awesome asset must contain the check glyph mapping');

	foreach(array(
		"'data-toggle':    'data-bs-toggle'",
		"'data-dismiss':   'data-bs-dismiss'",
		"'data-target':    'data-bs-target'",
		"'data-parent':    'data-bs-parent'",
		"'data-ride':      'data-bs-ride'",
		"'data-slide':     'data-bs-slide'",
		"'data-slide-to':  'data-bs-slide-to'",
		"'data-offset':    'data-bs-offset'",
		"'data-spy':       'data-bs-spy'",
		"'data-interval':  'data-bs-interval'",
		"'data-backdrop':  'data-bs-backdrop'",
		"'data-keyboard':  'data-bs-keyboard'",
		"'data-focus':     'data-bs-focus'",
		"'data-placement': 'data-bs-placement'",
		"'data-trigger':   'data-bs-trigger'",
		"'data-container': 'data-bs-container'",
	) as $mapping) {
		require_contains($js, $mapping, "bs4-compat.js must keep attribute mapping $mapping.");
	}

	foreach(array(
		'data-bs-content'=>'popover data-content conversion',
		'jQuery.fn.modal'=>'modal jQuery API proxy',
		'jQuery.fn.button'=>'button jQuery API proxy',
		'jQuery.fn.tooltip'=>'tooltip jQuery API proxy',
		'jQuery.fn.popover'=>'popover jQuery API proxy',
		'jQuery.fn.location'=>'Xiuno location helper fallback',
		'button[type="submit"]'=>'submit button reset coverage',
		'jQuery.fn.reset'=>'Xiuno reset helper fallback',
		'jQuery.fn.checked'=>'Xiuno checked helper fallback',
		'jQuery.fn.alert'=>'Xiuno alert helper fallback',
		"jfeedback.text(message).addClass('d-block');"=>'visible field validation feedback',
		"jthis.off('input.xn-alert change.xn-alert').one('input.xn-alert change.xn-alert'"=>'field validation feedback clear timing',
		'xn-alert-original-aria-describedby'=>'field validation aria-describedby preservation',
		'querySelectorAll(\'.custom-file-input\')'=>'custom-file change binding',
		'querySelectorAll(\'.dropdown-menu-right, .dropdown-menu-left\')'=>'dropdown alignment binding',
		'contains(\'close\')'=>'legacy close button click binding',
		'querySelectorAll(\'[data-toggle="buttons"],[data-bs-toggle="buttons"]\')'=>'btn-group-toggle binding',
		'querySelectorAll(\'[data-toggle="tab"],[data-bs-toggle="tab"]\')'=>'tab href binding',
		'actionRe = /(?:^|[?&\\/-])plugin-(download|install|enable|disable|unstall|upgrade)-/'=>'plugin action URL POST repair boundary',
		'function isSafePluginHref(href)'=>'plugin action URL same-origin boundary',
		'url.protocol === window.location.protocol && url.host === window.location.host'=>'plugin action URL same-origin check',
		"links[i].setAttribute('data-method', 'post');"=>'legacy admin plugin action POST repair',
		"e.target.closest ? e.target.closest('a') : null"=>'legacy admin plugin action click repair target lookup',
		'if (!link || !isPluginWriteLink(link)) return;'=>'legacy admin plugin action click repair boundary',
		"link.setAttribute('data-method', 'post');"=>'legacy admin plugin action click repair must only delegate POST handling',
		"ajaxMethod === 'POST' && isSameOrigin"=>'same-origin jQuery CSRF boundary',
		'window._csrf_ajax_setup_jquery !== jQuery'=>'CSRF rebind after legacy jQuery replacement',
		"method === 'POST' && isSameOrigin(input)"=>'same-origin fetch CSRF boundary',
		'function convertLegacyIcons(root)'=>'legacy icon DOM bridge',
		"var modernIconClasses = ['fa', 'fas', 'far', 'fab', 'fal', 'fad', 'la', 'las', 'lar', 'lab', 'lal', 'lad'];"=>'modern icon compatibility boundary',
		'if (element.classList.contains(modernIconClasses[i])) return;'=>'modern icon classes must not be rewritten',
		"element.classList.add('fa-' + classes[ci].slice(5));"=>'legacy icon name bridge',
		"if (converted) element.classList.add('fa');"=>'legacy Font Awesome base class bridge',
		'convertLegacyIcons(root);'=>'legacy icon bridge invocation',
		'convertAttributes(n);'=>'dynamic legacy icon observer coverage',
	) as $needle=>$label) {
		require_contains($js, $needle, "bs4-compat.js must keep $label.");
	}

	foreach(array(
		'jQuery.fn.modal',
		'jQuery.fn.button',
		'data-bs-content',
		'isSameOrigin',
		"jfeedback.text(message).addClass('d-block');",
		"jthis.off('input.xn-alert change.xn-alert').one('input.xn-alert change.xn-alert'",
		'xn-alert-original-aria-describedby',
		'plugin-(download|install|enable|disable|unstall|upgrade)-',
		'function isSafePluginHref',
		"setAttribute('data-method', 'post')",
		'_csrf_ajax_setup_jquery',
		'function convertLegacyIcons(root)',
		"classList.add('fa-' + classes[ci].slice(5))",
		"classList.add('fa')",
	) as $needle) {
		require_contains($js_min, $needle, "bs4-compat.min.js must include $needle.");
	}

	$injector_start = strpos($index, 'function xn_compat_inject_output($html)');
	$injector_end = $injector_start === FALSE ? FALSE : strpos($index, "ob_start('xn_compat_inject_output');", $injector_start);
	if($injector_start === FALSE || $injector_end === FALSE) {
		$errors[] = 'index.php must expose the compatibility output injector for behavioral checks.';
	} else {
		$injector_source = substr($index, $injector_start, $injector_end - $injector_start);
		eval($injector_source);
		if(!function_exists('csrf_token')) {
			function csrf_token() { return 'compat-check-token'; }
		}

		$_SERVER['conf'] = array('view_url'=>'view/', 'static_version'=>'?compat-check');
		$_SERVER['SCRIPT_NAME'] = '/index.php';
		$theme_html = '<!doctype html><html><head><link rel="stylesheet" href="plugin/theme/line-awesome.min.css"></head><body><i class="icon-check"></i></body></html>';
		$theme_output = xn_compat_inject_output($theme_html);
		require_contains($theme_output, 'href="view/css/font-awesome.min.css?compat-check"', 'theme output must inject Font Awesome when a replacement header omits it');
		require_contains($theme_output, 'href="view/css/bs4-compat.css?compat-check"', 'theme output must inject the compatibility stylesheet');
		require_contains($theme_output, 'src="view/js/bs4-compat.js?compat-check"', 'theme output must inject the compatibility script');
		if(strpos($theme_output, 'font-awesome.min.css') > strpos($theme_output, 'bs4-compat.css')) {
			$errors[] = 'Font Awesome must load before the compatibility stylesheet that defines legacy icon aliases.';
		}

		$existing_font_html = '<html><head><link rel="stylesheet" href="view/css/font-awesome.min.css?existing"></head><body></body></html>';
		$existing_font_output = xn_compat_inject_output($existing_font_html);
		if(substr_count($existing_font_output, 'font-awesome.min.css') !== 1) {
			$errors[] = 'compatibility output injector must not duplicate an existing Font Awesome asset.';
		}

		$_SERVER['SCRIPT_NAME'] = '/admin/index.php';
		$admin_output = xn_compat_inject_output('<html><head></head><body></body></html>');
		require_contains($admin_output, 'href="../view/css/font-awesome.min.css?compat-check"', 'admin output must inject Font Awesome from the correct relative path');

		$json = '{"code":0,"message":"ok"}';
		if(xn_compat_inject_output($json) !== $json) {
			$errors[] = 'compatibility output injector must leave non-HTML responses unchanged.';
		}
	}
}

if(!empty($errors)) {
	fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
	exit(1);
}

echo "OK: BS4 compatibility layer checks passed\n";
