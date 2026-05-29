<?php

$root = dirname(__DIR__);
$setting_route = file_get_contents($root.'/admin/route/setting.php');
$update_route = file_get_contents($root.'/admin/route/update.php');

function fail($message) {
	fwrite(STDERR, "FAIL: $message\n");
	exit(1);
}

function section_between($source, $start, $end) {
	$start_pos = strpos($source, $start);
	if($start_pos === FALSE) fail("Missing section start: $start");
	$end_pos = strpos($source, $end, $start_pos + strlen($start));
	if($end_pos === FALSE) fail("Missing section end after $start: $end");
	return substr($source, $start_pos, $end_pos - $start_pos);
}

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
strpos($proxy_host, 'FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE') !== FALSE
	|| fail('Custom update proxies must reject private/reserved IP hosts.');
strpos($proxy_host, "substr(\$host, -6) === '.local'") !== FALSE
	|| fail('Custom update proxies must reject local hostnames.');

$http_get = section_between($update_route, 'function update_http_get', 'function update_github_download');
strpos($http_get, 'xn_http_url_allowed($url)') !== FALSE
	|| fail('Update HTTP GET must enforce allowed URL schemes.');
strpos($http_get, 'xn_http_curl_protocols') !== FALSE
	|| fail('Update HTTP GET must restrict cURL protocols.');

$download = section_between($update_route, 'function update_github_download_binary', 'function update_release_expected_sha256');
strpos($download, 'xn_http_url_allowed($url)') !== FALSE
	|| fail('Update binary download must enforce allowed URL schemes.');
strpos($download, 'xn_http_curl_protocols') !== FALSE
	|| fail('Update binary download must restrict cURL protocols.');

$conf_setting = section_between($update_route, 'function update_conf_setting', "\n}\n\n?>");
strpos($conf_setting, '=== strlen($s)') !== FALSE
	|| fail('update_conf_setting() must detect partial writes.');

echo "OK: admin config safety checks passed\n";
