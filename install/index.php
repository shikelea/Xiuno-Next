<?php

define('DEBUG', 0);
define('APP_PATH', realpath(dirname(__FILE__).'/../').'/');
define('INSTALL_PATH', dirname(__FILE__).'/');

define('MESSAGE_HTM_PATH', INSTALL_PATH.'view/htm/message.htm');
define('INSTALL_LOCK_FILE', APP_PATH.'conf/.installed.lock');

// 切换到上一级目录，操作很方便。

include INSTALL_PATH.'install.func.php';
require_once INSTALL_PATH.'install-state.func.php';

$conf = (include APP_PATH.'conf/conf.default.php');
$conf['lang'] = install_language_resolve(isset($conf['lang']) ? $conf['lang'] : 'zh-cn');
$lang = include APP_PATH."lang/{$conf['lang']}/bbs.php";
$lang += include APP_PATH."lang/{$conf['lang']}/bbs_install.php";
$conf['log_path'] = APP_PATH.$conf['log_path']; 
$conf['tmp_path'] = APP_PATH.$conf['tmp_path']; 

include APP_PATH.'xiunophp/xiunophp.php';

// 引入 Composer 自动加载
if (file_exists(APP_PATH.'vendor/autoload.php')) {
    require APP_PATH.'vendor/autoload.php';
}

include APP_PATH.'model/misc.func.php';
include APP_PATH.'model/plugin.func.php';
include APP_PATH.'model/user.func.php';
include APP_PATH.'model/group.func.php';
include APP_PATH.'model/check.func.php';
include APP_PATH.'model/form.func.php';
include APP_PATH.'model/forum.func.php';

$action = param('action');

if(!in_array($method, array('GET', 'POST'), TRUE)) {
	message(-1, 'Method Not Allowed');
}
install_session_start();

// Use the same state model as the front entry: valid installs redirect, incomplete
// states stop with an actionable diagnostic instead of bouncing forever.
$install_state = xn_install_state_inspect(APP_PATH.'conf/conf.php', INSTALL_LOCK_FILE);
$install_state['state'] === 'valid' AND message(0, jump(lang('installed_tips'), '../'));
if(in_array($install_state['state'], array('present-invalid', 'lock-only'), TRUE)) {
	$diagnostic = xn_install_state_diagnostic($install_state['state']);
	http_response_code(503);
		message(-1, $diagnostic['message']);
}

// 语言已在加载语言包前从白名单 POST/cookie 中解析。
$_lang = $conf['lang'];



// 第一步，阅读
if(empty($action)) {
	
	if($method == 'GET') {
		$input = array();
		$input['lang'] = form_select('lang', array('zh-cn'=>'简体中文', 'zh-tw'=>'正體中文', 'en-us'=>'English', 'ru-ru'=>'Русский', 'th-th'=>'ไทย'), $conf['lang']);
		
		// 修改 conf.php
		include INSTALL_PATH."view/htm/index.htm";
	} else {
		install_csrf_check();
		$_lang = install_language_normalize(install_post('lang'), 'zh-cn');
		$conf['lang'] = $_lang;
		xn_setcookie('lang', $_lang, 0, '', TRUE);
		
		http_location('index.php?action=license');
	}
	
} elseif($action == 'license') {
	
	
	// 设置到 cookie
	
	include INSTALL_PATH."view/htm/license.htm";
	
} elseif($action == 'env') {
	
	if($method == 'GET') {
		$requirements = install_requirements_check();
		$succeed = $requirements['ok'] ? 1 : 0;
		$env = $requirements['env'];
		$write = $requirements['write'];
		include INSTALL_PATH."view/htm/env.htm";
	} else {
	
	}
	
} elseif($action == 'db') {
	
	if($method == 'GET') {
		
		$succeed = 1;
		$mysql_support = function_exists('mysql_connect');
		$pdo_mysql_support = extension_loaded('pdo_mysql');
		$myisam_support = extension_loaded('pdo_mysql');
		$innodb_support = extension_loaded('pdo_mysql');
		$db_defaults = install_db_form_defaults();
		
		(!$mysql_support && !$pdo_mysql_support) AND message(-1, lang('evn_not_support_php_mysql'));

		include INSTALL_PATH."view/htm/db.htm";
		
	} else {
		// Re-read the shared state immediately before accepting an installation POST. Another
		// request may have published a valid or invalid state after this request entered.
		$post_install_state = xn_install_state_inspect(APP_PATH.'conf/conf.php', INSTALL_LOCK_FILE);
		$post_install_state['state'] === 'valid' AND message(0, jump(lang('installed_tips'), '../'));
		if($post_install_state['state'] !== 'missing') {
			$diagnostic = xn_install_state_diagnostic($post_install_state['state']);
			http_response_code(503);
			message(-1, $diagnostic['message']);
		}
		install_csrf_check();
		$requirements = install_requirements_check();
		empty($requirements['ok']) AND message(-1, implode("\n", $requirements['errors']));
		
		$type = install_post('type');
		$engine = install_post('engine');
		$host = install_post('host');
		$name = install_post('name');
		$user = install_post('user');
		$password = install_post('password');
		
		$adminemail = install_post('adminemail');
		$adminuser = install_post('adminuser');
		$adminpass = install_post('adminpass');
		
		// 强制使用 pdo_mysql，防止意外
		if($type == 'mysql') $type = 'pdo_mysql';
		
		empty($host) AND message('host', lang('dbhost_is_empty'));
		!install_db_host_port($host, $port) AND message('host', 'Database host must be a hostname/IP with an optional numeric port.');
		$db_host = $port == 3306 ? $host : $host . ':' . $port;
		$type !== 'pdo_mysql' AND message('type', 'Only pdo_mysql is supported.');
		!in_array($engine, array('innodb', 'myisam'), TRUE) AND message('engine', 'Database engine must be innodb or myisam.');
		empty($name) AND message('name', lang('dbname_is_empty'));
		!install_db_name_safe($name) AND message('name', 'Database name may only contain letters, numbers and underscores.');
		empty($user) AND message('user', lang('dbuser_is_empty'));
		empty($adminemail) AND message('adminemail', lang('please_input_email'));
		empty($adminuser) AND message('adminuser', lang('please_input_username'));
		empty($adminpass) AND message('adminpass', lang('please_input_password'));
		!is_email($adminemail, $err) AND message('adminemail', $err);
		!is_username($adminuser, $err) AND message('adminuser', $err);
		!is_password(md5($adminpass), $err) AND message('adminpass', $err);

		install_lock_start();
		
		// 设置超时尽量短一些
		//set_time_limit(60);
		ini_set('mysql.connect_timeout',  5);
		ini_set('default_socket_timeout', 5); 

		$conf['db']['type'] = $type;	
		$conf['db']['mysql']['master']['host'] = $db_host;
		$conf['db']['mysql']['master']['name'] = $name;
		$conf['db']['mysql']['master']['user'] = $user;
		$conf['db']['mysql']['master']['password'] = $password;
		$conf['db']['mysql']['master']['engine'] = $engine;
		$conf['db']['pdo_mysql']['master']['host'] = $db_host;
		$conf['db']['pdo_mysql']['master']['name'] = $name;
		$conf['db']['pdo_mysql']['master']['user'] = $user;
		$conf['db']['pdo_mysql']['master']['password'] = $password;
		$conf['db']['pdo_mysql']['master']['engine'] = $engine;

		$auth_key = install_secure_random_hex(32);
		$auth_key === FALSE AND message(-1, 'Unable to generate a secure installation key.');
		try {
			$password_hash = password_hash(md5($adminpass), PASSWORD_BCRYPT);
		} catch(Throwable $e) {
			$password_hash = FALSE;
		}
		(!is_string($password_hash) || $password_hash === '') AND message(-1, 'Unable to secure the administrator password.');
		$replace = array(
			'db'=>$conf['db'],
			'auth_key'=>$auth_key,
			'installed'=>1,
			'lang'=>$conf['lang'],
		);
		$conf_stage = install_config_stage_begin(
			APP_PATH.'conf/conf.default.php',
			APP_PATH.'conf/conf.php',
			$replace
		);
		empty($conf_stage['ok']) AND message(-1, $conf_stage['error']);
		foreach(array('tmp', 'attach', 'avatar', 'forum') as $upload_dir) {
			install_directory_prepare(APP_PATH.'upload/'.$upload_dir, 0777) === FALSE
				AND message(-1, 'Failed to initialize the upload directories.');
		}
		
		$_SERVER['db'] = $db = db_new($conf['db']);
		// 此处可能报错
		
		$r = db_connect($db);
		
		if($r === FALSE) {
			if($errno == 1049 || $errno == 1045) {
				if($type == 'pdo_mysql') {
					try {
						$attr = array(
							PDO::ATTR_TIMEOUT => 5,
							//PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
						);
						$link = new PDO("mysql:host=$host;port=$port", $user, $password, $attr);
						$db_charset = isset($conf['db'][$type]['master']['charset']) ? $conf['db'][$type]['master']['charset'] : 'utf8';
						$charset_clause = install_db_charset_clause($db_charset);
						$r = $link->exec("CREATE DATABASE `$name` $charset_clause");
						if($r === FALSE) {
							$error = $link->errorInfo();
							$errno = $error[1];
							$errstr = $error[2];
						}
					} catch (PDOException $e) {
						$errno = $e->getCode();
						$errstr = $e->getMessage();
					}
				}
			}
			if($r === FALSE) {
				message(-1, "$errstr (errno: $errno)");
			}
		}
		
		$conf['cache']['mysql']['db'] = $db; // 这里直接传 $db，复用 $db；如果传配置文件，会产生新链接。
		$_SERVER['cache'] = $cache = !empty($conf['cache']) ? cache_new($conf['cache']) : NULL;
		
		// 设置引擎的类型
		if($engine == 'innodb') {
			$db->innodb_first = TRUE;
		} else {
			$db->innodb_first = FALSE;
		}
		
		// 连接成功以后，先在同一连接加锁并确认目标库没有任何 Xiuno 核心表。
		// 检查失败与发现旧表都 fail-closed，绝不进入 schema DDL。
		$prepare = install_database_prepare($db, INSTALL_PATH.'install.sql');
		empty($prepare['ok']) AND message(-1, $prepare['error']);
		
		// 初始化管理员、本地化数据与必要目录。0 行更新可能是幂等成功，只有 FALSE 才失败。
		$update = array('username'=>$adminuser, 'email'=>$adminemail, 'password'=>$password_hash, 'salt'=>'', 'create_date'=>$time, 'create_ip'=>$longip);
		$admin_expected = array('username'=>$adminuser, 'email'=>$adminemail, 'password'=>$password_hash, 'salt'=>'');
		$admin_write = db_update('user', array('uid'=>1), $update);
		$admin_record = db_find_one('user', array('uid'=>1));
		!install_record_update_verified($admin_write, $admin_record, $admin_expected)
			AND message(-1, 'Failed to initialize the administrator account.');
		foreach(array(0, 1, 2, 4, 5, 6, 7, 101, 102, 103, 104, 105) as $gid) {
			$group_expected = array('name'=>lang('group_'.$gid));
			$group_write = group_update($gid, $group_expected);
			$group_record = db_find_one('group', array('gid'=>$gid));
			!install_record_update_verified($group_write, $group_record, $group_expected)
				AND message(-1, 'Failed to initialize localized group names.');
		}
		$forum_expected = array('name'=>lang('default_forum_name'), 'brief'=>lang('default_forum_brief'));
		$forum_write = forum_update(1, $forum_expected);
		$forum_record = db_find_one('forum', array('fid'=>1));
		!install_record_update_verified($forum_write, $forum_record, $forum_expected)
			AND message(-1, 'Failed to initialize the default forum.');

		// conf.php 是最后核心提交点；同目录 no-clobber link 原子发布，此前退出会清理 owned staging。
		install_config_stage_commit($conf_stage) === FALSE
			AND message(-1, lang('write_to_file_failed'));
		// 防御纵深锁可由前台入口重建，因此提交后写失败不回滚已验证的 conf.php。
		@file_put_contents(INSTALL_LOCK_FILE, date('c')."\n", LOCK_EX);
		
		install_db_advisory_lock_end();
		install_lock_end();
		message(0, jump(lang('conguralation_installed'), '../'));
	}
}

function install_lock_start() {
	global $install_task_locked;
	!xn_lock_start('install_task', 600) AND message(-1, 'Another install task is running, please wait.');
	$install_task_locked = TRUE;
	register_shutdown_function('install_lock_end');
}

function install_lock_end() {
	global $install_task_locked;
	// Shutdown handlers run in registration order. Clean owned staging and release the DB lock here
	// before exposing the local task lock to another installer request.
	install_config_stage_cleanup();
	install_db_advisory_lock_end();
	if(empty($install_task_locked)) return;
	xn_lock_end('install_task');
	$install_task_locked = FALSE;
}

function install_db_name_safe($name) {
	return is_string($name) && preg_match('/^[A-Za-z0-9_]{1,64}$/', $name);
}

function install_db_charset_clause($charset) {
	$charset = strtolower(trim((string)$charset));
	if($charset == 'utf8mb4') return 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
	if($charset == 'utf8') return 'CHARACTER SET utf8 COLLATE utf8_general_ci';
	return 'CHARACTER SET utf8 COLLATE utf8_general_ci';
}

function install_post($key, $defval = '', $htmlspecialchars = TRUE) {
	$val = _POST($key, $defval);
	return param_force($val, $defval, $htmlspecialchars, FALSE);
}

function install_session_start() {
	if(session_status() !== PHP_SESSION_ACTIVE) {
		session_start();
	}
}

function install_csrf_token() {
	install_session_start();
	if(empty($_SESSION['install_csrf_token'])) {
		$_SESSION['install_csrf_token'] = bin2hex(random_bytes(32));
	}
	return $_SESSION['install_csrf_token'];
}

function install_csrf_check() {
	install_session_start();
	$token = _POST('_token', '');
	if(empty($token) || empty($_SESSION['install_csrf_token']) || !hash_equals($_SESSION['install_csrf_token'], $token)) {
		message(-1, 'Bad install CSRF token.');
	}
}

function install_db_host_port(&$host, &$port) {
	$host = trim((string)$host);
	$port = 3306;
	if($host === '' || strlen($host) > 255 || preg_match('/[\x00-\x1F\x7F;]/', $host)) return FALSE;
	if(strpos($host, ':') !== FALSE) {
		$arr = explode(':', $host);
		if(count($arr) !== 2) return FALSE;
		$host = $arr[0];
		$port = $arr[1];
	}
	if($host === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $host)) return FALSE;
	if(!preg_match('/^\d{1,5}$/', (string)$port)) return FALSE;
	$port = intval($port);
	return $port >= 1 && $port <= 65535;
}

?>
