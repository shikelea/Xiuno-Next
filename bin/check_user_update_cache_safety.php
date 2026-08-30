<?php

$root = dirname(__DIR__).'/';

function user_update_cache_fail($message) {
	fwrite(STDERR, "FAIL: $message\n");
	exit(1);
}

function user_update_cache_assert($condition, $message) {
	$condition OR user_update_cache_fail($message);
}

$userUpdateRows = array();
$userUpdateReplicaRows = array();
$userUpdateDbResult = 1;
$userUpdateDbCalls = array();
$userUpdateAfterDbCall = NULL;
$userUpdatePointReads = 0;
$userUpdatePrimaryReadFailure = FALSE;
$userUpdateSharedCache = array();
$userUpdateCacheGets = array();
$userUpdateCacheGetFailure = FALSE;
$userUpdateCacheDeletes = array();
$userUpdateCacheSets = array();
$userUpdateCacheDeleteResult = TRUE;
$userUpdateCacheSetResult = TRUE;
$userUpdateLogs = array();

function user_update_cache_run_php_child($source, &$output) {
	$output = '';
	$descriptors = array(
		0=>array('pipe', 'r'),
		1=>array('pipe', 'w'),
		2=>array('redirect', 1),
	);
	$pipes = array();
	$process = @proc_open(array(PHP_BINARY), $descriptors, $pipes, __DIR__, NULL, array('bypass_shell'=>TRUE));
	if(!is_resource($process)) return 127;
	fwrite($pipes[0], $source);
	fclose($pipes[0]);
	$output = stream_get_contents($pipes[1]);
	fclose($pipes[1]);
	return proc_close($process);
}

function db_update($table, $condition, $update, $db = NULL) {
	global $userUpdateRows, $userUpdateDbResult, $userUpdateDbCalls, $userUpdateAfterDbCall;
	$table === 'user' OR user_update_cache_fail('user_update() wrote an unexpected table.');
	$userUpdateDbCalls[] = array($condition, $update);
	$result = $userUpdateDbResult;
	if(is_callable($userUpdateAfterDbCall)) {
		$callback = $userUpdateAfterDbCall;
		$userUpdateAfterDbCall = NULL;
		$callback($condition, $update, $result);
	}
	if($result !== 1) return $result;
	$uid = isset($condition['uid']) ? intval($condition['uid']) : 0;
	isset($userUpdateRows[$uid]) OR user_update_cache_fail('Successful update fixture targeted a missing user.');
	foreach($update as $key=>$value) {
		$operator = substr($key, -1);
		if($operator === '+' || $operator === '-') {
			$field = substr($key, 0, -1);
			$current = isset($userUpdateRows[$uid][$field]) ? intval($userUpdateRows[$uid][$field]) : 0;
			$userUpdateRows[$uid][$field] = $operator === '+' ? $current + intval($value) : $current - intval($value);
		} else {
			$userUpdateRows[$uid][$key] = $value;
		}
	}
	return $result;
}

function db_find_one($table, $condition = array(), $orderby = array(), $columns = array()) {
	global $userUpdateReplicaRows, $userUpdatePointReads;
	$table === 'user' OR user_update_cache_fail('User cache reread queried an unexpected table.');
	$userUpdatePointReads++;
	$uid = isset($condition['uid']) ? intval($condition['uid']) : 0;
	return isset($userUpdateReplicaRows[$uid]) ? $userUpdateReplicaRows[$uid] : NULL;
}

function db_find_one_master($table, $condition = array(), $orderby = array(), $columns = array()) {
	global $userUpdateRows, $userUpdatePrimaryReadFailure, $userUpdatePointReads;
	$table === 'user' OR user_update_cache_fail('Primary user cache reread queried an unexpected table.');
	$userUpdatePointReads++;
	if($userUpdatePrimaryReadFailure) {
		return FALSE;
	}
	$uid = isset($condition['uid']) ? intval($condition['uid']) : 0;
	return isset($userUpdateRows[$uid]) ? $userUpdateRows[$uid] : NULL;
}

function cache_get($key) {
	global $userUpdateSharedCache, $userUpdateCacheGets, $userUpdateCacheGetFailure;
	$userUpdateCacheGets[] = $key;
	if($userUpdateCacheGetFailure) return FALSE;
	return array_key_exists($key, $userUpdateSharedCache) ? $userUpdateSharedCache[$key] : NULL;
}

function cache_get_primary($key) {
	return cache_get($key);
}

function cache_set($key, $value, $ttl = 0) {
	global $userUpdateSharedCache, $userUpdateCacheSets, $userUpdateCacheSetResult;
	$userUpdateCacheSets[] = array($key, $value, $ttl);
	if($userUpdateCacheSetResult === FALSE) return FALSE;
	$userUpdateSharedCache[$key] = $value;
	return TRUE;
}

function cache_delete($key) {
	global $userUpdateSharedCache, $userUpdateCacheDeletes, $userUpdateCacheDeleteResult;
	$userUpdateCacheDeletes[] = $key;
	$result = is_array($userUpdateCacheDeleteResult)
		? (empty($userUpdateCacheDeleteResult) ? FALSE : array_shift($userUpdateCacheDeleteResult))
		: $userUpdateCacheDeleteResult;
	if($result === FALSE) return FALSE;
	unset($userUpdateSharedCache[$key]);
	return TRUE;
}

function xn_log($message, $channel = 'error') {
	global $userUpdateLogs;
	$userUpdateLogs[] = array($message, $channel);
	return TRUE;
}

function group_name($gid) {
	return 'group-'.intval($gid);
}

function lang($key, $vars = array()) {
	return $key;
}

function user_update_cache_raw_user($uid, $gid = 100, $logins = 4) {
	return array(
		'uid'=>intval($uid),
		'gid'=>intval($gid),
		'username'=>'member-'.intval($uid),
		'email'=>'member-'.intval($uid).'@example.test',
		'password'=>'hash',
		'salt'=>'salt',
		'create_ip'=>2130706433,
		'create_date'=>1700000000,
		'login_ip'=>2130706433,
		'login_date'=>1700001000,
		'avatar'=>0,
		'threads'=>10,
		'posts'=>20,
		'logins'=>intval($logins),
	);
}

function user_update_cache_reset($result, $cache_type = 'memory') {
	global $conf, $g_static_users, $g_user_cache_primary_only, $grouplist;
	global $userUpdateRows, $userUpdateReplicaRows, $userUpdateDbResult, $userUpdateDbCalls, $userUpdateAfterDbCall;
	global $userUpdatePointReads, $userUpdatePrimaryReadFailure;
	global $userUpdateSharedCache, $userUpdateCacheGets, $userUpdateCacheGetFailure, $userUpdateCacheDeletes, $userUpdateCacheSets;
	global $userUpdateCacheDeleteResult, $userUpdateCacheSetResult, $userUpdateLogs;
	$conf = array('cache'=>array('type'=>$cache_type), 'upload_url'=>'upload/');
	$grouplist = array();
	$userUpdateRows = array(7=>user_update_cache_raw_user(7));
	$userUpdateReplicaRows = $userUpdateRows;
	$userUpdateDbResult = $result;
	$userUpdateDbCalls = array();
	$userUpdateAfterDbCall = NULL;
	$userUpdatePointReads = 0;
	$userUpdatePrimaryReadFailure = FALSE;
	$userUpdateCacheGets = array();
	$userUpdateCacheGetFailure = FALSE;
	$userUpdateCacheDeletes = array();
	$userUpdateCacheSets = array();
	$userUpdateCacheDeleteResult = TRUE;
	$userUpdateCacheSetResult = TRUE;
	$userUpdateLogs = array();
	$cached = $userUpdateRows[7];
	$cached['groupname'] = 'group-'.$cached['gid'];
	$g_static_users = array(7=>$cached);
	$g_user_cache_primary_only = array();
	$userUpdateSharedCache = array('user-7'=>$cached);
	return $cached;
}

$userModelSource = file_get_contents($root.'model/user.func.php');
is_string($userModelSource) OR user_update_cache_fail('Unable to read the user model for compiled Hook coverage.');
strpos($userModelSource, 'return cache_get_primary($key) === NULL;') !== FALSE
	OR user_update_cache_fail('Absent-key invalidation proof must use the cache primary endpoint.');
$userUpdateStart = strpos($userModelSource, 'function user__update(');
$userUpdateEnd = $userUpdateStart === FALSE ? FALSE : strpos($userModelSource, 'function user__read(', $userUpdateStart);
($userUpdateStart !== FALSE && $userUpdateEnd !== FALSE) OR user_update_cache_fail('Unable to isolate user__update() for compiled Hook coverage.');
$compiledUserUpdateBase = substr($userModelSource, $userUpdateStart, $userUpdateEnd - $userUpdateStart);
$compiledUserUpdate = $compiledUserUpdateBase;
$hookMarker = '// hook model_user__update_end.php';
strpos($compiledUserUpdate, $hookMarker) !== FALSE OR user_update_cache_fail('Unable to locate the user__update end Hook marker.');
$compiledUserUpdate = str_replace(
	$hookMarker,
	$hookMarker."\n\t\t".'$r = \'legacy-hook-return\';'."\n\t\t".'$db_result = \'hook-mutated-result\';'."\n\t\t".'$raw_db_result = 0;',
	$compiledUserUpdate
);
$compiledHookChild = "<?php\n"
	."function db_update(\$table, \$condition, \$update, \$db = NULL) { return 1; }\n"
	.$compiledUserUpdate
	."\n\$db_result = NULL;\n"
	."\$return = user__update(7, array('gid'=>101), NULL, \$db_result);\n"
	."echo json_encode(array('return'=>\$return, 'db_result'=>\$db_result));\n";
$compiledHookExit = user_update_cache_run_php_child($compiledHookChild, $compiledHookOutput);
$compiledHookResult = json_decode(trim($compiledHookOutput), TRUE);
$compiledHookExit === 0 && is_array($compiledHookResult)
	&& $compiledHookResult['return'] === 'legacy-hook-return'
	&& $compiledHookResult['db_result'] === 1
	OR user_update_cache_fail('A compiled legacy end Hook changed the preserved raw database result: '.$compiledHookOutput);

// A start Hook may retarget the legacy write. Cache reconciliation must follow the uid that was
// actually placed in the database condition, not the outer caller's stale pre-Hook argument.
$userUpdateWrapperStart = strpos($userModelSource, 'function user_update(');
$userUpdateWrapperEnd = $userUpdateWrapperStart === FALSE ? FALSE : strpos($userModelSource, 'function user_read(', $userUpdateWrapperStart);
($userUpdateWrapperStart !== FALSE && $userUpdateWrapperEnd !== FALSE)
	OR user_update_cache_fail('Unable to isolate user_update() for committed target coverage.');
$compiledUserUpdateWrapper = substr($userModelSource, $userUpdateWrapperStart, $userUpdateWrapperEnd - $userUpdateWrapperStart);
$startHookMarker = '// hook model_user__update_start.php';
strpos($compiledUserUpdate, $startHookMarker) !== FALSE
	OR user_update_cache_fail('Unable to locate the user__update start Hook marker.');
$compiledRetargetedUserUpdate = str_replace(
	$startHookMarker,
	$startHookMarker."\n\t".'$uid = 8;',
	$compiledUserUpdate
);
$compiledTargetChild = "<?php\n"
	."\$target_conditions = array();\n"
	."\$reconciled_uids = array();\n"
	."function db_update(\$table, \$condition, \$update, \$db = NULL) { \$GLOBALS['target_conditions'][] = \$condition; return 1; }\n"
	."function user_cache_reconcile_after_write(\$uid, \$canonical = NULL, \$canonical_known = FALSE) { \$GLOBALS['reconciled_uids'][] = \$uid; return TRUE; }\n"
	.$compiledRetargetedUserUpdate
	."\n".$compiledUserUpdateWrapper
	."\n\$db_result = NULL; \$committed_uid = NULL;\n"
	."\$return = user_update(7, array('gid'=>101), NULL, \$db_result, \$committed_uid);\n"
	."echo json_encode(array('return'=>\$return, 'db_result'=>\$db_result, 'committed_uid'=>\$committed_uid, 'condition_uid'=>\$target_conditions[0]['uid'], 'reconciled'=>\$reconciled_uids));\n";
$compiledTargetExit = user_update_cache_run_php_child($compiledTargetChild, $compiledTargetOutput);
$compiledTargetResult = json_decode(trim($compiledTargetOutput), TRUE);
$compiledTargetExit === 0 && is_array($compiledTargetResult)
	&& $compiledTargetResult['db_result'] === 1
	&& $compiledTargetResult['committed_uid'] === 8
	&& $compiledTargetResult['condition_uid'] === 8
	&& $compiledTargetResult['reconciled'] === array(8)
	OR user_update_cache_fail('Cache reconciliation did not follow the Hook-retargeted committed uid: '.$compiledTargetOutput);

// The public wrapper has its own legacy end Hook. Preserve its $r override, while keeping result and
// committed UID evidence LIFO-safe across nested and throwing wrapper Hooks.
$wrapperEndHook = '// hook model_user_update_end.php';
strpos($compiledUserUpdateWrapper, $wrapperEndHook) !== FALSE
	OR user_update_cache_fail('Unable to locate the user_update wrapper end Hook marker.');
$compiledNestedWrapper = str_replace(
	$wrapperEndHook,
	$wrapperEndHook."\n\t\tif(intval(\$uid) === 7) { \$nested_result = NULL; \$nested_uid = NULL; user_update(8, array(), NULL, \$nested_result, \$nested_uid); \$GLOBALS['nested_wrapper_evidence'] = array(\$nested_result, \$nested_uid); }\n\t\tif(intval(\$uid) === 9) throw new RuntimeException('wrapper evidence fixture failure');\n\t\t\$r = 'legacy-wrapper-return'; \$db_result = 'wrapper-mutated-result'; \$committed_uid = 999;",
	$compiledUserUpdateWrapper
);
$compiledWrapperChild = "<?php\n"
	."\$nested_wrapper_evidence = NULL;\n"
	."function db_update(\$table, \$condition, \$update, \$db = NULL) { \$uid = intval(\$condition['uid']); return \$uid === 8 ? 0 : (\$uid === 9 ? FALSE : 1); }\n"
	."function user_cache_reconcile_after_write(\$uid, \$canonical = NULL, \$canonical_known = FALSE) { return TRUE; }\n"
	.$compiledUserUpdateBase
	."\n".$compiledNestedWrapper
	."\n\$outer_result = NULL; \$outer_uid = NULL; \$outer_return = user_update(7, array(), NULL, \$outer_result, \$outer_uid);\n"
	."\$throw_caught = FALSE; try { \$throw_result = NULL; \$throw_uid = NULL; user_update(9, array(), NULL, \$throw_result, \$throw_uid); } catch(RuntimeException \$e) { \$throw_caught = TRUE; }\n"
	."\$after_result = NULL; \$after_uid = NULL; \$after_return = user_update(10, array(), NULL, \$after_result, \$after_uid);\n"
	."echo json_encode(array('outer_return'=>\$outer_return, 'outer'=>array(\$outer_result, \$outer_uid), 'nested'=>\$nested_wrapper_evidence, 'throw_caught'=>\$throw_caught, 'after_return'=>\$after_return, 'after'=>array(\$after_result, \$after_uid)));\n";
$compiledWrapperExit = user_update_cache_run_php_child($compiledWrapperChild, $compiledWrapperOutput);
$compiledWrapperResult = json_decode(trim($compiledWrapperOutput), TRUE);
$compiledWrapperExit === 0 && is_array($compiledWrapperResult)
	&& $compiledWrapperResult['outer_return'] === 'legacy-wrapper-return'
	&& $compiledWrapperResult['outer'] === array(1, 7)
	&& $compiledWrapperResult['nested'] === array(0, 8)
	&& $compiledWrapperResult['throw_caught'] === TRUE
	&& $compiledWrapperResult['after_return'] === 'legacy-wrapper-return'
	&& $compiledWrapperResult['after'] === array(1, 10)
	OR user_update_cache_fail('Wrapper result/UID evidence was mutable, non-reentrant or leaked after an exception: '.$compiledWrapperOutput);

// Credential commits may keep the legacy retargetable Hook surface, but a one-row result for a
// different UID must not authorize or revoke state for the originally locked user.
$passwordCommitStart = strpos($userModelSource, 'function user_password_commit_locked(');
$passwordCommitEnd = $passwordCommitStart === FALSE ? FALSE : strpos($userModelSource, 'function user_password_commit(', $passwordCommitStart);
($passwordCommitStart !== FALSE && $passwordCommitEnd !== FALSE)
	OR user_update_cache_fail('Unable to isolate user_password_commit_locked() for committed-target coverage.');
$compiledPasswordCommit = substr($userModelSource, $passwordCommitStart, $passwordCommitEnd - $passwordCommitStart);
$compiledPasswordTargetChild = "<?php\n"
	."\$conf = array('cache'=>array('type'=>'mysql')); \$g_static_users = array(); \$credential_targets = array(); \$revoke_calls = array();\n"
	."function db_update(\$table, \$condition, \$update, \$db = NULL) { \$GLOBALS['credential_targets'][] = intval(\$condition['uid']); return 1; }\n"
	."function user_cache_reconcile_after_write(\$uid, \$canonical = NULL, \$canonical_known = FALSE) { return TRUE; }\n"
	."function user__read_primary_proven(\$uid) { return array('uid'=>intval(\$uid), 'auth_epoch'=>0); }\n"
	."function user_auth_epoch(\$user) { return intval(\$user['auth_epoch']); }\n"
	."function user_reset_grant_revoke_locked(\$uid) { \$GLOBALS['revoke_calls'][] = intval(\$uid); return TRUE; }\n"
	."function cache_delete(\$key) { return TRUE; } function xn_log(\$message, \$channel = '') { return TRUE; }\n"
	.$compiledRetargetedUserUpdate
	."\n".$compiledUserUpdateWrapper
	."\n".$compiledPasswordCommit
	."\n\$result = user_password_commit_locked(7, 'new-password-hash');\n"
	."echo json_encode(array('result'=>\$result, 'targets'=>\$credential_targets, 'revokes'=>\$revoke_calls));\n";
$compiledPasswordTargetExit = user_update_cache_run_php_child($compiledPasswordTargetChild, $compiledPasswordTargetOutput);
$compiledPasswordTargetResult = json_decode(trim($compiledPasswordTargetOutput), TRUE);
$compiledPasswordTargetExit === 0 && is_array($compiledPasswordTargetResult)
	&& $compiledPasswordTargetResult['result'] === FALSE
	&& $compiledPasswordTargetResult['targets'] === array(8)
	&& $compiledPasswordTargetResult['revokes'] === array()
	OR user_update_cache_fail('A credential Hook retarget authorized or revoked the originally locked UID: '.$compiledPasswordTargetOutput);

// Structured evidence must remain LIFO across nested legacy Hooks, and finally must pop a throwing
// frame so the following update cannot inherit either its result or committed uid.
$nestedHookSource = <<<'PHP'
// hook model_user__update_end.php
		if($uid === 7) {
			$nested_result = NULL;
			$nested_uid = NULL;
			user__update(8, array('gid'=>102), NULL, $nested_result, $nested_uid);
			$GLOBALS['nested_evidence'] = array($nested_result, $nested_uid);
		}
		if($uid === 9) throw new RuntimeException('nested evidence fixture failure');
PHP;
$compiledNestedUserUpdate = str_replace($hookMarker, $nestedHookSource, $compiledUserUpdateBase);
$compiledNestedChild = "<?php\n"
	."function db_update(\$table, \$condition, \$update, \$db = NULL) { \$uid = intval(\$condition['uid']); return \$uid === 8 ? 0 : (\$uid === 9 ? FALSE : 1); }\n"
	.$compiledNestedUserUpdate
	."\n\$outer_result = NULL; \$outer_uid = NULL; user__update(7, array(), NULL, \$outer_result, \$outer_uid);\n"
	."\$throw_caught = FALSE; try { \$throw_result = NULL; \$throw_uid = NULL; user__update(9, array(), NULL, \$throw_result, \$throw_uid); } catch(RuntimeException \$e) { \$throw_caught = TRUE; }\n"
	."\$after_result = NULL; \$after_uid = NULL; user__update(10, array(), NULL, \$after_result, \$after_uid);\n"
	."echo json_encode(array('outer'=>array(\$outer_result, \$outer_uid), 'nested'=>\$nested_evidence, 'throw_caught'=>\$throw_caught, 'after'=>array(\$after_result, \$after_uid)));\n";
$compiledNestedExit = user_update_cache_run_php_child($compiledNestedChild, $compiledNestedOutput);
$compiledNestedResult = json_decode(trim($compiledNestedOutput), TRUE);
$compiledNestedExit === 0 && is_array($compiledNestedResult)
	&& $compiledNestedResult['outer'] === array(1, 7)
	&& $compiledNestedResult['nested'] === array(0, 8)
	&& $compiledNestedResult['throw_caught'] === TRUE
	&& $compiledNestedResult['after'] === array(1, 10)
	OR user_update_cache_fail('Committed result/uid evidence was not re-entrant or was leaked by an exception: '.$compiledNestedOutput);

// Read Hooks retain their public return mutations, while strict callers consume a separate LIFO
// proof of the actual UID, endpoint and raw database row. UID retargeting and primary downgrade are
// both rejected, and a throwing nested Hook must not leak evidence into the next call.
$userReadStart = strpos($userModelSource, 'function user__read(');
$userReadEnd = $userReadStart === FALSE ? FALSE : strpos($userModelSource, 'function user__delete(', $userReadStart);
($userReadStart !== FALSE && $userReadEnd !== FALSE)
	OR user_update_cache_fail('Unable to isolate user__read() for primary evidence coverage.');
$compiledUserRead = substr($userModelSource, $userReadStart, $userReadEnd - $userReadStart);
$readStartHook = '// hook model_user__read_start.php';
$readEndHook = '// hook model_user__read_end.php';
$compiledUserRead = str_replace(
	$readStartHook,
	$readStartHook."\n\tif(intval(\$uid) === 10) \$uid = 11;\n\tif(intval(\$uid) === 12) \$primary = FALSE;",
	$compiledUserRead
);
$compiledUserRead = str_replace(
	$readEndHook,
	$readEndHook."\n\t\tif(\$actual_uid === 7) { \$nested = NULL; user__read(8, TRUE, \$nested); \$GLOBALS['nested_read_evidence'] = \$nested; \$user = array('uid'=>77, 'source'=>'legacy-end-hook'); \$db_evidence = array('spoof'=>TRUE); }\n\t\tif(\$actual_uid === 9) throw new RuntimeException('read evidence fixture failure');",
	$compiledUserRead
);
$compiledReadChild = "<?php\n"
	."\$primary_reads = array(); \$replica_reads = array(); \$nested_read_evidence = NULL;\n"
	."function db_find_one_master(\$table, \$condition = array()) { \$uid = intval(\$condition['uid']); \$GLOBALS['primary_reads'][] = \$uid; return array('uid'=>\$uid, 'source'=>'primary'); }\n"
	."function db_find_one(\$table, \$condition = array()) { \$uid = intval(\$condition['uid']); \$GLOBALS['replica_reads'][] = \$uid; return array('uid'=>\$uid, 'source'=>'replica'); }\n"
	.$compiledUserRead
	."\n\$ordinary_evidence = NULL; \$ordinary = user__read(7, TRUE, \$ordinary_evidence);\n"
	."\$proven = user__read_primary_proven(7); \$retargeted = user__read_primary_proven(10); \$downgraded = user__read_primary_proven(12);\n"
	."\$throw_caught = FALSE; try { \$throw_evidence = NULL; user__read(9, TRUE, \$throw_evidence); } catch(RuntimeException \$e) { \$throw_caught = TRUE; }\n"
	."\$after_evidence = NULL; user__read(13, TRUE, \$after_evidence);\n"
	."echo json_encode(array('ordinary'=>\$ordinary, 'ordinary_evidence'=>\$ordinary_evidence, 'nested'=>\$nested_read_evidence, 'proven'=>\$proven, 'retargeted'=>\$retargeted, 'downgraded'=>\$downgraded, 'throw_caught'=>\$throw_caught, 'after'=>\$after_evidence, 'primary_reads'=>\$primary_reads, 'replica_reads'=>\$replica_reads));\n";
$compiledReadExit = user_update_cache_run_php_child($compiledReadChild, $compiledReadOutput);
$compiledReadResult = json_decode(trim($compiledReadOutput), TRUE);
$compiledReadExit === 0 && is_array($compiledReadResult)
	&& $compiledReadResult['ordinary']['uid'] === 77
	&& $compiledReadResult['ordinary_evidence']['uid'] === 7
	&& $compiledReadResult['ordinary_evidence']['primary'] === TRUE
	&& $compiledReadResult['ordinary_evidence']['row']['uid'] === 7
	&& $compiledReadResult['nested']['uid'] === 8
	&& $compiledReadResult['proven']['uid'] === 7
	&& $compiledReadResult['proven']['source'] === 'primary'
	&& $compiledReadResult['retargeted'] === FALSE
	&& $compiledReadResult['downgraded'] === FALSE
	&& $compiledReadResult['throw_caught'] === TRUE
	&& $compiledReadResult['after']['uid'] === 13
	&& $compiledReadResult['after']['primary'] === TRUE
	&& in_array(12, $compiledReadResult['replica_reads'], TRUE)
	OR user_update_cache_fail('Primary user-read evidence was mutable, non-reentrant or accepted a Hook redirect: '.$compiledReadOutput);

include $root.'model/user.func.php';

// A failed write must leave both request-local and shared caches at the last proven state.
$before = user_update_cache_reset(FALSE);
$db_result = NULL;
$result = user_update(7, array('gid'=>999, 'logins+'=>1), NULL, $db_result);
$result === FALSE && $db_result === FALSE
	OR user_update_cache_fail('Database FALSE was not preserved by user_update().');
$g_static_users[7] === $before
	OR user_update_cache_fail('Database FALSE manufactured request-local user state.');
$userUpdateSharedCache['user-7'] === $before && empty($userUpdateCacheDeletes)
	OR user_update_cache_fail('Database FALSE invalidated a shared user cache without a committed change.');
$userUpdateRows[7]['gid'] === 100 && $userUpdateRows[7]['logins'] === 4
	OR user_update_cache_fail('Database FALSE fixture unexpectedly changed persistent user state.');

// A conditional miss or idempotent zero-row update likewise proves no new row generation.
$before = user_update_cache_reset(0);
$db_result = NULL;
$result = user_update(7, array('gid'=>999, 'logins+'=>1), NULL, $db_result);
$result === 0 && $db_result === 0
	OR user_update_cache_fail('Database zero-row result was not preserved by user_update().');
$g_static_users[7] === $before && !isset($g_static_users[7]['logins+'])
	OR user_update_cache_fail('Zero-row update merged uncommitted fields into the request cache.');
$userUpdateSharedCache['user-7'] === $before && empty($userUpdateCacheDeletes)
	OR user_update_cache_fail('Zero-row update invalidated a shared cache without a committed change.');

// On an exact one-row commit, discard derived/request caches and force the next request-local read
// to the primary even when shared invalidation succeeds. The replica fixture intentionally remains
// at the pre-commit generation; SQL operators such as logins+ must never become pseudo-fields.
user_update_cache_reset(1);
$db_result = NULL;
$result = user_update(7, array('gid'=>102, 'logins+'=>1), NULL, $db_result);
$result === 1 && $db_result === 1
	OR user_update_cache_fail('One-row commit was not preserved by user_update().');
!isset($g_static_users[7])
	OR user_update_cache_fail('Committed update retained a request-local derived user generation.');
$userUpdateCacheDeletes === array('user-7') && !isset($userUpdateSharedCache['user-7'])
	OR user_update_cache_fail('Committed update did not invalidate the shared user cache exactly once.');
$after = user_read_cache(7);
$userUpdatePointReads === 1 && $after['gid'] === 102 && $after['logins'] === 5
	OR user_update_cache_fail('Committed update did not reread the canonical database row.');
!isset($after['logins+']) && $after['groupname'] === 'group-102'
	OR user_update_cache_fail('Committed update exposed an SQL operator or stale derived group field.');

// Several cache backends return FALSE when DELETE targets an already absent key. A definite NULL
// read proves the idempotent invalidation is complete; it must not be diagnosed as backend failure.
user_update_cache_reset(1);
$userUpdateSharedCache = array();
$userUpdateCacheDeleteResult = FALSE;
$db_result = NULL;
user_update(7, array('gid'=>103), NULL, $db_result) === 1 && $db_result === 1
	&& !isset($g_static_users[7]) && array_key_exists(7, $g_user_cache_primary_only)
	&& $userUpdatePointReads === 0
	&& $userUpdateCacheDeletes === array('user-7')
	&& $userUpdateCacheGets === array('user-7')
	&& empty($userUpdateLogs)
	OR user_update_cache_fail('An already absent shared user key was misreported as an invalidation failure.');

// FALSE from both DELETE and GET is not a proven miss. Preserve primary-only state, reread the
// write endpoint and keep the diagnostic rather than silently accepting an unknown cache state.
user_update_cache_reset(1);
$userUpdateSharedCache = array();
$userUpdateCacheDeleteResult = FALSE;
$userUpdateCacheGetFailure = TRUE;
$db_result = NULL;
user_update(7, array('gid'=>103), NULL, $db_result) === 1 && $db_result === 1
	&& isset($g_static_users[7]) && $g_static_users[7]['gid'] === 103
	&& $userUpdatePointReads === 1
	&& $userUpdateCacheDeletes === array('user-7', 'user-7')
	&& $userUpdateCacheGets === array('user-7', 'user-7')
	&& count($userUpdateLogs) === 1
	OR user_update_cache_fail('An unavailable cache GET was mistaken for a proven absent key.');

// A committed row cannot be rolled back when shared-cache deletion fails. Preserve a primary
// canonical row for this request and retry the monotonic delete; never write an unlocked snapshot
// back over a possibly newer concurrent cache generation.
user_update_cache_reset(1);
$userUpdateCacheDeleteResult = array(FALSE, TRUE);
$db_result = NULL;
user_update(7, array('gid'=>104, 'logins+'=>1), NULL, $db_result) === 1 && $db_result === 1
	&& isset($g_static_users[7]) && $g_static_users[7]['gid'] === 104 && $g_static_users[7]['logins'] === 5
	&& $userUpdatePointReads === 1 && $userUpdateCacheDeletes === array('user-7', 'user-7')
	&& !isset($userUpdateSharedCache['user-7']) && empty($userUpdateCacheSets) && empty($userUpdateLogs)
	OR user_update_cache_fail('A transient cache delete failure did not retain the canonical request row and retry invalidation safely.');

user_update_cache_reset(1);
$userUpdateCacheDeleteResult = FALSE;
$userUpdateCacheSetResult = FALSE;
$db_result = NULL;
user_update(7, array('gid'=>105), NULL, $db_result) === 1 && $db_result === 1
	&& isset($g_static_users[7]) && $g_static_users[7]['gid'] === 105
	&& user_read_cache(7)['gid'] === 105 && $userUpdatePointReads === 1
	&& $userUpdateCacheDeletes === array('user-7', 'user-7') && empty($userUpdateCacheSets)
	&& isset($userUpdateSharedCache['user-7']) && $userUpdateSharedCache['user-7']['gid'] === 100
	&& count($userUpdateLogs) === 1
	&& strpos($userUpdateLogs[0][0], 'Unable to invalidate the shared user cache') !== FALSE
	OR user_update_cache_fail('An irreparable shared cache hid the committed database result or lacked diagnostics.');

// When both shared invalidation and the immediate primary reread fail, keep a request-local bypass.
// A later read must retry the primary rather than returning the known-stale shared generation.
user_update_cache_reset(1);
$userUpdateCacheDeleteResult = FALSE;
$userUpdatePrimaryReadFailure = TRUE;
$db_result = NULL;
user_update(7, array('gid'=>106), NULL, $db_result) === 1 && $db_result === 1
	&& !isset($g_static_users[7]) && array_key_exists(7, $g_user_cache_primary_only)
	|| user_update_cache_fail('Committed update did not retain a primary-only request boundary after reconciliation failure.');
$failed_primary_read = user_read_cache(7);
$failed_primary_read['uid'] === 0 && $userUpdatePointReads === 2
	&& isset($userUpdateSharedCache['user-7']) && $userUpdateSharedCache['user-7']['gid'] === 100
	&& empty($userUpdateCacheSets) && !isset($g_static_users[7])
	|| user_update_cache_fail('A failed primary retry fell back to the stale shared cache or cached a guest result.');
strpos($userUpdateLogs[0][0], 'this request is using the primary row') === FALSE
	|| user_update_cache_fail('Reconciliation diagnostics falsely claimed that an unavailable primary row was in use.');

// MySQL cache mode has no shared cache key to delete, but a configured read replica can still lag.
// The request-local primary-only generation must therefore apply in this mode as well.
user_update_cache_reset(1, 'mysql');
$db_result = NULL;
user_update(7, array('gid'=>107), NULL, $db_result) === 1 && $db_result === 1
	&& user_read_cache(7)['gid'] === 107 && $userUpdatePointReads === 1
	&& empty($userUpdateCacheDeletes) && empty($userUpdateCacheSets)
	|| user_update_cache_fail('MySQL cache mode reread a lagging replica after an exact committed update.');

// Group recalculation must not report success when its underlying user write fails.
user_update_cache_reset(FALSE, 'mysql');
$grouplist = array(array('gid'=>101, 'creditsfrom'=>0, 'creditsto'=>100));
user_update_group(7) === FALSE
	OR user_update_cache_fail('user_update_group() reported success after a failed database write.');
$g_static_users[7]['gid'] === 100
	OR user_update_cache_fail('Failed group update changed the request-local group.');

user_update_cache_reset(0, 'mysql');
$grouplist = array(array('gid'=>101, 'creditsfrom'=>0, 'creditsto'=>100));
user_update_group(7) === FALSE && !isset($g_static_users[7]) && $userUpdatePointReads === 1
	OR user_update_cache_fail('A zero-row group mismatch was reported as success or skipped its primary reconciliation.');

user_update_cache_reset(0, 'mysql');
$grouplist = array(array('gid'=>101, 'creditsfrom'=>0, 'creditsto'=>100));
$userUpdateRows[7]['gid'] = 101;
user_update_group(7) === TRUE && !isset($g_static_users[7]) && $userUpdatePointReads === 1
	OR user_update_cache_fail('A zero-row group update did not verify and adopt a concurrent primary winner.');

user_update_cache_reset(0, 'mysql');
$grouplist = array(array('gid'=>101, 'creditsfrom'=>0, 'creditsto'=>100));
unset($userUpdateRows[7]);
user_update_group(7) === FALSE && !isset($g_static_users[7]) && $userUpdatePointReads === 1
	OR user_update_cache_fail('A deleted primary user was reported as a successful zero-row group transition.');

user_update_cache_reset(0, 'mysql');
$grouplist = array(array('gid'=>101, 'creditsfrom'=>0, 'creditsto'=>100));
$userUpdatePrimaryReadFailure = TRUE;
user_update_group(7) === FALSE && isset($g_static_users[7]) && $userUpdatePointReads === 1
	OR user_update_cache_fail('A failed primary reconciliation discarded the last proven group or reported success.');

user_update_cache_reset(1, 'mysql');
$grouplist = array(array('gid'=>101, 'creditsfrom'=>0, 'creditsto'=>100));
user_update_group(7) === TRUE && $userUpdateRows[7]['gid'] === 101 && !isset($g_static_users[7])
	OR user_update_cache_fail('Successful group update did not publish and invalidate one canonical row.');

echo "OK: user update cache consistency checks passed\n";

?>
