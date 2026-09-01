<?php

$root = dirname(__DIR__);

function fail($message) {
	fwrite(STDERR, "FAIL: $message\n");
	exit(1);
}

function G($key, $default = NULL) {
	return array_key_exists($key, $GLOBALS) ? $GLOBALS[$key] : $default;
}

function lang($key, $replace = array()) {
	$value = isset($GLOBALS['install_safety_lang'][$key]) ? $GLOBALS['install_safety_lang'][$key] : $key;
	foreach($replace as $name=>$replacement) $value = str_replace('{'.$name.'}', $replacement, $value);
	return $value;
}

function db_exec($sql) {
	$GLOBALS['install_safety_db_exec_calls'][] = $sql;
	$fail_at = isset($GLOBALS['install_safety_db_exec_fail_at']) ? intval($GLOBALS['install_safety_db_exec_fail_at']) : 0;
	return $fail_at > 0 && count($GLOBALS['install_safety_db_exec_calls']) === $fail_at ? FALSE : TRUE;
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

$install = source_text($root.'/install/index.php');
$install_func = source_text($root.'/install/install.func.php');
$install_db_view = source_text($root.'/install/view/htm/db.htm');
$install_header_view = source_text($root.'/install/view/htm/header.inc.htm');
$install_footer_view = source_text($root.'/install/view/htm/footer.inc.htm');
$install_sql = source_text($root.'/install/install.sql');
$misc_func = source_text($root.'/model/misc.func.php');
$composer = json_decode(source_text($root.'/composer.json'), TRUE);
is_array($composer) || fail('Unable to parse composer.json for installer requirement checks.');
$nginx = source_text($root.'/docker/nginx/conf.d/default.conf');
$dockerfile = source_text($root.'/docker/php/Dockerfile');
$compose = source_text($root.'/docker-compose.yml');
$workflow = source_text($root.'/.github/workflows/ci.yml');
$docker_smoke = source_text($root.'/bin/check_docker_http_smoke.sh');

$db_post = section_between($install, "} elseif(\$action == 'db')", "\n\t}\n}\n\nfunction install_lock_start");

require_once $root.'/install/install.func.php';
require_once $root.'/install/install-state.func.php';

defined('INSTALL_PATH') || define('INSTALL_PATH', $root.'/install/');
defined('DEBUG') || define('DEBUG', 0);
$GLOBALS['conf'] = array('lang'=>'th-th', 'version'=>'fixture');
$GLOBALS['install_safety_lang'] = array(
	'install_title'=>'Installer fixture',
	'install_guide'=>'Install guide',
);
$code = -1;
$message = 'Installer fixture error';
ob_start();
include INSTALL_PATH.'view/htm/message.htm';
$install_message_html = ob_get_clean();
is_string($install_message_html) && $install_message_html !== ''
	|| fail('Installer message shell must render executable HTML.');
preg_match('#<html\s+lang="th-th">#i', $install_message_html) === 1
	|| fail('Installer HTML language must follow the validated installer locale.');
preg_match('#class="[^"]*\bcollapse\b[^"]*"#i', $install_header_view) === 0
	|| fail('Installer navigation container must not be hidden by an unowned Bootstrap collapse state.');
strpos($install_header_view, 'navnavbar') === FALSE
	|| fail('Installer navigation must use valid Bootstrap navbar classes.');
strpos($install_message_html, '.icon-warning-sign:before') !== FALSE
	&& strpos($install_message_html, 'class="icon-warning-sign"') !== FALSE
	|| fail('Installer error messages must map and render the warning icon locally.');
substr_count(strtolower($install_message_html), '<div') === substr_count(strtolower($install_message_html), '</div>')
	|| fail('Installer shell must keep rendered div elements structurally balanced.');
strpos(str_replace(array("\r", "\n", "\t"), '', $install_footer_view), '</div></body>') === FALSE
	|| fail('Installer footer must not emit an unmatched closing div before body.');
$message_func = section_between($misc_func, 'function message($code, $message, $extra = array())', "\n}\n\n// 上锁");
strpos($message_func, "include defined('INSTALL_PATH') ? MESSAGE_HTM_PATH : _include(MESSAGE_HTM_PATH);") !== FALSE
	|| fail('Installer errors must bypass plugin cache locks without changing compiled admin message templates.');

if(class_exists('DOMDocument')) {
	$previous_libxml_errors = libxml_use_internal_errors(TRUE);
	$dom = new DOMDocument();
	$loaded = $dom->loadHTML($install_message_html, LIBXML_NONET);
	libxml_clear_errors();
	libxml_use_internal_errors($previous_libxml_errors);
	$loaded || fail('Installer message shell must be parseable as an HTML document.');
	$xpath = new DOMXPath($dom);
	$xpath->query('/html/body/div[@id="wrapper"]')->length === 1
		|| fail('Installer shell must render one wrapper root inside body.');
	$xpath->query('//*[@id="wrapper"]/*[@id="body"]')->length === 1
		&& $xpath->query('//*[@id="wrapper"]/*[@id="footer"]')->length === 1
		|| fail('Installer body and footer must remain direct children of the wrapper.');
	$nav_container = $xpath->query('//*[@id="header"]/*[contains(concat(" ", normalize-space(@class), " "), " container ")]');
	$nav_container->length === 1
		|| fail('Installer header must keep a visible Bootstrap container.');
	strpos(' '.$nav_container->item(0)->getAttribute('class').' ', ' collapse ') === FALSE
		|| fail('Installer header container must not require an absent collapse controller.');
	$nav_list = $xpath->query('//*[@id="nav_pc"]');
	$nav_list->length === 1
		&& strpos(' '.$nav_list->item(0)->getAttribute('class').' ', ' navbar-nav ') !== FALSE
		|| fail('Installer desktop navigation must expose the Bootstrap navbar-nav class.');
}

$installer_extensions = array_keys(install_required_extensions());
$composer_extensions = array();
foreach(array_keys($composer['require']) as $requirement) {
	if(strpos($requirement, 'ext-') === 0) $composer_extensions[] = substr($requirement, 4);
}
sort($installer_extensions, SORT_STRING);
sort($composer_extensions, SORT_STRING);
$installer_extensions === $composer_extensions
	|| fail('Composer and the installer must enforce the same required PHP extension set.');

function install_safety_remove_tree($path) {
	if(is_link($path) || is_file($path)) {
		@unlink($path);
		return;
	}
	if(!is_dir($path)) return;
	$entries = scandir($path);
	if(is_array($entries)) {
		foreach($entries as $entry) {
			if($entry === '.' || $entry === '..') continue;
			install_safety_remove_tree($path.'/'.$entry);
		}
	}
	@rmdir($path);
}

function install_safety_has_stage($target) {
	$matches = glob($target.'.install-*.tmp');
	return is_array($matches) && !empty($matches);
}

$auth_key = install_secure_random_hex(32);
is_string($auth_key) && preg_match('/\A[a-f0-9]{64}\z/', $auth_key) === 1
	|| fail('Installer authentication keys must be 64 lowercase hex characters from 32 secure random bytes.');
install_secure_random_hex(0) === FALSE && install_secure_random_hex(65) === FALSE
	|| fail('Installer secure-random helper must reject unsupported byte lengths.');

$expected_record = array('name'=>'localized');
install_record_update_verified(0, $expected_record, $expected_record)
	|| fail('A zero-row database update must remain valid when readback matches the expected state.');
!install_record_update_verified(FALSE, $expected_record, $expected_record)
	|| fail('A FALSE database update must fail even when a later record happens to match.');
!install_record_update_verified(0, array(), $expected_record)
	|| fail('A missing database row must fail readback instead of being treated as an idempotent update.');

$fixture_token = install_secure_random_hex(8);
$fixture_token !== FALSE || fail('Unable to allocate an installer safety fixture token.');
$fixture_root = rtrim(sys_get_temp_dir(), '/\\').'/xiuno-install-safety-'.getmypid().'-'.$fixture_token;
@mkdir($fixture_root, 0700, TRUE) || fail('Unable to create the installer safety fixture directory.');
register_shutdown_function(function() use ($fixture_root) { install_safety_remove_tree($fixture_root); });

$state_config = $fixture_root.'/state-conf.php';
$state_lock = $fixture_root.'/.installed.lock';
$state = xn_install_state_inspect($state_config, $state_lock);
$state['state'] === 'missing' && $state['config'] === NULL
	|| fail('Absent config and lock must resolve to the missing install state.');
file_put_contents($state_lock, "fixture\n", LOCK_EX) !== FALSE
	|| fail('Unable to create the lock-only install-state fixture.');
$state = xn_install_state_inspect($state_config, $state_lock);
$state['state'] === 'lock-only' && $state['lock_present'] === TRUE
	|| fail('An install lock without conf.php must resolve to lock-only.');
@unlink($state_lock) || fail('Unable to clear the lock-only install-state fixture.');
file_put_contents($state_config, "<?php\nreturn FALSE;\n", LOCK_EX) !== FALSE
	|| fail('Unable to create the invalid install-state fixture.');
$state = xn_install_state_inspect($state_config, $state_lock);
$state['state'] === 'present-invalid' && $state['config'] === NULL
	|| fail('An unreadable/incomplete conf.php must resolve to present-invalid.');
@unlink($state_config) || fail('Unable to clear the invalid install-state fixture.');
$valid_state_config = array(
	'db'=>array(
		'type'=>'pdo_mysql',
		'pdo_mysql'=>array(
			'master'=>array(
				'host'=>'127.0.0.1',
				'user'=>'fixture',
				'password'=>'',
				'name'=>'xiuno_test',
				'tablepre'=>'bbs_',
				'charset'=>'utf8mb4',
				'engine'=>'innodb',
			),
			'slaves'=>array(),
		),
	),
	'lang'=>'zh-cn',
	'log_path'=>'./log/',
	'tmp_path'=>'./tmp/',
	'upload_path'=>'./upload/',
	'auth_key'=>str_repeat('a', 64),
	'installed'=>1,
);
file_put_contents($state_config, "<?php\nreturn ".var_export($valid_state_config, TRUE).";\n", LOCK_EX) !== FALSE
	|| fail('Unable to create the valid install-state fixture.');
$state = xn_install_state_inspect($state_config, $state_lock);
$state['state'] === 'valid' && $state['config'] === $valid_state_config
	|| fail('A complete published configuration must resolve to valid with an exact config payload.');
@unlink($state_config) || fail('Unable to clear the valid install-state fixture.');
$invalid_state_cases = array(
	'db-not-array'=>array('db'=>1),
	'db-driver-missing'=>array('db'=>array('type'=>'pdo_mysql')),
	'db-master-missing'=>array('db'=>array('type'=>'pdo_mysql', 'pdo_mysql'=>array())),
	'unknown-db-type'=>array('db'=>array('type'=>'unknown', 'unknown'=>array('master'=>array()))),
	'lang-not-string'=>array('lang'=>array('zh-cn')),
	'lang-path-traversal'=>array('lang'=>'../zh-cn'),
	'tmp-path-not-string'=>array('tmp_path'=>array('./tmp/')),
	'empty-log-path'=>array('log_path'=>''),
	'missing-auth-key'=>array('auth_key'=>NULL),
	'non-boolean-installed'=>array('installed'=>'yes'),
);
foreach($invalid_state_cases as $case=>$replace) {
	$invalid_config = array_replace($valid_state_config, $replace);
	if($case === 'missing-auth-key') unset($invalid_config['auth_key']);
	file_put_contents($state_config, "<?php\nreturn ".var_export($invalid_config, TRUE).";\n", LOCK_EX) !== FALSE
		|| fail("Unable to create invalid install-state fixture: $case");
	$state = xn_install_state_inspect($state_config, $state_lock);
	$state['state'] === 'present-invalid' && $state['config'] === NULL
		|| fail("Malformed configuration must fail closed: $case");
	@unlink($state_config) || fail("Unable to clear invalid install-state fixture: $case");
}
$invalid_diagnostic = xn_install_state_diagnostic('present-invalid');
$lock_diagnostic = xn_install_state_diagnostic('lock-only');
strpos($invalid_diagnostic['message'], 'will not overwrite') !== FALSE
	&& strpos($lock_diagnostic['message'], '.installed.lock') !== FALSE
	|| fail('Incomplete install states must provide actionable no-overwrite diagnostics.');

$required_writable = install_required_writable_directories($fixture_root);
array_keys($required_writable) === array('../conf/', '../log/', '../tmp/', '../upload/')
	|| fail('Installer writable requirements must contain only runtime-owned directories.');
foreach($required_writable as $label=>$path) {
	strpos(str_replace('\\', '/', $path), str_replace('\\', '/', $fixture_root).'/') === 0
		|| fail("Installer writable path must be rooted in the supplied application directory: $label");
}

$source_config = $root.'/conf/conf.default.php';
$target_config = $fixture_root.'/conf.php';
$backup_config = install_config_backup_path($target_config);
$replace_config = array(
	'db'=>array('type'=>'pdo_mysql', 'pdo_mysql'=>array('master'=>array('name'=>'fixture'))),
	'auth_key'=>$auth_key,
	'installed'=>1,
	'lang'=>'en-us',
);

$abort_stage = install_config_stage_begin($source_config, $target_config, $replace_config);
!empty($abort_stage['ok']) && is_file($abort_stage['temp']) && !is_file($target_config)
	|| fail('Installer configuration must be fully staged without publishing conf.php.');
install_config_stage_abort($abort_stage)
	&& !file_exists($target_config) && !file_exists($backup_config) && !install_safety_has_stage($target_config)
	|| fail('Explicit installer staging abort must remove only its owned temporary file.');

$short_stage = install_config_stage_begin($source_config, $target_config, $replace_config, array(
	'write'=>function($file, $content) use ($replace_config) {
		$partial_config = "<?php\nreturn ".var_export($replace_config, TRUE).";\n";
		$written = file_put_contents($file, $partial_config, LOCK_EX);
		return $written !== FALSE;
	},
));
empty($short_stage['ok']) && !file_exists($target_config) && !file_exists($backup_config) && !install_safety_has_stage($target_config)
	|| fail('A short or incomplete staged configuration must fail validation and leave no install state.');

$commit_stage = install_config_stage_begin($source_config, $target_config, $replace_config);
!empty($commit_stage['ok']) && install_config_stage_commit($commit_stage)
	|| fail('A validated same-directory installer configuration must publish successfully.');
$persisted_config = install_config_read($target_config);
$expected_persisted_config = array_merge(install_config_read($source_config), $replace_config);
$persisted_config === $expected_persisted_config
	&& !file_exists($backup_config) && !install_safety_has_stage($target_config)
	|| fail('Published conf.php must persist the complete validated default config and exact replacements without residue.');
@unlink($target_config) || fail('Unable to reset the successful installer staging fixture.');

$publish_stage = install_config_stage_begin($source_config, $target_config, $replace_config);
!empty($publish_stage['ok']) || fail('Unable to prepare the publication-failure installer fixture.');
$publish_failed = install_config_stage_commit($publish_stage, array(
	'link'=>function($source, $target) { return FALSE; },
));
$publish_failed === FALSE && !file_exists($target_config) && !file_exists($backup_config) && !install_safety_has_stage($target_config)
	|| fail('A failed final config publication must leave no config, backup, or owned staging file.');

$tampered_stage = install_config_stage_begin($source_config, $target_config, $replace_config);
!empty($tampered_stage['ok']) || fail('Unable to prepare the tampered-staging installer fixture.');
$tampered_content = "<?php\nreturn ".var_export($replace_config, TRUE).";\n";
file_put_contents($tampered_stage['temp'], $tampered_content, LOCK_EX) === strlen($tampered_content)
	|| fail('Unable to alter the tampered-staging installer fixture.');
install_config_stage_commit($tampered_stage) === FALSE
	&& !file_exists($target_config) && !file_exists($backup_config) && !install_safety_has_stage($target_config)
	|| fail('Final config publication must revalidate and reject staging content changed after initial validation.');

$existing_content = "<?php\nreturn array('marker'=>'existing');\n";
install_file_write_exclusive($target_config, $existing_content)
	|| fail('Unable to create the pre-existing installer target fixture.');
$existing_stage = install_config_stage_begin($source_config, $target_config, $replace_config);
empty($existing_stage['ok']) && file_get_contents($target_config) === $existing_content && !install_safety_has_stage($target_config)
	|| fail('Installer staging must refuse and preserve a pre-existing conf.php target.');
@unlink($target_config) || fail('Unable to reset the pre-existing installer target fixture.');

$stale_stage_path = $target_config.'.install-stale.tmp';
install_file_write_exclusive($stale_stage_path, $existing_content)
	|| fail('Unable to create the pre-existing installer staging fixture.');
$stale_stage = install_config_stage_begin($source_config, $target_config, $replace_config);
empty($stale_stage['ok']) && file_get_contents($stale_stage_path) === $existing_content && !file_exists($target_config)
	|| fail('Installer staging must refuse but never delete a temporary file it does not own.');
@unlink($stale_stage_path) || fail('Unable to reset the pre-existing installer staging fixture.');

$race_stage = install_config_stage_begin($source_config, $target_config, $replace_config);
!empty($race_stage['ok']) || fail('Unable to prepare the installer commit-race fixture.');
$race_publish = install_config_stage_commit($race_stage, array(
	'link'=>function($source, $target) use ($existing_content) {
		if(!install_file_write_exclusive($target, $existing_content)) return FALSE;
		return link($source, $target);
	},
));
$race_publish === FALSE
	&& file_get_contents($target_config) === $existing_content && !install_safety_has_stage($target_config)
	|| fail('Atomic no-clobber config publication must preserve a target created inside the publication race window.');
@unlink($target_config) || fail('Unable to reset the installer commit-race fixture.');

$existing_dir = $fixture_root.'/upload-existing';
@mkdir($existing_dir, 0700, TRUE) || fail('Unable to create the existing-directory fixture.');
$existing_mkdir_calls = 0;
install_directory_prepare($existing_dir, 0777, function($dir, $mode, $recursive) use (&$existing_mkdir_calls) {
	$existing_mkdir_calls++;
	return FALSE;
}) && $existing_mkdir_calls === 0
	|| fail('Installer directory preparation must accept an existing directory without recreating it.');
$created_dir = $fixture_root.'/upload-created';
install_directory_prepare($created_dir, 0777, function($dir, $mode, $recursive) {
	return mkdir($dir, $mode, $recursive);
}) && is_dir($created_dir)
	|| fail('Installer directory preparation must verify a newly created directory on disk.');
$failed_dir = $fixture_root.'/upload-failed';
install_directory_prepare($failed_dir, 0777, function($dir, $mode, $recursive) { return FALSE; }) === FALSE
	&& !is_dir($failed_dir)
	|| fail('Installer directory preparation must fail when creation leaves the directory absent.');

$shutdown_before_target = $fixture_root.'/shutdown-before/conf.php';
@mkdir(dirname($shutdown_before_target), 0700, TRUE) || fail('Unable to create the shutdown-before fixture directory.');
$shutdown_before_script = $fixture_root.'/shutdown-before.php';
$shutdown_before_code = "<?php\n"
	.'require_once '.var_export($root.'/install/install.func.php', TRUE).";\n"
	.'$stage = install_config_stage_begin('.var_export($source_config, TRUE).', '.var_export($shutdown_before_target, TRUE).', '.var_export($replace_config, TRUE).');'."\n"
	."if(empty(\$stage['ok'])) exit(2);\n"
	."exit(0);\n";
file_put_contents($shutdown_before_script, $shutdown_before_code, LOCK_EX) === strlen($shutdown_before_code)
	|| fail('Unable to write the shutdown-before installer fixture.');
$shutdown_output = array();
$shutdown_exit = 0;
exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($shutdown_before_script).' 2>&1', $shutdown_output, $shutdown_exit);
$shutdown_exit === 0 && !file_exists($shutdown_before_target)
	&& !file_exists(install_config_backup_path($shutdown_before_target)) && !install_safety_has_stage($shutdown_before_target)
	|| fail('Process shutdown before final rename must remove its owned installer staging file.');

$shutdown_after_target = $fixture_root.'/shutdown-after/conf.php';
@mkdir(dirname($shutdown_after_target), 0700, TRUE) || fail('Unable to create the shutdown-after fixture directory.');
$shutdown_after_script = $fixture_root.'/shutdown-after.php';
$shutdown_after_code = "<?php\n"
	.'require_once '.var_export($root.'/install/install.func.php', TRUE).";\n"
	.'$stage = install_config_stage_begin('.var_export($source_config, TRUE).', '.var_export($shutdown_after_target, TRUE).', '.var_export($replace_config, TRUE).');'."\n"
	."if(empty(\$stage['ok'])) exit(2);\n"
	."if(!install_config_stage_commit(\$stage)) exit(3);\n"
	."exit(0);\n";
file_put_contents($shutdown_after_script, $shutdown_after_code, LOCK_EX) === strlen($shutdown_after_code)
	|| fail('Unable to write the shutdown-after installer fixture.');
$shutdown_output = array();
$shutdown_exit = 0;
exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($shutdown_after_script).' 2>&1', $shutdown_output, $shutdown_exit);
$shutdown_exit === 0 && install_config_matches(install_config_read($shutdown_after_target), $replace_config)
	&& !file_exists(install_config_backup_path($shutdown_after_target)) && !install_safety_has_stage($shutdown_after_target)
	|| fail('Process shutdown after final rename must preserve the published config and remove no committed state.');

class InstallSafetyReconnectPdo extends PDO {
	public function __construct() {}
}

class InstallSafetyReconnectDb {
	public $wlink = NULL;
	public $tablepre = 'bbs_';
	public $connect_master_calls = 0;

	public function connect_master() {
		$this->connect_master_calls++;
		$this->wlink = new InstallSafetyReconnectPdo();
		return $this->wlink;
	}
}

$reconnect_db = new InstallSafetyReconnectDb();
$reconnect_result = install_db_advisory_lock_start($reconnect_db);
empty($reconnect_result['ok'])
	&& $reconnect_db->connect_master_calls === 1
	&& $reconnect_db->wlink instanceof PDO
	|| fail('Installer safety lock must reconnect through the DB object when a missing database was just created.');

$old_post = $_POST;
$old_cookie = $_COOKIE;
$_POST = array();
$_COOKIE = array('lang'=>'en-us');
install_language_resolve('zh-cn') === 'en-us'
	|| fail('Installer locale resolution must honor a validated language cookie before language files load.');
$_POST = array('lang'=>'ru-ru');
$_COOKIE = array('lang'=>'en-us');
install_language_resolve('zh-cn') === 'ru-ru'
	|| fail('Installer locale resolution must give the submitted language selection precedence over the cookie.');
$_POST = array('lang'=>'../../outside');
$_COOKIE = array();
install_language_resolve('zh-cn') === 'zh-cn'
	|| fail('Installer locale resolution must reject languages outside the whitelist.');
$_POST = $old_post;
$_COOKIE = $old_cookie;

$docker_defaults = install_db_form_defaults('docker');
$traditional_defaults = install_db_form_defaults('traditional');
$docker_defaults === array('host'=>'db', 'name'=>'xiunobbs', 'user'=>'xiuno')
	|| fail('Docker installer profile must match the Compose database host, name, and user.');
$traditional_defaults === array('host'=>'127.0.0.1', 'name'=>'xiunobbs', 'user'=>'root')
	|| fail('Traditional installer defaults must remain suitable for a local database without exposing a password.');
strpos($install_db_view, 'type="password" name="password"') !== FALSE
	&& strpos($install_db_view, 'name="password" class="form-control" value="root"') === FALSE
	|| fail('Installer database passwords must use a masked empty input instead of a visible hard-coded credential.');
strpos($compose, 'XIUNO_INSTALL_PROFILE: docker') !== FALSE
	|| fail('Docker Compose must identify its non-secret installer profile.');

$database_writes = 0;
$lock_ok = function($db) { return array('ok'=>TRUE, 'error'=>''); };
$ddl = function($sqlfile) use (&$database_writes) { $database_writes++; return array('ok'=>TRUE, 'error'=>''); };
$lock_failure = install_database_prepare(NULL, 'fixture.sql', array(
	'lock_start'=>function($db) { return array('ok'=>FALSE, 'error'=>'lock failed'); },
	'probe'=>function($db) { fail('Database probe ran after the install lock failed.'); },
	'ddl'=>$ddl,
));
empty($lock_failure['ok']) && $database_writes === 0
	|| fail('An advisory-lock failure must fail closed with zero schema writes.');
$existing = install_database_prepare(NULL, 'fixture.sql', array(
	'lock_start'=>$lock_ok,
	'probe'=>function($db) { return array('ok'=>TRUE, 'found'=>array('bbs_user'), 'error'=>''); },
	'ddl'=>$ddl,
));
empty($existing['ok']) && $database_writes === 0
	|| fail('Existing Xiuno tables must stop installation before install_sql_file or any schema write runs.');
$probe_failure = install_database_prepare(NULL, 'fixture.sql', array(
	'lock_start'=>$lock_ok,
	'probe'=>function($db) { return array('ok'=>FALSE, 'found'=>array(), 'error'=>'probe failed'); },
	'ddl'=>$ddl,
));
empty($probe_failure['ok']) && $database_writes === 0
	|| fail('A target-database inspection failure must fail closed with zero schema writes.');
$malformed_probe = install_database_prepare(NULL, 'fixture.sql', array(
	'lock_start'=>$lock_ok,
	'probe'=>function($db) { return array('ok'=>TRUE, 'error'=>''); },
	'ddl'=>$ddl,
));
empty($malformed_probe['ok']) && $database_writes === 0
	|| fail('A malformed target-database inspection result must fail closed with zero schema writes.');
$empty = install_database_prepare(NULL, 'fixture.sql', array(
	'lock_start'=>$lock_ok,
	'probe'=>function($db) { return array('ok'=>TRUE, 'found'=>array(), 'error'=>''); },
	'ddl'=>$ddl,
));
!empty($empty['ok']) && $database_writes === 1
	|| fail('An empty target database must reach the schema runner exactly once.');
$failed_ddl_calls = 0;
$failed_ddl = install_database_prepare(NULL, 'fixture.sql', array(
	'lock_start'=>$lock_ok,
	'probe'=>function($db) { return array('ok'=>TRUE, 'found'=>array(), 'error'=>''); },
	'ddl'=>function($sqlfile) use (&$failed_ddl_calls) { $failed_ddl_calls++; return array('ok'=>FALSE, 'error'=>'fixture ddl failed'); },
));
empty($failed_ddl['ok']) && $failed_ddl_calls === 1 && $failed_ddl['error'] === 'fixture ddl failed'
	|| fail('A schema runner failure must not be reported as a successful database preparation.');

$sql_read_warnings = array();
set_error_handler(function($severity, $message) use (&$sql_read_warnings) {
	if((error_reporting() & $severity) !== 0) $sql_read_warnings[] = array($severity, $message);
	return TRUE;
});
try {
	$missing_sql_result = install_sql_file($fixture_root.'/missing-install.sql');
} finally {
	restore_error_handler();
}
is_array($missing_sql_result) && empty($missing_sql_result['ok']) && empty($sql_read_warnings)
	|| fail('The real schema runner must return a quiet structured read failure instead of terminating or leaking a PHP warning.');
$schema_fixture = $fixture_root.'/install-runner.sql';
file_put_contents($schema_fixture, "CREATE TABLE fixture_one (id int);\nUSE ignored_database;\nCREATE TABLE fixture_two (id int);\n", LOCK_EX) !== FALSE
	|| fail('Unable to create the structured schema-runner fixture.');
$GLOBALS['install_safety_db_exec_calls'] = array();
$GLOBALS['install_safety_db_exec_fail_at'] = 2;
$errno = 1234;
$errstr = 'fixture failure';
$schema_failure = install_sql_file($schema_fixture);
empty($schema_failure['ok']) && strpos($schema_failure['error'], 'statement #3') !== FALSE
	&& count($GLOBALS['install_safety_db_exec_calls']) === 2
	|| fail('The real schema runner must skip USE, stop on the first DB failure, and return a structured partial-schema diagnostic.');
$GLOBALS['install_safety_db_exec_calls'] = array();
$GLOBALS['install_safety_db_exec_fail_at'] = 0;
$schema_success = install_sql_file($schema_fixture);
!empty($schema_success['ok']) && $schema_success['statements'] === 3
	&& count($GLOBALS['install_safety_db_exec_calls']) === 2
	|| fail('The real schema runner must return structured success after executing all non-USE statements.');

stripos($install_sql, 'DROP TABLE') === FALSE
	|| fail('Fresh-install schema must never drop an existing table, including after a preflight race.');
preg_match_all('/CREATE TABLE\s+`?([A-Za-z0-9_]+)`?/i', $install_sql, $created_matches);
$created_tables = array_values(array_unique($created_matches[1]));
$guarded_tables = install_core_table_names('bbs_');
sort($created_tables, SORT_STRING);
sort($guarded_tables, SORT_STRING);
$created_tables === $guarded_tables
	|| fail('Installer existing-table preflight must cover every core table created by install.sql.');
$last_create_pos = strripos($install_sql, 'CREATE TABLE');
$sentinel_create_sql_pos = strripos($install_sql, 'CREATE TABLE `bbs_table_day`');
($last_create_pos !== FALSE && $sentinel_create_sql_pos === $last_create_pos)
	|| fail('Docker installer sentinel must remain the final core table created by install.sql.');

strpos($install, "function install_lock_start()") !== FALSE
	|| fail('Installer must have an install task lock helper.');
strpos($install, "!xn_lock_start('install_task', 600)") !== FALSE
	|| fail('Installer DB POST flow must use a shared install_task lock.');
strpos($install, "register_shutdown_function('install_lock_end')") !== FALSE
	|| fail('Installer lock must be released by shutdown for direct message()/exit paths.');
strpos($install, "function install_lock_end()") !== FALSE
	|| fail('Installer must have an install lock release helper.');
$install_lock_end = section_between($install, 'function install_lock_end()', 'function install_db_name_safe');
$shutdown_stage_cleanup_pos = strpos($install_lock_end, 'install_config_stage_cleanup();');
$shutdown_db_unlock_pos = strpos($install_lock_end, 'install_db_advisory_lock_end();');
$shutdown_task_unlock_pos = strpos($install_lock_end, "xn_lock_end('install_task');");
($shutdown_stage_cleanup_pos !== FALSE && $shutdown_db_unlock_pos !== FALSE && $shutdown_task_unlock_pos !== FALSE
	&& $shutdown_stage_cleanup_pos < $shutdown_db_unlock_pos && $shutdown_db_unlock_pos < $shutdown_task_unlock_pos)
	|| fail('Installer shutdown must clean owned staging and release the DB advisory lock before its local task lock.');
strpos($install, 'install_session_start();') !== FALSE
	|| fail('Installer must start a session before rendering CSRF-protected forms.');
strpos($install, 'function install_csrf_token()') !== FALSE
	|| fail('Installer must expose a CSRF token helper for POST forms.');
strpos($install, 'function install_csrf_check()') !== FALSE
	|| fail('Installer must verify CSRF tokens on POST branches.');
strpos($install, "hash_equals(\$_SESSION['install_csrf_token'], \$token)") !== FALSE
	|| fail('Installer CSRF check must compare tokens with hash_equals().');
strpos($install, "function install_post(\$key") !== FALSE
	|| fail('Installer must use a POST-only helper for submitted setup fields.');
strpos($install, "\$_lang = install_language_normalize(install_post('lang'), 'zh-cn')") !== FALSE
	|| fail('Installer language POST branch must not read from merged request data.');
strpos($db_post, "'lang'=>\$conf['lang']") !== FALSE
	|| fail('Installer must persist the selected locale into the final conf.php.');
$install_func_pos = strpos($install, "include INSTALL_PATH.'install.func.php';");
$locale_resolve_pos = strpos($install, 'install_language_resolve(');
$language_load_pos = strpos($install, 'bbs_install.php');
($install_func_pos !== FALSE && $locale_resolve_pos !== FALSE && $language_load_pos !== FALSE
	&& $install_func_pos < $locale_resolve_pos && $locale_resolve_pos < $language_load_pos)
	|| fail('Installer locale must be validated before any language file is loaded.');
strpos($install, "include APP_PATH.'model/check.func.php';") !== FALSE
	|| fail('Installer must load the shared user input validators.');

strpos($db_post, "install_db_name_safe(\$name)") !== FALSE
	|| fail('Installer must validate the database name before connecting or creating databases.');
strpos($install, "function install_db_name_safe(\$name)") !== FALSE
	|| fail('Installer database-name validation helper is missing.');
strpos($install, "preg_match('/^[A-Za-z0-9_]{1,64}$/'") !== FALSE
	|| fail('Installer database names must be constrained to a safe identifier whitelist.');
strpos($install, "function install_db_host_port(&\$host, &\$port)") !== FALSE
	|| fail('Installer must validate database host and port before building a PDO DSN.');
strpos($db_post, "!install_db_host_port(\$host, \$port)") !== FALSE
	|| fail('Installer DB POST flow must validate database host and port.');
strpos($db_post, '$db_host = $port == 3306 ? $host : $host . \':\' . $port;') !== FALSE
	|| fail('Installer DB POST flow must preserve non-default ports in saved database configuration.');
strpos($db_post, "\$type !== 'pdo_mysql'") !== FALSE
	|| fail('Installer DB POST flow must reject unsupported database drivers.');
strpos($db_post, "!in_array(\$engine, array('innodb', 'myisam'), TRUE)") !== FALSE
	|| fail('Installer DB POST flow must whitelist database engines.');
foreach (array('type', 'engine', 'host', 'name', 'user', 'password', 'adminemail', 'adminuser', 'adminpass') as $field) {
	strpos($db_post, "param('$field") === FALSE
		|| fail("Installer DB POST flow must not read $field from merged request data.");
	strpos($db_post, "install_post('$field") !== FALSE
		|| fail("Installer DB POST flow must read $field from POST only.");
}
$adminemail_required_pos = strpos($db_post, "empty(\$adminemail) AND message('adminemail', lang('please_input_email'));");
$adminuser_required_pos = strpos($db_post, "empty(\$adminuser) AND message('adminuser', lang('please_input_username'));");
$adminpass_required_pos = strpos($db_post, "empty(\$adminpass) AND message('adminpass', lang('please_input_password'));");
$adminemail_validate_pos = strpos($db_post, "!is_email(\$adminemail, \$err) AND message('adminemail', \$err);");
$adminuser_validate_pos = strpos($db_post, "!is_username(\$adminuser, \$err) AND message('adminuser', \$err);");
$adminpass_validate_pos = strpos($db_post, "!is_password(md5(\$adminpass), \$err) AND message('adminpass', \$err);");
$install_lock_pos = strpos($db_post, "install_lock_start();");
$adminemail_required_pos !== FALSE
	|| fail('Installer must require the initial admin email before setup writes.');
$adminuser_required_pos !== FALSE
	|| fail('Installer must require the initial admin username before setup writes.');
$adminpass_required_pos !== FALSE
	|| fail('Installer must require the initial admin password before setup writes.');
$adminemail_validate_pos !== FALSE
	|| fail('Installer must validate the initial admin email with the shared email validator.');
$adminuser_validate_pos !== FALSE
	|| fail('Installer must validate the initial admin username with the shared username validator.');
$adminpass_validate_pos !== FALSE
	|| fail('Installer must validate the initial admin password with the shared password validator.');
foreach(array($adminemail_required_pos, $adminuser_required_pos, $adminpass_required_pos, $adminemail_validate_pos, $adminuser_validate_pos, $adminpass_validate_pos) as $admin_check_pos) {
	($install_lock_pos !== FALSE && $admin_check_pos < $install_lock_pos)
		|| fail('Installer must validate initial admin credentials before acquiring the install lock and writing setup state.');
}

$create_pos = strpos($db_post, 'CREATE DATABASE `$name` $charset_clause');
$validate_pos = strpos($db_post, 'install_db_name_safe($name)');
($create_pos !== FALSE && $validate_pos !== FALSE && $validate_pos < $create_pos)
	|| fail('Installer must validate database names before CREATE DATABASE uses them.');
strpos($db_post, 'install_db_charset_clause(') !== FALSE
	|| fail('Installer must derive a safe database charset clause before CREATE DATABASE.');
strpos($install, "function install_db_charset_clause(\$charset)") !== FALSE
	|| fail('Installer database charset whitelist helper is missing.');
strpos($install, "CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci") !== FALSE
	|| fail('Installer must support explicit utf8mb4 database creation.');
strpos($install, "CHARACTER SET utf8 COLLATE utf8_general_ci") !== FALSE
	|| fail('Installer must keep explicit utf8 fallback database creation.');

strpos($db_post, "install_lock_start();") !== FALSE
	|| fail('Installer DB POST flow must acquire the install lock.');
$post_state_pos = strpos($db_post, "\$post_install_state = xn_install_state_inspect(APP_PATH.'conf/conf.php', INSTALL_LOCK_FILE);");
$post_state_diagnostic_pos = strpos($db_post, "xn_install_state_diagnostic(\$post_install_state['state'])");
$post_state_503_pos = strpos($db_post, 'http_response_code(503);', $post_state_pos === FALSE ? 0 : $post_state_pos);
$csrf_check_pos = strpos($db_post, 'install_csrf_check();');
($post_state_pos !== FALSE && $post_state_diagnostic_pos !== FALSE && $post_state_503_pos !== FALSE
	&& $csrf_check_pos !== FALSE && $post_state_pos < $csrf_check_pos && $post_state_diagnostic_pos < $csrf_check_pos
	&& $post_state_503_pos < $csrf_check_pos)
	|| fail('Installer DB POST must re-inspect shared install state and fail invalid/lock-only state with a 503 diagnostic before accepting input.');
strpos($db_post, "is_file(APP_PATH.'conf/conf.php')") === FALSE
	|| fail('Installer DB POST must not equate an arbitrary conf.php file with a valid completed installation.');
$secure_key_pos = strpos($db_post, 'install_secure_random_hex(32)');
$password_hash_pos = strpos($db_post, 'password_hash(md5($adminpass), PASSWORD_BCRYPT)');
$requirements_check_pos = strpos($db_post, '$requirements = install_requirements_check();');
$requirements_fail_pos = strpos($db_post, "empty(\$requirements['ok']) AND message(-1, implode(\"\\n\", \$requirements['errors']));");
$stage_pos = strpos($db_post, 'install_config_stage_begin(');
$directory_pos = strpos($db_post, 'install_directory_prepare(');
$create_database_pos = strpos($db_post, 'CREATE DATABASE `$name` $charset_clause');
$prepare_pos = strpos($db_post, "install_database_prepare(\$db, INSTALL_PATH.'install.sql')");
$admin_write_pos = strpos($db_post, "db_update('user', array('uid'=>1), \$update)");
$admin_readback_pos = strpos($db_post, "db_find_one('user', array('uid'=>1))");
$group_write_pos = strpos($db_post, 'group_update($gid, $group_expected)');
$group_readback_pos = strpos($db_post, "db_find_one('group', array('gid'=>\$gid))");
$forum_write_pos = strpos($db_post, 'forum_update(1, $forum_expected)');
$forum_readback_pos = strpos($db_post, "db_find_one('forum', array('fid'=>1))");
$config_commit_pos = strpos($db_post, 'install_config_stage_commit($conf_stage)');
$defense_lock_pos = strpos($db_post, '@file_put_contents(INSTALL_LOCK_FILE');
$db_lock_release_pos = strpos($db_post, 'install_db_advisory_lock_end();');
$task_lock_release_pos = strpos($db_post, 'install_lock_end();');
$success_pos = strpos($db_post, "message(0, jump(lang('conguralation_installed'), '../'));");
foreach(array(
	$requirements_check_pos, $requirements_fail_pos, $secure_key_pos, $password_hash_pos, $stage_pos, $directory_pos, $create_database_pos, $prepare_pos,
	$admin_write_pos, $admin_readback_pos, $group_write_pos, $group_readback_pos,
	$forum_write_pos, $forum_readback_pos, $config_commit_pos, $defense_lock_pos,
	$db_lock_release_pos, $task_lock_release_pos, $success_pos,
) as $required_position) {
	$required_position !== FALSE || fail('Installer completion flow is missing a required staged-commit operation.');
}
($requirements_check_pos < $requirements_fail_pos && $requirements_fail_pos < $secure_key_pos
	&& $requirements_fail_pos < $stage_pos && $requirements_fail_pos < $create_database_pos && $requirements_fail_pos < $prepare_pos
	&& $secure_key_pos < $stage_pos && $password_hash_pos < $stage_pos
	&& $stage_pos < $directory_pos && $directory_pos < $create_database_pos
	&& $create_database_pos < $prepare_pos && $prepare_pos < $admin_write_pos
	&& $admin_write_pos < $admin_readback_pos && $admin_readback_pos < $group_write_pos
	&& $group_write_pos < $group_readback_pos && $group_readback_pos < $forum_write_pos
	&& $forum_write_pos < $forum_readback_pos && $forum_readback_pos < $config_commit_pos
	&& $config_commit_pos < $defense_lock_pos && $defense_lock_pos < $db_lock_release_pos
	&& $db_lock_release_pos < $task_lock_release_pos && $task_lock_release_pos < $success_pos)
	|| fail('Installer must stage local state first, verify directories and DB initialization, publish conf.php last, then release locks in order.');
strpos($db_post, 'install_sql_file(') === FALSE
	|| fail('Installer route must not bypass install_database_prepare with a direct schema call.');
$sql_runner_pos = strpos($install_func, 'function install_sql_file($sqlfile)');
$sql_runner_pos !== FALSE || fail('Installer schema runner is missing.');
$sql_runner = substr($install_func, $sql_runner_pos);
strpos($sql_runner, "return array('ok'=>TRUE") !== FALSE
	&& strpos($sql_runner, "return array('ok'=>FALSE") !== FALSE
	&& strpos($sql_runner, 'message(') === FALSE
	&& preg_match('/\\bexit\\b/', $sql_runner) !== 1
	|| fail('Installer schema runner must return structured results without sending or terminating the route response.');
strpos($install_func, "register_shutdown_function('install_db_advisory_lock_end')") !== FALSE
	|| fail('Installer database advisory lock must have shutdown release coverage.');
strpos($install_func, "'SELECT GET_LOCK(?, 0)'") !== FALSE
	|| fail('Installer must serialize same-database setup through a parameterized advisory lock.');
strpos($install_func, "is_callable(array(\$db, 'connect_master'))") !== FALSE
	|| fail('Installer safety lock must reconnect through the DB object after creating a missing database.');
strpos($db_post, 'copy(') === FALSE && strpos($db_post, 'file_replace_var(') === FALSE
	&& strpos($db_post, 'xn_rand(64)') === FALSE
	|| fail('Installer DB route must not publish config through copy/file replacement or use the legacy PRNG.');
strpos($install_func, "register_shutdown_function('install_config_stage_cleanup')") !== FALSE
	|| fail('Installer staging must have shutdown cleanup for message()/exit paths.');
strpos($install_func, "@fopen(\$file, 'xb')") !== FALSE
	&& strpos($install_func, 'while($offset < $length)') !== FALSE
	&& strpos($install_func, 'install_config_read($temp)') !== FALSE
	|| fail('Installer staging must use an exclusive complete write and validate the temporary PHP config.');
strpos($install_func, "\$publish = isset(\$operations['link']) ? \$operations['link'] : 'link';") !== FALSE
	&& strpos($install_func, "call_user_func(\$publish, \$record['temp'], \$record['target']) !== TRUE") !== FALSE
	&& strpos($install_func, "install_config_read(\$record['temp']) !== \$record['config']") !== FALSE
	|| fail('Final installer config publication must use a filesystem-level atomic no-clobber hard link.');
strpos($install_func, "foreach(array('probe', 'temp') as \$path_key)") !== FALSE
	&& strpos($install_func, 'if(!$ok) return FALSE;') !== FALSE
	|| fail('Installer staging cleanup must attempt every owned name and retain ownership after a deletion failure.');
strpos($install_func, 'if(!@link($temp, $probe) || !@unlink($probe))') !== FALSE
	&& $stage_pos < $create_database_pos
	|| fail('Installer must prove same-directory hard-link publication before any database creation or DDL.');
strpos($db_post, '!install_record_update_verified($admin_write, $admin_record, $admin_expected)') !== FALSE
	&& strpos($db_post, '!install_record_update_verified($group_write, $group_record, $group_expected)') !== FALSE
	&& strpos($db_post, '!install_record_update_verified($forum_write, $forum_record, $forum_expected)') !== FALSE
	|| fail('Installer must verify administrator, group, and forum state after accepting zero-row updates.');
strpos($db_post, '@file_put_contents(INSTALL_LOCK_FILE') !== FALSE
	&& strpos($db_post, '@file_put_contents(INSTALL_LOCK_FILE, date(\'c\')."\\n", LOCK_EX) === FALSE') === FALSE
	|| fail('Post-commit installed lock must remain best-effort defense in depth, not a second core commit.');

strpos($nginx, 'location = /install/index.php') !== FALSE
	|| fail('Docker Nginx must expose the installer entry point for first-time setup.');
strpos($nginx, 'SCRIPT_FILENAME $document_root/install/index.php') !== FALSE
	|| fail('Docker Nginx installer entry point must execute the expected script.');
strpos($nginx, 'location ~ ^/install/') !== FALSE
	|| fail('Docker Nginx must deny direct access to other installer files.');
strpos($nginx, 'location ~ ^/(conf|log|tmp|data|bin|install)/') === FALSE
	|| fail('Docker Nginx sensitive-directory rule must not shadow the exact installer entry point.');
strpos($nginx, 'location ~ ^/(admin/)?view/htm/') !== FALSE
	|| fail('Docker Nginx must not expose PHP template sources as static files.');
strpos($nginx, 'location ~ ^/admin/route/') !== FALSE
	|| fail('Docker Nginx must not execute admin route fragments directly.');
strpos($nginx, 'location ~* ^/upload/.*\.php$') !== FALSE
	|| fail('Docker Nginx must deny PHP execution from the upload directory case-insensitively.');
strpos($nginx, 'location ~* ^/plugin/[^/]+/(install|unstall|upgrade|setting)\.php$') !== FALSE
	|| fail('Docker Nginx must deny direct plugin lifecycle and setting scripts.');
strpos($nginx, 'location ~* ^/plugin/[^/]+/(hook|overwrite)/.*\.php$') !== FALSE
	|| fail('Docker Nginx must deny direct plugin Hook and overwrite PHP fragments.');
strpos($nginx, 'location ~* ^/plugin/.*\.php$') === FALSE
	|| fail('Docker Nginx must not block every plugin PHP endpoint without an explicit public-entry contract.');
strpos($dockerfile, 'oniguruma-dev') !== FALSE
	|| fail('Docker PHP image must install the mbstring build dependency.');
preg_match('/docker-php-ext-install[^\n]*\bmbstring\b/', $dockerfile) === 1
	|| fail('Docker PHP image must install the mbstring extension required by the installer.');
strpos($workflow, 'php bin/run_checks.php --profile=docker --fail-on-skip') !== FALSE
	|| fail('CI must run the manifest-classified Docker Nginx and HTTP profile without accepting SKIP.');
foreach(array(
	'/install/install.func.php',
	'/model/check.func.php',
	'/view/htm/header.inc.htm',
	'/admin/route/update.php',
	'/upload/xiuno-http-smoke.php',
	'/plugin/xiuno-http-smoke/install.php',
	'/plugin/xiuno-http-smoke/unstall.php',
	'/plugin/xiuno-http-smoke/upgrade.php',
	'/plugin/xiuno-http-smoke/setting.php',
	'/plugin/xiuno-http-smoke/hook/probe.php',
	'/plugin/xiuno-http-smoke/overwrite/probe.php',
) as $blocked_path) {
	strpos($docker_smoke, "assert_status '$blocked_path' '404'") !== FALSE
		|| fail("Docker HTTP smoke must verify blocked path: $blocked_path");
}
strpos($docker_smoke, "login_with_password \"\$ADMIN_PASSWORD\"") !== FALSE
	|| fail('Docker HTTP smoke must verify username login with the installed password.');
strpos($docker_smoke, "logout_user\nlogin_with_password \"\$ADMIN_PASSWORD\"") !== FALSE
	|| fail('Docker HTTP smoke must verify username login again after logout.');
strpos($docker_smoke, 'password_new=d41d8cd98f00b204e9800998ecf8427e') !== FALSE
	|| fail('Docker HTTP smoke must verify rejection of the empty-password digest.');
strpos($docker_smoke, "login_with_password \"\$NEW_PASSWORD\"") !== FALSE
	|| fail('Docker HTTP smoke must verify login after a password change.');
strpos($docker_smoke, 'COMPOSE_STARTED=0') !== FALSE
	|| fail('Docker HTTP smoke cleanup must track whether Compose was started.');
strpos($docker_smoke, 'if (( COMPOSE_STARTED == 1 )); then') !== FALSE
	|| fail('Docker HTTP smoke must not stop unrelated containers before starting its own stack.');
strpos($docker_smoke, 'REMOVE_INSTALL_STATE=0') !== FALSE
	|| fail('Docker HTTP smoke cleanup must track whether it owns generated install state.');
strpos($docker_smoke, 'if (( REMOVE_INSTALL_STATE == 1 )); then') !== FALSE
	|| fail('Docker HTTP smoke must not remove an existing local installation.');
strpos($docker_smoke, 'mkdir -p "$ROOT/conf" "$ROOT/log" "$ROOT/tmp" "$ROOT/upload"') !== FALSE
	|| fail('Docker HTTP smoke must recreate required non-plugin runtime directories in a clean checkout.');
strpos($docker_smoke, 'chmod 0777 "$ROOT/conf" "$ROOT/log" "$ROOT/tmp" "$ROOT/upload"') !== FALSE
	|| fail('Docker HTTP smoke must prepare only the required non-plugin runtime directories for container writes.');
strpos($docker_smoke, 'PLUGIN_MANIFEST_BEFORE="$(plugin_manifest)"') !== FALSE
	&& strpos($docker_smoke, 'plugin tree changed during Docker HTTP smoke') !== FALSE
	|| fail('Docker HTTP smoke must verify the read-only plugin tree before and after the run.');
preg_match('~(?:mkdir|chmod|rm\s+-rf)[^\n]*\$ROOT/plugin~', $docker_smoke) === 0
	|| fail('Docker HTTP smoke must never create, chmod, or recursively delete the plugin tree.');
substr_count($compose, './:/var/www/html:ro') === 2
	|| fail('Docker app and web services must mount the source tree read-only.');
foreach(array('conf', 'log', 'tmp', 'upload') as $runtime_dir) {
	strpos($compose, "./$runtime_dir:/var/www/html/$runtime_dir") !== FALSE
		|| fail("Docker app must receive a writable $runtime_dir overlay.");
}
strpos($compose, 'vendor-data:/var/www/html/vendor') !== FALSE
	&& preg_match('/^\s{2}vendor-data:\s*$/m', $compose) === 1
	|| fail('Docker Compose must provide a dedicated writable dependency volume under the read-only source mount.');
strpos($docker_smoke, 'dependency volume is not writable') !== FALSE
	|| fail('Docker HTTP smoke must verify that the isolated Composer dependency volume is writable.');
strpos($docker_smoke, 'export COMPOSE_PROJECT_NAME=') !== FALSE
	&& strpos($compose, '${XIUNO_HTTP_PORT:-8080}:80') !== FALSE
	|| fail('Docker HTTP smoke must isolate its Compose project and expose a configurable host port.');
strpos($docker_smoke, 'mysqladmin ping -h 127.0.0.1') !== FALSE
	|| fail('Docker HTTP smoke must wait for the final MySQL TCP service, not its temporary setup socket.');
strpos($docker_smoke, "mysql -h 127.0.0.1 -u\"\$DB_USER\" \"\$DB_NAME\" -Nse 'SELECT 1'") !== FALSE
	|| fail('Docker HTTP smoke must verify the application database account before Web installation.');
$sentinel_create_pos = strpos($docker_smoke, "CREATE TABLE bbs_table_day (sentinel_id INT NOT NULL PRIMARY KEY, marker VARCHAR(64) NOT NULL)");
$sentinel_reject_pos = strpos($docker_smoke, 'assert_json_not_code "$SENTINEL_RESPONSE" \'0\'');
$sentinel_preserve_pos = strpos($docker_smoke, 'SENTINEL_COLUMNS=');
$sentinel_drop_pos = strpos($docker_smoke, 'DROP TABLE `bbs_table_day`');
$retry_token_pos = strpos($docker_smoke, 'INSTALL_TOKEN="$(fetch_install_token)"', $sentinel_drop_pos === FALSE ? 0 : $sentinel_drop_pos);
($sentinel_create_pos !== FALSE && $sentinel_reject_pos !== FALSE && $sentinel_preserve_pos !== FALSE
	&& $sentinel_drop_pos !== FALSE && $retry_token_pos !== FALSE
	&& $sentinel_create_pos < $sentinel_reject_pos && $sentinel_reject_pos < $sentinel_preserve_pos
	&& $sentinel_preserve_pos < $sentinel_drop_pos && $sentinel_drop_pos < $retry_token_pos)
	|| fail('Docker HTTP smoke must reject a late-created core sentinel without mutation, then drop it and fetch a fresh token before normal installation.');
strpos($docker_smoke, 'assert_no_unpublished_install_state') !== FALSE
	&& strpos($docker_smoke, 'conf.php.install-*.tmp') !== FALSE
	&& strpos($docker_smoke, 'conf/conf.backup.php') !== FALSE
	|| fail('Docker installer rejection and cleanup must cover config, backup, lock, and staging residue.');
strpos($docker_smoke, 'INSTALL_AUTH_KEY=') !== FALSE
	&& strpos($docker_smoke, '^[a-f0-9]{64}$') !== FALSE
	|| fail('Docker installer success must verify the persisted authentication key shape.');

echo "OK: install safety checks passed\n";
