<?php

!defined('DEBUG') AND exit('Access Denied.');

include XIUNOPHP_PATH.'xn_zip.func.php';

$action = param(1);

function plugin_route_dir($position = 2) {
	$named = param('dir', '', FALSE);
	$dir = $named !== '' ? $named : param($position, '', FALSE);
	if(!plugin_dir_is_valid($dir)) plugin_message(-1, lang('plugin_dir_invalid'));
	return $dir;
}

function plugin_require_package_root($dir) {
	$error = '';
	$root = plugin_package_root_path($dir, $error);
	if($root === FALSE) {
		plugin_package_root_diagnostic($dir, $error);
		plugin_message(-1, lang('plugin_package_path_invalid'));
	}
	return $root;
}

// 初始化插件变量 / init plugin var
plugin_init() === TRUE OR message(-1, lang('plugin_state_unavailable'));

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
	$dir = plugin_route_dir();
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
					$download_url = plugin_url('download', $dir);
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
	$dir = plugin_route_dir();
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
	
	$dir = plugin_route_dir();
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
	
	$dir = plugin_route_dir();
	plugin_check_exists($dir);
	$name = $plugins[$dir]['name'];
	plugin_require_state_storage_writable($dir);
	plugin_require_action_state($dir, 'install');
	$replacement_dirs = plugin_require_auto_unstall_contract($dir);
	plugin_require_auto_unstall_storage_writable($dir, $replacement_dirs);
	
	// 检查目录可写 / check directory writable
	//plugin_check_dir_is_writable();
	
	// 插件依赖检查 / check plugin dependency
	plugin_check_dependency($dir, 'install');
	plugin_check_auto_unstall_dependencies($dir, $replacement_dirs);
	plugin_check_php_syntax($dir);
	
	// 安装插件 / install plugin
	$plugin_snapshot = plugin_state_snapshot($dir);
	plugin_require_state_write(plugin_install($dir), $dir, $plugin_snapshot);
	$lifecycle_message = plugin_run_lifecycle($dir, 'install', $plugin_snapshot);
	
	$replaced_dirs = plugin_auto_unstall_same_type($dir, $plugin_snapshot, $replacement_dirs);
	$compat_metadata_failures = array();
	foreach($replaced_dirs as $replaced_dir) {
		if(!plugin_setting_schema_unbind_plugin($replaced_dir)) {
			plugin_lifecycle_log('plugin setting schema owner could not be detached after replacement dir='.$replaced_dir);
			$compat_metadata_failures[] = $replaced_dir;
		}
	}
	// Compatibility-owned sidecar/default writes belong to the outer install commit point. If
	// same-type replacement fails above, only third-party install.php side effects may remain;
	// the compatibility layer itself has not registered ownership or normalized defaults.
	if(!plugin_lifecycle_persist_setting_schema($dir, 'install')) $compat_metadata_failures[] = $dir;

	plugin_lock_end();
	if(!empty($compat_metadata_failures)) {
		message(1, '插件状态已提交，但兼容层设置元数据未能完成：'.htmlspecialchars(implode(', ', array_unique($compat_metadata_failures))).'。请检查 plugin_lifecycle_error 日志后再进入设置页。');
	}
	if(is_array($lifecycle_message)) {
		message($lifecycle_message['code'], $lifecycle_message['message'], $lifecycle_message['extra']);
	}

	$msg = lang('plugin_install_sucessfully', array('name'=>$name));
	message(0, jump($msg, http_referer(), 3));
	
} elseif($action == 'unstall') {
	
	plugin_require_post();
	plugin_lock_start();
	
	$dir = plugin_route_dir();
	plugin_check_exists($dir);
	$name = $plugins[$dir]['name'];
	plugin_require_state_storage_writable($dir);
	plugin_require_action_state($dir, 'unstall');
	
	// 检查目录可写
	// plugin_check_dir_is_writable();
	
	// 插件依赖检查
	plugin_check_dependency($dir, 'unstall');
	
	// 卸载插件
	$plugin_snapshot = plugin_state_snapshot($dir);
	plugin_require_state_write(plugin_unstall($dir), $dir, $plugin_snapshot);
	$lifecycle_message = plugin_run_lifecycle($dir, 'unstall', $plugin_snapshot);
	$setting_schema_unbound = plugin_setting_schema_unbind_plugin($dir);
	if(!$setting_schema_unbound) plugin_lifecycle_log('plugin setting schema owner could not be detached after unstall dir='.$dir);
	
	// 删除插件
	//!DEBUG && rmdir_recusive("../plugin/$dir");
	
	plugin_lock_end();
	if(!$setting_schema_unbound) {
		message(1, '插件已卸载，但兼容层设置元数据清理失败。设置值未被核心删除；请检查 plugin_lifecycle_error 日志。');
	}
	if(is_array($lifecycle_message)) {
		message($lifecycle_message['code'], $lifecycle_message['message'], $lifecycle_message['extra']);
	}
	
	$msg = lang('plugin_unstall_sucessfully', array('name'=>$name, 'dir'=>"plugin/$dir"));
	message(0, jump($msg, http_referer(), 5));
	
} elseif($action == 'enable') {
	
	plugin_require_post();
	plugin_lock_start();
	
	$dir = plugin_route_dir();
	plugin_check_exists($dir);
	$name = $plugins[$dir]['name'];
	plugin_require_state_storage_writable($dir);
	plugin_require_action_state($dir, 'enable');
	
	// 检查目录可写
	//plugin_check_dir_is_writable();
	
	// 插件依赖检查
	plugin_check_dependency($dir, 'install');
	
	// 启用插件
	plugin_check_php_syntax($dir);
	$plugin_snapshot = plugin_state_snapshot($dir);
	plugin_require_state_write(plugin_enable($dir), $dir, $plugin_snapshot);
	
	plugin_lock_end();
	
	$msg = lang('plugin_enable_sucessfully', array('name'=>$name));
	message(0, jump($msg, http_referer(), 1));
	
} elseif($action == 'disable') {
	
	plugin_require_post();
	plugin_lock_start();
	
	$dir = plugin_route_dir();
	plugin_check_exists($dir);
	$name = $plugins[$dir]['name'];
	plugin_require_state_storage_writable($dir);
	plugin_require_action_state($dir, 'disable');
	
	// 检查目录可写
	//plugin_check_dir_is_writable();
	
	// 插件依赖检查
	plugin_check_dependency($dir, 'disable');
	
	// 禁用插件
	$plugin_snapshot = plugin_state_snapshot($dir);
	plugin_require_state_write(plugin_disable($dir), $dir, $plugin_snapshot);
	
	plugin_lock_end();
	
	$msg = lang('plugin_disable_sucessfully', array('name'=>$name));
	message(0, jump($msg, http_referer(), 3));
	
} elseif($action == 'upgrade') {
	
	plugin_require_post();
	plugin_lock_start();
	
	$dir = plugin_route_dir();
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

	$dir = plugin_route_dir();
	plugin_check_exists($dir);

	// 🔒 安全修复：插件设置页面必须验证管理员权限，防止普通用户访问后台
	// 检查用户是否拥有后台管理权限
	$gid != 1 AND message(-1, lang('insufficient_privilege'));
	empty($plugins[$dir]['installed']) AND message(-1, lang('plugin_not_installed'));

	$name = $plugins[$dir]['name'];

	// 🔒 安全修复：防止目录遍历攻击，确保包含的文件在 plugin/ 目录内
	// 使用 realpath 规范化路径，防止 ../ 等路径遍历
	$package_root = plugin_require_package_root($dir);
	$pluginfile = $package_root.'setting.php';
	$real_path = is_link($pluginfile) ? FALSE : plugin_realpath_within($pluginfile, $package_root);

	// 验证文件必须在 plugin/ 目录内
	if ($real_path === FALSE || !is_file($real_path)) {
		message(-1, lang('plugin_setting_path_invalid'));
	}

	$setting_page = plugin_compat_include_setting_page($real_path, $dir);
	empty($setting_page['has_output']) AND message(-1, lang('plugin_setting_no_output'));
}


	

function plugin_require_state_storage_writable($dir) {
	if(plugin_state_storage_writable($dir)) return TRUE;
	plugin_message(-1, lang('plugin_state_storage_readonly', array('file'=>'plugin/'.$dir.'/conf.json')));
}

function plugin_require_auto_unstall_storage_writable($dir, $replacement_dirs = NULL) {
	if($replacement_dirs === NULL) $replacement_dirs = plugin_auto_unstall_candidates($dir);
	foreach($replacement_dirs as $_dir) plugin_require_state_storage_writable($_dir);
	return TRUE;
}

// Historical compatibility check retained as documentation only. Lifecycle state updates require
// the exact plugin conf.json target above; unrelated core model/view/admin paths are not package
// storage and must not be made writable merely to run a plugin.
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

function plugin_auto_unstall_plan($dir) {
	global $plugins;
	$plan = array('exclusive_group'=>'', 'candidates'=>array());
	if(!isset($plugins[$dir]) || !is_array($plugins[$dir])) return $plan;
	$plan['exclusive_group'] = plugin_exclusive_group_normalize(array_value($plugins[$dir], 'exclusive_group', ''));
	foreach($plugins as $_dir => $_plugin) {
		if($dir == $_dir || empty($_plugin['installed'])) continue;
		$_group = plugin_exclusive_group_normalize(array_value($_plugin, 'exclusive_group', ''));
		if($plan['exclusive_group'] !== '' && $_group === $plan['exclusive_group']) $plan['candidates'][] = $_dir;
	}
	sort($plan['candidates'], SORT_STRING);
	return $plan;
}

function plugin_require_auto_unstall_contract($dir) {
	$plan = plugin_auto_unstall_plan($dir);
	return $plan['candidates'];
}

function plugin_auto_unstall_candidates($dir) {
	return plugin_require_auto_unstall_contract($dir);
}

function plugin_check_auto_unstall_dependencies($dir, $replacement_dirs = NULL) {
	if($replacement_dirs === NULL) $replacement_dirs = plugin_auto_unstall_candidates($dir);
	foreach($replacement_dirs as $_dir) {
		plugin_check_dependency($_dir, 'unstall');
	}
}

function plugin_auto_unstall_same_type($dir, $primary_snapshot = NULL, $replacement_dirs = NULL) {
	if($replacement_dirs === NULL) $replacement_dirs = plugin_auto_unstall_candidates($dir);
	$restore_states = array();
	$uninstalled_dirs = array();
	if($primary_snapshot !== NULL) $restore_states[$dir] = $primary_snapshot;
	foreach($replacement_dirs as $_dir) {
		if(!plugin_state_storage_writable($_dir)) {
			// The target install.php and earlier replacement lifecycles have already run. A direct
			// read-only response here would strand the new state and any earlier removals, so this
			// execution-time capability loss belongs to the same aggregate rollback boundary.
			plugin_lifecycle_restore_or_fail($dir, NULL, NULL, $restore_states);
			plugin_message(-1, lang('plugin_state_storage_readonly', array('file'=>'plugin/'.$_dir.'/conf.json')));
		}
		$snapshot = plugin_state_snapshot($_dir);
		$restore_states[$_dir] = $snapshot;
		if(!plugin_unstall($_dir)) {
			plugin_require_state_write(FALSE, $_dir, $snapshot, NULL, $restore_states);
		}
		// A returned lifecycle payload is a completed, non-deferred success message. The wrapper
		// already restores and exits for failures or wizard steps, so treating every array as a
		// rollback here turns a normal legacy message(0, ...) into a false-success replacement.
		plugin_run_lifecycle($_dir, 'unstall', $snapshot, NULL, $restore_states);
		$uninstalled_dirs[] = $_dir;
	}
	plugin_check_auto_unstall_result($dir, $restore_states);
	return $uninstalled_dirs;
}

function plugin_check_auto_unstall_result($dir, $restore_states) {
	global $plugins;
	$arr = plugin_dependencies($dir);
	if(empty($arr)) return TRUE;
	plugin_lifecycle_restore_or_fail($dir, NULL, NULL, $restore_states);
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
			plugin_lifecycle_restore_or_fail($dir, $snapshot, $package_snapshot);
			plugin_message(-1, 'conf.json '.lang('format_maybe_error'));
		}
		$arr = plugin_dependencies($dir);
		if(!empty($arr)) {
			$s = plugin_dependency_arr_to_links($arr);
			$msg = lang('plugin_dependency_following', array('name'=>$name, 's'=>$s));
			plugin_lifecycle_restore_or_fail($dir, $snapshot, $package_snapshot);
			plugin_message(-1, $msg);
		}
	} else {
		$arr = plugin_by_dependencies($dir);
		if(!empty($arr)) {
			$s = plugin_dependency_arr_to_links($arr);
			$message_key = $action == 'disable' ? 'plugin_being_dependent_cant_disable' : 'plugin_being_dependent_cant_delete';
			$msg = lang($message_key, array('name'=>$name, 's'=>$s));
			plugin_lifecycle_restore_or_fail($dir, $snapshot, $package_snapshot);
			plugin_message(-1, $msg);
		}
	}
}

function plugin_reload_local($dir, $snapshot = NULL, $package_snapshot = NULL) {
	global $plugins;
	$root_error = '';
	$package_root = plugin_package_root_path($dir, $root_error);
	$conf_error = '';
	$conffile = $package_root === FALSE ? FALSE : plugin_package_conf_path($dir, $conf_error);
	if($conffile === FALSE) {
		plugin_package_root_diagnostic($dir, $package_root === FALSE ? $root_error : $conf_error);
		plugin_lifecycle_restore_or_fail($dir, $snapshot, $package_snapshot);
		plugin_message(-1, lang('plugin_package_path_invalid'));
	}
	$conf_bytes = file_get_contents($conffile);
	$arr = $conf_bytes === FALSE ? array() : xn_json_decode($conf_bytes);
	if(empty($arr)) {
		plugin_lifecycle_restore_or_fail($dir, $snapshot, $package_snapshot);
		plugin_message(-1, 'conf.json '.lang('format_maybe_error'));
	}
	$plugins[$dir] = $arr;
	$plugins[$dir]['hooks'] = array();
	$hook_root = $package_root.'hook/';
	$hookpaths = is_dir($hook_root) && !is_link(rtrim($hook_root, '/')) ? glob($hook_root.'*.*') : array();
	if(is_array($hookpaths)) {
		foreach($hookpaths as $hookpath) {
			if(is_link($hookpath) || !is_file($hookpath) || plugin_realpath_within($hookpath, $hook_root) === FALSE) continue;
			$hookname = file_name($hookpath);
			$plugins[$dir]['hooks'][$hookname] = $hookpath;
		}
	}
	$plugins[$dir] = plugin_read_by_dir($dir);
	return TRUE;
}

function plugin_require_state_write($ok, $dir, $snapshot = NULL, $package_snapshot = NULL, $extra_state_restore = array()) {
	if($ok) return TRUE;
	$failed = plugin_lifecycle_restore_collect_failures($dir, $snapshot, $package_snapshot, $extra_state_restore);
	if(!empty($failed)) {
		plugin_message(-1, 'Plugin lifecycle state restore failed ['.implode(',', $failed).']: '.htmlspecialchars($dir));
		return FALSE;
	}
	plugin_message(-1, lang('save_conf_failed', array('file'=>"plugin/$dir/conf.json")));
	return FALSE;
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
	if(function_exists('plugin_setting_admin_request_capture_message')) plugin_setting_admin_request_capture_message($code, $message, $extra);
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

function plugin_lifecycle_form_action_is_local($action, $base_href = NULL) {
	return plugin_compat_form_action_is_local($action, $base_href);
}

function plugin_lifecycle_form_action_route($form_action) {
	$path = (string)parse_url($form_action, PHP_URL_PATH);
	$query = parse_url($form_action, PHP_URL_QUERY);
	// Query rewrite modes keep the route in the first bare query segment. Path
	// rewrite modes keep that route in the path and use the query for arguments.
	$path_basename = strtolower(basename(str_replace('\\', '/', $path)));
	$path_targets_current_script = $path === '' || substr($path, -1) === '/' || $path_basename === 'index.php';
	if($path_targets_current_script && is_string($query) && $query !== '') {
		$route = explode('&', $query, 2)[0];
	} else {
		$route = $path;
	}
	$route = trim(rawurldecode($route), '/');
	while(strpos($route, './') === 0) $route = substr($route, 2);
	$route = preg_replace('~\\.htm$~i', '', $route);
	return rtrim($route, '/');
}

function plugin_lifecycle_message_is_deferred($dir, $action, $message) {
	if(!in_array($action, array('install', 'unstall', 'upgrade'), TRUE) || !is_string($message)) return FALSE;
	$base_href = plugin_compat_html_base_href($message, $base_found);
	$forms = xn_html_scan_tags($message, 'form');
	$form_open = FALSE;
	foreach($forms as $token) {
		if(empty($token['closing'])) {
			if($form_open) return FALSE;
			$form_open = TRUE;
		} else {
			if(!$form_open) return FALSE;
			$form_open = FALSE;
		}
	}
	if($form_open) return FALSE;
	foreach($forms as $token) {
		if(!empty($token['closing'])) continue;
		$form = $token['tag'];

		$method = plugin_compat_html_tag_attribute($form, 'method', $method_found);
		$method = $method_found ? trim(xn_html_attribute_value_decode($method)) : '';
		if(!$method_found || strcasecmp($method, 'post') !== 0) continue;
		$form_action = plugin_compat_html_tag_attribute($form, 'action', $action_found);
		if(!$action_found) return TRUE;
		$form_action = xn_html_attribute_value_decode(trim($form_action));
		if($form_action === '') return TRUE;
		if(!plugin_lifecycle_form_action_is_local($form_action, $base_found ? $base_href : NULL)) continue;
		$route = plugin_lifecycle_form_action_route($form_action);
		if($route === 'plugin-'.$action.'-'.$dir || $route === 'plugin/'.$action.'/'.$dir) return TRUE;
		if($route === 'plugin-'.$action || $route === 'plugin/'.$action) {
			$query = parse_url($form_action, PHP_URL_QUERY);
			$args = array();
			is_string($query) && $query !== '' AND parse_str($query, $args);
			if(isset($args['dir']) && is_string($args['dir']) && hash_equals($dir, $args['dir'])) return TRUE;
		}
	}
	return FALSE;
}

function plugin_lifecycle_pending_message_take() {
	global $plugin_lifecycle_message_pending;
	$message = $plugin_lifecycle_message_pending instanceof PluginLifecycleMessage ? $plugin_lifecycle_message_pending : NULL;
	$plugin_lifecycle_message_pending = NULL;
	return $message;
}

function plugin_lifecycle_restore_collect_failures($dir, $snapshot = NULL, $package_snapshot = NULL, $extra_state_restore = array()) {
	$failed = array();
	if($package_snapshot !== NULL && !plugin_package_restore($package_snapshot, TRUE, FALSE)) $failed[] = 'package';
	if($snapshot !== NULL && !plugin_state_restore($dir, $snapshot)) $failed[] = 'state';
	$related_state_restore = $extra_state_restore;
	if($snapshot !== NULL && is_array($related_state_restore) && array_key_exists($dir, $related_state_restore)) {
		unset($related_state_restore[$dir]);
	}
	if(!plugin_restore_extra_states($related_state_restore)) $failed[] = 'extra_states';
	return $failed;
}

function plugin_lifecycle_restore_or_fail($dir, $snapshot = NULL, $package_snapshot = NULL, $extra_state_restore = array()) {
	$failed = plugin_lifecycle_restore_collect_failures($dir, $snapshot, $package_snapshot, $extra_state_restore);
	if(!empty($failed)) {
		plugin_message(-1, 'Plugin lifecycle state restore failed ['.implode(',', $failed).']: '.htmlspecialchars($dir));
	}
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
	$root_error = '';
	$package_root = plugin_package_root_path($dir, $root_error);
	if($package_root === FALSE) {
		plugin_package_root_diagnostic($dir, $root_error);
		plugin_lifecycle_restore_or_fail($dir, $snapshot, $package_snapshot, $extra_state_restore);
		plugin_message(-1, lang('plugin_package_path_invalid'));
	}
	$file = $package_root.$action.'.php';
	if(!file_exists($file) && !is_link($file)) return TRUE;
	$real_file = is_link($file) ? FALSE : plugin_realpath_within($file, $package_root);
	if($real_file === FALSE || !is_file($real_file) || !is_readable($real_file)) {
		plugin_lifecycle_restore_or_fail($dir, $snapshot, $package_snapshot, $extra_state_restore);
		plugin_message(-1, lang('plugin_package_path_invalid'));
	}
	$file = $real_file;
	plugin_lifecycle_guard_start($dir, $action, $snapshot, $package_snapshot, $extra_state_restore);
	try {
		$result = plugin_compat_include_lifecycle($file, $dir, $action);
		$pending_message = plugin_lifecycle_pending_message_take();
		if($pending_message !== NULL) {
			$lifecycle_result = plugin_lifecycle_handle_message($dir, $action, $pending_message, $snapshot, $package_snapshot, $extra_state_restore);
			return $lifecycle_result;
		}
		plugin_lifecycle_guard_clear();
		if($result === FALSE) {
			plugin_lifecycle_restore_or_fail($dir, $snapshot, $package_snapshot, $extra_state_restore);
			plugin_message(-1, 'Plugin '.$action.' failed: '.htmlspecialchars($dir));
		}
	} catch(PluginLifecycleMessage $e) {
		plugin_lifecycle_pending_message_take();
		$lifecycle_result = plugin_lifecycle_handle_message($dir, $action, $e, $snapshot, $package_snapshot, $extra_state_restore);
		return $lifecycle_result;
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

function plugin_lifecycle_persist_setting_schema($dir, $action) {
	if(!in_array($action, array('install', 'upgrade'), TRUE)) return TRUE;
	$ok = plugin_setting_schema_persist_plugin($dir);
	if(!$ok) plugin_lifecycle_log('plugin setting defaults could not be persisted after '.$action.' dir='.$dir);
	return $ok;
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

// shutdown 阶段的恢复必须检查每一步结果并记录失败：这里是插件脚本 fatal/exit 后最后的闭环点，静默失败等于撕裂状态
function plugin_lifecycle_guard_restore() {
	global $plugin_lifecycle_guard;
	if(empty($plugin_lifecycle_guard) || !is_array($plugin_lifecycle_guard)) return;
	$guard = $plugin_lifecycle_guard;
	$plugin_lifecycle_guard = NULL;
	$failed = plugin_lifecycle_restore_collect_failures(
		array_value($guard, 'dir', ''),
		array_value($guard, 'snapshot', NULL),
		array_value($guard, 'package_snapshot', NULL),
		array_value($guard, 'extra_state_restore', array())
	);
	if(!empty($failed)) {
		plugin_lifecycle_log('plugin shutdown restore failed ['.implode(',', $failed).'] dir='.array_value($guard, 'dir', '').' action='.array_value($guard, 'action', ''));
	}
}

// shutdown 场景的日志兜底：日志类缺失时降级到 error_log，绝不能在 shutdown 恢复路径里引入新的致命错误
function plugin_lifecycle_log($s) {
	if(function_exists('xn_log') && class_exists('XiunoLogger')) {
		xn_log($s, 'plugin_lifecycle_error');
		return;
	}
	@error_log('xiuno: '.$s);
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
			$url = htmlspecialchars(plugin_url('read', $dir), ENT_QUOTES);
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
	if(plugin_package_path_exists(rtrim($dest_dir, '/'))) {
		$dest_dir = plugin_require_package_root($dir);
	}
	$empty_manifest = plugin_package_manifest_digest(array());
	$snapshot = array(
		'dir'=>$dir,
		'dest_dir'=>$dest_dir,
		'backup_dir'=>'',
		'had_dest'=>is_dir($dest_dir),
		'restore_id'=>str_replace('.', '', uniqid('', TRUE)),
		'backup_manifest_version'=>1,
		'backup_manifest_path_sha256'=>hash('sha256', ''),
		'backup_manifest_sha256'=>$empty_manifest['sha256'],
		'backup_manifest_file_count'=>$empty_manifest['file_count'],
	);
	if(!$snapshot['had_dest']) return $snapshot;
	$snapshot['backup_dir'] = $conf['tmp_path'].'plugin_backup_'.$dir.'_'.str_replace('.', '', uniqid('', TRUE)).'/';
	$error = '';
	if(!plugin_copy_dir($dest_dir, $snapshot['backup_dir'], $error)) {
		rmdir_recusive($snapshot['backup_dir'], 0);
		plugin_message(-1, 'Plugin package snapshot failed: '.htmlspecialchars($error));
	}
	$source_summary = array();
	$backup_summary = array();
	$backup_path_sha256 = plugin_package_manifest_path_sha256($snapshot['backup_dir'], $error);
	if($backup_path_sha256 === FALSE
		|| !plugin_package_manifest_summary($dest_dir, $source_summary, $error)
		|| !plugin_package_manifest_summary($snapshot['backup_dir'], $backup_summary, $error)
		|| $source_summary['file_count'] !== $backup_summary['file_count']
		|| !hash_equals($source_summary['sha256'], $backup_summary['sha256'])) {
		$error === '' AND $error = 'Original and protected backup package manifests differ.';
		$cleanup_error = '';
		if(plugin_package_path_exists($snapshot['backup_dir']) && !plugin_package_remove_path($snapshot['backup_dir'], $cleanup_error)) {
			$error .= '; snapshot cleanup failed: '.$cleanup_error;
		}
		plugin_message(-1, 'Plugin package snapshot verification failed: '.htmlspecialchars($error));
	}
	$snapshot['backup_manifest_path_sha256'] = $backup_path_sha256;
	$snapshot['backup_manifest_sha256'] = $backup_summary['sha256'];
	$snapshot['backup_manifest_file_count'] = $backup_summary['file_count'];
	return $snapshot;
}

// 先在目标同级目录完整复制并校验备份，再通过可逆 rename 交换；任何交换前失败都不得改动现有目标目录
// $silent 模式在失败时返回 FALSE 而不是触发 message()/exit；聚合恢复会关闭单步日志，由外层统一报告一次
function plugin_package_restore($snapshot, $silent = FALSE, $log_silent_failure = TRUE) {
	if(empty($snapshot) || !is_array($snapshot)) return TRUE;
	$dest_dir = isset($snapshot['dest_dir']) ? rtrim(str_replace('\\', '/', (string)$snapshot['dest_dir']), '/') : '';
	$backup_dir = isset($snapshot['backup_dir']) ? rtrim(str_replace('\\', '/', (string)$snapshot['backup_dir']), '/') : '';
	$error = '';
	if($dest_dir === '') return plugin_package_restore_fail($snapshot, 'Destination directory is missing.', $silent, $log_silent_failure);

	$staging_dir = '';
	$previous_dir = '';
	if(!plugin_package_restore_paths($snapshot, $dest_dir, $staging_dir, $previous_dir, $error)) {
		return plugin_package_restore_fail($snapshot, $error, $silent, $log_silent_failure);
	}

	if(empty($snapshot['had_dest'])) {
		if(!plugin_package_restore_absent($dest_dir, $previous_dir, $error)) {
			return plugin_package_restore_fail($snapshot, $error, $silent, $log_silent_failure);
		}
	} else {
		if($backup_dir === '' || !is_dir($backup_dir)) {
			$error = 'Source directory missing: '.$backup_dir;
			return plugin_package_restore_fail($snapshot, $error, $silent, $log_silent_failure);
		}
		if(!plugin_package_snapshot_manifest_verify($snapshot, $backup_dir, 'backup', $error)) {
			return plugin_package_restore_fail($snapshot, $error, $silent, $log_silent_failure);
		}
		if($backup_dir === $staging_dir || $backup_dir === $previous_dir) {
			return plugin_package_restore_fail($snapshot, 'Backup directory conflicts with a restore work path.', $silent, $log_silent_failure);
		}
		if(plugin_package_path_exists($staging_dir)) {
			$cleanup_error = '';
			if(!plugin_package_remove_path($staging_dir, $cleanup_error)) {
				return plugin_package_restore_fail($snapshot, 'Cannot clear stale restore staging directory: '.$cleanup_error, $silent, $log_silent_failure);
			}
		}
		if(!plugin_copy_dir($backup_dir.'/', $staging_dir.'/', $error)
			|| !plugin_package_dirs_equal($backup_dir.'/', $staging_dir.'/', $error)
			|| !plugin_package_snapshot_manifest_verify($snapshot, $staging_dir, 'staging', $error)) {
			$cleanup_error = '';
			if(plugin_package_path_exists($staging_dir) && !plugin_package_remove_path($staging_dir, $cleanup_error)) {
				$error .= '; staging cleanup failed: '.$cleanup_error;
			}
			return plugin_package_restore_fail($snapshot, $error, $silent, $log_silent_failure);
		}
		if(!plugin_package_restore_exchange($dest_dir, $staging_dir, $previous_dir, $error)) {
			$cleanup_error = '';
			if(plugin_package_path_exists($staging_dir) && !plugin_package_remove_path($staging_dir, $cleanup_error)) {
				$error .= '; staging cleanup failed: '.$cleanup_error;
			}
			return plugin_package_restore_fail($snapshot, $error, $silent, $log_silent_failure);
		}
	}
	if(!plugin_clear_tmp_dir()) {
		return plugin_package_restore_fail($snapshot, 'Runtime cache invalidation failed after package restoration.', $silent, $log_silent_failure);
	}
	if(!plugin_package_snapshot_delete($snapshot)) {
		return plugin_package_restore_fail($snapshot, 'Protected package snapshot cleanup failed after package restoration.', $silent, $log_silent_failure);
	}
	return TRUE;
}

function plugin_package_restore_fail($snapshot, $error, $silent = FALSE, $log_silent_failure = TRUE) {
	$dir = isset($snapshot['dir']) ? (string)$snapshot['dir'] : '';
	if($silent) {
		if($log_silent_failure) plugin_lifecycle_log('plugin package rollback failed: dir='.$dir.' error='.$error);
		return FALSE;
	}
	plugin_message(-1, 'Plugin package rollback failed: '.htmlspecialchars($error));
	return FALSE;
}

function plugin_package_restore_paths($snapshot, $dest_dir, &$staging_dir, &$previous_dir, &$error = '') {
	$parent_dir = str_replace('\\', '/', dirname($dest_dir));
	if($parent_dir === '' || !is_dir($parent_dir)) {
		$error = 'Destination parent directory missing: '.$parent_dir;
		return FALSE;
	}
	$restore_id = isset($snapshot['restore_id']) ? (string)$snapshot['restore_id'] : '';
	if(!preg_match('/^[A-Za-z0-9_-]{8,80}$/D', $restore_id)) {
		$restore_id = str_replace('.', '', uniqid('', TRUE));
	}
	$path_id = substr(hash('sha256', $dest_dir), 0, 12).'_'.$restore_id;
	$staging_dir = $parent_dir.'/.plugin_restore_'.$path_id.'_staging';
	$previous_dir = $parent_dir.'/.plugin_restore_'.$path_id.'_previous';
	return TRUE;
}

function plugin_package_path_exists($path) {
	return is_link($path) || file_exists($path);
}

function plugin_package_remove_path($path, &$error = '') {
	if(!plugin_package_path_exists($path)) return TRUE;
	if(is_link($path) || is_file($path)) {
		if(@unlink($path) && !plugin_package_path_exists($path)) return TRUE;
		$error = 'Cannot remove path: '.$path;
		return FALSE;
	}
	if(!is_dir($path)) {
		if(@unlink($path) && !plugin_package_path_exists($path)) return TRUE;
		$error = 'Unsupported path type: '.$path;
		return FALSE;
	}
	foreach(plugin_dir_items(rtrim(str_replace('\\', '/', $path), '/').'/') as $item) {
		if(!plugin_package_remove_path($item, $error)) return FALSE;
	}
	if(@rmdir($path) && !plugin_package_path_exists($path)) return TRUE;
	$error = 'Cannot remove directory: '.$path;
	return FALSE;
}

function plugin_package_restore_exchange($dest_dir, $staging_dir, $previous_dir, &$error = '') {
	if(plugin_package_path_exists($previous_dir)) {
		$error = 'Restore recovery path already exists: '.$previous_dir;
		return FALSE;
	}
	$had_current_dest = plugin_package_path_exists($dest_dir);
	if($had_current_dest && !@rename($dest_dir, $previous_dir)) {
		$error = 'Cannot move current plugin package to recovery path: '.$dest_dir;
		return FALSE;
	}
	if(!@rename($staging_dir, $dest_dir)) {
		$error = 'Cannot activate staged plugin package: '.$staging_dir;
		if($had_current_dest) {
			if(plugin_package_path_exists($dest_dir)) {
				$error .= '; destination changed unexpectedly and the previous package remains at '.$previous_dir;
			} elseif(!@rename($previous_dir, $dest_dir)) {
				$error .= '; cannot restore the previous package, preserved at '.$previous_dir;
			} else {
				$error .= '; previous destination restored';
			}
		}
		return FALSE;
	}
	if($had_current_dest) {
		$cleanup_error = '';
		if(!plugin_package_remove_path($previous_dir, $cleanup_error)) {
			$error = 'Plugin package restored, but displaced package cleanup failed: '.$cleanup_error;
			return FALSE;
		}
	}
	return TRUE;
}

function plugin_package_restore_absent($dest_dir, $previous_dir, &$error = '') {
	if(plugin_package_path_exists($previous_dir)) {
		$error = 'Restore recovery path already exists: '.$previous_dir;
		return FALSE;
	}
	if(!plugin_package_path_exists($dest_dir)) return TRUE;
	if(!@rename($dest_dir, $previous_dir)) {
		$error = 'Cannot move new plugin package to recovery path: '.$dest_dir;
		return FALSE;
	}
	$cleanup_error = '';
	if(!plugin_package_remove_path($previous_dir, $cleanup_error)) {
		$error = 'New plugin package was isolated but could not be removed completely: '.$cleanup_error;
		return FALSE;
	}
	return TRUE;
}

function plugin_package_dirs_equal($source_dir, $staging_dir, &$error = '') {
	$source_manifest = array();
	$staging_manifest = array();
	if(!plugin_package_dir_manifest($source_dir, $source_manifest, $error)) return FALSE;
	if(!plugin_package_dir_manifest($staging_dir, $staging_manifest, $error)) return FALSE;
	ksort($source_manifest, SORT_STRING);
	ksort($staging_manifest, SORT_STRING);
	if($source_manifest !== $staging_manifest) {
		$error = 'Staged plugin package verification failed.';
		return FALSE;
	}
	return TRUE;
}

function plugin_package_manifest_digest($manifest) {
	ksort($manifest, SORT_STRING);
	$context = hash_init('sha256');
	$file_count = 0;
	foreach($manifest as $relative=>$entry) {
		strncmp($entry, 'file:', 5) === 0 AND $file_count++;
		// NUL is not valid in a filesystem path, so length-independent framing is unambiguous even
		// when a package filename contains spaces, quotes, newlines, or glob metacharacters.
		hash_update($context, $relative."\0".$entry."\0");
	}
	return array('sha256'=>hash_final($context), 'file_count'=>$file_count);
}

function plugin_package_manifest_summary($dir, &$summary, &$error = '') {
	$manifest = array();
	if(!plugin_package_dir_manifest($dir, $manifest, $error)) return FALSE;
	$summary = plugin_package_manifest_digest($manifest);
	return TRUE;
}

function plugin_package_manifest_path_sha256($path, &$error = '') {
	$real = realpath(rtrim(str_replace('\\', '/', $path), '/'));
	if($real === FALSE || !is_dir($real)) {
		$error = 'Manifest backup path is unavailable: '.$path;
		return FALSE;
	}
	return hash('sha256', rtrim(str_replace('\\', '/', $real), '/'));
}

// A had_dest=true snapshot created before the integrity format cannot safely activate its backup:
// there is no trusted value against which to distinguish the original copy from later mutation.
// Those in-memory snapshots do not survive a PHP deployment, so fail-closed is both safer and the
// only deterministic compatibility boundary. had_dest=false never activates backup contents.
function plugin_package_snapshot_manifest_verify($snapshot, $dir, $label, &$error = '') {
	if(!isset($snapshot['backup_manifest_version']) || $snapshot['backup_manifest_version'] !== 1
		|| !isset($snapshot['backup_manifest_path_sha256']) || !is_string($snapshot['backup_manifest_path_sha256'])
		|| !preg_match('/^[a-f0-9]{64}$/D', $snapshot['backup_manifest_path_sha256'])
		|| !isset($snapshot['backup_manifest_sha256']) || !is_string($snapshot['backup_manifest_sha256'])
		|| !preg_match('/^[a-f0-9]{64}$/D', $snapshot['backup_manifest_sha256'])
		|| !isset($snapshot['backup_manifest_file_count']) || !is_int($snapshot['backup_manifest_file_count'])
		|| $snapshot['backup_manifest_file_count'] < 0) {
		$error = 'Legacy plugin package snapshot has no valid integrity manifest; refusing rollback.';
		return FALSE;
	}
	$backup_path_sha256 = plugin_package_manifest_path_sha256(array_value($snapshot, 'backup_dir', ''), $error);
	if($backup_path_sha256 === FALSE) return FALSE;
	if(!hash_equals($snapshot['backup_manifest_path_sha256'], $backup_path_sha256)) {
		$error = 'Plugin package snapshot backup path changed before rollback.';
		return FALSE;
	}
	$summary = array();
	if(!plugin_package_manifest_summary($dir, $summary, $error)) return FALSE;
	if($summary['file_count'] !== $snapshot['backup_manifest_file_count']
		|| !hash_equals($snapshot['backup_manifest_sha256'], $summary['sha256'])) {
		$error = 'Plugin package '.$label.' integrity verification failed.';
		return FALSE;
	}
	return TRUE;
}

function plugin_package_dir_manifest($dir, &$manifest, &$error = '', $prefix = '') {
	$dir = rtrim(str_replace('\\', '/', $dir), '/').'/';
	if(is_link(rtrim($dir, '/')) || !is_dir($dir)) {
		$error = 'Manifest source directory missing or unsafe: '.$dir;
		return FALSE;
	}
	$items = plugin_dir_items($dir);
	usort($items, function($a, $b) { return strcmp(str_replace('\\', '/', $a), str_replace('\\', '/', $b)); });
	foreach($items as $item) {
		$item = str_replace('\\', '/', $item);
		$name = basename($item);
		$relative = $prefix === '' ? $name : $prefix.'/'.$name;
		if(is_link($item)) {
			$error = 'Symlink is not allowed: '.$relative;
			return FALSE;
		} elseif(is_dir($item)) {
			$manifest[$relative] = 'dir';
			if(!plugin_package_dir_manifest($item.'/', $manifest, $error, $relative)) return FALSE;
		} elseif(is_file($item)) {
			$size = @filesize($item);
			$hash = @hash_file('sha256', $item);
			if($size === FALSE || $hash === FALSE) {
				$error = 'Cannot verify staged file: '.$relative;
				return FALSE;
			}
			$manifest[$relative] = 'file:'.$size.':'.$hash;
		} else {
			$error = 'Unsupported file type: '.$relative;
			return FALSE;
		}
	}
	return TRUE;
}

function plugin_package_snapshot_delete($snapshot) {
	if(empty($snapshot) || !is_array($snapshot) || empty($snapshot['backup_dir'])) return TRUE;
	$error = '';
	return plugin_package_remove_path($snapshot['backup_dir'], $error);
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
		plugin_lifecycle_restore_or_fail($dir, NULL, $package_snapshot);
		$error = $errors[0];
		plugin_message(-1, 'Plugin PHP syntax check failed: '.htmlspecialchars($error['file']).' '.htmlspecialchars($error['detail']));
	}
	return TRUE;
}

function plugin_check_exists($dir, $local = TRUE) {
	global $plugins, $official_plugins;
	!plugin_dir_is_valid($dir) AND plugin_message(-1, lang('plugin_name_error'));
	if($local) {
		!isset($plugins[$dir]) AND plugin_message(-1, lang('plugin_not_exists'));
		plugin_require_package_root($dir);
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
	// This route is itself executing from an _include() cache snapshot. PHP has already opened the
	// file, so release its reader lease before upgrading the request to lifecycle-exclusive state.
	plugin_include_cache_reader_release_all();
	if(!plugin_state_visibility_write_lock_start()) {
		plugin_lock_end();
		message(-1, lang('plugin_task_locked'));
	}
	// The request-level plugin list was built before waiting for the task lock. Rebuild it from an
	// empty snapshot while holding the exclusive visibility lock, then validate every transition
	// against this final generation.
	plugin_init() === TRUE OR plugin_message(-1, 'Unable to reload plugin state after acquiring the task lock.');
}

function plugin_lock_end() {
	global $plugin_task_locked;
	if(empty($plugin_task_locked)) return;
	plugin_state_visibility_write_lock_end();
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
