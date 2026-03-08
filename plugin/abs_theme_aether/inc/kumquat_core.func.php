<?php

/**
 * 金桔框架
 * Kumquat Framework
 * 作者：Tillreetree, Geticer
 */

//设置页面标题
$header['title'] = $PLUGIN_PROFILE['name'] . lang('setting');
$header['mobile_title'] = $PLUGIN_PROFILE['name'] . lang('setting');

/* 【开始】金桔框架——内置控件 */
// IDIOTS DON’T TOUCH WHAT YOU DON’T UNDERSTAND!!!!

/**
 * 文本标签
 *
 * @param string $text 文字内容
 * @param string $type 标签类型
 * @param string $class_add class
 * @param string $id_add ID
 */
function kumquat_control_label($text, $type = '', $class_add = '', $id_add = '') {
    $before = '<p';
    $after  = '</p>';
    switch ($type) {
        case 'h1':
            $before = '<h1';
            $after  = '</h1>';
            break;

        case 'h2':
            $before = '<h2';
            $after  = '</h2>';
            break;

        case 'h3':
            $before = '<h3';
            $after  = '</h3>';
            break;

        case 'h4':
            $before = '<h4';
            $after  = '</h4>';
            break;

        case 'h5':
            $before = '<h5';
            $after  = '</h5>';
            break;

        case 'h6':
            $before = '<h6';
            $after  = '</h6>';
            break;

        case 'small':
            $before = '<p class="help-block"';
            $after  = '</p>';
            break;

        case 'label':
            $before = '<label';
            $class_add .= ' col-md-2 form-control-label';
            $after  = '</label>';
            break;

        case 'code':
            $before = '<code';
            $after  = '</code>';
            break;

        case 'pre':
            $before = '<pre';
            $after  = '</pre>';
            break;

        case 'span':
            $before = '<span';
            $after  = '</span>';
            break;

        default:
            $before = '<p';
            $after  = '</p>';
            break;
    }
    return $before . ' id="' . $id_add . '" class="' . $class_add . '">' . $text . $after;
}

/**
 * 金桔框架——控制器适配
 * @param $controller_name 控制器的name
 * @param $args 参数
 * @param $settings 已有的设置项，用于获取值
 * @return string 对应的设置项
 */
function kumquat_controller_adapter($controller_name, array $args, $settings) {
    echo '<div class="col-md-10">';
    $type = $args['type'];

    //如果未定义默认值，则默认值为0（FALSE）
    //*
    if (!isset($args['default'])) {
        $args['default'] = 0;
    }
    //*/
    switch ($type) {
            //===== Xiuno BBS-内置控件 =====
        case 'text':
        case 'text_html':
            echo form_text($controller_name, kumquat_get_option_value($controller_name, $settings, $args['default']), FALSE, '');
            break;
        case 'textarea':
        case 'textarea_html':
            echo form_textarea($controller_name, kumquat_get_option_value($controller_name, $settings, $args['default']));
            break;
        case 'checkbox':
            echo form_checkbox($controller_name, kumquat_get_option_value($controller_name, $settings, $args['default']), $args['label']);
            break;
        case 'checkbox_multiple':
            echo form_checkbox_multiple($controller_name, $args['choices'], kumquat_get_option_value($controller_name, $settings, $args['default'])); //改良
            break;
        case 'radio-yes_no':
            echo form_radio_yes_no($controller_name, kumquat_get_option_value($controller_name, $settings, $args['default']));
            break;
        case 'radio':
            echo form_radio($controller_name, $args['choices'], kumquat_get_option_value($controller_name, $settings, $args['default']));
            break;
        case 'select':
            echo form_select(
                $controller_name,
                $args['choices'],
                kumquat_get_option_value($controller_name, $settings, $args['default'])
            );
            break;
        case 'select_multiple':
            echo form_select_multiple($controller_name, $args['choices'], kumquat_get_option_value($controller_name, $settings, $args['default']));
            break;
        case 'password':
            echo form_password($controller_name, kumquat_get_option_value($controller_name, $settings, $args['default']), FALSE);
            break;
        case 'hidden':
            echo form_hidden($controller_name, kumquat_get_option_value($controller_name, $settings, $args['default']));
            break;
            //===== 金桔框架-扩展控件 =====
        case 'toggle':
            echo form_radio_toggle_yes_no($controller_name, kumquat_get_option_value($controller_name, $settings, $args['default']));
            break;
        case 'radio-toggle':
            echo form_radio_toggle($controller_name, $args['choices'], kumquat_get_option_value($controller_name, $settings, $args['default']));
            break;
        case 'radio-image':
            echo form_radio_image($controller_name, $args['choices'], kumquat_get_option_value($controller_name, $settings, $args['default']));
            break;

        case 'email':
            echo form_custom_type($controller_name, kumquat_get_option_value($controller_name, $settings, $args['default']), FALSE, 'email');
            break;
        case 'url':
            echo form_custom_type($controller_name, kumquat_get_option_value($controller_name, $settings, $args['default']), FALSE, 'url');
            break;
        case 'number':
            echo form_number(
                $controller_name,
                kumquat_get_option_value($controller_name, $settings, $args['default']),
                FALSE,
                $args['min'],
                $args['max'],
                $args['step']
            );
            break;
        case 'range':
            echo form_range($controller_name, kumquat_get_option_value($controller_name, $settings, $args['default']), false, $args['min'], $args['max'], $args['step'], isset($args['show_label']) ? $args['show_label'] : false, isset($args['vertical']) ? $args['vertical'] : false);
            break;

        case 'html':
            echo form_textarea_html($controller_name, kumquat_get_option_value($controller_name, $settings, $args['default']), FALSE, isset($args['height']) ? $args['height'] : false);
            break;

        case 'date':
            echo form_custom_type($controller_name, kumquat_get_option_value($controller_name, $settings, $args['default']), FALSE, 'date');
            break;
        case 'time':
            echo form_custom_type($controller_name, kumquat_get_option_value($controller_name, $settings, $args['default']), FALSE, 'time');
            break;
        case 'week':
            echo form_custom_type($controller_name, kumquat_get_option_value($controller_name, $settings, $args['default']), FALSE, 'week');
            break;
        case 'datetime':
            echo form_custom_type($controller_name, kumquat_get_option_value($controller_name, $settings, $args['default']), FALSE, 'datetime-local');
            break;

        case 'color':
            echo form_color($controller_name, kumquat_get_option_value($controller_name, $settings, $args['default']), $args['default']);
            break;
        case 'color-select':
            echo form_color_select($controller_name, $args["choices"], kumquat_get_option_value($controller_name, $settings, $args['default']));
            break;
        case 'color_schemes':
            echo form_color_scheme_select($controller_name, $args["choices"], kumquat_get_option_value($controller_name, $settings, $args['default']));
            break;

        case 'array_text':
            echo form_incrementable_text($controller_name, $args['columns_label'], kumquat_get_option_value($controller_name, $settings, $args['default']));
            break;
        case 'matrix_text':
            echo form_incrementable_text_matrix($controller_name, $args['columns_label'], kumquat_get_option_value($controller_name, $settings, $args['default']));
            break;
            //===== 金桔框架-专用控件 =====
        case 'css_typography':
            echo form_css_typography($controller_name, kumquat_get_option_value($controller_name, $settings, $args['default']), isset($args) ? $args : array());
            break;
        case 'css_background_image':
            echo form_css_background_image($controller_name, kumquat_get_option_value($controller_name, $settings, $args['default']), isset($args) ? $args : array());
            break;
            //===== 金桔框架-Xiuno BBS专用控件 =====
        case 'forumlist':
            echo form_forumlist_single($controller_name, kumquat_get_option_value($controller_name, $settings, $args['default']));
            break;
        case 'forumlist_multi':
            echo form_forumlist_multi($controller_name, kumquat_get_option_value($controller_name, $settings, $args['default']));
            break;
        case 'forumlist_dropdown':
            echo form_forumlist_dropdown($controller_name, kumquat_get_option_value($controller_name, $settings, $args['default']));
            break;

        case 'text_per_forum':
            echo form_text_per_forum($controller_name, kumquat_get_option_value($controller_name, $settings, $args['default']));
            break;
        case 'number_per_forum':
            echo form_number_per_forum(
                $controller_name,
                kumquat_get_option_value($controller_name, $settings, $args['default']),
                FALSE,
                $args['min'],
                $args['max'],
                $args['step']
            );
            break;

        case 'usergroup':
            echo form_usergroup_single($controller_name, kumquat_get_option_value($controller_name, $settings, $args['default']));
            break;
        case 'usergroup_multi':
            echo form_usergroup_multi($controller_name, kumquat_get_option_value($controller_name, $settings, $args['default']));
            break;
            //===== 金桔框架-其他 =====
        case 'label': /* 特殊：文字标签 */
            echo kumquat_control_label($args["default"]);
            break;
            /*case 'image':
            echo "WIP";
            break;
            //*/
        default:
            echo lang('This control is not supported');
            break;
    }
    if (isset($args["description"]) && $args["type"] !== 'hidden') {
        echo kumquat_control_label($args["description"], 'small');
    }
    if (DEBUG == 1 || DEBUG == 2) {
        echo kumquat_control_label(lang('Use this when calling') . "：<br>kumquat_get_setting('" . PLUGIN_NAME . "', '" . $controller_name . "')<br>\$" . PLUGIN_NAME . "_setting['" . str_replace('/', "']['", $controller_name) . "']", 'code');

    }
    if (DEBUG == 2) {
        $controller_name_var = explode('/', $controller_name);
        echo ('<details><pre>' . end($controller_name_var) . ' => ');
        print_r($args);
        echo ('</pre></details>');
    }
    echo '</div>';
}

/* 【结束】金桔框架——内置控件 */

/**
 * 金桔框架——输出设置项
 * @param array $data 要导入的设置数组。
 * @param array $settings 已有的设置。
 * @return string 每一个设置项的HTML
 */
function kumquat_output_setting_frontend($data, $settings) {
    if (!isset($data)) {
        echo kumquat_control_label(lang('This plugin has no settings'), 'h1');
    } else {
        echo '<ul class="nav nav-pills kumquat-nav mb-2">';
        foreach ($data as $panel => $value) {
            $controller_name_panel = $panel;
            $active_add = '';
            if (!isset($value['title']) || empty($value['title'])) {
                $value['title'] = $panel;
            }

            echo '<li class="nav-item">';
            if ($value['title'] == reset($data)['title']) {
                $active_add = ' class="nav-link active"';
            } else {
                $active_add = ' class="nav-link"';
            }
            echo ('<a href="#' . $controller_name_panel . '" data-toggle="tab"' . $active_add . '>' . kumquat_control_label($value['title'], 'span'));
            if (DEBUG == 1 || DEBUG == 2) {
                echo kumquat_control_label($controller_name_panel, 'code');
            }
            echo '</a></li>';
        }
        echo '</ul>';
        echo '<div class="tab-content">';
        foreach ($data as $panel => $value) {
            $controller_name_panel = $panel;
            if ($value['title'] == reset($data)['title']) {
                echo '<section class="tab-pane active" id="' . $controller_name_panel . '">';
            } else {
                echo '<section class="tab-pane" id="' . $controller_name_panel . '">';
            }
            if (!isset($value['sections'])) {
                echo kumquat_control_label(lang('This panel has no settings'));
            } else {
                foreach ($value['sections'] as $section => $value) {
                    $controller_name_section = $controller_name_panel . '/' . $section;
                    if (!isset($value['title']) || empty($value['title'])) {
                        $value['title'] = $section;
                    }
                    echo kumquat_control_label($value['title'], 'h3', 'section-title');
                    if (DEBUG == 1 || DEBUG == 2) {
                        echo kumquat_control_label($controller_name_section, 'code');
                    }
                    if (isset($value['description'])) {
                        echo kumquat_control_label($value['description']);
                    }

                    if (!isset($value['options'])) {
                        echo kumquat_control_label(lang('This section has no settings'));
                    } else {
                        $option_col = 12;
                        if (!isset($value['_cols'])) {
                            $option_col_count = 1;
                        } else {
                            $option_col_count = $value['_cols'];
                        }
                        switch ($option_col_count) {
                            case 2:
                                $option_col = 6;
                                break;
                            case 3:
                                $option_col = 4;
                                break;
                            case 4:
                                $option_col = 3;
                                break;
                            default:
                                $option_col = 12;
                                break;
                        }
                        echo '<div class="row">';
                        foreach ($value['options'] as $option => $control) {
                            $controller_name = $controller_name_section . '/' . $option;
                            if (!isset($control['label']) || !isset($control['type'])) {
                                //do nothing
                            } else {
                                echo '<div class="col-lg-' . $option_col . '">';
                                echo '<article class="form-group row">';
                                if ($control['type'] !== 'hidden') {
                                    echo kumquat_control_label($control['label'], 'label');
                                }
                                kumquat_controller_adapter($controller_name, $control, $settings);
                                echo '</article>';
                                echo '</div>';
                            }
                        }
                        echo '</div>';
                    }
                }
            }
            echo '</section>';
        }
        echo '</div>';
    }
}

/**
 * 金桔框架——保存设置
 * @param array $data 新的设置项数据
 */
function kumquat_save_setting(array $data) {
    $setting = array();

    foreach ($data['panels'] as $panel => $value) {
        $controller_name_panel = $panel;
        if (!isset($value['sections'])) {
            continue;
        } else {
            foreach ($value['sections'] as $section => $value) {
                $controller_name_section = $controller_name_panel . '/' . $section;
                if (!empty($value['options'])) {
                    foreach ($value['options'] as $option => $control) {
                        $controller_name = $controller_name_section . '/' . $option;
                        // 对设置项进行校验
                        switch ($control['type']) {
                            case 'date':
                                $setting[$panel][$section][$option] = kumquat_sanitize_date(param($controller_name)); //日期统一
                                # $setting[$controller_name] = kumquat_sanitize_date(param($controller_name)); //日期统一
                                break;

                            case 'text':
                            case 'textarea':
                                $setting[$panel][$section][$option] = strip_tags(param($controller_name)); //文本框不允许有HTML内容，html文本框不过滤
                                #$setting[$controller_name] = strip_tags(param($controller_name)); //文本框不允许有HTML内容，html文本框不过滤
                                break;

                            case 'text_html':
                            case 'textarea_html':
                            case 'html':
                                $setting[$panel][$section][$option] = param($controller_name, ''); //允许有HTML的文本框
                                #$setting[$controller_name] = param($controller_name, ''); //允许有HTML的文本框
                                break;

                            case 'number':
                            case 'range':
                                $setting[$panel][$section][$option] = param($controller_name, 0); //数字框为int
                                #$setting[$controller_name] = param($controller_name, 0); //数字框为int
                                break;

                            case 'select_multiple':
                            case 'checkbox_multiple':
                            case 'forumlist_multi':
                            case 'usergroup_multi':
                                $setting[$panel][$section][$option] = param($controller_name, array()); //多选框为数组类型
                                #$setting[$controller_name] = param($controller_name, array()); //多选框为数组类型
                                break;

                            case 'css_typography':
                            case 'css_background_image':
                                $setting[$panel][$section][$option] = param($controller_name, array()); //CSS为数组类型
                                #$setting[$controller_name] = param($controller_name, array()); //CSS为数组类型
                                break;

                            case 'array_text':
                                $setting[$panel][$section][$option] = param($controller_name, array()); //数组文本框是数组类型
                                break;

                            case 'matrix_text':
                                $this_option = $_POST[$controller_name];
                                $this_option = array_chunk($this_option,count($control['columns_label']));
                                $setting[$panel][$section][$option] = $this_option; //矩阵文本框是二维数组类型
                                break;

                            case 'dropdown_per_forum':
                            case 'text_per_forum':
                            case 'number_per_forum':

                                $setting[$panel][$section][$option] = param($controller_name, array()); //“每个论坛板块使用一种输入框”为数组类型，键为板块ID，值为选项值
                                break;

                            case 'label':
                                //do nothing
                                break;

                            default:
                                //其他情况
                                $setting[$panel][$section][$option] = param($controller_name);
                                #$setting[$controller_name] = param($controller_name);
                                break;
                        }
                        //var_dump($controller_name."：".param($controller_name)."<br>");
                    }
                }
            }
        }
    };

    foreach ($data['kumquat_flag'] as $key => $value) {
        #$controller_name = 'kumquat_flag' . '/' . $key;
        $setting['kumquat_flag'][$key] = param($controller_name);
    }

    $setting['THIS_LOCATION'] = param('THIS_LOCATION', '');
    $setting['THIS_LOCATION_FRONTEND'] = param('THIS_LOCATION_FRONTEND', '');

    return $setting;
}

/**
 * 金桔框架——重置设置
 * @param array $data 设置项数据
 */
function kumquat_reset_setting(array $data) {
    $setting = array();
    foreach ($data['panels'] as $panel => $value) {
        #$controller_name_panel = $panel;
        if (!isset($value['sections'])) {
            continue;
        } else {
            foreach ($value['sections'] as $section => $value) {
                #$controller_name_section = $controller_name_panel . '/' . $section;
                if (!empty($value['options'])) {
                    foreach ($value['options'] as $option => $control) {
                        #$controller_name = $controller_name_section . '/' . $option;
                        if (isset($control['default'])) {
                            $setting[$panel][$section][$option] = $control['default'];
                            #$setting[$controller_name] = $control['default'];
                        } else {
                            $setting[$panel][$section][$option] = '';
                            #$setting[$controller_name] = "";
                        }
                        //$control = kumquat_get_option_value($control, $data);
                    }
                }
            }
        }
    };
    $setting['kumquat_flag']['reset_settings'] = false;
    $setting['THIS_LOCATION'] = param('THIS_LOCATION', '');
    $setting['THIS_LOCATION_FRONTEND'] = param('THIS_LOCATION_FRONTEND', '');
    return $setting;
}

////////////////////////////////////////////////////////////////////
//                          _ooOoo_                               //
//                         o8888888o                              //
//                         88" . "88                              //
//                         (| ^_^ |)                              //
//                         O\  =  /O                              //
//                      ____/`---'\____                           //
//                    .'  \\|     |//  `.                         //
//                   /  \\|||  :  |||//  \                        //
//                  /  _||||| -:- |||||-  \                       //
//                  |   | \\\  -  /// |   |                       //
//                  | \_|  ''\---/''  |   |                       //
//                  \  .-\__  `-`  ___/-. /                       //
//                ___`. .'  /--.--\  `. . ___                     //
//              ."" '<  `.___\_<|>_/___.'  >'"".                  //
//            | | :  `- \`.;`\ _ /`;.`/ - ` : | |                 //
//            \  \ `-.   \_ __\ /__ _/   .-` /  /                 //
//      ========`-.____`-.___\_____/___.-`____.-'========         //
//                           `=---='                              //
//      ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^        //
//           佛祖保佑        永无BUG         永不修改             //
////////////////////////////////////////////////////////////////////