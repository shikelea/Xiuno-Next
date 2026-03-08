<?php

// hook model_misc_start.php


/*
	url("thread-create-1.htm");
	根据 $conf['url_rewrite_on'] 设置，返回以下四种格式：
	?thread-create-1.htm
	thread-create-1.htm
	?/thread/create/1
	/thread/create/1
*/
function url($url, $extra = array()) {
	$conf = _SERVER('conf');
	!isset($conf['url_rewrite_on']) AND $conf['url_rewrite_on'] = 0;
	
	// hook model_url_start.php
	
	$r = $path = $query = '';
	if(strpos($url, '/') !== FALSE) {
		$path = substr($url, 0, strrpos($url, '/') + 1);
		$query = substr($url, strrpos($url, '/') + 1);
	} else {
		$path = '';
		$query = $url;
	}
	
	if($conf['url_rewrite_on'] == 0) {
		$r = $path . '?' . $query . '.htm';
	} elseif($conf['url_rewrite_on'] == 1) {
		$r = $path . $query . '.htm';
	} elseif($conf['url_rewrite_on'] == 2) {
		$r = $path . '?' . str_replace('-', '/', $query);
	} elseif($conf['url_rewrite_on'] == 3) {
		$r = $path . str_replace('-', '/', $query);
	}
	// 附加参数
	if($extra) {
		$args = http_build_query($extra);
		$sep = strpos($r, '?') === FALSE ? '?' : '&';
		$r .= $sep.$args;
	}
	
	// 主题兼容层：全局 URL 参数注册（主题通过 url_extra_register() 注入）
	global $g_url_extra_params;
	if(!empty($g_url_extra_params) && is_array($g_url_extra_params)) {
		foreach($g_url_extra_params as $cb) {
			if(is_callable($cb)) {
				$extra_params = call_user_func($cb, $url, $r);
				if(!empty($extra_params) && is_array($extra_params)) {
					$args = http_build_query($extra_params);
					$sep = strpos($r, '?') === FALSE ? '?' : '&';
					$r .= $sep.$args;
				}
			}
		}
	}
	
	// hook model_url_end.php
	
	return $r;
}


// 检测站点的运行级别
function check_runlevel() {
	global $conf, $method, $gid;
	// hook model_check_runlevel_start.php
	
	if($gid == 1) return;
	$param0 = param(0);
	$param1 = param(1);
	if($param0 == 'user' && in_array($param1, array('login', 'create', 'logout', 'sendinitpw', 'resetpw', 'resetpw_sendcode', 'resetpw_complete', 'synlogin'))) return;
	switch ($conf['runlevel']) {
		case 0: message(-1, $conf['runlevel_reason']); break;
		case 1: message(-1, lang('runlevel_reson_1')); break;
		case 2: ($gid == 0 || $method != 'GET') AND message(-1, lang('runlevel_reson_2')); break;
		case 3: $gid == 0 AND message(-1, lang('runlevel_reson_3')); break;
		case 4: $method != 'GET' AND message(-1, lang('runlevel_reson_4')); break;
		//case 5: break;
	}
	// hook model_check_runlevel_end.php
}

/*
	message(0, '登录成功');
	message(1, '密码错误');
	message(-1, '数据库连接失败');
	
	code:
		< 0 全局错误，比如：系统错误：数据库丢失连接/文件不可读写
		= 0 正确
		> 0 一般业务逻辑错误，可以定位到具体控件，比如：用户名为空/密码为空
*/
function message($code, $message, $extra = array()) {
	global $ajax, $header, $conf;
	
	$arr = $extra;
	$arr['code'] = $code.'';
	$arr['message'] = $message;
	$header['title'] = $conf['sitename'];
	
	// hook model_message_start.php
	
	// 防止 message 本身出现错误死循环
	static $called = FALSE;
	$called ? exit(xn_json_encode($arr)) : $called = TRUE;
	
	// HTMX 兼容层：原生支持 HTMX 请求的消息响应
	$is_htmx = isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'] === 'true';
	if($is_htmx && !$ajax && !theme_has('htmx_message')) {
		$msg_str = is_array($message) ? print_r($message, true) : (string)$message;
		$int_code = intval($code);
		$type = ($int_code === 0) ? 'success' : (($int_code === -1) ? 'danger' : (($int_code >= 1) ? 'warning' : 'info'));
		// hook model_message_htmx_before.php
		$trigger_data = array('showMessage' => array('code' => $int_code, 'type' => $type, 'message' => $msg_str));
		$hx_trigger_name = 'HX-Trigger';
		// hook model_message_htmx_trigger.php
		header($hx_trigger_name . ': ' . json_encode($trigger_data, JSON_UNESCAPED_UNICODE));
		echo $msg_str;
		// hook model_message_htmx_after.php
		exit;
	}
	if($ajax) {
		echo xn_json_encode($arr);
	} else {
		if(IN_CMD) {
			if(is_array($message) || is_object($message)) {
				print_r($message);
			} else {
				echo $message;
			}
			exit;
		} else {
			if(defined('MESSAGE_HTM_PATH')) {
				include _include(MESSAGE_HTM_PATH);
			} else {
				include _include(APP_PATH."view/htm/message.htm");
			}
		}
	}
	// hook model_message_end.php
	exit;
}

// 上锁
function xn_lock_start($lockname = '', $life = 10) {
	global $conf, $time;
	$lockfile = $conf['tmp_path'].'lock_'.$lockname.'.lock';
	if(is_file($lockfile)) {
		// 大于 $life 秒，删除锁
		if($time - filemtime($lockfile) > $life) {
			xn_unlink($lockfile);
		} else {
			// 锁存在，上锁失败。
			return FALSE;
		}
	}
	
	$r = file_put_contents($lockfile, $time, LOCK_EX);
	return $r;
}

// 删除锁
function xn_lock_end($lockname = '') {
	global $conf, $time;
	$lockfile = $conf['tmp_path'].'lock_'.$lockname.'.lock';
	xn_unlink($lockfile);
}


// class xn_html_safe 由 axiuno@gmail.com 编写

include_once XIUNOPHP_PATH.'xn_html_safe.func.php';

function xn_html_safe($doc, $arg = array()) {
	
	// hook model_xn_html_safe_start.php
	
	empty($arg['table_max_width']) AND $arg['table_max_width'] = 746; // 这个宽度为 bbs 回帖宽度
	
	$pattern = array (
		//'img_url'=>'#^(https?://[^\'"\\\\<>:\s]+(:\d+)?)?([^\'"\\\\<>:\s]+?)*$#is',
		'img_url'=>'#^(((https?://[^\'"\\\\<>:\s]+(:\d+)?)?([^\'"\\\\<>:\s]+?)*)|(data:image/png;base64,[\w\/+]+))$#is',
		'url'=>'#^(https?://[^\'"\\\\<>:\s]+(:\d+)?)?([^\'"\\\\<>:\s]+?)*$#is', // '#https?://[\w\-/%?.=]+#is'
		'mailto'=>'#^mailto:([\w%\-\.]+)@([\w%\-\.]+)(\.[\w%\-\.]+?)+$#is',
		'ftp_url'=>'#^ftp:([\w%\-\.]+)@([\w%\-\.]+)(\.[\w%\-\.]+?)+$#is',
		'ed2k_url'=>'#^(?:ed2k|thunder|qvod|magnet)://[^\s\'\"\\\\<>]+$#is',
		'color'=>'#^(\#\w{3,6})|(rgb\(\d+,\s*\d+,\s*\d+\)|(\w{3,10}))$#is',
		'safe'=>'#^[\w\-:;\.\s\x7f-\xff]+$#is',
		'css'=>'#^[\(,\)\#;\w\-\.\s\x7f-\xff]+$#is',
		'word'=>'#^[\w\-\x7f-\xff]+$#is',
	);

	$white_tag = array('a', 'b', 'i', 'u', 'font', 'strong', 'em', 'span',
		'table', 'tr', 'td', 'th', 'tbody', 'thead', 'tfoot','caption',
		'ol', 'ul', 'li', 'dl', 'dt', 'dd', 'menu', 'multicol',
		'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'p', 'div', 'pre',
		'br', 'img', 'area',  'embed', 'code', 'blockquote', 'iframe', 'section', 'fieldset', 'legend'
	);
	$white_value = array(
		'href'=>array('pcre', '', array($pattern['url'], $pattern['ed2k_url'])),
		'src'=>array('pcre', '', array($pattern['img_url'])),
		'width'=>array('range', '', array(0, 4096)),
		'height'=>array('range', 'auto', array(0, 80000)),
		'size'=>array('range', 4, array(-10, 10)),
		'border'=>array('range', 0, array(0, 10)),
		'family'=>array('pcre', '', array($pattern['word'])),
		'class'=>array('pcre', '', array($pattern['safe'])),
		'face'=>array('pcre', '', array($pattern['word'])),
		'color'=>array('pcre', '', array($pattern['color'])),
		'alt'=>array('pcre', '', array($pattern['safe'])),
		'label'=>array('pcre', '', array($pattern['safe'])),
		'title'=>array('pcre', '', array($pattern['safe'])),
		'target'=>array('list', '_self', array('_blank', '_self')),
		'type'=>array('pcre', '', array('#^[\w/\-]+$#')),
		'allowfullscreen'=>array('list', 'true', array('true', '1', 'on')),
		'wmode'=>array('list', 'transparent', array('transparent', '')),
		'allowscriptaccess'=>array('list', 'never', array('never')),
		'value'=>array('list', '', array('#^[\w+/\-]$#')),
		'cellspacing'=>array('range', 0, array(0, 10)),
		'cellpadding'=>array('range', 0, array(0, 10)),
		'frameborder'=>array('range', 0, array(0, 10)),
		'allowfullscreen'=>array('range', 0, array(0, 10)),
		'align'=>array('list', 'left', array('left', 'center', 'right')),
		'valign'=>array('list', 'middle', array('middle', 'top', 'bottom')),
        'name'=>array('pcre', '', array($pattern['word'])),
	);
	$white_css = array(
		'font'=>array('pcre', 'none', array($pattern['safe'])),
		'font-style'=>array('pcre', 'none', array($pattern['safe'])),
		'font-weight'=>array('pcre', 'none', array($pattern['safe'])),
		'font-family'=>array('pcre', 'none', array($pattern['word'])),
		'font-size'=>array('range', 12, array(6, 48)),
		'width'=>array('range', '100%', array(1, 1800)),
		'height'=>array('range', '', array(1, 80000)),
		'min-width'=>array('range', 1, array(1, 80000)),
		'min-height'=>array('range', 400, array(1, 80000)),
		'max-width'=>array('range', 1800, array(1, 80000)),
		'max-height'=>array('range', 80000, array(1, 80000)),
		'line-height'=>array('range', '14px', array(1, 50)),
		'color'=>array('pcre', '#000000', array($pattern['color'])),
		'background'=>array('pcre', 'none', array($pattern['color'], '#url\((https?://[^\'"\\\\<>]+?:?\d?)?([^\'"\\\\<>:]+?)*\)[\w\s\-]*$#')),
		'background-color'=>array('pcre', 'none', array($pattern['color'])),
		'background-image'=>array('pcre', 'none', array($pattern['img_url'])),
		'background-position'=>array('pcre', 'none', array($pattern['safe'])),
		'border'=>array('pcre', 'none', array($pattern['css'])),
		'border-left'=>array('pcre', 'none', array($pattern['css'])),
		'border-right'=>array('pcre', 'none', array($pattern['css'])),
		'border-top'=>array('pcre', 'none', array($pattern['css'])),
		'border-left-color'=>array('pcre', 'none', array($pattern['css'])),
		'border-right-color'=>array('pcre', 'none', array($pattern['css'])),
		'border-top-color'=>array('pcre', 'none', array($pattern['css'])),
		'border-bottom-color'=>array('pcre', 'none', array($pattern['css'])),
		'border-left-width'=>array('pcre', 'none', array($pattern['css'])),
		'border-right-width'=>array('pcre', 'none', array($pattern['css'])),
		'border-top-width'=>array('pcre', 'none', array($pattern['css'])),
		'border-bottom-width'=>array('pcre', 'none', array($pattern['css'])),
		'border-bottom-style'=>array('pcre', 'none', array($pattern['css'])),
		'margin-left'=>array('range', 0, array(0, 100)),
		'margin-right'=>array('range', 0, array(0, 100)),
		'margin-top'=>array('range', 0, array(0, 100)),
		'margin-bottom'=>array('range', 0, array(0, 100)),
		'margin'=>array('pcre', '', array($pattern['safe'])),
		'padding'=>array('pcre', '', array($pattern['safe'])),
		'padding-left'=>array('range', 0, array(0, 100)),
		'padding-right'=>array('range', 0, array(0, 100)),
		'padding-top'=>array('range', 0, array(0, 100)),
		'padding-bottom'=>array('range', 0, array(0, 100)),
		'zoom'=>array('range', 1, array(1, 10)),
		'list-style'=>array('list', 'none', array('disc', 'circle', 'square', 'decimal', 'lower-roman', 'upper-roman', 'none')),
		'text-align'=>array('list', 'left', array('left', 'right', 'center', 'justify')),
		'text-indent'=>array('range', 0, array(0, 100)),
		
		// 代码高亮需要支持，但是不安全！
		/*
		'position'=>array('list', 'static', array('absolute', 'fixed', 'relative', 'static')),
		'left'=>array('range', 0, array(0, 1000)),
		'top'=>array('range', 0, array(0, 1000)),
		'white-space'=>array('list', 'nowrap', array('nowrap', 'pre')),
		'word-wrap'=>array('list', 'normal', array('break-word', 'normal')),
		'word-break'=>array('list', 'break-all', array('break-all', 'normal')),
		'display'=>array('list', 'block', array('block', 'table', 'none', 'inline-block', 'table-cell')),
		'overflow'=>array('list', 'auto', array('scroll', 'hidden', 'auto')),
		'overflow-x'=>array('list', 'auto', array('scroll', 'hidden', 'auto')),
		'overflow-y'=>array('list', 'auto', array('scroll', 'hidden', 'auto')),
		*/
		
	);
	
	// hook model_xn_html_safe_new_before.php
	$safehtml = new HTML_White($white_tag, $white_value, $white_css, $arg);
	
	// hook model_xn_html_safe_parse_before.php
	$result = $safehtml->parse($doc);
	
	// hook model_xn_html_safe_end.php
	
	return $result;
}

/*
	api_output(0, 'OK', array('uid'=>1));
*/
function api_output($code, $message, $data = array()) {
	global $conf;
	$arr = array(
		'code' => $code,
		'message' => $message,
		'data' => $data,
	);
	
	// hook model_api_output_start.php
	
	header('Content-Type: application/json; charset=UTF-8');
	echo xn_json_encode($arr);
	exit;
}

// CSRF Token：生成或获取当前 session 的 CSRF token
function csrf_token() {
	if (empty($_SESSION['csrf_token'])) {
		$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
	}
	return $_SESSION['csrf_token'];
}

// CSRF Token：校验请求中的 token 是否合法
function csrf_check() {
	global $conf;
	if (isset($conf['csrf_on']) && $conf['csrf_on'] == 0) return;
	$token = isset($_REQUEST['_token']) ? $_REQUEST['_token'] : (isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : '');
	if (empty($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
		message(-1, 'CSRF token 校验失败，请刷新页面后重试。');
	}
}

// 主题兼容层：URL 参数注册器
// 主题可调用 url_extra_register(callback) 注册回调，url() 生成时自动调用
// callback 签名: function($url_input, $url_output) => array 或 null
function url_extra_register($callback) {
	global $g_url_extra_params;
	if(!isset($g_url_extra_params)) $g_url_extra_params = array();
	$g_url_extra_params[] = $callback;
}

// 主题兼容层：主题注册 API
// 主题可调用 theme_register() 声明自己的能力，核心据此做兼容
function theme_register($name, $capabilities = array()) {
	global $g_theme_info;
	if(!isset($g_theme_info)) $g_theme_info = array();
	$g_theme_info[$name] = array(
		'name' => $name,
		'capabilities' => $capabilities,
	);
}

// 主题兼容层：查询当前主题能力
function theme_has($capability) {
	global $g_theme_info;
	if(empty($g_theme_info)) return false;
	foreach($g_theme_info as $info) {
		if(in_array($capability, $info['capabilities'])) return true;
	}
	return false;
}

// 主题兼容层：资源注册 API
// 主题可注册 CSS/JS 资源，由核心统一在 header/footer 输出
function theme_enqueue_style($handle, $src, $priority = 10) {
	global $g_theme_styles;
	if(!isset($g_theme_styles)) $g_theme_styles = array();
	$g_theme_styles[$handle] = array('src' => $src, 'priority' => $priority);
}

function theme_enqueue_script($handle, $src, $priority = 10, $attrs = array()) {
	global $g_theme_scripts;
	if(!isset($g_theme_scripts)) $g_theme_scripts = array();
	$g_theme_scripts[$handle] = array('src' => $src, 'priority' => $priority, 'attrs' => $attrs);
}

// 主题兼容层：输出已注册的样式
function theme_render_styles() {
	global $g_theme_styles, $conf;
	if(empty($g_theme_styles)) return;
	$sv = isset($conf['static_version']) ? $conf['static_version'] : '';
	uasort($g_theme_styles, function($a, $b) { return $a['priority'] - $b['priority']; });
	foreach($g_theme_styles as $handle => $style) {
		echo '<link rel="stylesheet" href="' . htmlspecialchars($style['src']) . $sv . '" id="style-' . htmlspecialchars($handle) . '">' . "\n";
	}
}

// 主题兼容层：输出已注册的脚本
function theme_render_scripts() {
	global $g_theme_scripts, $conf;
	if(empty($g_theme_scripts)) return;
	$sv = isset($conf['static_version']) ? $conf['static_version'] : '';
	uasort($g_theme_scripts, function($a, $b) { return $a['priority'] - $b['priority']; });
	foreach($g_theme_scripts as $handle => $script) {
		$extra = '';
		foreach($script['attrs'] as $k => $v) { $extra .= ' ' . htmlspecialchars($k) . '="' . htmlspecialchars($v) . '"'; }
		echo '<script src="' . htmlspecialchars($script['src']) . $sv . '"' . $extra . ' id="script-' . htmlspecialchars($handle) . '"></script>' . "\n";
	}
}

// hook model_misc_end.php

?>