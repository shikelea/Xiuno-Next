(function() {
    'use strict';
    
    var PluginManager = tinymce.PluginManager;
    
    PluginManager.add('customPluginDemo', function(editor) {
        // 1. 添加折叠内容按钮
        editor.ui.registry.addButton('foldcontent', {
            icon: 'collapse',
            tooltip: '折叠内容',
            onAction: function() {
                editor.windowManager.open({
                    title: '插入折叠内容',
                    body: {
                        type: 'panel',
                        items: [
                            {
                                type: 'input',
                                name: 'title',
                                label: '折叠标题',
                                value: '点击展开'
                            },
                            {
                                type: 'textarea',
                                name: 'content',
                                label: '折叠内容',
                                placeholder: '输入要折叠的内容...'
                            }
                        ]
                    },
                    buttons: [
                        { type: 'cancel', text: '取消' },
                        { 
                            type: 'submit', 
                            text: '插入',
                            primary: true
                        }
                    ],
                    onSubmit: function(api) {
                        var data = api.getData();
                        editor.insertContent(
                            '<details open class="foldable" style="border:1px solid #ddd;margin:10px 0;border-radius:4px;">' +
                            '<summary>' + 
                            data.title + '</summary>' +
                            '<div>' + data.content + '</div>' +
                            '</details><p>&nbsp;</p>'
                        );
                        api.close();
                    }
                });
            }
        });
        
        // 3. 添加更多自定义按钮（示例）
        editor.ui.registry.addButton('spoiler', {
            icon: 'eye',
            tooltip: '隐藏内容（反白）',
            onAction: function() {
                editor.insertContent('<span class="spoiler" style="background:#333;color:#333;">隐藏内容</span>');
            }
        });
        
        // 4. 添加快捷键支持
        editor.addShortcut('Alt+Shift+F', '插入折叠内容', function() {
            editor.execCommand('mceInsertContent', false, 
                '<details open class="foldable" style="border:1px solid #ddd;margin:10px 0;border-radius:4px;">' +
                '<summary>点击展开</summary>' +
                '<div>隐藏内容</div>' +
                '</details><p>&nbsp;</p>'
            );
        });
        
        // 5. 返回插件的公共API（可选）
        return {
            getMetadata: function() {
                return {
                    name: '自定义按钮功能演示插件 by Tillreetree',
                    url: 'https://xiunobbs.cn/'
                };
            }
        };
    });
})();