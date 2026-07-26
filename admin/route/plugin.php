<?php

!defined('DEBUG') AND exit('Access Denied.');

include XIUNOPHP_PATH.'xn_zip.func.php';

$action = param(1);

// 初始化插件变量 / init plugin var
plugin_init();

// 插件依赖的环境检查
plugin_env_check();

empty($action) AND $action = 'local';

if($action == 'local') {
	
	// 本地插件 local plugin list
	$pluginlist = $plugins;
	
	$pagination = '';
	$pugin_cate_html = '';
	
	$header['title']    = lang('local_plugin');
	$header['mobile_title'] = lang('local_plugin');
	
	
	include _include(ADMIN_PATH."view/htm/plugin_list.htm");

} elseif($action == 'official_fee' || $action == 'official_free') {

	$cateid = param(2, 0);
	$page = param(3, 1);
	$pagesize = 10;
	$cond = $cateid ? array('cateid'=>$cateid) : array();
	$cond['price'] = $action == 'official_fee' ? array('>'=>0) : 0;
			
	// plugin category
	$pugin_cates = array(0=>lang('pugin_cate_0'), 1=>lang('pugin_cate_1'), 2=>lang('pugin_cate_2'), 3=>lang('pugin_cate_3'), 4=>lang('pugin_cate_4'), 99=>lang('pugin_cate_99'));

	$pugin_cate_html = plugin_cate_active($action, $pugin_cates, $cateid, $page);
	
	// official plugin
	$total = plugin_official_total($cond);
	$pluginlist = plugin_official_list($cond, array('pluginid'=>-1), $page, $pagesize);
	$pagination = pagination(url("plugin-$action-$cateid-{page}"), $total, $page, $pagesize);
	
	$header['title']    = lang('official_plugin');
	$header['mobile_title'] = lang('official_plugin');
	
	include _include(ADMIN_PATH."view/htm/plugin_list.htm");
	
// 给出二维码扫描后开始下载。
} elseif($action == 'read') {

	// 给出插件的介绍+付款二维码
	$dir = param_word(2);
	$siteid = plugin_siteid();
	
	$plugin = plugin_read_by_dir($dir);
	empty($plugin) AND message(-1, lang('plugin_not_exists'));
	
	$islocal = plugin_is_local($dir);
	
	$url = '';
	$download_url = '';
	$errmsg = '';
	if($plugin['pluginid']) {
		// 判断是否已经购买过。
		// 如果之前免费，后来收费，则判断是否已经支付。
		
		// 如果收费，判断是否购买过。
		if(!empty($plugin['official']) && $plugin['official']['price'] > 0) {
			$url = plugin_order_buy_qrcode_url($siteid, $dir);
			// 如果已经购买过，或者发生错误。
			if($url === FALSE) {
				/*
					0: 返回支付 URL(weixin://)
					1: 已经支付
					2: 不需要支付
					-1: 业务逻辑错误
					<-1: 系统错误
				*/
				if($errno == 1 || $errno == 2) {
					// 已经支付，就给出下载地址。
					$download_url = url("plugin-download-$dir");
				} else {
					$download_url = '';
					$errmsg = $errstr;
					//message($errno, $errstr);
				}
			// 如果没购买，则在用二维码显示 $url;
			} else {
				// ... 二维码显示 $url
			}
		// 如果免费
		} else {
		
		}
	} else {
		// 判断新版本是否收费，是否已经支付。
		$url = '';
		
	}
	
	$tab = !$islocal ? ($plugin['price'] > 0 ? 'official_fee' : 'official_free') : 'local';
	$header['title']    = lang('plugin_detail').'-'.$plugin['name'];
	$header['mobile_title'] = $plugin['name'];
	include _include(ADMIN_PATH."view/htm/plugin_read.htm");
	
// 给出二维码扫描后开始下载。
} elseif($action == 'is_bought') {

	// 给出插件的介绍+付款二维码
	$dir = param_word(2);
	plugin_check_exists($dir, FALSE);
	$plugin = plugin_read_by_dir($dir);
	
	if($plugin['official']['price'] == 0) {
		message(1, lang('plugin_is_free'));
	}
	if(plugin_is_bought($dir)) {
		message(0, lang('plugin_is_bought'));
	} else {
		message(isset($errno) ? $errno : 2, isset($errno) ? $errstr : lang('plugin_not_bought'));
	}
	
	
// 下载官方插件。 / download official plugin
} elseif($action == 'download') {
	
	plugin_require_post();
	plugin_lock_start();
	
	$dir = param_word(2);
	plugin_check_exists($dir, FALSE);
	$plugin = plugin_read_by_dir($dir);
	
	$official = plugin_official_read($dir);
	empty($official) AND plugin_message(-1, lang('plugin_not_exists'));
	
	// 检查版本  / check version match
	if(version_compare($conf['version'], $official['bbs_version']) == -1) {
		plugin_message(-1, lang('plugin_versio_not_match', array('bbs_version'=>$official['bbs_version'], 'version'=>$conf['version'])));
	}
	
	// 下载，解压 / download and zip
	plugin_official_remote_closed();
	
} elseif($action == 'install') {
	
	plugin_require_post();
	plugin_lock_start();
	
	$dir = param_word(2);
	plugin_check_exists($dir);
	$name = $plugins[$dir]['name'];
	plugin_require_action_state($dir, 'install');
	
	// 检查目录可写 / check directory writable
	//plugin_check_dir_is_writable();
	
	// 插件依赖检查 / check plugin dependency
	plugin_check_dependency($dir, 'install');
	plugin_check_auto_unstall_dependencies($dir);
	plugin_check_php_syntax($dir);
	
	// 安装插件 / install plugin
	$plugin_snapshot = plugin_state_snapshot($dir);
	plugin_require_state_write(plugin_install($dir), $dir, $plugin_snapshot);
	$lifecycle_message = plugin_run_lifecycle($dir, 'install', $plugin_snapshot);
	
	plugin_auto_unstall_same_type($dir, $plugin_snapshot);

	plugin_lock_end();
	if(is_array($lifecycle_message)) {
		message($lifecycle_message['code'], $lifecycle_message['message'], $lifecycle_message['extra']);
	}

	$msg = lang('plugin_install_sucessfully', array('name'=>$name));
	message(0, jump($msg, http_referer(), 3));
	
} elseif($action == 'unstall') {
	
	plugin_require_post();
	plugin_lock_start();
	
	$dir = param_word(2);
	plugin_check_exists($dir);
	$name = $plugins[$dir]['name'];
	plugin_require_action_state($dir, 'unstall');
	
	// 检查目录可写
	// plugin_check_dir_is_writable();
	
	// 插件依赖检查
	plugin_check_dependency($dir, 'unstall');
	
	// 卸载插件
	plugin_check_dependency($dir, 'unstall');
	$plugin_snapshot = plugin_state_snapshot($dir);
	plugin_require_state_write(plugin_unstall($dir), $dir, $plugin_snapshot);
	$lifecycle_message = plugin_run_lifecycle($dir, 'unstall', $plugin_snapshot);
	
	// 删除插件
	//!DEBUG && rmdir_recusive("../plugin/$dir");
	
	plugin_lock_end();
	if(is_array($lifecycle_message)) {
		message($lifecycle_message['code'], $lifecycle_message['message'], $lifecycle_message['extra']);
	}
	
	$msg = lang('plugin_unstall_sucessfully', array('name'=>$name, 'dir'=>"plugin/$dir"));
	message(0, jump($msg, http_referer(), 5));
	
} elseif($action == 'enable') {
	
	plugin_require_post();
	plugin_lock_start();
	
	$dir = param_word(2);
	plugin_check_exists($dir);
	$name = $plugins[$dir]['name'];
	plugin_require_action_state($dir, 'enable');
	
	// 检查目录可写
	//plugin_check_dir_is_writable();
	
	// 插件依赖检查
	plugin_check_dependency($dir, 'install');
	
	// 启用插件
	plugin_check_dependency($dir, 'install');
	plugin_check_php_syntax($dir);
	plugin_require_state_write(plugin_enable($dir), $dir);
	
	plugin_lock_end();
	
	$msg = lang('plugin_enable_sucessfully', array('name'=>$name));
	message(0, jump($msg, http_referer(), 1));
	
} elseif($action == 'disable') {
	
	plugin_require_post();
	plugin_lock_start();
	
	$dir = param_word(2);
	plugin_check_exists($dir);
	$name = $plugins[$dir]['name'];
	plugin_require_action_state($dir, 'disable');
	
	// 检查目录可写
	//plugin_check_dir_is_writable();
	
	// 插件依赖检查
	plugin_check_dependency($dir, 'unstall');
	
	// 禁用插件
	plugin_check_dependency($dir, 'unstall');
	plugin_require_state_write(plugin_disable($dir), $dir);
	
	plugin_lock_end();
	
	$msg = lang('plugin_disable_sucessfully', array('name'=>$name));
	message(0, jump($msg, http_referer(), 3));
	
} elseif($action == 'upgrade') {
	
	plugin_require_post();
	plugin_lock_start();
	
	$dir = param_word(2);
	plugin_check_exists($dir, FALSE);
	$name = $plugins[$dir]['name'];
	
	// 判断插件版本
	$plugin = plugin_read_by_dir($dir);
	plugin_require_action_state($dir, 'upgrade', $plugin);
	
	// 检查目录可写
	//plugin_check_dir_is_writable();
	
	// 插件依赖检查
	plugin_check_dependency($dir, 'install', NULL, NULL, FALSE);
	$official = plugin_read_by_dir($dir, FALSE);
	plugin_official_remote_closed();
	
} elseif($action == 'setting') {

	$dir = param_word(2);
	plugin_check_exists($dir);

	// 🔒 安全修复：插件设置页面必须验证管理员权限，防止普通用户访问后台
	// 检查用户是否拥有后台管理权限
	$gid != 1 AND message(-1, lang('insufficient_privilege'));

	$name = $plugins[$dir]['name'];

	// 🔒 安全修复：防止目录遍历攻击，确保包含的文件在 plugin/ 目录内
	// 使用 realpath 规范化路径，防止 ../ 等路径遍历
	$pluginfile = APP_PATH."plugin/$dir/setting.php";
	$real_path = realpath($pluginfile);
	$safe_dir = realpath(APP_PATH."plugin/");

	// 验证文件必须在 plugin/ 目录内
	if (!$real_path || strpos($real_path, $safe_dir) !== 0) {
		message(-1, 'Invalid plugin path');
	}

	include _include($pluginfile);
}


	

// 检查目录是否可写，插件要求 model view admin 目录文件可写。
/*
function plugin_check_dir_is_writable() {
	// 检测目录和文件可写
	$dirs = array(
		APP_PATH.'model', 
		APP_PATH.'plugin', 
		APP_PATH.'view', 
		APP_PATH.'route', 
		APP_PATH.'view/js', 
		APP_PATH.'view/htm', 
		APP_PATH.'view/css', 
		APP_PATH.'plugin', 
		ADMIN_PATH.'route', 
		ADMIN_PATH.'view/htm');
	$dirarr = array();
	foreach($dirs as $dir) {
		if(!xn_is_writable($dir)) {
			$dirarr[] = $dir;
		}
	}
	$msg = lang('plugin_set_relatied_dir_writable', array('dir'=>implode(', ', $dirarr)));
	!empty($dirarr) AND message(-1, $msg);
}*/

function plugin_require_action_state($dir, $action, $plugin = NULL) {
	global $plugins;
	if($plugin === NULL) $plugin = array_value($plugins, $dir, array());
	$installed = !empty($plugin['installed']);
	$enable = !empty($plugin['enable']);
	if($action == 'install' && $installed) plugin_message(-1, 'Plugin already installed, please refresh.');
	if($action == 'unstall' && !$installed) plugin_message(-1, 'Plugin is not installed, please refresh.');
	if($action == 'enable' && (!$installed || $enable)) plugin_message(-1, 'Plugin cannot be enabled in its current state, please refresh.');
	if($action == 'disable' && (!$installed || !$enable)) plugin_message(-1, 'Plugin cannot be disabled in its current state, please refresh.');
	if($action == 'upgrade' && (empty($plugin) || empty($plugin['have_upgrade']))) plugin_message(-1, lang('plugin_not_need_update'));
}

function plugin_auto_unstall_candidates($dir) {
	global $plugins;
	$arr = array();
	$is_theme = strpos($dir, '_theme_') !== FALSE;
	$pos = strpos($dir, '_');
	$suffix = $pos === FALSE ? '' : substr($dir, $pos);
	foreach($plugins as $_dir => $_plugin) {
		if($dir == $_dir || empty($_plugin['installed'])) continue;
		if($is_theme) {
			if(strpos($_dir, '_theme_') !== FALSE) $arr[] = $_dir;
		} elseif($suffix !== '') {
			$_pos = strpos($_dir, '_');
			$_suffix = $_pos === FALSE ? '' : substr($_dir, $_pos);
			if($_suffix === $suffix) $arr[] = $_dir;
		}
	}
	return $arr;
}

function plugin_check_auto_unstall_dependencies($dir) {
	foreach(plugin_auto_unstall_candidates($dir) as $_dir) {
		plugin_check_dependency($_dir, 'unstall');
	}
}

function plugin_auto_unstall_same_type($dir, $primary_snapshot = NULL) {
	$restore_states = array();
	if($primary_snapshot !== NULL) $restore_states[$dir] = $primary_snapshot;
	foreach(plugin_auto_unstall_candidates($dir) as $_dir) {
		$snapshot = plugin_state_snapshot($_dir);
		$restore_states[$_dir] = $snapshot;
		if(!plugin_unstall($_dir)) {
			plugin_restore_extra_states($restore_states);
			plugin_require_state_write(FALSE, $_dir, $snapshot);
		}
		$lifecycle_message = plugin_run_lifecycle($_dir, 'unstall', $snapshot, NULL, $restore_states);
		if(is_array($lifecycle_message)) {
			plugin_lifecycle_restore_or_fail($_dir, $snapshot, NULL, $restore_states);
			plugin_lock_end();
			message($lifecycle_message['code'], $lifecycle_message['message'], $lifecycle_message['extra']);
		}
	}
	plugin_check_auto_unstall_result($dir, $restore_states);
}

function plugin_check_auto_unstall_result($dir, $restore_states) {
	global $plugins;
	$arr = plugin_dependencies($dir);
	if(empty($arr)) return TRUE;
	plugin_restore_extra_states($restore_states);
	$name = isset($plugins[$dir]['name']) ? $plugins[$dir]['name'] : $dir;
	$s = plugin_dependency_arr_to_links($arr);
	$msg = lang('plugin_dependency_following', array('name'=>$name, 's'=>$s));
	plugin_message(-1, $msg);
}

function plugin_check_dependency($dir, $action = 'install', $snapshot = NULL, $package_snapshot = NULL, $check_self_metadata = TRUE) {
	global $plugins;
	$name = $plugins[$dir]['name'];
	if($action == 'install') {
		if($check_self_metadata && !empty($plugins[$dir]['metadata_error'])) {
			if($package_snapshot !== NULL) plugin_package_restore($package_snapshot);
			if($snapshot !== NULL) plugin_state_restore($dir, $snapshot);
			plugin_message(-1, 'conf.json '.lang('format_maybe_error'));
		}
		$arr = plugin_dependencies($dir);
		if(!empty($arr)) {
			$s = plugin_dependency_arr_to_links($arr);
			$msg = lang('plugin_dependency_following', array('name'=>$name, 's'=>$s));
			if($package_snapshot !== NULL) plugin_package_restore($package_snapshot);
			if($snapshot !== NULL) plugin_state_restore($dir, $snapshot);
			plugin_message(-1, $msg);
		}
	} else {
		$arr = plugin_by_dependencies($dir);
		if(!empty($arr)) {
			$s = plugin_dependency_arr_to_links($arr);
			$msg = lang('plugin_being_dependent_cant_delete', array('name'=>$name, 's'=>$s));
			if($package_snapshot !== NULL) plugin_package_restore($package_snapshot);
			if($snapshot !== NULL) plugin_state_restore($dir, $snapshot);
			plugin_message(-1, $msg);
		}
	}
}

function plugin_reload_local($dir, $snapshot = NULL, $package_snapshot = NULL) {
	global $plugins;
	$conffile = APP_PATH."plugin/$dir/conf.json";
	if(!is_file($conffile)) {
		if($package_snapshot !== NULL) plugin_package_restore($package_snapshot);
		if($snapshot !== NULL) plugin_state_restore($dir, $snapshot);
		plugin_message(-1, 'conf.json '.lang('not_exists'));
	}
	$arr = xn_json_decode(file_get_contents($conffile));
	if(empty($arr)) {
		if($package_snapshot !== NULL) plugin_package_restore($package_snapshot);
		if($snapshot !== NULL) plugin_state_restore($dir, $snapshot);
		plugin_message(-1, 'conf.json '.lang('format_maybe_error'));
	}
	$plugins[$dir] = $arr;
	$plugins[$dir]['hooks'] = array();
	$hookpaths = glob(APP_PATH."plugin/$dir/hook/*.*");
	if(is_array($hookpaths)) {
		foreach($hookpaths as $hookpath) {
			$hookname = file_name($hookpath);
			$plugins[$dir]['hooks'][$hookname] = $hookpath;
		}
	}
	$plugins[$dir] = plugin_read_by_dir($dir);
	return TRUE;
}

function plugin_require_state_write($ok, $dir, $snapshot = NULL, $package_snapshot = NULL) {
	if($ok) return TRUE;
	if($package_snapshot !== NULL) plugin_package_restore($package_snapshot);
	if($snapshot !== NULL) plugin_state_restore($dir, $snapshot);
	plugin_message(-1, lang('save_conf_failed', array('file'=>"plugin/$dir/conf.json")));
}

final class PluginLifecycleMessage extends Error {
	public $response_code;
	public $response_message;
	public $response_extra;

	public function __construct($code, $message, $extra = array()) {
		parent::__construct('Plugin lifecycle message');
		$this->response_code = $code;
		$this->response_message = $message;
		$this->response_extra = is_array($extra) ? $extra : array();
	}
}

function plugin_lifecycle_capture_message($code, $message, $extra = array()) {
	global $plugin_lifecycle_guard, $plugin_lifecycle_message_pending;
	if(empty($plugin_lifecycle_guard) || !is_array($plugin_lifecycle_guard)) return;
	if($plugin_lifecycle_message_pending instanceof PluginLifecycleMessage) {
		throw $plugin_lifecycle_message_pending;
	}
	$plugin_lifecycle_message_pending = new PluginLifecycleMessage($code, $message, $extra);
	throw $plugin_lifecycle_message_pending;
}

function plugin_lifecycle_message_is_success($code) {
	return $code === 0 || $code === '0';
}

function plugin_lifecycle_form_action_is_local($action) {
	return plugin_compat_form_action_is_local($action);
}

function plugin_lifecycle_form_action_route($form_action) {
	$query = parse_url($form_action, PHP_URL_QUERY);
	if(is_string($query) && $query !== '') {
		$route = explode('&', $query, 2)[0];
	} else {
		$route = (string)parse_url($form_action, PHP_URL_PATH);
	}
	$route = trim(rawurldecode($route), '/');
	$route = preg_replace('~\\.htm$~i', '', $route);
	return rtrim($route, '/');
}

function plugin_lifecycle_message_is_deferred($dir, $action, $message) {
	if(!in_array($action, array('install', 'unstall', 'upgrade'), TRUE) || !is_string($message)) return FALSE;
	if(!preg_match_all('~<form\\b[^>]*>~i', $message, $forms)) return FALSE;
	foreach($forms[0] as $form) {
		if(!preg_match("~\\smethod\\s*=\\s*(['\\\"]?)post\\1(?=[\\s>/])~i", $form)) continue;
		if(!preg_match("~\\saction\\s*=\\s*(?:\"([^\"]*)\"|'([^']*)'|([^\\s>]*))~i", $form, $action_match)) return TRUE;
		$form_action = isset($action_match[1]) && $action_match[1] !== '' ? $action_match[1] : (isset($action_match[2]) && $action_match[2] !== '' ? $action_match[2] : (isset($action_match[3]) ? $action_match[3] : ''));
		$form_action = html_entity_decode(trim($form_action), ENT_QUOTES, 'UTF-8');
		if($form_action === '') return TRUE;
		if(!plugin_lifecycle_form_action_is_local($form_action)) continue;
		$route = plugin_lifecycle_form_action_route($form_action);
		if($route === 'plugin-'.$action.'-'.$dir || $route === 'plugin/'.$action.'/'.$dir) return TRUE;
	}
	return FALSE;
}

function plugin_lifecycle_pending_message_take() {
	global $plugin_lifecycle_message_pending;
	$message = $plugin_lifecycle_message_pending instanceof PluginLifecycleMessage ? $plugin_lifecycle_message_pending : NULL;
	$plugin_lifecycle_message_pending = NULL;
	return $message;
}

function plugin_lifecycle_restore_or_fail($dir, $snapshot = NULL, $package_snapshot = NULL, $extra_state_restore = array()) {
	if($package_snapshot !== NULL) plugin_package_restore($package_snapshot);
	$ok = TRUE;
	if($snapshot !== NULL && !plugin_state_restore($dir, $snapshot)) $ok = FALSE;
	if(!plugin_restore_extra_states($extra_state_restore)) $ok = FALSE;
	if(!$ok) plugin_message(-1, 'Plugin lifecycle rollback failed: '.htmlspecialchars($dir));
	return TRUE;
}

function plugin_lifecycle_handle_message($dir, $action, PluginLifecycleMessage $e, $snapshot = NULL, $package_snapshot = NULL, $extra_state_restore = array()) {
	plugin_lifecycle_guard_clear();
	if(!plugin_lifecycle_message_is_success($e->response_code) || plugin_lifecycle_message_is_deferred($dir, $action, $e->response_message)) {
		plugin_lifecycle_restore_or_fail($dir, $snapshot, $package_snapshot, $extra_state_restore);
		plugin_lock_end();
		message($e->response_code, $e->response_message, $e->response_extra);
	}
	return array(
		'code'=>$e->response_code,
		'message'=>$e->response_message,
		'extra'=>$e->response_extra,
	);
}

function plugin_run_lifecycle($dir, $action, $snapshot = NULL, $package_snapshot = NULL, $extra_state_restore = array()) {
	$file = APP_PATH."plugin/$dir/$action.php";
	if(!is_file($file)) return TRUE;
	plugin_lifecycle_guard_start($dir, $action, $snapshot, $package_snapshot, $extra_state_restore);
	try {
		$result = plugin_compat_include_lifecycle($file);
		$pending_message = plugin_lifecycle_pending_message_take();
		if($pending_message !== NULL) {
			return plugin_lifecycle_handle_message($dir, $action, $pending_message, $snapshot, $package_snapshot, $extra_state_restore);
		}
		plugin_lifecycle_guard_clear();
		if($result === FALSE) {
			plugin_lifecycle_restore_or_fail($dir, $snapshot, $package_snapshot, $extra_state_restore);
			plugin_message(-1, 'Plugin '.$action.' failed: '.htmlspecialchars($dir));
		}
	} catch(PluginLifecycleMessage $e) {
		plugin_lifecycle_pending_message_take();
		return plugin_lifecycle_handle_message($dir, $action, $e, $snapshot, $package_snapshot, $extra_state_restore);
	} catch(Throwable $e) {
		$pending_message = plugin_lifecycle_pending_message_take();
		if($pending_message !== NULL && (!plugin_lifecycle_message_is_success($pending_message->response_code) || plugin_lifecycle_message_is_deferred($dir, $action, $pending_message->response_message))) {
			return plugin_lifecycle_handle_message($dir, $action, $pending_message, $snapshot, $package_snapshot, $extra_state_restore);
		}
		plugin_lifecycle_guard_clear();
		plugin_lifecycle_restore_or_fail($dir, $snapshot, $package_snapshot, $extra_state_restore);
		plugin_message(-1, 'Plugin '.$action.' failed: '.htmlspecialchars($e->getMessage()));
	}
	return TRUE;
}

function plugin_db_exec_or_throw($sql) {
	global $errno, $errstr;
	$r = db_exec($sql);
	if($r === FALSE) {
		$msg = $errstr ? $errstr : 'Unknown database error';
		throw new RuntimeException($msg);
	}
	return $r;
}

function plugin_lifecycle_guard_start($dir, $action, $snapshot = NULL, $package_snapshot = NULL, $extra_state_restore = array()) {
	global $plugin_lifecycle_guard, $plugin_lifecycle_message_pending;
	$plugin_lifecycle_message_pending = NULL;
	$plugin_lifecycle_guard = array(
		'dir'=>$dir,
		'action'=>$action,
		'snapshot'=>$snapshot,
		'package_snapshot'=>$package_snapshot,
		'extra_state_restore'=>$extra_state_restore,
	);
}

function plugin_lifecycle_guard_clear() {
	global $plugin_lifecycle_guard, $plugin_lifecycle_message_pending;
	$plugin_lifecycle_guard = NULL;
	$plugin_lifecycle_message_pending = NULL;
}

function plugin_lifecycle_guard_restore() {
	global $plugin_lifecycle_guard;
	if(empty($plugin_lifecycle_guard) || !is_array($plugin_lifecycle_guard)) return;
	$guard = $plugin_lifecycle_guard;
	$plugin_lifecycle_guard = NULL;
	if(!empty($guard['package_snapshot'])) plugin_package_restore($guard['package_snapshot']);
	if(isset($guard['snapshot'])) plugin_state_restore($guard['dir'], $guard['snapshot']);
	plugin_restore_extra_states(array_value($guard, 'extra_state_restore', array()));
}

function plugin_restore_extra_states($states) {
	if(empty($states) || !is_array($states)) return TRUE;
	$ok = TRUE;
	foreach($states as $dir=>$snapshot) {
		if($snapshot !== NULL && !plugin_state_restore($dir, $snapshot)) $ok = FALSE;
	}
	return $ok;
}

function plugin_dependency_arr_to_links($arr) {
	global $plugins;
	$s = '';
	foreach($arr as $dir=>$dependency) {
		//if(!isset($plugins[$dir])) continue;
		$name = is_array($dependency) && !empty($dependency['name']) ? $dependency['name'] : (isset($plugins[$dir]['name']) ? $plugins[$dir]['name'] : $dir);
		$status = plugin_dependency_status_text($dependency);
		$status_html = $status !== '' ? ' <span class="text-muted small">(' . htmlspecialchars($status) . ')</span>' : '';
		$name_html = '[' . htmlspecialchars($name) . ']';
		if(plugin_dependency_has_detail_page($dir)) {
			$url = htmlspecialchars(url("plugin-read-$dir"), ENT_QUOTES);
			$s .= ' <a href="' . $url . '">' . $name_html . '</a>' . $status_html . ' ';
		} else {
			$s .= ' <span class="text-muted">' . $name_html . '</span>' . $status_html . ' ';
		}
	}
	return $s;
}

function plugin_dependency_has_detail_page($dir) {
	global $plugins;
	if(isset($plugins[$dir])) return TRUE;
	return !empty(plugin_official_read($dir));
}


// 下载插件、解压
function plugin_download_unzip($dir, $package_snapshot = NULL) {
	plugin_official_remote_closed();
}

function plugin_package_snapshot($dir) {
	global $conf;
	$dest_dir = APP_PATH."plugin/$dir/";
	$snapshot = array(
		'dir'=>$dir,
		'dest_dir'=>$dest_dir,
		'backup_dir'=>'',
		'had_dest'=>is_dir($dest_dir),
	);
	if(!$snapshot['had_dest']) return $snapshot;
	$snapshot['backup_dir'] = $conf['tmp_path'].'plugin_backup_'.$dir.'_'.str_replace('.', '', uniqid('', TRUE)).'/';
	$error = '';
	if(!plugin_copy_dir($dest_dir, $snapshot['backup_dir'], $error)) {
		rmdir_recusive($snapshot['backup_dir'], 0);
		plugin_message(-1, 'Plugin package snapshot failed: '.htmlspecialchars($error));
	}
	return $snapshot;
}

function plugin_package_restore($snapshot) {
	if(empty($snapshot) || !is_array($snapshot)) return TRUE;
	$dest_dir = $snapshot['dest_dir'];
	$backup_dir = isset($snapshot['backup_dir']) ? $snapshot['backup_dir'] : '';
	rmdir_recusive($dest_dir, 0);
	if(!empty($snapshot['had_dest'])) {
		$error = '';
		if(!is_dir($backup_dir) || !plugin_copy_dir($backup_dir, $dest_dir, $error)) {
			plugin_message(-1, 'Plugin package rollback failed: '.htmlspecialchars($error));
		}
	}
	plugin_package_snapshot_delete($snapshot);
	plugin_clear_tmp_dir();
	return TRUE;
}

function plugin_package_snapshot_delete($snapshot) {
	if(empty($snapshot) || !is_array($snapshot) || empty($snapshot['backup_dir'])) return TRUE;
	rmdir_recusive($snapshot['backup_dir'], 0);
	return TRUE;
}

function plugin_zip_validate_package($zip, $dir, &$error = '') {
	for($i = 0; $i < $zip->numFiles; $i++) {
		$name = str_replace('\\', '/', $zip->getNameIndex($i));
		if(!xn_zip_safe_name($name)) {
			$error = 'Unsafe path in zip: '.$name;
			return FALSE;
		}
		if(plugin_zip_entry_is_symlink($zip, $i)) {
			$error = 'Symlink entry is not allowed in zip: '.$name;
			return FALSE;
		}
		$name = ltrim($name, './');
		if($name === '') continue;
		if(strpos($name, $dir.'/') !== 0 && $name !== $dir) {
			$error = 'Unexpected plugin directory in zip: '.$name;
			return FALSE;
		}
	}
	return TRUE;
}

function plugin_zip_entry_is_symlink($zip, $index) {
	if(!method_exists($zip, 'getExternalAttributesIndex')) return FALSE;
	$opsys = 0;
	$attr = 0;
	if(!$zip->getExternalAttributesIndex($index, $opsys, $attr)) return FALSE;
	$mode = ($attr >> 16) & 0170000;
	return $mode === 0120000;
}

function plugin_copy_dir($src, $dst, &$error = '') {
	$src = rtrim(str_replace('\\', '/', $src), '/').'/';
	$dst = rtrim(str_replace('\\', '/', $dst), '/').'/';
	if(!is_dir($src)) {
		$error = 'Source directory missing: '.$src;
		return FALSE;
	}
	if(!plugin_mkdir_recursive($dst)) {
		$error = 'Cannot create directory: '.$dst;
		return FALSE;
	}
	$items = plugin_dir_items($src);
	if(empty($items)) return TRUE;
	foreach($items as $item) {
		$item = str_replace('\\', '/', $item);
		$name = basename($item);
		if(is_link($item)) {
			$error = 'Symlink is not allowed: '.$name;
			return FALSE;
		} elseif(is_dir($item)) {
			if(!plugin_copy_dir($item.'/', $dst.$name.'/', $error)) return FALSE;
		} elseif(is_file($item)) {
			if(!@copy($item, $dst.$name)) {
				$error = 'Cannot copy file: '.$name;
				return FALSE;
			}
		} else {
			$error = 'Unsupported file type: '.$name;
			return FALSE;
		}
	}
	return TRUE;
}

function plugin_dir_items($dir) {
	$items = glob($dir.'*');
	$dotitems = glob($dir.'.*');
	$items = is_array($items) ? $items : array();
	if(is_array($dotitems)) {
		foreach($dotitems as $item) {
			$name = basename($item);
			if($name == '.' || $name == '..') continue;
			$items[] = $item;
		}
	}
	return $items;
}

function plugin_mkdir_recursive($dir) {
	return is_dir($dir) || mkdir($dir, 0777, TRUE);
}

function plugin_is_bought($dir) {
	return xn_error(-1, plugin_official_remote_closed_error());
}

function plugin_order_buy_qrcode_url($siteid, $dir, $app_url = '') {
	return xn_error(-1, plugin_official_remote_closed_error());
}

function plugin_official_remote_closed() {
	plugin_message(-1, plugin_official_remote_closed_error());
}

function plugin_official_remote_closed_error() {
	return 'Official plugin marketplace is closed; install vetted local plugin/theme packages instead.';
}

function plugin_is_local($dir) {
	global $plugins;
	return isset($plugins[$dir]) ? TRUE : FALSE;
}

function plugin_check_php_syntax($dir, $package_snapshot = NULL) {
	$errors = plugin_php_syntax_errors($dir);
	if(!empty($errors)) {
		if($package_snapshot !== NULL) plugin_package_restore($package_snapshot);
		$error = $errors[0];
		plugin_message(-1, 'Plugin PHP syntax check failed: '.htmlspecialchars($error['file']).' '.htmlspecialchars($error['detail']));
	}
	return TRUE;
}

function plugin_check_exists($dir, $local = TRUE) {
	global $plugins, $official_plugins;
	!is_word($dir) AND plugin_message(-1, lang('plugin_name_error'));
	if($local) {
		!isset($plugins[$dir]) AND plugin_message(-1, lang('plugin_not_exists'));
	} else {
		!isset($official_plugins[$dir]) AND plugin_message(-1, lang('plugin_not_exists'));
	}
}

// bootstrap style
function plugin_cate_active($action, $plugin_cate, $cateid, $page) {
	$s = '';
	foreach ($plugin_cate as $_cateid=>$_catename) {
		$url = url("plugin-$action-$_cateid-$page");
		$s .= '<a role="button" class="btn btn btn-secondary'.($cateid == $_cateid ? ' active' : '').'" href="'.$url.'">'.$_catename.'</a>';
	}
	return $s;
}

function plugin_lock_start() {
	global $plugin_task_locked, $plugin_shutdown_registered;
	!xn_lock_start(plugin_lock_name(), 300) AND message(-1, lang('plugin_task_locked'));
	$plugin_task_locked = TRUE;
	if(empty($plugin_shutdown_registered)) {
		register_shutdown_function('plugin_shutdown_guard');
		$plugin_shutdown_registered = TRUE;
	}
}

function plugin_lock_end() {
	global $plugin_task_locked;
	if(empty($plugin_task_locked)) return;
	xn_lock_end(plugin_lock_name());
	$plugin_task_locked = FALSE;
}

function plugin_message($code, $message) {
	plugin_lock_end();
	message($code, $message);
}

function plugin_lock_name() {
	return 'plugin_task';
}

function plugin_shutdown_guard() {
	plugin_lifecycle_guard_restore();
	plugin_lock_end();
}

function plugin_require_post() {
	global $method;
	$method != 'POST' AND message(-1, 'Method Not Allowed');
}

// 依赖
function plugin_env_check() {
	//!class_exists('ZipArchive') AND message(-1, 'ZipArchive does not exists! require PHP version > 5.2.0');
}

?>
