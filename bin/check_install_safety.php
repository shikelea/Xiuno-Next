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

$install = source_text($root.'/install/index.php');
$nginx = source_text($root.'/docker/nginx/conf.d/default.conf');
$dockerfile = source_text($root.'/docker/php/Dockerfile');
$workflow = source_text($root.'/.github/workflows/ci.yml');
$docker_smoke = source_text($root.'/bin/check_docker_http_smoke.sh');

$db_post = section_between($install, "} elseif(\$action == 'db')", "\n\t}\n}\n\nfunction install_lock_start");

strpos($install, "function install_lock_start()") !== FALSE
	|| fail('Installer must have an install task lock helper.');
strpos($install, "!xn_lock_start('install_task', 600)") !== FALSE
	|| fail('Installer DB POST flow must use a shared install_task lock.');
strpos($install, "register_shutdown_function('install_lock_end')") !== FALSE
	|| fail('Installer lock must be released by shutdown for direct message()/exit paths.');
strpos($install, "function install_lock_end()") !== FALSE
	|| fail('Installer must have an install lock release helper.');
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
strpos($install, "\$_lang = install_post('lang')") !== FALSE
	|| fail('Installer language POST branch must not read from merged request data.');
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
foreach (array('type', 'engine', 'host', 'name', 'user', 'password', 'force', 'adminemail', 'adminuser', 'adminpass') as $field) {
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
strpos($db_post, "copy(APP_PATH.'conf/conf.default.php', APP_PATH.'conf/conf.php') || message") !== FALSE
	|| fail('Installer must stop when conf.default.php cannot be copied to conf.php.');
strpos($db_post, "file_replace_var(APP_PATH.'conf/conf.php', \$replace) === FALSE") !== FALSE
	|| fail('Installer must stop when conf.php replacement fails.');
strpos($db_post, "file_put_contents(INSTALL_LOCK_FILE") !== FALSE
	|| fail('Installer must write the installed lock file after config replacement.');
strpos($db_post, "install_lock_end();\n\t\tmessage(0, jump(lang('conguralation_installed'), '../'));") !== FALSE
	|| fail('Installer must release the lock before reporting success.');

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
strpos($nginx, 'location ~ ^/upload/.*\.php$') !== FALSE
	|| fail('Docker Nginx must deny PHP execution from the upload directory.');
strpos($dockerfile, 'oniguruma-dev') !== FALSE
	|| fail('Docker PHP image must install the mbstring build dependency.');
preg_match('/docker-php-ext-install[^\n]*\bmbstring\b/', $dockerfile) === 1
	|| fail('Docker PHP image must install the mbstring extension required by the installer.');
strpos($workflow, 'bash bin/check_docker_http_smoke.sh') !== FALSE
	|| fail('CI must run the Docker Nginx and HTTP workflow smoke test.');
foreach(array('/install/install.func.php', '/model/check.func.php', '/view/htm/header.inc.htm', '/admin/route/update.php', '/upload/xiuno-http-smoke.php') as $blocked_path) {
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
strpos($docker_smoke, 'mkdir -p "$ROOT/conf" "$ROOT/log" "$ROOT/tmp" "$ROOT/upload" "$ROOT/plugin"') !== FALSE
	|| fail('Docker HTTP smoke must recreate ignored runtime directories in a clean checkout.');

echo "OK: install safety checks passed\n";
