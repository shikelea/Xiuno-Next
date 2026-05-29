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

if($css === FALSE) $errors[] = 'failed to read view/css/bs4-compat.css';
if($css_min === FALSE) $errors[] = 'failed to read view/css/bs4-compat.min.css';
if($js === FALSE) $errors[] = 'failed to read view/js/bs4-compat.js';
if($js_min === FALSE) $errors[] = 'failed to read view/js/bs4-compat.min.js';

if(empty($errors)) {
	$css_selectors = array(
		'.form-group'=>'form-group spacing',
		'.custom-select'=>'custom-select styling',
		'.custom-control'=>'custom-control wrapper',
		'.custom-control-input'=>'custom-control input',
		'.custom-file'=>'custom-file wrapper',
		'.custom-file-input'=>'custom-file input',
		'.custom-file-label'=>'custom-file label',
		'.form-row'=>'form-row layout',
		'.badge-primary'=>'contextual badge colors',
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
		'.btn-group-toggle'=>'button toggle group',
	);
	foreach($css_selectors as $selector=>$label) {
		require_contains($css, $selector, "bs4-compat.css must keep $label ($selector).");
	}

	foreach(array('.form-group', '.btn-block', '.custom-file', '.input-group-prepend', '.btn-group-toggle') as $selector) {
		require_contains($css_min, $selector, "bs4-compat.min.css must include $selector.");
	}

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
		'jQuery.fn.reset'=>'Xiuno reset helper fallback',
		'jQuery.fn.checked'=>'Xiuno checked helper fallback',
		'jQuery.fn.alert'=>'Xiuno alert helper fallback',
		'querySelectorAll(\'.custom-file-input\')'=>'custom-file change binding',
		'querySelectorAll(\'.dropdown-menu-right, .dropdown-menu-left\')'=>'dropdown alignment binding',
		'contains(\'close\')'=>'legacy close button click binding',
		'querySelectorAll(\'[data-toggle="buttons"],[data-bs-toggle="buttons"]\')'=>'btn-group-toggle binding',
		'querySelectorAll(\'[data-toggle="tab"],[data-bs-toggle="tab"]\')'=>'tab href binding',
		"ajaxMethod === 'POST' && isSameOrigin"=>'same-origin jQuery CSRF boundary',
		"method === 'POST' && isSameOrigin(input)"=>'same-origin fetch CSRF boundary',
	) as $needle=>$label) {
		require_contains($js, $needle, "bs4-compat.js must keep $label.");
	}

	foreach(array('jQuery.fn.modal', 'jQuery.fn.button', 'data-bs-content', 'isSameOrigin') as $needle) {
		require_contains($js_min, $needle, "bs4-compat.min.js must include $needle.");
	}
}

if(!empty($errors)) {
	fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
	exit(1);
}

echo "OK: BS4 compatibility layer checks passed\n";
