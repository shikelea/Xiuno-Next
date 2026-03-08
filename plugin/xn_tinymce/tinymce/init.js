//粘贴图片，采用官方6.0系列新函数
const paste_image_upload_handler = (blobInfo, progress) => new Promise((resolve, reject) => {
    // 此方法来自xiuno.js，图片粘贴上传使用
       xn.upload_file(blobInfo.blob(), xn.url('attach-create'), {
            is_image: 1
        }, function(code, json) {
            if (code == 0) {
                resolve(json.url);
            } else {
               reject({ message: 'Error: ' + json });
            }
        });
        
});

// 这个是配置文件, 整体结构参考大白编辑器
tinymce.init({
    selector: 'textarea#message',
    content_css: 'plugin/xn_tinymce/tinymce/style.css', // 编辑内容区附加css文件
    language_url: 'plugin/xn_tinymce/tinymce/langs/zh-Hans.js', // 本地化中文语言包
    language: 'zh-Hans', // 默认语言简体中文
    menubar: false, // 菜单栏，隐藏请设置为false
    statusbar: true, // 状态栏，隐藏请设置为false
    resize: true, // 仅允许改变高度
    toolbar_mode: 'floating', // 工具栏抽屉模式 取值：floating / sliding / scrolling / wrap
    toolbar_sticky: true, // 停靠工具栏到顶部
    branding: false, // 隐藏标示，防止误点
    min_height: 500, // 最小高度
    draggable_modal: true, // 模态框允许拖动（主要针对后续插件应用）
    image_uploadtab: false, // 不展示默认的上传标签，用xiunoimgup就可以，支持多文件/单文件上传。--powerpaste启用后会导致粘贴时卡顿，这和计算有关，好处是可以同时粘贴文字和图片。
    autosave_ask_before_unload: true,//如果用户在编辑器中有未保存的更改，则自动保存插件会向用户发出警告
    autosave_interval: '20s', //自动保存间隔时间
    autosave_retention: '360m', //自动保存内容应保留在本地存储中的持续时间
    autosave_restore_when_empty: true, //编辑器打开如果是空的（新建帖子），自动恢复草稿
    images_file_types: 'jpeg,jpg,png,gif,bmp,webp', //允许拖放的文件
    plugins: ['advlist', 'anchor', 'autolink','autosave', 'autoresize', 'charmap', 'code', 'codesample', 'xiunoimgup','-directionality', 'fullscreen', 'help', 'image', 'importcss','insertdatetime', 'link', 'lists', 'media', 'nonbreaking','pagebreak', 'preview', 'quickbars', 'save', 'searchreplace', 'table', '-template',  '-visualblocks', '-visualchars', 'wordcount'], // 加载的插件，-为禁用
    menu: {
        file: { title: 'File', items: 'restoredraft | preview | export print | deleteallconversations' },
        edit: { title: 'Edit', items: 'undo redo | cut copy paste pastetext | selectall | searchreplace' },
        view: { title: 'View', items: 'code | visualaid visualchars visualblocks | spellchecker | preview fullscreen | showcomments' },
        insert: { title: 'Insert', items: 'xiunoimgup | image link media addcomment pageembed  codesample inserttable | charmap emoticons hr | pagebreak nonbreaking anchor tableofcontents | insertdatetime' },
        format: { title: 'Format', items: 'bold italic underline strikethrough superscript subscript codeformat | styles blocks fontfamily fontsize align lineheight | forecolor backcolor | language | removeformat' },
        tools: { title: 'Tools', items: 'spellchecker spellcheckerlanguage | a11ycheck code wordcount' },
        table: { title: 'Table', items: 'inserttable | cell row column | advtablesort | tableprops deletetable' },
        help: { title: 'Help', items: 'help' }
    },
    toolbar: [' code formatting fontcolor removeformat  alignment blockquote indentation list  imgup link media codesample table  anchor hr toc preview  fullscreen restoredraft  help', t_external_toolbar.join(' ')], // 界面按钮
    // toolbar: ['fontfamily code | undo redo  | formatting fontcolor removeformat | alignment blockquote indentation list | imgup link media codesample table | anchor hr toc preview | fullscreen restoredraft | other', t_external_toolbar.join(' ')], // 界面按钮 备份
    toolbar_groups: { //按钮分组，节省空间，方便使用
        formatting: {
            icon: 'format',
            tooltip: '格式化',
            items: 'formatselect | fontselect | fontsizeselect | bold italic underline strikethrough | superscript subscript'
        },
        alignment: {
            icon: 'align-left',
            tooltip: '对齐',
            items: 'alignleft aligncenter alignright alignjustify'
        },
        imgup: {
            icon: 'gallery',
            tooltip: '上传图片',
            items: 'xiunoimgup | image'
        },
        list: {
            icon: 'unordered-list',
            tooltip: '列表',
            items: 'bullist numlist'
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
            items: 'charmap -insertdatetime help'
        }
    },
    fontsize_formats: '12px 14px 16px 18px 24px 36px 48px 56px 72px',
    font_family_formats: '微软雅黑=Microsoft YaHei,Helvetica Neue,PingFang SC,sans-serif;苹果苹方=PingFang SC,Microsoft YaHei,sans-serif;宋体=simsun,serif;仿宋体=FangSong,serif;黑体=SimHei,sans-serif;Arial=arial,helvetica,sans-serif;Arial Black=arial black,avant garde;Book Antiqua=book antiqua,palatino;Comic Sans MS=comic sans ms,sans-serif;Courier New=courier new,courier;Georgia=georgia,palatino;Helvetica=helvetica;Impact=impact,chicago;Symbol=symbol;Tahoma=tahoma,arial,helvetica,sans-serif;Terminal=terminal,monaco;Times New Roman=times new roman,times;Verdana=verdana,geneva;Webdings=webdings;Wingdings=wingdings,zapf dingbats;知乎配置=BlinkMacSystemFont, Helvetica Neue, PingFang SC, Microsoft YaHei, Source Han Sans SC, Noto Sans CJK SC, WenQuanYi Micro Hei, sans-serif;小米配置=Helvetica Neue,Helvetica,Arial,Microsoft Yahei,Hiragino Sans GB,Heiti SC,WenQuanYi Micro Hei,sans-serif',
    paste_data_images: true, // 粘贴图片必须开启
    indentation: '2em', // padding方式的缩进，没text-indent好用，但这个不需要插件
    quickbars_selection_toolbar: 'bold italic | link | H1 H2 H3 | blockquote', // pc端可禁用快速工具栏填写false
    quickbars_insert_toolbar: false, // pc端禁用回车工具栏
    media_live_embeds: true, // 让媒体编辑时可观看（但实际测试中无用）
    contextmenu: false, // 禁用编辑器的右键菜单@c
    external_plugins: t_external_plugins, // 附加插件
    images_upload_handler: paste_image_upload_handler,
    mobile: {
        toolbar_sticky: true, // 固定工具栏到顶部
        toolbar_mode: 'Wrap',
        toolbar: ['code | undo redo | removeformat | blockquote | xiunoimgup link media codesample table | anchor hr toc preview  | fullscreen restoredraft'], // 手机界面按钮
    },
    convert_fonts_to_spans: false, // 不强制font转换为span
    extended_valid_elements: 'span[style|class],b,i', // 保留span/b/i标签
    paste_remove_styles_if_webkit: false, // 禁用webkit粘贴过滤器，保留style样式，如果不想保留可选择后点击【清除样式】
    // forced_root_block : '', // 去掉换行自动加P（可以确保非块元素包含在块元素中），改为使用br换行
    // cache_suffix: '?v=1.0.3',// 缓存css/js url自动添加后缀
});

