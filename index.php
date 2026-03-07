<?php
/*
 * Copyright (C) xiuno.com
 */

//xhprof_enable();

//$_SERVER['REQUEST_URI'] = '/?user-login.htm';
//$_SERVER['REQUEST_METHOD'] = 'POST';
//$_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';
//$_COOKIE['bbs_sid'] = 'e1d8c2790b9dd08267e6ea2595c3bc82';
//$postdata = 'email=admin&password=c4ca4238a0b923820dcc509a6f75849b';
//parse_str($postdata, $_POST);

// 0: Production mode; 1: Developer mode; 2: Plugin developement mode;
// 0: 线上模式; 1: 调试模式; 2: 插件开发模式;
!defined('DEBUG') and define('DEBUG', 0);
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_WARNING);
define('APP_PATH', dirname(__FILE__) . '/'); // __DIR__
!defined('ADMIN_PATH') and define('ADMIN_PATH', APP_PATH . 'admin/');
!defined('XIUNOPHP_PATH') and define('XIUNOPHP_PATH', APP_PATH . 'xiunophp/');

// 引入 Composer 自动加载
if (file_exists(APP_PATH . 'vendor/autoload.php')) {
	require APP_PATH . 'vendor/autoload.php';
}

// 注册致命错误处理函数，实现插件崩溃自动隔离
register_shutdown_function(function () {
	$error = error_get_last();
	if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
		$error_file = str_replace('\\', '/', $error['file']);
		$app_plugin_path = str_replace('\\', '/', APP_PATH . 'plugin/');
		$app_tmp_path = str_replace('\\', '/', APP_PATH . 'tmp/');
		if (strpos($error_file, $app_plugin_path) === 0 || strpos($error_file, $app_tmp_path) === 0) {
			$safe_file = APP_PATH . 'tmp/safe_mode.php';
			if (!is_file($safe_file)) {
				$msg = "<?php\n// 自动进入安全模式\n// 错误信息: {$error['message']}\n// 文件: {$error['file']}\n// 行数: {$error['line']}\n";
				@file_put_contents($safe_file, $msg);
			}
		}
	}
});

// !ini_get('zlib.output_compression') AND ob_start('ob_gzhandler');

//ob_start('ob_gzhandler');
$conf = (@include APP_PATH . 'conf/conf.php') or exit('<script>window.location="install/"</script>');

// 兼容 4.0.3 的配置文件	
!isset($conf['user_create_on']) and $conf['user_create_on'] = 1;
!isset($conf['logo_mobile_url']) and $conf['logo_mobile_url'] = 'view/img/logo.png';
!isset($conf['logo_pc_url']) and $conf['logo_pc_url'] = 'view/img/logo.png';
!isset($conf['logo_water_url']) and $conf['logo_water_url'] = 'view/img/water-small.png';
!isset($conf['version']) AND $conf['version'] = '4.4.4'; // 版本号优先读取 conf.php，此处为兜底默认值

// 转换为绝对路径，防止被包含时出错。
substr($conf['log_path'], 0, 2) == './' and $conf['log_path'] = APP_PATH . $conf['log_path'];

substr($conf['tmp_path'], 0, 2) == './' and $conf['tmp_path'] = APP_PATH . $conf['tmp_path'];

substr($conf['upload_path'], 0, 2) == './' and $conf['upload_path'] = APP_PATH . $conf['upload_path'];

// 插件安全与错误隔离机制: 运行安全模式跳过插件加载
$safe_key = isset($_GET['safe_mode']) ? $_GET['safe_mode'] : '';
if (is_file(APP_PATH . 'tmp/safe_mode.php') || is_file(APP_PATH . 'tmp/safe_mode') || ($safe_key && isset($conf['auth_key']) && $safe_key === $conf['auth_key'])) {
	$conf['disabled_plugin'] = 1;
}

$_SERVER['conf'] = $conf;

if (DEBUG > 1) {
	include XIUNOPHP_PATH . 'xiunophp.php';
}
else {
	include XIUNOPHP_PATH . 'xiunophp.min.php';
}

// 测试数据库连接 / try to connect database
//db_connect() OR exit($errstr);

include APP_PATH . 'model/plugin.func.php';
include _include(APP_PATH . 'model.inc.php');
include _include(APP_PATH . 'index.inc.php');

//file_put_contents((ini_get('xhprof.output_dir') ? : '/tmp') . '/' . uniqid() . '.xhprof.xhprof', serialize(xhprof_disable()));

?>
