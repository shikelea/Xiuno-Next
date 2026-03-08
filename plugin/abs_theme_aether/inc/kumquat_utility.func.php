<?php
/* 【开始】金桔框架——实用函数 */
// IDIOTS DON’T TOUCH WHAT YOU DON’T UNDERSTAND!!!!

if (!function_exists('hexrgb')) {
    /** 
     * 十六进制颜色转换为RGB
     * 
     * @param string $color 颜色的十六进制代码（例如 #abcdef）
     * @return string 171,205,239
     */
    function hexrgb($color) {
        if (isset($color[0]) && $color[0] == '#')
            $color = substr($color, 1);

        if (strlen($color) == 6)
            list($r, $g, $b) = array(
                $color[0] . $color[1],
                $color[2] . $color[3],
                $color[4] . $color[5]
            );
        elseif (strlen($color) == 3)
            list($r, $g, $b) = array($color[0] . $color[0], $color[1] . $color[1], $color[2] . $color[2]);
        else
            return false;

        $r = hexdec($r);
        $g = hexdec($g);
        $b = hexdec($b);

        return $r . ',' . $g . ',' . $b;
    }
}

if (!function_exists('hexrgba')) {
    /**
     * 十六进制颜色转换为RGBA
     *
     * @param string $color 颜色的十六进制代码（例如 #abcdef）
     * @param bool|int|float $opacity 透明度（例如 0.5）
     * @return string rgb(171,205,239) 或 rgba(171,205,239,0.5)
     */
    function hexrgba($color, $opacity = false) {
        $default = 'rgb(0,0,0)';

        if (empty($color))
            return $default;

        if ($color[0] == '#') {
            $color = substr($color, 1);
        }

        if (strlen($color) == 6) {
            $hex = array($color[0] . $color[1], $color[2] . $color[3], $color[4] . $color[5]);
        } elseif (strlen($color) == 3) {
            $hex = array($color[0] . $color[0], $color[1] . $color[1], $color[2] . $color[2]);
        } else {
            return $default;
        }

        $rgb =  array_map('hexdec', $hex);

        if ($opacity) {
            if (abs($opacity) > 1)
                $opacity = 1.0;
            $output = 'rgba(' . implode(",", $rgb) . ',' . $opacity . ')';
        } else {
            $output = 'rgb(' . implode(",", $rgb) . ')';
        }

        return $output;
    }
}

if (!function_exists('hexToHsl')) {
    /**
     * 颜色转换为HSL
     * @param string $color 颜色的十六进制代码（例如 #abcdef）
     * @return string 210,68%,80.4%
     */
    function hexToHsl($hex, $return_array = false) {

        if (function_exists('str_starts_with') && str_starts_with($hex, '#')) {
            $hex = substr($hex, 1);
        } elseif (strpos($hex, '#') === 0) {
            // 兼容PHP 7~8的写法
            $hex = substr($hex, 1);
        }

        $red = hexdec(substr($hex, 0, 2)) / 255;
        $green = hexdec(substr($hex, 2, 2)) / 255;
        $blue = hexdec(substr($hex, 4, 2)) / 255;

        $cmin = min($red, $green, $blue);
        $cmax = max($red, $green, $blue);
        $delta = $cmax - $cmin;

        if ($delta == 0) {
            $hue = 0;
        } elseif ($cmax === $red) {
            $hue = (($green - $blue) / $delta);
        } elseif ($cmax === $green) {
            $hue = ($blue - $red) / $delta + 2;
        } else {
            $hue = ($red - $green) / $delta + 4;
        }

        $hue = round($hue * 60);
        if ($hue < 0) {
            $hue += 360;
        }

        $lightness = (($cmax + $cmin) / 2);
        $saturation = $delta === 0 ? 0 : ($delta / (1 - abs(2 * $lightness - 1)));
        if ($saturation < 0) {
            $saturation += 1;
        }

        $lightness = round($lightness * 100);
        $saturation = round($saturation * 100);
        if ($return_array) {
            return array(
                'h' => $hue,
                's' => $saturation,
                'l' => $lightness,
            );
        } else {
            return "${hue}, ${saturation}%, ${lightness}%";
        }
    }
}

/**
 * 截取字符串（指定开始和结束的字符串）
 *
 * @param string $str 要截取的字符串
 * @param string $start 从哪个字符串开始截取
 * @param string $end 截取到哪个字符串结束
 * @param int $start_offset 字符串开始处偏移量（可根据自己需要修改）
 * @param int $end_offset 字符串结束处偏移量（可根据自己需要修改）
 * @return string 截取的字符串
 */
function str_get_between($str, $start, $end, $start_offset = 0, $end_offset = 0) {
    $haystack = $str;
    $haystack = '!!!' . $haystack . '!!!';
    $start_pos = strripos($haystack, $start);
    $end_pos = strripos($haystack, $end);
    if (($start_pos == false || $end_pos == false) || $start_pos >= $end_pos) {
        return false;
    }
    $haystack = substr($haystack, ($start_pos + $start_offset), ($end_pos - $start_pos - $end_offset));
    return $haystack;
}

/**
 * 金桔框架——校验——日期
 * @param string $input 要校验的日期
 * @return string 格式正确的日期（年-月-日）
 */
function kumquat_sanitize_date($input) {
    $date = new DateTime($input);
    return $date->format('Y-m-d');
}

/**
 * 金桔框架——获取设置项的值
 * 如果未定义就获取默认值
 * @param $option_name 设置项的名字
 * @param $settings 在这里寻找
 * @param $default 默认值
 */
function kumquat_get_option_value($option_name, $settings, $default = NULL) {
    $arr_option_name = explode('/', $option_name);
    //var_dump($arr_option_name);
    if (isset($settings) && isset($settings[$arr_option_name[0]][$arr_option_name[1]][$arr_option_name[2]])) {
        return $settings[$arr_option_name[0]][$arr_option_name[1]][$arr_option_name[2]];
    } else if (!is_null($default)) {
        return $default;
    } else {
        return 'undefined';
    }
}

/**
 * 金桔框架——保存设置按钮
 * @param bool $full_width 是否为全宽度
 * @param string $color 按钮的颜色
 */
function kumquat_save_button($full_width = false, $color = 'primary') {
    $full_width_add = '';
    $full_width == true ? $full_width_add = ' btn-block' : '';
    $result = '<button type="submit" class="btn btn-' . $color . $full_width_add . '" id="submit" data-loading-text="' . lang('submiting') . '...">' . lang('save') . lang('setting') . '</button>';
    return $result;
}
/**
 * 金桔框架——唤起重置设置弹窗按钮
 * @param string $color 按钮的颜色
 */
function kumquat_reset_modal_button($color = 'outline-secondary') {
    $result = '<button type="button" class="btn btn-kumquatResetModal btn-' . $color . '" data-toggle="modal" data-target="#kumquatResetModal">' . lang('Reset') . lang('setting') . '</button>';
    return $result;
}

/**
 * 金桔框架——重置设置按钮
 * @param string $color 按钮的颜色
 */
function kumquat_reset_button($color = 'danger') {
    $result = '<input type="hidden" name="kumquat_flag/reset_settings" value="1">' . '<button type="button" class="btn btn-' . $color . '" id="kumquat_reset_confirm">' . lang('Reset') . lang('setting') . '</button>';
    return $result;
}

if (!function_exists('kumquat_css_font_assemble')) {
    /**
     * 金桔框架——组装CSS样式——字体
     * @param array $setting_value 设置项
     * @return string 符合CSS标准的font值
     */
    function kumquat_css_font_assemble($setting_value) {
        if (!isset($setting_value['font-size_unit'])) {
            $setting_value['font-size_unit'] = 'px';
        }
        return $setting_value['font-style']
            . ' '
            . $setting_value['font-weight']
            . ' '
            . $setting_value['font-size']
            . $setting_value['font-size_unit']
            . '/'
            . $setting_value['line-height']
            . ' '
            . $setting_value['font-family'];
    }
}

if (!function_exists('kumquat_css_background_image_assemble')) {
    /**
     * 金桔框架——组装CSS样式——背景图片
     * @param array 符合CSS标准的background值
     */
    function kumquat_css_background_image_assemble($setting_value) {
        $result = 'url('
            . $setting_value['background-url']
            . ') '
            . $setting_value['background-repeat']
            . ' ';
        if ('initial' != $setting_value['background-attachment']) {
            $result .= $setting_value['background-attachment'] . ' ';
        }
        $result .= $setting_value['background-position']
            . ' / '
            . $setting_value['background-size'];

        return $result;
    }
}
/* 【结束】金桔框架——实用函数 */