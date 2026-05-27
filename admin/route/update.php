<?php

!defined('DEBUG') AND exit('Access Denied.');

// GitHub 仓库配置
define('GITHUB_REPO', 'shikelea/Xiuno-Next');
define('GITHUB_API_URL', 'https://api.github.com/repos/' . GITHUB_REPO);

// 内置 GitHub 加速代理列表
$github_proxies = array(
	array('name' => 'EdgeOne (腾讯CDN)', 'url' => 'https://edgeone.gh-proxy.com'),
	array('name' => 'GH-Proxy',          'url' => 'https://gh-proxy.com'),
	array('name' => 'LLKK',              'url' => 'https://gh.llkk.cc'),
);

$action = param(1);
empty($action) AND $action = 'check';

// ==================== 检查更新 ====================
if ($action == 'check') {

	$header['title'] = lang('update_title');
	$header['mobile_title'] = lang('update_title');

	// 读取已保存的代理设置
	$saved_proxy = isset($conf['github_proxy']) ? $conf['github_proxy'] : '';
	$proxy_fallback = FALSE;

	$current_version = $conf['version'];
	$latest = update_github_latest_release($saved_proxy);
	if ($latest === FALSE && !empty($saved_proxy)) {
		// 代理可能不可用：自动回退直连重试
		$latest = update_github_latest_release('');
		$proxy_fallback = ($latest !== FALSE);
	}
	$latest_version = '';
	$has_update = FALSE;
	$error = '';
	$changelog = '';
	$download_url = '';
	$rollback_backup = update_latest_backup();

	if ($latest === FALSE) {
		$error = lang('update_check_failed');
	} else {
		$latest_version = ltrim($latest['tag_name'], 'vV');
		$has_update = version_compare($latest_version, $current_version) > 0;
		$changelog = isset($latest['body']) ? $latest['body'] : '';
		$download_url = 'https://github.com/' . GITHUB_REPO . '/archive/refs/tags/' . $latest['tag_name'] . '.zip';
	}

	include _include(ADMIN_PATH . "view/htm/update.htm");

// ==================== 执行更新 ====================
// ==================== 测试代理连通性 ====================
} elseif ($action == 'test_proxy') {

	$method != 'POST' AND message(-1, 'Method Not Allowed');

	$proxy_url = trim(param('proxy_url', '', 'POST'));
	// 测试目标：GitHub API（小请求，快速响应）
	$test_url = GITHUB_API_URL . '/releases/latest';
	if (!empty($proxy_url)) {
		$proxy_url = rtrim($proxy_url, '/');
		$test_url = $proxy_url . '/' . $test_url;
	}

	$start = microtime(true);
	$response = update_http_get($test_url, 8);
	$elapsed = round((microtime(true) - $start) * 1000);

	if ($response === FALSE || empty($response)) {
		message(-1, lang('update_proxy_unreachable'));
	}
	$data = xn_json_decode($response);
	if (empty($data) || (isset($data['message']) && isset($data['documentation_url']))) {
		message(-1, lang('update_proxy_unreachable'));
	}

	message(0, array('latency' => $elapsed));

// ==================== 保存代理设置 ====================
} elseif ($action == 'save_proxy') {

	$method != 'POST' AND message(-1, 'Method Not Allowed');

	$proxy_url = trim(param('proxy_url', '', 'POST'));
	update_conf_setting('github_proxy', $proxy_url);
	message(0, lang('update_proxy_saved'));

// ==================== 执行更新 ====================
} elseif ($action == 'download') {

	$method != 'POST' AND message(-1, 'Method Not Allowed');

	set_time_limit(120);

	// 读取代理设置
	$proxy = isset($conf['github_proxy']) ? $conf['github_proxy'] : '';
	$proxy_fallback_used = FALSE;

	$latest = update_github_latest_release($proxy);
	if ($latest === FALSE) {
		// 代理可能不可用：自动回退直连重试
		if (!empty($proxy)) {
			$latest = update_github_latest_release('');
			$proxy_fallback_used = ($latest !== FALSE);
		}
		if ($latest === FALSE) message(-1, lang('update_check_failed'));
	}

	$latest_version = ltrim($latest['tag_name'], 'vV');
	if (version_compare($latest_version, $conf['version']) <= 0) {
		message(0, lang('update_already_latest'));
	}

	// 使用 github.com/archive 直接下载链接（不走 API，无速率限制，代理兼容性更好）
	$tag_name = $latest['tag_name'];
	$download_url = 'https://github.com/' . GITHUB_REPO . '/archive/refs/tags/' . $tag_name . '.zip';

	// 通过代理下载
	$actual_url = update_proxied_url($download_url, $proxy);

	// 下载 zip
	$zipfile = $conf['tmp_path'] . 'update_' . $latest_version . '.zip';
	$dl_error = '';
	$zipdata = update_github_download_binary($actual_url, 120, $dl_error);
	if ($zipdata === FALSE || strlen($zipdata) < 100) {
		// 代理不可用：自动回退直连重试
		if (!empty($proxy)) {
			$dl_error = '';
			$zipdata = update_github_download_binary($download_url, 120, $dl_error);
			$proxy_fallback_used = TRUE;
		}
		if ($zipdata === FALSE || strlen($zipdata) < 100) {
			$detail = $dl_error ? ' (' . $dl_error . ')' : '';
			message(-1, lang('update_download_failed') . $detail);
		}
	}

	// 校验 ZIP 文件魔数头（PK\x03\x04）
	if (substr($zipdata, 0, 2) !== 'PK') {
		// 下载到的不是 ZIP，可能是代理返回的 HTML/JSON 错误页
		$hint = substr($zipdata, 0, 200);
		message(-1, lang('update_not_zip') . ' (' . htmlspecialchars($hint) . ')');
	}

	file_put_contents($zipfile, $zipdata);

	// 检查 ZipArchive 扩展
	if (!class_exists('ZipArchive')) {
		@unlink($zipfile);
		message(-1, lang('update_no_ziparchive'));
	}

	// 解压到临时目录
	include XIUNOPHP_PATH . 'xn_zip.func.php';
	$extract_dir = $conf['tmp_path'] . 'update_extract/';
	if (is_dir($extract_dir)) {
		rmdir_recusive($extract_dir, 1);
	}
	xn_mkdir($extract_dir);

	$zip = new ZipArchive;
	$open_result = $zip->open($zipfile);
	if ($open_result !== TRUE) {
		@unlink($zipfile);
		message(-1, lang('update_extract_failed') . ' (ZipArchive error: ' . $open_result . ')');
	}
	$zip_error = '';
	if (!update_zip_validate($zip, $zip_error)) {
		$zip->close();
		@unlink($zipfile);
		rmdir_recusive($extract_dir, 1);
		message(-1, lang('update_not_zip') . ' (' . htmlspecialchars($zip_error) . ')');
	}
	$zip->extractTo($extract_dir);
	$zip->close();

	// GitHub zip 解压后有一层包裹目录，找到它
	$source_dir = update_find_source_dir($extract_dir);
	if ($source_dir === FALSE) {
		@unlink($zipfile);
		rmdir_recusive($extract_dir, 1);
		message(-1, lang('update_extract_failed'));
	}

	// 受保护的目录和文件（不覆盖）
	$protected = array('conf', 'tmp', 'upload', 'plugin', '.htaccess', '.git', '.gitignore');

	// 复制文件到项目根目录
	$app_root = APP_PATH;
	$backup_dir = $conf['tmp_path'] . 'update_backup_' . date('Ymd_His') . '/';
	xn_mkdir($backup_dir);
	$backup_error = '';
	$backup_result = update_backup_existing_files($source_dir, $app_root, $protected, $backup_dir, $backup_error);
	if ($backup_result === FALSE) {
		@unlink($zipfile);
		rmdir_recusive($extract_dir, 1);
		message(-1, lang('update_backup_failed') . ' (' . htmlspecialchars($backup_error) . ')');
	}
	// 备份 conf.php 后再写版本号，确保回滚时版本状态也能恢复。
	$conf_backup_error = '';
	if (is_file(APP_PATH . 'conf/conf.php') && !update_backup_file(APP_PATH . 'conf/conf.php', $backup_dir . 'conf/conf.php', $conf_backup_error)) {
		@unlink($zipfile);
		rmdir_recusive($extract_dir, 1);
		message(-1, lang('update_backup_failed') . ' (' . htmlspecialchars($conf_backup_error) . ')');
	}
	$result = update_copy_files($source_dir, $app_root, $protected);
	$result['backed_up'] = $backup_result['backed_up'] + (is_file($backup_dir . 'conf/conf.php') ? 1 : 0);
	$zip_sha256 = hash_file('sha256', $zipfile);
	@file_put_contents(APP_PATH . 'log/update.log', date('Y-m-d H:i:s') . " updated to v{$latest_version}, copied={$result['copied']}, backed_up={$result['backed_up']}, zip_sha256={$zip_sha256}, backup={$backup_dir}\n", FILE_APPEND);

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

	$msg = lang('update_success', array('version' => $latest_version));
	if (!empty($proxy_fallback_used)) $msg .= ' ' . lang('update_proxy_fallback_used');
	if (!empty($result['backed_up'])) $msg .= ' Backup: ' . str_replace(APP_PATH, '', $backup_dir);
	message(0, $msg);

// ==================== 回滚到最近备份 ====================
} elseif ($action == 'rollback') {

	$method != 'POST' AND message(-1, 'Method Not Allowed');

	$backup = trim(param('backup', '', 'POST'));
	$backup_dir = update_resolve_backup($backup);
	if ($backup_dir === FALSE) {
		message(-1, lang('update_rollback_no_backup'));
	}

	$restore_error = '';
	$result = update_restore_backup($backup_dir, APP_PATH, $restore_error);
	if ($result === FALSE) {
		message(-1, lang('update_rollback_failed') . ' (' . htmlspecialchars($restore_error) . ')');
	}

	// 清理缓存
	$cachefiles = glob($conf['tmp_path'] . '*.php');
	if ($cachefiles) {
		foreach ($cachefiles as $f) @unlink($f);
	}

	@file_put_contents(APP_PATH . 'log/update.log', date('Y-m-d H:i:s') . " rollback from {$backup_dir}, restored={$result['restored']}\n", FILE_APPEND);
	message(0, lang('update_rollback_success', array('count' => $result['restored'])));

}

// ==================== 工具函数 ====================

/**
 * 调用 GitHub API 获取最新 Release
 */
function update_github_latest_release($proxy = '') {
	$url = update_proxied_url(GITHUB_API_URL . '/releases/latest', $proxy);
	$s = update_http_get_json($url);
	if ($s === FALSE) {
		// 没有 release 时尝试获取最新 tag
		$url = update_proxied_url(GITHUB_API_URL . '/tags', $proxy);
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
 * 将 GitHub URL 通过代理加速
 */
function update_proxied_url($url, $proxy = '') {
	if (empty($proxy)) return $url;
	$proxy = rtrim($proxy, '/');
	return $proxy . '/' . $url;
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
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
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
			'verify_peer' => true,
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
 * 下载二进制文件（不发送 JSON Accept 头，避免代理返回非 ZIP 内容）
 */
function update_github_download_binary($url, $timeout = 120, &$error = '') {
	if (function_exists('curl_init')) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Xiuno-Next-Updater');
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Accept: */*',
		));
		$response = curl_exec($ch);
		$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curl_error = curl_error($ch);
		$curl_errno = curl_errno($ch);
		$final_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
		curl_close($ch);
		if ($httpcode >= 200 && $httpcode < 400 && $response !== FALSE) {
			return $response;
		}
		$error = "HTTP $httpcode";
		if ($curl_errno) $error .= ", cURL #{$curl_errno}: {$curl_error}";
		if ($final_url !== $url) $error .= ", redirect: " . substr($final_url, 0, 80);
		return FALSE;
	}
	$opts = array(
		'http' => array(
			'method' => 'GET',
			'timeout' => $timeout,
			'header' => "User-Agent: Xiuno-Next-Updater\r\nAccept: */*\r\n",
			'follow_location' => 1,
			'max_redirects' => 10,
		),
		'ssl' => array(
			'verify_peer' => true,
		),
	);
	$ctx = stream_context_create($opts);
	$s = @file_get_contents($url, false, $ctx);
	if ($s === FALSE) $error = 'file_get_contents failed';
	return $s !== FALSE ? $s : FALSE;
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
 * 校验 ZIP 内路径，防止 Zip Slip 覆盖应用目录外的文件。
 */
function update_zip_validate($zip, &$error = '') {
	$num = $zip->numFiles;
	for ($i = 0; $i < $num; $i++) {
		$name = $zip->getNameIndex($i);
		$name = str_replace('\\', '/', $name);
		if ($name === '' || strpos($name, "\0") !== FALSE) {
			$error = 'Invalid empty or binary path in zip';
			return FALSE;
		}
		if ($name[0] === '/' || preg_match('#^[A-Za-z]:#', $name) || preg_match('#(^|/)\.\.(/|$)#', $name)) {
			$error = 'Unsafe path in zip: ' . $name;
			return FALSE;
		}
	}
	return TRUE;
}

/**
 * 递归复制文件，跳过受保护的目录
 */
function update_copy_files($src, $dst, $protected = array(), $relative = '') {
	$result = array('copied' => 0, 'backed_up' => 0);
	$src = rtrim(str_replace('\\', '/', $src), '/') . '/';
	$dst = rtrim(str_replace('\\', '/', $dst), '/') . '/';

	$items = glob($src . '*');
	if (empty($items)) return $result;

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
			$child = update_copy_files($item . '/', $dst . $name . '/', $protected, $rel);
			$result['copied'] += $child['copied'];
			$result['backed_up'] += $child['backed_up'];
		} else {
			if (@copy($item, $dst . $name)) {
				$result['copied']++;
			}
		}
	}
	return $result;
}

function update_backup_existing_files($src, $dst, $protected, $backup_dir, &$error = '', $relative = '') {
	$result = array('backed_up' => 0);
	$src = rtrim(str_replace('\\', '/', $src), '/') . '/';
	$dst = rtrim(str_replace('\\', '/', $dst), '/') . '/';
	$backup_dir = rtrim(str_replace('\\', '/', $backup_dir), '/') . '/';

	$items = glob($src . '*');
	if (empty($items)) return $result;

	foreach ($items as $item) {
		$item = str_replace('\\', '/', $item);
		$name = basename($item);
		$rel = $relative ? $relative . '/' . $name : $name;

		if (empty($relative) && in_array($name, $protected)) {
			continue;
		}

		if (is_dir($item)) {
			$child = update_backup_existing_files($item . '/', $dst . $name . '/', $protected, $backup_dir, $error, $rel);
			if ($child === FALSE) return FALSE;
			$result['backed_up'] += $child['backed_up'];
		} elseif (is_file($dst . $name)) {
			if (!update_backup_file($dst . $name, $backup_dir . $rel, $error)) {
				return FALSE;
			}
			$result['backed_up']++;
		}
	}
	return $result;
}

function update_backup_file($src, $backup_file, &$error = '') {
	update_mkdir_recursive(dirname($backup_file));
	if (!is_dir(dirname($backup_file))) {
		$error = 'Cannot create backup directory: ' . dirname($backup_file);
		return FALSE;
	}
	if (!@copy($src, $backup_file)) {
		$error = 'Cannot backup file: ' . $src;
		return FALSE;
	}
	return TRUE;
}

function update_latest_backup() {
	$conf = _SERVER('conf');
	$dirs = glob($conf['tmp_path'] . 'update_backup_*', GLOB_ONLYDIR);
	if (empty($dirs)) return array();
	usort($dirs, function($a, $b) { return filemtime($b) - filemtime($a); });
	$dir = rtrim(str_replace('\\', '/', $dirs[0]), '/') . '/';
	return array(
		'name' => basename($dir),
		'path' => str_replace(APP_PATH, '', $dir),
		'time' => date('Y-m-d H:i:s', filemtime($dir)),
		'files' => update_count_files($dir),
	);
}

function update_resolve_backup($backup) {
	$conf = _SERVER('conf');
	$backup = basename($backup);
	if (!preg_match('/^update_backup_\d{8}_\d{6}$/', $backup)) return FALSE;
	$dir = rtrim(str_replace('\\', '/', $conf['tmp_path']), '/') . '/' . $backup . '/';
	return is_dir($dir) ? $dir : FALSE;
}

function update_restore_backup($backup_dir, $dst_root, &$error = '', $relative = '') {
	$result = array('restored' => 0);
	$backup_dir = rtrim(str_replace('\\', '/', $backup_dir), '/') . '/';
	$dst_root = rtrim(str_replace('\\', '/', $dst_root), '/') . '/';
	$current = $backup_dir . ($relative ? $relative . '/' : '');
	$items = glob($current . '*');
	if (empty($items)) return $result;

	foreach ($items as $item) {
		$item = str_replace('\\', '/', $item);
		$name = basename($item);
		$rel = $relative ? $relative . '/' . $name : $name;
		if (preg_match('#(^|/)\.\.(/|$)#', $rel)) {
			$error = 'Unsafe backup path: ' . $rel;
			return FALSE;
		}
		$target = $dst_root . $rel;
		if (is_dir($item)) {
			update_mkdir_recursive($target);
			$child = update_restore_backup($backup_dir, $dst_root, $error, $rel);
			if ($child === FALSE) return FALSE;
			$result['restored'] += $child['restored'];
		} else {
			update_mkdir_recursive(dirname($target));
			if (!@copy($item, $target)) {
				$error = 'Cannot restore file: ' . $rel;
				return FALSE;
			}
			$result['restored']++;
		}
	}
	return $result;
}

function update_count_files($dir) {
	$n = 0;
	$dir = rtrim(str_replace('\\', '/', $dir), '/') . '/';
	$items = glob($dir . '*');
	if (empty($items)) return 0;
	foreach ($items as $item) {
		if (is_dir($item)) {
			$n += update_count_files($item);
		} else {
			$n++;
		}
	}
	return $n;
}

function update_mkdir_recursive($dir) {
	if (is_dir($dir)) return TRUE;
	return mkdir($dir, 0777, TRUE);
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

/**
 * 写入/更新 conf.php 中的任意配置项
 */
function update_conf_setting($key, $value) {
	$conffile = APP_PATH . 'conf/conf.php';
	if (!is_file($conffile)) return FALSE;
	$s = file_get_contents($conffile);
	if ($s === FALSE) return FALSE;
	$escaped = addslashes($value);
	// 已存在则替换
	if (preg_match("/'" . preg_quote($key, '/') . "'\s*=>/", $s)) {
		$s = preg_replace("/'" . preg_quote($key, '/') . "'\s*=>\s*'[^']*'/", "'$key' => '$escaped'", $s);
	} else {
		// 不存在则追加到数组末尾 ); 前面
		$s = preg_replace('/\);\s*\?>\s*$/', "\t'$key' => '$escaped',\n);\n?>", $s);
	}
	return file_put_contents($conffile, $s);
}

?>
