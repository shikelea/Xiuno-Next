<?php

require_once dirname(__DIR__).'/model/html_compat.func.php';

$root = dirname(__DIR__);
$plugin_route = file_get_contents($root.'/admin/route/plugin.php');
$plugin_model = file_get_contents($root.'/model/plugin.func.php');
$misc_model = file_get_contents($root.'/model/misc.func.php');
$plugin_list_view = file_get_contents($root.'/admin/view/htm/plugin_list.htm');
$plugin_read_view = file_get_contents($root.'/admin/view/htm/plugin_read.htm');

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

$model_functions = array(
	'plugin_enable'=>'function plugin_clear_tmp_dir',
	'plugin_disable'=>'function plugin_install_all',
	'plugin_install'=>'function plugin_unstall',
	'plugin_unstall'=>'function plugin_state_snapshot',
);
foreach($model_functions as $function=>$next_function) {
	$section = section_between($plugin_model, "function $function(", $next_function);
	strpos($section, '$old = plugin_state_snapshot($dir);') !== FALSE
		|| fail("$function must snapshot plugin state before mutating globals.");
	strpos($section, '$r = file_replace_var(') !== FALSE
		|| fail("$function must check file_replace_var() result.");
	strpos($section, 'if($r === FALSE)') !== FALSE
		|| fail("$function must hard-fail on config write errors.");
	strpos($section, 'plugin_state_restore($dir, $old);') !== FALSE
		|| fail("$function must restore in-memory state when config write fails.");
	strpos($section, 'if(!plugin_clear_tmp_dir())') !== FALSE
		|| fail("$function must treat runtime-cache invalidation failure as a failed state mutation.");
}

$install_all = section_between($plugin_model, 'function plugin_install_all()', 'function plugin_unstall_all()');
$unstall_all = section_between($plugin_model, 'function plugin_unstall_all()', '/*');
strpos($install_all, 'if(!plugin_install($dir)) return FALSE;') !== FALSE
	&& strpos($install_all, 'return TRUE;') !== FALSE
	|| fail('plugin_install_all() must propagate a child install failure and report success only after every item succeeds.');
strpos($unstall_all, 'if(!plugin_unstall($dir)) return FALSE;') !== FALSE
	&& strpos($unstall_all, 'return TRUE;') !== FALSE
	|| fail('plugin_unstall_all() must propagate a child unstall failure and report success only after every item succeeds.');

strpos($plugin_model, 'function plugin_state_snapshot($dir)') !== FALSE
	|| fail('plugin_state_snapshot() helper is missing.');
strpos($plugin_model, 'function plugin_state_restore($dir, $snapshot)') !== FALSE
	|| fail('plugin_state_restore() helper is missing.');

$route_bootstrap = section_between($plugin_route, '$action = param(1);', "if(\$action == 'local')");
strpos($route_bootstrap, "plugin_init() === TRUE OR message(-1, lang('plugin_state_unavailable'));") !== FALSE
	|| fail('Plugin administration must fail closed when the initial state snapshot cannot be read.');
$state_storage = section_between($plugin_model, 'function plugin_state_storage_writable(', 'function plugin_init');
$dir_contract = section_between($plugin_model, 'function plugin_dir_is_valid(', 'function plugin_url');
$realpath_contract = section_between($plugin_model, 'function plugin_realpath_within(', 'function plugin_php_syntax_errors');
strpos($state_storage, 'plugin_package_conf_path($dir, $error)') !== FALSE
	&& substr_count($state_storage, 'is_writable(') >= 2
	|| fail('Plugin lifecycle capability must use the canonical package boundary and check the exact conf.json file and parent directory.');
defined('APP_PATH') || define('APP_PATH', $root.'/');
require_once $root.'/xiunophp/plugin_identifier.func.php';
try {
	eval($realpath_contract."\n".$dir_contract."\n".$state_storage);
} catch(Throwable $e) {
	fail('Plugin state-storage capability fixture could not load: '.$e->getMessage());
}
plugin_state_storage_writable('../escape') === FALSE
	&& plugin_state_storage_writable('missing_state_'.bin2hex(random_bytes(4))) === FALSE
	|| fail('Plugin state-storage capability must reject traversal and missing state targets without writing package data.');

$state_storage_require = section_between($plugin_route, 'function plugin_require_state_storage_writable(', 'function plugin_require_action_state');
strpos($state_storage_require, 'plugin_state_storage_writable($dir)') !== FALSE
	&& strpos($state_storage_require, "plugin_message(-1, lang('plugin_state_storage_readonly'") !== FALSE
	&& strpos($state_storage_require, 'plugin_require_auto_unstall_storage_writable') !== FALSE
	|| fail('Plugin state mutations must expose one precise read-only storage diagnostic.');
foreach(array($plugin_list_view, $plugin_read_view) as $plugin_view) {
	strpos($plugin_view, 'plugin_state_storage_writable($dir)') !== FALSE
		&& strpos($plugin_view, "lang('plugin_state_storage_readonly_short')") !== FALSE
		|| fail('Plugin administration views must identify immutable state storage before rendering lifecycle controls.');
}

strpos($plugin_route, 'function plugin_require_state_write($ok, $dir, $snapshot = NULL, $package_snapshot = NULL, $extra_state_restore = array())') !== FALSE
	|| fail('plugin_require_state_write() helper is missing.');
strpos($plugin_route, "lang('save_conf_failed'") !== FALSE
	|| fail('Plugin config write failures must use the existing save_conf_failed message.');

$lifecycle = section_between($plugin_route, 'function plugin_run_lifecycle', 'function plugin_dependency_arr_to_links');
strpos($lifecycle, 'plugin_lifecycle_guard_start($dir, $action, $snapshot, $package_snapshot, $extra_state_restore);') !== FALSE
	|| fail('Plugin lifecycle execution must arm a shutdown rollback guard before including third-party code.');
strpos($lifecycle, 'plugin_lifecycle_guard_clear();') !== FALSE
	|| fail('Plugin lifecycle execution must clear the shutdown rollback guard after normal return or catch.');
strpos($lifecycle, 'catch(Throwable $e)') !== FALSE
	|| fail('Plugin lifecycle files must be wrapped in a Throwable catch.');
strpos($lifecycle, 'plugin_compat_include_lifecycle($file, $dir)') !== FALSE
	|| fail('Plugin lifecycle execution must preserve the legacy global include scope.');
$compat_include = section_between($plugin_model, 'function plugin_compat_include_lifecycle(', 'function plugin_compat_form_action_is_local');
strpos($compat_include, 'extract($GLOBALS, EXTR_REFS | EXTR_SKIP);') !== FALSE
	|| fail('Plugin lifecycle include scope must expose legacy global variables by reference.');
strpos($lifecycle, 'plugin_lifecycle_restore_or_fail($dir, $snapshot, $package_snapshot, $extra_state_restore);') !== FALSE
	|| fail('Plugin lifecycle failures must restore state and package snapshots through the checked rollback helper.');
$restore_aggregate = section_between($plugin_route, 'function plugin_lifecycle_restore_collect_failures(', 'function plugin_lifecycle_restore_or_fail(');
$restore_helper = section_between($plugin_route, 'function plugin_lifecycle_restore_or_fail(', 'function plugin_lifecycle_handle_message');
$state_write_helper = section_between($plugin_route, 'function plugin_require_state_write(', 'final class PluginLifecycleMessage');
$extra_restore_helper = section_between($plugin_route, 'function plugin_restore_extra_states(', 'function plugin_dependency_arr_to_links');
$package_restore = section_between($plugin_route, 'function plugin_package_restore(', 'function plugin_package_snapshot_delete');
strpos($restore_aggregate, 'plugin_package_restore($package_snapshot, TRUE, FALSE)') !== FALSE
	|| fail('Plugin lifecycle restoration must use quiet silent package restore so one failed step cannot terminate or double-report the aggregate restore.');
strpos($restore_aggregate, 'if($snapshot !== NULL && !plugin_state_restore($dir, $snapshot))') !== FALSE
	|| fail('Plugin lifecycle rollback must check primary state restoration.');
strpos($restore_aggregate, 'unset($related_state_restore[$dir]);') !== FALSE
	|| fail('Plugin lifecycle restoration must not restore the primary plugin a second time through related states.');
strpos($restore_aggregate, 'if(!plugin_restore_extra_states($related_state_restore))') !== FALSE
	|| fail('Plugin lifecycle rollback must check related state restoration.');
foreach(array('package', 'state', 'extra_states') as $restore_step) {
	strpos($restore_aggregate, "\$failed[] = '$restore_step';") !== FALSE
		|| fail("Plugin lifecycle restoration must collect $restore_step failures.");
}
strpos($restore_helper, 'plugin_lifecycle_restore_collect_failures($dir, $snapshot, $package_snapshot, $extra_state_restore)') !== FALSE
	|| fail('Plugin lifecycle failures must run the complete aggregate restore before reporting.');
strpos($restore_helper, 'Plugin lifecycle state restore failed [') !== FALSE
	|| fail('Plugin lifecycle aggregate restore failures must be reported once with the failed steps.');
strpos($state_write_helper, 'plugin_lifecycle_restore_collect_failures($dir, $snapshot, $package_snapshot, $extra_state_restore)') !== FALSE
	|| fail('State-write failures must use the same checked aggregate restoration contract as lifecycle failures.');
strpos($state_write_helper, 'Plugin lifecycle state restore failed [') !== FALSE
	|| fail('State-write failures must surface restoration failure instead of masking it with the original config-write error.');
strpos($package_restore, "plugin_message(-1, 'Plugin package rollback failed:") !== FALSE
	|| fail('Plugin package restoration must hard-fail instead of returning an unchecked rollback error.');
strpos($lifecycle, 'plugin_message(-1') !== FALSE
	|| fail('Plugin lifecycle failures must release the task lock before exiting.');
strpos($plugin_route, 'function plugin_restore_extra_states($states)') !== FALSE
	|| fail('Related plugin state restore helper is missing.');
strpos($plugin_route, 'function plugin_lifecycle_guard_restore()') !== FALSE
	|| fail('Plugin lifecycle shutdown restore helper is missing.');
$shutdown_restore = section_between($plugin_route, 'function plugin_lifecycle_guard_restore()', 'function plugin_lifecycle_log');
strpos($shutdown_restore, 'plugin_lifecycle_restore_collect_failures(') !== FALSE
	|| fail('Plugin lifecycle shutdown restore must use the same checked aggregate restoration as normal failures.');
strpos($shutdown_restore, 'plugin_lifecycle_log(') !== FALSE
	|| fail('Plugin lifecycle shutdown restore must log restore failures.');
$package_restore_silent = section_between($plugin_route, 'function plugin_package_restore(', 'function plugin_package_snapshot_delete');
strpos($package_restore_silent, 'if($silent) {') !== FALSE && strpos($package_restore_silent, 'return FALSE;') !== FALSE
	|| fail('plugin_package_restore() must support a silent failure mode for shutdown restore.');
$message_function = section_between($misc_model, 'function message($code, $message, $extra = array())', 'function xn_lock_start(');
strpos($message_function, "if(function_exists('plugin_lifecycle_capture_message'))") !== FALSE
	|| fail('message() must detect plugin lifecycle capture support.');
strpos($message_function, 'plugin_lifecycle_capture_message($code, $message, $extra);') !== FALSE
	|| fail('message() must hand code, message, and extra to the lifecycle wrapper.');
$capture_helper = section_between($plugin_route, 'function plugin_lifecycle_capture_message(', 'function plugin_lifecycle_message_is_success');
strpos($capture_helper, 'if(empty($plugin_lifecycle_guard) || !is_array($plugin_lifecycle_guard)) return;') !== FALSE
	|| fail('Lifecycle message capture must be inactive outside a running plugin lifecycle.');
$guard_clear = section_between($plugin_route, 'function plugin_lifecycle_guard_clear()', 'function plugin_lifecycle_guard_restore');
strpos($guard_clear, '$plugin_lifecycle_message_pending = NULL;') !== FALSE
	|| fail('Lifecycle guard cleanup must clear any pending controlled message.');
strpos($plugin_route, 'final class PluginLifecycleMessage extends Error') !== FALSE
	|| fail('Plugin lifecycle controlled message exception is missing.');
strpos($plugin_route, 'catch(PluginLifecycleMessage $e)') !== FALSE
	|| fail('Plugin lifecycle wrapper must distinguish controlled messages from failures.');
strpos($plugin_route, 'function plugin_lifecycle_message_is_deferred($dir, $action, $message)') !== FALSE
	|| fail('Plugin lifecycle wrapper must recognize deferred install wizard forms.');
strpos($plugin_route, 'function plugin_lifecycle_form_action_is_local($action, $base_href = NULL)') !== FALSE
	|| fail('Deferred lifecycle forms must reject external actions.');
strpos($plugin_route, "\$route === 'plugin-'.\$action.'-'.\$dir") !== FALSE
	|| fail('Deferred lifecycle messages must equal the current query-format lifecycle route.');
strpos($plugin_route, "\$route === 'plugin/'.\$action.'/'.\$dir") !== FALSE
	|| fail('Deferred lifecycle messages must equal the current path-format lifecycle route.');
strpos($plugin_route, 'function plugin_lifecycle_pending_message_take()') !== FALSE
	|| fail('Lifecycle messages swallowed by broad plugin catch blocks must remain detectable.');
strpos($plugin_route, 'function plugin_lifecycle_restore_or_fail(') !== FALSE
	|| fail('Lifecycle rollback failures must be surfaced.');
strpos($plugin_route, 'message($e->response_code, $e->response_message, $e->response_extra)') !== FALSE
	|| fail('Controlled lifecycle responses must preserve extra response fields.');

// Behavioral invariant: quoted `>` bytes are valid inside form attributes and must not truncate
// the opening tag. Deferred lifecycle detection shares the compatibility layer's quote-aware tag
// scanner so a legacy multi-step installer remains deferred without weakening same-origin routing.
$compat_form_local = section_between($plugin_model, 'function plugin_compat_form_action_is_local(', 'function plugin_compat_html_tag_boundary');
$compat_tag_boundary = section_between($plugin_model, 'function plugin_compat_html_tag_boundary(', 'function plugin_compat_html_tag_end');
$compat_tag_end = section_between($plugin_model, 'function plugin_compat_html_tag_end(', 'function plugin_compat_html_tag_attribute');
$compat_tag_attribute = section_between($plugin_model, 'function plugin_compat_html_tag_attribute(', 'function plugin_compat_html_base_href');
$compat_base_href = section_between($plugin_model, 'function plugin_compat_html_base_href(', 'function plugin_compat_html_remove_token_inputs');
$lifecycle_form_local = section_between($plugin_route, 'function plugin_lifecycle_form_action_is_local(', 'function plugin_lifecycle_form_action_route');
$lifecycle_form_route = section_between($plugin_route, 'function plugin_lifecycle_form_action_route(', 'function plugin_lifecycle_message_is_deferred');
$lifecycle_deferred = section_between($plugin_route, 'function plugin_lifecycle_message_is_deferred(', 'function plugin_lifecycle_pending_message_take');
strpos($lifecycle_deferred, "preg_match_all('~<form\\\\b[^>]*>~i'") === FALSE
	|| fail('Deferred lifecycle detection must not truncate opening tags with a [^>]* regex.');
strpos($lifecycle_deferred, 'xn_html_attribute_value_decode(trim($form_action))') !== FALSE
	|| fail('Deferred lifecycle detection must use the shared browser-compatible HTML attribute decoder.');
$_SERVER['HTTP_HOST'] = 'forum.test';
try {
	eval($compat_form_local."\n".$compat_tag_boundary."\n".$compat_tag_end."\n".$compat_tag_attribute."\n".$compat_base_href
		."\n".$lifecycle_form_local."\n".$lifecycle_form_route."\n".$lifecycle_deferred);
} catch(Throwable $e) {
	fail('Deferred lifecycle behavior fixture could not load helpers: '.$e->getMessage());
}
$deferred_cases = array(
	'quoted greater-than query route'=>array(
		'<form data-note="1 > 0" method="post" action="plugin-install-demo.htm"><button>next</button></form>',
		'install',
		TRUE,
	),
	'path rewrite route keeps query arguments separate'=>array(
		'<form method="post" action="plugin-install-demo.htm?step=2"><button>next</button></form>',
		'install',
		TRUE,
	),
	'named directory route preserves the full identifier'=>array(
		'<form method="post" action="plugin-install.htm?dir=demo&amp;step=2"><button>next</button></form>',
		'install',
		TRUE,
	),
	'semicolonless decimal entities preserve the current lifecycle route'=>array(
		'<form method="post" action="plugin&#45install&#45demo.htm"><button>next</button></form>',
		'install',
		TRUE,
	),
	'semicolonless hexadecimal entities preserve the current lifecycle route'=>array(
		'<form method="post" action="plugin-install-demo&#x2ehtm"><button>next</button></form>',
		'install',
		TRUE,
	),
	'HTML5 named entities preserve the current lifecycle route'=>array(
		'<form method="post" action="plugin&sol;install&sol;demo.htm"><button>next</button></form>',
		'install',
		TRUE,
	),
	'named directory route for another package is not deferred'=>array(
		'<form method="post" action="plugin-install.htm?dir=other"><button>next</button></form>',
		'install',
		FALSE,
	),
	'path format route keeps query arguments separate'=>array(
		'<form method="post" action="plugin/unstall/demo?step=confirm"><button>next</button></form>',
		'unstall',
		TRUE,
	),
	'query rewrite route through index script'=>array(
		'<form method="post" action="index.php?plugin-upgrade-demo.htm&amp;step=2"><button>next</button></form>',
		'upgrade',
		TRUE,
	),
	'quoted greater-than path route'=>array(
		"<form title='step > one' METHOD='POST' action='/plugin/upgrade/demo.htm'>next</form>",
		'upgrade',
		TRUE,
	),
	'missing action posts to current route'=>array(
		'<form data-note="step > one" method="post">next</form>',
		'install',
		TRUE,
	),
	'external post is not a local deferred step'=>array(
		'<form data-note="1 > 0" method="post" action="https://outside.test/plugin-install-demo.htm">next</form>',
		'install',
		FALSE,
	),
	'nested lifecycle form follows browser fail-closed behavior'=>array(
		'<form method="post" action="https://outside.test/"><form method="post" action="plugin-install-demo.htm">next</form></form>',
		'install',
		FALSE,
	),
	'entity-encoded external post is not a local deferred step'=>array(
		'<form method="post" action="https&colon;&sol;&sol;outside.test/plugin-install-demo.htm">next</form>',
		'install',
		FALSE,
	),
	'external document base makes an explicit relative post external'=>array(
		'<base href="https://outside.test/wizard/"><form method="post" action="plugin-install-demo.htm">next</form>',
		'install',
		FALSE,
	),
	'actionless form still posts to the current document under an external base'=>array(
		'<base href="https://outside.test/wizard/"><form method="post">next</form>',
		'install',
		TRUE,
	),
	'different local route is not the current lifecycle'=>array(
		'<form data-note="1 > 0" method="post" action="plugin-install-other.htm">next</form>',
		'install',
		FALSE,
	),
	'unrelated path must not adopt a lifecycle-looking query segment'=>array(
		'<form method="post" action="save.htm?plugin-install-demo.htm"><button>next</button></form>',
		'install',
		FALSE,
	),
	'get form is not deferred'=>array(
		'<form data-note="1 > 0" method="get" action="plugin-install-demo.htm">next</form>',
		'install',
		FALSE,
	),
	'script form source is not active markup'=>array(
		'<script>var sample = \'<form method="post" action="plugin-install-demo.htm">\';</script>',
		'install',
		FALSE,
	),
	'template form is not an active lifecycle step'=>array(
		'<template><form method="post" action="plugin-install-demo.htm">next</form></template>',
		'install',
		FALSE,
	),
);
foreach($deferred_cases as $label=>$case) {
	$actual = plugin_lifecycle_message_is_deferred('demo', $case[1], $case[0]);
	$actual === $case[2]
		|| fail('Deferred lifecycle form case failed: '.$label.' expected '.var_export($case[2], TRUE).', got '.var_export($actual, TRUE).'.');
}

// Behavioral invariant: even if package restoration fails, primary and related state restores
// must still run before exactly one user-facing aggregate failure is emitted.
$GLOBALS['plugin_restore_guard_trace'] = array();
$GLOBALS['plugin_restore_guard_messages'] = array();
$GLOBALS['plugin_restore_guard_logs'] = array();
function plugin_package_restore($snapshot, $silent = FALSE, $log_silent_failure = TRUE) {
	$GLOBALS['plugin_restore_guard_trace'][] = $silent
		? ($log_silent_failure ? 'package:silent:logged' : 'package:silent:quiet')
		: 'package:interactive';
	if(!$silent) throw new RuntimeException('Interactive package restore terminated aggregate restoration.');
	if($log_silent_failure) plugin_lifecycle_log('individual package restore failure');
	return FALSE;
}
function plugin_state_restore($dir, $snapshot) {
	$GLOBALS['plugin_restore_guard_trace'][] = 'state:'.$dir;
	return FALSE;
}
function plugin_message($code, $message, $extra = array()) {
	$GLOBALS['plugin_restore_guard_trace'][] = 'message';
	$GLOBALS['plugin_restore_guard_messages'][] = array('code'=>$code, 'message'=>$message, 'extra'=>$extra);
}
function plugin_lifecycle_log($message) {
	$GLOBALS['plugin_restore_guard_trace'][] = 'log';
	$GLOBALS['plugin_restore_guard_logs'][] = $message;
}
function array_value($array, $key, $default = NULL) {
	return isset($array[$key]) ? $array[$key] : $default;
}
try {
	eval($restore_aggregate."\n".$restore_helper."\n".$state_write_helper."\n".$extra_restore_helper);
	$restore_result = plugin_lifecycle_restore_or_fail(
		'aggregate_demo',
		array('installed'=>1, 'enable'=>1),
		array('dir'=>'aggregate_demo'),
		array(
			'aggregate_demo'=>array('installed'=>1, 'enable'=>1),
			'related_demo'=>array('installed'=>1, 'enable'=>1),
		)
	);
} catch(Throwable $e) {
	fail('Plugin lifecycle aggregate restoration must not short-circuit: '.$e->getMessage());
}
$expected_restore_trace = array('package:silent:quiet', 'state:aggregate_demo', 'state:related_demo', 'message');
$GLOBALS['plugin_restore_guard_trace'] === $expected_restore_trace
	|| fail('Plugin lifecycle aggregate restoration must attempt package, primary state once, and every distinct related state before reporting once.');
$restore_result === TRUE
	|| fail('Plugin lifecycle aggregate restoration must retain the existing successful-return contract after reporting.');
count($GLOBALS['plugin_restore_guard_messages']) === 1
	|| fail('Plugin lifecycle aggregate restoration must emit exactly one user-facing failure.');
$restore_message = $GLOBALS['plugin_restore_guard_messages'][0];
$restore_message['code'] === -1
	&& strpos($restore_message['message'], '[package,state,extra_states]') !== FALSE
	|| fail('Plugin lifecycle aggregate restoration must identify every failed restore step in its single failure report.');
empty($GLOBALS['plugin_restore_guard_logs'])
	|| fail('Normal aggregate restoration must suppress individual package logs before its single user-facing report.');

// The generic state-write boundary must not emit the original save_conf_failed response after a
// package/state/related-state restoration step has failed. That aggregate recovery failure is the
// actionable error and must remain the only response even when a test message stub returns.
$GLOBALS['plugin_restore_guard_trace'] = array();
$GLOBALS['plugin_restore_guard_messages'] = array();
$state_write_result = plugin_require_state_write(
	FALSE,
	'aggregate_demo',
	array('installed'=>1, 'enable'=>1),
	array('dir'=>'aggregate_demo'),
	array(
		'aggregate_demo'=>array('installed'=>1, 'enable'=>1),
		'related_demo'=>array('installed'=>1, 'enable'=>1),
	)
);
$state_write_result === FALSE
	|| fail('State-write failure must remain FALSE when aggregate restoration also fails.');
$GLOBALS['plugin_restore_guard_trace'] === $expected_restore_trace
	|| fail('State-write failure must attempt package, primary state once, related states, then one aggregate response.');
count($GLOBALS['plugin_restore_guard_messages']) === 1
	&& strpos($GLOBALS['plugin_restore_guard_messages'][0]['message'], '[package,state,extra_states]') !== FALSE
	|| fail('State-write restoration failure was masked by the original config-write response.');

// Without a primary snapshot, a matching directory in extra states is not a duplicate and must
// remain recoverable alongside every other related state.
$GLOBALS['plugin_restore_guard_trace'] = array();
$GLOBALS['plugin_restore_guard_messages'] = array();
try {
	plugin_lifecycle_restore_or_fail(
		'aggregate_demo',
		NULL,
		NULL,
		array(
			'aggregate_demo'=>array('installed'=>1, 'enable'=>1),
			'related_demo'=>array('installed'=>1, 'enable'=>1),
		)
	);
} catch(Throwable $e) {
	fail('Plugin lifecycle extra-only restoration must not short-circuit: '.$e->getMessage());
}
$expected_extra_only_trace = array('state:aggregate_demo', 'state:related_demo', 'message');
$GLOBALS['plugin_restore_guard_trace'] === $expected_extra_only_trace
	|| fail('Plugin lifecycle restoration without a primary snapshot must preserve the matching directory in extra states.');
count($GLOBALS['plugin_restore_guard_messages']) === 1
	&& strpos($GLOBALS['plugin_restore_guard_messages'][0]['message'], '[extra_states]') !== FALSE
	|| fail('Plugin lifecycle extra-only restoration must report its aggregate related-state failure once.');

// Shutdown uses the same collector but owns one aggregate log instead of a user-facing message.
$GLOBALS['plugin_restore_guard_trace'] = array();
$GLOBALS['plugin_restore_guard_logs'] = array();
$GLOBALS['plugin_lifecycle_guard'] = array(
	'dir'=>'aggregate_demo',
	'action'=>'upgrade',
	'snapshot'=>array('installed'=>1, 'enable'=>1),
	'package_snapshot'=>array('dir'=>'aggregate_demo'),
	'extra_state_restore'=>array(
		'aggregate_demo'=>array('installed'=>1, 'enable'=>1),
		'related_demo'=>array('installed'=>1, 'enable'=>1),
	),
);
try {
	eval($shutdown_restore);
	plugin_lifecycle_guard_restore();
} catch(Throwable $e) {
	fail('Plugin lifecycle shutdown aggregate restoration must not short-circuit: '.$e->getMessage());
}
$expected_shutdown_trace = array('package:silent:quiet', 'state:aggregate_demo', 'state:related_demo', 'log');
$GLOBALS['plugin_restore_guard_trace'] === $expected_shutdown_trace
	|| fail('Plugin lifecycle shutdown restoration must attempt each distinct state once and emit exactly one aggregate log.');
count($GLOBALS['plugin_restore_guard_logs']) === 1
	&& strpos($GLOBALS['plugin_restore_guard_logs'][0], '[package,state,extra_states]') !== FALSE
	|| fail('Plugin lifecycle shutdown restoration must log every failed step exactly once.');
$GLOBALS['plugin_lifecycle_guard'] === NULL
	|| fail('Plugin lifecycle shutdown restoration must disarm the guard before attempting recovery.');

$exclusive_group = section_between($plugin_model, 'function plugin_exclusive_group_normalize(', 'function plugin_init');
strpos($exclusive_group, "preg_match('~^[a-z0-9][a-z0-9._-]{0,63}$~D'") !== FALSE
	|| fail('Explicit replacement groups must use one portable, bounded lowercase identifier contract.');
$plugin_read = section_between($plugin_model, 'function plugin_read_by_dir(', 'function plugin_siteid');
strpos($plugin_read, '$exclusive_group_invalid =') !== FALSE
	&& strpos($plugin_read, "if(\$exclusive_group_invalid) \$local['metadata_error'] = 1;") !== FALSE
	&& strpos($plugin_read, "\$local['exclusive_group'] = plugin_exclusive_group_normalize(") !== FALSE
	&& strpos($plugin_read, "\$plugin['exclusive_group'] = \$local['exclusive_group'];") !== FALSE
	|| fail('Replacement authority must be normalized from local conf.json, reject invalid non-empty declarations, and never come from official metadata.');

$auto_plan = section_between($plugin_route, 'function plugin_auto_unstall_plan(', 'function plugin_require_auto_unstall_contract');
strpos($auto_plan, "\$plan['exclusive_group'] !== '' && \$_group === \$plan['exclusive_group']") !== FALSE
	&& strpos($auto_plan, "\$plan['candidates'][] = \$_dir;") !== FALSE
	|| fail('Aggregate replacement candidates must require the same valid explicit group on both packages.');
$auto_contract = section_between($plugin_route, 'function plugin_require_auto_unstall_contract(', 'function plugin_auto_unstall_candidates');
strpos($plugin_route, 'plugin_legacy_auto_unstall_match') === FALSE
	&& strpos($auto_plan, 'legacy_candidates') === FALSE
	&& strpos($auto_contract, "return \$plan['candidates'];") !== FALSE
	|| fail('Directory names must remain opaque; only explicit groups may influence replacement planning.');

$auto_unstall = section_between($plugin_route, 'function plugin_auto_unstall_same_type(', 'function plugin_check_auto_unstall_result');
strpos($auto_unstall, 'if(!plugin_state_storage_writable($_dir))') !== FALSE
	&& strpos($auto_unstall, 'plugin_lifecycle_restore_or_fail($dir, NULL, NULL, $restore_states);') !== FALSE
	&& strpos($auto_unstall, "lang('plugin_state_storage_readonly'") !== FALSE
	|| fail('Execution-time replacement storage loss must restore the whole touched batch before reporting the precise target.');
strpos($auto_unstall, 'plugin_require_state_write(FALSE, $_dir, $snapshot, NULL, $restore_states);') !== FALSE
	|| fail('Same-type replacement state-write failure must restore primary and related snapshots through the unified checked boundary.');
strpos($auto_unstall, "plugin_run_lifecycle(\$_dir, 'unstall', \$snapshot, NULL, \$restore_states);") !== FALSE
	|| fail('Same-type auto-uninstall must execute the related lifecycle under the aggregate restore boundary.');
strpos($auto_unstall, 'if(is_array($lifecycle_message))') === FALSE
	|| fail('Same-type auto-uninstall must not roll back a completed non-deferred message(0) lifecycle.');
$auto_unstall_result = section_between($plugin_route, 'function plugin_check_auto_unstall_result(', 'function plugin_check_dependency');
strpos($auto_unstall_result, 'plugin_lifecycle_restore_or_fail($dir, NULL, NULL, $restore_states);') !== FALSE
	|| fail('Same-type dependency revalidation failure must not ignore related-state restoration results.');

$setting_branch = section_between($plugin_route, "} elseif(\$action == 'setting')", 'function plugin_require_state_storage_writable');
strpos($setting_branch, "\$gid != 1 AND message(-1, lang('insufficient_privilege'));") !== FALSE
	|| fail('Plugin settings must allow the super administrator and reject other groups.');
strpos($setting_branch, "empty(\$plugins[\$dir]['installed']) AND message(-1, lang('plugin_not_installed'));") !== FALSE
	|| fail('Plugin settings must reject direct access to an uninstalled package.');
strpos($setting_branch, "allowadminpanel") === FALSE
	|| fail('Plugin settings must not depend on the nonexistent allowadminpanel group field.');

foreach(array('install'=>'unstall', 'unstall'=>'enable', 'upgrade'=>'setting') as $action=>$next) {
	$branch = section_between($plugin_route, "} elseif(\$action == '$action')", "} elseif(\$action == '$next')");
	if($action == 'upgrade' && strpos($branch, 'plugin_official_remote_closed();') !== FALSE) {
		continue;
	}
	strpos($branch, '$plugin_snapshot = plugin_state_snapshot($dir);') !== FALSE
		|| fail("Plugin $action action must snapshot state before lifecycle work.");
	strpos($branch, 'plugin_require_state_storage_writable($dir);') !== FALSE
		|| fail("Plugin $action action must preflight its exact conf.json storage before mutation.");
	strpos($branch, 'plugin_require_state_write(') !== FALSE
		|| fail("Plugin $action action must hard-fail on config write errors.");
	(
		strpos($branch, "plugin_run_lifecycle(\$dir, '$action', \$plugin_snapshot);") !== FALSE ||
		strpos($branch, "plugin_run_lifecycle(\$dir, '$action', \$plugin_snapshot, \$package_snapshot);") !== FALSE
	)
		|| fail("Plugin $action action must run lifecycle files through the rollback wrapper.");
}

foreach(array('enable'=>'disable', 'disable'=>'upgrade') as $action=>$next) {
	$branch = section_between($plugin_route, "} elseif(\$action == '$action')", "} elseif(\$action == '$next')");
	strpos($branch, 'plugin_require_state_storage_writable($dir);') !== FALSE
		|| fail("Plugin $action action must preflight its exact conf.json storage before mutation.");
	strpos($branch, 'plugin_require_state_write(') !== FALSE
		|| fail("Plugin $action action must hard-fail on config write errors.");
	strpos($branch, '$plugin_snapshot = plugin_state_snapshot($dir);') !== FALSE
		|| fail("Plugin $action action must explicitly snapshot the final locked state before mutation.");
	strpos($branch, "plugin_require_state_write(plugin_$action(\$dir), \$dir, \$plugin_snapshot);") !== FALSE
		|| fail("Plugin $action action must pass its explicit snapshot to the unified state-write failure boundary.");
}

$install_branch = section_between($plugin_route, "} elseif(\$action == 'install')", "} elseif(\$action == 'unstall')");
strpos($install_branch, '$replacement_dirs = plugin_require_auto_unstall_contract($dir);') !== FALSE
	&& strpos($install_branch, 'plugin_check_auto_unstall_dependencies($dir, $replacement_dirs);') !== FALSE
	&& strpos($install_branch, 'plugin_auto_unstall_same_type($dir, $plugin_snapshot, $replacement_dirs);') !== FALSE
	|| fail('Plugin install must freeze and reuse one preflighted explicit replacement set.');
$install_contract_pos = strpos($install_branch, '$replacement_dirs = plugin_require_auto_unstall_contract($dir);');
$install_state_write_pos = strpos($install_branch, 'plugin_require_state_write(plugin_install($dir)');
strpos($install_branch, "\$lifecycle_message = plugin_run_lifecycle(\$dir, 'install', \$plugin_snapshot);") !== FALSE
	|| fail('Plugin install must retain controlled lifecycle success messages until finalization completes.');
strpos($install_branch, 'if(is_array($lifecycle_message))') !== FALSE
	|| fail('Plugin install must only treat structured lifecycle messages as response payloads.');
strpos($install_branch, "message(\$lifecycle_message['code'], \$lifecycle_message['message'], \$lifecycle_message['extra']);") !== FALSE
	|| fail('Plugin install must emit controlled lifecycle messages after finalization.');
$install_lifecycle_pos = strpos($install_branch, "\$lifecycle_message = plugin_run_lifecycle(");
$install_finalize_pos = strpos($install_branch, 'plugin_auto_unstall_same_type(');
$install_schema_persist_pos = strpos($install_branch, "plugin_lifecycle_persist_setting_schema(\$dir, 'install')");
$install_unbind_pos = strpos($install_branch, 'plugin_setting_schema_unbind_plugin($replaced_dir)');
$install_unlock_pos = strpos($install_branch, 'plugin_lock_end();');
$install_response_pos = strpos($install_branch, 'if(is_array($lifecycle_message))');
($install_contract_pos !== FALSE && $install_state_write_pos !== FALSE && $install_contract_pos < $install_state_write_pos
	&& $install_lifecycle_pos !== FALSE && $install_contract_pos < $install_lifecycle_pos
	&& $install_finalize_pos !== FALSE && $install_schema_persist_pos !== FALSE
	&& $install_unbind_pos !== FALSE && $install_lifecycle_pos < $install_finalize_pos
	&& $install_finalize_pos < $install_unbind_pos && $install_unbind_pos < $install_schema_persist_pos
	&& $install_schema_persist_pos < $install_unlock_pos && $install_unlock_pos < $install_response_pos)
	|| fail('Plugin install must finalize replacement, detach old schema owners, persist the new owner, then unlock before emitting a controlled success message.');

$run_lifecycle = section_between($plugin_route, 'function plugin_run_lifecycle(', 'function plugin_lifecycle_persist_setting_schema(');
strpos($run_lifecycle, 'plugin_lifecycle_persist_setting_schema(') === FALSE
	|| fail('Lifecycle script success is not the outer action commit point and must not persist compatibility settings itself.');

$unstall_branch = section_between($plugin_route, "} elseif(\$action == 'unstall')", "} elseif(\$action == 'enable')");
strpos($unstall_branch, "\$lifecycle_message = plugin_run_lifecycle(\$dir, 'unstall', \$plugin_snapshot);") !== FALSE
	|| fail('Plugin unstall must retain controlled lifecycle success messages until finalization completes.');
strpos($unstall_branch, 'if(is_array($lifecycle_message))') !== FALSE
	|| fail('Plugin unstall must only treat structured lifecycle messages as response payloads.');
strpos($unstall_branch, "message(\$lifecycle_message['code'], \$lifecycle_message['message'], \$lifecycle_message['extra']);") !== FALSE
	|| fail('Plugin unstall must emit controlled lifecycle messages after finalization.');
$unstall_lifecycle_pos = strpos($unstall_branch, "\$lifecycle_message = plugin_run_lifecycle(");
$unstall_unbind_pos = strpos($unstall_branch, 'plugin_setting_schema_unbind_plugin($dir)');
$unstall_unlock_pos = strpos($unstall_branch, 'plugin_lock_end();');
$unstall_response_pos = strpos($unstall_branch, 'if(is_array($lifecycle_message))');
($unstall_lifecycle_pos !== FALSE && $unstall_unbind_pos !== FALSE
	&& $unstall_lifecycle_pos < $unstall_unbind_pos && $unstall_unbind_pos < $unstall_unlock_pos
	&& $unstall_unlock_pos < $unstall_response_pos)
	|| fail('Plugin unstall must detach compatibility schema ownership before unlocking and emitting a controlled success message.');

echo "OK: plugin lifecycle safety checks passed\n";
