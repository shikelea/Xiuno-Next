<?php

$root = dirname(__DIR__);
$install = file_get_contents($root.'/install/index.php');

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

$db_post = section_between($install, "} elseif(\$action == 'db')", "\n\t}\n}\n\nfunction install_lock_start");

strpos($install, "function install_lock_start()") !== FALSE
	|| fail('Installer must have an install task lock helper.');
strpos($install, "!xn_lock_start('install_task', 600)") !== FALSE
	|| fail('Installer DB POST flow must use a shared install_task lock.');
strpos($install, "register_shutdown_function('install_lock_end')") !== FALSE
	|| fail('Installer lock must be released by shutdown for direct message()/exit paths.');
strpos($install, "function install_lock_end()") !== FALSE
	|| fail('Installer must have an install lock release helper.');

strpos($db_post, "install_db_name_safe(\$name)") !== FALSE
	|| fail('Installer must validate the database name before connecting or creating databases.');
strpos($install, "function install_db_name_safe(\$name)") !== FALSE
	|| fail('Installer database-name validation helper is missing.');
strpos($install, "preg_match('/^[A-Za-z0-9_]{1,64}$/'") !== FALSE
	|| fail('Installer database names must be constrained to a safe identifier whitelist.');

$create_pos = strpos($db_post, 'CREATE DATABASE `$name`');
$validate_pos = strpos($db_post, 'install_db_name_safe($name)');
($create_pos !== FALSE && $validate_pos !== FALSE && $validate_pos < $create_pos)
	|| fail('Installer must validate database names before CREATE DATABASE uses them.');

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

echo "OK: install safety checks passed\n";
