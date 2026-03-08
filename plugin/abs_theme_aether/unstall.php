<?php
!defined('DEBUG') and exit('Forbidden');

/*=============================================
=                   让主题能接管插件hook覆盖的补丁                   =
=============================================*/
$target_file = APP_PATH . 'model/plugin.func.php';
$backup_pattern = $target_file . '.*.bak';

// 查找最新备份
$backups = glob($backup_pattern);
if (empty($backups)) {
    die("找不到备份文件");
}

// 按时间排序获取最新备份
usort($backups, function ($a, $b) {
    return filemtime($b) - filemtime($a);
});
$latest_backup = $backups[0];

// 验证备份有效性
$backup_content = file_get_contents($latest_backup);
if (strpos($backup_content, "/*HTMX_OVERRIDE_MARKER*/") !== false) {
    die("备份文件已被污染");
}

// 原子操作恢复
$temp_file = tempnam(dirname($target_file), 'restore');
file_put_contents($temp_file, $backup_content);
if (!rename($temp_file, $target_file)) {
    die("恢复失败");
}

// 清理旧备份（保留最近3个）
array_map('unlink', array_slice($backups, 3));

// 记录卸载
file_put_contents(
    dirname($target_file) . '/htmx_patch.log',
    "Uninstalled at " . date('Y-m-d H:i:s') . "\nRestored from: $latest_backup\n",
    FILE_APPEND
);

    /*============  End of 让主题能接管插件hook覆盖的补丁  =============*/

/*=============================================
=                   金桔框架初始化                   =
=============================================*/


/* 【开始】金桔框架——常量定义 */
define('PLUGIN_DIR', 'plugin/' . param(2) . '/');
define('PLUGIN_NAME', param(2));

$PLUGIN_SETTING = setting_get(PLUGIN_NAME . '_setting');

/* 【结束】金桔框架——常量定义 */

if ($PLUGIN_SETTING['kumquat_flag']['delete_plugin_settings']) {
	setting_delete(PLUGIN_NAME . '_setting');
}
/*============  End of 金桔框架初始化  =============*/