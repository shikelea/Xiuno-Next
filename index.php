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
require_once XIUNOPHP_PATH . 'request.func.php';
xn_request_id_init();
require_once APP_PATH . 'model/plugin_safe_mode.func.php';

// 引入 Composer 自动加载
if (file_exists(APP_PATH . 'vendor/autoload.php')) {
	require APP_PATH . 'vendor/autoload.php';
}

// 注册致命错误处理函数，实现插件崩溃自动隔离
register_shutdown_function(function () {
	global $conf;
	$error = error_get_last();
	plugin_safe_mode_handle_shutdown_error($error, isset($conf) && is_array($conf) ? $conf : array(), APP_PATH);
});

// !ini_get('zlib.output_compression') AND ob_start('ob_gzhandler');

//ob_start('ob_gzhandler');
require_once APP_PATH . 'install/install-state.func.php';
$install_state = xn_install_state_inspect(APP_PATH . 'conf/conf.php', APP_PATH . 'conf/.installed.lock');
if($install_state['state'] === 'missing') {
	header('Location: install/', TRUE, 302);
	exit;
}
if($install_state['state'] !== 'valid') {
	$diagnostic = xn_install_state_diagnostic($install_state['state']);
	http_response_code(503);
	header('Content-Type: text/html; charset=UTF-8');
	echo '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><title>'
		.htmlspecialchars($diagnostic['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
		.'</title></head><body><main><h1>'
		.htmlspecialchars($diagnostic['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
		.'</h1><p>'.htmlspecialchars($diagnostic['message'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
		.'</p><p>'.xn_request_id_support_html().'</p></main></body></html>';
	exit;
}
$conf = $install_state['config'];
if (!is_file(APP_PATH . 'conf/.installed.lock')) {
	@file_put_contents(APP_PATH . 'conf/.installed.lock', date('c') . "\n", LOCK_EX);
}

// 兼容 4.0.3 的配置文件	
!isset($conf['user_create_on']) and $conf['user_create_on'] = 1;
!isset($conf['logo_mobile_url']) and $conf['logo_mobile_url'] = 'view/img/logo.png';
!isset($conf['logo_pc_url']) and $conf['logo_pc_url'] = 'view/img/logo.png';
!isset($conf['logo_water_url']) and $conf['logo_water_url'] = 'view/img/water-small.png';
$conf['version'] = '4.5.1'; // 版本号随代码发布，在线更新后新 index.php 会带新版本号

// 转换为绝对路径，防止被包含时出错。
substr($conf['log_path'], 0, 2) == './' and $conf['log_path'] = APP_PATH . $conf['log_path'];

substr($conf['tmp_path'], 0, 2) == './' and $conf['tmp_path'] = APP_PATH . $conf['tmp_path'];

substr($conf['upload_path'], 0, 2) == './' and $conf['upload_path'] = APP_PATH . $conf['upload_path'];

// 插件安全与错误隔离机制: 仅由本机 CLI 或致命错误 marker 启用。
// auth_key 是 Session/HMAC 根密钥，绝不能通过 URL 恢复入口暴露。
if (plugin_safe_mode_is_active($conf, APP_PATH)) {
	$conf['disabled_plugin'] = 1;
}

$_SERVER['conf'] = $conf;
require_once APP_PATH . 'model/html_compat.func.php';

// 通用兼容层注入器：只处理完整 HTML 文档。显式非 HTML 响应按块透传，避免
// JSON 内容被改写，也避免附件下载被整份缓存在 PHP 内存中。
function xn_compat_response_content_type() {
	foreach(headers_list() as $header_line) {
		if(stripos($header_line, 'Content-Type:') !== 0) continue;
		$value = trim(substr($header_line, strlen('Content-Type:')));
		$separator = strpos($value, ';');
		if($separator !== FALSE) $value = substr($value, 0, $separator);
		return strtolower(trim($value));
	}
	return '';
}

function xn_compat_output_looks_like_html($html) {
	return is_string($html)
		&& preg_match('~^(?:\xEF\xBB\xBF)?\s*(?:<!doctype\s+html\b|<html\b)~i', $html) === 1;
}

function xn_compat_output_is_html_document($html) {
	$content_type = xn_compat_response_content_type();
	if($content_type !== '' && !in_array($content_type, array('text/html', 'application/xhtml+xml'), TRUE)) return FALSE;
	return xn_compat_output_looks_like_html($html)
		&& stripos($html, '<head') !== FALSE
		&& stripos($html, '</head>') !== FALSE;
}

function xn_compat_html_tag_attribute_values($html, $tag_name, $attribute_name) {
	if(!is_string($html) || !preg_match('/^[a-z][a-z0-9:-]*$/i', $tag_name) || !preg_match('/^[a-z][a-z0-9:-]*$/i', $attribute_name)) return array();
	$values = array();
	foreach(xn_html_scan_tags($html, $tag_name) as $token) {
		if(!empty($token['closing'])) continue;
		$value = xn_html_tag_attribute($token['tag'], $attribute_name, $found);
		if(!$found) continue;
		$values[] = xn_html_attribute_value_decode($value);
	}
	return $values;
}

function xn_compat_html_has_meta_name($html, $name) {
	foreach(xn_compat_html_tag_attribute_values($html, 'meta', 'name') as $value) {
		if(strcasecmp(trim($value), $name) === 0) return TRUE;
	}
	return FALSE;
}

function xn_compat_inject_before_closing_tag($html, $tag_name, $injection) {
	if(!is_string($html) || !is_string($injection) || $injection === '') return $html;
	foreach(xn_html_scan_tags($html, $tag_name) as $token) {
		if(empty($token['closing'])) continue;
		$offset = intval($token['start']);
		return substr($html, 0, $offset).$injection.substr($html, $offset);
	}
	return $html;
}

function xn_compat_http_origin_parts($url) {
	$parts = @parse_url((string)$url);
	if(!is_array($parts)) return FALSE;
	$scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : '';
	$host = isset($parts['host']) ? strtolower($parts['host']) : '';
	if(!in_array($scheme, array('http', 'https'), TRUE) || $host === '') return FALSE;
	$port = isset($parts['port']) ? intval($parts['port']) : ($scheme === 'https' ? 443 : 80);
	return array($scheme, $host, $port);
}

function xn_compat_current_origin() {
	$secure = function_exists('xn_cookie_secure') ? xn_cookie_secure() : FALSE;
	if(!$secure) {
		$https = strtolower(isset($_SERVER['HTTPS']) ? (string)$_SERVER['HTTPS'] : '');
		$forwarded = strtolower(isset($_SERVER['HTTP_X_FORWARDED_PROTO']) ? (string)$_SERVER['HTTP_X_FORWARDED_PROTO'] : '');
		$forwarded = trim(explode(',', $forwarded, 2)[0]);
		$secure = $https === 'on' || $https === '1' || $forwarded === 'https' || intval(isset($_SERVER['SERVER_PORT']) ? $_SERVER['SERVER_PORT'] : 0) === 443;
	}
	$scheme = $secure ? 'https' : 'http';
	$host_header = trim(isset($_SERVER['HTTP_HOST']) ? (string)$_SERVER['HTTP_HOST'] : '');
	if($host_header === '' || preg_match('/[\x00-\x20\x7F\\\\\/?#@]/', $host_header)) return '';
	$parts = @parse_url($scheme.'://'.$host_header);
	if(!is_array($parts) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) return '';
	$host = strtolower($parts['host']);
	$port = isset($parts['port']) ? ':'.intval($parts['port']) : '';
	return $scheme.'://'.$host.$port;
}

function xn_compat_urls_share_origin($left, $right) {
	$left_parts = xn_compat_http_origin_parts($left);
	$right_parts = xn_compat_http_origin_parts($right);
	return $left_parts !== FALSE && $right_parts !== FALSE && $left_parts === $right_parts;
}

function xn_compat_resolve_web_path($script_name, $path) {
	$script_name = str_replace('\\', '/', (string)$script_name);
	$path = (string)$path;
	if(substr($path, 0, 1) === '/') {
		$combined = $path;
	} else {
		$slash = strrpos($script_name, '/');
		$directory = $slash === FALSE ? '/' : substr($script_name, 0, $slash + 1);
		$combined = $directory.$path;
	}
	$segments = array();
	foreach(explode('/', $combined) as $segment) {
		if($segment === '' || $segment === '.') continue;
		if($segment === '..') {
			if(!empty($segments)) array_pop($segments);
			continue;
		}
		$segments[] = $segment;
	}
	$resolved = '/'.implode('/', $segments);
	if(substr($path, -1) === '/' && substr($resolved, -1) !== '/') $resolved .= '/';
	return $resolved;
}

function xn_compat_html_has_local_asset($html, $tag_name, $attribute_name, $filename_pattern, $is_admin_request = FALSE, $has_active_base = FALSE, $current_origin = '') {
	foreach(xn_compat_html_tag_attribute_values($html, $tag_name, $attribute_name) as $value) {
		$value = trim($value);
		if($value === '' || preg_match('/[\x00-\x20\x7F]/', $value) || strpos($value, '\\') !== FALSE) continue;
		if($has_active_base) {
			// A theme-controlled base changes the origin and path of every relative URL,
			// including root-relative values. Only an explicit same-origin absolute URL
			// can safely suppress the core fallback in this document shape.
			if($current_origin === '' || !parse_url($value, PHP_URL_SCHEME) || !xn_compat_urls_share_origin($value, $current_origin)) continue;
			$path = (string)parse_url($value, PHP_URL_PATH);
		} else {
			if(substr($value, 0, 2) === '//' || parse_url($value, PHP_URL_SCHEME)) continue;
			$path = preg_split('/[?#]/', $value, 2)[0];
		}
		if(!preg_match($filename_pattern, $path)) continue;
		// index.php is at the Web root while admin/index.php is one directory
		// deeper. A bare view/... URL on an admin response is therefore broken.
		if($is_admin_request && substr($path, 0, 1) !== '/' && substr($path, 0, 3) !== '../') continue;
		return TRUE;
	}
	return FALSE;
}

function xn_compat_post_submit_context() {
	$method = strtoupper(isset($_SERVER['REQUEST_METHOD']) ? (string)$_SERVER['REQUEST_METHOD'] : '');
	if($method !== 'GET') return FALSE;
	$route = isset($GLOBALS['route']) ? (string)$GLOBALS['route'] : '';
	$route_action = isset($GLOBALS['action']) ? (string)$GLOBALS['action'] : '';
	if($route === 'thread' && $route_action === 'create') return array($route, $route_action);
	if($route === 'post' && in_array($route_action, array('create', 'update'), TRUE)) return array($route, $route_action);
	return FALSE;
}

function xn_compat_post_submit_html($route, $route_action) {
	$key = $route === 'thread' ? 'thread_create' : ($route_action === 'update' ? 'post_update' : 'post_create');
	$fallback = array(
		'thread_create'=>'Create topic',
		'post_create'=>'Reply',
		'post_update'=>'Update post',
		'submiting'=>'Submitting',
	);
	$label = function_exists('lang') ? lang($key) : $fallback[$key];
	$loading = function_exists('lang') ? lang('submiting') : $fallback['submiting'];
	$label = htmlspecialchars((string)$label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	$loading = htmlspecialchars((string)$loading, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	return "\n".'<div class="xn-compat-post-submit text-end mt-3">'
		.'<button type="submit" class="btn btn-primary" id="submit" data-xn-compat-post-submit="1" data-loading-text="'.$loading.'..."> '
		.$label.' </button></div>'."\n";
}

function xn_compat_inject_output($html) {
	if(!xn_compat_output_is_html_document($html)) return $html;
	$post_context = xn_compat_post_submit_context();
	if($post_context !== FALSE) {
		$html = xn_html_inject_post_submit_fallback(
			$html,
			$post_context[0],
			$post_context[1],
			xn_compat_post_submit_html($post_context[0], $post_context[1])
		);
	}

	$conf = $_SERVER['conf'];
	// view_url 只允许相对路径或以 / 开头的路径，防止注入外部域名或非 Web scheme
	$view_url = isset($conf['view_url']) ? (string)$conf['view_url'] : 'view/';
	if (
		preg_match('/[\x00-\x20\x7F]/', $view_url)
		|| strpos($view_url, '\\') !== FALSE
		|| preg_match('/[?#]/', $view_url)
		|| substr($view_url, 0, 2) === '//'
		|| parse_url($view_url, PHP_URL_SCHEME)
	) {
		$view_url = 'view/';
	}
	$script_name = str_replace('\\', '/', isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '');
	$is_admin_request = strpos($script_name, '/admin/') !== FALSE || preg_match('#(^|/)admin/index\.php$#', $script_name);
	if ($is_admin_request && substr($view_url, 0, 1) !== '/' && substr($view_url, 0, 3) !== '../') {
		$view_url = '../' . $view_url;
	}
	$base_hrefs = xn_compat_html_tag_attribute_values($html, 'base', 'href');
	$has_active_base = !empty($base_hrefs);
	$current_origin = $has_active_base ? xn_compat_current_origin() : '';
	$can_inject_assets = !$has_active_base || $current_origin !== '';
	if($has_active_base && $current_origin !== '') {
		$view_url = $current_origin.xn_compat_resolve_web_path($script_name, $view_url);
	}
	$view_url = htmlspecialchars($view_url, ENT_QUOTES, 'UTF-8');
	$sv = isset($conf['static_version']) ? htmlspecialchars($conf['static_version'], ENT_QUOTES, 'UTF-8') : '';

	$head_inject = '';

	// 1) CSRF <meta> tag — bs4-compat.js 会从此读取 token，无需额外内嵌脚本
	if (!xn_compat_html_has_meta_name($html, 'csrf-token') && function_exists('csrf_token')) {
		$head_inject .= '<meta name="csrf-token" content="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">' . "\n";
	}

	// 2) Font Awesome 4 — legacy icon-* plugins still rely on its glyphs.
	$has_font_awesome = xn_compat_html_has_local_asset($html, 'link', 'href', '~(?:^|/)font-awesome(?:\.min)?\.css$~i', $is_admin_request, $has_active_base, $current_origin);
	if (!$has_font_awesome && $can_inject_assets) {
		$head_inject .= '<link rel="stylesheet" href="' . $view_url . 'css/font-awesome.min.css' . $sv . '">' . "\n";
	}

	// 3) bs4-compat.css — 必须同时识别源码版与压缩版，否则默认模板加载 .min 时会被重复注入
	$has_bs4_css = xn_compat_html_has_local_asset($html, 'link', 'href', '~(?:^|/)bs4-compat(?:\.min)?\.css$~i', $is_admin_request, $has_active_base, $current_origin);
	if (!$has_bs4_css && $can_inject_assets) {
		$head_inject .= '<link rel="stylesheet" href="' . $view_url . 'css/bs4-compat.css' . $sv . '">' . "\n";
	}

	// 4) bs4-compat.js — 缺失时在 head 末尾加载，使兼容运行时能在主题正文
	// 内联脚本执行前建立观察器；它会在 jQuery/Bootstrap 脚本 load 后重新绑定。
	$has_bs4_js = xn_compat_html_has_local_asset($html, 'script', 'src', '~(?:^|/)bs4-compat(?:\.min)?\.js$~i', $is_admin_request, $has_active_base, $current_origin);
	if (!$has_bs4_js && $can_inject_assets) {
		$head_inject .= '<script src="' . $view_url . 'js/bs4-compat.js' . $sv . '"></script>' . "\n";
	}

	if ($head_inject) {
		$html = xn_compat_inject_before_closing_tag($html, 'head', $head_inject);
	}

	return $html;
}

function xn_compat_output_handler($chunk, $phase = 0) {
	static $buffer = '';
	static $passthrough = FALSE;
	if(($phase & PHP_OUTPUT_HANDLER_CLEAN) === PHP_OUTPUT_HANDLER_CLEAN) {
		$buffer = '';
		return '';
	}
	if($passthrough) return $chunk;

	$content_type = xn_compat_response_content_type();
	if($content_type !== '' && !in_array($content_type, array('text/html', 'application/xhtml+xml'), TRUE)) {
		$passthrough = TRUE;
		$output = $buffer.$chunk;
		$buffer = '';
		return $output;
	}

	$buffer .= $chunk;
	$is_final = ($phase & PHP_OUTPUT_HANDLER_FINAL) === PHP_OUTPUT_HANDLER_FINAL;
	if($is_final) {
		$output = xn_compat_inject_output($buffer);
		$buffer = '';
		return $output;
	}

	// Responses without an explicit Content-Type are held only long enough to identify a normal
	// HTML document. Unknown/binary streams become passthrough after a bounded 64 KiB probe.
	if($content_type === '' && strlen($buffer) >= 65536 && !xn_compat_output_looks_like_html($buffer)) {
		$passthrough = TRUE;
		$output = $buffer;
		$buffer = '';
		return $output;
	}
	return '';
}
ob_start('xn_compat_output_handler', 8192);
if (DEBUG > 1) {
	include XIUNOPHP_PATH . 'xiunophp.php';
}
else {
	include XIUNOPHP_PATH . 'xiunophp.min.php';
}

// Fail before plugin compilation and Session startup when the configured database is unavailable.
// Continuing with FALSE reads makes an outage look like an empty forum and produces misleading
// Session/CSRF errors in browsers and legacy plugin AJAX flows.
if(!is_object($db) || !db_connect($db)) {
	$service_message = '数据库服务暂时不可用，请稍后重试或联系管理员。';
	http_response_code(503);
	header('Retry-After: 60');
	header('Cache-Control: no-store');
	if($ajax || param(0, '') === 'api') {
		header('Content-Type: application/json; charset=UTF-8');
		echo xn_json_encode(array('code'=>'-1', 'message'=>$service_message, 'request_id'=>xn_request_id_current()));
	} else {
		header('Content-Type: text/html; charset=UTF-8');
		echo '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><title>服务暂时不可用</title></head>'
			.'<body><main><h1>服务暂时不可用</h1><p>'.htmlspecialchars($service_message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
			.'</p><p>'.xn_request_id_support_html().'</p></main></body></html>';
	}
	exit;
}

include APP_PATH . 'model/plugin.func.php';
include _include(APP_PATH . 'model.inc.php');
include _include(APP_PATH . 'index.inc.php');

//file_put_contents((ini_get('xhprof.output_dir') ? : '/tmp') . '/' . uniqid() . '.xhprof.xhprof', serialize(xhprof_disable()));

?>
