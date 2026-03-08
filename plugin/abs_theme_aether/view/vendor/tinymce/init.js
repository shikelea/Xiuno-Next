// 这个是配置文件, 请参考备注，问题反馈请到www.huux.cc
console.log(t_external_toolbar.join(' '));
tinymce.init({
    selector: '#message',
    content_css: 'plugin/abs_theme_aether/view/vendor/tinymce/style.css', // 编辑内容区附加css文件
    language_url: 'plugin/abs_theme_aether/view/vendor/tinymce/langs/zh_CN.js', // 本地化中文语言包
    language: 'zh_CN', // 默认语言简体中文
    menubar: false, // 隐藏菜单栏，显示请设置为true
    statusbar: true, // 显示状态栏，隐藏请设置为false
    resize: true, // 仅允许改变高度
    toolbar_mode: 'floating', // 工具栏抽屉模式 取值：floating / sliding / scrolling / wrap
    toolbar_sticky: true, // 停靠工具栏到顶部
    branding: false, // 隐藏标示，防止误点
    min_height: 500, // 最小高度
    draggable_modal: true, // 模态框允许拖动（主要针对后续插件应用）
    image_uploadtab: false, // 不展示默认的上传标签，用xiunoimgup就可以，支持多文件/单文件上传。--powerpaste启用后会导致粘贴时卡顿，这和计算有关，好处是可以同时粘贴文字和图片。
    plugins: ['autolink', 'autoresize', 'charmap', 'code', '-directionality', 'emoticons', 'fullscreen', 'help', 'hr', 'image', '-insertdatetime', 'link', 'lists', 'media', 'paste', '-powerpaste', '-preview', 'quickbars', 'table', 'textpattern', '-visualblocks', '-visualchars', 'xiunoimgup', 'wordcount', Object.keys(t_external_plugins)], // 加载的插件，-为禁用

    toolbar: ['formatselect bold italic strikethrough link bullist numlist blockquote aligncenter alignright | emoticons | imgup removeformat fontcolor other | ' + t_external_toolbar.join(' ') + ' | fullscreen'],
    toolbar_groups: { //按钮分组，节省空间，方便使用
        imgup: {
            icon: 'gallery',
            tooltip: '上传图片',
            items: 'xiunoimgup image media'
        },
        indentation: {
            icon: 'indent',
            tooltip: '缩进',
            items: 'indent outdent'
        },
        fontcolor: {
            icon: 'color-levels',
            tooltip: '文字颜色',
            items: 'forecolor backcolor'
        },
        other: {
            icon: 'more-drawer',
            tooltip: '更多按钮',
            items: 'superscript subscript table hr charmap | undo redo | code help'
        }
    },

    textpattern_patterns: [
        /* 类似WordPress的快捷格式（Markdown风格） */
        { start: '*', end: '*', format: 'italic' },
        { start: '**', end: '**', format: 'bold' },
        { start: '~~', end: '~~', format: 'strikethrough' },
        { start: '#', format: 'h1' },
        { start: '##', format: 'h2' },
        { start: '###', format: 'h3' },
        { start: '####', format: 'h4' },
        { start: '#####', format: 'h5' },
        { start: '######', format: 'h6' },
        { start: '> ', format: 'blockquote' },
        { start: '1. ', cmd: 'InsertOrderedList' },
        { start: '* ', cmd: 'InsertUnorderedList' },
        { start: '- ', cmd: 'InsertUnorderedList' },
        { start: '· ', cmd: 'InsertUnorderedList' },
        { start: '-----', replacement: '<hr>' },
        { start: '————', replacement: '<hr>' },
        /* 简单的表情替换 */
        { start: '（笑）', replacement: '🙂' },
        { start: '（笑哭）', replacement: '😂' },
        { start: 'ROFL', replacement: '🤣' },
        { start: '（哭）', replacement: '😭' },
        { start: '（半恼）', replacement: '😠' },
        { start: '（恼）', replacement: '😡' },
        { start: '（怒）', replacement: '💢' },
        { start: '（尴尬）', replacement: '😓' },
        { start: '（无语）', replacement: '🤦' },
    ],

    fontsize_formats: '0.8em 1em 1.2em 1.4em 1.6em 1.8em 2em 2.4em 2.8em',
    paste_data_images: true, // 粘贴图片必须开启
    quickbars_selection_toolbar: 'bold italic link', // pc端可禁用快速工具栏填写false
    quickbars_insert_toolbar: false, // pc端禁用回车工具栏
    media_live_embeds: true, // 让媒体编辑时可观看（但实际测试中无用）
    contextmenu: false, // 禁用编辑器的右键菜单@c
    external_plugins: t_external_plugins, // 附加插件
    images_upload_handler: function (blobInfo, success, failure) {
        // 此方法来自xiuno.js，图片粘贴上传使用
        xn.upload_file(blobInfo.blob(), xn.url('attach-create'), {
            is_image: 1
        }, function (code, json) {
            if (code == 0) {
                success(json.url);
            } else {
                $.alert(json);
            }
        });
    },
    setup: function (editor) {
        const addShortcut = (key, desc, cmd) => {
            editor.shortcuts.add(key, desc, typeof cmd === 'function' ? cmd : () => editor.execCommand(cmd));
        };

        // 标题快捷键 Ctrl/Cmd + 1~6 和 Alt+Shift+1~6
        for (let i = 1; i <= 6; i++) {
            addShortcut('Meta+' + i, 'Insert H' + i + ' heading', () => editor.formatter.apply('h' + i));
            addShortcut('Alt+Shift+' + i, 'Insert H' + i + ' heading', () => editor.formatter.apply('h' + i));
        }

        // 地址标签 <address>
        addShortcut('Alt+Shift+9', 'Insert Address Tag', () => editor.formatter.apply('address'));

        // 对齐方式
        addShortcut('Alt+Shift+l', 'Left Align', 'JustifyLeft');
        addShortcut('Alt+Shift+r', 'Right Align', 'JustifyRight');
        addShortcut('Alt+Shift+c', 'Center Align', 'JustifyCenter');
        addShortcut('Alt+Shift+j', 'Justify Align', 'JustifyFull');

        // 删除线
        addShortcut('Alt+Shift+d', 'Strikethrough', 'Strikethrough');

        // 列表
        addShortcut('Alt+Shift+u', 'Unordered List', 'InsertUnorderedList');
        addShortcut('Alt+Shift+o', 'Ordered List', 'InsertOrderedList');

        // 插入/移除链接
        addShortcut('Alt+Shift+a', 'Insert Link', 'mceLink');
        addShortcut('Alt+Shift+s', 'Remove Link', 'unlink');

        // 引用
        addShortcut('Alt+Shift+q', 'Blockquote', 'mceBlockQuote');

        // 图片
        addShortcut('Alt+Shift+m', 'Insert Image', 'mceImage');

        // More 标签（自定义）
        addShortcut('Alt+Shift+t', 'Insert <!--more-->', () => editor.insertContent('<!--more-->'));

        // 分页标签（pagebreak）
        addShortcut('Alt+Shift+p', 'Insert Page Break', 'mcePageBreak');

        // 全屏模式
        addShortcut('Alt+Shift+w', 'Fullscreen Mode', 'mceFullScreen');

        // 帮助
        addShortcut('Alt+Shift+h', 'Open Help Dialog', 'mceHelp');

        // 拼写检查（模拟）
        addShortcut('Alt+Shift+n', 'Spell Check', () => alert('拼写检查功能未启用'));

        // 监听内容变化，实时保存到 textarea
        editor.on('change', function () {
            editor.save();
        });

        // 监听键盘输入
        editor.on('keyup', function () {
            editor.save();
        });

        // 失去焦点时保存
        editor.on('blur', function () {
            editor.save();
        });

        // 设置定时自动保存（每2秒）
        setInterval(function () {
            if (editor.initialized) {
                editor.save();
            }
        }, 2000);
    },
    // 下面为2.0版本后加配置项
    mobile: {
        toolbar_sticky: true, // 固定工具栏到顶部
        toolbar_mode: 'scrolling',
        toolbar: ['formatselect bold italic forecolor link bullist blockquote xiunoimgup image media | emoticons spoiler hide_login hide_reply | removeformat undo redo other fullscreen', t_external_toolbar.join(' ')],
    },
    paste_remove_spans: true,
    paste_remove_styles: true,
    extended_valid_elements: 'span[style|class],b,i', // 保留span/b/i标签
    paste_remove_styles_if_webkit: true, // 禁用webkit粘贴过滤器，保留style样式，如果不想保留可选择后点击【清除样式】
});