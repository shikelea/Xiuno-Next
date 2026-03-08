<?php
!defined('DEBUG') and exit('Forbidden');
 
/*=============================================
=                   让主题能接管插件hook覆盖的补丁                   =
=============================================*/

define('PATCH_MARKER', "/*HTMX_OVERRIDE_MARKER*/");
define('THEME_ID', 'abs_theme_aether');

$target_file = APP_PATH.'model/plugin.func.php';
$backup_file = $target_file.'.'.time().'.bak';

// 原子操作：创建备份
if (!copy($target_file, $backup_file)) {
    die("无法创建备份文件");
}

// 读取原始内容
$original = file_get_contents($target_file);
if (strpos($original, PATCH_MARKER) !== false) {
    unlink($backup_file);
    die("已经打过补丁");
}

// 精准定位插入点
$insert_point = '$hooks[$hookname][] = array(\'hookpath\'=>$hookpath, \'rank\'=>$rank);';
$pos = strpos($original, $insert_point);
if ($pos === false) {
    unlink($backup_file);
    die("无法定位插入点");
}

// 构造补丁代码
$patch = '
if (file_exists(APP_PATH."plugin/'.THEME_ID.'/overwrite/plugin/$dir/hook/$hookname")) {
    $hooks[$hookname][] = array(\'hookpath\'=>APP_PATH."plugin/'.THEME_ID.'/overwrite/plugin/$dir/hook/$hookname", \'rank\'=>$rank);
} else {
    $hooks[$hookname][] = array(\'hookpath\'=>$hookpath, \'rank\'=>$rank);
}
';

// 插入补丁
$patched = substr_replace($original, $patch, $pos, strlen($insert_point));

// 添加标记
$patched = str_replace('<?php\n', "<?php\n". PATCH_MARKER . '\n', $patched);

// 原子操作：写入新文件
$temp_file = tempnam(dirname($target_file), 'patch');
file_put_contents($temp_file, $patched);
if (!rename($temp_file, $target_file)) {
    unlink($backup_file);
    die("补丁应用失败");
}

// 记录安装信息
file_put_contents(dirname($target_file).'/htmx_patch.log', 
    "Installed at ".date('Y-m-d H:i:s')."\nBackup: $backup_file\n", 
    FILE_APPEND);


/*============  End of 让主题能接管插件hook覆盖的补丁  =============*/

/*=============================================
=                   金桔框架初始化                   =
=============================================*/


/* 【开始】金桔框架——常量定义 */
$BASE_URL = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$BASE_URL = empty($BASE_URL) ? '/' : '/' . trim($BASE_URL, '/') . '/';

define('WEBSITE_DIR', $_SERVER["HTTP_HOST"] . $BASE_URL);
define('PLUGIN_DIR', 'plugin/' . param(2) . '/');
define('PLUGIN_NAME', param(2));
$plugin_profile_file = file_get_contents(APP_PATH . PLUGIN_DIR . 'conf.json');
$PLUGIN_PROFILE = json_decode($plugin_profile_file, true);
$PLUGIN_SETTING = setting_get(PLUGIN_NAME . '_setting');
include_once(APP_PATH . PLUGIN_DIR . 'conf.php');
/* 【结束】金桔框架——常量定义 */

/**
 * 金桔框架——初始化设置
 * @param array $data 要导入的设置数组。
 * @return array 每一个设置项
 */
function kumquat_setting_init($data) {
	$setting = array();
	if (!isset($data['panels'])) {
		return $setting;
	} else {
		foreach ($data['panels'] as $panel => $value) {
			#$controller_name_panel = $panel;
			foreach ($value['sections'] as $section => $value) {
				#$controller_name_section = $controller_name_panel.'/'.$section;
				foreach ($value['options'] as $option => $control) {
					#$controller_name = $controller_name_section.'/'.$option;
					if (isset($control['default'])) {
						$setting[$panel][$section][$option] = $control['default'];
						#$setting[$controller_name] = $control['default'];
					} else {
						$setting[$panel][$section][$option] = 0;
						#$setting[$controller_name] = 0;
					}
				}
			}
		}
	}
	$setting['THIS_LOCATION_FRONTEND'] = WEBSITE_DIR . PLUGIN_DIR;
	$setting['THIS_LOCATION'] = PLUGIN_DIR;
	$setting['kumquat_flag'] = $data['kumquat_flag'];
	return $setting;
}

if (empty($PLUGIN_SETTING)) {
	$setting = kumquat_setting_init($data);
	setting_set(PLUGIN_NAME . '_setting', $setting);
}

/*============  End of 金桔框架初始化  =============*/