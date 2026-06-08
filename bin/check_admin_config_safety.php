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

$setting_route = source_text($root.'/admin/route/setting.php');
$update_route = source_text($root.'/admin/route/update.php');

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
strpos($shared_http, 'update_url_public_https_allowed($url)') !== FALSE
	|| strpos($shared_http, 'update_url_public_https_allowed($current, $resolved_ips)') !== FALSE
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
strpos($shared_http, 'update_url_public_https_allowed($url)') !== FALSE
	|| strpos($shared_http, 'update_url_public_https_allowed($current, $resolved_ips)') !== FALSE
	|| fail('Update binary download must enforce public HTTPS URL boundaries.');

$conf_setting = section_between($update_route, 'function update_conf_setting', "\n}\n\n?>");
strpos($conf_setting, '=== strlen($s)') !== FALSE
	|| fail('update_conf_setting() must detect partial writes.');
strpos($conf_setting, '$count = 0;') !== FALSE
	|| fail('update_conf_setting() must verify replacement or append regex matches.');
strpos($conf_setting, 'if ($count < 1) return FALSE;') !== FALSE
	|| fail('update_conf_setting() must fail when no config entry was replaced or appended.');

echo "OK: admin config safety checks passed\n";
