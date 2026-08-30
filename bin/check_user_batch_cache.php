<?php

$root = dirname(__DIR__).'/';

function batch_fail($message) {
	fwrite(STDERR, "FAIL: $message\n");
	exit(1);
}

function batch_hook_tick($name) {
	global $batchHookCounts;
	isset($batchHookCounts[$name]) OR $batchHookCounts[$name] = 0;
	$batchHookCounts[$name]++;
}

function batch_hook_adjust_read(&$uid, &$primary) {
	global $batchHookReadMode;
	if(intval($uid) !== 7) return;
	if($batchHookReadMode === 'uid') $uid = 8;
	if($batchHookReadMode === 'primary') $primary = TRUE;
}

function batch_hook_adjust_cache_result(&$user, $uid) {
	global $batchHookShapeUid;
	if(intval($uid) !== intval($batchHookShapeUid)) return;
	$user = array(
		'uid'=>intval($uid),
		'username'=>'hook-shaped-'.intval($uid),
		'gid'=>9,
		'hook_shape'=>TRUE,
	);
}

function batch_hook_adjust_format_result(&$user) {
	global $batchHookFormatRetargetUid;
	if(!is_array($user) || !isset($user['uid']) || intval($user['uid']) !== intval($batchHookFormatRetargetUid)) return;
	$user['uid'] = intval($user['uid']) + 1;
}

function batch_hook_tamper_cache_boundary(&$uid, &$primary_only) {
	global $batchHookCacheBoundaryTamper;
	if(!$batchHookCacheBoundaryTamper) return;
	$uid = 8;
	$primary_only = FALSE;
}

function group_name($gid) {
	return 'group-'.intval($gid);
}

function lang($key, $vars = array()) {
	return $key;
}

$batchUserRows = array();
for($uid = 1; $uid <= 520; $uid++) {
	$batchUserRows[$uid] = array(
		'uid'=>$uid,
		'gid'=>($uid % 5) + 1,
		'username'=>'member-'.$uid,
		'email'=>'member-'.$uid.'@example.test',
		'password'=>'hash-'.$uid,
		'salt'=>'salt-'.$uid,
		'create_ip'=>2130706433,
		'create_date'=>1700000000 + $uid,
		'login_ip'=>2130706433,
		'login_date'=>1700001000 + $uid,
		'avatar'=>0,
		'threads'=>$uid,
		'posts'=>$uid * 2,
	);
}

$batchFindQueries = 0;
$batchPointQueries = 0;
$batchPrimaryPointQueries = 0;
$batchPrimaryReadFail = FALSE;
$batchFindFail = FALSE;
$batchFindOmitUid = 0;
$batchHookCounts = array();
$batchHookReadMode = '';
$batchHookShapeUid = 0;
$batchHookFormatRetargetUid = 0;
$batchHookCacheBoundaryTamper = FALSE;
$batchUserCache = array();
$batchCacheGets = 0;
$batchCacheSets = 0;

function db_find($table, $condition = array(), $orderby = array(), $page = 1, $pagesize = 10, $key = '', $columns = array()) {
	global $batchUserRows, $batchFindQueries, $batchFindFail, $batchFindOmitUid;
	$table === 'user' OR batch_fail('Batch helper queried an unexpected table.');
	empty($columns) OR batch_fail('Batch helper must fetch complete raw user rows for existing Hooks.');
	$key === 'uid' OR batch_fail('Batch helper must key complete rows by UID.');
	$batchFindQueries++;
	if($batchFindFail) return FALSE;
	$uids = isset($condition['uid']) && is_array($condition['uid']) ? $condition['uid'] : array();
	$rows = array();
	foreach($uids as $uid) {
		$uid = intval($uid);
		if($uid === intval($batchFindOmitUid) || !isset($batchUserRows[$uid])) continue;
		$row = $batchUserRows[$uid];
		$key ? $rows[$row[$key]] = $row : $rows[] = $row;
	}
	return array_slice($rows, 0, intval($pagesize), TRUE);
}

function db_find_one($table, $condition = array(), $orderby = array(), $columns = array()) {
	global $batchUserRows, $batchPointQueries;
	$table === 'user' OR batch_fail('Point helper queried an unexpected table.');
	$batchPointQueries++;
	$uid = isset($condition['uid']) ? intval($condition['uid']) : 0;
	return isset($batchUserRows[$uid]) ? $batchUserRows[$uid] : NULL;
}

function db_find_one_master($table, $condition = array(), $orderby = array(), $columns = array()) {
	global $batchUserRows, $batchPrimaryPointQueries, $batchPrimaryReadFail;
	$table === 'user' OR batch_fail('Primary point helper queried an unexpected table.');
	$batchPrimaryPointQueries++;
	if($batchPrimaryReadFail) return FALSE;
	$uid = isset($condition['uid']) ? intval($condition['uid']) : 0;
	return isset($batchUserRows[$uid]) ? $batchUserRows[$uid] : NULL;
}

function cache_get($key) {
	global $batchUserCache, $batchCacheGets;
	$batchCacheGets++;
	return array_key_exists($key, $batchUserCache) ? $batchUserCache[$key] : NULL;
}

function cache_set($key, $value, $ttl = 0) {
	global $batchUserCache, $batchCacheSets;
	$batchCacheSets++;
	$batchUserCache[$key] = $value;
	return TRUE;
}

function cache_delete($key) {
	global $batchUserCache;
	unset($batchUserCache[$key]);
	return TRUE;
}

$source = file_get_contents($root.'model/user.func.php');
$source !== FALSE OR batch_fail('Unable to read model/user.func.php.');
$hookReplacements = array(
	'// hook model_user__read_start.php'=>"batch_hook_tick('user__read_start'); batch_hook_adjust_read(\$uid, \$primary);",
	'// hook model_user__read_end.php'=>"batch_hook_tick('user__read_end'); if(is_array(\$user)) \$user['hook_user__read_end'] = TRUE;",
	'// hook model_user_read_start.php'=>"batch_hook_tick('user_read_start');",
	'// hook model_user_read_end.php'=>"batch_hook_tick('user_read_end'); if(is_array(\$user)) \$user['hook_user_read_end'] = TRUE;",
	'// hook model_user_read_cache_start.php'=>"batch_hook_tick('user_read_cache_start'); batch_hook_tamper_cache_boundary(\$uid, \$primary_only);",
	'// hook model_user_read_cache_end.php'=>"batch_hook_tick('user_read_cache_end'); batch_hook_adjust_cache_result(\$g_static_users[\$uid], \$uid); batch_hook_tamper_cache_boundary(\$uid, \$primary_only);",
	'// hook model_user_format_start.php'=>"batch_hook_tick('user_format_start');",
	'// hook model_user_format_end.php'=>"batch_hook_tick('user_format_end'); \$user['hook_user_format_end'] = TRUE; batch_hook_adjust_format_result(\$user);",
);
foreach($hookReplacements as $marker=>$replacement) {
	substr_count($source, $marker) === 1 OR batch_fail('Expected exactly one Hook marker: '.$marker);
	$source = str_replace($marker, $replacement, $source);
}

$compiled = tempnam(sys_get_temp_dir(), 'xn_user_batch_');
$compiled !== FALSE OR batch_fail('Unable to allocate the compiled user fixture.');
register_shutdown_function(function() use ($compiled) {
	is_file($compiled) AND @unlink($compiled);
});
file_put_contents($compiled, $source) === strlen($source) OR batch_fail('Unable to write the compiled user fixture.');

$conf = array(
	'cache'=>array('type'=>'mysql'),
	'upload_url'=>'upload/',
	'online_hold_time'=>3600,
);
$time = 10000;
$grouplist = array();
include $compiled;
include $root.'model/session.func.php';

function batch_reset_case() {
	global $conf, $g_static_users, $g_user_cache_primary_only, $g_user_read_prefetch_context;
	global $batchFindQueries, $batchPointQueries, $batchPrimaryPointQueries;
	global $batchPrimaryReadFail, $batchFindFail, $batchFindOmitUid, $batchHookCounts, $batchHookReadMode, $batchHookShapeUid, $batchHookFormatRetargetUid, $batchHookCacheBoundaryTamper;
	global $batchUserCache, $batchCacheGets, $batchCacheSets;
	$conf['cache']['type'] = 'mysql';
	$g_static_users = array();
	$g_user_cache_primary_only = array();
	$g_user_read_prefetch_context = NULL;
	$batchFindQueries = 0;
	$batchPointQueries = 0;
	$batchPrimaryPointQueries = 0;
	$batchPrimaryReadFail = FALSE;
	$batchFindFail = FALSE;
	$batchFindOmitUid = 0;
	$batchHookCounts = array();
	$batchHookReadMode = '';
	$batchHookShapeUid = 0;
	$batchHookFormatRetargetUid = 0;
	$batchHookCacheBoundaryTamper = FALSE;
	$batchUserCache = array();
	$batchCacheGets = 0;
	$batchCacheSets = 0;
}

function batch_read_individually($uids) {
	$users = array();
	foreach($uids as $uid) $users[intval($uid)] = user_read_cache($uid);
	return $users;
}

function batch_assert_hook_count($counts, $expected, $label) {
	$names = array(
		'user__read_start', 'user__read_end', 'user_read_start', 'user_read_end',
		'user_read_cache_start', 'user_read_cache_end', 'user_format_start', 'user_format_end',
	);
	foreach($names as $name) {
		$value = isset($counts[$name]) ? intval($counts[$name]) : 0;
		$value === intval($expected) OR batch_fail($label.' changed '.$name.' Hook count: '.$value.' != '.$expected);
	}
}

// Establish the old per-user path as the behavioral oracle, then prove that one batch query
// produces byte-for-byte equivalent formatted users and executes every existing Hook equally often.
$uids = range(1, 500);
batch_reset_case();
$individual = batch_read_individually($uids);
$individualHookCounts = $batchHookCounts;
$batchPointQueries === 500 OR batch_fail('Individual oracle did not execute 500 point reads.');
$batchFindQueries === 0 OR batch_fail('Individual oracle unexpectedly executed a batch read.');
batch_assert_hook_count($individualHookCounts, 500, 'Individual oracle');

batch_reset_case();
$batched = user_read_cache_batch($uids);
$batched === $individual OR batch_fail('Batch results differ from the existing per-user Hook/format path.');
$batchFindQueries === 1 OR batch_fail('Five hundred users must use one batch read.');
$batchPointQueries === 0 OR batch_fail('Complete batch results unexpectedly fell back to point reads.');
$batchPrimaryPointQueries === 0 OR batch_fail('Display prefetch unexpectedly used the primary user reader.');
$batchHookCounts === $individualHookCounts OR batch_fail('Batch path changed existing Hook invocation counts.');
batch_assert_hook_count($batchHookCounts, 500, 'Batch path');
$g_user_read_prefetch_context === NULL OR batch_fail('Batch prefetch context leaked past the request-local call.');

// Exercise the real online formatter, not only the generic helper, so a future caller regression
// cannot silently restore the 500-query shape while leaving the helper's isolated guard green.
batch_reset_case();
$onlineRows = array();
foreach($uids as $uid) {
	$onlineRows[] = array(
		'sid'=>'online-'.$uid,
		'uid'=>$uid,
		'bigdata'=>0,
		'last_date'=>$time,
		'ip'=>2130706433,
	);
}
$formattedOnline = online_member_snapshot_format_rows($onlineRows);
count($formattedOnline) === 500 OR batch_fail('Online formatter lost display rows.');
$formattedOnline[0]['username'] === 'member-1' && $formattedOnline[499]['username'] === 'member-500'
	OR batch_fail('Online formatter did not use the batched user results.');
$batchFindQueries === 1 && $batchPointQueries === 0
	OR batch_fail('Online formatter did not reduce 500 user reads to one batch query.');
batch_assert_hook_count($batchHookCounts, 500, 'Online formatter batch path');

// A Hook which rewrites the requested UID or reader must make that item use the original point path.
foreach(array('uid', 'primary') as $mode) {
	batch_reset_case();
	$batchHookReadMode = $mode;
	$oracle = batch_read_individually(array(7, 9));
	$oracleCounts = $batchHookCounts;

	batch_reset_case();
	$batchHookReadMode = $mode;
	$result = user_read_cache_batch(array(7, 9));
	$result === $oracle OR batch_fail('Hook '.$mode.' rewrite changed the batch result.');
	$batchFindQueries === 1 OR batch_fail('Hook '.$mode.' rewrite did not retain the bounded batch read.');
	if($mode === 'uid') {
		$batchPointQueries === 1 && $batchPrimaryPointQueries === 0
			OR batch_fail('UID rewrite did not abandon prefetch for the original replica point reader.');
	} else {
		$batchPointQueries === 0 && $batchPrimaryPointQueries === 1
			OR batch_fail('Primary rewrite did not abandon prefetch for the primary point reader.');
	}
	$batchHookCounts === $oracleCounts OR batch_fail('Hook '.$mode.' rewrite changed Hook counts.');
}

// Existing Hooks may replace the final public user shape. The batch path must return it unchanged.
batch_reset_case();
$batchHookShapeUid = 12;
$oracle = batch_read_individually(array(12, 13));
$oracleCounts = $batchHookCounts;
batch_reset_case();
$batchHookShapeUid = 12;
$result = user_read_cache_batch(array(12, 13));
$result === $oracle OR batch_fail('Batch path lost a Hook-defined return shape.');
!empty($result[12]['hook_shape']) && $result[12]['username'] === 'hook-shaped-12'
	OR batch_fail('Hook-defined return shape was not observable.');
$batchHookCounts === $oracleCounts OR batch_fail('Return-shape Hook changed invocation counts under batching.');

// Strict formatted reads may keep additive formatting Hooks, but must reject a Hook that retargets
// the raw primary row to another UID before it can become an authenticated/static identity.
batch_reset_case();
$batchHookFormatRetargetUid = 7;
user_read_primary_proven(7) === FALSE
	&& $batchPrimaryPointQueries === 1
	&& !isset($g_static_users[7])
	OR batch_fail('A formatting Hook retargeted a proven primary row into the original UID cache key.');

// A post-write primary-only retry that cannot read the primary must still execute the historical
// cache-end Hook. Its public fallback shape is returned for compatibility, but neither that shape
// nor the guest base may become a proven request generation; the next call must retry the primary.
batch_reset_case();
$g_user_cache_primary_only[7] = NULL;
$batchPrimaryReadFail = TRUE;
$batchHookShapeUid = 7;
$batchHookCacheBoundaryTamper = TRUE;
$primaryFailureFirst = user_read_cache(7);
!empty($primaryFailureFirst['hook_shape']) && intval($primaryFailureFirst['uid']) === 7
	&& $batchPrimaryPointQueries === 1
	&& !isset($g_static_users[7])
	&& array_key_exists(7, $g_user_cache_primary_only) && $g_user_cache_primary_only[7] === NULL
	OR batch_fail('Primary-only failure skipped the cache-end Hook or cached its fallback as proven state.');
$primaryFailureSecond = user_read_cache(7);
!empty($primaryFailureSecond['hook_shape'])
	&& $batchPrimaryPointQueries === 2
	&& !isset($g_static_users[7])
	&& $g_user_cache_primary_only[7] === NULL
	OR batch_fail('Primary-only failure did not retry the primary after returning the Hook-shaped fallback.');

// Failure and partial-result cases retain the old reads. They must not turn unavailable data into
// guest/empty users merely to preserve the one-query performance shape.
batch_reset_case();
$batchFindFail = TRUE;
$failedBatch = user_read_cache_batch(range(1, 20));
count($failedBatch) === 20 OR batch_fail('Failed batch read lost users instead of falling back.');
$batchFindQueries === 1 && $batchPointQueries === 20
	OR batch_fail('Failed batch read did not fall back to every original point read.');

batch_reset_case();
$batchFindOmitUid = 20;
$partialBatch = user_read_cache_batch(range(1, 20));
isset($partialBatch[20]['username']) && $partialBatch[20]['username'] === 'member-20'
	OR batch_fail('Missing batch row was treated as an empty user.');
$batchFindQueries === 1 && $batchPointQueries === 1
	OR batch_fail('Only the missing batch row should use the original point reader.');

// Nested callers may already own a prefetch context; the helper must restore it exactly.
batch_reset_case();
$sentinel = array('uid'=>519, 'row'=>$batchUserRows[519]);
$g_user_read_prefetch_context = $sentinel;
user_read_cache_batch(array(1, 2));
$g_user_read_prefetch_context === $sentinel OR batch_fail('Batch helper did not restore an outer prefetch context.');

// Non-MySQL user caches keep their established per-key hit/miss behavior; an eager database batch
// would both waste the shared cache and skip its compatibility semantics.
$cachedUser = array('uid'=>1, 'username'=>'cached-member-1', 'gid'=>3, 'cached'=>TRUE);
batch_reset_case();
$conf['cache']['type'] = 'memory';
$batchUserCache['user-1'] = $cachedUser;
$oracle = batch_read_individually(array(1, 2));
$oracleCounts = $batchHookCounts;
$oracleCacheGets = $batchCacheGets;
$oracleCacheSets = $batchCacheSets;
$oraclePointQueries = $batchPointQueries;
batch_reset_case();
$conf['cache']['type'] = 'memory';
$batchUserCache['user-1'] = $cachedUser;
$result = user_read_cache_batch(array(1, 2));
$result === $oracle OR batch_fail('Non-MySQL cache results changed under the batch helper.');
$batchFindQueries === 0 OR batch_fail('Non-MySQL cache path executed an eager database batch.');
$batchPointQueries === $oraclePointQueries && $batchCacheGets === $oracleCacheGets && $batchCacheSets === $oracleCacheSets
	OR batch_fail('Non-MySQL cache hit/miss query shape changed.');
$batchHookCounts === $oracleCounts OR batch_fail('Non-MySQL cache Hook counts changed.');

@unlink($compiled);
echo "OK: user batch cache Hook and query-shape checks passed\n";
