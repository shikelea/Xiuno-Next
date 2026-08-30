<?php

if(PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
	http_response_code(404);
	if(!headers_sent()) header('Content-Type: text/plain; charset=UTF-8');
	echo "Not Found\n";
	exit(1);
}

$app_path = realpath(__DIR__.'/..');
if($app_path === FALSE) {
	fwrite(STDERR, "Unable to locate the Xiuno application root.\n");
	exit(2);
}
$app_path = rtrim(str_replace('\\', '/', $app_path), '/').'/';

require_once $app_path.'install/install-state.func.php';
require_once $app_path.'model/plugin_safe_mode.func.php';

$install_state = xn_install_state_inspect($app_path.'conf/conf.php', $app_path.'conf/.installed.lock');
$conf = $install_state['state'] === 'valid' ? $install_state['config'] : array();
$config_note = $install_state['state'] === 'valid'
	? 'valid conf/conf.php'
	: $install_state['state'].' configuration; using APP tmp/log fallback; unavailable external paths cannot be inspected';
$action = isset($argv[1]) ? strtolower(trim((string)$argv[1])) : '';

if(!in_array($action, array('activate', 'status', 'deactivate'), TRUE) || count($argv) !== 2) {
	fwrite(STDERR, "Usage: php bin/plugin_safe_mode.php activate|status|deactivate\n");
	exit(2);
}

if($action === 'status') {
	$status = plugin_safe_mode_status($conf, $app_path);
	echo 'Safe mode: '.($status['active'] ? 'active' : 'inactive')."\n";
	echo 'Configuration: '.$config_note."\n";
	echo 'Marker: '.$status['marker_path']."\n";
	echo 'Lock: '.$status['lock_path']."\n";
	echo 'Log: '.$status['log_path']."\n";
	if($status['latest_error'] !== '') echo 'Latest error: '.$status['latest_error']."\n";
	exit(0);
}

if($action === 'activate') {
	$error = '';
	$marker_path = '';
	if(!plugin_safe_mode_enable($conf, $app_path, $error, $marker_path)) {
		fwrite(STDERR, 'Unable to activate plugin safe mode ('.$error."). Check tmp_path permissions and retry.\n");
		exit(3);
	}
	echo "Plugin safe mode is active.\n";
	echo 'Configuration: '.$config_note."\n";
	echo 'Marker: '.$marker_path."\n";
	exit(0);
}

$error = '';
$failed_paths = array();
if(!plugin_safe_mode_exit($conf, $app_path, $error, $failed_paths)) {
	$details = empty($failed_paths) ? '' : ' Remaining markers: '.implode('; ', $failed_paths);
	fwrite(STDERR, 'Unable to deactivate plugin safe mode ('.$error.').'.$details."\n");
	exit(3);
}
echo "Plugin safe mode is inactive.\n";
echo 'Configuration: '.$config_note."\n";
exit(0);

?>
