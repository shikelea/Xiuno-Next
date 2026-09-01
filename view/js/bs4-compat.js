/**
 * Bootstrap 4 -> Bootstrap 5 compatibility runtime.
 *
 * The runtime is deliberately package-agnostic: it repairs documented BS4/Xiuno
 * contracts without naming or rewriting any third-party plugin or theme.
 */
(function (window, document) {
    'use strict';

    var existingRuntime = window.XiunoCompat;
    if (existingRuntime && existingRuntime._runtimeId === 'xiuno-bs4-compat-v2' && typeof existingRuntime.refresh === 'function') {
        // Source/minified duplicates and an explicit second load are refresh signals,
        // not reasons to add another observer or another set of global listeners.
        existingRuntime.refresh(document);
        return;
    }

    var runtime = existingRuntime && typeof existingRuntime === 'object' ? existingRuntime : {};
    runtime._runtimeId = 'xiuno-bs4-compat-v2';
    runtime.version = '2.0.0';
    runtime.jquery = null;
	runtime._jqueryStateMap = typeof WeakMap !== 'undefined' ? new WeakMap() : null;
	runtime._jqueryStates = [];
    runtime._formSubmissionStates = typeof WeakMap !== 'undefined' ? new WeakMap() : null;
    runtime._legacyQuickReplyPending = null;
    runtime._legacyQuickReplyReloadTimer = null;
    runtime._pendingRoots = [];
    runtime._refreshTimer = null;
    window.XiunoCompat = runtime;
    window.__xnBs4CompatLoaded = true;

    // BS4 -> BS5 attribute mapping. data-toggle="dropdown" is handled through
    // a structure-aware branch below so non-Bootstrap theme menus stay untouched.
    var attrMap = {
        'data-toggle':    'data-bs-toggle',
        'data-dismiss':   'data-bs-dismiss',
        'data-target':    'data-bs-target',
        'data-parent':    'data-bs-parent',
        'data-ride':      'data-bs-ride',
        'data-slide':     'data-bs-slide',
        'data-slide-to':  'data-bs-slide-to',
        'data-offset':    'data-bs-offset',
        'data-spy':       'data-bs-spy',
        'data-interval':  'data-bs-interval',
        'data-backdrop':  'data-bs-backdrop',
        'data-keyboard':  'data-bs-keyboard',
        'data-focus':     'data-bs-focus',
        'data-placement': 'data-bs-placement',
        'data-trigger':   'data-bs-trigger',
		'data-container': 'data-bs-container',
		'data-autohide':  'data-bs-autohide',
		'data-html':      'data-bs-html',
		'data-delay':     'data-bs-delay',
		'data-animation': 'data-bs-animation',
		'data-template':  'data-bs-template',
		'data-boundary':  'data-bs-boundary',
		'data-display':   'data-bs-display',
		'data-reference': 'data-bs-reference',
		'data-wrap':      'data-bs-wrap',
		'data-pause':     'data-bs-pause',
		'data-touch':     'data-bs-touch'
    };
    var legacyAttributes = Object.keys(attrMap);
    var modernAttributes = legacyAttributes.map(function (attribute) { return attrMap[attribute]; });
    var ownershipAttributes = modernAttributes.map(function (attribute) { return automaticMarker(attribute); });
    var attrSelector = legacyAttributes.concat(ownershipAttributes).map(function (attribute) {
        return '[' + attribute + ']';
    }).join(',');
    var observedAttributes = legacyAttributes.concat(modernAttributes, ['data-content', 'data-bs-content', 'href', 'action', 'method', 'name', 'type', 'class']);
    var legacyIconSelector = '[class^="icon-"],[class*=" icon-"]';
    var modernIconClasses = ['fa', 'fas', 'far', 'fab', 'fal', 'fad', 'la', 'las', 'lar', 'lab', 'lal', 'lad'];
    var componentNames = ['alert', 'button', 'carousel', 'collapse', 'dropdown', 'modal', 'offcanvas', 'popover', 'scrollspy', 'tab', 'toast', 'tooltip'];
	var componentConstructorNames = { scrollspy: 'ScrollSpy' };

    function eachRootAndDescendant(root, selector, callback) {
        root = root || document;
        if (!root) return;
        if (root.nodeType === 1 && typeof root.matches === 'function' && root.matches(selector)) callback(root);
        if (typeof root.querySelectorAll !== 'function') return;
        var descendants = root.querySelectorAll(selector);
        for (var i = 0; i < descendants.length; i++) callback(descendants[i]);
    }
    runtime.eachRootAndDescendant = eachRootAndDescendant;

    function closestElement(target, selector) {
        if (!target) return null;
        if (target.nodeType !== 1) target = target.parentElement;
        return target && typeof target.closest === 'function' ? target.closest(selector) : null;
    }

    function automaticMarker(modernAttribute) {
        return modernAttribute.replace(/^data-bs-/, 'data-xn-bs-auto-');
    }

    function syncAutomaticAttribute(element, legacyAttribute, modernAttribute, allowed) {
        var marker = automaticMarker(modernAttribute);
        var markerOwned = element.hasAttribute(marker);
        var previousValue = markerOwned ? element.getAttribute(marker) : null;
        var modernValue = element.getAttribute(modernAttribute);

        // A caller changed the modern attribute after we wrote it. Relinquish
        // ownership and never overwrite or later remove that caller-owned value.
        if (markerOwned && modernValue !== previousValue) {
            element.removeAttribute(marker);
            markerOwned = false;
        }

        if (!element.hasAttribute(legacyAttribute) || allowed === false) {
            if (markerOwned) {
                if (element.getAttribute(modernAttribute) === previousValue) element.removeAttribute(modernAttribute);
                element.removeAttribute(marker);
            }
            return;
        }

        var legacyValue = element.getAttribute(legacyAttribute);
        if (markerOwned) {
			if (modernValue !== legacyValue) element.setAttribute(modernAttribute, legacyValue);
			if (previousValue !== legacyValue) element.setAttribute(marker, legacyValue);
        } else if (!element.hasAttribute(modernAttribute)) {
            element.setAttribute(modernAttribute, legacyValue);
            element.setAttribute(marker, legacyValue);
        }
    }

    function isDropdownMenu(element) {
        return !!(element && element.nodeType === 1 && element.classList.contains('dropdown-menu'));
    }

    function dropdownMenuFor(toggle) {
        if (!toggle || toggle.nodeType !== 1) return null;
        if (isDropdownMenu(toggle.nextElementSibling)) return toggle.nextElementSibling;
        if (isDropdownMenu(toggle.previousElementSibling)) return toggle.previousElementSibling;

        var container = typeof toggle.closest === 'function'
            ? toggle.closest('.dropdown, .dropup, .dropend, .dropstart, .btn-group')
            : null;
        if (container && typeof container.querySelector === 'function') {
            var containerMenu = container.querySelector('.dropdown-menu');
            if (containerMenu) return containerMenu;
        }

        var parent = toggle.parentElement;
        return parent && typeof parent.querySelector === 'function' ? parent.querySelector('.dropdown-menu') : null;
    }

    function hasBootstrapDropdownStructure(toggle) {
        return !!dropdownMenuFor(toggle);
    }

    function convertLegacyIcon(element) {
        if (!element || !element.classList) return;
        for (var i = 0; i < modernIconClasses.length; i++) {
            if (element.classList.contains(modernIconClasses[i])) return;
        }
        var classes = (element.getAttribute('class') || '').split(/\s+/);
        var converted = false;
        for (var ci = 0; ci < classes.length; ci++) {
            if (!/^icon-[a-z0-9][a-z0-9-]*$/i.test(classes[ci])) continue;
            element.classList.add('fa-' + classes[ci].slice(5));
            converted = true;
        }
        if (converted) element.classList.add('fa');
    }

    function convertLegacyIcons(root) {
        eachRootAndDescendant(root, legacyIconSelector, convertLegacyIcon);
    }

    function convertElementAttributes(element) {
        for (var legacyAttribute in attrMap) {
            if (!Object.prototype.hasOwnProperty.call(attrMap, legacyAttribute)) continue;
            var modernAttribute = attrMap[legacyAttribute];
            var allowed = true;
            if (legacyAttribute === 'data-toggle' && element.hasAttribute(legacyAttribute)) {
                var toggleType = (element.getAttribute(legacyAttribute) || '').toLowerCase();
                if (toggleType === 'dropdown') allowed = hasBootstrapDropdownStructure(element);
            }
            syncAutomaticAttribute(element, legacyAttribute, modernAttribute, allowed);
        }
    }

    function convertAttributes(root) {
        eachRootAndDescendant(root, attrSelector, convertElementAttributes);
		eachRootAndDescendant(root, '[data-content],[' + automaticMarker('data-bs-content') + ']', function (popover) {
			var toggle = (popover.getAttribute('data-bs-toggle') || popover.getAttribute('data-toggle') || '').toLowerCase();
			syncAutomaticAttribute(popover, 'data-content', 'data-bs-content', toggle === 'popover');
		});
        convertLegacyIcons(root);
    }

    function requestUrl(input) {
        if (typeof input === 'string') return input;
        if (input && typeof input.href === 'string') return input.href;
        if (input && typeof input.url === 'string') return input.url;
        return null;
    }

    function isSameOrigin(input) {
        try {
            var target = requestUrl(input);
            return target !== null && new URL(target, document.baseURI || window.location.href).origin === window.location.origin;
        } catch (error) {
            return false;
        }
    }

    function csrfToken() {
        var token = window.csrf_token;
        if (!token) {
            var meta = document.querySelector('meta[name="csrf-token"]');
            if (meta) token = meta.getAttribute('content');
        }
        return token || '';
    }

    function setupCsrfToken() {
        if (typeof window.csrf_token === 'undefined' || !window.csrf_token) {
            var token = csrfToken();
            if (token) window.csrf_token = token;
        }
    }

	function bodyHasType(body, constructorName, tagName) {
		if (!body) return false;
		var Constructor = window[constructorName];
		if (typeof Constructor === 'function' && body instanceof Constructor) return true;
		return Object.prototype.toString.call(body) === '[object ' + tagName + ']';
	}

	function cloneFetchBodyWithoutLocalCsrf(body, headers, token) {
		var localToken = String(token || '');
		if (!body || !localToken) return { changed: false, body: body };

		if (bodyHasType(body, 'FormData', 'FormData')) {
			if (typeof body.forEach !== 'function' || typeof window.FormData !== 'function') throw new TypeError('Unable to inspect cross-origin FormData safely.');
			var nextFormData = new window.FormData();
			var formChanged = false;
			body.forEach(function (value, name) {
				// File values are package/user data, even when a legacy form happens to call
				// the field `_token`; only the exact scalar session token is compatibility-owned.
				if (name === '_token' && typeof value === 'string' && value === localToken) {
					formChanged = true;
					return;
				}
				nextFormData.append(name, value);
			});
			return { changed: formChanged, body: formChanged ? nextFormData : body };
		}

		var parameters = null;
		var stringBody = false;
		if (bodyHasType(body, 'URLSearchParams', 'URLSearchParams')) {
			if (typeof body.forEach !== 'function' || typeof window.URLSearchParams !== 'function') throw new TypeError('Unable to inspect cross-origin URLSearchParams safely.');
			parameters = body;
		} else if (typeof body === 'string') {
			var contentType = headers && typeof headers.get === 'function' ? (headers.get('Content-Type') || '') : '';
			var mediaType = String(contentType).split(';', 1)[0].replace(/^\s+|\s+$/g, '').toLowerCase();
			if (mediaType !== 'application/x-www-form-urlencoded' || typeof window.URLSearchParams !== 'function') return { changed: false, body: body };
			parameters = new window.URLSearchParams(body);
			stringBody = true;
		}
		if (!parameters) return { changed: false, body: body };

		var nextParameters = new window.URLSearchParams();
		var parametersChanged = false;
		parameters.forEach(function (value, name) {
			if (name === '_token' && String(value) === localToken) {
				parametersChanged = true;
				return;
			}
			nextParameters.append(name, value);
		});
		if (!parametersChanged) return { changed: false, body: body };
		return { changed: true, body: stringBody ? nextParameters.toString() : nextParameters };
	}

    function installFetchCsrf() {
        if (typeof window.fetch !== 'function' || window.fetch === runtime._fetchWrapper) return;
        var originalFetch = window.fetch;
        var wrapper = function (input, init) {
            var method = '';
            if (init && init.method) method = String(init.method).toUpperCase();
            else if (input && typeof input === 'object' && input.method) method = String(input.method).toUpperCase();
            var token = csrfToken();
			var sameOrigin = isSameOrigin(input);
			var shouldInject = token && method === 'POST' && sameOrigin;
			var headerSource = init && init.headers !== undefined
				? init.headers
				: (input && typeof input === 'object' && input.headers ? input.headers : undefined);
			var headers;
			try {
				headers = new Headers(headerSource);
			} catch (error) {
				// Native fetch returns a rejected promise for an invalid HeadersInit.
				// Never replace the caller's invalid headers with an empty set and
				// accidentally transmit a request with different semantics.
				return window.Promise.reject(error);
			}
			var currentHeader = headers.get('X-CSRF-TOKEN');
			var shouldStrip = token && !sameOrigin && currentHeader === token;
			var bodyResult = { changed: false, body: null };
			try {
				if (token && !sameOrigin && init && Object.prototype.hasOwnProperty.call(init, 'body')) {
					bodyResult = cloneFetchBodyWithoutLocalCsrf(init.body, headers, token);
				}
			} catch (error) {
				// A recognized but unreadable body must fail closed instead of transmitting
				// the current session token. This matches fetch's rejected-promise surface.
				return window.Promise.reject(error);
			}
			if (shouldInject || shouldStrip || bodyResult.changed) {
				var nextInit = {};
				if (init) {
					for (var key in init) {
						if (Object.prototype.hasOwnProperty.call(init, key)) nextInit[key] = init[key];
					}
				}
				if (shouldInject) headers.set('X-CSRF-TOKEN', token);
				else if (shouldStrip) headers.delete('X-CSRF-TOKEN');
				nextInit.headers = headers;
				if (bodyResult.changed) nextInit.body = bodyResult.body;
				init = nextInit;
            }
            return originalFetch.call(this, input, init);
        };
        wrapper._xnCsrf = true;
        wrapper._xnOriginal = originalFetch;
        runtime._fetchOriginal = originalFetch;
        runtime._fetchWrapper = wrapper;
        window.fetch = wrapper;
        window._csrf_fetch_setup_done = true;
    }

    function formSubmissionDetails(form, submitter) {
        var validSubmitter = !!(submitter && submitter.nodeType === 1 && submitter.form === form);
        var method = validSubmitter && submitter.hasAttribute('formmethod') ? submitter.formMethod : form.method;
        var action = validSubmitter && submitter.hasAttribute('formaction') ? submitter.formAction : form.action;
        var isPost = String(method || 'get').toUpperCase() === 'POST';
        var sameOrigin = isSameOrigin(action || window.location.href);
        return { action: action || window.location.href, isPost: isPost, sameOrigin: sameOrigin, localPost: isPost && sameOrigin };
    }

    function rememberFormSubmission(form, submitter) {
        var state = { submitter: submitter && submitter.form === form ? submitter : null };
        if (runtime._formSubmissionStates) runtime._formSubmissionStates.set(form, state);
        else form._xnCsrfSubmissionState = state;
        window.setTimeout(function () {
            var current = runtime._formSubmissionStates ? runtime._formSubmissionStates.get(form) : form._xnCsrfSubmissionState;
            if (current !== state) return;
            if (runtime._formSubmissionStates) runtime._formSubmissionStates.delete(form);
            else delete form._xnCsrfSubmissionState;
            // A same-origin submitter may temporarily add a token to an otherwise cross-origin
            // form. Restore the form's default contract after the submission event finishes.
            reconcileCsrfForm(form, csrfToken(), null);
        }, 0);
    }

    function rememberedFormSubmitter(form) {
        var state = runtime._formSubmissionStates ? runtime._formSubmissionStates.get(form) : form._xnCsrfSubmissionState;
        return state ? state.submitter : null;
    }

    function csrfInputsForForm(form) {
		var controls = form && form.elements ? form.elements : [];
		var inputs = [];
		for (var i = 0; i < controls.length; i++) {
			if (controls[i] && controls[i].tagName === 'INPUT' && controls[i].name === '_token') inputs.push(controls[i]);
		}
		return inputs;
	}

    function removeLocalCsrf(form, token) {
		var inputs = csrfInputsForForm(form);
		var removedValues = [];
		for (var i = inputs.length - 1; i >= 0; i--) {
			if (inputs[i].getAttribute('data-xn-csrf-auto') === '1' || (token && inputs[i].value === token)) {
				removedValues.push(inputs[i].value);
				if (inputs[i].parentNode) inputs[i].parentNode.removeChild(inputs[i]);
			}
		}
		return removedValues;
	}

	function stripLocalCsrfFormData(formData, token, ownedValues) {
		if (!formData || typeof formData.getAll !== 'function' || typeof formData.delete !== 'function') return;
		var values = formData.getAll('_token');
		if (!values.length) return;
		formData.delete('_token');
		for (var i = 0; i < values.length; i++) {
			var value = values[i];
			var owned = token && String(value) === String(token);
			if (!owned && ownedValues) {
				for (var j = 0; j < ownedValues.length; j++) {
					if (String(value) === String(ownedValues[j])) {
						owned = true;
						break;
					}
				}
			}
			if (!owned) formData.append('_token', value);
		}
    }

    function reconcileCsrfForm(form, token, submitter) {
        if (!form || form.tagName !== 'FORM') return;
        var submission = formSubmissionDetails(form, submitter);
        if (!submission.localPost) {
            removeLocalCsrf(form, token);
            return;
        }
		if (!token) return;
		var inputs = csrfInputsForForm(form);
		if (inputs.length) {
			inputs[0].value = token;
			for (var i = inputs.length - 1; i > 0; i--) {
				if (inputs[i].parentNode) inputs[i].parentNode.removeChild(inputs[i]);
			}
			return;
		}
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = '_token';
        input.value = token;
        input.setAttribute('data-xn-csrf-auto', '1');
        form.appendChild(input);
    }

    function reconcileCsrfForms(root) {
        var token = csrfToken();
        if (!token) return;
        eachRootAndDescendant(root, 'form', function (form) { reconcileCsrfForm(form, token, null); });
    }

	function activeNamedFormValues(form, name) {
		var values = [];
		var controls = form && form.elements ? form.elements : [];
		for (var i = 0; i < controls.length; i++) {
			var control = controls[i];
			if (!control || control.disabled || String(control.name || '') !== name) continue;
			values.push(String(control.value == null ? '' : control.value));
		}
		return values;
	}

	function normalizedSameOriginRequestUrl(input) {
		try {
			var resolved = new URL(input || window.location.href, document.baseURI || window.location.href);
			if (resolved.origin !== window.location.origin) return '';
			resolved.hash = '';
			return resolved.href;
		} catch (error) {
			return '';
		}
	}

	function isCoreQuickReplyAction(action) {
		var resolved;
		try {
			resolved = new URL(action || window.location.href, document.baseURI || window.location.href);
		} catch (error) {
			return false;
		}
		if (resolved.origin !== window.location.origin) return false;
		var queryRoute = resolved.search ? resolved.search.slice(1).split('&')[0] : '';
		if (queryRoute && queryRoute.indexOf('=') === -1) {
			try { queryRoute = decodeURIComponent(queryRoute); } catch (error2) { return false; }
			queryRoute = queryRoute.replace(/^\/+|\/+$/g, '').toLowerCase();
			if (/^post-create-[1-9][0-9]*-1(?:\.htm)?$/.test(queryRoute)
				|| /^post\/create\/[1-9][0-9]*\/1$/.test(queryRoute)) return true;
		}
		var pathname = resolved.pathname || '';
		try { pathname = decodeURIComponent(pathname); } catch (error3) { return false; }
		var segments = pathname.toLowerCase().split('/').filter(function (segment) { return segment !== ''; });
		if (!segments.length) return false;
		var last = segments[segments.length - 1];
		if (/^post-create-[1-9][0-9]*-1(?:\.htm)?$/.test(last)) return true;
		return segments.length >= 4
			&& segments[segments.length - 4] === 'post'
			&& segments[segments.length - 3] === 'create'
			&& /^[1-9][0-9]*$/.test(segments[segments.length - 2])
			&& segments[segments.length - 1] === '1';
	}

	function legacyQuickReplySubmission(form, submitter) {
		if (!form || form.id !== 'quick_reply_form' || form.ownerDocument !== document) return null;
		if (document.querySelectorAll('form#quick_reply_form').length !== 1) return null;
		if (document.querySelector('[data-xn-thread-post-count]')) return null;
		var submission = formSubmissionDetails(form, submitter);
		if (!submission.localPost || !isCoreQuickReplyAction(submission.action)) return null;
		var messageValues = activeNamedFormValues(form, 'message');
		var returnHtmlValues = activeNamedFormValues(form, 'return_html');
		if (messageValues.length !== 1 || returnHtmlValues.length !== 1 || returnHtmlValues[0] !== '1') return null;
		var action = normalizedSameOriginRequestUrl(submission.action);
		return action ? { action: action, form: form, request: null, startedAt: Date.now() } : null;
	}

	function rememberLegacyQuickReplySubmission(form, submitter) {
		runtime._legacyQuickReplyPending = legacyQuickReplySubmission(form, submitter);
	}

	function quickReplyAjaxDataMatches(data) {
		if (typeof data !== 'string' || typeof window.URLSearchParams !== 'function') return false;
		var parameters;
		try { parameters = new window.URLSearchParams(data); } catch (error) { return false; }
		var messageValues = parameters.getAll('message');
		var returnHtmlValues = parameters.getAll('return_html');
		return messageValues.length === 1
			&& returnHtmlValues.length === 1 && returnHtmlValues[0] === '1';
	}

	function pendingQuickReplyMatches(settings) {
		var pending = runtime._legacyQuickReplyPending;
		if (!pending) return false;
		if (Date.now() - pending.startedAt > 120000 || !document.documentElement.contains(pending.form)) {
			runtime._legacyQuickReplyPending = null;
			return false;
		}
		var method = settings && (settings.type || settings.method) ? String(settings.type || settings.method).toUpperCase() : 'GET';
		return method === 'POST'
			&& normalizedSameOriginRequestUrl(settings && settings.url) === pending.action
			&& quickReplyAjaxDataMatches(settings && settings.data);
	}

	function successfulQuickReplyResponse(xhr, data) {
		var response = data;
		if (typeof response !== 'string' && (!response || typeof response !== 'object')) response = xhr && xhr.responseText;
		if (typeof response === 'string') {
			try { response = JSON.parse(response); } catch (error) { return false; }
		}
		return !!(response && typeof response === 'object' && (response.code === 0 || response.code === '0'));
	}

	function scheduleLegacyQuickReplyReload() {
		if (runtime._legacyQuickReplyReloadTimer !== null) return;
		runtime._legacyQuickReplyReloadTimer = window.setTimeout(function () {
			runtime._legacyQuickReplyReloadTimer = null;
			window.location.reload();
		}, 0);
	}

	function installLegacyQuickReplyReload(jq, state) {
		if (state.quickReplyReloadBound || typeof jq.fn.on !== 'function') return;
		state.quickReplyReloadBound = true;
		var jdocument = jq(document);
		jdocument.on('ajaxSend.xnLegacyQuickReplyReload', function (event, xhr, settings) {
			if (!pendingQuickReplyMatches(settings)) return;
			var pending = runtime._legacyQuickReplyPending;
			if (pending && !pending.request) pending.request = xhr;
		});
		jdocument.on('ajaxSuccess.xnLegacyQuickReplyReload', function (event, xhr, settings, data) {
			var pending = runtime._legacyQuickReplyPending;
			if (!pending || pending.request !== xhr) return;
			var successful = pendingQuickReplyMatches(settings) && successfulQuickReplyResponse(xhr, data);
			runtime._legacyQuickReplyPending = null;
			if (successful) scheduleLegacyQuickReplyReload();
		});
		jdocument.on('ajaxError.xnLegacyQuickReplyReload ajaxComplete.xnLegacyQuickReplyReload', function (event, xhr, settings) {
			var pending = runtime._legacyQuickReplyPending;
			if (pending && pending.request === xhr) runtime._legacyQuickReplyPending = null;
		});
	}

    var pluginActionRe = /(?:^|[?&\/-])plugin-(download|install|enable|disable|unstall|upgrade)-/;
    var pluginClassRe = /\b(download|install|enable|disable|unstall|upgrade)\b/;

    function isSafePluginHref(href) {
        var url;
        try { url = new URL(href, window.location.href); } catch (error) { return false; }
        return url.protocol === window.location.protocol && url.host === window.location.host;
    }

    function isPluginWriteLink(link) {
        var href = link.getAttribute('href') || link.getAttribute('data-href') || '';
        if (!href || href.toLowerCase().indexOf('javascript:') === 0 || !isSafePluginHref(href)) return false;
        if (!pluginActionRe.test(href)) return false;
        var inPluginArea = typeof link.closest === 'function' && link.closest('.plugin, table, .card, .media, .list-group');
        return pluginClassRe.test(link.className || '') || !!inPluginArea;
    }

    function fixPluginPostLinks(root) {
        var selector = 'a.download,a.install,a.enable,a.disable,a.unstall,a.upgrade,' +
            'a[href*="plugin-download-"],a[href*="plugin-install-"],a[href*="plugin-enable-"],' +
            'a[href*="plugin-disable-"],a[href*="plugin-unstall-"],a[href*="plugin-upgrade-"]';
        eachRootAndDescendant(root, selector, function (link) {
            if (!link.hasAttribute('data-method') && isPluginWriteLink(link)) link.setAttribute('data-method', 'post');
        });
    }

    function handleBrokenImg(image) {
        if (image.getAttribute('data-bs4c-handled')) return;
        image.setAttribute('data-bs4c-handled', '1');
        image.classList.add('bs4c-img-fallback');
    }

	function resetBrokenImg(image) {
		if (!image || image.tagName !== 'IMG' || !image.hasAttribute('data-bs4c-handled')) return;
		image.removeAttribute('data-bs4c-handled');
		image.classList.remove('bs4c-img-fallback');
	}

    function getJQueryState(jq) {
		if (runtime._jqueryStateMap) {
			var mappedState = runtime._jqueryStateMap.get(jq);
			if (mappedState) return mappedState;
			mappedState = { jquery: jq, originals: {}, originalDescriptors: {}, proxies: {}, disabled: {}, csrfHook: null, csrfPrefilter: null };
			runtime._jqueryStateMap.set(jq, mappedState);
			return mappedState;
		}
        for (var i = 0; i < runtime._jqueryStates.length; i++) {
            if (runtime._jqueryStates[i].jquery === jq) return runtime._jqueryStates[i];
        }
        var state = { jquery: jq, originals: {}, originalDescriptors: {}, proxies: {}, disabled: {}, csrfHook: null, csrfPrefilter: null };
        runtime._jqueryStates.push(state);
        return state;
    }

    function captureCurrentImplementations(jq, state) {
        for (var i = 0; i < componentNames.length; i++) {
            var name = componentNames[i];
            if (state.disabled[name] || (state.proxies[name] && jq.fn[name] === state.proxies[name])) continue;
            state.originals[name] = jq.fn[name];
            state.originalDescriptors[name] = Object.getOwnPropertyDescriptor(jq.fn, name) || null;
        }
    }

    function restoreOriginal(jq, state, name) {
        var descriptor = state.originalDescriptors[name];
        if (descriptor) {
            try { Object.defineProperty(jq.fn, name, descriptor); return; } catch (error) {}
        }
        if (typeof state.originals[name] === 'undefined') {
            try { delete jq.fn[name]; } catch (error2) { jq.fn[name] = undefined; }
        } else jq.fn[name] = state.originals[name];
    }

    function componentConstructor(name) {
        if (!window.bootstrap) return null;
        var constructorName = componentConstructorNames[name] || (name.charAt(0).toUpperCase() + name.slice(1));
        return window.bootstrap[constructorName] || null;
    }

	function normalizeComponentCollection(collection) {
		if (!collection || typeof collection.each !== 'function') return;
		collection.each(function () { convertAttributes(this); });
	}

    function getOrCreateComponent(Component, element, options) {
        if (typeof Component.getOrCreateInstance === 'function') return Component.getOrCreateInstance(element, options || {});
        if (typeof Component.getInstance === 'function') {
            var existing = Component.getInstance(element);
            if (existing) return existing;
        }
        return new Component(element, options || {});
    }

    function delegateOriginal(jq, state, name, collection, args) {
        var original = state.originals[name];
        if (typeof original === 'function' && original !== state.proxies[name]) return original.apply(collection, args);
        var action = args.length ? args[0] : undefined;
        throw new TypeError('XiunoCompat ' + name + ': unsupported action "' + action + '"');
    }

    function decorateProxy(jq, state, name, proxy) {
        var original = state.originals[name];
        var Component = componentConstructor(name);
        proxy.Constructor = original && original.Constructor ? original.Constructor : Component;
        proxy.noConflict = function () {
            state.disabled[name] = true;
            restoreOriginal(jq, state, name);
            return proxy;
        };
        proxy._xnPatched = true;
        proxy._xnCompatRuntime = runtime._runtimeId;
        state.proxies[name] = proxy;
        jq.fn[name] = proxy;
    }

    function installModalProxy(jq, state) {
        if (state.disabled.modal || (state.proxies.modal && jq.fn.modal === state.proxies.modal)) return;
        var proxy = function (action) {
			normalizeComponentCollection(this);
            var Component = componentConstructor('modal');
            if (!Component) {
                if (typeof state.originals.modal === 'function') return state.originals.modal.apply(this, arguments);
                return this;
            }
            if (typeof action === 'string') {
                if (['show', 'hide', 'toggle', 'handleUpdate', 'dispose'].indexOf(action) === -1) return delegateOriginal(jq, state, 'modal', this, arguments);
                return this.each(function () {
                    var instance = getOrCreateComponent(Component, this, {});
                    if (typeof instance[action] === 'function') instance[action]();
                });
            }
            if (typeof action === 'undefined' || (action && typeof action === 'object')) {
                var options = {};
                if (action && typeof action === 'object') {
                    for (var key in action) {
                        if (Object.prototype.hasOwnProperty.call(action, key) && key !== 'show') options[key] = action[key];
                    }
                }
                return this.each(function () {
                    var instance = getOrCreateComponent(Component, this, options);
                    if (typeof action === 'undefined' || action.show !== false) instance.show();
                });
            }
            return delegateOriginal(jq, state, 'modal', this, arguments);
        };
        decorateProxy(jq, state, 'modal', proxy);
    }

    function installSimpleComponentProxy(jq, state, name, actions) {
        if (state.disabled[name] || (state.proxies[name] && jq.fn[name] === state.proxies[name])) return;
        var proxy = function (action) {
			normalizeComponentCollection(this);
            var Component = componentConstructor(name);
            if (!Component) {
                if (typeof state.originals[name] === 'function') return state.originals[name].apply(this, arguments);
                return this;
            }
            if (typeof action === 'undefined' || (action && typeof action === 'object')) {
                var options = action && typeof action === 'object' ? action : {};
                return this.each(function () { getOrCreateComponent(Component, this, options); });
            }
			if (name === 'carousel' && typeof action === 'number') {
				return this.each(function () {
					var instance = getOrCreateComponent(Component, this, {});
					if (typeof instance.to === 'function') instance.to(action);
				});
			}
            if (typeof action === 'string' && actions.indexOf(action) !== -1) {
                return this.each(function () {
                    // getOrCreate is intentional for show: legacy code commonly calls
                    // .tooltip('show')/.popover('show') without an explicit init step.
                    var instance = getOrCreateComponent(Component, this, {});
                    if (typeof instance[action] === 'function') instance[action]();
                });
            }
            return delegateOriginal(jq, state, name, this, arguments);
        };
        decorateProxy(jq, state, name, proxy);
    }

    function installDropdownProxy(jq, state) {
        installSimpleComponentProxy(jq, state, 'dropdown', ['show', 'hide', 'toggle', 'update', 'dispose']);
    }

    function installTabProxy(jq, state) {
        installSimpleComponentProxy(jq, state, 'tab', ['show', 'dispose']);
    }

	function installCarouselProxy(jq, state) {
		installSimpleComponentProxy(jq, state, 'carousel', ['next', 'nextWhenVisible', 'prev', 'pause', 'cycle', 'dispose']);
	}

	function installCollapseProxy(jq, state) {
		installSimpleComponentProxy(jq, state, 'collapse', ['show', 'hide', 'toggle', 'dispose']);
	}

	function installOffcanvasProxy(jq, state) {
		installSimpleComponentProxy(jq, state, 'offcanvas', ['show', 'hide', 'toggle', 'dispose']);
	}

	function installScrollSpyProxy(jq, state) {
		installSimpleComponentProxy(jq, state, 'scrollspy', ['refresh', 'dispose']);
	}

	function installToastProxy(jq, state) {
		installSimpleComponentProxy(jq, state, 'toast', ['show', 'hide', 'dispose', 'isShown']);
	}

	function compatButtonContent(jthis, element, value) {
		var usesValue = element.tagName === 'INPUT';
		if (arguments.length < 3) return usesValue ? jthis.val() : jthis.html();
		if (usesValue) jthis.val(value);
		else jthis.html(value);
	}

	function compatButtonApply(jq, element, action) {
		if (typeof window.xn_button_apply === 'function') return window.xn_button_apply(jq, element, action);
		if (!element || action === 'toggle' || typeof action === 'undefined') return false;
		var jthis = jq(element);
		var state = jthis.data('xn-button-state');
		if ((action === 'loading' || (typeof action === 'string' && ['reset', 'enable', 'disable', 'disabled'].indexOf(action) === -1)) && typeof state === 'undefined') {
			state = {
				content: compatButtonContent(jthis, element),
				disabled: !!element.disabled,
				disabledClass: jthis.hasClass('disabled'),
				hadAriaDisabled: element.hasAttribute('aria-disabled'),
				ariaDisabled: element.getAttribute('aria-disabled') || ''
			};
			jthis.data('xn-button-state', state);
		}
		if (action === 'loading') {
			var loadingText = jthis.attr('data-loading-text') || jthis.attr('loading-text') || jthis.data('loading-text');
			jthis.prop('disabled', true).addClass('disabled').attr('aria-disabled', 'true');
			if (typeof loadingText !== 'undefined' && loadingText !== '') compatButtonContent(jthis, element, loadingText);
		} else if (action === 'reset') {
			if (typeof state === 'undefined') return true;
			compatButtonContent(jthis, element, state.content);
			jthis.prop('disabled', !!state.disabled).toggleClass('disabled', !!state.disabledClass);
			if (state.hadAriaDisabled) jthis.attr('aria-disabled', state.ariaDisabled);
			else jthis.removeAttr('aria-disabled');
			jthis.removeData('xn-button-state');
		} else if (action === 'disabled' || action === 'disable') {
			jthis.prop('disabled', true).addClass('disabled').attr('aria-disabled', 'true');
		} else if (action === 'enable') {
			jthis.prop('disabled', false).removeClass('disabled').removeAttr('aria-disabled');
		} else {
			compatButtonContent(jthis, element, action);
		}
		return true;
	}

    function installButtonProxy(jq, state) {
        if (state.disabled.button || (state.proxies.button && jq.fn.button === state.proxies.button)) return;
        var proxy = function (action) {
			normalizeComponentCollection(this);
            var Component = componentConstructor('button');
            return this.each(function () {
                var element = this;
                var $element = jq(element);
				$element.queue(function (next) {
					if (!compatButtonApply(jq, element, action) && action === 'toggle') {
						if (Component) getOrCreateComponent(Component, element, {}).toggle();
						else $element.toggleClass('active').attr('aria-pressed', $element.hasClass('active'));
					}
					next();
				});
            });
        };
        decorateProxy(jq, state, 'button', proxy);
    }

    function showFieldAlert(jq, element, message) {
		if (typeof window.xn_field_alert_show === 'function') {
			window.xn_field_alert_show(jq, element, message);
			return;
		}
        var jthis = jq(element);
        jthis.addClass('is-invalid').attr('aria-invalid', 'true');
        if (jthis.data('xn-alert-original-aria-describedby') === undefined) jthis.data('xn-alert-original-aria-describedby', jthis.attr('aria-describedby') || '');
        if (jthis.data('xn-alert-title-saved') !== true) {
            jthis.data('xn-alert-title-saved', true);
            jthis.data('xn-alert-had-title', element.hasAttribute('title'));
            jthis.data('xn-alert-original-title', element.getAttribute('title') || '');
        }
        var jfeedback = jthis.siblings('.invalid-feedback').first();
        if (!jfeedback.length) jfeedback = jthis.closest('.input-group, .mb-3, .form-group, .form-floating').find('.invalid-feedback').first();
        if (!jfeedback.length) jfeedback = jq('<div class="invalid-feedback"></div>').insertAfter(jthis);
        jfeedback.text(message).addClass('d-block');
        if (!jfeedback.attr('id')) jfeedback.attr('id', 'invalid-feedback-' + Math.random().toString(36).slice(2));
        jthis.data('xn-alert-feedback', jfeedback);
        jthis.attr('title', message);

        try {
            var Tooltip = componentConstructor('tooltip');
            var tooltip = Tooltip && Tooltip.getInstance ? Tooltip.getInstance(jthis[0]) : null;
            if (tooltip) {
                if (typeof tooltip.setContent === 'function') tooltip.setContent({ '.tooltip-inner': message });
                tooltip.show();
            } else if (Tooltip) new Tooltip(jthis[0], { title: message, trigger: 'manual', placement: 'top' }).show();
        } catch (error) {
            try { jthis.tooltip({ title: message, trigger: 'manual', placement: 'top' }).tooltip('show'); } catch (ignored) {}
        }

        var feedbackId = jfeedback.attr('id');
        var originalDescribedby = jthis.data('xn-alert-original-aria-describedby') || '';
        var describedby = originalDescribedby ? originalDescribedby.split(/\s+/) : [];
        if (jq.inArray(feedbackId, describedby) === -1) describedby.push(feedbackId);
        jthis.attr('aria-describedby', jq.trim(describedby.join(' ')));
        jthis.off('input.xn-alert change.xn-alert').one('input.xn-alert change.xn-alert', function () {
            jthis.removeClass('is-invalid').removeAttr('aria-invalid');
            var feedback = jthis.data('xn-alert-feedback');
            if (feedback && feedback.length) feedback.text('').removeClass('d-block');
            try {
                var Tooltip = componentConstructor('tooltip');
                var instance = Tooltip && Tooltip.getInstance ? Tooltip.getInstance(jthis[0]) : null;
                if (instance) instance.dispose();
            } catch (error2) {
                try { jthis.tooltip('dispose'); } catch (ignored2) {}
            }
            var savedDescribedby = jthis.data('xn-alert-original-aria-describedby');
            if (savedDescribedby) jthis.attr('aria-describedby', savedDescribedby);
            else jthis.removeAttr('aria-describedby');
            restoreFieldAlertTitle(jthis);
            jthis.removeData('xn-alert-original-aria-describedby').removeData('xn-alert-feedback');
        });
    }

    function restoreFieldAlertTitle(jthis) {
        if (jthis.data('xn-alert-title-saved') !== true) return;
        if (jthis.data('xn-alert-had-title')) jthis.attr('title', jthis.data('xn-alert-original-title') || '');
        else jthis.removeAttr('title');
        jthis.removeData('xn-alert-title-saved').removeData('xn-alert-had-title').removeData('xn-alert-original-title');
    }

    function installAlertHelper(jq, state) {
        if (state.disabled.alert || (state.proxies.alert && jq.fn.alert === state.proxies.alert)) return;
        var proxy = function (message) {
			normalizeComponentCollection(this);
            var componentSelection = this.length > 0 && this.filter('.alert').length === this.length;
            if (componentSelection) {
                var Alert = componentConstructor('alert');
                if (typeof message === 'undefined' || (message && typeof message === 'object')) {
                    if (!Alert) return this;
                    return this.each(function () { getOrCreateComponent(Alert, this, message || {}); });
                }
                if (message === 'close' || message === 'dispose') {
                    return this.each(function () {
                        if (Alert) {
                            var instance = getOrCreateComponent(Alert, this, {});
                            if (typeof instance[message] === 'function') instance[message]();
                        } else if (message === 'close' && this.parentNode) this.parentNode.removeChild(this);
                    });
                }
                return delegateOriginal(jq, state, 'alert', this, arguments);
            }
            return this.each(function () { showFieldAlert(jq, this, message); });
        };
        decorateProxy(jq, state, 'alert', proxy);
    }

    function installTooltipProxy(jq, state) {
        installSimpleComponentProxy(jq, state, 'tooltip', ['show', 'hide', 'toggle', 'dispose', 'enable', 'disable', 'update']);
    }

    function installPopoverProxy(jq, state) {
        installSimpleComponentProxy(jq, state, 'popover', ['show', 'hide', 'toggle', 'dispose', 'enable', 'disable', 'update']);
    }

    function installFallbackXiunoApis(jq) {
        if (!jq.fn.location) {
            jq.fn.location = function (href) {
                this.queue(function (next) {
                    if (!href) window.location.reload();
                    else window.location = href;
                    next();
                });
                return this;
            };
        }
        if (!jq.fn.checked) {
            jq.fn.checked = function (value) {
                if (arguments.length) {
                    var values = value instanceof Array ? value.map(function (item) { return String(item); }) : [String(value)];
                    return this.each(function () {
                        if (this.tagName.toLowerCase() === 'select') jq(this).find('option').each(function () { this.selected = jq.inArray(String(this.value), values) !== -1; });
                        else if (this.type === 'checkbox' || this.type === 'radio') this.checked = jq.inArray(String(this.value), values) !== -1;
                    });
                }
                if (!this.length) return [];
                if (this[0].tagName.toLowerCase() === 'select') return jq(this[0]).find('option:selected').val() || '';
                if (this[0].type === 'checkbox') {
                    var checkedValues = [];
                    this.each(function () { if (this.checked) checkedValues.push(this.value); });
                    return checkedValues;
                }
                if (this[0].type === 'radio') {
                    var checked = this.filter(':checked').first();
                    return checked.length ? checked.val() : '';
                }
                return '';
            };
        }
        if (!jq.fn.reset) {
            jq.fn.reset = function () {
                var jform = jq(this);
                jform.find('input[type="submit"], button[type="submit"]').button('reset');
                jform.find('.is-invalid').each(function () {
					if (typeof window.xn_field_alert_clear === 'function') {
						window.xn_field_alert_clear(jq, this);
						return;
					}
                    var field = jq(this);
                    var feedback = field.data('xn-alert-feedback');
                    var describedby = field.data('xn-alert-original-aria-describedby');
                    if (describedby) field.attr('aria-describedby', describedby);
                    else field.removeAttr('aria-describedby');
                    if (feedback && feedback.length) feedback.removeClass('d-block').text('');
                    restoreFieldAlertTitle(field);
                    field.removeData('xn-alert-original-aria-describedby').removeData('xn-alert-feedback');
                });
                jform.find('.is-invalid').removeClass('is-invalid').removeAttr('aria-invalid');
                return this;
            };
        }
    }

    function installXiunoCoreSnapshot(jq) {
        var snapshot = window.XiunoJQueryCore;
        if (snapshot && typeof snapshot.install === 'function') snapshot.install(jq);
        installFallbackXiunoApis(jq);
    }

    function stripLocalCsrfHeader(headers, token) {
        if (!headers || !token) return;
        for (var name in headers) {
            if (!Object.prototype.hasOwnProperty.call(headers, name)) continue;
            if (String(name).toLowerCase() === 'x-csrf-token' && String(headers[name]) === String(token)) delete headers[name];
        }
    }

	function guardLocalCsrfRequestHeader(xhr, token) {
		if (!xhr || typeof xhr.setRequestHeader !== 'function' || !token || xhr.setRequestHeader._xnCsrfGuard) return;
		var originalSetRequestHeader = xhr.setRequestHeader;
		var guardedSetRequestHeader = function (name, value) {
			if (String(name).toLowerCase() === 'x-csrf-token' && String(value) === String(token)) return this;
			return originalSetRequestHeader.apply(this, arguments);
		};
		guardedSetRequestHeader._xnCsrfGuard = true;
		guardedSetRequestHeader._xnOriginal = originalSetRequestHeader;
		xhr.setRequestHeader = guardedSetRequestHeader;
	}

    function callBeforeSendWithoutCrossOriginCsrf(callback, context, xhr, settings, token) {
        if (typeof callback !== 'function') return;
        if (!xhr || typeof xhr.setRequestHeader !== 'function' || !token) return callback.call(context, xhr, settings);
        var originalSetRequestHeader = xhr.setRequestHeader;
        xhr.setRequestHeader = function (name, value) {
            if (String(name).toLowerCase() === 'x-csrf-token' && String(value) === String(token)) return;
            return originalSetRequestHeader.apply(this, arguments);
        };
        try {
            return callback.call(context, xhr, settings);
        } finally {
            xhr.setRequestHeader = originalSetRequestHeader;
        }
    }

    function installJQueryCsrf(jq, state) {
        if (typeof jq.ajaxSetup !== 'function') return;
        if (!state.csrfPrefilter && typeof jq.ajaxPrefilter === 'function') {
            state.csrfPrefilter = function (options, originalOptions, xhr) {
                var token = csrfToken();
                var requestUrl = options && options.url ? options.url : window.location.href;
                if (!token || (!(options && options.crossDomain) && isSameOrigin(requestUrl))) return;
                stripLocalCsrfHeader(options && options.headers, token);
				// Run before ordinary third-party prefilters and keep the guard on this
				// request object through beforeSend. This covers plugins that call
				// jqXHR.setRequestHeader() directly instead of using settings.headers.
				guardLocalCsrfRequestHeader(xhr, token);
            };
            jq.ajaxPrefilter('+*', state.csrfPrefilter);
        }
        if (jq.ajaxSettings && jq.ajaxSettings.beforeSend === state.csrfHook) return;
        var prevBeforeSend = jq.ajaxSettings && jq.ajaxSettings.beforeSend;
        if (prevBeforeSend && prevBeforeSend._xnCsrf) prevBeforeSend = null;
        var csrfBeforeSend = function (xhr, settings) {
            var token = csrfToken();
            var requestUrl = settings && settings.url ? settings.url : window.location.href;
            var sameOrigin = !(settings && settings.crossDomain) && isSameOrigin(requestUrl);
            var prevResult;
            if (typeof prevBeforeSend === 'function') {
                prevResult = sameOrigin
                    ? prevBeforeSend.call(this, xhr, settings)
                    : callBeforeSendWithoutCrossOriginCsrf(prevBeforeSend, this, xhr, settings, token);
                if (prevResult === false) return false;
            }
            var ajaxMethod = settings && (settings.type || settings.method) ? String(settings.type || settings.method).toUpperCase() : 'GET';
            if (token && ajaxMethod === 'POST' && sameOrigin) xhr.setRequestHeader('X-CSRF-TOKEN', token);
            return prevResult;
        };
        csrfBeforeSend._xnCsrf = true;
        csrfBeforeSend._xnPrevious = prevBeforeSend;
        state.csrfHook = csrfBeforeSend;
        jq.ajaxSetup({ beforeSend: csrfBeforeSend });
        window._csrf_ajax_setup_done = true;
        window._csrf_ajax_setup_jquery = jq;
    }

    function installJQueryAdapters(jq) {
        if (!jq || !jq.fn) return;
        var state = getJQueryState(jq);
        // Capture this identity before the explicit Xiuno whitelist is copied.
        // This preserves a real Bootstrap/third-party implementation for noConflict.
        captureCurrentImplementations(jq, state);
        installXiunoCoreSnapshot(jq);
        installJQueryCsrf(jq, state);
		installLegacyQuickReplyReload(jq, state);
        installModalProxy(jq, state);
        installDropdownProxy(jq, state);
        installTabProxy(jq, state);
		installCarouselProxy(jq, state);
		installCollapseProxy(jq, state);
		installOffcanvasProxy(jq, state);
		installScrollSpyProxy(jq, state);
		installToastProxy(jq, state);
        installButtonProxy(jq, state);
        installAlertHelper(jq, state);
        installTooltipProxy(jq, state);
        installPopoverProxy(jq, state);
        runtime.jquery = jq;
    }
    runtime.installJQuery = installJQueryAdapters;

    function fixDropdownAlign(root) {
        eachRootAndDescendant(root, '.dropdown-menu-right, .dropdown-menu-left', function (menu) {
			syncOwnedAlignmentClass(menu, 'dropdown-menu-right', 'dropdown-menu-end', 'data-xn-bs4c-align-end');
			syncOwnedAlignmentClass(menu, 'dropdown-menu-left', 'dropdown-menu-start', 'data-xn-bs4c-align-start');
        });
		eachRootAndDescendant(root, '[data-xn-bs4c-align-end],[data-xn-bs4c-align-start]', function (menu) {
			syncOwnedAlignmentClass(menu, 'dropdown-menu-right', 'dropdown-menu-end', 'data-xn-bs4c-align-end');
			syncOwnedAlignmentClass(menu, 'dropdown-menu-left', 'dropdown-menu-start', 'data-xn-bs4c-align-start');
		});
    }

	function syncOwnedAlignmentClass(element, legacyClass, modernClass, marker) {
		if (element.classList.contains(legacyClass)) {
			if (!element.classList.contains(modernClass)) {
				element.classList.add(modernClass);
				element.setAttribute(marker, '1');
			}
			return;
		}
		if (!element.hasAttribute(marker)) return;
		element.classList.remove(modernClass);
		element.removeAttribute(marker);
	}

    function bindCustomFile(root) {
        eachRootAndDescendant(root, '.custom-file-input', function (input) { input.setAttribute('data-bs4c-bound', '1'); });
    }

    function coreAuthFormRoute(form) {
        if (!form || String(form.method || '').toLowerCase() !== 'post') return '';
        var action = form.getAttribute('action') || window.location.href;
        var resolved;
        try {
            resolved = new URL(action, document.baseURI || window.location.href);
        } catch (error) {
            return '';
        }
        if (resolved.origin !== window.location.origin) return '';
        var candidates = [];
        var queryRoute = resolved.search ? resolved.search.slice(1).split('&')[0] : '';
        if (queryRoute && queryRoute.indexOf('=') === -1) candidates.push(queryRoute);
        candidates.push(resolved.pathname || '');
        var routes = ['user-resetpw-complete', 'user-resetpw', 'my-password', 'index-login', 'user-login', 'user-create'];
        for (var candidateIndex = 0; candidateIndex < candidates.length; candidateIndex++) {
            var candidate = candidates[candidateIndex];
            try { candidate = decodeURIComponent(candidate); } catch (error) {}
            candidate = candidate.toLowerCase()
                .replace(/\.htm$/i, '')
                .replace(/[_/\\]+/g, '-')
                .replace(/^-+|-+$/g, '')
                .replace(/-+/g, '-');
            if (/(^|-)user-update(?:-[0-9]+)?$/.test(candidate)) return 'user-update';
            for (var routeIndex = 0; routeIndex < routes.length; routeIndex++) {
                var route = routes[routeIndex];
                if (candidate === route || candidate.slice(-(route.length + 1)) === '-' + route) return route;
            }
        }
        return '';
    }

    function setMissingFieldSemantics(field, autocomplete, required, inputmode) {
        if (!field || field.disabled) return;
        if (autocomplete && !field.hasAttribute('autocomplete')) field.setAttribute('autocomplete', autocomplete);
        if (required && !field.hasAttribute('required')) field.setAttribute('required', '');
        if (inputmode && !field.hasAttribute('inputmode')) field.setAttribute('inputmode', inputmode);
    }

    // Theme overwrites often predate browser credential semantics. Repair only same-origin core auth
    // routes and standard field names/types; never target a theme/private selector or override an
    // explicit package choice such as autocomplete="off".
    function repairCoreAuthFormSemantics(root) {
        eachRootAndDescendant(root, 'form[method="post"],form[method="POST"]', function (form) {
            var route = coreAuthFormRoute(form);
            if (!route) return;
            var fields = form.elements || [];
            for (var i = 0; i < fields.length; i++) {
                var field = fields[i];
                if (!field || !field.tagName || field.tagName.toLowerCase() !== 'input') continue;
                var name = String(field.name || '').toLowerCase();
                var type = String(field.type || '').toLowerCase();
                if (route === 'index-login' && name === 'password' && type === 'password') {
                    setMissingFieldSemantics(field, 'current-password', true);
                } else if (route === 'user-login') {
                    if (name === 'email') setMissingFieldSemantics(field, 'username', true);
                    else if (name === 'password' && type === 'password') setMissingFieldSemantics(field, 'current-password', true);
                } else if (route === 'user-create') {
                    if (name === 'email') setMissingFieldSemantics(field, 'email', true, 'email');
                    else if (name === 'username') setMissingFieldSemantics(field, 'username', true);
                    else if (name === 'password' && type === 'password') setMissingFieldSemantics(field, 'new-password', true);
                    else if (name === 'code') setMissingFieldSemantics(field, 'one-time-code', true, 'numeric');
                } else if (route === 'user-resetpw') {
                    if (name === 'email') setMissingFieldSemantics(field, 'email', true, 'email');
                    else if (name === 'code') setMissingFieldSemantics(field, 'one-time-code', true, 'numeric');
                } else if (route === 'user-resetpw-complete' && type === 'password') {
                    setMissingFieldSemantics(field, 'new-password', true);
                } else if (route === 'my-password' && type === 'password') {
                    setMissingFieldSemantics(field, name === 'password_old' ? 'current-password' : 'new-password', true);
                } else if (route === 'user-update' && name === 'password' && type === 'password') {
                    // Admin password replacement is optional when editing unrelated profile fields.
                    setMissingFieldSemantics(field, 'new-password', false);
                }
            }
        });
    }

    function updateCustomFileLabel(input) {
        var parent = input.parentNode;
        var label = parent && parent.querySelector ? parent.querySelector('.custom-file-label') : null;
        if (!label) return;
        var files = input.files;
        if (!files || files.length === 0) return;
        var names = [];
        for (var i = 0; i < files.length; i++) names.push(files[i].name);
        label.textContent = names.join(', ');
    }

    function initBtnGroupToggle(root) {
        eachRootAndDescendant(root, '[data-toggle="buttons"],[data-bs-toggle="buttons"]', function (group) {
            group.setAttribute('data-bs4c-toggle-init', '1');
            var inputs = group.querySelectorAll('input[type="radio"], input[type="checkbox"]');
            for (var i = 0; i < inputs.length; i++) {
                var button = closestElement(inputs[i], '.btn');
				if (button) {
					button.classList.toggle('active', !!inputs[i].checked);
					button.setAttribute('aria-pressed', inputs[i].checked ? 'true' : 'false');
				}
            }
        });
    }

    function toggleBtnGroup(event, group) {
        var button = closestElement(event.target, '.btn');
        if (!button || !group.contains(button) || button.classList.contains('disabled')) return;
        var radio = button.querySelector('input[type="radio"]');
        var checkbox = button.querySelector('input[type="checkbox"]');
        if (!radio && !checkbox) return;
		var input = radio || checkbox;
		if (input.disabled) return;
        event.preventDefault();
        if (radio) {
			var changed = !radio.checked;
			var radios = group.querySelectorAll('input[type="radio"]');
			for (var i = 0; i < radios.length; i++) {
				if (radios[i] !== radio && radios[i].name === radio.name) radios[i].checked = false;
				var radioButton = closestElement(radios[i], '.btn');
				if (radioButton && radios[i].name === radio.name) {
					radioButton.classList.toggle('active', !!radios[i].checked);
					radioButton.setAttribute('aria-pressed', radios[i].checked ? 'true' : 'false');
				}
			}
            button.classList.add('active');
            radio.checked = true;
            button.setAttribute('aria-pressed', 'true');
			if (changed) radio.dispatchEvent(new Event('change', { bubbles: true }));
        } else {
            checkbox.checked = !checkbox.checked;
            button.classList.toggle('active', checkbox.checked);
            button.setAttribute('aria-pressed', checkbox.checked ? 'true' : 'false');
			checkbox.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function fixTabHref(root) {
		var targetMarker = 'data-xn-bs-auto-tab-target';
		eachRootAndDescendant(root, '[data-toggle="tab"],[data-bs-toggle="tab"],[' + targetMarker + ']', function (tab) {
            var href = tab.getAttribute('href') || '';
			var isTab = tab.getAttribute('data-toggle') === 'tab' || tab.getAttribute('data-bs-toggle') === 'tab';
			if (isTab && href.charAt(0) === '#' && href.length > 1 && !tab.hasAttribute('data-target')) {
                var markerOwned = tab.hasAttribute(targetMarker);
                var oldTarget = markerOwned ? tab.getAttribute(targetMarker) : null;
                if (markerOwned && tab.getAttribute('data-bs-target') !== oldTarget) {
                    tab.removeAttribute(targetMarker);
                    markerOwned = false;
                }
                if (markerOwned || !tab.hasAttribute('data-bs-target')) {
                    tab.setAttribute('data-bs-target', href);
                    tab.setAttribute(targetMarker, href);
                }
			} else if (tab.hasAttribute(targetMarker)) {
				var ownedTarget = tab.getAttribute(targetMarker);
				if (tab.getAttribute('data-bs-target') === ownedTarget) tab.removeAttribute('data-bs-target');
				tab.removeAttribute(targetMarker);
            }
            var Tab = componentConstructor('tab');
			if (isTab && Tab) getOrCreateComponent(Tab, tab, {});
        });
    }

    function closeLegacyComponent(button) {
        if (button.hasAttribute('data-bs-dismiss')) return;
        var parent = closestElement(button.parentElement, '.alert, .modal, .toast');
        if (!parent) return;
        var name = parent.classList.contains('modal') ? 'modal' : (parent.classList.contains('toast') ? 'toast' : 'alert');
        var Component = componentConstructor(name);
        if (Component) {
            var instance = getOrCreateComponent(Component, parent, {});
            if (name === 'alert' && typeof instance.close === 'function') instance.close();
            else if (typeof instance.hide === 'function') instance.hide();
            return;
        }
        parent.style.display = 'none';
    }

    function handleGlobalClick(event) {
        var link = closestElement(event.target, 'a');
        if (link && isPluginWriteLink(link)) link.setAttribute('data-method', 'post');
        var closeButton = closestElement(event.target, '.close');
        if (closeButton) closeLegacyComponent(closeButton);
        var group = closestElement(event.target, '[data-toggle="buttons"],[data-bs-toggle="buttons"]');
        if (group) toggleBtnGroup(event, group);
    }

    runtime.refresh = function (root) {
        root = root || document;
        setupCsrfToken();
        installFetchCsrf();
        installJQueryAdapters(window.jQuery);
        convertAttributes(root);
        reconcileCsrfForms(root);
        fixPluginPostLinks(root);
        fixDropdownAlign(root);
        bindCustomFile(root);
        repairCoreAuthFormSemantics(root);
        initBtnGroupToggle(root);
        fixTabHref(root);
        runtime.refreshCount = (runtime.refreshCount || 0) + 1;
        return runtime;
    };
	runtime.clearFieldAlert = function (element) {
		if (typeof window.xn_field_alert_clear === 'function' && window.jQuery) window.xn_field_alert_clear(window.jQuery, element);
	};

    runtime.scheduleRefresh = function (root) {
        root = root || document;
        if (root === document) runtime._pendingRoots = [document];
		else if (runtime._pendingRoots.indexOf(document) === -1) {
			var covered = false;
			for (var pendingIndex = runtime._pendingRoots.length - 1; pendingIndex >= 0; pendingIndex--) {
				var pendingRoot = runtime._pendingRoots[pendingIndex];
				if (pendingRoot === root || (pendingRoot && typeof pendingRoot.contains === 'function' && pendingRoot.contains(root))) {
					covered = true;
					break;
				}
				if (root && typeof root.contains === 'function' && root.contains(pendingRoot)) runtime._pendingRoots.splice(pendingIndex, 1);
			}
			if (!covered) runtime._pendingRoots.push(root);
		}
        if (runtime._refreshTimer !== null) return;
        runtime._refreshTimer = window.setTimeout(function () {
            var roots = runtime._pendingRoots.slice();
            runtime._pendingRoots.length = 0;
            runtime._refreshTimer = null;
            for (var i = 0; i < roots.length; i++) runtime.refresh(roots[i]);
        }, 0);
    };

    document.addEventListener('click', handleGlobalClick, true);
    document.addEventListener('change', function (event) {
        if (event.target && event.target.matches && event.target.matches('.custom-file-input')) updateCustomFileLabel(event.target);
    }, true);
    document.addEventListener('error', function (event) {
        if (event.target && event.target.tagName === 'IMG') handleBrokenImg(event.target);
    }, true);
    document.addEventListener('submit', function (event) {
        if (event.target && event.target.tagName === 'FORM') {
            rememberLegacyQuickReplySubmission(event.target, event.submitter || null);
            rememberFormSubmission(event.target, event.submitter || null);
            reconcileCsrfForm(event.target, csrfToken(), event.submitter || null);
        }
    }, true);
    document.addEventListener('formdata', function (event) {
        var form = event.target;
        if (!form || form.tagName !== 'FORM') return;
		var token = csrfToken();
		var submission = formSubmissionDetails(form, rememberedFormSubmitter(form));
		if (submission.localPost) {
			if (token) {
				event.formData.delete('_token');
				event.formData.append('_token', token);
			}
			return;
		}
		var ownedValues = removeLocalCsrf(form, token);
		stripLocalCsrfFormData(event.formData, token, ownedValues);
    }, true);
    document.addEventListener('xiuno:fragment-ready', function (event) {
        runtime.refresh(event.detail && event.detail.elt ? event.detail.elt : (event.target || document));
    });
    document.addEventListener('load', function (event) {
		// A parser-following inline script can run immediately after an external
		// jQuery/Bootstrap/Xiuno script. Rebind synchronously during the script load
		// event so that inline legacy calls observe the final compatibility APIs.
		if (event.target && event.target.tagName === 'SCRIPT') runtime.refresh(document);
		else if (event.target && event.target.tagName === 'IMG') resetBrokenImg(event.target);
    }, true);

    function documentReadyRefresh() {
        runtime.refresh(document);
        // When compat loaded before Bootstrap, Bootstrap's own DOMContentLoaded
        // callback can run later in the same event and replace the adapters.
        runtime.scheduleRefresh(document);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', documentReadyRefresh);
    else documentReadyRefresh();
    window.addEventListener('load', function () { runtime.refresh(document); });

    if (typeof MutationObserver !== 'undefined' && document.documentElement) {
        runtime.observer = new MutationObserver(function (mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var mutation = mutations[i];
                if (mutation.type === 'attributes') {
					if (mutation.oldValue === mutation.target.getAttribute(mutation.attributeName)) continue;
                    runtime.scheduleRefresh(mutation.target);
					if (mutation.attributeName === 'class' && mutation.target.parentElement) {
						var className = mutation.target.getAttribute('class') || '';
						var oldClassName = mutation.oldValue || '';
						if (/(^|\s)dropdown-menu(?:\s|$)/.test(className) || /(^|\s)dropdown-menu(?:\s|$)/.test(oldClassName)) runtime.scheduleRefresh(mutation.target.parentElement);
					}
                    continue;
                }
				// Added roots can initialize themselves. The container only needs a rescan after
				// removals, where an owned dropdown/attribute mapping may have become invalid.
				if (mutation.removedNodes && mutation.removedNodes.length) runtime.scheduleRefresh(mutation.target);
                for (var j = 0; j < mutation.addedNodes.length; j++) {
                    var node = mutation.addedNodes[j];
                    if (node.nodeType === 1 || node.nodeType === 11) {
                        runtime.scheduleRefresh(node);
                        var addsDropdownStructure = node.nodeType === 1 && (isDropdownMenu(node)
                            || (typeof node.querySelector === 'function' && !!node.querySelector('.dropdown-menu')));
                        if (addsDropdownStructure) runtime.scheduleRefresh(mutation.target);
                    }
                }
            }
        });
        runtime.observer.observe(document.documentElement, {
            childList: true,
            subtree: true,
            attributes: true,
			attributeOldValue: true,
            attributeFilter: observedAttributes
        });
    }

    runtime.refresh(document);
})(window, document);
