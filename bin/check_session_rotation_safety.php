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

$tokenLogin = source_section($indexSource, '$uid = intval(_SESSION', '$user = user_read');
$tokenLoginPattern = <<<'REGEX'
#if\s*\(\s*empty\(\$uid\)\s*\)\s*\{\s*
\$uid\s*=\s*user_token_get\(\)\s*;\s*
if\s*\(\s*\$uid\s*\)\s*\{\s*
if\s*\(\s*sess_regenerate_id\(\)\s*\)\s*\{\s*
\$_SESSION\['uid'\]\s*=\s*\$uid\s*;\s*
\}\s*else\s*\{\s*
user_token_clear\(\)\s*;\s*
\$uid\s*=\s*0\s*;\s*
\}\s*
\}\s*
\}#xs
REGEX;
preg_match($tokenLoginPattern, $tokenLogin) === 1
	|| fail('Persistent token login must rotate before storing uid and fail closed when rotation fails.');

$passwordLogin = source_section($userRoute, "} elseif(\$action == 'login')", "} elseif(\$action == 'create')");
$passwordRotatePos = strpos($passwordLogin, 'sess_regenerate_id()');
$passwordUidPos = strpos($passwordLogin, "\$_SESSION['uid'] = \$uid;");
($passwordRotatePos !== FALSE && $passwordUidPos !== FALSE && $passwordRotatePos < $passwordUidPos)
	|| fail('Password login must rotate the session before storing uid.');
strpos($passwordLogin, "sess_regenerate_id() OR message(-1, 'Unable to renew session. Please try again.');") !== FALSE
	|| fail('Password login must fail rather than authenticate when session rotation fails.');
$passwordUpgradePos = strpos($passwordLogin, 'user_password_needs_upgrade($_user)');
$passwordUpdatePos = strpos($passwordLogin, "user_update(\$_user['uid']");
$passwordRateClearPos = strpos($passwordLogin, 'user_login_rate_clear($email);');
($passwordRotatePos !== FALSE && $passwordUpgradePos !== FALSE && $passwordUpdatePos !== FALSE && $passwordRateClearPos !== FALSE
	&& $passwordRotatePos < $passwordUpgradePos && $passwordRotatePos < $passwordUpdatePos && $passwordRotatePos < $passwordRateClearPos)
	|| fail('Password login must rotate before password upgrades, login counters, and rate-limit success side effects.');
strpos($workflow, 'php bin/check_session_rotation_safety.php') !== FALSE
	|| fail('CI must run the session rotation safety guard.');

$sessionRows = array();
$sessionDataRows = array();
$cookieWrites = array();
$deleteFailures = array();
$updateFailures = array();
$sessionInsertFailure = FALSE;
$sessionInsertRace = array();

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

function array_diff_value($new, $old) {
	return $new;
}

function db_find_one($table, $cond) {
	global $sessionRows, $sessionDataRows;
	$sid = isset($cond['sid']) ? $cond['sid'] : '';
	if($table == 'session') return isset($sessionRows[$sid]) ? $sessionRows[$sid] : array();
	if($table == 'session_data') return isset($sessionDataRows[$sid]) ? $sessionDataRows[$sid] : array();
	return array();
}

function db_insert($table, $row) {
	global $sessionRows, $sessionDataRows, $sessionInsertFailure, $sessionInsertRace;
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
		if(strlen($row['sid']) > 32 || isset($sessionDataRows[$row['sid']])) return FALSE;
		$sessionDataRows[$row['sid']] = $row + array('last_date'=>0, 'data'=>'');
	}
	return TRUE;
}

function db_update($table, $cond, $update) {
	global $sessionRows, $sessionDataRows, $updateFailures;
	if(!empty($updateFailures[$table])) return FALSE;
	$sid = isset($cond['sid']) ? $cond['sid'] : '';
	if($table == 'session' && isset($sessionRows[$sid])) {
		if(isset($cond['last_date']['>']) && intval($sessionRows[$sid]['last_date']) <= intval($cond['last_date']['>'])) return 0;
		if(isset($cond['bigdata']['<=']) && intval($sessionRows[$sid]['bigdata']) > intval($cond['bigdata']['<='])) return 0;
		$sessionRows[$sid] = array_merge($sessionRows[$sid], $update);
		return TRUE;
	}
	if($table == 'session_data' && isset($sessionDataRows[$sid])) {
		$sessionDataRows[$sid] = array_merge($sessionDataRows[$sid], $update);
		return TRUE;
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
	global $sessionRows, $sessionDataRows, $deleteFailures;
	if(!empty($deleteFailures[$table])) return FALSE;
	if($table == 'session') {
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
sess_read($missingBigDataSid) === '' && isset($g_session['data']) && $g_session['data'] === ''
	|| fail('A missing session_data row must produce an empty session string without a PHP warning.');

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
sess_write($delayedBigDataSid, TRUE);
isset($sessionDataRows[$delayedBigDataSid]) && intval($sessionDataRows[$delayedBigDataSid]['last_date']) === $time
	|| fail('A delayed small-to-big session write must timestamp its new session_data row.');
sess_gc($conf['online_hold_time']);
isset($sessionDataRows[$delayedBigDataSid])
	|| fail('Session GC must not immediately delete a newly created delayed session_data row.');
session_write_close();
$conf['session_delay_update'] = 0;
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
sess_gc($conf['online_hold_time']);
isset($sessionRows[$freshTombstoneSid]) && !isset($sessionRows[$boundaryTombstoneSid]) && !isset($sessionRows[$expiredTombstoneSid]) && !isset($sessionRows[$expiredActiveSid])
	|| fail('Session GC must retain tombstones for 24 hours while expiring normal sessions on schedule.');

echo "Session rotation safety checks passed\n";
