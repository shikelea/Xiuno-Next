<?php

$data = [
    'panels' => [
        // 全局设置面板
        'global' => [
            'title' => '全局设置',
            'description' => '主题的基础功能开关',
            'sections' => [
                'basic' => [
                    'title' => '基础功能',
                    'description' => '控制主题的核心功能',
                    'options' => [
                        /*                         'back_top' => [
                            'label' => '返回顶部功能',
                            'description' => '在页面右下角显示返回顶部按钮',
                            'type' => 'toggle',
                            'default' => true
                        ], */
                        'user_online' => [
                            'label' => '用户在线标识',
                            'description' => '在在线用户头像旁显示在线状态',
                            'type' => 'toggle',
                            'default' => true
                        ],
                        /*                         'user_info_card' => [
                            'label' => '用户信息卡片',
                            'description' => '鼠标悬停在用户头像上显示详细信息',
                            'type' => 'toggle',
                            'default' => true
                        ], */
                        'groupicon_display' => [
                            'label' => '用户组标识显示',
                            'description' => '显示用户所属用户组的文字图标',
                            'type' => 'toggle',
                            'default' => true
                        ],
                        /*                         'pic_lazyload' => [
                            'label' => '图片懒加载',
                            'description' => '延迟加载页面中的图片，提高页面加载速度',
                            'type' => 'toggle',
                            'default' => true
                        ] */
                    ]
                ],
                'device' => [
                    'title' => '设备适配',
                    'description' => '控制不同设备上的显示效果',
                    'options' => [
                        /*                         'mobile_first' => [
                            'label' => '移动端优先',
                            'description' => '优先优化移动端显示效果',
                            'type' => 'toggle',
                            'default' => true
                        ],
                        's2_full_width' => [
                            'label' => '默认全屏S2',
                            'description' => '在PC端默认显示全屏S2区域',
                            'type' => 'toggle',
                            'default' => false
                        ] */]
                ]
            ]
        ],

        // 外观设置面板
        'appearance' => [
            'title' => '外观设置',
            'description' => '自定义主题的视觉效果',
            'sections' => [
                'color' => [
                    'title' => '颜色设置',
                    'description' => '调整主题的颜色方案',
                    'options' => [
                        'primary_color' => [
                            'label' => '主题主色',
                            'description' => '调整主题的主要颜色；<b>注意！在此处选择的颜色，在实际显示中可能会更淡，这是有意的设计，防止颜色与背景颜色过接近导致阅读困难</b>',
                            'type' => 'color',
                            'default' => '#66ccff'
                        ],
                        /*                         'secondary_color' => [
                            'label' => '主题辅色',
                            'description' => '调整主题的辅助颜色',
                            'type' => 'color',
                            'default' => '#ff6666'
                        ], */
                        /*                         'dark_mode' => [
                            'label' => '夜间模式',
                            'description' => '允许用户切换夜间模式',
                            'type' => 'toggle',
                            'default' => true
                        ],
                        'dark_mode_auto' => [
                            'label' => '自动夜间模式',
                            'description' => '根据时间自动切换夜间模式',
                            'type' => 'toggle',
                            'default' => false
                        ],
                        'dark_mode_time_start' => [
                            'label' => '夜间模式开始时间',
                            'description' => '自动夜间模式的开始时间',
                            'type' => 'number',
                            'default' => 19,
                            'min' => 0,
                            'max' => 23,
                            'step' => 1
                        ],
                        'dark_mode_time_end' => [
                            'label' => '夜间模式结束时间',
                            'description' => '自动夜间模式的结束时间',
                            'type' => 'number',
                            'default' => 7,
                            'min' => 0,
                            'max' => 23,
                            'step' => 1
                        ] */
                    ]
                ],
                'font' => [
                    'title' => '字体设置',
                    'description' => '调整主题的字体样式',
                    'options' => [
                        'font_size' => [
                            'label' => '字号',
                            'description' => '<div class="d-flex justify-content-between small text-muted mt-1"><span>小</span><span>·</span><span>默认</span><span>·</span><b>中等</b><span>·</span><span>大</span><span>·</span><span>更大</span><span>·</span><span>超大</span><span>·</span><span>最大</span></div>调整整体字体大小',
                            'type' => 'range',
                            'default' => 16,
                            'min' => 12,
                            'max' => 24,
                            'step' => 1
                        ]
                    ]
                ],
                'avatar' => [
                    'title' => '头像设置',
                    'description' => '调整用户头像的显示效果',
                    'options' => [
                        'avatar_shape' => [
                            'label' => '头像形状',
                            'description' => '选择用户头像的显示形状',
                            'type' => 'select',
                            'default' => 'circle',
                            'choices' => [
                                'circle' => '圆形',
                                'rectangle' => '矩形',
                                'squircle' => 'Squircle'
                            ]
                        ]
                    ]
                ]
            ]
        ],

        // 主页设置面板
        'homepage' => [
            'title' => '主页设置',
            'description' => '自定义主页的布局和内容',
            'sections' => [
                'layout' => [
                    'title' => '主页布局',
                    'description' => '调整主页的整体布局',
                    'options' => [
                        'homepage_style' => [
                            'label' => '主页风格',
                            'type' => 'select',
                            'default' => 'v1',
                            'choices' => [
                                'v1' => '社交圈子氛围',
                                'v2' => '门户网站氛围',
                            ],
                        ],
                        /*
                        'homepage_banner' => [
                            'label' => '显示轮播图',
                            'description' => '在主页顶部显示轮播图',
                            'type' => 'toggle',
                            'default' => true
                        ],
                        'homepage_banner_images' => [
                            'label' => '轮播图片',
                            'description' => '输入轮播图片的URL，每行一个',
                            'type' => 'textarea',
                            'default' => ''
                        ],
                        */
                        'homepage_recommend_forums' => [
                            'label' => '显示推荐板块',
                            'description' => '在主页显示推荐板块',
                            'type' => 'toggle',
                            'default' => true
                        ],
                        /*
                        'homepage_recommend_forums_ids' => [
                            'label' => '推荐板块ID',
                            'description' => '输入要推荐的板块ID，用逗号分隔',
                            'type' => 'text',
                            'default' => ''
                        ],
                        */
                        'homepage_announcement' => [
                            'label' => '显示社区公告',
                            'description' => '在主页显示社区公告',
                            'type' => 'toggle',
                            'default' => true
                        ]
                    ]
                ],
                'content' => [
                    'title' => '主页内容',
                    'description' => '调整主页的内容展示',
                    'options' => []
                ]
            ]
        ],

        // 帖子列表设置面板
        'threadlist' => [
            'title' => '帖子列表设置',
            'description' => '调整帖子列表的显示效果',
            'sections' => [
                'style' => [
                    'title' => '列表样式',
                    'description' => '选择帖子列表的展示样式',
                    'options' => [
                        'thread_list_style' => [
                            'label' => '帖子列表样式',
                            'description' => '选择帖子列表的展示样式',
                            'type' => 'select',
                            'default' => 'classic_v1',
                            'choices' => [
                                'classic_v1' => '经典样式',
                                'sns_v1' => '朋友圈样式',
                                'sns_v2' => '微博样式',
                                'masonry_v1' => '瀑布流样式',
                                'news_v1' => '新闻样式',
                                'news_v2' => '精选样式（图在左）',
                                'news_v3' => '部落样式',
                                'image_v1' => '大图样式（图在上）',
                            ]
                        ],
                        'thread_forum_name' => [
                            'label' => '显示板块名称',
                            'description' => '在帖子列表中显示板块名称',
                            'type' => 'toggle',
                            'default' => true
                        ],
                        'thread_last_reply' => [
                            'label' => '显示最后回复',
                            'description' => '在帖子列表中显示最后回复信息',
                            'type' => 'toggle',
                            'default' => true
                        ],
                        'new_thread_indicator' => [
                            'label' => '新帖标识',
                            'description' => '在新发布的帖子上显示新帖标识',
                            'type' => 'toggle',
                            'default' => true
                        ]
                    ]
                ],
                'summary' => [
                    'title' => '帖子摘要',
                    'description' => '调整帖子摘要的显示效果',
                    'options' => [
                        'thread_summary' => [
                            'label' => '显示帖子摘要',
                            'description' => '在帖子列表中显示帖子摘要；该选项还会影响“显示摘要图片”功能',
                            'type' => 'toggle',
                            'default' => true
                        ],
                        'thread_summary_words' => [
                            'label' => '摘要字数',
                            'description' => '调整帖子摘要的字数',
                            'type' => 'number',
                            'default' => 100,
                            'min' => 0,
                            'max' => 500,
                            'step' => 10
                        ],
                        'thread_summary_images' => [
                            'label' => '显示摘要图片',
                            'description' => '在帖子摘要中显示图片',
                            'type' => 'toggle',
                            'default' => true
                        ],
                        'thread_summary_images_count' => [
                            'label' => '摘要图片数量',
                            'description' => '调整帖子摘要中显示的<b>最多</b>图片数量，用于朋友圈样式，其他样式可能最多一到三张图片；若希望完全不显示图片，请将上一项设置为“否”',
                            'type' => 'select',
                            'default' => '3',
                            'choices' => [
                                '1' => '1张',
                                '3' => '3张',
                                '6' => '6张',
                                '9' => '9张'
                            ]
                        ]
                    ]
                ]
            ]
        ],

        // 帖子详情设置面板
        'thread' => [
            'title' => '帖子详情设置',
            'description' => '调整帖子详情页的显示效果',
            'sections' => [
                'basic' => [
                    'title' => '基本设置',
                    'description' => '调整帖子详情页的基本设置',
                    'options' => [
                        /*                         'thread_navigation' => [
                            'label' => '显示路径导航',
                            'description' => '在帖子详情页显示路径导航',
                            'type' => 'toggle',
                            'default' => true
                        ], */
                        /*                         'thread_author_info' => [
                            'label' => '显示作者信息',
                            'description' => '在帖子详情页显示作者详细信息',
                            'type' => 'toggle',
                            'default' => true
                        ], */
                        'thread_post_floor' => [
                            'label' => '显示楼层',
                            'description' => '在回帖中显示楼层信息',
                            'type' => 'toggle',
                            'default' => true
                        ],
                        'thread_pre_next' => [
                            'label' => '显示上一篇/下一篇',
                            'description' => '在帖子详情页显示上一篇/下一篇帖子',
                            'type' => 'toggle',
                            'default' => true
                        ]
                    ]
                ],
                'interaction' => [
                    'title' => '交互设置',
                    'description' => '调整帖子详情页的交互效果',
                    'options' => [/*
                        'thread_reply_refresh' => [
                            'label' => '回复后刷新',
                            'description' => '用户回复后自动刷新页面',
                            'type' => 'toggle',
                            'default' => true
                        ],
                        'thread_like_button' => [
                            'label' => '显示点赞按钮',
                            'description' => '在帖子详情页显示点赞按钮',
                            'type' => 'toggle',
                            'default' => true
                        ],
                        'thread_share_button' => [
                            'label' => '显示分享按钮',
                            'description' => '在帖子详情页显示分享按钮',
                            'type' => 'toggle',
                            'default' => true
                        ]*/
                    ]
                ]
            ]
        ],

        // 导航设置面板
        'navigation' => [
            'title' => '导航设置',
            'description' => '调整主题的导航效果',
            'sections' => [
                'bottom' => [
                    'title' => '底部导航',
                    'description' => '调整底部导航栏的设置',
                    /*                     'options' => [
                        'bottom_nav' => [
                            'label' => '显示底部导航',
                            'description' => '在移动端显示底部导航栏',
                            'type' => 'toggle',
                            'default' => true
                        ],
                        'bottom_nav_items' => [
                            'label' => '导航项设置',
                            'description' => '自定义底部导航项，格式：图标,名称,链接，每行一个',
                            'type' => 'textarea',
                            'default' => ''
                        ],
                        'bottom_nav_active_color' => [
                            'label' => '激活状态颜色',
                            'description' => '调整底部导航项激活状态的颜色',
                            'type' => 'color',
                            'default' => '#66ccff'
                        ]
                    ] */
                ],
                'side' => [
                    'title' => '侧边导航',
                    'description' => '调整侧边导航的设置',
                    'options' => [
                        /*                         'side_nav' => [
                            'label' => '显示侧边导航',
                            'description' => '在PC端显示侧边导航',
                            'type' => 'toggle',
                            'default' => true
                        ],
                        'side_nav_style' => [
                            'label' => '侧边导航样式',
                            'description' => '选择侧边导航的样式',
                            'type' => 'select',
                            'default' => 'fixed',
                            'choices' => [
                                'fixed' => '固定定位',
                                'scroll' => '滚动跟随'
                            ]
                        ] */]
                ]
            ]
        ],

        // 用户体验设置面板
        'ux' => [
            'title' => '用户体验设置',
            'description' => '调整主题的用户体验效果',
            'sections' => [
                'loading' => [
                    'title' => '加载效果',
                    'description' => '调整页面的加载效果',
                    'options' => [
                        'page_loading' => [
                            'label' => '显示页面加载动画',
                            'description' => '在页面加载时显示动画效果',
                            'type' => 'toggle',
                            'default' => true
                        ],
                        /*                         'image_loading' => [
                            'label' => '显示图片加载占位',
                            'description' => '在图片加载时显示占位效果',
                            'type' => 'toggle',
                            'default' => true
                        ] */
                    ]
                ],
                'feedback' => [
                    'title' => '交互反馈',
                    'description' => '调整用户交互的反馈效果',
                    'options' => [
                        /*                         'tap_feedback' => [
                            'label' => '点击反馈效果',
                            'description' => '在用户点击时显示反馈效果',
                            'type' => 'toggle',
                            'default' => true
                        ],
                        'scroll_feedback' => [
                            'label' => '滚动反馈效果',
                            'description' => '在用户滚动时显示反馈效果',
                            'type' => 'toggle',
                            'default' => false
                        ] */]
                ]
            ]
        ],

        'page' => [
            'title' => '页面文字内容设置',
            'sections' => [
                'user_login' => [
                    'title' => '登录',
                    '_group' => 'G6',
                    'options' => [
                        'title' => [
                            'label' => '页面标题',
                            'type' => 'text',
                            'default' => '欢迎回来',
                        ],
                        'subtitle' => [
                            'label' => '页面副标题',
                            'type' => 'text',
                            'default' => '你不在的日子里，我们攒了好多新帖子，登录后参与讨论'
                        ],
                        'image' => [
                            'label' => '主题图片',
                            'description' => '显示在表单的另一侧；不填写则不显示',
                            'type' => 'text',
                            'default' => './plugin/abs_theme_aether/view/img/看板娘-春日.png',
                        ],
                        'background_image' => [
                            'label' => '背景图片',
                            'description' => '显示在表单的另一侧，作为主题图片的底纹；不填写则不显示',
                            'type' => 'text',
                            'default' => './plugin/abs_theme_aether/view/img/auth-bg.png',
                        ],
                    ],
                ],
                'user_register' => [
                    'title' => '注册',
                    '_group' => 'G6',
                    'options' => [
                        'title' => [
                            'label' => '页面标题',
                            'type' => 'text',
                            'default' => '注册账号',
                        ],
                        'subtitle' => [
                            'label' => '页面副标题',
                            'type' => 'text',
                            'default' => '30秒后，你就是这里的一员了'
                        ],
                        'image' => [
                            'label' => '主题图片',
                            'description' => ' 显示在表单的另一侧',
                            'type' => 'text',
                            'default' => './plugin/abs_theme_aether/view/img/看板娘-春日.png',
                        ],
                        'background_image' => [
                            'label' => '背景图片',
                            'type' => 'text',
                            'default' => './plugin/abs_theme_aether/view/img/auth-bg.png',
                        ],
                    ],
                ],
                'user_forgetpw' => [
                    'title' => '忘记密码',
                    '_group' => 'G6',
                    'options' => [
                        'title' => [
                            'label' => '页面标题',
                            'type' => 'text',
                            'default' => '忘记密码',
                        ],
                        'image' => [
                            'label' => '主题图片',
                            'description' => ' 显示在表单的另一侧',
                            'type' => 'text',
                            'default' => './plugin/abs_theme_aether/view/img/看板娘-春日.png',
                        ],
                        'background_image' => [
                            'label' => '背景图片',
                            'type' => 'text',
                            'default' => './plugin/abs_theme_aether/view/img/auth-bg.png',
                        ],
                    ],
                ],
                'password_requirements' => [
                    'title' => '密码要求',
                    '_group' => 'G6',
                    'description' => '将会在“注册”、“忘记密码”、个人中心的“密码”界面中展示。',
                    'options' => [
                        'show' => [
                            'label' => '显示该部分？',
                            'type' => 'toggle',
                            'default' => true,
                        ],
                        'title' => [
                            'label' => '标题',
                            'type' => 'text',
                            'default' => '一个靠谱的密码应该是：',
                        ],
                        'content' => [
                            'label' => '内容',
                            'type' => 'textarea',
                            'default' => '至少 8 个字符，越长越安心' . PHP_EOL . '至少 1 个大写字母' . PHP_EOL . '至少 1 个数字' . PHP_EOL . '加个符号？那就更难被猜到啦',
                        ],
                    ],
                ],
                'about' => [
                    'title' => '关于本站',
                    '_group' => 'G6',
                    'description' => '<a href="../' . url('about_us') . '">进入页面</a>；因程序限制，暂无法实现“任意页面创建、编辑、删除”。若不希望某个页面对外展示，仅需清空内容并去掉链接即可。',
                    'options' => [
                        'content' => [
                            'label' => '页面内容',
                            'type' => 'html',
                            '<h2>关于 ' . $conf['sitename'] . '</h2>' . "\n" .
                                 '<p style="font-size: 1.2rem;">这里是一个由兴趣驱动的小社区。</p>' . "\n" .
                                 '<p>我们聊技术、聊生活、聊那些让人眼睛发亮的东西。</p>' . "\n" .
                                 '<p>建站于 ' . date('Y-m-d') . '，目前还在慢慢生长。</p>' . "\n" .
                                 '<p>—— 如果你愿意，这里也是你的角落。</p>',
                            //'description' => '若希望整个页面使用短代码，请点击“源代码”按钮，然后粘贴内容，最后点击“保存”。',
                        ],/*
                        'use_shortcode' => [
                            'label' => '解析短代码？',
                            'description' => '一般情况下不需要修改这个选项。',
                            'type' => 'toggle',
                            'default' => false,
                        ],*/
                    ],
                ],
                'terms' => [
                    'title' => '网站规则/使用协议/版权声明',
                    '_group' => 'G6',
                    'description' => '<a href="../' . url('terms') . '">进入页面</a>',
                    'options' => [

                        'title' => [
                            'label' => '页面标题',
                            'type' => 'text',
                            'default' => '网站规则',
                        ],

                        'content' => [
                            'label' => '页面内容',
                            'type' => 'html',
                            'default' => '<h2>一言以蔽之</h2>' . 
                            '<p>礼貌待人，别当个混蛋。</p>' . "\n" .
                            '<h3>互相尊重</h3>'  .
                            '<p>不攻击人，不给人贴标签。</p>'  .
                            '<h3>不灌水、不刷屏</h3>'  .
                            '<p>认真说话，比说很多话更重要。</p>'  .
                            '<h3>不违法、不涉政</h3>'  .
                            '<p>这是底线，也是我们还能继续聊下去的前提。</p>'  .
                            '<hr>'  .
                            '<p class="text-muted">以上内容由站长设定，' . $conf['sitename'] . ' 保留最终解释权。</p>',
                            //'description' => '若希望整个页面使用短代码，请点击“源代码”按钮，然后粘贴内容，最后点击“保存”。',
                        ],/*
                        'use_shortcode' => [
                            'label' => '解析短代码？',
                            'description' => '一般情况下不需要修改这个选项。',
                            'type' => 'toggle',
                            'default' => false,
                        ],*/
                    ],
                ],
                'privacy' => [
                    'title' => '隐私政策',
                    '_group' => 'G6',
                    'description' => '<a href="../' . url('privacy') . '">进入页面</a>',
                    'options' => [

                        'title' => [
                            'label' => '页面标题',
                            'type' => 'text',
                            'default' => '隐私政策',
                        ],

                        'content' => [
                            'label' => '页面内容',
                            'type' => 'html',
                            'default' => '<h3>我们收集什么</h3>' . "\n" .
                            '<p>账号信息、发帖记录、操作日志——正常社区该有的都有。</p>' . "\n" .
                            '<h3>我们不会做什么</h3>' . "\n" .
                            '<p>不出售数据，不偷偷追踪，不往你邮箱塞垃圾。</p>' . "\n" .
                            '<h3>你有权离开</h3>' . "\n" .
                            '<p>随时可以注销账号，数据会按规定删除。</p>' . "\n" .
                            '<p>本内容为暂定规范。若您看到了这段话，请联系站长让他/她重写本页内容。</p>',
                            //'description' => '若希望整个页面使用短代码，请点击“源代码”按钮，然后粘贴内容，最后点击“保存”。',
                        ],/*
                        'use_shortcode' => [
                            'label' => '解析短代码？',
                            'description' => '一般情况下不需要修改这个选项。',
                            'type' => 'toggle',
                            'default' => false,
                        ],*/
                    ],
                ],
                'contact' => [
                    'title' => '联系我们',
                    '_group' => 'G6',
                    'description' => '<a href="../' . url('contact_us') . '">进入页面</a>',
                    'options' => [

                        'title' => [
                            'label' => '页面标题',
                            'type' => 'text',
                            'default' => '联系我们',
                        ],

                        'content' => [
                            'label' => '页面内容',
                            'type' => 'html',
                            'default' => '<h3>网站问题、举报投诉、建议意见</h3><ul><li>Email：</li><li>QQ群：</li></ul><p>或者到建议以及意见反馈专区发帖。</p><h3>商务合作</h3><ul><li>联系人：</li><li>Email：</li><li>QQ：</li></ul><p>若您看到了这段话，请联系站长补充。</p>',
                            //'description' => '若希望整个页面使用短代码，请点击“源代码”按钮，然后粘贴内容，最后点击“保存”。',
                        ],/*
                        'use_shortcode' => [
                            'label' => '解析短代码？',
                            'description' => '一般情况下不需要修改这个选项。',
                            'type' => 'toggle',
                            'default' => false,
                        ],*/
                    ],
                ],
            ],
        ],

        // 高级设置面板
        'advanced' => [
            'title' => '高级设置',
            'description' => '调整主题的高级功能',
            'sections' => [
                'performance' => [
                    'title' => '性能优化',
                    'description' => '调整主题的性能优化设置',
                    'options' => [
                        'minify_css' => [
                            'label' => '压缩CSS',
                            'description' => '启用CSS压缩以提高加载速度',
                            'type' => 'toggle',
                            'default' => true
                        ],
                        'minify_js' => [
                            'label' => '压缩JS',
                            'description' => '启用JS压缩以提高加载速度',
                            'type' => 'toggle',
                            'default' => true
                        ],
                        /*                         'cache_template' => [
                            'label' => '缓存模板',
                            'description' => '启用模板缓存以提高加载速度',
                            'type' => 'toggle',
                            'default' => true
                        ] */
                    ]
                ],
                'custom' => [
                    'title' => '自定义代码',
                    'description' => '给懂代码的人准备的工具箱',
                    'options' => [
                        'custom_css' => [
                            'label' => '自定义CSS',
                            'description' => '写点CSS，让社区长成你想要的样子',
                            'type' => 'textarea_html',
                            'default' => ''
                        ],
                        'custom_js' => [
                            'label' => '自定义JS',
                            'description' => '加点JavaScript小魔法;要带上script标签，将会在页面底部加载',
                            'type' => 'textarea_html',
                            'default' => ''
                        ],
                        'custom_head' => [
                            'label' => '自定义头部代码',
                            'description' => '在页面头部添加自定义代码（CSS或JS均可，要带上style或script标签）',
                            'type' => 'textarea_html',
                            'default' => ''
                        ]
                    ]
                ]
            ]
        ]
    ],
    'kumquat_config' => [
        'allow_delete_plugin_settings' => true,
        'allow_reset_settings' => true,
        'show_all_vars_table' => true
    ]
];

return $data;
