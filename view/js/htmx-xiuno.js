/*
 * Xiuno Next HTMX bridge.
 * Keeps HTMX progressive: plain links/forms must continue to work without JS.
 */
(function() {
    'use strict';

    if (window.htmx && window.htmx.config) {
        window.htmx.config.allowEval = false;
        window.htmx.config.allowScriptTags = false;
    }

    function csrfToken() {
        if (window.csrf_token) return window.csrf_token;
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function ensureMessageHost() {
        var host = document.getElementById('htmx-message-host');
        if (host) return host;

        host = document.createElement('div');
        host.id = 'htmx-message-host';
        host.className = 'position-fixed top-0 start-50 translate-middle-x p-3';
        host.style.zIndex = '1080';
        document.body.appendChild(host);
        return host;
    }

    function showMessage(detail) {
        if (!detail || !detail.message) return;
        var types = {
            success: true,
            info: true,
            warning: true,
            danger: true
        };
        var type = types[detail.type] ? detail.type : 'info';
        var host = ensureMessageHost();
        var alert = document.createElement('div');
        alert.className = 'alert alert-' + type + ' shadow-sm mb-2 show';
        alert.setAttribute('role', 'status');
        alert.textContent = detail.message;
        host.appendChild(alert);
        window.setTimeout(function() {
            alert.classList.add('fade');
            alert.classList.remove('show');
            window.setTimeout(function() { alert.remove(); }, 300);
        }, 2600);
    }

    document.body.addEventListener('htmx:configRequest', function(event) {
        var token = csrfToken();
        var verb = (event.detail.verb || '').toUpperCase();
        if (token && verb !== 'GET') {
            event.detail.headers = event.detail.headers || {};
            event.detail.headers['X-CSRF-TOKEN'] = token;
        }
    });

    document.body.addEventListener('showMessage', function(event) {
        showMessage(event.detail);
    });
})();
