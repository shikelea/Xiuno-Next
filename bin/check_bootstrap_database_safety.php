<?php

$root = dirname(__DIR__);
$source = file_get_contents($root.'/index.php');
$docker_smoke = file_get_contents($root.'/bin/check_docker_http_smoke.sh');

function fail($message) {
	fwrite(STDERR, "FAIL: $message\n");
	exit(1);
}

is_string($source) || fail('Unable to read the production bootstrap.');
$runtime_pos = strpos($source, "include XIUNOPHP_PATH . 'xiunophp.min.php';");
$probe_pos = strpos($source, 'if(!is_object($db) || !db_connect($db))');
$plugin_pos = strpos($source, "include APP_PATH . 'model/plugin.func.php';");
$session_model_pos = strpos($source, "include _include(APP_PATH . 'model.inc.php');");
($runtime_pos !== FALSE && $probe_pos !== FALSE && $plugin_pos !== FALSE && $session_model_pos !== FALSE
	&& $runtime_pos < $probe_pos && $probe_pos < $plugin_pos && $probe_pos < $session_model_pos)
	|| fail('Database readiness must be checked after the DB runtime exists but before plugins, models, or Sessions can run.');

$failure = substr($source, $probe_pos, $plugin_pos - $probe_pos);
strpos($failure, 'http_response_code(503);') !== FALSE
	&& strpos($failure, "header('Retry-After: 60');") !== FALSE
	&& strpos($failure, "header('Cache-Control: no-store');") !== FALSE
	|| fail('Database startup failure must return a non-cacheable HTTP 503 with retry guidance.');
strpos($failure, "if(\$ajax || param(0, '') === 'api')") !== FALSE
	&& strpos($failure, "header('Content-Type: application/json; charset=UTF-8');") !== FALSE
	&& strpos($failure, "array('code'=>'-1', 'message'=>\$service_message, 'request_id'=>xn_request_id_current())") !== FALSE
	|| fail('AJAX and API callers must receive a non-empty structured 503 response.');
strpos($failure, "header('Content-Type: text/html; charset=UTF-8');") !== FALSE
	&& strpos($failure, 'htmlspecialchars($service_message, ENT_QUOTES | ENT_SUBSTITUTE') !== FALSE
	|| fail('Browser callers must receive a UTF-8 HTML diagnostic without unsafe interpolation.');
substr_count($failure, 'exit;') === 1
	|| fail('Database failure branch must terminate exactly once before application bootstrap continues.');

is_string($docker_smoke)
	&& strpos($docker_smoke, 'Database outage did not return HTTP 503.') !== FALSE
	&& strpos($docker_smoke, 'Database outage AJAX response was not structured JSON.') !== FALSE
	|| fail('Docker HTTP smoke must exercise browser and AJAX behavior with the database stopped.');

echo "OK: bootstrap database safety checks passed\n";
