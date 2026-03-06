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
