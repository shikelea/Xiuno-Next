<?php

!defined('DEBUG') AND exit('Access Denied.');

// GitHub 仓库配置
define('GITHUB_REPO', 'shikelea/Xiuno-Next');
define('GITHUB_API_URL', 'https://api.github.com/repos/' . GITHUB_REPO);

$action = param(1);
empty($action) AND $action = 'check';

// ==================== 检查更新 ====================
if ($action == 'check') {

	$header['title'] = lang('update_title');
	$header['mobile_title'] = lang('update_title');

	$current_version = $conf['version'];
	$latest = update_github_latest_release();
	$latest_version = '';
	$has_update = FALSE;
	$error = '';
	$changelog = '';
	$download_url = '';

	if ($latest === FALSE) {
		$error = lang('update_check_failed');
	} else {
		$latest_version = ltrim($latest['tag_name'], 'vV');
		$has_update = version_compare($latest_version, $current_version) > 0;
		$changelog = isset($latest['body']) ? $latest['body'] : '';
		$download_url = isset($latest['zipball_url']) ? $latest['zipball_url'] : '';
	}

	include _include(ADMIN_PATH . "view/htm/update.htm");

// ==================== 执行更新 ====================
} elseif ($action == 'download') {

	$method != 'POST' AND message(-1, 'Method Not Allowed');

	set_time_limit(120);

	$latest = update_github_latest_release();
	if ($latest === FALSE) {
		message(-1, lang('update_check_failed'));
	}

	$latest_version = ltrim($latest['tag_name'], 'vV');
	if (version_compare($latest_version, $conf['version']) <= 0) {
		message(0, lang('update_already_latest'));
	}

	$download_url = $latest['zipball_url'];
	if (empty($download_url)) {
		message(-1, lang('update_download_url_empty'));
	}

	// 下载 zip
	$zipfile = $conf['tmp_path'] . 'update_' . $latest_version . '.zip';
	$zipdata = update_github_download($download_url);
	if ($zipdata === FALSE || empty($zipdata)) {
		message(-1, lang('update_download_failed'));
	}
	file_put_contents($zipfile, $zipdata);

	// 解压到临时目录
	include XIUNOPHP_PATH . 'xn_zip.func.php';
	$extract_dir = $conf['tmp_path'] . 'update_extract/';
	if (is_dir($extract_dir)) {
		rmdir_recusive($extract_dir, 1);
	}
	xn_mkdir($extract_dir);
	xn_unzip($zipfile, $extract_dir);

	// GitHub zip 解压后有一层包裹目录，找到它
	$source_dir = update_find_source_dir($extract_dir);
	if ($source_dir === FALSE) {
		message(-1, lang('update_extract_failed'));
	}

	// 受保护的目录和文件（不覆盖）
	$protected = array('conf', 'tmp', 'upload', 'plugin', '.htaccess', '.git', '.gitignore');

	// 复制文件到项目根目录
	$app_root = APP_PATH;
	$result = update_copy_files($source_dir, $app_root, $protected);

	// 更新 conf.php 中的版本号
	update_conf_version($latest_version);

	// 清理临时文件
	@unlink($zipfile);
	rmdir_recusive($extract_dir, 1);

	// 清理缓存
	$cachedir = $conf['tmp_path'];
	$cachefiles = glob($cachedir . '*.php');
	if ($cachefiles) {
		foreach ($cachefiles as $f) @unlink($f);
	}

	message(0, lang('update_success', array('version' => $latest_version)));

}

// ==================== 工具函数 ====================

/**
 * 调用 GitHub API 获取最新 Release
 */
function update_github_latest_release() {
	$url = GITHUB_API_URL . '/releases/latest';
	$s = update_http_get_json($url);
	if ($s === FALSE) {
		// 没有 release 时尝试获取最新 tag
		$url = GITHUB_API_URL . '/tags';
		$s = update_http_get_json($url);
		if ($s === FALSE || empty($s)) return FALSE;
		// 取第一个 tag 模拟 release 格式
		$tag = $s[0];
		return array(
			'tag_name' => $tag['name'],
			'body' => '',
			'zipball_url' => $tag['zipball_url'],
		);
	}
	return $s;
}

/**
 * 发起 HTTPS GET 请求，返回解码后的 JSON
 */
function update_http_get_json($url) {
	$response = update_http_get($url);
	if ($response === FALSE || empty($response)) return FALSE;
	$data = xn_json_decode($response);
	if (empty($data)) return FALSE;
	// GitHub API 错误检查
	if (isset($data['message']) && isset($data['documentation_url'])) return FALSE;
	return $data;
}

/**
 * HTTPS GET 请求（带 User-Agent，GitHub API 必须）
 */
function update_http_get($url, $timeout = 10) {
	// 优先使用 cURL
	if (function_exists('curl_init')) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Xiuno-Next-Updater');
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Accept: application/vnd.github.v3+json',
		));
		$response = curl_exec($ch);
		$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		if ($httpcode >= 200 && $httpcode < 400 && $response !== FALSE) {
			return $response;
		}
		return FALSE;
	}

	// 备选：file_get_contents
	$opts = array(
		'http' => array(
			'method' => 'GET',
			'timeout' => $timeout,
			'header' => "User-Agent: Xiuno-Next-Updater\r\nAccept: application/vnd.github.v3+json\r\n",
		),
		'ssl' => array(
			'verify_peer' => false,
		),
	);
	$ctx = stream_context_create($opts);
	$s = @file_get_contents($url, false, $ctx);
	return $s !== FALSE ? $s : FALSE;
}

/**
 * 从 GitHub 下载文件（支持重定向）
 */
function update_github_download($url) {
	return update_http_get($url, 60);
}

/**
 * 找到解压后的源码目录（GitHub zip 有一层包裹）
 */
function update_find_source_dir($extract_dir) {
	$dirs = glob($extract_dir . '*', GLOB_ONLYDIR);
	if (empty($dirs)) return FALSE;
	// 通常只有一个目录
	$dir = $dirs[0] . '/';
	$dir = str_replace('\\', '/', $dir);
	// 验证是否包含关键文件
	if (is_file($dir . 'index.php') || is_dir($dir . 'model')) {
		return $dir;
	}
	return FALSE;
}

/**
 * 递归复制文件，跳过受保护的目录
 */
function update_copy_files($src, $dst, $protected = array(), $relative = '') {
	$count = 0;
	$src = rtrim(str_replace('\\', '/', $src), '/') . '/';
	$dst = rtrim(str_replace('\\', '/', $dst), '/') . '/';

	$items = glob($src . '*');
	if (empty($items)) return $count;

	foreach ($items as $item) {
		$item = str_replace('\\', '/', $item);
		$name = basename($item);
		$rel = $relative ? $relative . '/' . $name : $name;

		// 跳过受保护的顶层目录/文件
		if (empty($relative) && in_array($name, $protected)) {
			continue;
		}

		if (is_dir($item)) {
			if (!is_dir($dst . $name)) {
				xn_mkdir($dst . $name);
			}
			$count += update_copy_files($item . '/', $dst . $name . '/', $protected, $rel);
		} else {
			if (@copy($item, $dst . $name)) {
				$count++;
			}
		}
	}
	return $count;
}

/**
 * 更新 conf.php 中的版本号
 */
function update_conf_version($new_version) {
	$conffile = APP_PATH . 'conf/conf.php';
	if (!is_file($conffile)) return FALSE;
	$s = file_get_contents($conffile);
	if ($s === FALSE) return FALSE;
	$s = preg_replace("/'version'\s*=>\s*'[^']*'/", "'version' => '$new_version'", $s);
	return file_put_contents($conffile, $s);
}

?>
