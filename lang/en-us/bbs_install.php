<?php

return array(
	'installed_tips' => 'You have been installed, and if you need to re install, please delete the conf/conf.php!',
	'please_set_conf_file_writable' => 'Please set the conf/conf.php file to write!',
	'evn_not_support_php_mysql' => 'The current PHP environment does not support mysql and pdo_mysql driver, can not continue to install.',
	'dbhost_is_empty' => 'Database host cannot be empty',
	'dbname_is_empty' => 'Database name cannot be empty',
	'dbuser_is_empty' => 'User name cannot be empty',
	'adminuser_is_empty' => 'Administrator user name can not be empty',
	'adminpass_is_empty' => 'Administrator password can not be empty',
	'conguralation_installed' => 'Congratulations, installation success, please remove install directory for security.',
	
	'step_1_title' => '1. Environmental Check',
	'runtime_env_check' => 'Runtime environment detection',
	'required' => 'Required',
	'current' => 'Current',
	'check_result' => 'Check Result',
	'passed' => 'Passed',
	'not_passed' => 'Not Passed',
	'not_the_best' => 'Not the ideal environment',
	'dir_writable_check' => 'Directory / file permissions',
	'writable' => 'Writable',
	'unwritable' => 'Unwritable',
	'check_again' => 'Check Again',
	'os' => 'OS',
	'unix_like' => 'UNIX Like',
	'php_version' => 'PHP Version',
	
	'step_2_title' => '2. Database settings',
	'db_type' => 'Database type',
	'db_engine' => 'Database Engine',
	'db_host' => 'Database Host',
	'db_name' => 'Database Name',
	'db_user' => 'Database User',
	'db_pass' => 'Database Password',
	'step_3_title' => '3. Administrator information',
	'admin_email' => 'Administrator Email',
	'admin_username' => 'Administrator Username',
	'admin_pw' => 'Administrator Password',
	'installing_about_moment' => 'Installing, it takes about a minute or so',
	'license_title' => 'Xiuno Next License Agreement',
	'license_content' => 'Thank you for choosing Xiuno Next, a lightweight, fast, and modern PHP forum engine developed based on Xiuno BBS 4.0.

Xiuno Next inherits the high-performance DNA of Xiuno BBS, integrates the modern PHP ecosystem (Composer, PSR standards), and fully upgrades the mobile experience.

Xiuno Next is released under the MIT license, you can freely modify, distribute, and use it commercially without worrying about any legal risks (the original copyright information should be retained after modification).',
	'license_date' => 'Release date: Mar 4, 2026',
	'agree_license_to_continue' => 'Agree to continue to install the agreement',
	'install_title' => 'Xiuno Next Installation wizard',
	'install_guide' => 'Installation Wizard',

	
	'function_check' => 'Function dependency check',
	'supported' => 'Supported',
	'not_supported' => 'Not Supported',
	'function_glob_not_exists' => 'Plugin install dependent on it, please setting php.ini, set disabled_functions = ; Lifting restrictions on this function',
	'function_gzcompress_not_exists' => 'Plugin install dependent on it, on Linux server, add compile argument: --with-zlib, on Windows Server, please setting php.ini open extension=php_zlib.dll',
	'function_mb_substr_not_exists' => 'System dependent on it, on Linux server, add compile argument: --with-mbstring, on Windows Server, please setting php.ini open extension=php_mbstring.dll',
	
	// hook lang_en_us_bbs_install.php
);

?>