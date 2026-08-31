<?php

$root = dirname(__DIR__);

function fail($message) {
	fwrite(STDERR, "FAIL: $message\n");
	exit(1);
}

function source_text($path) {
	$source = file_get_contents($path);
	$source === FALSE AND fail("Unable to read $path");
	return str_replace(array("\r\n", "\r"), "\n", $source);
}

function section_between($source, $start, $end) {
	$start_pos = strpos($source, $start);
	if($start_pos === FALSE) fail("Missing section start: $start");
	$end_pos = strpos($source, $end, $start_pos + strlen($start));
	if($end_pos === FALSE) fail("Missing section end after $start: $end");
	return substr($source, $start_pos, $end_pos - $start_pos);
}

function remove_fixture_tree($dir) {
	if(is_link($dir)) {
		@unlink($dir) || @rmdir($dir);
		return;
	}
	if(!is_dir($dir)) return;
	$items = scandir($dir);
	if(!is_array($items)) return;
	foreach($items as $item) {
		if($item === '.' || $item === '..') continue;
		$path = $dir.DIRECTORY_SEPARATOR.$item;
		if(is_link($path)) {
			@unlink($path) || @rmdir($path);
		} elseif(is_dir($path)) {
			remove_fixture_tree($path);
		} else {
			@unlink($path);
		}
	}
	@rmdir($dir);
}

function fixture_write($path, $content) {
	$parent = dirname($path);
	if(!is_dir($parent) && !mkdir($parent, 0777, TRUE) && !is_dir($parent)) {
		fail('Unable to create fixture directory: '.$parent);
	}
	file_put_contents($path, $content) === strlen($content)
		|| fail('Unable to write fixture: '.$path);
}

$setting_route = source_text($root.'/admin/route/setting.php');
$update_route = source_text($root.'/admin/route/update.php');
$locale_model_path = $root.'/model/locale.func.php';
is_file($locale_model_path) || fail('Missing shared locale availability helper.');
$locale_model = source_text($locale_model_path);
$smtp_model = source_text($root.'/model/smtp.func.php');

strpos($locale_model, "function locale_identifier_is_valid(") !== FALSE
	|| fail('Locale identifiers must use one shared validator.');
strpos($locale_model, "function locale_is_available(") !== FALSE
	|| fail('Locale resources must use one shared availability check.');
strpos($locale_model, "function locale_list_available(") !== FALSE
	|| fail('Admin locale options must be discovered through the shared availability check.');
strpos($locale_model, 'is_link($directory)') !== FALSE
	&& strpos($locale_model, 'is_link($file)') !== FALSE
	|| fail('Locale directories and required resource files must reject symbolic links.');

$locale_check_position = strpos($setting_route, 'locale_is_available($_lang');
$base_write_position = strpos($setting_route, "file_replace_var(APP_PATH.'conf/conf.php', \$replace)");
$locale_check_position !== FALSE && $base_write_position !== FALSE && $locale_check_position < $base_write_position
	|| fail('Base settings must reject an unavailable locale before writing conf.php.');
strpos($setting_route, "message('lang', lang('locale_unavailable'))") !== FALSE
	|| fail('Unavailable locale input must use the localized lang field error.');
strpos($setting_route, 'locale_list_available(APP_PATH') !== FALSE
	|| fail('Admin locale options must not be a hard-coded bundled-language list.');

strpos($smtp_model, 'file_replace_var_write($file, $original, $replacement)') !== FALSE
	|| fail('SMTP config must use the shared atomic checked config writer.');
strpos($setting_route, '$r = smtp_save();') !== FALSE
	|| fail('SMTP settings route must use the SMTP atomic save boundary.');
strpos($setting_route, "\$r === FALSE AND message(-1, lang('save_conf_failed', array('file'=>'conf/smtp.conf.php')))") !== FALSE
	|| fail('SMTP settings must report a localized failure only when atomic save fails.');
strpos($setting_route, "file_put_contents_try(APP_PATH.'conf/smtp.conf.php'") === FALSE
	|| fail('SMTP route must not truncate the live config directly.');

require_once $locale_model_path;
$fixture_root = rtrim(sys_get_temp_dir(), '/\\').'/xiuno-admin-config-safety-'.bin2hex(random_bytes(6));
$fixture_lang = $fixture_root.'/lang';
@mkdir($fixture_lang, 0777, TRUE);
register_shutdown_function(function() use ($fixture_root) { remove_fixture_tree($fixture_root); });

$required_locale_files = array('bbs.php', 'bbs_admin.php', 'bbs.js');
foreach($required_locale_files as $required_file) {
	fixture_write($fixture_lang.'/custom-locale/'.$required_file, $required_file === 'bbs.js' ? 'window.custom_locale=true;' : '<?php return array();');
}
fixture_write($fixture_lang.'/missing-admin/bbs.php', '<?php return array();');
fixture_write($fixture_lang.'/missing-admin/bbs.js', 'window.missing_admin=true;');
foreach($required_locale_files as $required_file) {
	fixture_write($fixture_lang.'/Mixed-Locale/'.$required_file, $required_file === 'bbs.js' ? 'window.mixed_locale=true;' : '<?php return array();');
}

locale_identifier_is_valid('custom-locale') || fail('A valid custom locale identifier was rejected.');
locale_identifier_is_valid(str_repeat('a', 64)) || fail('A 64-character locale identifier was rejected.');
!locale_identifier_is_valid(str_repeat('a', 65)) || fail('An overlong locale identifier was accepted.');
!locale_identifier_is_valid('Custom-Locale') || fail('A non-canonical uppercase locale identifier was accepted.');
!locale_identifier_is_valid('../custom-locale') || fail('A traversal locale identifier was accepted.');
locale_is_available('custom-locale', $fixture_lang) || fail('A complete custom locale was unavailable.');
!locale_is_available('missing-admin', $fixture_lang) || fail('A locale missing bbs_admin.php was available.');
!locale_is_available('../custom-locale', $fixture_lang) || fail('A traversal locale path was available.');
!locale_is_available('mixed-locale', $fixture_lang) || fail('A non-canonical locale directory was available by case folding.');
$available_locales = locale_list_available($fixture_lang);
$available_locales === array('custom-locale')
	|| fail('Locale discovery did not return exactly the complete safe locale fixture.');

$outside_locale = $fixture_root.'/outside-locale';
foreach($required_locale_files as $required_file) {
	fixture_write($outside_locale.'/'.$required_file, $required_file === 'bbs.js' ? 'window.outside_locale=true;' : '<?php return array();');
}
$locale_link = $fixture_lang.'/linked-locale';
$locale_link_created = function_exists('symlink') && @symlink($outside_locale, $locale_link);
if($locale_link_created) {
	!locale_is_available('linked-locale', $fixture_lang)
		|| fail('A symbolic-link locale directory was available.');
}

$linked_file_locale = $fixture_lang.'/linked-file';
@mkdir($linked_file_locale, 0777, TRUE);
fixture_write($linked_file_locale.'/bbs_admin.php', '<?php return array();');
fixture_write($linked_file_locale.'/bbs.js', 'window.linked_file=true;');
$locale_file_link_created = function_exists('symlink') && @symlink($outside_locale.'/bbs.php', $linked_file_locale.'/bbs.php');
if($locale_file_link_created) {
	!locale_is_available('linked-file', $fixture_lang)
		|| fail('A locale with a symbolic-link required resource was available.');
}
if((!$locale_link_created || !$locale_file_link_created) && DIRECTORY_SEPARATOR !== '\\') {
	fail('A Unix test host must support both locale symlink negative cases; refusing a false PASS.');
}
if(!$locale_link_created || !$locale_file_link_created) {
	echo "NOTICE: one or more locale symlink cases are unavailable on this Windows host; Unix CI must execute them.\n";
}

defined('APP_PATH') || define('APP_PATH', $fixture_root.'/');
$smtp_writer_mode = 'success';
$smtp_store = array();
function file_get_contents_try($file) {
	global $smtp_store;
	return array_key_exists($file, $smtp_store) ? $smtp_store[$file] : FALSE;
}
function file_replace_var_write($file, $original, $replacement) {
	global $smtp_writer_mode, $smtp_store;
	if(!array_key_exists($file, $smtp_store) || $smtp_store[$file] !== $original) return FALSE;
	if($smtp_writer_mode !== 'success') return FALSE;
	$smtp_store[$file] = $replacement;
	return strlen($replacement);
}
require_once $root.'/model/smtp.func.php';

$smtp_file = APP_PATH.'conf/smtp.conf.php';
$smtp_original_list = array(array('email'=>'old@example.test', 'host'=>'old.test', 'port'=>25, 'user'=>'old', 'pass'=>'secret'));
$smtp_original = "<?php\r\nreturn ".var_export($smtp_original_list, TRUE).";\r\n?>";
$smtp_replacement_list = array(array('email'=>'new@example.test', 'host'=>'new.test', 'port'=>587, 'user'=>'new', 'pass'=>'changed'));
$smtp_store = array($smtp_file=>$smtp_original);
$smtplist = $smtp_replacement_list;
$smtp_writer_mode = 'success';
smtp_save() !== FALSE || fail('A complete SMTP config save failed.');
$smtp_store[$smtp_file] === "<?php\r\nreturn ".var_export($smtp_replacement_list, TRUE).";\r\n?>"
	|| fail('SMTP save did not publish the requested list through the checked writer.');

$smtp_store = array($smtp_file=>$smtp_original);
$smtplist = $smtp_original_list;
$smtp_writer_mode = 'short-write';
smtp_update(0, array('host'=>'partial.test')) === FALSE
	|| fail('SMTP update reported success after an atomic writer failure.');
$smtp_store[$smtp_file] === $smtp_original
	|| fail('Failed SMTP update changed the previous config generation.');
$smtplist === $smtp_original_list
	|| fail('Failed SMTP update did not restore the in-memory list.');

$smtp_store = array($smtp_file=>$smtp_original);
$smtplist = $smtp_original_list;
$smtp_writer_mode = 'readback-mismatch';
smtp_delete(0) === FALSE || fail('SMTP delete reported success after readback verification failure.');
$smtp_store[$smtp_file] === $smtp_original || fail('Failed SMTP delete changed the previous config generation.');
$smtplist === $smtp_original_list || fail('Failed SMTP delete did not restore the in-memory list.');

// Execute the real procedural POST route with only its framework edges stubbed. An invalid locale
// must reach the field error before the config writer, while a complete custom locale may commit.
class AdminConfigMessage extends RuntimeException {
	public $response_code;
	public function __construct($code, $message) {
		parent::__construct((string)$message);
		$this->response_code = $code;
	}
}
$admin_config_params = array();
$admin_config_write_count = 0;
$admin_config_last_replace = array();
$admin_config_empty_include = $fixture_root.'/empty.php';
fixture_write($admin_config_empty_include, '<?php');
fixture_write($smtp_file, $smtp_original);
defined('DEBUG') || define('DEBUG', 0);
function _include($path) {
	global $admin_config_empty_include;
	return $admin_config_empty_include;
}
function param($key, $default = NULL, $filter = TRUE) {
	global $admin_config_params;
	return array_key_exists($key, $admin_config_params) ? $admin_config_params[$key] : $default;
}
function xn_html_safe($value) { return $value; }
function xn_substr($value, $start, $length) { return substr($value, $start, $length); }
function lang($key, $replace = array()) {
	$message = $key;
	foreach($replace as $name=>$value) $message = str_replace('{'.$name.'}', $value, $message);
	return $message;
}
function message($code, $message) { throw new AdminConfigMessage($code, $message); }
function file_replace_var($file, $replace = array(), $pretty = FALSE) {
	global $admin_config_write_count, $admin_config_last_replace;
	$admin_config_write_count++;
	$admin_config_last_replace = $replace;
	return 1;
}
function run_admin_base_post($setting_route_path, $locale) {
	global $admin_config_params, $admin_config_write_count, $admin_config_last_replace, $conf;
	$admin_config_params = array(
		1=>'base',
		'sitename'=>'Fixture site',
		'sitebrief'=>'Fixture brief',
		'runlevel'=>0,
		'user_create_on'=>1,
		'user_create_email_on'=>1,
		'user_resetpw_on'=>1,
		'lang'=>$locale,
	);
	$admin_config_write_count = 0;
	$admin_config_last_replace = array();
	$method = 'POST';
	$conf = array();
	try {
		include $setting_route_path;
	} catch(AdminConfigMessage $message) {
		return $message;
	}
	fail('Admin base POST route returned without a response.');
}

$invalid_locale_message = run_admin_base_post($root.'/admin/route/setting.php', 'missing-admin');
$invalid_locale_message->response_code === 'lang'
	|| fail('An incomplete locale did not return the lang field error.');
$admin_config_write_count === 0
	|| fail('An incomplete locale reached the conf.php writer.');

$valid_locale_message = run_admin_base_post($root.'/admin/route/setting.php', 'custom-locale');
$valid_locale_message->response_code === 0
	|| fail('A complete custom locale did not reach the success response.');
$admin_config_write_count === 1
	|| fail('A complete custom locale did not write conf.php exactly once.');
isset($admin_config_last_replace['lang']) && $admin_config_last_replace['lang'] === 'custom-locale'
	|| fail('The validated custom locale was not passed to the config writer.');

strpos($setting_route, "\$r = file_replace_var(APP_PATH.'conf/conf.php', \$replace);") !== FALSE
	|| fail('Base settings must capture conf.php write result.');
strpos($setting_route, "lang('save_conf_failed', array('file'=>'conf/conf.php'))") !== FALSE
	|| fail('Base settings must hard-fail when conf.php cannot be written.');

$test_proxy = section_between($update_route, "} elseif (\$action == 'test_proxy')", "} elseif (\$action == 'save_proxy')");
strpos($test_proxy, "update_proxy_normalize(_POST('proxy_url', ''))") !== FALSE
	|| fail('Proxy test must read proxy_url from POST and normalize it.');
strpos($test_proxy, "param('proxy_url', '', 'POST')") === FALSE
	|| fail('Proxy test must not misuse param() third argument as a method selector.');

$save_proxy = section_between($update_route, "} elseif (\$action == 'save_proxy')", "} elseif (\$action == 'download')");
strpos($save_proxy, "update_proxy_normalize(_POST('proxy_url', ''))") !== FALSE
	|| fail('Proxy save must read proxy_url from POST and normalize it.');
strpos($save_proxy, '!update_conf_setting(') !== FALSE
	|| fail('Proxy save must hard-fail when conf.php cannot be written.');

$proxy_normalize = section_between($update_route, 'function update_proxy_normalize', 'function update_proxy_public_host');
strpos($proxy_normalize, "strtolower(\$parts['scheme']) !== 'https'") !== FALSE
	|| fail('Custom update proxies must be HTTPS only.');
strpos($proxy_normalize, "\$parts['user']") !== FALSE
	|| fail('Custom update proxies must reject embedded credentials.');
strpos($proxy_normalize, "\$parts['query']") !== FALSE
	|| fail('Custom update proxies must reject query strings.');

$proxy_host = section_between($update_route, 'function update_proxy_public_host', 'function update_http_get_json');
strpos($proxy_host, 'update_public_ip_allowed($host)') !== FALSE
	|| fail('Custom update proxies must reject private/reserved IP hosts.');
strpos($update_route, 'FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE') !== FALSE
	|| fail('Custom update proxies must reject private/reserved resolved IP addresses.');
strpos($proxy_host, "substr(\$host, -6) === '.local'") !== FALSE
	|| fail('Custom update proxies must reject local hostnames.');
strpos($proxy_host, 'update_resolve_public_ips($host, $ips)') !== FALSE
	|| fail('Custom update proxy hostnames must resolve to public IP addresses.');

$http_get = section_between($update_route, 'function update_http_get', 'function update_github_download');
$shared_http = section_between($update_route, 'function update_http_get_body', 'function update_github_download');
strpos($http_get, 'update_http_get_body($url') !== FALSE
	|| fail('Update HTTP GET must use the shared bounded HTTP helper.');
strpos($shared_http, 'xn_http_curl_protocols') !== FALSE
	|| fail('Update HTTP GET must restrict cURL protocols.');
strpos($shared_http, "'verify_peer_name' => true") !== FALSE
	|| fail('Update HTTP GET stream fallback must explicitly verify TLS host names.');
strpos($shared_http, 'update_url_public_https_allowed($current, $resolved_ips, $error)') !== FALSE
	|| fail('Update HTTP GET must enforce public HTTPS URL boundaries.');
strpos($shared_http, "'cURL is required for safe online updates'") !== FALSE
	|| fail('Update HTTP GET must require cURL for DNS pinning.');
strpos($update_route, 'CURLOPT_RESOLVE') !== FALSE
	|| fail('Update HTTP GET must pin validated DNS results with CURLOPT_RESOLVE.');
strpos($update_route, 'dns_get_record($host, DNS_A | DNS_AAAA)') !== FALSE
	|| fail('Update HTTP GET must resolve and validate public A/AAAA records.');

$download = section_between($update_route, 'function update_github_download_binary', 'function update_release_expected_sha256');
strpos($download, 'update_http_get_body($url') !== FALSE
	|| fail('Update binary download must use the shared bounded HTTP helper.');
strpos($shared_http, 'xn_http_curl_protocols') !== FALSE
	|| fail('Update binary download must restrict cURL protocols.');
strpos($shared_http, "'verify_peer_name' => true") !== FALSE
	|| fail('Update binary stream fallback must explicitly verify TLS host names.');
strpos($shared_http, 'update_url_public_https_allowed($current, $resolved_ips, $error)') !== FALSE
	|| fail('Update binary download must enforce public HTTPS URL boundaries.');

$conf_setting = section_between($update_route, 'function update_conf_setting', "\n}\n\n?>");
strpos($conf_setting, '=== strlen($s)') !== FALSE
	|| fail('update_conf_setting() must detect partial writes.');
strpos($conf_setting, '$count = 0;') !== FALSE
	|| fail('update_conf_setting() must verify replacement or append regex matches.');
strpos($conf_setting, 'if ($count < 1) return FALSE;') !== FALSE
	|| fail('update_conf_setting() must fail when no config entry was replaced or appended.');

echo "OK: admin config safety checks passed\n";
