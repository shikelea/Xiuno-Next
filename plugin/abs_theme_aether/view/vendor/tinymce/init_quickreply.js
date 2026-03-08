// 这个是配置文件, 请参考备注，问题反馈请到www.huux.cc
tinymce.init({
    selector: '#message',
    content_css: 'plugin/abs_theme_aether/view/vendor/tinymce/style.css', // 编辑内容区附加css文件
    language_url: 'plugin/abs_theme_aether/view/vendor/tinymce/langs/zh_CN.js', // 本地化中文语言包

    language: 'zh_CN', // 默认语言简体中文
    menubar: false, // 隐藏菜单栏，显示请设置为true
    statusbar: false, // 显示状态栏，隐藏请设置为false
    resize: true, // 仅允许改变高度
    toolbar_mode: 'floating', // 工具栏抽屉模式 取值：floating / sliding / scrolling / wrap
    toolbar_sticky: false, // 停靠工具栏到顶部
    branding: false, // 隐藏标示，防止误点
    min_height: 256, // 最小高度
    draggable_modal: true, // 模态框允许拖动（主要针对后续插件应用）
    image_uploadtab: false, // 不展示默认的上传标签，用xiunoimgup就可以，支持多文件/单文件上传。--powerpaste启用后会导致粘贴时卡顿，这和计算有关，好处是可以同时粘贴文字和图片。
    plugins: ['autolink', 'autoresize', 'charmap', 'code', 'codesample', 'emoticons', 'fullscreen', 'help', 'hr', 'image', 'link', 'lists', 'media', 'paste', 'quickbars', 'textpattern', 'wordcount', Object.keys(t_external_plugins)], // 加载的插件，-为禁用
    toolbar: ['bold italic link blockquote emoticons other | ' + t_external_toolbar.join(' ')],
    toolbar_groups: { //按钮分组，节省空间，方便使用
        other: {
            icon: 'more-drawer',
            tooltip: '更多按钮',
            items: 'strikethrough bullist numlist | image media removeformat | fullscreen'
        }
    },
    paste_data_images: true, // 粘贴图片必须开启
    quickbars_selection_toolbar: false, // pc端可禁用快速工具栏填写false
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
    
    // 下面为2.0版本后加配置项
    mobile: {
        toolbar_sticky: false // 固定工具栏到顶部
    },
    extended_valid_elements: 'span[style|class],b,i', // 保留span/b/i标签
    paste_remove_styles_if_webkit: false, // 禁用webkit粘贴过滤器，保留style样式，如果不想保留可选择后点击【清除样式】
    // forced_root_block : '', // 去掉换行自动加P（可以确保非块元素包含在块元素中），改为使用br换行
    // skin: 'oxide-dark',  // 设置深色皮肤，默认为oxide
    // cache_suffix: '?v=1.0.3',// 缓存css/js url自动添加后缀
});