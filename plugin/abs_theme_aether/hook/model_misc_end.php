//<?php

	/**
	 * 获取上传文件的accept属性值
	 * 
	 * @return string
	 */
function aether_get_upload_accept() {
    $filetypes = include APP_PATH . 'conf/attach.conf.php';
    
    // 手动添加现代格式
    $filetypes['image'][] = 'webp';
    $filetypes['image'][] = 'avif';
    $filetypes['video'][] = 'webm';
    
    // hook aether_get_upload_accept_add_more_extension.php
    
    $accept_parts = [];
    
    foreach ($filetypes as $type => $ext_array) {
        if ($type === 'all') continue;
        if (empty($ext_array)) continue;
        
        // ✅ 使用更高效的array_merge
        foreach ($ext_array as $ext) {
            $accept_parts[] = '.' . $ext;
        }
    }
    
    // ✅ 去重排序
    $accept_parts = array_unique($accept_parts);
    sort($accept_parts);
    
    return implode(',', $accept_parts);
}
	/**
	 * 格式化文件大小为人类可读的格式
	 * 
	 * @param int $filesize 文件大小（字节）
	 * @return string 格式化后的文件大小字符串
	 */
	function aether_format_filesize($filesize) {
		$filesize = intval($filesize);
		if ($filesize >= 1073741824) {
			return round($filesize / 1073741824, 1) . ' GB';
		} elseif ($filesize >= 1048576) {
			return round($filesize / 1048576, 1) . ' MB';
		} elseif ($filesize >= 1024) {
			return round($filesize / 1024, 1) . ' KB';
		} else {
			return $filesize . ' B';
		}
	}
	/**
	 * 【Aether主题】生成帖子附件列表HTML
	 * 
	 * @param array $filelist 附件列表数组
	 * @param bool $include_delete 是否包含删除按钮
	 * @return string 
	 */
	function aether_post_file_list_html($filelist, $include_delete = FALSE) {
		if (empty($filelist)) return '';

		// hook model_aether_post_file_list_html_start.php

		//$s = '<div id="attachment-list">' ;
		$s = '';
		foreach ($filelist as &$attach) {
			$icon_class = 'la-file';
			$text_class = 'text-muted';

			// 根据文件类型设置不同的图标和颜色
			switch ($attach['filetype']) {
				case 'image':
					$icon_class = 'la-file-image';
					$text_class = 'text-primary';
					break;
				case 'pdf':
					$icon_class = 'la-file-pdf';
					$text_class = 'text-danger';
					break;
				case 'doc':
				case 'docx':
					$icon_class = 'la-file-word';
					$text_class = 'text-blue';
					break;
				case 'xls':
				case 'xlsx':
					$icon_class = 'la-file-excel';
					$text_class = 'text-success';
					break;
				case 'zip':
				case 'rar':
				case '7z':
					$icon_class = 'la-file-archive';
					$text_class = 'text-warning';
					break;
			}

			// 计算文件大小
			$filesize_fmt = aether_format_filesize(isset($attach['filesize']) ? $attach['filesize'] : 0);

			$s .= '<li class="list-group-item col-6 col-md-4 col-lg-3 col-xl-2" id="attach-' . $attach['aid'] . '">';
			$s .= '<div class="d-flex flex-column justify-content-between align-items-center">';
			$s .= '<header class="ratio ratio-1x1 w-100 mb-2">';
			$s .= '<a href="' . url('attach-download-' . $attach['aid']) . '" target="_blank" class="d-flex align-items-center justify-content-center h-100">' ;
			
			// 根据文件类型显示不同内容
			if ($attach['filetype'] == 'image' && isset($attach['url'])) {
				// 可被预览的文件格式（图片）
				$s .= '<img src="' . $attach['url'] . '" alt="' . $attach['orgfilename'] . '" title="' . $attach['orgfilename'] . '" loading="lazy" decoding="async" class="w-100 h-100 object-contain" />';
			} else {
				// 不可被预览的文件格式
				$s .= '<i class="las ' . $icon_class . ' ' . $text_class . ' display-1 m-0 p-0"></i>';
			}
			$s .= '</a>';
			
			$s .= '</header>';
			$s .= '<footer class="d-flex align-items-center w-100 justify-content-between flex-wrap">';
			$s .= '<a href="' . url('attach-download-' . $attach['aid']) . '" target="_blank" class="fw-medium text-decoration-none flex-grow-1 truncate">' . $attach['orgfilename'] . '</a>';
			$s .= '<span>';
			$s .= '<small class="text-muted">' . $filesize_fmt . '</small>';
			
			// hook model_aether_post_file_list_html_delete_before.php
			if ($include_delete) {
				$s .= '<button type="button" class="btn btn-link text-danger btn-sm delete" data-aid="' . $attach['aid'] . '" hx-post="' . url('attach-delete-' . $attach['aid']) . '" hx-confirm="确定删除吗？" hx-target="#attach-' . $attach['aid'] . '" hx-swap="delete">';
				$s .= '<i class="las la-trash"></i>';
				$s .= '</button>';
			}
			// hook model_aether_post_file_list_html_delete_after.php
			
			$s .= '</span>';

			$s .= '</footer>';
			$s .= '</div>';
			$s .= '</li>';
		};
		//$s .= '</div>' ;

		// hook model_aether_post_file_list_html_end.php

		return $s;
	}

	if (!function_exists('stately_catch_post_imgs')) {
		/**
		 * 获取文章内的图片 
		 *
		 * @param string $content 文章内容
		 * @param bool $only_need_one_image 只需要一张图？
		 * @return array|string|null 文章里缩略图的网址，如果有一张图，返回string，如果有超过两张图，返回array，如果没有图，返回null
		 */
		function stately_catch_post_imgs($content, $only_need_one_image = false) {
			// 短代码，指定宽高
			$output = preg_match_all("/\[img width=(\d+),height=(\d+)\](.*?)\[\/img\]/is", $content, $matches);
			if (empty($output)) {
				// 短代码，无宽高
				$output = preg_match_all("/\[img\](.*?)\[\/img\]/is", $content, $matches);
				if (empty($output)) {
					// HTML
					$output = preg_match_all('/<img*.+src=[\'"]([^\'"]+)[\'"].*>/iU', $content, $matches);

					if (empty($output)) {
						// 实在没有了……
						$result = NULL;
					} else {
						if (count($matches[1]) == 1 || $only_need_one_image) {
							$result = $matches[1][0];
						} else {
							$result = $matches[1];
						}
					}
				} else {
					if (count($matches[1]) == 1 || $only_need_one_image) {
						$result = $matches[1][0];
					} else {
						$result = $matches[1];
					}
				}
			} else {
				if (count($matches[3]) == 1 || $only_need_one_image) {
					$result = $matches[3][0];
				} else {
					$result = $matches[3];
				}
			}
			return $result;
		}
	}


	if (!function_exists('stately_format_number')) {
		/**
		 * 将数字转换为k/m/b/t（英文）或万/亿/兆（中文）等简洁格式
		 * 
		 * @param int|float $number 要格式化的数字
		 * @param int $precision 保留小数位数
		 * @param bool $chinese_alter 是否使用中文单位（默认英文）
		 * @return string 格式化后的字符串
		 */
		function stately_format_number($number = 0, $precision = 2, $chinese_alter = false) {
			$num = floatval($number);
			$sign = ($num < 0) ? '-' : '';
			$absNum = abs($num);

			// 处理0值快速返回
			if ($absNum == 0) {
				return 0;
				//return $sign . round($absNum, $precision);
			}

			if ($chinese_alter) {
				// 中文单位：万进制
				$units = [
					pow(10000, 6) => '秭',
					pow(10000, 5) => '垓',
					pow(10000, 4) => '京',
					pow(10000, 3) => '兆',
					pow(10000, 2) => '亿',
					pow(10000, 1) => '万',
					1 => ''
				];
			} else {
				// 英文单位：千进制
				$units = [
					pow(1000, 5) => 'q', // quadrillion
					pow(1000, 4) => 't', // trillion
					pow(1000, 3) => 'b', // billion
					pow(1000, 2) => 'm', // million
					pow(1000, 1) => 'k', // thousand
					1 => ''
				];
			}

			// 从大到小排序单位
			krsort($units, SORT_NUMERIC);

			$result = $absNum;
			$suffix = '';
			foreach ($units as $unitValue => $unitSuffix) {
				if ($absNum >= $unitValue) {
					$result = $absNum / $unitValue;
					$suffix = $unitSuffix;
					break;
				}
			}

			// 格式化为字符串（自动处理尾随零）
			$formatted = round($result, $precision);
			return $sign . $formatted . $suffix;
		}
	}


	if (!function_exists('kumquat_get_setting')) {
		/**
		 * 金桔框架——获取设置
		 *
		 * @param string $plugin_id 插件ID
		 * @param string|null $setting_id 要获取的设置项；若不填写则获取全部设置（以多纬数组呈现）
		 * @return mixed 设置值或设置数组
		 */
		function kumquat_get_setting($plugin_id, $setting_id = NULL) {
			if (empty($plugin_id)) {
				return NULL;
			} else {
				$plugin_settings = setting_get($plugin_id . '_setting');
				$arr_option_name = explode('/', $setting_id);
				//var_dump($arr_option_name);
				if (
					!is_null($plugin_settings)
					&& isset($settings[$arr_option_name[0]][$arr_option_name[1]][$arr_option_name[2]])
				) {
					return $settings[$arr_option_name[0]][$arr_option_name[1]][$arr_option_name[2]];
				} else {
					return '[QUMQUAT][ERROR] The setting' . $setting_id . 'is not exist';
				}
			}
		}
	}

	if (!function_exists('kumquat_css_font_assemble')) {
		function kumquat_css_font_assemble(array $setting_value) {
			return $setting_value['font-style'] . ' ' . $setting_value['font-weight'] . ' ' . $setting_value['font-size'] . $setting_value['font-size_unit'] . '/' . $setting_value['line-height'] . ' ' . $setting_value['font-family'];
		}
	}
	if (!function_exists('kumquat_css_background_image_assemble')) {
		function kumquat_css_background_image_assemble($setting_value) {
			$result = 'url("'
				. $setting_value['background-url']
				. '") '
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
		 * @param bool|int|float $opacity 透明度（例如 0.5），如果是false则为RGB格式
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
		 * @param bool $return_array 是否返回数组，默认返回字符串
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
				return $hue . ', ' . $saturation . '%, ' . $lightness . '%;';
			}
		}
	}

	if (!function_exists('rgbToHct')) {
		/**
		 * RGB转换为HCT
		 * 简化实现，基于HSL近似HCT
		 * @param int $r 红色通道值(0-255)
		 * @param int $g 绿色通道值(0-255)
		 * @param int $b 蓝色通道值(0-255)
		 * @return array HCT值(h: 0-360, c: 0-100, t: 0-100)
		 */
		function rgbToHct($r, $g, $b) {
			$r = $r / 255;
			$g = $g / 255;
			$b = $b / 255;

			$cmin = min($r, $g, $b);
			$cmax = max($r, $g, $b);
			$delta = $cmax - $cmin;

			// 色相计算
			if ($delta == 0) {
				$hue = 0;
			} elseif ($cmax === $r) {
				$hue = fmod((($g - $b) / $delta), 6) * 60;
			} elseif ($cmax === $g) {
				$hue = (($b - $r) / $delta + 2) * 60;
			} else {
				$hue = (($r - $g) / $delta + 4) * 60;
			}

			if ($hue < 0) {
				$hue += 360;
			}

			// 色度(基于HSL饱和度近似)
			$lightness = ($cmax + $cmin) / 2;
			$chroma = $delta === 0 ? 0 : ($delta / (1 - abs(2 * $lightness - 1)));

			// 色调(基于HSL亮度)
			$tone = $lightness;

			return array(
				'h' => round($hue),
				'c' => round($chroma * 100),
				't' => round($tone * 100)
			);
		}
	}

	if (!function_exists('hctToRgb')) {
		/**
		 * HCT转换为RGB
		 * 简化实现，基于HSL近似HCT
		 * @param int $h 色相(0-360)
		 * @param int $c 色度(0-100)
		 * @param int $t 色调(0-100)
		 * @return array RGB值(r: 0-255, g: 0-255, b: 0-255)
		 */
		function hctToRgb($h, $c, $t) {
			$h = $h / 360;
			$c = $c / 100;
			$t = $t / 100;

			// 基于HSL的实现
			$a = $c * min($t, 1 - $t);
			$f = function ($n) use ($h, $t, $a) {
				$k = fmod(($n + $h * 6), 6);
				return $t - $a * max(min($k, 4 - $k, 1), 0);
			};

			$r = $f(5);
			$g = $f(3);
			$b = $f(1);

			return array(
				'r' => round($r * 255),
				'g' => round($g * 255),
				'b' => round($b * 255)
			);
		}
	}

	if (!function_exists('rgbToHex')) {
		/**
		 * RGB转换为HEX
		 * @param int $r 红色通道值(0-255)
		 * @param int $g 绿色通道值(0-255)
		 * @param int $b 蓝色通道值(0-255)
		 * @return string 十六进制颜色值
		 */
		function rgbToHex($r, $g, $b) {
			return '#' . str_pad(dechex($r), 2, '0', STR_PAD_LEFT)
				. str_pad(dechex($g), 2, '0', STR_PAD_LEFT)
				. str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
		}
	}

	if (!function_exists('hslToHex')) {
		/**
		 * HSL转换为HEX
		 * @param int $h 色相(0-360)
		 * @param int $s 饱和度(0-100)
		 * @param int $l 亮度(0-100)
		 * @return string 十六进制颜色值
		 */
		function hslToHex($h, $s, $l) {
			$h = $h / 360;
			$s = $s / 100;
			$l = $l / 100;

			if ($s == 0) {
				$r = $g = $b = $l;
			} else {
				$hue2rgb = function ($p, $q, $t) {
					if ($t < 0) $t += 1;
					if ($t > 1) $t -= 1;
					if ($t < 1 / 6) return $p + ($q - $p) * 6 * $t;
					if ($t < 1 / 2) return $q;
					if ($t < 2 / 3) return $p + ($q - $p) * (2 / 3 - $t) * 6;
					return $p;
				};

				$q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
				$p = 2 * $l - $q;

				$r = $hue2rgb($p, $q, $h + 1 / 3);
				$g = $hue2rgb($p, $q, $h);
				$b = $hue2rgb($p, $q, $h - 1 / 3);
			}

			return rgbToHex(round($r * 255), round($g * 255), round($b * 255));
		}
	}

	if (!function_exists('generateMd3ColorPalette')) {
		function remapSaturation($s, $min = 0, $max = 100) {
			// 将饱和度从0-100范围重新映射到min-max范围
			return round($min + ($s / 100) * ($max - $min));
		}

		/**
		 * 基于色相调整饱和度
		 * 黄色部分饱和度降低，蓝色部分饱和度提高
		 * @param float $hue 色相值(0-360)
		 * @return float 调整后的饱和度
		 */
		function adjustSaturationByHue($hue) {
			$hue = fmod($hue, 360);
			if ($hue < 0) $hue += 360;

			// 简化：关键点数组
			$points = [
				0 => 65,   // 红
				60 => 55,  // 黄
				120 => 50, // 绿
				180 => 65, // 青
				240 => 100, // 蓝
				300 => 70, // 紫
				360 => 65  // 红
			];

			// 找到边界
			$prev = 0;
			$next = 360;
			foreach (array_keys($points) as $key) {
				if ($key <= $hue) $prev = $key;
				if ($key >= $hue) {
					$next = $key;
					break;
				}
			}

			// 插值
			if ($prev == $next) return $points[$prev];
			$ratio = ($hue - $prev) / ($next - $prev);
			return round($points[$prev] + ($points[$next] - $points[$prev]) * $ratio);
		}

		/**
		 * 生成Material Design 3色彩调色板
		 * @param string $hex 主色十六进制值
		 * @param bool $darkMode 是否为夜间模式（默认为false）
		 * @return array MD3色彩变体数组
		 */
		function generateMd3ColorPalette($hex, $darkMode = false) {
			// 首先获取主色的HSL值
			$hsl = hexToHsl($hex, true);

			// 基于色相调整饱和度
			$baseSaturation = $hsl['s'];
			$adjustedSaturation = adjustSaturationByHue($hsl['h'], $baseSaturation);

			if ($darkMode) {
				// 夜间模式：在调整后的饱和度基础上进行夜间模式映射
				$hsl['s'] = remapSaturation($hsl['s'], 10, round($adjustedSaturation * 0.8));
			} else {
				// 日间模式：在调整后的饱和度基础上进行日间模式映射
				$hsl['s'] = remapSaturation($hsl['s'], 10, $adjustedSaturation);
			}

			// MD3色调级别 (0-100，间隔10)
			$toneLevels = array(0, 4, 5, 6, 10, 12, 15, 17, 20, 22, 24, 25, 30, 35, 40, 50, 60, 70, 80, 90, 92, 94, 95, 96, 98, 99, 100);
			$palette = array();

			// 夜间模式色调映射反转
			if ($darkMode) {
				// 夜间模式：将亮色调映射到暗色调，暗色调映射到亮色调
				$toneLevelsFlipped = array_reverse($toneLevels);
				$toneMapping = array_combine($toneLevels, $toneLevelsFlipped);
			}

			// 为每个色调级别生成颜色
			foreach ($toneLevels as $tone) {
				$targetTone = $darkMode ? $toneMapping[$tone] : $tone;
				// 保持色相和饱和度不变，只调整亮度
				$palette[$tone] = hslToHex($hsl['h'], $hsl['s'], $targetTone);
			}

			return $palette;
		}
	}

	if (!function_exists('generateMd3CssVariables')) {
		/**
		 * 生成Material Design 3 CSS变量
		 * @param string $primaryHex 主色十六进制值
		 * @param string $secondaryHex 辅助色十六进制值（可选）
		 * @param string $tertiaryHex 第三色十六进制值（可选）
		 * @return string CSS变量代码
		 */
		function generateMd3CssVariables($primaryHex = '#1e91ff') {

			$secondaryHex = hexToHsl($primaryHex, true);
			// 如果h小于等于180，增加40，否则减少40
			$secondaryHex['h'] = $secondaryHex['h'] <= 180 ? $secondaryHex['h'] + 40 : $secondaryHex['h'] - 40;
			$secondaryHex = hslToHex($secondaryHex['h'], $secondaryHex['s'], $secondaryHex['l']);

			$tertiaryHex = hexToHsl($primaryHex, true);
			// 转半圈
			$tertiaryHex['h'] = ($tertiaryHex['h'] + 180) % 360;
			$tertiaryHex = hslToHex($tertiaryHex['h'], $tertiaryHex['s'], $tertiaryHex['l']);
			// 生成各种颜色的调色板
			$primaryPalette = generateMd3ColorPalette($primaryHex);
			$primaryPaletteDark = generateMd3ColorPalette($primaryHex, true);

			$secondaryPalette = generateMd3ColorPalette($secondaryHex);
			$secondaryPaletteDark = generateMd3ColorPalette($secondaryHex, true);
			$tertiaryPalette = generateMd3ColorPalette($tertiaryHex);
			$tertiaryPaletteDark = generateMd3ColorPalette($tertiaryHex, true);

			// 生成中性色
			//$neutralHex = '#6a95b9'; //  hsl(207.34deg 36.07% 57.06%);
			$neutralHex = hexToHsl($primaryHex, true);
			$neutralHex['s'] = round($neutralHex['s'] * 0.5);
			$neutralHex['l'] = 57;
			$neutralHex = hslToHex($neutralHex['h'], $neutralHex['s'], $neutralHex['l']);
			$neutralPalette = generateMd3ColorPalette($neutralHex);
			$neutralPaletteDark = generateMd3ColorPalette($neutralHex, true);

			//$neutralVariantHex = '#48769c'; // hsl(207.14deg 36.84% 44.71%);
			$neutralVariantHex = hexToHsl($primaryHex, true);
			$neutralVariantHex['s'] = round($neutralVariantHex['s'] * 0.333);
			$neutralVariantHex['l'] = 45;
			$neutralVariantHex = hslToHex($neutralVariantHex['h'], $neutralVariantHex['s'], $neutralVariantHex['l']);
			$neutralVariantPalette = generateMd3ColorPalette($neutralVariantHex);
			$neutralVariantPaletteDark = generateMd3ColorPalette($neutralVariantHex, true);

			$errorHex = '#ff6363';
			$errorPalette = generateMd3ColorPalette($errorHex);
			$errorPaletteDark = generateMd3ColorPalette($errorHex, true);

			// 生成CSS变量
			$css = '';

			// 亮色模式
			$css .= ':root, [data-bs-theme=light] {' . PHP_EOL;
			//*
			// 主色变量
			foreach ($primaryPalette as $tone => $color) {
				$css .= '    --md3-primary-' . $tone . ': ' . $color . ';' . PHP_EOL;
			}

			// 辅助色变量
			foreach ($secondaryPalette as $tone => $color) {
				$css .= '    --md3-secondary-' . $tone . ': ' . $color . ';' . PHP_EOL;
			}

			// 第三色变量
			foreach ($tertiaryPalette as $tone => $color) {
				$css .= '    --md3-tertiary-' . $tone . ': ' . $color . ';' . PHP_EOL;
			}

			// 中性色变量
			foreach ($neutralPalette as $tone => $color) {
				$css .= '    --md3-neutral-' . $tone . ': ' . $color . ';' . PHP_EOL;
			}

			// 中性变体变量
			foreach ($neutralVariantPalette as $tone => $color) {
				$css .= '    --md3-neutral-variant-' . $tone . ': ' . $color . ';' . PHP_EOL;
			}
			//*/
			// 系统颜色映射
			$css .= '        /* 系统颜色映射 */' . PHP_EOL;
			$css .= '    --md3-primary: ' . $primaryPalette[60] . ';' . PHP_EOL;
			$css .= '    --md3-primary-rgb: ' . implode(', ', hexToRgb($primaryPalette[60])) . ';' . PHP_EOL;

			$css .= '    --md3-on-primary: ' . $primaryPalette[100] . ';' . PHP_EOL;
			$css .= '    --md3-primary-container: ' . $primaryPalette[30] . ';' . PHP_EOL;
			$css .= '    --md3-on-primary-container: ' . $primaryPalette[90] . ';' . PHP_EOL;
			$css .= '    ' . PHP_EOL;
			$css .= '    --md3-secondary: ' . $secondaryPalette[60] . ';' . PHP_EOL;
			$css .= '    --md3-secondary-rgb: ' . implode(', ', hexToRgb($secondaryPalette[60])) . ';' . PHP_EOL;
			$css .= '    --md3-on-secondary: ' . $secondaryPalette[100] . ';' . PHP_EOL;
			$css .= '    --md3-secondary-container: ' . $secondaryPalette[30] . ';' . PHP_EOL;
			$css .= '    --md3-on-secondary-container: ' . $secondaryPalette[90] . ';' . PHP_EOL;
			$css .= '    ' . PHP_EOL;
			$css .= '    --md3-tertiary: ' . $tertiaryPalette[60] . ';' . PHP_EOL;
			$css .= '    --md3-tertiary-rgb: ' . implode(', ', hexToRgb($tertiaryPalette[60])) . ';' . PHP_EOL;
			$css .= '    --md3-on-tertiary: ' . $tertiaryPalette[100] . ';' . PHP_EOL;
			$css .= '    --md3-tertiary-container: ' . $tertiaryPalette[30] . ';' . PHP_EOL;
			$css .= '    --md3-on-tertiary-container: ' . $tertiaryPalette[90] . ';' . PHP_EOL;
			$css .= '    ' . PHP_EOL;
			$css .= '    --md3-surface: ' . $neutralPalette[98] . ';' . PHP_EOL;
			$css .= '    --md3-surface-variant: ' . $neutralVariantPalette[30] . ';' . PHP_EOL;
			$css .= '    --md3-background: ' . $neutralPalette[98] . ';' . PHP_EOL;
			$css .= '    --md3-background-rgb: ' . implode(', ', hexToRgb($neutralPalette[98])) . ';' . PHP_EOL;
			$css .= '    --md3-error:' . $errorPalette[60] . ';' . PHP_EOL;
			$css .= '    --md3-on-error: ' . $errorPalette[100] . ';' . PHP_EOL;
			$css .= '    --md3-error-container: ' . $errorPalette[30] . ';' . PHP_EOL;
			$css .= '    --md3-on-error-container: ' . $errorPalette[80] . ';' . PHP_EOL;
			$css .= '    --md3-outline: ' . $neutralVariantPalette[80] . ';' . PHP_EOL;
			$css .= '    --md3-outline-variant: ' . $neutralVariantPalette[95] . ';' . PHP_EOL;
			$css .= '    --md3-shadow: ' . $neutralPalette[0] . ';' . PHP_EOL;
			$css .= '    --md3-scrim: ' . $neutralPalette[0] . ';' . PHP_EOL;
			$css .= '    --md3-inverse-surface: ' . $neutralPalette[35] . ';' . PHP_EOL;
			$css .= '    --md3-inverse-surface-rgb: ' . implode(', ', hexToRgb($neutralPalette[35])) . ';' . PHP_EOL;
			$css .= '    --md3-inverse-on-surface: ' . $neutralPalette[95] . ';' . PHP_EOL;
			$css .= '    --md3-inverse-on-surface-rgb: ' . implode(', ', hexToRgb($neutralPalette[95])) . ';' . PHP_EOL;
			$css .= '    --md3-inverse-primary: ' . $primaryPalette[40] . ';' . PHP_EOL;
			$css .= '    --md3-primary-fixed: ' . $primaryPalette[30] . ';' . PHP_EOL;
			$css .= '    --md3-primary-fixed-dim: ' . $primaryPalette[20] . ';' . PHP_EOL;
			$css .= '    --md3-on-primary-fixed: ' . $primaryPalette[90] . ';' . PHP_EOL;
			$css .= '    --md3-on-primary-fixed-variant: ' . $primaryPalette[80] . ';' . PHP_EOL;
			$css .= '    --md3-secondary-fixed: ' . $secondaryPalette[30] . ';' . PHP_EOL;
			$css .= '    --md3-secondary-fixed-dim: ' . $secondaryPalette[20] . ';' . PHP_EOL;
			$css .= '    --md3-on-secondary-fixed: ' . $secondaryPalette[90] . ';' . PHP_EOL;
			$css .= '    --md3-on-secondary-fixed-variant: ' . $secondaryPalette[80] . ';' . PHP_EOL;
			$css .= '    --md3-tertiary-fixed: ' . $tertiaryPalette[30] . ';' . PHP_EOL;
			$css .= '    --md3-tertiary-fixed-dim: ' . $tertiaryPalette[20] . ';' . PHP_EOL;
			$css .= '    --md3-on-tertiary-fixed: ' . $tertiaryPalette[90] . ';' . PHP_EOL;
			$css .= '    --md3-on-tertiary-fixed-variant: ' . $tertiaryPalette[80] . ';' . PHP_EOL;
			$css .= '    --md3-surface-dim: ' . $neutralPalette[95] . ';' . PHP_EOL;
			$css .= '    --md3-surface-bright: ' . $neutralPalette[98] . ';' . PHP_EOL;
			$css .= '    --md3-surface-container-lowest: ' . $neutralPalette[100] . ';' . PHP_EOL;
			$css .= '    --md3-surface-container-low: ' . $neutralPalette[96] . ';' . PHP_EOL;
			$css .= '    --md3-surface-container: ' . $neutralPalette[94] . ';' . PHP_EOL;
			$css .= '    --md3-surface-container-high: ' . $neutralPalette[92] . ';' . PHP_EOL;
			$css .= '    --md3-surface-container-highest: ' . $neutralPalette[90] . ';' . PHP_EOL;

			$css .= '}' . PHP_EOL;

			$primaryPalette = $primaryPaletteDark;
			$secondaryPalette = $secondaryPaletteDark;
			$tertiaryPalette = $tertiaryPaletteDark;
			$neutralPalette = $neutralPaletteDark;
			$neutralVariantPalette = $neutralVariantPaletteDark;
			// 暗色模式
			$css .= '[data-bs-theme=dark] {' . PHP_EOL;
			///*
			// 主色变量
			foreach ($primaryPaletteDark as $tone => $color) {
				$css .= '    --md3-primary-' . $tone . ': ' . $color . ';' . PHP_EOL;
			}

			// 辅助色变量
			foreach ($secondaryPaletteDark as $tone => $color) {
				$css .= '    --md3-secondary-' . $tone . ': ' . $color . ';' . PHP_EOL;
			}

			// 第三色变量
			foreach ($tertiaryPaletteDark as $tone => $color) {
				$css .= '    --md3-tertiary-' . $tone . ': ' . $color . ';' . PHP_EOL;
			}

			// 中性色变量
			foreach ($neutralPaletteDark as $tone => $color) {
				$css .= '    --md3-neutral-' . $tone . ': ' . $color . ';' . PHP_EOL;
			}

			// 中性变体变量
			foreach ($neutralVariantPaletteDark as $tone => $color) {
				$css .= '    --md3-neutral-variant-' . $tone . ': ' . $color . ';' . PHP_EOL;
			}
			//*/
			// 系统颜色映射
			$css .= '        /* 系统颜色映射 */' . PHP_EOL;
			$css .= '    --md3-primary: ' . $primaryPalette[24] . ';' . PHP_EOL;
			$css .= '    --md3-primary-rgb: ' . implode(', ', hexToRgb($primaryPalette[24])) . ';' . PHP_EOL;
			$css .= '    --md3-on-primary: ' . $primaryPalette[0] . ';' . PHP_EOL;
			$css .= '    --md3-primary-container: ' . $primaryPalette[30] . ';' . PHP_EOL;
			$css .= '    --md3-on-primary-container: ' . $primaryPalette[10] . ';' . PHP_EOL;
			$css .= '    ' . PHP_EOL;
			$css .= '    --md3-secondary: ' . $secondaryPalette[30] . ';' . PHP_EOL;
			$css .= '    --md3-secondary-rgb: ' . implode(', ', hexToRgb($secondaryPalette[30])) . ';' . PHP_EOL;
			$css .= '    --md3-on-secondary: ' . $secondaryPalette[0] . ';' . PHP_EOL;
			$css .= '    --md3-secondary-container: ' . $secondaryPalette[30] . ';' . PHP_EOL;
			$css .= '    --md3-on-secondary-container: ' . $secondaryPalette[10] . ';' . PHP_EOL;
			$css .= '    ' . PHP_EOL;
			$css .= '    --md3-tertiary: ' . $tertiaryPalette[30] . ';' . PHP_EOL;
			$css .= '    --md3-tertiary-rgb: ' . implode(', ', hexToRgb($tertiaryPalette[30])) . ';' . PHP_EOL;
			$css .= '    --md3-on-tertiary: ' . $tertiaryPalette[0] . ';' . PHP_EOL;
			$css .= '    --md3-tertiary-container: ' . $tertiaryPalette[30] . ';' . PHP_EOL;
			$css .= '    --md3-on-tertiary-container: ' . $tertiaryPalette[10] . ';' . PHP_EOL;
			$css .= '    ' . PHP_EOL;
			$css .= '    --md3-surface: ' . $neutralPalette[98] . ';' . PHP_EOL;
			$css .= '    --md3-surface-variant: ' . $neutralVariantPalette[30] . ';' . PHP_EOL;
			$css .= '    --md3-background: ' . $neutralPalette[98] . ';' . PHP_EOL;
			$css .= '    --md3-background-rgb: ' . implode(', ', hexToRgb($neutralPalette[98])) . ';' . PHP_EOL;
			$css .= '    --md3-error:' . $errorPalette[60] . ';' . PHP_EOL;
			$css .= '    --md3-on-error: ' . $errorPalette[100] . ';' . PHP_EOL;
			$css .= '    --md3-error-container: ' . $errorPalette[30] . ';' . PHP_EOL;
			$css .= '    --md3-on-error-container: ' . $errorPalette[80] . ';' . PHP_EOL;
			$css .= '    --md3-outline: ' . $neutralVariantPalette[80] . ';' . PHP_EOL;
			$css .= '    --md3-outline-variant: ' . $neutralVariantPalette[95] . ';' . PHP_EOL;
			$css .= '    --md3-shadow: ' . $neutralPalette[100] . ';' . PHP_EOL;
			$css .= '    --md3-scrim: ' . $neutralPalette[100] . ';' . PHP_EOL;
			$css .= '    --md3-inverse-surface: ' . $neutralPalette[70] . ';' . PHP_EOL;
			$css .= '    --md3-inverse-surface-rgb: ' . implode(', ', hexToRgb($neutralPalette[70])) . ';' . PHP_EOL;
			$css .= '    --md3-inverse-on-surface: ' . $neutralPalette[20] . ';' . PHP_EOL;
			$css .= '    --md3-inverse-on-surface-rgb: ' . implode(', ', hexToRgb($neutralPalette[20])) . ';' . PHP_EOL;
			$css .= '    --md3-inverse-primary: ' . $primaryPalette[40] . ';' . PHP_EOL;
			$css .= '    --md3-primary-fixed: ' . $primaryPalette[30] . ';' . PHP_EOL;
			$css .= '    --md3-primary-fixed-dim: ' . $primaryPalette[20] . ';' . PHP_EOL;
			$css .= '    --md3-on-primary-fixed: ' . $primaryPalette[90] . ';' . PHP_EOL;
			$css .= '    --md3-on-primary-fixed-variant: ' . $primaryPalette[80] . ';' . PHP_EOL;
			$css .= '    --md3-secondary-fixed: ' . $secondaryPalette[30] . ';' . PHP_EOL;
			$css .= '    --md3-secondary-fixed-dim: ' . $secondaryPalette[20] . ';' . PHP_EOL;
			$css .= '    --md3-on-secondary-fixed: ' . $secondaryPalette[90] . ';' . PHP_EOL;
			$css .= '    --md3-on-secondary-fixed-variant: ' . $secondaryPalette[80] . ';' . PHP_EOL;
			$css .= '    --md3-tertiary-fixed: ' . $tertiaryPalette[30] . ';' . PHP_EOL;
			$css .= '    --md3-tertiary-fixed-dim: ' . $tertiaryPalette[20] . ';' . PHP_EOL;
			$css .= '    --md3-on-tertiary-fixed: ' . $tertiaryPalette[90] . ';' . PHP_EOL;
			$css .= '    --md3-on-tertiary-fixed-variant: ' . $tertiaryPalette[80] . ';' . PHP_EOL;
			$css .= '    --md3-surface-dim: ' . $neutralPalette[95] . ';' . PHP_EOL;
			$css .= '    --md3-surface-bright: ' . $neutralPalette[98] . ';' . PHP_EOL;
			$css .= '    --md3-surface-container-lowest: ' . $neutralPalette[100] . ';' . PHP_EOL;
			$css .= '    --md3-surface-container-low: ' . $neutralPalette[96] . ';' . PHP_EOL;
			$css .= '    --md3-surface-container: ' . $neutralPalette[94] . ';' . PHP_EOL;
			$css .= '    --md3-surface-container-high: ' . $neutralPalette[92] . ';' . PHP_EOL;
			$css .= '    --md3-surface-container-highest: ' . $neutralPalette[90] . ';' . PHP_EOL;

			$css .= '}' . PHP_EOL;

			return $css;
		}
	}

	if (!function_exists('hexToRgb')) {
		/**
		 * HEX转换为RGB
		 * @param string $hex 十六进制颜色值
		 * @return array RGB值
		 */
		function hexToRgb($hex) {
			if (strpos($hex, '#') === 0) {
				$hex = substr($hex, 1);
			}

			if (strlen($hex) == 3) {
				$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
			}

			return array(
				'r' => hexdec(substr($hex, 0, 2)),
				'g' => hexdec(substr($hex, 2, 2)),
				'b' => hexdec(substr($hex, 4, 2))
			);
		}
	}
