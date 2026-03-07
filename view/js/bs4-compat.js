/**
 * Bootstrap 4 → Bootstrap 5 兼容垫片
 * 自动将旧插件中的 BS4 data 属性转换为 BS5 格式
 * 确保旧插件在 BS5 环境下正常工作
 */
(function() {
    'use strict';

    // BS4 → BS5 属性映射
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
        'data-content':   'data-bs-content',
        'data-placement': 'data-bs-placement',
        'data-trigger':   'data-bs-trigger',
        'data-container': 'data-bs-container'
    };

    function convertAttributes() {
        for (var bs4Attr in attrMap) {
            var bs5Attr = attrMap[bs4Attr];
            var elements = document.querySelectorAll('[' + bs4Attr + ']');
            for (var i = 0; i < elements.length; i++) {
                var el = elements[i];
                if (!el.hasAttribute(bs5Attr)) {
                    el.setAttribute(bs5Attr, el.getAttribute(bs4Attr));
                }
            }
        }
    }

    // 页面加载时执行一次
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', convertAttributes);
    } else {
        convertAttributes();
    }

    // 监听 DOM 变化（插件动态插入的元素）
    if (typeof MutationObserver !== 'undefined') {
        var observer = new MutationObserver(function(mutations) {
            var needsConvert = false;
            for (var i = 0; i < mutations.length; i++) {
                if (mutations[i].addedNodes.length > 0) {
                    needsConvert = true;
                    break;
                }
            }
            if (needsConvert) convertAttributes();
        });
        observer.observe(document.documentElement, { childList: true, subtree: true });
    }
})();

// CSRF 自动设置：从 meta tag 读取，确保主题覆盖 footer 时 CSRF 不丢失
(function() {
    'use strict';
    function setupCsrf() {
        if (typeof window.csrf_token === 'undefined' || !window.csrf_token) {
            var meta = document.querySelector('meta[name="csrf-token"]');
            if (meta && typeof jQuery !== 'undefined') {
                window.csrf_token = meta.getAttribute('content');
                jQuery.ajaxSetup({beforeSend:function(xhr){xhr.setRequestHeader('X-CSRF-TOKEN', window.csrf_token);}});
            }
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupCsrf);
    } else {
        setupCsrf();
    }
    // 延迟再检查一次，确保在所有脚本加载后生效
    setTimeout(setupCsrf, 100);
})();

// CSRF 表单兼容：自动为所有 form[method=post] 注入 _token hidden 字段
(function() {
    'use strict';
    function injectCsrfToForms() {
        var token = window.csrf_token;
        if (!token) {
            var meta = document.querySelector('meta[name="csrf-token"]');
            if (meta) token = meta.getAttribute('content');
        }
        if (!token) return;
        var forms = document.querySelectorAll('form[method="post"], form[method="POST"]');
        for (var i = 0; i < forms.length; i++) {
            if (forms[i].querySelector('input[name="_token"]')) continue;
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = '_token';
            input.value = token;
            forms[i].appendChild(input);
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', injectCsrfToForms);
    } else {
        injectCsrfToForms();
    }
    // 监听动态插入的表单
    if (typeof MutationObserver !== 'undefined') {
        var observer = new MutationObserver(function(mutations) {
            var hasNewNodes = false;
            for (var i = 0; i < mutations.length; i++) {
                if (mutations[i].addedNodes.length > 0) { hasNewNodes = true; break; }
            }
            if (hasNewNodes) injectCsrfToForms();
        });
        document.addEventListener('DOMContentLoaded', function() {
            observer.observe(document.body, { childList: true, subtree: true });
        });
    }
})();

// BS4 Modal JS API 兼容：代理 jQuery .modal() 方法到 BS5 Modal 实例
(function() {
    'use strict';
    if (typeof jQuery === 'undefined') return;
    var origModal = jQuery.fn.modal;
    jQuery.fn.modal = function(action) {
        // 如果 BS5 bootstrap.Modal 可用，使用它
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            return this.each(function() {
                var instance = bootstrap.Modal.getInstance(this) || new bootstrap.Modal(this);
                if (action === 'show') instance.show();
                else if (action === 'hide') instance.hide();
                else if (action === 'toggle') instance.toggle();
                else if (action === 'dispose' || action === 'handleUpdate') instance.dispose();
                else if (typeof action === 'object' || typeof action === 'undefined') instance.show();
            });
        }
        // 回退到原始实现
        if (origModal) return origModal.apply(this, arguments);
        return this;
    };
})();
