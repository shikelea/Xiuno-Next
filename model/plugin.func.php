<?php

 // 本地插件
//$plugin_srcfiles = array();
$plugin_paths = array();
$plugins = array(); // 跟官方插件合并

// 官方插件列表
$official_plugins = array();

define('PLUGIN_OFFICIAL_URL', DEBUG == 4 ? 'http://plugin.x.com/' : 'http://plugin.xiuno.com/');

// todo: 对路径进行处理 include _include(APP_PATH.'view/htm/header.inc.htm');
$g_include_slot_kv = array();
function _include($srcfile) {
	global $conf;
	// 合并插件，存入 tmp_path
	$len = strlen(APP_PATH);
	$tmpfile = $conf['tmp_path'].substr(str_replace('/', '_', $srcfile), $len);
	if(!is_file($tmpfile) || DEBUG > 1) {
		// 开始编译
		$s = plugin_compile_srcfile($srcfile);
		
		// 支持 <template> <slot>
		$g_include_slot_kv = array();
		for($i = 0; $i < 10; $i++) {
			$s = preg_replace_callback('#<template\sinclude="(.*?)">(.*?)</template>#is', '_include_callback_1', $s);
			if(strpos($s, '<template') === FALSE) break;
		}
		file_put_contents_try($tmpfile, $s);
		
		$s = plugin_compile_srcfile($tmpfile);
		file_put_contents_try($tmpfile, $s);
		
	}
	return $tmpfile;
}

function _include_callback_1($m) {
	global $g_include_slot_kv;
	$r = file_get_contents($m[1]);
	preg_match_all('#<slot\sname="(.*?)">(.*?)</slot>#is', $m[2], $m2);
	if(!empty($m2[1])) {
		$kv = array_combine($m2[1], $m2[2]);
		$g_include_slot_kv += $kv;
		foreach($g_include_slot_kv as $slot=>$content) {
			$r = preg_replace('#<slot\sname="'.$slot.'"\s*/>#is', $content, $r);
		}
	}
	return $r;
}

// 在安装、卸载插件的时候，需要先初始化
function plugin_init() {
	global $plugin_srcfiles, $plugin_paths, $plugins, $official_plugins;
	/*$plugin_srcfiles = array_merge(
		glob(APP_PATH.'model/*.php'), 
		glob(APP_PATH.'route/*.php'), 
		glob(APP_PATH.'view/htm/*.*'), 
		glob(ADMIN_PATH.'route/*.php'), 
		glob(ADMIN_PATH.'view/htm/*.*'),
		glob(APP_PATH.'lang/en-us/*.*'),
		glob(APP_PATH.'lang/zh-cn/*.*'),
		glob(APP_PATH.'lang/zh-tw/*.*'),
		array(APP_PATH.'model.inc.php')
	);
	foreach($plugin_srcfiles as $k=>$file) {
		$filename = file_name($file);
		if(is_backfile($filename)) {
			unset($plugin_srcfiles[$k]);
		}
	}*/
	
	$official_plugins = plugin_official_list_cache();
	empty($official_plugins) AND $official_plugins = array();
	
	$plugin_paths = glob(APP_PATH.'plugin/*', GLOB_ONLYDIR);
	if(is_array($plugin_paths)) {
		foreach($plugin_paths as $path) {
			$dir = file_name($path);
			$conffile = $path."/conf.json";
			if(!is_file($conffile)) continue;
			$arr = xn_json_decode(file_get_contents($conffile));
			if(empty($arr)) continue;
			$plugins[$dir] = $arr;
			
			// 额外的信息
			$plugins[$dir]['hooks'] = array();
			$hookpaths = glob(APP_PATH."plugin/$dir/hook/*.*"); // path
			if(is_array($hookpaths)) {
				foreach($hookpaths as $hookpath) {
					$hookname = file_name($hookpath);
					$plugins[$dir]['hooks'][$hookname] = $hookpath;
				}
			}
			
			// 本地 + 线上数据
			$plugins[$dir] = plugin_read_by_dir($dir);
		}
	}
}

// 插件依赖检测，返回依赖的插件列表，如果返回为空则表示不依赖
/*
	返回依赖的插件数组：
	array(
		'xn_ad'=>'1.0',
		'xn_umeditor'=>'1.0',
	);
*/
function plugin_dependencies($dir) {
	$details = plugin_dependency_details($dir);
	
	// 检查插件依赖关系
	$arr = array();
	foreach($details as $_dir=>$detail) {
		if($detail['status'] != 'ok') {
			$arr[$_dir] = $detail;
		}
	}
	return $arr;
}

/*
	返回被依赖的插件数组：
	array(
		'xn_ad'=>'1.0',
		'xn_umeditor'=>'1.0',
	);
*/
function plugin_dependency_details($dir) {
	global $plugins;
	if(!isset($plugins[$dir])) return array();

	$plugin = $plugins[$dir];
	if(!isset($plugin['dependencies']) || !is_array($plugin['dependencies'])) return array();

	$arr = array();
	foreach($plugin['dependencies'] as $_dir=>$version) {
		$detail = array(
			'dir'=>$_dir,
			'name'=>$_dir,
			'required_version'=>(string)$version,
			'current_version'=>'',
			'status'=>'ok',
			'cycle_path'=>array(),
		);

		if(!plugin_dependency_dir_valid($_dir)) {
			$detail['status'] = 'invalid_dir';
		} elseif(!isset($plugins[$_dir])) {
			$detail['status'] = 'not_downloaded';
		} else {
			$dep = $plugins[$_dir];
			$detail['name'] = isset($dep['name']) && $dep['name'] !== '' ? $dep['name'] : $_dir;
			$detail['current_version'] = isset($dep['version']) ? (string)$dep['version'] : '';
			if(!empty($dep['metadata_error']) || (isset($dep['dependencies']) && !is_array($dep['dependencies']))) {
				$detail['status'] = 'metadata_error';
			} elseif(empty($dep['installed'])) {
				$detail['status'] = 'downloaded_not_installed';
			} elseif(empty($dep['enable'])) {
				$detail['status'] = 'installed_disabled';
			} elseif($version !== '' && $detail['current_version'] !== '' && version_compare($detail['current_version'], (string)$version) < 0) {
				$detail['status'] = 'version_low';
			} else {
				$cycle_path = plugin_dependency_cycle_path($_dir, $dir);
				if(!empty($cycle_path)) {
					$detail['status'] = 'cycle';
					$detail['cycle_path'] = $cycle_path;
				}
			}
		}
		$arr[$_dir] = $detail;
	}
	return $arr;
}

function plugin_dependency_dir_valid($dir) {
	return is_scalar($dir) && preg_match('#^\w{1,32}$#', (string)$dir);
}

function plugin_dependency_cycle_path($current, $target, $visited = array()) {
	global $plugins;
	if(!isset($plugins[$current])) return array();
	if(isset($visited[$current])) return array();
	$visited[$current] = TRUE;

	$dependencies = isset($plugins[$current]['dependencies']) && is_array($plugins[$current]['dependencies']) ? $plugins[$current]['dependencies'] : array();
	foreach($dependencies as $next=>$version) {
		if($next == $target) return array($current, $target);
		$path = plugin_dependency_cycle_path($next, $target, $visited);
		if(!empty($path)) {
			array_unshift($path, $current);
			return $path;
		}
	}
	return array();
}

function plugin_dependency_status_text($detail) {
	if(!is_array($detail)) return '';
	$status = isset($detail['status']) ? $detail['status'] : '';
	$required = isset($detail['required_version']) ? $detail['required_version'] : '';
	$current = isset($detail['current_version']) ? $detail['current_version'] : '';
	$map = array(
		'not_downloaded'=>'not downloaded',
		'downloaded_not_installed'=>'downloaded, not installed',
		'installed_disabled'=>'installed, disabled',
		'version_low'=>'version too low' . ($required !== '' ? " ({$current} < {$required})" : ''),
		'metadata_error'=>'metadata error',
		'cycle'=>'dependency cycle',
		'invalid_dir'=>'invalid dependency name',
	);
	return isset($map[$status]) ? $map[$status] : $status;
}

function plugin_by_dependencies($dir) {
	global $plugins;
	
	$arr = array();
	foreach($plugins as $_dir=>$plugin) {
		if(isset($plugin['dependencies']) && is_array($plugin['dependencies']) && isset($plugin['dependencies'][$dir]) && $plugin['enable']) {
			$arr[$_dir] = $plugin['version'];
		}
	}
	return $arr;
}

function plugin_enable($dir) {
	global $plugins;
	
	if(!isset($plugins[$dir])) {
		return FALSE;
	}
	
	$old = plugin_state_snapshot($dir);
	$plugins[$dir]['enable'] = 1;
	
	//plugin_overwrite($dir, 'install');
	//plugin_hook($dir, 'install');
	
	$r = file_replace_var(APP_PATH."plugin/$dir/conf.json", array('enable'=>1), TRUE);
	if($r === FALSE) {
		plugin_state_restore($dir, $old);
		return FALSE;
	}
	
	plugin_clear_tmp_dir();
	
	return TRUE;
}

// 清空插件的临时目录
function plugin_clear_tmp_dir() {
	global $conf;
	rmdir_recusive($conf['tmp_path'], TRUE);
	xn_unlink($conf['tmp_path'].'model.min.php');
}

function plugin_disable($dir) {
	global $plugins;
	
	if(!isset($plugins[$dir])) {
		return FALSE;
	}
	
	$old = plugin_state_snapshot($dir);
	$plugins[$dir]['enable'] = 0;
	
	//plugin_overwrite($dir, 'unstall');
	//plugin_hook($dir, 'unstall');
	
	$r = file_replace_var(APP_PATH."plugin/$dir/conf.json", array('enable'=>0), TRUE);
	if($r === FALSE) {
		plugin_state_restore($dir, $old);
		return FALSE;
	}
	
	plugin_clear_tmp_dir();
	
	return TRUE;
}

// 安装所有的本地插件
function plugin_install_all() {
	global $plugins;
	
	// 检查文件更新
	foreach ($plugins as $dir=>$plugin) {
		plugin_install($dir);
	}
}

// 卸载所有的本地插件
function plugin_unstall_all() {
	global $plugins;
	
	// 检查文件更新
	foreach ($plugins as $dir=>$plugin) {
		plugin_unstall($dir);
	}
}
/*
	插件安装：
		把所有的插件点合并，重新写入文件。如果没有备份文件，则备份一份。
		插件名可以为源文件名：view/header.htm
*/
function plugin_install($dir) {
	global $plugins, $conf;
	
	if(!isset($plugins[$dir])) {
		return FALSE;
	}
	
	$old = plugin_state_snapshot($dir);
	$plugins[$dir]['installed'] = 1;
	$plugins[$dir]['enable'] = 1;
	
	// 1. 直接覆盖的方式
	//plugin_overwrite($dir, 'install');
	
	// 2. 钩子的方式
	//plugin_hook($dir, 'install');
	
	// 写入配置文件
	$r = file_replace_var(APP_PATH."plugin/$dir/conf.json", array('installed'=>1, 'enable'=>1), TRUE);
	if($r === FALSE) {
		plugin_state_restore($dir, $old);
		return FALSE;
	}
	
	plugin_clear_tmp_dir();
	
	return TRUE;
}

// copy from plugin_install 修改
function plugin_unstall($dir) {
	global $plugins;
	
	if(!isset($plugins[$dir])) {
		return TRUE;
	}
	
	$old = plugin_state_snapshot($dir);
	$plugins[$dir]['installed'] = 0;
	$plugins[$dir]['enable'] = 0;
	
	// 1. 直接覆盖的方式
	//plugin_overwrite($dir, 'unstall');
	
	// 2. 钩子的方式
	//plugin_hook($dir, 'unstall');
	
	// 写入配置文件
	$r = file_replace_var(APP_PATH."plugin/$dir/conf.json", array('installed'=>0, 'enable'=>0), TRUE);
	if($r === FALSE) {
		plugin_state_restore($dir, $old);
		return FALSE;
	}
	
	plugin_clear_tmp_dir();
	
	return TRUE;
}

function plugin_state_snapshot($dir) {
	global $plugins;
	return isset($plugins[$dir]) ? $plugins[$dir] : NULL;
}

function plugin_state_restore($dir, $snapshot) {
	global $plugins;
	if($snapshot === NULL) return FALSE;
	$plugins[$dir] = $snapshot;
	$replace = array();
	foreach(array('installed', 'enable') as $key) {
		if(isset($snapshot[$key])) $replace[$key] = empty($snapshot[$key]) ? 0 : 1;
	}
	if(!empty($replace)) {
		$r = file_replace_var(APP_PATH."plugin/$dir/conf.json", $replace, TRUE);
		if($r === FALSE) return FALSE;
	}
	plugin_clear_tmp_dir();
	return TRUE;
}

function plugin_php_syntax_errors($dir) {
	$root = APP_PATH."plugin/$dir/";
	$errors = array();
	if(!is_dir($root) || !function_exists('exec')) return $errors;
	$files = plugin_php_files($root);
	foreach($files as $file) {
		$path = str_replace('\\', '/', $file);
		if(strpos($path, "/plugin/$dir/hook/") !== FALSE) continue;
		$out = array();
		$code = 0;
		$php = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
		exec(escapeshellarg($php).' -l '.escapeshellarg($file).' 2>&1', $out, $code);
		if($code !== 0) {
			$errors[] = array(
				'file'=>str_replace(APP_PATH, '', $path),
				'detail'=>implode("\n", $out),
			);
		}
	}
	return $errors;
}

function plugin_php_files($dir) {
	$files = array();
	$items = glob(rtrim($dir, '/').'/*');
	if(empty($items)) return $files;
	foreach($items as $item) {
		if(is_dir($item)) {
			$files = array_merge($files, plugin_php_files($item));
		} elseif(strtolower(substr($item, -4)) === '.php') {
			$files[] = $item;
		}
	}
	return $files;
}

function plugin_paths_enabled() {
	static $return_paths;
	if(empty($return_paths)) {
		$return_paths = array();
		$plugin_paths = glob(APP_PATH.'plugin/*', GLOB_ONLYDIR);
		if(empty($plugin_paths)) return array();
		foreach($plugin_paths as $path) {
			$conffile = $path."/conf.json";
			if(!is_file($conffile)) continue;
			$pconf = xn_json_decode(file_get_contents($conffile));
			if(empty($pconf)) continue;
			if(empty($pconf['enable']) || empty($pconf['installed'])) continue;
			$return_paths[$path] = $pconf;
		}
	}
	return $return_paths;
}

// 编译源文件，把插件合并到该文件，不需要递归，执行的过程中 include _include() 自动会递归。
function plugin_compile_srcfile($srcfile) {
	global $conf;
	// 判断是否开启插件
	if(!empty($conf['disabled_plugin'])) {
		$s = file_get_contents($srcfile);
		return $s;
	}
	
	// 如果有 overwrite，则用 overwrite 替换掉
	$srcfile = plugin_find_overwrite($srcfile);
	$s = file_get_contents($srcfile);
	
	// 最多支持 10 层
	for($i = 0; $i < 10; $i++) {
		if(strpos($s, '<!--{hook') !== FALSE || strpos($s, '// hook') !== FALSE) {
			$s = preg_replace('#<!--{hook\s+(.*?)}-->#', '// hook \\1', $s);
			$s = preg_replace_callback('#//\s*hook\s+(\S+)#is', 'plugin_compile_srcfile_callback', $s);
		} else {
			break;
		}
	}
	return $s;
}


// 只返回一个权重最高的文件名
function plugin_find_overwrite($srcfile) {
	//$plugin_paths = glob(APP_PATH.'plugin/*', GLOB_ONLYDIR);
	
	$plugin_paths = plugin_paths_enabled();
	
	$len = strlen(APP_PATH);
	/*
	// 如果发现插件目录，则尝试去掉插件目录前缀，避免新建的 overwrite 目录过深。
	if(strpos($srcfile, '/plugin/') !== FALSE) {
		preg_match('#'.preg_quote(APP_PATH).'plugin/\w+/#i', $srcfile, $m);
		if(!empty($m[0])) {
			$len = strlen($m[0]);
		}
	}*/
	
	$returnfile = $srcfile;
	$maxrank = 0;
	foreach($plugin_paths as $path=>$pconf) {
		
		// 文件路径后半部分
		$dir = file_name($path);
		$filepath_half = substr($srcfile, $len);
		$overwrite_file = APP_PATH."plugin/$dir/overwrite/$filepath_half";
		if(is_file($overwrite_file)) {
			$rank = isset($pconf['overwrites_rank'][$filepath_half]) ? $pconf['overwrites_rank'][$filepath_half] : 0;
			if($rank >= $maxrank) {
				$returnfile = $overwrite_file;
				$maxrank = $rank;
			}
		}
	}
	return $returnfile;
}

function plugin_compile_srcfile_callback($m) {
	static $hooks;
	if(empty($hooks)) {
		$hooks = array();
		$plugin_paths = plugin_paths_enabled();
		
		//$plugin_paths = glob(APP_PATH.'plugin/*', GLOB_ONLYDIR);
		foreach($plugin_paths as $path=>$pconf) {
			$dir = file_name($path);
			$hookpaths = glob(APP_PATH."plugin/$dir/hook/*.*"); // path
			if(is_array($hookpaths)) {
				foreach($hookpaths as $hookpath) {
					$hookname = file_name($hookpath);
					$rank = isset($pconf['hooks_rank']["$hookname"]) ? $pconf['hooks_rank']["$hookname"] : 0;
					$hooks[$hookname][] = array('hookpath'=>$hookpath, 'rank'=>$rank);
				}
			}
		}
		foreach ($hooks as $hookname=>$arrlist) {
			$arrlist = arrlist_multisort($arrlist, 'rank', FALSE);
			$hooks[$hookname] = arrlist_values($arrlist, 'hookpath');
		}
		
	}
	
	$s = '';
	$hookname = $m[1];
	if(!empty($hooks[$hookname])) {
		$fileext = file_ext($hookname);
		foreach($hooks[$hookname] as $path) {
			$t = file_get_contents($path);
			if($fileext == 'php' && preg_match('#^\s*<\?php\s+exit;#is', $t)) {
				// 正则表达式去除兼容性比较好。
				$t = preg_replace('#^\s*<\?php\s*exit;(.*?)(?:\?>)?\s*$#is', '\\1', $t);
				
				/* 去掉首尾标签
				if(substr($t, 0, 5) == '<?php' && substr($t, -2, 2) == '?>') {
					$t = substr($t, 5, -2);		
				}
				// 去掉 exit;
				$t = preg_replace('#\s*exit;\s*#', "\r\n", $t);
				*/
			}
			$s .= $t;
		}
	}
	return $s;
}

// 先下载，购买，付费，再安装
function plugin_online_install($dir) {

}



// -------------------> 官方插件列表缓存到本地。

// 条件满足的总数
function plugin_official_total($cond = array()) {
	global $official_plugins;
	$offlist = $official_plugins;
	$offlist = arrlist_cond_orderby($offlist, $cond, array(), 1, 1000);
	return count($offlist);
}

// 远程插件列表，从官方服务器获取插件列表，全部缓存到本地，定期更新
function plugin_official_list($cond = array(), $orderby = array('pluginid'=>-1), $page = 1, $pagesize = 20) {
	global $official_plugins;
	// 服务端插件信息，缓存起来
	$offlist = $official_plugins;
	$offlist = arrlist_cond_orderby($offlist, $cond, $orderby, $page, $pagesize);
	foreach($offlist as &$plugin) $plugin = plugin_read_by_dir($plugin['dir'], FALSE);
	return $offlist;
}

function plugin_official_list_cache() {
	// 官方插件市场已关闭，直接返回空数组
	return array();
}

function plugin_official_read($dir) {
	global $official_plugins;
	$offlist = $official_plugins;
	$plugin = isset($offlist[$dir]) ? $offlist[$dir] : array();
	return $plugin;
}

// -------------------> 本地插件列表缓存到本地。
// 安装，卸载，禁用，更新
function plugin_read_by_dir($dir, $local_first = TRUE) {
	global $plugins;

	$local = array_value($plugins, $dir, array());
	$official = plugin_official_read($dir);
	if(empty($local) && empty($official)) return array();
	if(empty($local)) $local_first = FALSE;
	
	// 本地插件信息
	//!isset($plugin['dir']) && $plugin['dir'] = '';
	!isset($local['name']) && $local['name'] = '';
	!isset($local['price']) && $local['price'] = 0;
	!isset($local['brief']) && $local['brief'] = '';
	!isset($local['version']) && $local['version'] = '1.0';
	!isset($local['bbs_version']) && $local['bbs_version'] = '4.0';
	!isset($local['installed']) && $local['installed'] = 0;
	!isset($local['enable']) && $local['enable'] = 0;
	!isset($local['hooks']) && $local['hooks'] = array();
	!isset($local['hooks_rank']) && $local['hooks_rank'] = array();
	!isset($local['dependencies']) && $local['dependencies'] = array();
	!isset($local['metadata_error']) && $local['metadata_error'] = 0;
	if(!is_array($local['dependencies'])) {
		$local['dependencies'] = array();
		$local['metadata_error'] = 1;
	}
	$local['installed'] = empty($local['installed']) ? 0 : 1;
	$local['enable'] = empty($local['enable']) ? 0 : 1;
	if(!$local['installed'] && $local['enable']) {
		$local['metadata_error'] = 1;
	}
	!isset($local['icon_url']) && $local['icon_url'] = '';
	!isset($local['have_setting']) && $local['have_setting'] = 0;
	!isset($local['setting_url']) && $local['setting_url'] = 0;
	
	// 加上官方插件的信息
	!isset($official['pluginid']) && $official['pluginid'] = 0;
	!isset($official['name']) && $official['name'] = '';
	!isset($official['price']) && $official['price'] = 0;
	!isset($official['brief']) && $official['brief'] = '';
	!isset($official['bbs_version']) && $official['bbs_version'] = '4.0';
	!isset($official['version']) && $official['version'] = '1.0';
	!isset($official['cateid']) && $official['cateid'] = 0;
	!isset($official['lastupdate']) && $official['lastupdate'] = 0;
	!isset($official['stars']) && $official['stars'] = 0;
	!isset($official['user_stars']) && $official['user_stars'] = 0;
	!isset($official['installs']) && $official['installs'] = 0;
	!isset($official['sells']) && $official['sells'] = 0;
	!isset($official['file_md5']) && $official['file_md5'] = '';
	!isset($official['filename']) && $official['filename'] = '';
	!isset($official['is_cert']) && $official['is_cert'] = 0;
	!isset($official['is_show']) && $official['is_show'] = 0;
	!isset($official['img1']) && $official['img1'] = 0;
	!isset($official['img2']) && $official['img2'] = 0;
	!isset($official['img3']) && $official['img3'] = 0;
	!isset($official['img4']) && $official['img4'] = 0;
	!isset($official['brief_url']) && $official['brief_url'] = '';
	!isset($official['qq']) && $official['qq'] = '';
	
	$local['official'] = $official;
	
	if($local_first) {
		$plugin = $local + $official;
	} else {
		$plugin = $official + $local;
	}
	// 额外的判断
	$plugin['icon_url'] = $plugin['pluginid'] ? PLUGIN_OFFICIAL_URL."upload/plugin/$plugin[pluginid]/icon.png" : "../plugin/$dir/icon.png";
	$plugin['setting_url'] = $plugin['installed'] && is_file("../plugin/$dir/setting.php") ? "plugin-setting-$dir.htm" : "";
	$plugin['downloaded'] = isset($plugins[$dir]);
	$plugin['stars_fmt'] = $plugin['pluginid'] ? str_repeat('<span class="icon star"></span>', $plugin['stars']) : '';
	$plugin['user_stars_fmt'] = $plugin['pluginid'] ? str_repeat('<span class="icon star"></span>', $plugin['user_stars']) : '';
	$plugin['is_cert_fmt'] = empty($plugin['is_cert']) ? '<span class="text-danger">'.lang('no').'</span>' : '<span class="text-success">'.lang('yes').'</span>';
	$plugin['have_upgrade'] = $plugin['installed'] && version_compare($official['version'], $local['version']) > 0 ? TRUE : FALSE;
	$plugin['official_version'] = $official['version']; // 官方版本
	$plugin['img1_url'] = $official['img1'] ? PLUGIN_OFFICIAL_URL.'upload/plugin/'.$plugin['pluginid'].'/img1.jpg' : ''; // 官方版本
	$plugin['img2_url'] = $official['img2'] ? PLUGIN_OFFICIAL_URL.'upload/plugin/'.$plugin['pluginid'].'/img2.jpg' : ''; // 官方版本
	$plugin['img3_url'] = $official['img3'] ? PLUGIN_OFFICIAL_URL.'upload/plugin/'.$plugin['pluginid'].'/img3.jpg' : ''; // 官方版本
	$plugin['img4_url'] = $official['img4'] ? PLUGIN_OFFICIAL_URL.'upload/plugin/'.$plugin['pluginid'].'/img4.jpg' : ''; // 官方版本
	return $plugin;
}

function plugin_siteid() {
	global $conf;
	$auth_key = $conf['auth_key'];
	$siteip = _SERVER('SERVER_ADDR');
	$siteid = md5($auth_key.$siteip);
	return $siteid;
}

/*function plugin_outid($dir) {
	global $conf;
	$auth_key = $conf['auth_key'];
	$siteip = _SERVER('SERVER_ADDR')
	$outid = md5($auth_key.$siteip.$dir);
	return $outid;
}*/
?>
