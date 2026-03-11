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

    function convertAttributes(root) {
        root = root || document;
        if (!root || typeof root.querySelectorAll !== 'function') return;
        for (var bs4Attr in attrMap) {
            var bs5Attr = attrMap[bs4Attr];

            // querySelectorAll() doesn't include the root element itself.
            if (root.nodeType === 1 && root.hasAttribute && root.hasAttribute(bs4Attr) && !root.hasAttribute(bs5Attr)) {
                root.setAttribute(bs5Attr, root.getAttribute(bs4Attr));
            }

            var elements = root.querySelectorAll('[' + bs4Attr + ']');
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
        document.addEventListener('DOMContentLoaded', function(){ convertAttributes(document); });
    } else {
        convertAttributes(document);
    }

    // 监听 DOM 变化（插件动态插入的元素）
    if (typeof MutationObserver !== 'undefined') {
        var observer = new MutationObserver(function(mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var nodes = mutations[i].addedNodes;
                for (var j = 0; j < nodes.length; j++) {
                    var n = nodes[j];
                    if (n.nodeType !== 1 && n.nodeType !== 11) continue; // Element / DocumentFragment
                    convertAttributes(n);
                }
            }
        });
        observer.observe(document.documentElement, { childList: true, subtree: true });
    }
})();

// CSRF 自动设置：确保 csrf_token 变量存在 + jQuery AJAX 全局拦截器
(function() {
    'use strict';
    function setupCsrf() {
        // 1. 确保 csrf_token 变量存在（优先用已有变量，否则从 meta 读取）
        if (typeof window.csrf_token === 'undefined' || !window.csrf_token) {
            var meta = document.querySelector('meta[name="csrf-token"]');
            if (meta) window.csrf_token = meta.getAttribute('content');
        }
        if (!window.csrf_token) return;

        // 2. 设置 jQuery AJAX 全局拦截器（如果 jQuery 已加载）
        if (typeof jQuery !== 'undefined' && !window._csrf_ajax_setup_done) {
            jQuery.ajaxSetup({beforeSend:function(xhr){xhr.setRequestHeader('X-CSRF-TOKEN', window.csrf_token);}});
            window._csrf_ajax_setup_done = true;
        }

        // 3. 设置 fetch 拦截器（部分现代主题使用 fetch 而非 jQuery）
        if (!window._csrf_fetch_setup_done && typeof window.fetch === 'function') {
            var origFetch = window.fetch;
            window.fetch = function(input, init) {
                init = init || {};
                if (init.method && init.method.toUpperCase() === 'POST') {
                    init.headers = init.headers || {};
                    if (typeof init.headers.set === 'function') {
                        init.headers.set('X-CSRF-TOKEN', window.csrf_token);
                    } else {
                        init.headers['X-CSRF-TOKEN'] = window.csrf_token;
                    }
                }
                return origFetch.call(this, input, init);
            };
            window._csrf_fetch_setup_done = true;
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupCsrf);
    } else {
        setupCsrf();
    }
    // 延迟再检查一次，确保在 jQuery 等脚本加载后生效
    setTimeout(setupCsrf, 500);
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
        var forms = document.querySelectorAll('form');
        for (var i = 0; i < forms.length; i++) {
            // 匹配所有 POST 表单（大小写不敏感）
            if (forms[i].method && forms[i].method.toUpperCase() !== 'POST') continue;
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
            for (var i = 0; i < mutations.length; i++) {
                if (mutations[i].addedNodes.length > 0) { injectCsrfToForms(); break; }
            }
        });
        function startObserve() {
            if (!document.body) return;
            observer.observe(document.body, { childList: true, subtree: true });
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', startObserve);
        } else {
            startObserve();
        }
    }
})();

// 缺失资源降级：检测 inline background-image 404 并自动降级
(function() {
    'use strict';
    // 检查单个元素的背景图是否可加载
    function checkBgImage(el) {
        var style = el.getAttribute('style');
        if (!style || style.indexOf('url(') === -1) return;
        var match = style.match(/url\(['"]?([^'"\)]+)['"]?\)/);
        if (!match || !match[1]) return;
        var url = match[1];
        // 跳过 data URI 和渐变
        if (url.indexOf('data:') === 0 || url.indexOf('linear-gradient') !== -1) return;
        var img = new Image();
        img.onerror = function() {
            el.classList.add('bs4c-bg-fallback');
        };
        img.src = url;
    }
    function scanBgImages() {
        var els = document.querySelectorAll('[style*="url("]');
        for (var i = 0; i < els.length; i++) {
            checkBgImage(els[i]);
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scanBgImages);
    } else {
        scanBgImages();
    }
    // 监听动态插入的元素
    if (typeof MutationObserver !== 'undefined') {
        var observer = new MutationObserver(function(mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var nodes = mutations[i].addedNodes;
                for (var j = 0; j < nodes.length; j++) {
                    if (nodes[j].nodeType !== 1) continue;
                    if (nodes[j].getAttribute && nodes[j].getAttribute('style')) {
                        checkBgImage(nodes[j]);
                    }
                    // 也检查子元素
                    var children = nodes[j].querySelectorAll ? nodes[j].querySelectorAll('[style*="url("]') : [];
                    for (var k = 0; k < children.length; k++) {
                        checkBgImage(children[k]);
                    }
                }
            }
        });
        function startObserve() {
            if (!document.body) return;
            observer.observe(document.body, { childList: true, subtree: true });
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', startObserve);
        } else {
            startObserve();
        }
    }
})();

// 缺失资源降级：破损 <img> 自动隐藏或降级
(function() {
    'use strict';
    function handleBrokenImg(img) {
        // 避免重复处理
        if (img.dataset.bs4cHandled) return;
        img.dataset.bs4cHandled = '1';
        img.classList.add('bs4c-img-fallback');
        // 小头像类图片直接隐藏，大图保持占位
        var w = img.width || img.offsetWidth || 0;
        if (w > 0 && w <= 80) {
            img.style.visibility = 'hidden';
        }
    }
    // 全局 error 事件捕获（捕获阶段，能拦截所有资源加载失败）
    document.addEventListener('error', function(e) {
        if (e.target && e.target.tagName === 'IMG') {
            handleBrokenImg(e.target);
        }
    }, true);
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

// BS4 Button JS API 兼容：代理 jQuery .button() 方法（loading / reset / toggle / disabled / enable）
// BS5 移除了 jQuery .button() 插件，但几乎所有旧插件都依赖它（370+ 处调用）
(function() {
    'use strict';
    if (typeof jQuery === 'undefined') return;
    var origButton = jQuery.fn.button;
    jQuery.fn.button = function(action) {
        return this.each(function() {
            var $el = jQuery(this);
            if (action === 'loading') {
                // 保存原始文本和状态
                if (!$el.data('bs4c-original-text')) {
                    $el.data('bs4c-original-text', $el.html());
                }
                var loadingText = $el.attr('data-loading-text') || $el.data('loading-text') || '...';
                $el.html(loadingText).prop('disabled', true).addClass('disabled');
            } else if (action === 'reset') {
                var origText = $el.data('bs4c-original-text');
                if (origText) $el.html(origText);
                $el.prop('disabled', false).removeClass('disabled');
            } else if (action === 'toggle') {
                $el.toggleClass('active');
                var isActive = $el.hasClass('active');
                $el.attr('aria-pressed', isActive);
            } else if (action === 'disabled' || action === 'disable') {
                $el.prop('disabled', true).addClass('disabled');
            } else if (action === 'enable') {
                $el.prop('disabled', false).removeClass('disabled');
            } else if (typeof action === 'string') {
                // .button('自定义文本') — 部分插件用 .button(message) 设置按钮文字
                if (!$el.data('bs4c-original-text')) {
                    $el.data('bs4c-original-text', $el.html());
                }
                $el.html(action);
            }
        });
    };
})();

// xiuno.js 核心方法兼容：当主题替换了 xiuno.js 时，确保这些方法存在
(function() {
    'use strict';
    if (typeof jQuery === 'undefined') return;

    // $.fn.location — 延迟跳转（支持 jQuery 动画队列链式调用）
    if (!jQuery.fn.location) {
        jQuery.fn.location = function(href) {
            this.queue(function(next) {
                if (!href) {
                    window.location.reload();
                } else {
                    window.location = href;
                }
                next();
            });
            return this;
        };
    }

    // $.fn.reset — 重置表单状态（恢复按钮 + 清除验证提示）
    if (!jQuery.fn.reset) {
        jQuery.fn.reset = function() {
            var jform = jQuery(this);
            jform.find('input[type="submit"], button[type="submit"]').button('reset');
            try { jform.find('input').tooltip('dispose'); } catch(e) {}
            return this;
        };
    }

    // $.fn.checked — select/radio/checkbox 值设置与获取
    if (!jQuery.fn.checked) {
        jQuery.fn.checked = function(v) {
            if (v) {
                v = v instanceof Array ? v.map(function(vv){return vv+''}) : v+'';
                var filter = function(){return !(v instanceof Array)?(this.value==v):(jQuery.inArray(this.value,v)!=-1)};
                this.each(function(){
                    var tag = this.tagName.toLowerCase();
                    if (tag==='select') {
                        jQuery(this).find('option').filter(filter).prop('selected',true);
                    } else if (this.type==='checkbox'||this.type==='radio') {
                        jQuery(this).filter(filter).prop('checked',true);
                    }
                });
                return this;
            } else {
                if (!this.length) return [];
                var el = this[0];
                var tagtype = el.tagName.toLowerCase()==='select'?'select':(el.type||'').toLowerCase();
                if (tagtype==='select') {
                    var opt = jQuery(el).find('option:selected');
                    return opt.length ? opt.attr('value') : '';
                } else if (tagtype==='checkbox') {
                    var r=[];
                    for(var i=0;i<this.length;i++) if(this[i].checked) r.push(this[i].value);
                    return r;
                } else if (tagtype==='radio') {
                    for(var i=0;i<this.length;i++) if(this[i].checked) return this[i].value;
                    return '';
                }
                return '';
            }
        };
    }

    // $.fn.alert — 在控件上方显示错误提示（BS5 Tooltip 方式）
    if (!jQuery.fn.alert || jQuery.fn.alert === jQuery.fn.alert._bs5native) {
        jQuery.fn.alert = function(message) {
            var jthis = jQuery(this);
            jthis.addClass('is-invalid');
            jthis.attr('title', message);
            try {
                var tooltip = bootstrap.Tooltip.getInstance(jthis[0]);
                if (tooltip) {
                    tooltip.setContent({'.tooltip-inner': message});
                    tooltip.show();
                } else {
                    new bootstrap.Tooltip(jthis[0], {title: message, trigger: 'manual', placement: 'top'}).show();
                }
            } catch(e) {
                try { jthis.tooltip({title: message, trigger: 'manual', placement: 'top'}).tooltip('show'); } catch(e2) {}
            }
            jthis.one('focus input', function() {
                jthis.removeClass('is-invalid');
                try { var t = bootstrap.Tooltip.getInstance(jthis[0]); if(t) t.dispose(); } catch(e) {
                    try { jthis.tooltip('dispose'); } catch(e2) {}
                }
            });
            return this;
        };
    }
})();

// BS4 Tooltip JS API 兼容：代理 jQuery .tooltip() 方法到 BS5 Tooltip 实例
(function() {
    'use strict';
    if (typeof jQuery === 'undefined') return;
    var origTooltip = jQuery.fn.tooltip;
    jQuery.fn.tooltip = function(action) {
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            return this.each(function() {
                var instance = bootstrap.Tooltip.getInstance(this);
                if (typeof action === 'object' || typeof action === 'undefined') {
                    // 初始化：$('[data-toggle="tooltip"]').tooltip() 或 .tooltip({...})
                    if (!instance) {
                        var opts = (typeof action === 'object') ? action : {};
                        instance = new bootstrap.Tooltip(this, opts);
                    }
                } else if (instance) {
                    if (action === 'show') instance.show();
                    else if (action === 'hide') instance.hide();
                    else if (action === 'toggle') instance.toggle();
                    else if (action === 'dispose') instance.dispose();
                    else if (action === 'enable') instance.enable();
                    else if (action === 'disable') instance.disable();
                    else if (action === 'update') instance.update();
                }
            });
        }
        if (origTooltip) return origTooltip.apply(this, arguments);
        return this;
    };
})();

// BS4 Popover JS API 兼容：代理 jQuery .popover() 方法到 BS5 Popover 实例
(function() {
    'use strict';
    if (typeof jQuery === 'undefined') return;
    var origPopover = jQuery.fn.popover;
    jQuery.fn.popover = function(action) {
        if (typeof bootstrap !== 'undefined' && bootstrap.Popover) {
            return this.each(function() {
                var instance = bootstrap.Popover.getInstance(this);
                if (typeof action === 'object' || typeof action === 'undefined') {
                    if (!instance) {
                        var opts = (typeof action === 'object') ? action : {};
                        instance = new bootstrap.Popover(this, opts);
                    }
                } else if (instance) {
                    if (action === 'show') instance.show();
                    else if (action === 'hide') instance.hide();
                    else if (action === 'toggle') instance.toggle();
                    else if (action === 'dispose') instance.dispose();
                    else if (action === 'enable') instance.enable();
                    else if (action === 'disable') instance.disable();
                    else if (action === 'update') instance.update();
                }
            });
        }
        if (origPopover) return origPopover.apply(this, arguments);
        return this;
    };
})();
