<?php

$root = dirname(__DIR__).'/';
$sessionSource = file_get_contents($root.'model/session.func.php');
$indexSource = file_get_contents($root.'index.inc.php');
$userRoute = file_get_contents($root.'route/user.php');
$workflow = file_get_contents($root.'.github/workflows/ci.yml');

function fail($message) {
	fwrite(STDERR, "FAIL: $message\n");
	exit(1);
}

function test_sid($label) {
	return substr(str_pad($label, 32, '0'), 0, 32);
}

function source_section($source, $start, $end) {
	$startPos = strpos($source, $start);
	$endPos = $startPos === FALSE ? FALSE : strpos($source, $end, $startPos + strlen($start));
	if($startPos === FALSE || $endPos === FALSE) fail("Missing source section: $start");
	return substr($source, $startPos, $endPos - $startPos);
}

if($sessionSource === FALSE || $indexSource === FALSE || $userRoute === FALSE || $workflow === FALSE) {
	fail('failed to read session rotation sources');
}

$rotate = source_section($sessionSource, 'function sess_regenerate_id()', 'function online_count()');
strpos($rotate, 'session_status() !== PHP_SESSION_ACTIVE') !== FALSE
	|| fail('Session rotation must refuse inactive sessions.');
strpos($rotate, 'session_regenerate_id(TRUE)') !== FALSE
	|| fail('Session rotation must invalidate the previous session ID.');
strpos($rotate, '$g_session_new_failed') !== FALSE
	|| fail('Session rotation must fail cleanly when the replacement session cannot be stored.');
strpos($rotate, '$sid = session_id();') !== FALSE
	|| fail('Session rotation must synchronize the procedural sid global.');

$sessionRead = source_section($sessionSource, 'function sess_read(', 'function sess_new(');
$sessionNew = source_section($sessionSource, 'function sess_new(', 'function sess_restart(');
$sessionWrite = source_section($sessionSource, 'function sess_write(', 'function sess_find_one_primary(');
strpos($sessionRead, '$arr = sess_find_one_primary($sid);') !== FALSE
	&& strpos($sessionRead, "sess_find_one_primary(\$g_session_data_sid, 'session_data')") !== FALSE
	&& strpos($sessionNew, '$existing = sess_find_one_primary($sid);') !== FALSE
	|| fail('Authentication Session reads and insert-race recovery must use the primary database view.');
$dataPreparePos = strpos($sessionWrite, "db_insert('session_data'");
$mainPublishPos = strpos($sessionWrite, "db_update('session', \$session_condition", $dataPreparePos === FALSE ? 0 : $dataPreparePos);
($dataPreparePos !== FALSE && $mainPublishPos !== FALSE && $dataPreparePos < $mainPublishPos)
	|| fail('Large Session auxiliary data must be complete before the main row publishes its bigdata pointer.');
strpos($sessionWrite, "'bigdata'=>intval(\$g_session['bigdata'])") !== FALSE
	&& strpos($sessionWrite, "'data'=>(string)\$g_session_main_data") !== FALSE
	|| fail('Session publication must compare-and-swap the observed storage mode and data pointer.');

$tokenLogin = source_section($indexSource, '$uid = intval(_SESSION', '$gid = empty($user)');
$tokenReadPos = strpos($tokenLogin, '$uid = user_token_get($token_auth_epoch);');
$tokenUserReadPos = strpos($tokenLogin, '$user = user_read_primary_proven($uid);');
$tokenEpochMatchPos = strpos($tokenLogin, 'user_auth_epoch_matches($user, $token_auth_epoch)');
$tokenRotatePos = strpos($tokenLogin, 'sess_regenerate_id()');
$tokenBindPos = strpos($tokenLogin, 'user_session_auth_bind($uid, $token_auth_epoch);');
$tokenClearPos = strpos($tokenLogin, 'user_token_clear();');
($tokenReadPos !== FALSE && $tokenUserReadPos !== FALSE && $tokenRotatePos !== FALSE
	&& $tokenEpochMatchPos !== FALSE && $tokenBindPos !== FALSE && $tokenClearPos !== FALSE
	&& $tokenReadPos < $tokenUserReadPos && $tokenUserReadPos < $tokenRotatePos
	&& $tokenEpochMatchPos < $tokenRotatePos && $tokenRotatePos < $tokenBindPos && $tokenBindPos < $tokenClearPos)
	|| fail('Persistent token login must match the proved epoch, rotate, bind that epoch, and fail closed on failure.');

$passwordLogin = source_section($userRoute, "} elseif(\$action == 'login')", "} elseif(\$action == 'create')");
$passwordRotatePos = strpos($passwordLogin, 'sess_regenerate_id()');
$passwordFinalizePos = strpos($passwordLogin, "user_login_credentials_refresh(\$_user['uid'], \$password)");
$passwordUidPos = strpos($passwordLogin, 'user_session_auth_bind($uid, $login_auth_epoch)');
($passwordRotatePos !== FALSE && $passwordUidPos !== FALSE && $passwordRotatePos < $passwordUidPos)
	|| fail('Password login must rotate the session before binding uid and credential epoch.');
strpos($passwordLogin, "sess_regenerate_id() OR message(-1, 'Unable to renew session. Please try again.');") !== FALSE
	|| fail('Password login must fail rather than authenticate when session rotation fails.');
$passwordUpdatePos = strpos($passwordLogin, "user_update(\$_user['uid']");
$passwordRateClearPos = strpos($passwordLogin, 'user_login_rate_clear($email);');
($passwordRotatePos !== FALSE && $passwordFinalizePos !== FALSE && $passwordUpdatePos !== FALSE && $passwordRateClearPos !== FALSE
	&& $passwordRotatePos < $passwordFinalizePos && $passwordFinalizePos < $passwordUpdatePos
	&& $passwordFinalizePos < $passwordRateClearPos)
	|| fail('Password login must rotate before finalization and finalize before success side effects.');
$ci_runs_deterministic = strpos($workflow, 'php bin/run_checks.php --profile=deterministic') !== FALSE;
strpos($workflow, 'php bin/check_session_rotation_safety.php') !== FALSE || $ci_runs_deterministic
	|| fail('CI must run the session rotation safety guard.');

$sessionRows = array();
$sessionDataRows = array();
$cookieWrites = array();
$deleteFailures = array();
$deleteFailureOnSessionCall = 0;
$sessionDeleteCalls = 0;
$updateFailures = array();
$updateFailuresAfterApply = array();
$insertFailures = array();
$beforeSessionUpdate = NULL;
$findFailures = array();
$sessionInsertFailure = FALSE;
$sessionInsertRace = array();
$primaryReadFailure = FALSE;
$primaryListFailure = FALSE;
$primaryReads = 0;
$gcAtomicFailure = FALSE;
$gcAtomicThrow = FALSE;
$beforeGcAtomicDelete = NULL;
$gcAtomicAttempts = 0;
$gcAtomicCalls = array();
$onlineGenerationWrites = 0;
$onlineGenerationFailure = FALSE;
$onlineGenerationThrow = FALSE;

class SessionPrimaryDb {
	public $tablepre = '';
	public function find_one_master($table, $cond = array(), $orderby = array(), $col = array()) { return NULL; }
	public function find_master($table, $cond = array(), $orderby = array(), $page = 1, $pagesize = 20, $key = '', $col = array()) { return array(); }
	public function exec($sql) {}
}

class SessionNoExecDb {
	public $tablepre = '';
}

class SessionPrivateExecDb {
	public $tablepre = '';
	private function exec($sql) {}
}

function _SERVER($key, $default = NULL) {
	return isset($_SERVER[$key]) ? $_SERVER[$key] : $default;
}

function _COOKIE($key, $default = NULL) {
	return isset($_COOKIE[$key]) ? $_COOKIE[$key] : $default;
}

function _SESSION($key, $default = NULL) {
	global $g_session;
	if(isset($_SESSION[$key])) return $_SESSION[$key];
	return isset($g_session[$key]) ? $g_session[$key] : $default;
}

function xn_cookie_secure() {
	return FALSE;
}

function xn_encrypt($value, $key) {
	return $value.'|'.$key;
}

function xn_setcookie($name, $value, $expires = 0, $path = '', $httponly = TRUE, $samesite = 'Lax') {
	global $cookieWrites;
	$cookieWrites[] = array($name, $value, $expires, $path, $httponly, $samesite);
	return TRUE;
}

function cache_set($key, $value, $ttl = 0) {
	global $onlineGenerationWrites, $onlineGenerationFailure, $onlineGenerationThrow;
	if($key === 'online_member_generation') {
		$onlineGenerationWrites++;
		if($onlineGenerationThrow) throw new RuntimeException('injected generation write failure');
		if($onlineGenerationFailure) return FALSE;
	}
	return TRUE;
}

function array_diff_value($new, $old) {
	foreach($new as $key=>$value) {
		if(isset($old[$key]) && $old[$key] == $value) unset($new[$key]);
	}
	return $new;
}

function db_find_one($table, $cond) {
	global $sessionRows, $sessionDataRows;
	$rows = $table == 'session' ? $sessionRows : ($table == 'session_data' ? $sessionDataRows : array());
	foreach($rows as $row) {
		if(row_matches($row, $cond)) return $row;
	}
	return array();
}

function db_find($table, $cond = array(), $orderby = array(), $page = 1, $pagesize = 20, $key = '') {
	global $sessionRows, $sessionDataRows, $findFailures;
	if(!empty($findFailures[$table])) return FALSE;
	$rows = $table == 'session' ? array_values($sessionRows) : ($table == 'session_data' ? array_values($sessionDataRows) : array());
	$rows = array_values(array_filter($rows, function($row) use ($cond) { return row_matches($row, $cond); }));
	if($orderby) {
		$field = key($orderby);
		$direction = current($orderby);
		usort($rows, function($left, $right) use ($field, $direction) {
			$result = intval($left[$field]) <=> intval($right[$field]);
			return $direction == 1 ? $result : -$result;
		});
	}
	$rows = array_slice($rows, max(0, ($page - 1) * $pagesize), $pagesize);
	if($key) {
		$indexed = array();
		foreach($rows as $row) $indexed[$row[$key]] = $row;
		return $indexed;
	}
	return $rows;
}

function db_find_one_master($table, $cond = array(), $orderby = array(), $col = array(), $db = NULL) {
	global $primaryReadFailure, $primaryReads;
	$primaryReads++;
	if($primaryReadFailure) return FALSE;
	return db_find_one($table, $cond);
}

function db_find_master($table, $cond = array(), $orderby = array(), $page = 1, $pagesize = 20, $key = '', $col = array(), $db = NULL) {
	global $primaryListFailure, $primaryReads;
	$primaryReads++;
	if($primaryListFailure) return FALSE;
	return db_find($table, $cond, $orderby, $page, $pagesize, $key);
}

function db_insert($table, $row) {
	global $sessionRows, $sessionDataRows, $sessionInsertFailure, $sessionInsertRace, $insertFailures;
	if($table == 'session') {
		if($sessionInsertFailure || strlen($row['sid']) > 32) return FALSE;
		if(isset($sessionInsertRace[$row['sid']])) {
			$sessionRows[$row['sid']] = $sessionInsertRace[$row['sid']];
			unset($sessionInsertRace[$row['sid']]);
			return FALSE;
		}
		if(isset($sessionRows[$row['sid']])) return FALSE;
		$sessionRows[$row['sid']] = $row;
	} elseif($table == 'session_data') {
		if(!empty($insertFailures[$table]) || strlen($row['sid']) > 32 || isset($sessionDataRows[$row['sid']])) return FALSE;
		$sessionDataRows[$row['sid']] = $row + array('last_date'=>0, 'data'=>'');
	}
	return TRUE;
}

function db_update($table, $cond, $update) {
	global $sessionRows, $sessionDataRows, $updateFailures, $updateFailuresAfterApply, $beforeSessionUpdate;
	if(!empty($updateFailures[$table])) return FALSE;
	$sid = isset($cond['sid']) ? $cond['sid'] : '';
	if($table == 'session' && isset($sessionRows[$sid])) {
		if(is_callable($beforeSessionUpdate)) {
			$callback = $beforeSessionUpdate;
			$beforeSessionUpdate = NULL;
			$callback($sid, $cond, $update);
		}
		if(!isset($sessionRows[$sid]) || !row_matches($sessionRows[$sid], $cond)) return 0;
		$changed = array_diff_value($update, $sessionRows[$sid]);
		$sessionRows[$sid] = array_merge($sessionRows[$sid], $update);
		if(!empty($updateFailuresAfterApply[$table])) return FALSE;
		return $changed ? 1 : 0;
	}
	if($table == 'session_data' && isset($sessionDataRows[$sid])) {
		if(!row_matches($sessionDataRows[$sid], $cond)) return 0;
		$changed = array_diff_value($update, $sessionDataRows[$sid]);
		$sessionDataRows[$sid] = array_merge($sessionDataRows[$sid], $update);
		if(!empty($updateFailuresAfterApply[$table])) return FALSE;
		return $changed ? 1 : 0;
	}
	return 0;
}

function row_matches($row, $cond) {
	foreach($cond as $key => $value) {
		if(!array_key_exists($key, $row)) return FALSE;
		if(!is_array($value)) {
			if((string)$row[$key] !== (string)$value) return FALSE;
			continue;
		}
		foreach($value as $operator => $expected) {
			if($operator == '<=' && !($row[$key] <= $expected)) return FALSE;
			if($operator == '<' && !($row[$key] < $expected)) return FALSE;
			if($operator == '>=' && !($row[$key] >= $expected)) return FALSE;
			if($operator == '>' && !($row[$key] > $expected)) return FALSE;
			if($operator == '!=' && !($row[$key] != $expected)) return FALSE;
		}
	}
	return TRUE;
}

function db_delete($table, $cond) {
	global $sessionRows, $sessionDataRows, $deleteFailures, $deleteFailureOnSessionCall, $sessionDeleteCalls;
	if(!empty($deleteFailures[$table])) return FALSE;
	if($table == 'session') {
		$sessionDeleteCalls++;
		if($deleteFailureOnSessionCall > 0 && $sessionDeleteCalls === $deleteFailureOnSessionCall) return FALSE;
		$deleted = 0;
		foreach($sessionRows as $sid => $row) {
			if(row_matches($row, $cond)) {
				unset($sessionRows[$sid]);
				$deleted++;
			}
		}
		return $deleted;
	}
	if($table == 'session_data') {
		$deleted = 0;
		foreach($sessionDataRows as $sid => $row) {
			if(row_matches($row, $cond)) {
				unset($sessionDataRows[$sid]);
				$deleted++;
			}
		}
		return $deleted;
	}
	return 0;
}

function session_guard_data_referenced($data_sid) {
	global $sessionRows;
	foreach($sessionRows as $row) {
		if(intval($row['bigdata']) !== 1) continue;
		if((isset($row['data']) ? (string)$row['data'] : '') === '') {
			if((string)$row['sid'] === (string)$data_sid) return TRUE;
		} elseif((string)$row['data'] === (string)$data_sid) {
			return TRUE;
		}
	}
	return FALSE;
}

function db_exec($sql, $db = NULL) {
	global $sessionDataRows, $gcAtomicFailure, $gcAtomicThrow, $beforeGcAtomicDelete, $gcAtomicAttempts, $gcAtomicCalls;
	$gcAtomicAttempts++;
	if($gcAtomicThrow) throw new RuntimeException('injected atomic cleanup failure');
	strpos($sql, 'session_data') !== FALSE && strpos($sql, 'NOT EXISTS') !== FALSE
		OR fail('Session auxiliary cleanup did not use a reference-aware atomic statement.');
	strpos($sql, "`session_ref`.`data`='' AND `session_ref`.`sid`") !== FALSE
		&& strpos($sql, "`session_ref`.`data`<>'' AND `session_ref`.`data`") !== FALSE
		OR fail('Session auxiliary cleanup did not protect both legacy and immutable main-row references.');
	$gcAtomicCalls[] = $sql;
	if(is_callable($beforeGcAtomicDelete)) {
		$callback = $beforeGcAtomicDelete;
		$beforeGcAtomicDelete = NULL;
		$callback();
	}
	if($gcAtomicFailure) return FALSE;

	$candidate = NULL;
	$expiry = NULL;
	if(preg_match("~WHERE `sid`='([a-f0-9]{32})' AND NOT EXISTS~D", $sql, $match)) {
		$candidate = $match[1];
	} elseif(preg_match('~WHERE `last_date` < (-?[0-9]+) AND NOT EXISTS~D', $sql, $match)) {
		$expiry = intval($match[1]);
	} else {
		fail('Session auxiliary cleanup used an unsupported guard SQL shape.');
	}

	$deleted = 0;
	foreach($sessionDataRows as $dataSid=>$row) {
		if($candidate !== NULL && (string)$dataSid !== $candidate) continue;
		if($expiry !== NULL && intval($row['last_date']) >= $expiry) continue;
		if(session_guard_data_referenced($dataSid)) continue;
		unset($sessionDataRows[$dataSid]);
		$deleted++;
	}
	return $deleted;
}

define('APP_PATH', $root);
$time = 1700000000;
$longip = 2130706433;
$conf = array('auth_key'=>'session-rotation-test', 'online_hold_time'=>3600);
$_SERVER = array(
	'HTTP_USER_AGENT'=>'session-rotation-test',
	'REQUEST_URI_NO_PATH'=>'user-login',
	'SCRIPT_NAME'=>'/index.php',
	'HTTPS'=>'off',
	'SERVER_PORT'=>80,
	'db'=>new SessionPrimaryDb(),
);
$_COOKIE = array();

$oldSid = test_sid('old-session');
$sessionRows[$oldSid] = array(
	'sid'=>$oldSid,
	'uid'=>7,
	'fid'=>3,
	'url'=>'old-route',
	'last_date'=>$time - 10,
	'data'=>'',
	'ip'=>$longip,
	'useragent'=>'session-rotation-test',
	'bigdata'=>1,
);
$sessionDataRows[$oldSid] = array('sid'=>$oldSid, 'data'=>'csrf_token|s:10:"keep-token";', 'last_date'=>$time - 10);

require $root.'model/session.func.php';

function prepare_write_fixture($sid, $bigdata, $encodedData) {
	global $sessionRows, $sessionDataRows, $time, $longip, $g_session;
	if(session_status() === PHP_SESSION_ACTIVE) session_abort();
	$sessionRows[$sid] = array(
		'sid'=>$sid,
		'uid'=>0,
		'fid'=>0,
		'url'=>'write-fixture',
		'last_date'=>$time,
		'data'=>$bigdata ? '' : $encodedData,
		'ip'=>$longip,
		'useragent'=>'session-rotation-test',
		'bigdata'=>$bigdata ? 1 : 0,
	);
	if($bigdata) $sessionDataRows[$sid] = array('sid'=>$sid, 'data'=>$encodedData, 'last_date'=>$time);
	else unset($sessionDataRows[$sid]);
	$_SESSION = array();
	$g_session = array();
	$GLOBALS['sid'] = '';
	session_id($sid);
	return sess_start();
}

sess_regenerate_id() === FALSE
	|| fail('Inactive sessions must not report successful rotation.');

$missingTombstoneSid = test_sid('missing-tombstone');
sess_tombstone($missingTombstoneSid)
	|| fail('A concurrently removed previous session must receive a replacement tombstone.');
isset($sessionRows[$missingTombstoneSid]) && intval($sessionRows[$missingTombstoneSid]['bigdata']) === 2
	|| fail('A missing previous session must be replaced with a tombstone.');

$raceTombstoneSid = test_sid('tombstone-insert-race');
$sessionInsertRace[$raceTombstoneSid] = array(
	'sid'=>$raceTombstoneSid,
	'uid'=>7,
	'fid'=>3,
	'url'=>'old-route',
	'last_date'=>$time,
	'data'=>'',
	'ip'=>$longip,
	'useragent'=>'session-rotation-test',
	'bigdata'=>0,
);
sess_tombstone($raceTombstoneSid)
	|| fail('A concurrent replacement session must be converted back into a tombstone.');
intval($sessionRows[$raceTombstoneSid]['uid']) === 0 && intval($sessionRows[$raceTombstoneSid]['bigdata']) === 2
	|| fail('A concurrent replacement session must not stay active under the old ID.');

$createRaceSid = test_sid('session-create-race');
$sessionInsertRace[$createRaceSid] = array(
	'sid'=>$createRaceSid,
	'uid'=>0,
	'fid'=>0,
	'url'=>'parallel-route',
	'last_date'=>$time,
	'data'=>'race-small-data',
	'ip'=>$longip,
	'useragent'=>'session-rotation-test',
	'bigdata'=>0,
);
$g_session = array();
$g_session_new_failed = FALSE;
sess_read($createRaceSid) === 'race-small-data'
	|| fail('A concurrent small session insert must return the row that won the race.');
!$g_session_new_failed && isset($g_session['sid']) && $g_session['sid'] === $createRaceSid
	|| fail('A concurrent small session insert must not leave the request without a session.');

$createBigDataRaceSid = test_sid('session-bigdata-race');
$sessionInsertRace[$createBigDataRaceSid] = array(
	'sid'=>$createBigDataRaceSid,
	'uid'=>0,
	'fid'=>0,
	'url'=>'parallel-route',
	'last_date'=>$time,
	'data'=>'',
	'ip'=>$longip,
	'useragent'=>'session-rotation-test',
	'bigdata'=>1,
);
$sessionDataRows[$createBigDataRaceSid] = array('sid'=>$createBigDataRaceSid, 'data'=>'race-big-data', 'last_date'=>$time);
$g_session = array();
$g_session_new_failed = FALSE;
sess_read($createBigDataRaceSid) === 'race-big-data'
	|| fail('A concurrent big session insert must restore session_data from the row that won the race.');
!$g_session_new_failed && isset($g_session['sid']) && $g_session['sid'] === $createBigDataRaceSid && $g_session['data'] === 'race-big-data'
	|| fail('A concurrent big session insert must retain the restored session data.');

$missingBigDataSid = test_sid('missing-bigdata');
$sessionRows[$missingBigDataSid] = array(
	'sid'=>$missingBigDataSid,
	'uid'=>0,
	'fid'=>0,
	'url'=>'parallel-route',
	'last_date'=>$time,
	'data'=>'',
	'ip'=>$longip,
	'useragent'=>'session-rotation-test',
	'bigdata'=>1,
);
$g_session = array();
$g_session_new_failed = FALSE;
sess_read($missingBigDataSid) === '' && $g_session_new_failed && $g_session === array()
	|| fail('A missing session_data row must fail closed instead of authenticating a partial empty snapshot.');

$invalidPointerSid = test_sid('invalid-bigdata-pointer');
$sessionRows[$invalidPointerSid] = array(
	'sid'=>$invalidPointerSid,
	'uid'=>7,
	'fid'=>3,
	'url'=>'parallel-route',
	'last_date'=>$time,
	'data'=>'not-a-valid-immutable-pointer',
	'ip'=>$longip,
	'useragent'=>'session-rotation-test',
	'bigdata'=>1,
);
$sessionDataRows[$invalidPointerSid] = array('sid'=>$invalidPointerSid, 'data'=>'stale-legacy-data', 'last_date'=>$time);
$g_session = array();
$g_session_new_failed = FALSE;
sess_read($invalidPointerSid) === '' && $g_session_new_failed && $g_session === array()
	|| fail('An invalid non-empty bigdata pointer must not fall back to a stale legacy SID row.');

$createRevokedRaceSid = test_sid('session-create-revoked-race');
$sessionInsertRace[$createRevokedRaceSid] = array(
	'sid'=>$createRevokedRaceSid,
	'uid'=>0,
	'fid'=>0,
	'url'=>'old-route',
	'last_date'=>$time,
	'data'=>'',
	'ip'=>$longip,
	'useragent'=>'session-rotation-test',
	'bigdata'=>2,
);
$g_session = array();
$g_session_new_failed = FALSE;
$g_session_revoked = FALSE;
sess_new($createRevokedRaceSid) === FALSE && $g_session_new_failed && $g_session_revoked
	|| fail('A concurrent tombstone must not be reused as an active session.');

$destroyTombstoneSid = test_sid('destroy-tombstone');
$sessionRows[$destroyTombstoneSid] = array(
	'sid'=>$destroyTombstoneSid,
	'uid'=>7,
	'fid'=>3,
	'url'=>'old-route',
	'last_date'=>$time,
	'data'=>'',
	'ip'=>$longip,
	'useragent'=>'session-rotation-test',
	'bigdata'=>1,
);
$sessionDataRows[$destroyTombstoneSid] = array('sid'=>$destroyTombstoneSid, 'data'=>'csrf_token|s:10:"keep-token";', 'last_date'=>$time);
sess_destroy($destroyTombstoneSid)
	|| fail('Explicit session destruction must create a tombstone.');
isset($sessionRows[$destroyTombstoneSid]) && intval($sessionRows[$destroyTombstoneSid]['bigdata']) === 2 && !isset($sessionDataRows[$destroyTombstoneSid])
	|| fail('Explicit session destruction must not leave a reusable ID or session data.');
$g_session_revoked = FALSE;
sess_read($destroyTombstoneSid) === '' && $g_session_revoked
	|| fail('An explicitly destroyed session ID must stay unavailable to stale clients.');

$initialFailureSid = test_sid('initial-failure');
$sessionInsertFailure = TRUE;
session_id($initialFailureSid);
sess_start() === FALSE
	|| fail('Initial session creation must fail when its database row cannot be inserted.');
$sessionInsertFailure = FALSE;
(session_status() === PHP_SESSION_NONE && $sid === '' && empty($_SESSION) && $g_session === array() && !isset($sessionRows[$initialFailureSid]))
	|| fail('Failed initial session creation must not leave an active unwritable session.');

$delayedBigDataSid = test_sid('delayed-bigdata');
$sessionRows[$delayedBigDataSid] = array(
	'sid'=>$delayedBigDataSid,
	'uid'=>0,
	'fid'=>0,
	'url'=>'user-login',
	'last_date'=>$time,
	'data'=>'',
	'ip'=>$longip,
	'useragent'=>'session-rotation-test',
	'bigdata'=>0,
);
$conf['session_delay_update'] = 60;
$_SESSION = array();
$g_session = array();
session_id($delayedBigDataSid);
sess_start() || fail('A delayed small session must start before it grows into session_data.');
$_SESSION['payload'] = str_repeat('x', 300);
sess_write($delayedBigDataSid, TRUE) || fail('A delayed small-to-big Session write must succeed.');
$delayedBigDataPointer = $sessionRows[$delayedBigDataSid]['data'];
is_string($delayedBigDataPointer) && preg_match('/^[a-f0-9]{32}$/D', $delayedBigDataPointer)
	&& $delayedBigDataPointer !== $delayedBigDataSid
	&& isset($sessionDataRows[$delayedBigDataPointer])
	&& intval($sessionDataRows[$delayedBigDataPointer]['last_date']) === $time
	|| fail('A delayed small-to-big session write must timestamp its new session_data row.');
sess_gc($conf['online_hold_time']);
isset($sessionDataRows[$delayedBigDataPointer])
	|| fail('Session GC must not immediately delete a newly created delayed session_data row.');
session_write_close();
$conf['session_delay_update'] = 0;
$_SESSION = array();

$insertFailureDataSid = test_sid('data-insert-failure');
prepare_write_fixture($insertFailureDataSid, 0, '') || fail('Insert-failure Session fixture could not start.');
$_SESSION['payload'] = str_repeat('r', 300);
$dataRowsBeforeInsertFailure = $sessionDataRows;
$insertFailures['session_data'] = TRUE;
sess_write($insertFailureDataSid, TRUE) === FALSE
	|| fail('A failed immutable Session auxiliary INSERT must propagate failure.');
intval($sessionRows[$insertFailureDataSid]['bigdata']) === 0 && $sessionRows[$insertFailureDataSid]['data'] === ''
	&& $sessionDataRows === $dataRowsBeforeInsertFailure
	|| fail('A failed immutable auxiliary INSERT must not publish a main-row pointer or leave partial data.');
unset($insertFailures['session_data']);
session_abort();

$mainPublishFailureSid = test_sid('main-publish-failure');
prepare_write_fixture($mainPublishFailureSid, 0, '') || fail('Main-publish-failure Session fixture could not start.');
$_SESSION['payload'] = str_repeat('m', 300);
$dataRowsBeforeMainPublishFailure = $sessionDataRows;
$updateFailures['session'] = TRUE;
sess_write($mainPublishFailureSid, TRUE) === FALSE
	|| fail('A failed main Session pointer update must propagate failure.');
intval($sessionRows[$mainPublishFailureSid]['bigdata']) === 0 && $sessionRows[$mainPublishFailureSid]['data'] === ''
	&& $sessionDataRows === $dataRowsBeforeMainPublishFailure
	|| fail('A failed main Session pointer update must clean its newly prepared auxiliary row.');
unset($updateFailures['session']);
session_abort();

$uncertainCommittedSid = test_sid('uncertain-committed-cas');
prepare_write_fixture($uncertainCommittedSid, 0, '') || fail('Uncertain committed CAS fixture could not start.');
$_SESSION['payload'] = str_repeat('u', 300);
$updateFailuresAfterApply['session'] = TRUE;
sess_write($uncertainCommittedSid, TRUE)
	|| fail('A main pointer committed before an uncertain FALSE result must be adopted after primary verification.');
unset($updateFailuresAfterApply['session']);
$uncertainCommittedPointer = $sessionRows[$uncertainCommittedSid]['data'];
preg_match('/^[a-f0-9]{32}$/D', $uncertainCommittedPointer)
	&& isset($sessionDataRows[$uncertainCommittedPointer])
	&& strpos($sessionDataRows[$uncertainCommittedPointer]['data'], str_repeat('u', 300)) !== FALSE
	|| fail('A proved uncertain Session commit lost or deleted its published auxiliary generation.');
session_abort();
$_SESSION = array();
$g_session = array();
session_id($uncertainCommittedSid);
sess_start() && isset($_SESSION['payload']) && $_SESSION['payload'] === str_repeat('u', 300)
	|| fail('A main pointer adopted after an uncertain result must restore its complete payload.');
session_abort();

$uncertainUnknownSid = test_sid('uncertain-unknown-cas');
prepare_write_fixture($uncertainUnknownSid, 0, '') || fail('Uncertain unknown CAS fixture could not start.');
$_SESSION['payload'] = str_repeat('v', 300);
$unknownRowsBefore = array_keys($sessionDataRows);
$updateFailures['session'] = TRUE;
$primaryReadFailure = TRUE;
sess_write($uncertainUnknownSid, TRUE) === FALSE
	|| fail('An uncertain main CAS with an unavailable primary verification must fail closed.');
$unknownRowsAfter = array_values(array_diff(array_keys($sessionDataRows), $unknownRowsBefore));
intval($sessionRows[$uncertainUnknownSid]['bigdata']) === 0 && count($unknownRowsAfter) === 1
	&& isset($sessionDataRows[$unknownRowsAfter[0]])
	|| fail('An uncertain unverified main CAS must retain its complete candidate for reference-aware GC.');
$primaryReadFailure = FALSE;
unset($updateFailures['session']);
unset($sessionDataRows[$unknownRowsAfter[0]]);
session_abort();

$oldLargeData = 'payload|s:300:"'.str_repeat('o', 300).'";';
$dataUpdateFailureSid = test_sid('data-update-failure');
prepare_write_fixture($dataUpdateFailureSid, 1, $oldLargeData) || fail('Data-update-failure Session fixture could not start.');
$_SESSION['payload'] = str_repeat('n', 300);
$mainBeforeDataFailure = $sessionRows[$dataUpdateFailureSid];
$dataRowsBeforeDataFailure = $sessionDataRows;
$insertFailures['session_data'] = TRUE;
sess_write($dataUpdateFailureSid, TRUE) === FALSE
	|| fail('A failed existing Session immutable auxiliary INSERT must propagate failure.');
$sessionRows[$dataUpdateFailureSid] === $mainBeforeDataFailure && $sessionDataRows === $dataRowsBeforeDataFailure
	|| fail('A failed existing Session auxiliary INSERT must preserve the complete old snapshot.');
unset($insertFailures['session_data']);
session_abort();

$existingMainFailureSid = test_sid('existing-main-failure');
prepare_write_fixture($existingMainFailureSid, 1, $oldLargeData) || fail('Existing-main-failure Session fixture could not start.');
$_SESSION['payload'] = str_repeat('p', 300);
$existingMainBeforeFailure = $sessionRows[$existingMainFailureSid];
$existingDataBeforeFailure = $sessionDataRows;
$updateFailures['session'] = TRUE;
sess_write($existingMainFailureSid, TRUE) === FALSE
	|| fail('An existing large Session must report a failed main pointer CAS.');
unset($updateFailures['session']);
$sessionRows[$existingMainFailureSid] === $existingMainBeforeFailure
	&& $sessionDataRows === $existingDataBeforeFailure
	|| fail('A failed existing main pointer CAS must clean only its new auxiliary and preserve the old snapshot.');
session_abort();
$_SESSION = array();
$g_session = array();
session_id($existingMainFailureSid);
sess_start() && isset($_SESSION['payload']) && $_SESSION['payload'] === str_repeat('o', 300)
	|| fail('The complete old large Session snapshot must remain readable after main publication fails.');
session_abort();

$legacyMigrationSid = test_sid('legacy-pointer-migration');
prepare_write_fixture($legacyMigrationSid, 1, $oldLargeData) || fail('Legacy pointer migration fixture could not start.');
// Do not change the decoded payload: even an unchanged first write must leave the legacy empty
// main pointer behind and publish an immutable generation.
sess_write($legacyMigrationSid, TRUE) || fail('A legacy empty-pointer Session must migrate on its first successful write.');
$firstImmutablePointer = $sessionRows[$legacyMigrationSid]['data'];
preg_match('/^[a-f0-9]{32}$/D', $firstImmutablePointer) && $firstImmutablePointer !== $legacyMigrationSid
	&& isset($sessionDataRows[$legacyMigrationSid], $sessionDataRows[$firstImmutablePointer])
	&& $sessionDataRows[$firstImmutablePointer]['data'] === $oldLargeData
	|| fail('Unchanged legacy migration must publish a unique immutable pointer while retaining the old auxiliary for GC.');
$_SESSION['payload'] = str_repeat('q', 300);
sess_write($legacyMigrationSid, TRUE) || fail('A migrated large Session must publish a second immutable generation.');
$secondImmutablePointer = $sessionRows[$legacyMigrationSid]['data'];
$secondImmutablePointer !== $firstImmutablePointer
	&& isset($sessionDataRows[$firstImmutablePointer], $sessionDataRows[$secondImmutablePointer])
	&& strpos($sessionDataRows[$secondImmutablePointer]['data'], str_repeat('q', 300)) !== FALSE
	|| fail('Every changed large Session payload must switch to a new immutable auxiliary generation.');
$unchangedDataRows = $sessionDataRows;
$time += 10;
sess_write($legacyMigrationSid, TRUE) || fail('An unchanged large Session heartbeat must remain writable.');
$sessionRows[$legacyMigrationSid]['data'] === $secondImmutablePointer
	&& array_keys($sessionDataRows) === array_keys($unchangedDataRows)
	&& intval($sessionDataRows[$secondImmutablePointer]['last_date']) === $time
	&& intval($sessionRows[$legacyMigrationSid]['last_date']) === $time
	|| fail('An unchanged large Session must reuse its immutable payload while refreshing main and auxiliary lifetimes.');
session_abort();

$refreshFailureSid = test_sid('unchanged-refresh-failure');
prepare_write_fixture($refreshFailureSid, 1, $oldLargeData) || fail('Unchanged-refresh-failure Session fixture could not start.');
sess_write($refreshFailureSid, TRUE) || fail('Unchanged-refresh-failure fixture could not migrate to an immutable pointer.');
$refreshFailurePointer = $sessionRows[$refreshFailureSid]['data'];
$time += 10;
$refreshMainBefore = $sessionRows[$refreshFailureSid];
$refreshDataBefore = $sessionDataRows[$refreshFailurePointer];
$updateFailures['session_data'] = TRUE;
sess_write($refreshFailureSid, TRUE) === FALSE
	|| fail('A failed unchanged auxiliary lifetime refresh must propagate failure.');
$sessionRows[$refreshFailureSid] === $refreshMainBefore && $sessionDataRows[$refreshFailurePointer] === $refreshDataBefore
	|| fail('A failed auxiliary lifetime refresh must stop before main Session publication.');
unset($updateFailures['session_data']);
session_abort();

$concurrentCasSid = test_sid('concurrent-pointer-cas');
prepare_write_fixture($concurrentCasSid, 1, $oldLargeData) || fail('Concurrent pointer CAS fixture could not start.');
$_SESSION['payload'] = str_repeat('a', 300);
$concurrentWinnerPointer = str_repeat('b', 32);
$concurrentWinnerData = 'payload|s:300:"'.str_repeat('w', 300).'";';
$concurrentRowsBefore = $sessionDataRows;
$beforeSessionUpdate = function($sid) use ($concurrentCasSid, $concurrentWinnerPointer, $concurrentWinnerData) {
	global $sessionRows, $sessionDataRows, $time;
	if($sid !== $concurrentCasSid) return;
	$sessionDataRows[$concurrentWinnerPointer] = array('sid'=>$concurrentWinnerPointer, 'data'=>$concurrentWinnerData, 'last_date'=>$time);
	$sessionRows[$sid]['data'] = $concurrentWinnerPointer;
	$sessionRows[$sid]['bigdata'] = 1;
};
sess_write($concurrentCasSid, TRUE) === FALSE
	|| fail('A stale large Session writer must lose the observed-pointer CAS.');
$concurrentExpectedRows = $concurrentRowsBefore;
$concurrentExpectedRows[$concurrentWinnerPointer] = array('sid'=>$concurrentWinnerPointer, 'data'=>$concurrentWinnerData, 'last_date'=>$time);
$sessionRows[$concurrentCasSid]['data'] === $concurrentWinnerPointer
	&& $sessionDataRows === $concurrentExpectedRows
	|| fail('A losing Session writer must preserve the concurrent winner and its legacy predecessor.');
session_abort();

$largeToSmallSid = test_sid('large-to-small');
prepare_write_fixture($largeToSmallSid, 1, $oldLargeData) || fail('Large-to-small Session fixture could not start.');
$_SESSION['payload'] = 'tiny';
sess_write($largeToSmallSid, TRUE)
	|| fail('A large-to-small Session write must publish inline data without deleting the old auxiliary inline.');
intval($sessionRows[$largeToSmallSid]['bigdata']) === 0
	&& strpos($sessionRows[$largeToSmallSid]['data'], 'tiny') !== FALSE
	&& isset($sessionDataRows[$largeToSmallSid])
	|| fail('Large-to-small publication must leave the old immutable auxiliary for deferred GC.');
session_abort();

$primaryFailureSid = test_sid('primary-read-failure');
prepare_write_fixture($primaryFailureSid, 0, 'payload|s:4:"safe";') || fail('Primary-read-failure Session fixture could not start.');
session_abort();
$_SERVER['db'] = new SessionPrimaryDb();
$primaryReadFailure = TRUE;
$primaryReadsBeforeFailure = $primaryReads;
session_id($primaryFailureSid);
sess_start() === FALSE
	|| fail('A supported primary Session read failure must fail closed instead of using a replica row.');
$primaryReads === $primaryReadsBeforeFailure + 1 && $g_session === array() && intval($sessionRows[$primaryFailureSid]['bigdata']) === 0
	|| fail('Primary Session read failure fell back, mutated state, or did not clear the request Session.');
$primaryReadFailure = FALSE;
$_SERVER['db'] = new SessionPrimaryDb();
$_SESSION = array();

session_id($oldSid);
sess_start();
isset($_SESSION['csrf_token']) && $_SESSION['csrf_token'] === 'keep-token'
	|| fail('Session handler must restore existing session data before rotation.');
$oldSessionSnapshot = $g_session;
$_SESSION['uid'] = 42;
$_SESSION['fid'] = 5;

sess_regenerate_id() || fail('Active session rotation must succeed.');
$newSid = session_id();
($newSid !== '' && $newSid !== $oldSid && $sid === $newSid)
	|| fail('Session rotation must produce and expose a new session ID.');
isset($_SESSION['csrf_token']) && $_SESSION['csrf_token'] === 'keep-token'
	|| fail('Session rotation must preserve in-memory session data.');
isset($sessionRows[$oldSid]) && intval($sessionRows[$oldSid]['uid']) === 0 && intval($sessionRows[$oldSid]['fid']) === 0 && intval($sessionRows[$oldSid]['bigdata']) === 2 && !isset($sessionDataRows[$oldSid])
	|| fail('Session rotation must leave the previous ID as an anonymous tombstone.');
isset($sessionRows[$newSid])
	|| fail('Session rotation must create a new database session row.');

session_write_close();
isset($sessionRows[$newSid]) && intval($sessionRows[$newSid]['uid']) === 42 && intval($sessionRows[$newSid]['fid']) === 5
	|| fail('Rotated session must persist uid and fid to the new row.');
strpos($sessionRows[$newSid]['data'], 'csrf_token|s:10:"keep-token";') !== FALSE
	|| fail('Rotated session must persist existing session data to the new row.');
$_SESSION = array('uid'=>7, 'fid'=>3);
$g_session = $oldSessionSnapshot;
sess_write($oldSid, '');
intval($sessionRows[$oldSid]['uid']) === 0 && intval($sessionRows[$oldSid]['fid']) === 0 && intval($sessionRows[$oldSid]['bigdata']) === 2
	|| fail('A stale request must not recreate or restore a successfully rotated session ID.');
$_SESSION = array();
$g_session = array();
session_id($oldSid);
sess_start() === FALSE
	|| fail('A tombstoned previous session ID must not start a replacement session.');
isset($sessionRows[$oldSid]) && intval($sessionRows[$oldSid]['bigdata']) === 2
	|| fail('A tombstoned previous session ID must remain unavailable to stale clients.');
$revokedCookieCleared = FALSE;
foreach($cookieWrites as $cookie) {
	if($cookie[0] == 'bbs_sid' && $cookie[1] === '' && $cookie[2] < $time && $cookie[3] == '/') $revokedCookieCleared = TRUE;
}
$revokedCookieCleared || fail('A tombstoned session ID must expire the client session cookie.');

$destroyFailureSid = test_sid('destroy-failure');
$sessionRows[$destroyFailureSid] = array(
	'sid'=>$destroyFailureSid,
	'uid'=>7,
	'fid'=>3,
	'url'=>'old-route',
	'last_date'=>$time,
	'data'=>'',
	'ip'=>$longip,
	'useragent'=>'session-rotation-test',
	'bigdata'=>1,
);
$sessionDataRows[$destroyFailureSid] = array('sid'=>$destroyFailureSid, 'data'=>'csrf_token|s:10:"keep-token";', 'last_date'=>$time);
session_id($destroyFailureSid);
sess_start();
$destroyFailureSnapshot = $g_session;
$deleteFailures['session'] = TRUE;
sess_regenerate_id()
	|| fail('Session rotation must revoke the previous session when its row cannot be deleted.');
isset($sessionRows[$destroyFailureSid]) && intval($sessionRows[$destroyFailureSid]['uid']) === 0 && intval($sessionRows[$destroyFailureSid]['fid']) === 0 && intval($sessionRows[$destroyFailureSid]['bigdata']) === 2 && !isset($sessionDataRows[$destroyFailureSid])
	|| fail('Undeletable previous session rows must become anonymous tombstones.');
$deleteFailures = array();
session_write_close();
$_SESSION = array('uid'=>7, 'fid'=>3);
$g_session = $destroyFailureSnapshot;
sess_write($destroyFailureSid, '');
intval($sessionRows[$destroyFailureSid]['uid']) === 0 && intval($sessionRows[$destroyFailureSid]['fid']) === 0 && intval($sessionRows[$destroyFailureSid]['bigdata']) === 2
	|| fail('A stale request must not restore an anonymous tombstone.');
$_SESSION = array();
$g_session = array();
session_id($destroyFailureSid);
sess_start() === FALSE
	|| fail('An undeletable previous session ID must not start a replacement session.');

$downgradeFailureSid = test_sid('downgrade-failure');
$sessionRows[$downgradeFailureSid] = array(
	'sid'=>$downgradeFailureSid,
	'uid'=>7,
	'fid'=>3,
	'url'=>'old-route',
	'last_date'=>$time,
	'data'=>'',
	'ip'=>$longip,
	'useragent'=>'session-rotation-test',
	'bigdata'=>0,
);
session_id($downgradeFailureSid);
sess_start();
$deleteFailures['session'] = TRUE;
$updateFailures['session'] = TRUE;
sess_regenerate_id() === FALSE
	|| fail('Session rotation must fail when neither deletion nor anonymous downgrade succeeds.');
(session_status() === PHP_SESSION_NONE && $sid === '' && empty($_SESSION) && $g_session === array())
	|| fail('Failed anonymous downgrade must not leave an active in-memory session.');
$deleteFailures = array();
$updateFailures = array();

$dataDestroyFailureSid = test_sid('data-destroy-failure');
$sessionRows[$dataDestroyFailureSid] = array(
	'sid'=>$dataDestroyFailureSid,
	'uid'=>7,
	'fid'=>3,
	'url'=>'old-route',
	'last_date'=>$time,
	'data'=>'',
	'ip'=>$longip,
	'useragent'=>'session-rotation-test',
	'bigdata'=>1,
);
$sessionDataRows[$dataDestroyFailureSid] = array('sid'=>$dataDestroyFailureSid, 'data'=>'csrf_token|s:10:"keep-token";', 'last_date'=>$time);
session_id($dataDestroyFailureSid);
sess_start();
$deleteFailures['session_data'] = TRUE;
sess_regenerate_id() === FALSE
	|| fail('Session rotation must fail when the previous session data cannot be deleted.');
isset($sessionRows[$dataDestroyFailureSid]) && intval($sessionRows[$dataDestroyFailureSid]['uid']) === 0 && intval($sessionRows[$dataDestroyFailureSid]['bigdata']) === 2 && isset($sessionDataRows[$dataDestroyFailureSid])
	|| fail('Failed session-data deletion must leave the old authenticated session tombstoned.');
(session_status() === PHP_SESSION_NONE && $sid === '' && empty($_SESSION) && $g_session === array())
	|| fail('Failed session-data deletion must discard the in-memory session.');
$deleteFailures = array();
session_id($dataDestroyFailureSid);
sess_start() === FALSE
	|| fail('A tombstoned session ID with orphaned data must not start a replacement session.');
db_delete('session', array('sid'=>$dataDestroyFailureSid));
db_delete('session_data', array('sid'=>$dataDestroyFailureSid));

$insertFailureSid = test_sid('insert-failure');
$sessionRows[$insertFailureSid] = array(
	'sid'=>$insertFailureSid,
	'uid'=>0,
	'fid'=>0,
	'url'=>'old-route',
	'last_date'=>$time,
	'data'=>'',
	'ip'=>$longip,
	'useragent'=>'session-rotation-test',
	'bigdata'=>0,
);
session_id($insertFailureSid);
sess_start();
$sessionInsertFailure = TRUE;
sess_regenerate_id() === FALSE
	|| fail('Session rotation must fail when the replacement session row cannot be created.');
$sessionInsertFailure = FALSE;
isset($sessionRows[$insertFailureSid]) && intval($sessionRows[$insertFailureSid]['uid']) === 0 && intval($sessionRows[$insertFailureSid]['bigdata']) === 2 && session_status() === PHP_SESSION_NONE && $sid === '' && empty($_SESSION) && $g_session === array()
	|| fail('Failed replacement session creation must leave no authenticated session active.');

$freshTombstoneSid = test_sid('fresh-tombstone');
$boundaryTombstoneSid = test_sid('boundary-tombstone');
$expiredTombstoneSid = test_sid('expired-tombstone');
$expiredActiveSid = test_sid('expired-active');
foreach(array(
	$freshTombstoneSid=>$time - 86399,
	$boundaryTombstoneSid=>$time - 86400,
	$expiredTombstoneSid=>$time - 86401,
) as $gcSid=>$lastDate) {
	$sessionRows[$gcSid] = array('sid'=>$gcSid, 'uid'=>0, 'fid'=>0, 'url'=>'', 'last_date'=>$lastDate, 'data'=>'', 'ip'=>0, 'useragent'=>'', 'bigdata'=>2);
}
$sessionRows[$expiredActiveSid] = array('sid'=>$expiredActiveSid, 'uid'=>0, 'fid'=>0, 'url'=>'', 'last_date'=>$time - $conf['online_hold_time'] - 1, 'data'=>'', 'ip'=>0, 'useragent'=>'', 'bigdata'=>0);
$expiredDataSid = test_sid('expired-data-after-main-failure');
$sessionRows[$expiredDataSid] = array('sid'=>$expiredDataSid, 'uid'=>0, 'fid'=>0, 'url'=>'', 'last_date'=>$time - $conf['online_hold_time'] - 1, 'data'=>'', 'ip'=>0, 'useragent'=>'', 'bigdata'=>1);
$sessionDataRows[$expiredDataSid] = array('sid'=>$expiredDataSid, 'data'=>'expired-data', 'last_date'=>$time - $conf['online_hold_time'] - 1);
$liveImmutableMainSid = test_sid('live-immutable-main');
$liveImmutableDataSid = str_repeat('c', 32);
$sessionRows[$liveImmutableMainSid] = array('sid'=>$liveImmutableMainSid, 'uid'=>7, 'fid'=>0, 'url'=>'', 'last_date'=>$time, 'data'=>$liveImmutableDataSid, 'ip'=>0, 'useragent'=>'', 'bigdata'=>1);
$sessionDataRows[$liveImmutableDataSid] = array('sid'=>$liveImmutableDataSid, 'data'=>'live-immutable-data', 'last_date'=>$time - $conf['online_hold_time'] - 100);
$liveLegacySid = test_sid('live-legacy-main');
$sessionRows[$liveLegacySid] = array('sid'=>$liveLegacySid, 'uid'=>8, 'fid'=>0, 'url'=>'', 'last_date'=>$time, 'data'=>'', 'ip'=>0, 'useragent'=>'', 'bigdata'=>1);
$sessionDataRows[$liveLegacySid] = array('sid'=>$liveLegacySid, 'data'=>'live-legacy-data', 'last_date'=>$time - $conf['online_hold_time'] - 100);
$orphanDataSid = str_repeat('d', 32);
$sessionDataRows[$orphanDataSid] = array('sid'=>$orphanDataSid, 'data'=>'orphan-data', 'last_date'=>$time - $conf['online_hold_time'] - 100);
$concurrentReferenceDataSid = str_repeat('e', 32);
$sessionDataRows[$concurrentReferenceDataSid] = array('sid'=>$concurrentReferenceDataSid, 'data'=>'racing-data', 'last_date'=>$time - $conf['online_hold_time'] - 100);

$gcAtomicAttemptsBeforeThrow = $gcAtomicAttempts;
$gcAtomicBeforeThrow = count($gcAtomicCalls);
$generationWritesBeforeAtomicThrow = $onlineGenerationWrites;
$dataRowsBeforeAtomicThrow = $sessionDataRows;
$gcAtomicThrow = TRUE;
$gcAtomicThrowEscaped = FALSE;
$gcAtomicThrowResult = NULL;
try {
	$gcAtomicThrowResult = sess_data_gc_orphans($time - $conf['online_hold_time']);
} catch(Throwable $e) {
	$gcAtomicThrowEscaped = TRUE;
}
$gcAtomicThrow = FALSE;
!$gcAtomicThrowEscaped && $gcAtomicThrowResult === FALSE
	&& $gcAtomicAttempts === $gcAtomicAttemptsBeforeThrow + 1
	&& $onlineGenerationWrites === $generationWritesBeforeAtomicThrow
	&& count($gcAtomicCalls) === $gcAtomicBeforeThrow && $sessionDataRows === $dataRowsBeforeAtomicThrow
	|| fail('Session auxiliary cleanup must contain atomic driver exceptions and retain all candidate data.');

$primaryDb = $_SERVER['db'];
$gcAtomicAttemptsBeforeNoExec = $gcAtomicAttempts;
$gcAtomicBeforeNoExec = count($gcAtomicCalls);
$generationWritesBeforeNoExec = $onlineGenerationWrites;
$dataRowsBeforeNoExec = $sessionDataRows;
$_SERVER['db'] = new SessionNoExecDb();
sess_data_gc_orphans($time - $conf['online_hold_time']) === FALSE
	&& $gcAtomicAttempts === $gcAtomicAttemptsBeforeNoExec
	&& $onlineGenerationWrites === $generationWritesBeforeNoExec
	&& count($gcAtomicCalls) === $gcAtomicBeforeNoExec && $sessionDataRows === $dataRowsBeforeNoExec
	|| fail('Session auxiliary cleanup must reject drivers without atomic exec capability before deleting data.');
$_SERVER['db'] = $primaryDb;

foreach(array(
	'missing exec'=>new SessionNoExecDb(),
	'non-callable exec'=>new SessionPrivateExecDb(),
) as $unsupportedLabel=>$unsupportedDb) {
	$sessionRowsBeforeUnsupportedGc = $sessionRows;
	$dataRowsBeforeUnsupportedGc = $sessionDataRows;
	$sessionDeleteCallsBeforeUnsupportedGc = $sessionDeleteCalls;
	$gcAtomicAttemptsBeforeUnsupportedGc = $gcAtomicAttempts;
	$generationWritesBeforeUnsupportedGc = $onlineGenerationWrites;
	$g_online_member_snapshot = array('marker'=>'unsupported-gc-preflight');
	$snapshotBeforeUnsupportedGc = $g_online_member_snapshot;
	$_SERVER['db'] = $unsupportedDb;
	$unsupportedGcResult = sess_gc($conf['online_hold_time']);
	$_SERVER['db'] = $primaryDb;
	$unsupportedGcResult === FALSE
		&& $sessionRows === $sessionRowsBeforeUnsupportedGc && $sessionDataRows === $dataRowsBeforeUnsupportedGc
		&& $sessionDeleteCalls === $sessionDeleteCallsBeforeUnsupportedGc
		&& $gcAtomicAttempts === $gcAtomicAttemptsBeforeUnsupportedGc
		&& $onlineGenerationWrites === $generationWritesBeforeUnsupportedGc
		&& $g_online_member_snapshot === $snapshotBeforeUnsupportedGc
		|| fail('Session GC must reject '.$unsupportedLabel.' before any main delete or generation invalidation.');
}

$g_online_member_snapshot = array('marker'=>'gc-main-delete-failure');
$gcAtomicBeforeMainFailure = count($gcAtomicCalls);
$generationWritesBeforeMainFailure = $onlineGenerationWrites;
$deleteFailures['session'] = TRUE;
sess_gc($conf['online_hold_time']) === FALSE
	&& isset($sessionRows[$expiredDataSid], $sessionDataRows[$expiredDataSid])
	&& $g_online_member_snapshot === NULL
	&& $onlineGenerationWrites === $generationWritesBeforeMainFailure + 1
	&& count($gcAtomicCalls) === $gcAtomicBeforeMainFailure
	|| fail('Session GC must invalidate generation and stop before auxiliary deletion when expired main-row deletion fails.');
unset($deleteFailures['session']);

$sessionDeleteCalls = 0;
$deleteFailureOnSessionCall = 2;
$g_online_member_snapshot = array('marker'=>'gc-tombstone-delete-failure');
$gcAtomicBeforeTombstoneFailure = count($gcAtomicCalls);
$generationWritesBeforeTombstoneFailure = $onlineGenerationWrites;
sess_gc($conf['online_hold_time']) === FALSE
	&& isset($sessionDataRows[$expiredDataSid], $sessionDataRows[$orphanDataSid])
	&& $g_online_member_snapshot === NULL
	&& $onlineGenerationWrites === $generationWritesBeforeTombstoneFailure + 1
	&& count($gcAtomicCalls) === $gcAtomicBeforeTombstoneFailure
	|| fail('Session GC must invalidate generation and stop before auxiliary deletion when tombstone cleanup fails.');
$deleteFailureOnSessionCall = 0;

$sessionDeleteCalls = 0;
$gcAtomicFailure = TRUE;
$g_online_member_snapshot = array('marker'=>'gc-atomic-cleanup-failure');
$generationWritesBeforeAtomicFailure = $onlineGenerationWrites;
sess_gc($conf['online_hold_time']) === FALSE
	&& isset($sessionDataRows[$expiredDataSid], $sessionDataRows[$orphanDataSid])
	&& $g_online_member_snapshot === NULL
	&& $onlineGenerationWrites === $generationWritesBeforeAtomicFailure + 1
	|| fail('Session GC must invalidate generation and propagate an atomic orphan-cleanup failure without deleting auxiliary data.');
$gcAtomicFailure = FALSE;

$sessionDeleteCalls = 0;
$gcAtomicThrow = TRUE;
$dataRowsBeforeGcAtomicThrow = $sessionDataRows;
$generationWritesBeforeGcAtomicThrow = $onlineGenerationWrites;
$g_online_member_snapshot = array('marker'=>'gc-atomic-cleanup-throw');
$gcAtomicThrowEscaped = FALSE;
$gcAtomicThrowResult = NULL;
try {
	$gcAtomicThrowResult = sess_gc($conf['online_hold_time']);
} catch(Throwable $e) {
	$gcAtomicThrowEscaped = TRUE;
}
$gcAtomicThrow = FALSE;
!$gcAtomicThrowEscaped && $gcAtomicThrowResult === FALSE
	&& $sessionDataRows === $dataRowsBeforeGcAtomicThrow && $g_online_member_snapshot === NULL
	&& $onlineGenerationWrites === $generationWritesBeforeGcAtomicThrow + 1
	|| fail('Session GC must contain atomic cleanup exceptions, retain auxiliary data, and invalidate generation.');

$sessionDeleteCalls = 0;
$deleteFailures['session'] = TRUE;
$onlineGenerationThrow = TRUE;
$generationWritesBeforeGenerationThrow = $onlineGenerationWrites;
$g_online_member_snapshot = array('marker'=>'gc-generation-write-throw');
$generationThrowEscaped = FALSE;
$generationThrowResult = NULL;
try {
	$generationThrowResult = sess_gc($conf['online_hold_time']);
} catch(Throwable $e) {
	$generationThrowEscaped = TRUE;
}
$onlineGenerationThrow = FALSE;
unset($deleteFailures['session']);
!$generationThrowEscaped && $generationThrowResult === FALSE
	&& $g_online_member_snapshot === NULL
	&& $onlineGenerationWrites === $generationWritesBeforeGenerationThrow + 1
	|| fail('Session GC must preserve its failure result when generation invalidation throws.');

$beforeGcAtomicDelete = function() use ($concurrentReferenceDataSid) {
	global $sessionRows, $time;
	$raceMainSid = test_sid('gc-concurrent-reference');
	$sessionRows[$raceMainSid] = array('sid'=>$raceMainSid, 'uid'=>9, 'fid'=>0, 'url'=>'', 'last_date'=>$time, 'data'=>$concurrentReferenceDataSid, 'ip'=>0, 'useragent'=>'', 'bigdata'=>1);
};
$sessionDeleteCalls = 0;
$generationWritesBeforeSuccessfulGc = $onlineGenerationWrites;
sess_gc($conf['online_hold_time'])
	|| fail('Session GC must report a successful ordered cleanup.');
$onlineGenerationWrites === $generationWritesBeforeSuccessfulGc + 1
	|| fail('Successful Session GC must advance the shared online generation exactly once.');
isset($sessionRows[$freshTombstoneSid]) && !isset($sessionRows[$boundaryTombstoneSid]) && !isset($sessionRows[$expiredTombstoneSid]) && !isset($sessionRows[$expiredActiveSid])
	|| fail('Session GC must retain tombstones for 24 hours while expiring normal sessions on schedule.');
!isset($sessionRows[$expiredDataSid], $sessionDataRows[$expiredDataSid])
	|| fail('Successful Session GC must remove the expired main row and its obsolete auxiliary generation.');
isset($sessionDataRows[$liveImmutableDataSid], $sessionDataRows[$liveLegacySid], $sessionDataRows[$concurrentReferenceDataSid])
	&& !isset($sessionDataRows[$orphanDataSid])
	|| fail('Reference-aware Session GC deleted a live immutable/legacy/concurrent pointer or retained a true orphan.');

$_SERVER['cache'] = TRUE;
$onlineGenerationFailure = TRUE;
$generationWritesBeforeGenerationFailure = $onlineGenerationWrites;
$g_online_member_snapshot = array('marker'=>'gc-generation-write-failure');
sess_gc($conf['online_hold_time']) === FALSE
	&& $g_online_member_snapshot === NULL
	&& $onlineGenerationWrites === $generationWritesBeforeGenerationFailure + 1
	|| fail('Session GC must report configured shared-generation publication failure.');
$onlineGenerationFailure = FALSE;
unset($_SERVER['cache']);

echo "Session rotation safety checks passed\n";
