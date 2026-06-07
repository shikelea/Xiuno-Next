<?php

$root = dirname(__DIR__);

defined('DEBUG') || define('DEBUG', 0);
defined('APP_PATH') || define('APP_PATH', $root.'/');
defined('ADMIN_PATH') || define('ADMIN_PATH', $root.'/admin/');
defined('XIUNOPHP_PATH') || define('XIUNOPHP_PATH', $root.'/xiunophp/');

include $root.'/model/plugin.func.php';

function fail($message) {
	fwrite(STDERR, "FAIL: $message\n");
	exit(1);
}

function fixture_plugin($name, $version, $installed, $enable, $dependencies = array()) {
	return array(
		'name'=>$name,
		'version'=>$version,
		'bbs_version'=>'4.0',
		'installed'=>$installed,
		'enable'=>$enable,
		'hooks_rank'=>array(),
		'overwrites_rank'=>array(),
		'dependencies'=>$dependencies,
	);
}

function load_family($notice_version = '1.9', $notice_installed = 1, $notice_enable = 1, $theme_version = '1.0', $theme_installed = 1, $theme_enable = 1, $credits_version = '1.20', $credits_installed = 1, $credits_enable = 1) {
	global $plugins;
	$plugins = array(
		'huux_notice'=>fixture_plugin('Message', $notice_version, $notice_installed, $notice_enable),
		'ax_notice_sx'=>fixture_plugin('Private message', '1.0', 1, 1, array('huux_notice'=>'1.9')),
		'ob_feedback'=>fixture_plugin('Feedback', '2.0.0', 1, 1, array('huux_notice'=>'1.0.0')),
		'till_quick_at'=>fixture_plugin('Mention helper', '1.0.0', 1, 1, array('huux_notice'=>'1.0.0')),
		'abs_theme_stately'=>fixture_plugin('Stately theme', $theme_version, $theme_installed, $theme_enable),
		'abs_themeacp_stately'=>fixture_plugin('Stately admin theme', '1.1.3', 1, 1, array('abs_theme_stately'=>'1.0')),
		'tt_credits'=>fixture_plugin('Credits', $credits_version, $credits_installed, $credits_enable),
		'tt_gift'=>fixture_plugin('Gift', '1.09', 1, 1, array('tt_credits'=>'1.09')),
		'tt_medal'=>fixture_plugin('Medal', '1.3.1', 1, 1, array('tt_credits'=>'1.20', 'huux_notice'=>'1.9')),
		'tt_offer'=>fixture_plugin('Offer', '1.02', 1, 1, array('tt_credits'=>'1.15')),
		'tt_redpacket'=>fixture_plugin('Red packet', '1.03', 1, 1, array('tt_credits'=>'1.16')),
	);
}

function assert_dependency_status($dir, $dependency, $status) {
	$details = plugin_dependency_details($dir);
	if(!isset($details[$dependency])) fail("Missing dependency detail $dir -> $dependency");
	$actual = $details[$dependency]['status'];
	if($actual !== $status) fail("Expected $dir -> $dependency to be $status, got $actual");
}

function assert_no_blockers($dir) {
	$blocked = plugin_dependencies($dir);
	if(!empty($blocked)) fail("$dir should have satisfied dependencies: ".implode(', ', array_keys($blocked)));
}

load_family();
foreach(array('ax_notice_sx', 'ob_feedback', 'till_quick_at', 'abs_themeacp_stately', 'tt_gift', 'tt_medal', 'tt_offer', 'tt_redpacket') as $dir) {
	assert_no_blockers($dir);
}

$reverse = plugin_by_dependencies('huux_notice');
foreach(array('ax_notice_sx', 'ob_feedback', 'till_quick_at', 'tt_medal') as $dir) {
	if(!isset($reverse[$dir])) fail("huux_notice reverse dependency should include $dir");
}
if(isset($reverse['abs_themeacp_stately'])) fail('Plugin reverse dependency list must not mix unrelated theme dependency families.');

$reverse = plugin_by_dependencies('tt_credits');
foreach(array('tt_gift', 'tt_medal', 'tt_offer', 'tt_redpacket') as $dir) {
	if(!isset($reverse[$dir])) fail("tt_credits reverse dependency should include $dir");
}
if(isset($reverse['ax_notice_sx'])) fail('Credits reverse dependency list must not mix unrelated notice dependency families.');

load_family('1.9', 0, 0);
assert_dependency_status('ax_notice_sx', 'huux_notice', 'downloaded_not_installed');

load_family('1.9', 1, 0);
assert_dependency_status('ob_feedback', 'huux_notice', 'installed_disabled');

load_family('1.8', 1, 1);
assert_dependency_status('ax_notice_sx', 'huux_notice', 'version_low');
assert_no_blockers('ob_feedback');

load_family();
unset($plugins['huux_notice']);
assert_dependency_status('till_quick_at', 'huux_notice', 'not_downloaded');

load_family('1.9', 1, 1, '0.9', 1, 1);
assert_dependency_status('abs_themeacp_stately', 'abs_theme_stately', 'version_low');

load_family('1.9', 1, 1, '1.0', 0, 0);
assert_dependency_status('abs_themeacp_stately', 'abs_theme_stately', 'downloaded_not_installed');

load_family('1.9', 1, 1, '1.0', 1, 1, '1.20', 0, 0);
assert_dependency_status('tt_gift', 'tt_credits', 'downloaded_not_installed');

load_family('1.9', 1, 1, '1.0', 1, 1, '1.20', 1, 0);
assert_dependency_status('tt_offer', 'tt_credits', 'installed_disabled');

load_family('1.9', 1, 1, '1.0', 1, 1, '1.15', 1, 1);
assert_dependency_status('tt_redpacket', 'tt_credits', 'version_low');
assert_dependency_status('tt_medal', 'tt_credits', 'version_low');
assert_no_blockers('tt_gift');
assert_no_blockers('tt_offer');

load_family();
unset($plugins['tt_credits']);
assert_dependency_status('tt_redpacket', 'tt_credits', 'not_downloaded');

load_family();
$plugins['till_quick_at']['enable'] = 0;
$reverse = plugin_by_dependencies('huux_notice');
if(isset($reverse['till_quick_at'])) fail('Disabled dependents should not block disabling or uninstalling their dependency.');

load_family();
$plugins['tt_redpacket']['enable'] = 0;
$reverse = plugin_by_dependencies('tt_credits');
if(isset($reverse['tt_redpacket'])) fail('Disabled credits dependents should not block disabling or uninstalling their dependency.');

echo "OK: ecosystem dependency family smoke checks passed\n";
