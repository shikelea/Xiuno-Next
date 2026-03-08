/**
 * # Aether 主题 JavaScript 助手
 * 
 * 万恶的浏览器兼容性让我写不了ES6
 * 这也是一种OOP
 * @type {Object}
 * @namespace Aether
 */
var Aether = window.Aether || {
    navigation: {
        history: [],  // 存储导航路径
        maxHistory: 10, // 最多记录10步

        // 记录一次导航
        record: function (from, to, sheet) {
            // 跳过轮询URL的记录
            var recordSkipList = ['notice-getnew', 'update_since','forum-gettagcatelist'];
            for (var i = 0; i < recordSkipList.length; i++) {
                if (to.includes(recordSkipList[i])) {
                    console.log('轮询URL，跳过记录:', to);
                    return;
                }
            }

            this.history.push({
                from: from,      // 来源页面，如 '/forum-1.htm'
                to: to,          // 目标页面，如 '/thread-123.htm'
                sheet: sheet,    // 'S2' 或 'S3'
                timestamp: Date.now()
            });

            // 保持历史长度
            if (this.history.length > this.maxHistory) {
                this.history.shift();
            }

            console.log('导航记录:', this.history.slice(-3)); // 显示最近3条
        },

        // 判断是否应该关闭S3
        shouldCloseS3: function (currentUrl) {
            if (this.history.length < 2) return true;

            var last = this.history[this.history.length - 1];  // 当前
            var prev = this.history[this.history.length - 2];  // 上一步

            // 情况1：S3 → S3（不关）
            if (last.sheet === 'S3' && prev.sheet === 'S3') {
                console.log('S3→S3 同层级切换，不关S3');
                return false;
            }

            // 情况2：S3 → S2（要关）
            if (last.sheet === 'S2' && prev.sheet === 'S3') {
                console.log('S3→S2 返回列表，关S3');
                return true;
            }

            // 其他情况默认关
            return true;
        },

        // 处理popstate
        onPopState: function () {
            // 获取当前URL
            var currentUrl = window.location.href;
            console.log('PopState事件，当前URL:', currentUrl);

            // 检查S3是否可见
            var s3 = document.querySelector('#S3-Wrapper.user-chat-show');
            var s3Visible = !!s3;
            console.log('S3可见状态:', s3Visible);

            // 检查是否是S3内部导航
            var isInternalNav = sessionStorage.getItem('aether_s3_internal_nav') === 'true';
            if (isInternalNav) {
                console.log('S3内部导航，不关S3');
                sessionStorage.removeItem('aether_s3_internal_nav');
                // 移除当前记录（因为我们要回退了）
                if (this.history.length > 0) {
                    this.history.pop();
                }
                return;
            }

            // 分析当前URL，判断是否是S3页面
            var isCurrentS3Page = false;

            // 检查是否是帖子详情（S3）
            if (currentUrl.includes('thread-')) {
                isCurrentS3Page = true;
            }
            // 检查是否是用户帖子列表（S3）
            else if (currentUrl.includes('my-thread') || currentUrl.includes('user-thread')) {
                isCurrentS3Page = true;
            }
            // 检查是否是消息详情（S3）
            else if (currentUrl.includes('notice-')) {
                isCurrentS3Page = true;
            }

            console.log('当前页面是否是S3:', isCurrentS3Page);

            // 核心逻辑：如果S3可见但当前页面不是S3页面，则关闭S3
            if (s3Visible && !isCurrentS3Page) {
                console.log('从S3返回S2页面，关闭S3');
                Aether.hideS3();
            } else if (s3Visible && isCurrentS3Page) {
                console.log('S3内部导航，不关S3');
            }

            // 处理历史记录
            if (this.history.length > 0) {
                var current = this.history.pop();
                console.log('移除历史记录:', current);
            }

            console.log('导航回退:', {
                currentUrl: currentUrl,
                s3Visible: s3Visible,
                isCurrentS3Page: isCurrentS3Page,
                remainingHistory: this.history.length
            });
        }
    },
    // 判断是否移动设备
    isMobile: function () {
        return window.matchMedia('(max-width: 991px)').matches;
    },

    // 移动端：显示 S3（全屏覆盖）
    showS3: function () {
        console.info('showS3 Preformed');
        var s3Wrapper = document.querySelector('#S3-Wrapper');
        if (s3Wrapper) {
            s3Wrapper.classList.add('user-chat-show');
        }
    },

    // 移动端：隐藏 S3
    hideS3: function () {
        console.info('hideS3 Preformed');
        var s3Wrapper = document.querySelector('#S3-Wrapper');
        if (s3Wrapper) {
            s3Wrapper.classList.remove('user-chat-show');
        }
    },

    // PC端：切换 S2 的全宽模式
    toggleS3: function () {
        console.info('toggleS3 Preformed');
        var S2 = document.getElementById('S2-Wrapper');
        if (S2) {
            S2.classList.toggle('s2-full-width');
        }
    },

    // 组合技：打开帖子详情
    openInS3: function (use_template = 'genetic') {
        console.info('openInS3 Preformed');
        var S2 = document.getElementById('S2-Wrapper');
        if (S2 && S2.classList.contains('s2-full-width')) {
            S2.classList.remove('s2-full-width');
        }
        var skeleton = document.getElementById('aether_skeleton__' + use_template);
        if (!skeleton) {
            console.error('未找到模板：' + use_template);
            document.getElementById('S3-Body').innerHTML = '';
        } else {
            document.getElementById('S3-Body').innerHTML = skeleton.innerHTML;
        }
        // 给一点延迟确保CSS变化完成
        setTimeout(function () { Aether.showS3(); }, 10);

        var currentUrl = window.location.href;
        var isFromS3 = document.querySelector('#S3-Wrapper.user-chat-show');

        if (isFromS3) {
            // 正在S3内切换帖子，特殊处理
            sessionStorage.setItem('aether_s3_internal_nav', 'true');
        }

    },

    // 显示 S4（全屏覆盖）
    showS4: function () {
        console.info('showS4 Preformed');
        var s4 = document.querySelector('#S4');
        if (s4) {
            s4.classList.add('show');
        }
    },

    // 隐藏 S4
    hideS4: function () {
        console.info('hideS4 Preformed');
        var s4 = document.querySelector('#S4');
        if (s4) {
            s4.classList.remove('show');
        }
    },

    showS6: function () {
        console.info('showS6 Preformed');
        // 添加调用栈跟踪
        var stack = new Error().stack;
        if (stack) {
            var stackLines = stack.split('\n');
            // 跳过前两行（当前函数和 Error 构造函数）
            var callerLine = stackLines[2];
            if (callerLine) {
                console.info('调用者:', callerLine.trim());
            }
        }
        var s6 = document.querySelector('#S6');
        if (s6) {
            s6.classList.add('show');
        }
    },
    hideS6: function () {
        console.info('hideS6 Preformed');
        var s6 = document.querySelector('#S6');
        if (s6) {
            s6.classList.remove('show');
            // 清空S6-Body内容，防止脚本在页面跳转时被重新执行
            var s6Body = document.querySelector('#S6-Body');
            if (s6Body) {
                s6Body.innerHTML = '';
            }
        }
    },

    // 从用户资料返回并查看其发帖
    navigateToUserThreadsS2: function () {
        Aether.hideS4();

        if (Aether.isMobile()) {
            // 移动端：同时关闭 S3
            Aether.hideS3();
        }
        // PC端：保持 S3 区域不变，只更新 S2 内容
    },

    // 切换 S5 回复区域的最大化/最小化
    toggleReplyMaximizeS5: function () {
        var s5Modal = document.querySelector('#S5_post_reply_modal');
        if (s5Modal) {
            s5Modal.classList.toggle('maximize');
        }
    },

    // ==================== 主题管理 ====================

    // 保存主题设置到localStorage
    saveTheme: function (theme) {
        try {
            localStorage.setItem('aether_preferred_theme', theme);
        } catch (e) {
            console.warn('无法保存主题设置到localStorage:', e);
        }
    },

    // 从localStorage加载主题设置
    loadTheme: function () {
        try {
            // 优先使用新的key，如果不存在则使用旧的
            var savedTheme = localStorage.getItem('aether_preferred_theme');
            if (savedTheme) {
                this.applyTheme(savedTheme, false);
            } else {
                // 默认使用light模式
                this.applyTheme('light', false);
            }
        } catch (e) {
            console.warn('无法从localStorage加载主题设置:', e);
        }
    },

    // 应用主题（包含TinyMCE同步）
    applyTheme: function (theme, save = true) {
        var body = document.querySelector('body');
        body.setAttribute('data-bs-theme', theme);

        if (save) {
            this.saveTheme(theme);
        }

        // 同步更新所有 TinyMCE 编辑器主题
        this.updateTinyMCETheme(theme);

        // 更新主题切换按钮的提示
        this.updateThemeButtonTooltip(theme);
    },

    // 设置主色调
    setPrimaryColor: function (color_hex = '#000000', save = true) {
        try {
            if (save) {
                localStorage.setItem('aether_primary_color', color_hex);
            }

            // 生成并应用MD3颜色CSS变量
            if (typeof generateMd3CssVariables === 'function') {
                const cssVariables = generateMd3CssVariables(color_hex);
                localStorage.setItem('aether_md3_colors_css', cssVariables);

                // 应用CSS变量
                if (parseInt(localStorage.getItem('aether_use_custom_color')) === 1) {
                    this.applyMd3CssVariables(cssVariables);
                }
            }
        } catch (e) {
            console.warn('无法设置主色调:', e);
        }
    },

    // 应用MD3颜色CSS变量
    applyMd3CssVariables: function (cssVariables) {
        var style = document.getElementById('aether-md3-colors');
        if (style) {
            style.textContent = cssVariables;
        }
    },

    // 设置字号
    setFontSize: function (fontSize_px = 16, save = true) {
        try {
            if (save) {
                localStorage.setItem('aether_font_size', fontSize_px);
            }

            // 改一个变量，控制全局
            document.body.style.setProperty('--bs-body-font-size', fontSize_px + 'px');
            document.documentElement.style.setProperty('--bs-body-font-size', fontSize_px + 'px');
        } catch (e) {
            console.warn('无法设置字号:', e);
        }
    },

    // 加载主色调设置
    loadPrimaryColor: function () {
        try {
            var savedColor = localStorage.getItem('aether_primary_color');
            var savedCssVariables = localStorage.getItem('aether_md3_colors_css');
            var useCustomColor = localStorage.getItem('aether_use_custom_color');

            if (savedCssVariables && parseInt(useCustomColor) === 1) {
                this.applyMd3CssVariables(savedCssVariables);
            } else if (savedColor && parseInt(useCustomColor) === 1) {
                this.setPrimaryColor(savedColor, false);
            }
        } catch (e) {
            console.warn('无法加载主色调设置:', e);
        }
    },

    // 加载字号设置
    loadFontSize: function () {
        try {
            var savedFontSize = localStorage.getItem('aether_font_size');
            if (savedFontSize) {
                this.setFontSize(savedFontSize, false);
            }
        } catch (e) {
            console.warn('无法加载字号设置:', e);
        }
    },

    // 切换主题（两模式循环：light -> dark -> light）
    toggleTheme: function () {
        var body = document.querySelector('body');
        var currentTheme = body.getAttribute('data-bs-theme') || 'light';
        var themes = ['light', 'dark'];

        var currentIndex = themes.indexOf(currentTheme);
        if (currentIndex === -1) currentIndex = 0;

        var nextIndex = (currentIndex + 1) % themes.length;
        var nextTheme = themes[nextIndex];

        this.applyTheme(nextTheme, true);
        return nextTheme;
    },

    // 更新主题切换按钮的提示文本
    updateThemeButtonTooltip: function (theme) {
        var themeToggleBtn = document.querySelector('[data-theme-toggle]');
        if (!themeToggleBtn) return;

        var themeNames = {
            'light': '浅色模式',
            'dark': '深色模式'
        };

        var displayTheme = themeNames[theme] || '浅色模式';
        themeToggleBtn.title = `当前主题: ${displayTheme} (点击切换)`;
        themeToggleBtn.setAttribute('aria-label', `切换主题，当前为${displayTheme}`);
    },

    // ==================== TinyMCE 主题管理 ====================

    // 主题颜色方案配置
    tinyMCEThemes: {
        'dark': {
            bgColor: '#1e1e1e',
            textColor: '#f0f0f0',
        },
        'light': {
            bgColor: '#ffffff',
            textColor: '#000000',
        }
    },

    // 更新所有 TinyMCE 编辑器主题
    updateTinyMCETheme: function (theme) {
        if (typeof tinymce === 'undefined' || !tinymce.editors) {
            console.info('本页面里 TinyMCE 未加载，没问题');
            return;
        }

        // 获取当前所有激活的 TinyMCE 编辑器实例
        var editors = tinymce.editors;

        // 如果没有编辑器，但 tinymce 对象存在，可能编辑器还未初始化完成
        if (editors.length === 0) {
            // 延迟重试
            setTimeout(() => {
                var retryEditors = tinymce.editors;
                if (retryEditors.length > 0) {
                    this.updateTinyMCETheme(theme);
                }
            }, 300);
            return;
        }

        // 获取颜色方案
        var colorSchemes = this.tinyMCEThemes;
        var colors = colorSchemes[theme] || colorSchemes.light;

        // 遍历所有编辑器实例并更新
        for (var i = 0; i < editors.length; i++) {
            var editor = editors[i];
            this.applyThemeToEditor(editor, theme, colors);
        }
    },

    // 单个编辑器应用主题的辅助函数
    applyThemeToEditor: function (editor, theme, colors) {
        if (!colors) {
            var colorSchemes = this.tinyMCEThemes;
            colors = colorSchemes[theme] || colorSchemes.light;
        }

        if (editor.initialized) {
            var body = editor.getBody();
            if (body) {
                // 直接设置 iframe 内 body 的样式
                body.style.backgroundColor = colors.bgColor;
                body.style.color = colors.textColor;

                // 清除旧的主题类，添加新的
                body.className = body.className.replace(/(^|\s)(theme-light|theme-dark)(\s|$)/g, ' ');
                body.classList.add('theme-' + theme);
            }
        }
    },

    // 设置 TinyMCE 主题监听器
    setupTinyMCEThemeListener: function () {
        // 方法1：监听 TinyMCE 编辑器添加事件
        if (typeof tinymce !== 'undefined') {
            // 保存原始的 addEditor 方法
            var originalAddEditor = tinymce.addEditor;

            // 重写 addEditor 方法，在编辑器添加时应用主题
            tinymce.addEditor = function (id, editor) {
                var result = originalAddEditor.apply(this, arguments);

                // 编辑器初始化后应用当前主题
                if (editor && editor.on) {
                    editor.on('init', function () {
                        // 短暂延迟确保 iframe 完全加载
                        setTimeout(() => {
                            var currentTheme = document.documentElement.getAttribute('data-bs-theme') || 'light';
                            Aether.applyThemeToEditor(editor, currentTheme);
                        }, 100);
                    });
                }

                return result;
            };

            // 同时，为已存在的编辑器也添加监听
            setTimeout(() => {
                var currentTheme = document.documentElement.getAttribute('data-bs-theme') || 'light';
                Aether.updateTinyMCETheme(currentTheme);
            }, 500);
        }
    },

    applyPrettyPrint: function () {
        const preElements = document.querySelectorAll('.message pre:not(.prettyprint)');
        preElements.forEach(pre => {
            pre.classList.add('prettyprint');
        });

        if (typeof prettyPrint === 'function') {
            prettyPrint();
        }
    },

    // ==================== Offcanvas 抑制功能 ====================

    // 初始化 Offcanvas 抑制功能
    initOffcanvasSuppression: function () {
        var myOffcanvas = document.querySelector('#S5_post_reply_modal');
        if (!myOffcanvas) return;

        // 当窗口大小变化的时候，这代表了手机端用户打开了虚拟键盘，不能触发Bootstrap自己的隐藏offcanvas
        window.addEventListener('resize', function (event) {
            if (myOffcanvas.classList.contains('show')) {
                const viewportHeight = window.innerHeight;
                const screenHeight = window.screen.height;
                const keyboardThreshold = screenHeight * 0.6;

                if (viewportHeight < keyboardThreshold) {
                    event.stopImmediatePropagation();
                }
            }
        }, true);

        // 监听点击事件，判断是否点击了 iframe
        myOffcanvas.addEventListener('click', function (event) {
            if (event.target.tagName === 'IFRAME' && myOffcanvas.contains(event.target)) {
                // 阻止事件冒泡，防止触发 offcanvas 的关闭逻辑
                event.stopPropagation();
            }
        });

        // 当用户点击了myOffcanvas里面的任何iframe时候，这代表了手机端用户点击了TinyMCE编辑器的编辑器，不能触发Bootstrap自己的隐藏offcanvas
        // 当用户点击了关闭按钮或背景覆盖层时，必须能隐藏offcanvas
        myOffcanvas.addEventListener('hide.bs.offcanvas', function (event) {
            // 检查是否有点击事件目标
            var target = event.relatedTarget;

            // 检查是否点击了关闭按钮或背景覆盖层
            if (target) {
                // 检查是否点击了关闭按钮
                if (target.classList.contains('close')) {
                    // 允许关闭操作
                    return;
                }

                // 检查是否点击了背景覆盖层
                if (target.classList.contains('offcanvas-manual-backdrop')) {
                    // 允许关闭操作
                    return;
                }

                // 检查是否点击了包含 data-bs-dismiss="offcanvas" 的元素
                if (target.hasAttribute('data-bs-dismiss') && target.getAttribute('data-bs-dismiss') === 'offcanvas') {
                    // 允许关闭操作
                    return;
                }
            } else {
                // 如果没有 relatedTarget，说明是通过 JavaScript 手动触发的关闭
                // 允许关闭操作
                return;
            }

            // 检查是否有焦点在 iframe 上（特别是 TinyMCE 编辑器）
            var activeElement = document.activeElement;
            if (activeElement && activeElement.tagName === 'IFRAME') {
                if (myOffcanvas.contains(activeElement)) {
                    // 阻止关闭操作
                    event.preventDefault();
                    return;
                }
            }

            // 检查是否有 TinyMCE 编辑器实例且处于焦点状态
            if (typeof tinymce !== 'undefined' && tinymce.editors) {
                // 特别检查 ID 为 message 的 TinyMCE 编辑器
                var messageEditor = tinymce.editors.message;
                if (messageEditor && messageEditor.iframeElement && myOffcanvas.contains(messageEditor.iframeElement)) {
                    try {
                        var iframeDoc = messageEditor.getDoc();
                        if (iframeDoc && iframeDoc.activeElement) {
                            // 阻止关闭操作
                            event.preventDefault();
                            return;
                        }
                    } catch (e) {
                        // 忽略 iframe 访问错误
                    }
                }

                // 检查所有其他 TinyMCE 编辑器实例
                for (var i = 0; i < tinymce.editors.length; i++) {
                    var editor = tinymce.editors[i];
                    if (editor && editor.iframeElement && myOffcanvas.contains(editor.iframeElement)) {
                        try {
                            var iframeDoc = editor.getDoc();
                            if (iframeDoc && iframeDoc.activeElement) {
                                // 阻止关闭操作
                                event.preventDefault();
                                return;
                            }
                        } catch (e) {
                            // 忽略 iframe 访问错误
                        }
                    }
                }
            }
        });
    },

    // ==================== 初始化 ====================

    init: function () {
        // 防止重复初始化
        if (this._initialized) return;
        this._initialized = true;

        // 加载保存的主题设置
        this.loadTheme();

        // 加载保存的主色调设置
        this.loadPrimaryColor();

        // 加载保存的字号设置
        this.loadFontSize();

        // 设置TinyMCE主题监听
        this.setupTinyMCEThemeListener();

        // 绑定主题切换按钮事件
        this.bindThemeToggle();

        // ===== 新增：初始化帖子引用功能 =====
        this.initThreadQuote();

        // ===== 新增：初始化Bootstrap组件 =====
        this.initBootstrapComponents();

        // ===== 新增：初始化Offcanvas抑制功能 =====
        this.initOffcanvasSuppression();

        // HTMX 加载状态
        if (typeof htmx !== 'undefined') {
            document.body.addEventListener('htmx:beforeRequest', function (e) {
                var target = e.detail.target;
                if (target) {
                    target.classList.add('htmx-loading');
                }
            });

            document.body.addEventListener('htmx:afterRequest', function (e) {
                var target = e.detail.target;
                if (target) {
                    target.classList.remove('htmx-loading');
                }
            });
        }

        // ESC键关闭S3/S4
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                // 检查S4是否显示（最顶层）
                var s4 = document.querySelector('#S4');
                if (s4 && s4.classList.contains('show')) {
                    // 关闭S4（最顶层）
                    Aether.hideS4();
                    return;
                }

                // 检查S3是否显示（中间层）
                var s3Wrapper = document.querySelector('#S3-Wrapper');
                if (s3Wrapper && s3Wrapper.classList.contains('user-chat-show')) {
                    // 关闭S3（中间层）
                    if (Aether.isMobile()) {
                        // 移动端：直接隐藏S3
                        Aether.hideS3();
                    } else {
                        // PC端：切换S2的全宽模式来隐藏S3
                        Aether.toggleS3();
                    }
                    return;
                }
            }
        });

        // 注册popstate事件监听器
        window.addEventListener('popstate', Aether.navigation.onPopState.bind(Aether.navigation));

        // =====
        if (typeof htmx !== 'undefined') {
            document.body.addEventListener('htmx:beforeRequest', function (e) {
                var target = e.detail.target || e.detail.requestConfig.target;
                var url = e.detail.path || e.detail.requestConfig.path;

                // 判断目标sheet
                var targetSheet = 'S2'; // 默认

                // 检查目标是否是S3相关内容
                if (target) {
                    // 情况1：直接目标是S3-Body
                    if (target === '#S3-Body' || target === 'S3-Body') {
                        targetSheet = 'S3';
                    }
                    // 情况2：目标在S3-Body内
                    else if (target.closest && target.closest('#S3-Body')) {
                        targetSheet = 'S3';
                    }
                }

                // 情况3：URL包含thread-，通常是帖子详情（S3）
                if (url && url.includes('thread-')) {
                    targetSheet = 'S3';
                }

                // 情况4：URL包含my-thread或user-thread，通常是用户帖子列表（S3）
                if (url && (url.includes('my-thread') || url.includes('user-thread'))) {
                    targetSheet = 'S3';
                }

                // 情况5：URL包含my-notice相关的消息详情（S3）
                if (url && url.includes('notice-')) {
                    targetSheet = 'S3';
                }

                // 记录当前页面状态
                var currentSheet = document.querySelector('#S3-Wrapper.user-chat-show') ? 'S3' : 'S2';
                var currentUrl = window.location.href;

                Aether.navigation.record(currentUrl, url, targetSheet);
                console.log('HTMX导航:', { target, url, targetSheet, currentSheet });
            });
        }
        // =====

        // 初始化涟漪效果
        this.initRippleEffect();

        // HTMX 动态内容加载后重新初始化涟漪效果
        if (typeof htmx !== 'undefined') {
            document.body.addEventListener('htmx:afterRequest', function (e) {
                Aether.initRippleEffect();
                // 重新初始化Offcanvas抑制功能，以确保新添加的元素也能被处理
                Aether.initOffcanvasSuppression();
            });
        }

    },

    // 初始化涟漪效果
    initRippleEffect: function () {
        // 检查用户是否启用了减少动画偏好
        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        // 检查 mdc 对象是否存在
        if (typeof mdc === 'undefined' || typeof mdc.ripple === 'undefined') {
            console.warn('MDC Ripple 未加载，无法初始化涟漪效果');
            return;
        }

        const rippleSelectors = 'a.btn,button.btn,.nav-link,.list-group-item,.icon-link,.dropdown-item,.ripple-surface';
        const rippleElements = Array.prototype.slice.call(document.querySelectorAll(rippleSelectors));

        rippleElements.forEach(element => {
            // 排除在#S3-Header和#S3-Footer中的元素
            if (element.closest('#S3-Header') || element.closest('#S3-Footer')) {
                return; // 跳过这些元素
            }

            // 确保元素有 mdc-ripple-surface 类
            if (!element.classList.contains('mdc-ripple-surface')) {
                element.classList.add('mdc-ripple-surface');
            }

            // 初始化涟漪效果
            try {
                // 如果已经初始化过，先销毁
                if (element._mdcRipple) {
                    element._mdcRipple.destroy();
                }
                // 创建新的 MDCRipple 实例并存储到元素上
                element._mdcRipple = new mdc.ripple.MDCRipple(element);
            } catch (error) {
                console.error('初始化涟漪效果失败:', error);
            }
        });
    },

    // 绑定主题切换按钮事件
    bindThemeToggle: function () {
        var themeToggleBtn = document.querySelector('[data-theme-toggle]');
        var lightDarkBtn = document.querySelectorAll('.light-dark-mode');

        // 优先使用 data-theme-toggle 属性的按钮
        if (themeToggleBtn) {
            themeToggleBtn.addEventListener('click', function () {
                Aether.toggleTheme();
            });
        }

        // 兼容旧版的 .light-dark-mode 按钮
        if (lightDarkBtn && lightDarkBtn.length > 0) {
            for (var i = 0; i < lightDarkBtn.length; i++) {
                lightDarkBtn[i].addEventListener('click', function () {
                    Aether.toggleTheme();
                });
            }
        }
    },

    initThreadQuote: function () {
        // 实际上不需要“初始化”，而是注册委托监听器（只做一次）
        if (this._threadQuoteBound) return;
        this._threadQuoteBound = true;

        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.post_reply');
            if (!btn) return;
            e.preventDefault();
            Aether.handlePostReplyClick(btn);
        });
    },

    handlePostReplyClick: function (me) {
        // 把你原来的点击逻辑放这里（注意：me 是 .post_reply 元素）
        const tid = me.dataset.tid;
        const pid = me.dataset.pid;
        const myDad = me.closest('.reply-item');
        const myGrandpa = myDad ? myDad.closest('.postlist') : null;
        const advancedReplyBtn = document.querySelector('#advanced_reply');
        const messageInput = document.querySelector('#message');
        const quickReplyForm = document.querySelector('#quick_reply_form');
        const quickReplyDummyInput = document.querySelector('#S3_quick_reply_dummy_input');

        if (!myDad || !myGrandpa || !advancedReplyBtn || !messageInput || !quickReplyForm) {
            console.error('找不到必要的元素');
            return;
        }

        if (myDad.classList.contains('quote')) {
            myDad.classList.remove('quote');
            quickReplyForm.classList.remove('quote');
            if (quickReplyDummyInput) {
                quickReplyDummyInput.textContent = '写下你的想法...';
            }
            const quotePidInput = quickReplyForm.querySelector('input[name="quotepid"]');
            if (quotePidInput) quotePidInput.value = '0';
            advancedReplyBtn.href = xn.url('post-create-' + tid);
        } else {
            myGrandpa.querySelectorAll('.reply-item').forEach(reply => {
                reply.classList.remove('quote');
            });

            myDad.classList.add('quote');
            quickReplyForm.classList.add('quote');
            if (quickReplyDummyInput) {
                quickReplyDummyInput.textContent = '正在引用...点击此处继续回复';
            }
            const quotePidInput = quickReplyForm.querySelector('input[name="quotepid"]');
            if (quotePidInput) quotePidInput.value = pid;

            advancedReplyBtn.href = xn.url('post-create-' + tid + '-0-' + pid);

            const offcanvasElement = document.getElementById('S5_post_reply_modal');
            if (offcanvasElement) {
                const offcanvas = new bootstrap.Offcanvas(offcanvasElement);
                offcanvas.show();
            }
        }

        messageInput.focus();
    },
    // ===== 新增：初始化Bootstrap组件 =====
    initBootstrapComponents: function () {
        // 初始化所有popover
        var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
        });

        // 初始化所有tooltip
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        console.info('Bootstrap组件初始化完成：', {
            popovers: popoverList.length,
            tooltips: tooltipList.length
        });
    },
}
// 全局可用，但不覆盖已存在的
if (!window.Aether) {
    window.Aether = Aether;
}
// 确保初始化
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
        Aether.init();
    });
} else {
    Aether.init();
}



/*=============================================
=                   HTMX MAGIC                   =
=============================================*/

// 等待 HTMX 加载完成，并在 DOM Ready 时手动启动
document.addEventListener('DOMContentLoaded', function () {
    if (window.htmx) {
        // HTMX 已同步加载
    } else if (window.htmxLoaded) {
        // HTMX 异步加载完成，手动触发扫描
        htmx.process(document.body);
    } else {
        // 监听加载完成后再处理
        window.addEventListener('load', () => htmx.process(document.body));
    }
});

/**
 * 高性能的forEach实现（不包含thisArg参数以保持最佳性能）
 * 
 * 来源：https://www.zhihu.com/question/556786869/answer/2706658837
 * 测试表明：不包含thisArg参数的forEach实现可以达到与原生for循环相近的性能
 * 
 * @template T
 * @param {Array<T>} array - 要遍历的数组
 * @param {(item: T, index: number, array: Array<T>) => void} callbackFn - 对每个元素执行的函数
 * @returns {void}
 */
function myForEach(array, callbackFn) {
    const length = array.length;
    for (let i = 0; i < length; i++) {
        callbackFn(array[i], i, array);
    }
}

/**
 * 智能设置活跃元素（支持单 key 或 key 数组）
 * 
 * 通过 data-active 属性来激活匹配的元素，并自动清除范围内其他活跃元素
 * 
 * @param {string|string[]} key - 要激活的 data-active 值（如 'my' 或 ['my', 'my-avatar']）
 * @param {string} [tag='*'] - 可选，限定元素标签（默认所有元素）
 * @param {string} [context='body'] - 可选，搜索范围（默认整个文档）
 * @example
 * // 单 key 示例 - 激活 data-active="menu" 的所有元素
 * setActive('menu');
 * 
 * // 多 key 示例 - 同时激活 data-active="user" 和 data-active="avatar" 的元素
 * setActive(['user', 'avatar'], '*', '#sidebar');
 * 
 * // 限定标签和范围示例 - 在 #nav 范围内激活所有 data-active="home" 的 li 元素
 * setActive('home', 'li', '#nav');
 */
function setActive(key, tag = '*', context = 'body') {
    const contexts = context.split(',').map(sel => sel.trim());
    myForEach(contexts, function (ctx) {
        let contextElements = document.querySelectorAll(ctx);
        if (contextElements.length === 0) {
            contextElements = [document];
        }

        myForEach(contextElements, function (contextElement) {
            const allActiveElements = contextElement.querySelectorAll(`${tag}[data-active]`);
            allActiveElements.forEach(element => {
                element.classList.remove('active');
            });
        });
    });

    const keys = Array.isArray(key) ? key : [key];
    myForEach(keys, function (k) {
        myForEach(contexts, function (ctx) {
            let contextElements = document.querySelectorAll(ctx);
            if (contextElements.length === 0) {
                contextElements = [document];
            }

            myForEach(contextElements, function (contextElement) {
                const targetElements = contextElement.querySelectorAll(`${tag}[data-active="${k}"]`);
                targetElements.forEach(element => {
                    element.classList.add('active');
                });
            });
        });
    });
}

/*============  End of HTMX MAGIC  =============*/