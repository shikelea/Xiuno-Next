<?php

$root = dirname(__DIR__) . '/';
$errors = array();
require_once $root . 'model/html_compat.func.php';

function require_contains($content, $needle, $message) {
	global $errors;
	if(strpos($content, $needle) === FALSE) $errors[] = $message;
}

$css = file_get_contents($root . 'view/css/bs4-compat.css');
$css_min = file_get_contents($root . 'view/css/bs4-compat.min.css');
$js = file_get_contents($root . 'view/js/bs4-compat.js');
$js_min = file_get_contents($root . 'view/js/bs4-compat.min.js');
$xiuno_js = file_get_contents($root . 'view/js/xiuno.js');
$browser_fixture = file_get_contents($root . 'bin/fixtures/bs4_compat_runtime.html');
$browser_runner = file_get_contents($root . 'bin/check_bs4_compat_browser.sh');
$windows_browser_runner = file_get_contents($root . 'bin/check_bs4_compat_browser.ps1');
$workflow = file_get_contents($root . '.github/workflows/ci.yml');
$font_awesome = file_get_contents($root . 'view/css/font-awesome.min.css');
$index = file_get_contents($root . 'index.php');
$install_footer = file_get_contents($root . 'install/view/htm/footer.inc.htm');

if($css === FALSE) $errors[] = 'failed to read view/css/bs4-compat.css';
if($css_min === FALSE) $errors[] = 'failed to read view/css/bs4-compat.min.css';
if($js === FALSE) $errors[] = 'failed to read view/js/bs4-compat.js';
if($js_min === FALSE) $errors[] = 'failed to read view/js/bs4-compat.min.js';
if($xiuno_js === FALSE) $errors[] = 'failed to read view/js/xiuno.js';
if($browser_fixture === FALSE) $errors[] = 'failed to read bin/fixtures/bs4_compat_runtime.html';
if($browser_runner === FALSE) $errors[] = 'failed to read bin/check_bs4_compat_browser.sh';
if($windows_browser_runner === FALSE) $errors[] = 'failed to read bin/check_bs4_compat_browser.ps1';
if($workflow === FALSE) $errors[] = 'failed to read .github/workflows/ci.yml';
if($font_awesome === FALSE) $errors[] = 'failed to read view/css/font-awesome.min.css';
if($index === FALSE) $errors[] = 'failed to read index.php';
if($install_footer === FALSE) $errors[] = 'failed to read install/view/htm/footer.inc.htm';

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
		'.icon-ok'=>'core message success icon fallback',
		'.btn-group-toggle'=>'button toggle group',
	);
	foreach($css_selectors as $selector=>$label) {
		require_contains($css, $selector, "bs4-compat.css must keep $label ($selector).");
	}

	foreach(array('.form-group', '.btn-block', '.custom-file', '.custom-control-label', '.badge-danger', '.input-group-prepend', '.btn-group-toggle', '.las', '.la-calendar', '.la-user', '.la-chart-bar', '.la-calendar-check', '.icon-magic', '.icon-check', '.icon-ok') as $selector) {
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
		"'data-autohide':  'data-bs-autohide'",
		"'data-html':      'data-bs-html'",
		"'data-delay':     'data-bs-delay'",
		"'data-animation': 'data-bs-animation'",
		"'data-template':  'data-bs-template'",
		"'data-boundary':  'data-bs-boundary'",
		"'data-wrap':      'data-bs-wrap'",
		"'data-pause':     'data-bs-pause'",
		"'data-touch':     'data-bs-touch'",
	) as $mapping) {
		require_contains($js, $mapping, "bs4-compat.js must keep attribute mapping $mapping.");
	}

	// Runtime contract: a second source/min load refreshes the singleton; the first
	// load owns one observer/listener set and rebinds every new jQuery identity.
	foreach(array(
		"existingRuntime.refresh(document)"=>'duplicate-load refresh contract',
		"window.XiunoCompat = runtime"=>'public singleton runtime',
		"window.__xnBs4CompatLoaded = true"=>'legacy load marker',
		'function eachRootAndDescendant(root, selector, callback)'=>'root and descendant traversal helper',
		"root.nodeType === 1 && typeof root.matches === 'function'"=>'dynamic root self coverage',
		"document.addEventListener('xiuno:fragment-ready'"=>'fragment lifecycle refresh',
		"event.detail && event.detail.elt"=>'fragment detail root selection',
		'var observedAttributes = legacyAttributes.concat(modernAttributes'=>'bounded legacy/modern attribute observation',
		'attributeFilter: observedAttributes'=>'bounded MutationObserver attribute filter',
		'var attrSelector = legacyAttributes.concat(ownershipAttributes)'=>'owned mapping cleanup selector',
		'runtime.scheduleRefresh(mutation.target)'=>'dynamic container and attribute refresh',
		'function hasBootstrapDropdownStructure(toggle)'=>'structure-aware dropdown mapping',
		"toggle.closest('.dropdown, .dropup, .dropend, .dropstart, .btn-group')"=>'generic Bootstrap dropdown container check',
		"return modernAttribute.replace(/^data-bs-/, 'data-xn-bs-auto-');"=>'runtime mapping ownership marker',
		"allowed = hasBootstrapDropdownStructure(element)"=>'dropdown mapping semantic filter',
		"element.removeAttribute(modernAttribute)"=>'owned mapping cleanup',
		"var componentNames = ['alert', 'button', 'carousel', 'collapse', 'dropdown', 'modal', 'offcanvas', 'popover', 'scrollspy', 'tab', 'toast', 'tooltip'];"=>'complete Bootstrap jQuery adapter set',
		"var componentConstructorNames = { scrollspy: 'ScrollSpy' };"=>'ScrollSpy constructor spelling',
		'function captureCurrentImplementations(jq, state)'=>'per-jQuery original implementation capture',
		'function installJQueryAdapters(jq)'=>'jQuery identity installer',
		'installXiunoCoreSnapshot(jq)'=>'explicit Xiuno API reinstall',
		'runtime.jquery = jq'=>'current jQuery identity tracking',
		'proxy.Constructor = original && original.Constructor ? original.Constructor : Component'=>'real Constructor preservation',
		'proxy.noConflict = function ()'=>'noConflict adapter',
		'restoreOriginal(jq, state, name)'=>'captured implementation restoration',
		"action.show !== false"=>'modal show:false initialization boundary',
		"message === 'close' || message === 'dispose'"=>'alert component close semantics',
		"this.filter('.alert').length === this.length"=>'alert component versus field dispatch',
		'function getOrCreateComponent(Component, element, options)'=>'uninitialized tooltip/popover action support',
		'function installModalProxy(jq, state)'=>'modal adapter installer',
		'function installDropdownProxy(jq, state)'=>'dropdown adapter installer',
		'function installTabProxy(jq, state)'=>'tab adapter installer',
		'function installButtonProxy(jq, state)'=>'button adapter installer',
		'function compatButtonApply(jq, element, action)'=>'button state compatibility contract',
		"radio.dispatchEvent(new Event('change', { bubbles: true }))"=>'button group change event contract',
		'function installAlertHelper(jq, state)'=>'alert adapter installer',
		'function installTooltipProxy(jq, state)'=>'tooltip adapter installer',
		'function installPopoverProxy(jq, state)'=>'popover adapter installer',
		'function installCarouselProxy(jq, state)'=>'carousel adapter installer',
		'function installCollapseProxy(jq, state)'=>'collapse adapter installer',
		'function installOffcanvasProxy(jq, state)'=>'offcanvas adapter installer',
		'function installScrollSpyProxy(jq, state)'=>'scrollspy adapter installer',
		'function installToastProxy(jq, state)'=>'toast adapter installer',
		'function normalizeComponentCollection(collection)'=>'synchronous component attribute normalization',
		"jfeedback.text(message).addClass('d-block');"=>'visible field validation feedback',
		"jthis.off('input.xn-alert change.xn-alert').one('input.xn-alert change.xn-alert'"=>'field validation feedback clear timing',
		'xn-alert-original-aria-describedby'=>'field validation aria-describedby preservation',
		'function restoreFieldAlertTitle(jthis)'=>'field validation title restoration',
		"typeof window.xn_field_alert_show === 'function'"=>'shared field alert ownership helper',
		'runtime.clearFieldAlert = function (element)'=>'public field alert cleanup contract',
		'function bindCustomFile(root)'=>'custom-file dynamic binding',
		'function fixDropdownAlign(root)'=>'dropdown alignment dynamic binding',
		'function initBtnGroupToggle(root)'=>'button group dynamic binding',
		'function fixTabHref(root)'=>'tab dynamic binding',
		'function isSafePluginHref(href)'=>'plugin action URL same-origin boundary',
		'url.protocol === window.location.protocol && url.host === window.location.host'=>'plugin action URL same-origin check',
		"link.setAttribute('data-method', 'post')"=>'legacy admin plugin action POST repair',
		"ajaxMethod === 'POST' && sameOrigin"=>'same-origin jQuery CSRF boundary',
		"jq.ajaxPrefilter('+*', state.csrfPrefilter)"=>'priority cross-origin jQuery header stripping',
		'function guardLocalCsrfRequestHeader(xhr, token)'=>'direct jqXHR header guard',
		"callBeforeSendWithoutCrossOriginCsrf"=>'cross-origin chained jQuery hook guarding',
		"var shouldInject = token && method === 'POST' && sameOrigin;"=>'same-origin fetch CSRF boundary',
		"var shouldStrip = token && !sameOrigin && currentHeader === token;"=>'cross-origin fetch CSRF stripping',
		'function cloneFetchBodyWithoutLocalCsrf(body, headers, token)'=>'cross-origin fetch body CSRF normalizer',
		"bodyHasType(body, 'FormData', 'FormData')"=>'FormData body classification',
		"bodyHasType(body, 'URLSearchParams', 'URLSearchParams')"=>'URLSearchParams body classification',
		"mediaType !== 'application/x-www-form-urlencoded'"=>'explicit form-encoded string boundary',
		"name === '_token' && typeof value === 'string' && value === localToken"=>'scalar FormData token ownership boundary',
		"Object.prototype.hasOwnProperty.call(init, 'body')"=>'caller-owned fetch body cloning boundary',
		'var nextInit = {};'=>'caller-owned fetch init cloning',
		'return window.Promise.reject(error);'=>'invalid HeadersInit rejected-promise semantics',
		"input && typeof input.href === 'string'"=>'fetch URL object origin resolution',
		'function formSubmissionDetails(form, submitter)'=>'effective form submission contract resolution',
		"submitter.hasAttribute('formaction') ? submitter.formAction : form.action"=>'submitter formaction resolution',
		"submitter.hasAttribute('formmethod') ? submitter.formMethod : form.method"=>'submitter formmethod resolution',
		'function removeLocalCsrf(form, token)'=>'dynamic cross-origin form token removal',
		'function stripLocalCsrfFormData(formData, token, ownedValues)'=>'formdata selective local token removal',
		"var targetMarker = 'data-xn-bs-auto-tab-target';"=>'tab href ownership marker',
		"document.addEventListener('submit'"=>'submit-time CSRF origin revalidation',
		"document.addEventListener('formdata'"=>'native form.submit CSRF origin revalidation',
		'function isCoreQuickReplyAction(action)'=>'strict core quick reply route classification',
		"document.querySelectorAll('form#quick_reply_form').length !== 1"=>'unique quick reply form boundary',
		"document.querySelector('[data-xn-thread-post-count]')"=>'current-theme count marker boundary',
		'function installLegacyQuickReplyReload(jq, state)'=>'legacy quick reply reload lifecycle',
		"ajaxSend.xnLegacyQuickReplyReload"=>'quick reply request identity binding',
		"response.code === 0 || response.code === '0'"=>'strict quick reply success code boundary',
		'window.location.reload();'=>'server-state quick reply restoration',
		"if (event.target && event.target.tagName === 'SCRIPT') runtime.refresh(document);"=>'synchronous dependency-script rebind',
		'function convertLegacyIcons(root)'=>'legacy icon DOM bridge',
		"var modernIconClasses = ['fa', 'fas', 'far', 'fab', 'fal', 'fad', 'la', 'las', 'lar', 'lab', 'lal', 'lad'];"=>'modern icon compatibility boundary',
		'if (element.classList.contains(modernIconClasses[i])) return;'=>'modern icon classes must not be rewritten',
		"element.classList.add('fa-' + classes[ci].slice(5));"=>'legacy icon name bridge',
		"if (converted) element.classList.add('fa');"=>'legacy Font Awesome base class bridge',
	) as $needle=>$label) {
		require_contains($js, $needle, "bs4-compat.js must keep $label.");
	}

	// The generated minified file is intentionally allowed to lag during focused
	// source work; release asset generation/checks reconcile it in the parent task.
	foreach(array('window.__xnBs4CompatLoaded', 'installModalProxy', 'installButtonProxy', 'installTooltipProxy', 'installPopoverProxy', 'installAlertHelper', 'installLegacyQuickReplyReload', '_legacyQuickReplyPending', 'data-xn-thread-post-count', '_xnPatched') as $needle) {
		require_contains($js_min, $needle, "existing bs4-compat.min.js baseline must include $needle.");
	}

	foreach(array(
		"var staticNames = ['location', 'pdata', 'cookie', 'xget', 'xpost', 'required', 'require', 'require_css', 'each_sync'];"=>'Xiuno static API whitelist',
		"var fnNames = ['loading', 'base64_encode_file', 'removeDeep', 'emptyDeep', 'son', 'checked', 'button', 'location', 'alert', 'serializeObject', 'attr_name_index', 'reset', 'base_href', 'xn_position', 'xn_menu', 'xn_dropdown', 'xn_toggle'];"=>'Xiuno fn API whitelist',
		'Object.getOwnPropertyDescriptor(owner, names[i])'=>'descriptor snapshot',
		'global.XiunoJQueryCore = {'=>'published Xiuno jQuery snapshot',
		'installDescriptors(target, staticNames, staticDescriptors)'=>'static whitelist reinstall',
		'installDescriptors(target.fn, fnNames, fnDescriptors)'=>'fn whitelist reinstall',
		'global.XiunoCompat.refresh(document)'=>'runtime refresh after xiuno.js load',
		'window.xn_button_apply = xn_button_apply'=>'shared button state helper',
		'window.xn_field_alert_show = xn_field_alert_show'=>'shared field alert helper',
		'window.xn_field_alert_clear = xn_field_alert_clear'=>'owned tooltip cleanup helper',
		'window.xn_fragment_ready = xn.fragment_ready'=>'shared fragment lifecycle helper',
		"var installedTargets = typeof WeakSet !== 'undefined' ? new WeakSet() : [];"=>'replaceable jQuery weak target tracking',
	) as $needle=>$label) {
		require_contains($xiuno_js, $needle, "xiuno.js must keep $label.");
	}

	foreach(array(
		'/view/js/jquery-3.1.0.js',
		'/view/js/popper.js',
		'/view/js/bootstrap.js',
		'/view/js/xiuno.js',
		'/view/js/bs4-compat.js',
		'testCompatFirst',
		'testHeadCompatWithoutReload',
		'testJQueryReplacement',
		'modal({ show: false })',
		"alert('close')",
		"tooltip('show')",
		"popover('show')",
		'data-xn-bs-auto-toggle',
		'data-xn-bs-auto-tab-target',
		'removed legacy placement cleans its owned mapping',
		'late dropdown-menu class mutation revalidates its toggle',
		'inserting a ready dropdown-menu revalidates an existing sibling toggle',
		'field alert cleanup restores a caller-owned title',
		'field alert restores a hidden caller tooltip without disposing it',
		'field alert preserves the visible state of a caller-owned tooltip',
		'form reset leaves unrelated caller-owned tooltips intact',
		'button reset restores an empty label and original disabled state',
		'button group radio toggle updates the checked value and emits one change event',
		'button group ignores a disabled input without emitting change',
		'core fragment-ready helper drives the shared compatibility lifecycle',
		'cross-origin xpost strips the current session token',
		'cross-origin fetch strips the transmitted current token only',
		'cross-origin fetch FormData strips only the scalar current token and preserves external tokens, files and fields',
		'cross-origin fetch URLSearchParams strips only the current token',
		'cross-origin fetch strips the current token from an explicitly form-encoded string',
		'cross-origin fetch does not reinterpret a string body without a form-encoded content type',
		'same-origin fetch preserves the caller FormData body while injecting the header token',
		'cross-origin submitter formaction removes the local token before submission',
		'browser-normalized backslash cross-origin form action never retains the local token',
		'same-origin POST submitter override receives the current token',
		'formdata rechecks submitter mutations made during submit handlers',
		'core quick reply route shape ',
		'same-origin route suffix lookalikes fail closed',
		'quick reply lookalikes missing a core request field remain untouched',
		'ambiguous duplicate return_html controls fail closed',
		'cross-origin quick reply lookalikes never receive navigation behavior',
		'GET submitter overrides remain outside the quick reply fallback',
		'current themes with the dedicated thread count marker keep local quick reply updates',
		'malformed success transport bodies never trigger a reload',
		'false-like response codes are not accepted as success',
		'a bound request whose settings change fails closed and clears pending state',
		'expired quick reply state cannot affect a later request',
		'successful legacy quick reply reloads once without requiring optional doctype or quote fields',
		'absolute current-origin compatibility assets resist an external document base',
		'xiuno:fragment-ready',
	) as $needle) {
		require_contains($browser_fixture, $needle, "browser fixture must cover runtime behavior ($needle).");
	}
	require_contains($browser_fixture, "get('assets') === 'min'", 'browser fixture must support generated asset coverage.');
	require_contains($browser_runner, '--virtual-time-budget=30000', 'browser runner must allow the asynchronous compatibility fixture to finish.');
	require_contains($browser_runner, 'data-failed="0"', 'browser runner must fail when any behavior assertion fails.');
	require_contains($browser_runner, '?assets=min', 'browser runner must execute the generated frontend assets as well as sources.');
	require_contains($browser_runner, '${CHROME_BIN:-}', 'browser runner must accept an explicit Chromium executable.');
	require_contains($browser_runner, '${XIUNO_TEST_HOME:-}', 'browser runner must support an isolated test home.');
	require_contains($browser_runner, '--user-data-dir=', 'browser runner must isolate the Chromium profile.');
	require_contains($windows_browser_runner, 'XIUNO_TEST_HOME', 'Windows browser runner must support an isolated test home.');
	require_contains($windows_browser_runner, 'WaitForExit(45000)', 'Windows browser runner must enforce an OS-level browser timeout.');
	require_contains($windows_browser_runner, '-WindowStyle Hidden', 'Windows browser runner must keep background browser/server processes hidden.');
	require_contains($workflow, 'php bin/run_checks.php --profile=browser --fail-on-skip', 'CI must execute the manifest-classified Chromium compatibility profile without accepting SKIP.');
	$install_xiuno_position = strpos($install_footer, '../view/js/xiuno.js');
	$install_compat_position = strpos($install_footer, '../view/js/bs4-compat.js');
	if($install_xiuno_position === FALSE || $install_compat_position === FALSE || $install_compat_position < $install_xiuno_position) {
		$errors[] = 'installer footer must load bs4-compat.js after xiuno.js so legacy button APIs keep final ownership.';
	}

	foreach(array(
		'jq.fn.modal',
		'jq.fn.button',
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

	$injector_start = strpos($index, 'function xn_compat_response_content_type()');
	$injector_end = $injector_start === FALSE ? FALSE : strpos($index, "ob_start('xn_compat_output_handler', 8192);", $injector_start);
	if($injector_start === FALSE || $injector_end === FALSE) {
		$errors[] = 'index.php must expose the compatibility output injector for behavioral checks.';
	} else {
		$injector_source = substr($index, $injector_start, $injector_end - $injector_start);
		eval($injector_source);
		if(!function_exists('csrf_token')) {
			function csrf_token() { return 'compat-check-token'; }
		}
		if(!function_exists('lang')) {
			function lang($key) {
				return isset($GLOBALS['compat_check_lang'][$key])
					? $GLOBALS['compat_check_lang'][$key]
					: $key;
			}
		}
		if(!function_exists('thread_subject_maxlength')) {
			function thread_subject_maxlength() { return 128; }
		}

		$_SERVER['conf'] = array('view_url'=>'view/', 'static_version'=>'?compat-check');
		$_SERVER['SCRIPT_NAME'] = '/index.php';
		$_SERVER['HTTP_HOST'] = 'forum.test';
		$_SERVER['SERVER_PORT'] = 80;
		$_SERVER['HTTPS'] = 'off';
		$theme_html = '<!doctype html><html><head><link rel="stylesheet" href="plugin/theme/line-awesome.min.css"></head><body><i class="icon-check"></i></body></html>';
		$theme_output = xn_compat_inject_output($theme_html);
		require_contains($theme_output, 'href="view/css/font-awesome.min.css?compat-check"', 'theme output must inject Font Awesome when a replacement header omits it');
		require_contains($theme_output, 'href="view/css/bs4-compat.css?compat-check"', 'theme output must inject the compatibility stylesheet');
		require_contains($theme_output, 'src="view/js/bs4-compat.js?compat-check"', 'theme output must inject the compatibility script');
		if(strpos($theme_output, 'font-awesome.min.css') > strpos($theme_output, 'bs4-compat.css')) {
			$errors[] = 'Font Awesome must load before the compatibility stylesheet that defines legacy icon aliases.';
		}

		// A complete legacy theme overwrite can omit the visible #submit while retaining the old
		// inline script that queues its success redirect on $('#submit'). Repair only the unique
		// same-origin core post form in final HTML, before that inline script is parsed.
		$GLOBALS['compat_check_lang'] = array(
			'thread_create'=>'Create <topic>',
			'post_create'=>'Reply & continue',
			'post_update'=>'Edit "post"',
			'submiting'=>'Working > now',
		);
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$GLOBALS['route'] = 'thread';
		$GLOBALS['action'] = 'create';
		$legacy_post_form = '<!doctype html><html><head></head><body>'
			.'<form data-note="2 > 1" id="form" method="POST" action="?thread-create.htm">'
			.'<input name="doctype" value="1"><select name="fid"><option value="1">Forum</option></select>'
			.'<input name="subject"><textarea name="message"></textarea><a>Add attachment</a></form>'
			.'<script>var jsubmit = $("#submit");</script></body></html>';
		$legacy_post_output = xn_compat_inject_output($legacy_post_form);
		if(substr_count($legacy_post_output, 'data-xn-compat-post-submit="1"') !== 1
			|| substr_count($legacy_post_output, 'id="submit"') !== 1
			|| substr_count($legacy_post_output, 'maxlength="128"') !== 1
			|| strpos($legacy_post_output, 'Create &lt;topic&gt;') === FALSE
			|| strpos($legacy_post_output, 'Working &gt; now') === FALSE
			|| strpos($legacy_post_output, 'data-xn-compat-post-submit="1"') > strpos($legacy_post_output, '</form>')
			|| strpos($legacy_post_output, 'id="submit"') > strpos($legacy_post_output, 'var jsubmit')) {
			$errors[] = 'a legacy core thread form without a submitter must receive one localized compatibility submit before its inline script.';
		}
		$legacy_post_second_pass = xn_compat_inject_output($legacy_post_output);
		if(substr_count($legacy_post_second_pass, 'data-xn-compat-post-submit="1"') !== 1
			|| substr_count($legacy_post_second_pass, 'id="submit"') !== 1
			|| substr_count($legacy_post_second_pass, 'maxlength="128"') !== 1) {
			$errors[] = 'legacy post submit injection must remain idempotent.';
		}

		$existing_submit_shapes = array(
			'<button id="submit">Create</button>',
			'<input type="submit" value="Create">',
			'<input type="image" src="submit.png">',
			'</form><button type="submit" form="form">Create</button><form hidden>',
		);
		foreach($existing_submit_shapes as $submit_shape) {
			$html = str_replace('</form>', $submit_shape.'</form>', $legacy_post_form);
			$output = xn_compat_inject_output($html);
			if(strpos($output, 'data-xn-compat-post-submit="1"') !== FALSE) {
				$errors[] = 'legacy post submit injection must not duplicate native or form-associated submit controls.';
				break;
			}
		}
		$native_submit_output = xn_compat_inject_output(str_replace('</form>', '<button id="submit">Create</button></form>', $legacy_post_form));
		if(substr_count($native_submit_output, 'maxlength="128"') !== 1) {
			$errors[] = 'a trusted legacy thread form with a native submitter must still receive the shared subject limit.';
		}
		$existing_limit_form = str_replace('<input name="subject">', '<input name="subject" maxlength="80">', $legacy_post_form);
		$existing_limit_output = xn_compat_inject_output($existing_limit_form);
		if(substr_count($existing_limit_output, 'maxlength="80"') !== 1 || strpos($existing_limit_output, 'maxlength="128"') !== FALSE) {
			$errors[] = 'legacy thread subject compatibility must preserve an explicit package limit.';
		}
		$global_submit_id = str_replace('</body>', '<div id="submit"></div></body>', $legacy_post_form);
		if(strpos(xn_compat_inject_output($global_submit_id), 'data-xn-compat-post-submit="1"') !== FALSE) {
			$errors[] = 'legacy post submit injection must fail closed when the document already owns id=submit.';
		}

		$unsafe_post_forms = array(
			str_replace('method="POST"', 'method="GET"', $legacy_post_form),
			str_replace('?thread-create.htm', 'https://evil.example/thread-create.htm', $legacy_post_form),
			str_replace('<head>', '<head><base href="https://evil.example/theme/">', $legacy_post_form),
			str_replace('<textarea name="message"></textarea>', '', $legacy_post_form),
			str_replace('<input name="subject">', '', $legacy_post_form),
			str_replace('<select name="fid"><option value="1">Forum</option></select>', '', $legacy_post_form),
			str_replace('</body>', str_replace('<!doctype html><html><head></head><body>', '', $legacy_post_form).'</body>', $legacy_post_form),
			str_replace('</form>', '', $legacy_post_form),
		);
		foreach($unsafe_post_forms as $unsafe_post_form) {
			if(strpos(xn_compat_inject_output($unsafe_post_form), 'data-xn-compat-post-submit="1"') !== FALSE) {
				$errors[] = 'legacy post submit injection must fail closed for non-POST, cross-origin, incomplete, ambiguous, or malformed forms.';
				break;
			}
		}

		$inactive_submit_markup = str_replace(
			'<a>Add attachment</a>',
			'<!-- <button id="submit">fake</button> --><script>var fake = "<input type=submit>";</script>'
			.'<textarea name="example"><button id="submit">fake</button></textarea>'
			.'<template><button id="submit">fake</button></template><a>Add attachment</a>',
			$legacy_post_form
		);
		if(substr_count(xn_compat_inject_output($inactive_submit_markup), 'data-xn-compat-post-submit="1"') !== 1) {
			$errors[] = 'comments and raw or inert containers must not suppress a required legacy post submitter.';
		}

		$GLOBALS['route'] = 'post';
		$GLOBALS['action'] = 'create';
		$legacy_reply_form = '<!doctype html><html><head></head><body><form id="form" method="post" action="?post-create-7-0.htm">'
			.'<input name="doctype" value="1"><textarea name="message"></textarea></form></body></html>';
		$legacy_reply_output = xn_compat_inject_output($legacy_reply_form);
		if(strpos($legacy_reply_output, 'data-xn-compat-post-submit="1"') === FALSE
			|| strpos($legacy_reply_output, 'Reply &amp; continue') === FALSE) {
			$errors[] = 'legacy advanced reply forms must receive the generic post-create submit fallback.';
		}
		$GLOBALS['action'] = 'update';
		$legacy_update_form = str_replace('?post-create-7-0.htm', 'http://forum.test/?post-update-9.htm', $legacy_reply_form);
		$legacy_update_output = xn_compat_inject_output($legacy_update_form);
		if(strpos($legacy_update_output, 'data-xn-compat-post-submit="1"') === FALSE
			|| strpos($legacy_update_output, 'Edit &quot;post&quot;') === FALSE) {
			$errors[] = 'legacy same-origin post-update forms must receive the generic localized submit fallback.';
		}
		$_SERVER['REQUEST_METHOD'] = 'POST';
		if(strpos(xn_compat_inject_output($legacy_update_form), 'data-xn-compat-post-submit="1"') !== FALSE) {
			$errors[] = 'post-response HTML must not receive a new compatibility submitter.';
		}
		$_SERVER['REQUEST_METHOD'] = 'GET';
		unset($GLOBALS['route'], $GLOBALS['action']);

		$existing_font_html = '<html><head><link rel="stylesheet" href="view/css/font-awesome.min.css?existing"></head><body></body></html>';
		$existing_font_output = xn_compat_inject_output($existing_font_html);
		if(substr_count($existing_font_output, 'font-awesome.min.css') !== 1) {
			$errors[] = 'compatibility output injector must not duplicate an existing Font Awesome asset.';
		}

		// 默认模板加载 .min 资产时，注入器必须识别它们，不得再注入源码版造成整层双加载
		$min_assets_html = '<html><head><link rel="stylesheet" href="view/css/font-awesome.min.css?v=1"><link rel="stylesheet" href="view/css/bs4-compat.min.css?v=1"></head><body><script src="view/js/bs4-compat.min.js?v=1"></script></body></html>';
		$min_assets_output = xn_compat_inject_output($min_assets_html);
		if(preg_match_all('~bs4-compat(?:\.min)?\.css~i', $min_assets_output) !== 1) {
			$errors[] = 'compatibility output injector must recognize the minified stylesheet and not inject a second copy.';
		}
		if(preg_match_all('~bs4-compat(?:\.min)?\.js~i', $min_assets_output) !== 1) {
			$errors[] = 'compatibility output injector must recognize the minified script and not inject a second copy.';
		}

		$encoded_asset_html = '<html><head><link rel="stylesheet" href="view/css/font-awesome.min.css">'
			.'<link rel="stylesheet" href="view/css/bs4-compat.min.css"></head><body>'
			.'<script src="view/js/bs4-compat&#46js"></script></body></html>';
		$encoded_asset_output = xn_compat_inject_output($encoded_asset_html);
		$encoded_script_values = xn_compat_html_tag_attribute_values($encoded_asset_output, 'script', 'src');
		if(count($encoded_script_values) !== 1 || reset($encoded_script_values) !== 'view/js/bs4-compat.js') {
			$errors[] = 'compatibility output injector must use browser-compatible attribute decoding before deciding an asset is missing.';
		}

		$quoted_asset_html = '<html><head><link data-note="1 > 0" rel="stylesheet" href="view/css/font-awesome.min.css">'
			.'<link title="2 > 1" rel="stylesheet" href="view/css/bs4-compat.min.css"></head><body>'
			.'<script data-note="3 > 2" src="view/js/bs4-compat.min.js"></script></body></html>';
		$quoted_asset_output = xn_compat_inject_output($quoted_asset_html);
		if(substr_count($quoted_asset_output, 'font-awesome.min.css') !== 1
			|| preg_match_all('~bs4-compat(?:\.min)?\.css~i', $quoted_asset_output) !== 1
			|| preg_match_all('~bs4-compat(?:\.min)?\.js~i', $quoted_asset_output) !== 1) {
			$errors[] = 'compatibility output injector must preserve quoted > attributes and recognize the existing assets.';
		}

		$inactive_asset_html = '<html><head><!-- <link href="view/css/bs4-compat.min.css"> -->'
			.'<script>var sample = \'<script src="view/js/bs4-compat.min.js">\';</script>'
			.'<textarea><link href="view/css/font-awesome.min.css"></textarea>'
			.'<template><link href="view/css/bs4-compat.min.css"></template></head><body></body></html>';
		$inactive_asset_output = xn_compat_inject_output($inactive_asset_html);
		$active_compat_scripts = array_filter(xn_compat_html_tag_attribute_values($inactive_asset_output, 'script', 'src'), function($value) {
			return preg_match('~(?:^|/)bs4-compat(?:\.min)?\.js(?:[?#].*)?$~i', $value);
		});
		$active_compat_styles = array_filter(xn_compat_html_tag_attribute_values($inactive_asset_output, 'link', 'href'), function($value) {
			return preg_match('~(?:^|/)bs4-compat(?:\.min)?\.css(?:[?#].*)?$~i', $value);
		});
		if(count($active_compat_scripts) !== 1 || count($active_compat_styles) !== 1) {
			$errors[] = 'compatibility output injector must ignore asset-looking text in comments and inert/raw containers.';
		}

		$fake_head_closures = '<!doctype html><html><head>'
			.'<!-- </head> --><script>var marker = "</head>";</script>'
			.'<style>.sample::after{content:"</head>"}</style>'
			.'<title>literal </head> marker</title><textarea></head></textarea>'
			.'<xmp></head></xmp><iframe></head></iframe><noembed></head></noembed>'
			.'<noframes></head></noframes><template></head></template>'
			.'</head><body></body></html>';
		$fake_head_output = xn_compat_inject_output($fake_head_closures);
		$true_head_close = strripos($fake_head_output, '</head>');
		$compat_position = strpos($fake_head_output, 'src="view/js/bs4-compat.js?compat-check"');
		if($true_head_close === FALSE || $compat_position === FALSE || $compat_position > $true_head_close
			|| strpos($fake_head_output, 'var marker = "</head>";') === FALSE
			|| strpos($fake_head_output, 'content:"</head>"') === FALSE) {
			$errors[] = 'compatibility output injector must insert only before the active closing head, never inside raw or inert text.';
		}
		$plaintext_head_tokens = xn_html_scan_tags('<html><head><plaintext>literal </head><body><form></form>', 'head');
		$plaintext_head_closures = array_filter($plaintext_head_tokens, function($token) { return !empty($token['closing']); });
		if(!empty($plaintext_head_closures)
			|| !empty(xn_html_scan_tags('<plaintext><form action="/post"></form>', 'form'))) {
			$errors[] = 'plaintext must consume the remaining document instead of exposing tag-looking text as active markup.';
		}

		$external_base_html = '<html><head><base href="https://cdn.example/theme/">'
			.'<link rel="stylesheet" href="view/css/font-awesome.min.css">'
			.'<link rel="stylesheet" href="view/css/bs4-compat.min.css"></head><body>'
			.'<script src="view/js/bs4-compat.min.js"></script></body></html>';
		$external_base_output = xn_compat_inject_output($external_base_html);
		require_contains($external_base_output, 'href="http://forum.test/view/css/font-awesome.min.css?compat-check"', 'external base output must inject Font Awesome through an explicit current-origin URL');
		require_contains($external_base_output, 'href="http://forum.test/view/css/bs4-compat.css?compat-check"', 'external base output must inject compatibility CSS through an explicit current-origin URL');
		require_contains($external_base_output, 'src="http://forum.test/view/js/bs4-compat.js?compat-check"', 'external base output must inject compatibility JS through an explicit current-origin URL');
		$external_base_second_pass = xn_compat_inject_output($external_base_output);
		if(substr_count($external_base_second_pass, 'src="http://forum.test/view/js/bs4-compat.js?compat-check"') !== 1) {
			$errors[] = 'external base output injection must remain idempotent after its explicit same-origin asset is present.';
		}

		$local_base_html = '<html><head><base href="/theme/assets/"><script src="view/js/bs4-compat.min.js"></script></head><body></body></html>';
		$local_base_output = xn_compat_inject_output($local_base_html);
		require_contains($local_base_output, 'src="http://forum.test/view/js/bs4-compat.js?compat-check"', 'a local base with a different path must not make an ambiguous relative asset suppress the core compatibility script');

		$absolute_local_assets_html = '<html><head><base href="https://cdn.example/theme/">'
			.'<link rel="stylesheet" href="http://forum.test/view/css/font-awesome.min.css">'
			.'<link rel="stylesheet" href="http://forum.test/view/css/bs4-compat.min.css"></head><body>'
			.'<script src="http://forum.test/view/js/bs4-compat.min.js"></script></body></html>';
		$absolute_local_assets_output = xn_compat_inject_output($absolute_local_assets_html);
		if(substr_count($absolute_local_assets_output, 'font-awesome.min.css') !== 1
			|| preg_match_all('~bs4-compat(?:\.min)?\.css~i', $absolute_local_assets_output) !== 1
			|| preg_match_all('~bs4-compat(?:\.min)?\.js~i', $absolute_local_assets_output) !== 1) {
			$errors[] = 'an active base must still recognize explicit absolute current-origin compatibility assets.';
		}

		$_SERVER['conf'] = array('view_url'=>'\\\\cdn.example\\theme\\', 'static_version'=>'?compat-check');
		$backslash_view_output = xn_compat_inject_output('<html><head><base href="https://cdn.example/theme/"></head><body></body></html>');
		if(strpos($backslash_view_output, 'cdn.example\\theme') !== FALSE || strpos($backslash_view_output, 'https://cdn.example/view/') !== FALSE) {
			$errors[] = 'backslash-based view_url values must never become browser-normalized external compatibility asset URLs.';
		}
		require_contains($backslash_view_output, 'src="http://forum.test/view/js/bs4-compat.js?compat-check"', 'invalid backslash view_url must fall back to an explicit current-origin core asset under an active base');

		$_SERVER['conf'] = array('view_url'=>'view/', 'static_version'=>'?compat-check');
		$_SERVER['SCRIPT_NAME'] = '/forum/admin/index.php';
		$subdirectory_admin_base_output = xn_compat_inject_output('<html><head><base href="https://cdn.example/theme/"></head><body></body></html>');
		require_contains($subdirectory_admin_base_output, 'src="http://forum.test/forum/view/js/bs4-compat.js?compat-check"', 'active-base admin output must resolve the core asset against the application path, not the theme base');

		$_SERVER['SCRIPT_NAME'] = '/admin/index.php';
		$admin_output = xn_compat_inject_output('<html><head></head><body></body></html>');
		require_contains($admin_output, 'href="../view/css/font-awesome.min.css?compat-check"', 'admin output must inject Font Awesome from the correct relative path');

		$json = '{"code":0,"message":"ok"}';
		if(xn_compat_inject_output($json) !== $json) {
			$errors[] = 'compatibility output injector must leave non-HTML responses unchanged.';
		}
		$json_with_head = '{"code":0,"message":"</head>"}';
		if(xn_compat_inject_output($json_with_head) !== $json_with_head) {
			$errors[] = 'compatibility output injector must not treat a JSON string containing </head> as an HTML document.';
		}
	}
}

if(!empty($errors)) {
	fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
	exit(1);
}

echo "OK: BS4 compatibility layer checks passed\n";
