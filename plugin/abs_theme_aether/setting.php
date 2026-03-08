<?php
!defined('DEBUG') and exit('Access Denied.');
/* 【开始】金桔框架——常量定义 */
//如果网站在子目录下，这个很有必要
$BASE_URL = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$BASE_URL = empty($BASE_URL) ? '/' : '/' . trim($BASE_URL, '/') . '/';
$HTTP_TYPE = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';
//网站的URL
define('WEBSITE_DIR', $_SERVER["HTTP_HOST"] . $BASE_URL);
//这个插件的文件夹URL
define('PLUGIN_DIR', 'plugin/' . param(2) . '/');
//这个插件的view文件夹URL
define('PLUGIN_VIEW_DIR', WEBSITE_DIR . PLUGIN_DIR . "view/");
//插件ID
define('PLUGIN_NAME', param(2));
//插件的conf.json文件
$plugin_profile_file = file_get_contents(APP_PATH . PLUGIN_DIR . 'conf.json');
//插件的conf
$PLUGIN_PROFILE = json_decode($plugin_profile_file, true);
//插件设置
$PLUGIN_SETTING = setting_get(PLUGIN_NAME . '_setting');
//金桔框架的位置
$kumquat_location = APP_PATH . PLUGIN_DIR . '/inc/';
//导入框架所需文件
include_once($kumquat_location . 'kumquat_utility.func.php');
include_once($kumquat_location . 'kumquat_core.func.php');
include_once($kumquat_location . 'kumquat_form.func.php');
include_once(APP_PATH . PLUGIN_DIR . 'conf.php');
//kumquat_init(PLUGIN_NAME);
/* 【结束】金桔框架——常量定义 */

if ($method == 'GET') {
	//var_dump($data);
	//var_dump($PLUGIN_SETTING);
	//var_dump(group_list_cache());
	//echo serialize($PLUGIN_SETTING);
	include _include(APP_PATH . PLUGIN_DIR . 'setting.htm');
} else {
	($data['kumquat_config']['allow_reset_settings'] && param('kumquat_flag/reset_settings'))
		? setting_set(PLUGIN_NAME . '_setting', kumquat_reset_setting($data))
		: setting_set(PLUGIN_NAME . '_setting', kumquat_save_setting($data));
	message(0, lang('modify_successfully'));
}