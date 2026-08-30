<?php

$root = dirname(__DIR__).'/';
defined('APP_PATH') OR define('APP_PATH', $root);

function fail($message) {
	fwrite(STDERR, "FAIL: $message\n");
	exit(1);
}

class OnlineMemberCompatDb {
	public $tablepre = 'bbs_';
	public function sql_find_one_master($sql) { return NULL; }
	public function sql_find_master($sql, $key = NULL) { return array(); }
	public function find_one_master($table, $condition = array(), $sort = array(), $columns = array()) { return NULL; }
	public function exec($sql) {}
}

class OnlineMemberUnsupportedDb {
	public $tablepre = 'bbs_';
}

$time = 10000;
$conf = array('online_hold_time'=>3600, 'session_delay_update'=>0);
$longip = 2130706433;
$cache = array();
$queries = array();
$primaryQueries = 0;
$primaryFindOneQueries = 0;
$replicaFindOneQueries = 0;
$fallbackFindQueries = 0;
$sessionRows = array();
$sessionDataRows = array();
$invalidateDuringRowsQuery = FALSE;
$failMasterCountQuery = FALSE;
$failMasterRowsQuery = FALSE;
$failMasterFindOne = FALSE;
$failMasterFindOneOnCall = 0;
$tombstoneRaceSid = '';
$tombstoneRacePublishedGeneration = NULL;
$_SERVER['db'] = new OnlineMemberCompatDb();
$_SERVER['cache'] = TRUE;

// More than 500 people are online. The newest 600 Session rows all belong to uid=1,
// so a fake DB which hands callers an already-correct list would hide the original
// "LIMIT 500 Sessions, then de-duplicate" defect.
for($i = 0; $i < 600; $i++) {
	$sessionRows[] = array(
		'sid'=>sprintf('dup-%04d', $i),
		'uid'=>1,
		'fid'=>0,
		'url'=>'thread-1.htm',
		'last_date'=>$time - intval($i / 100),
		'data'=>'',
		'ip'=>$longip,
		'useragent'=>'compat-test',
		'bigdata'=>0,
	);
}
for($uid = 2; $uid <= 619; $uid++) {
	$sessionRows[] = array(
		'sid'=>'member-'.$uid,
		'uid'=>$uid,
		'fid'=>0,
		'url'=>'index.htm',
		'last_date'=>9000 + ($uid % 500),
		'data'=>'',
		'ip'=>$longip,
		'useragent'=>'compat-test',
		'bigdata'=>0,
	);
}
// Exact boundary: active while time=10000, expired when time advances to 10001.
$sessionRows[] = array('sid'=>'boundary', 'uid'=>620, 'fid'=>0, 'url'=>'', 'last_date'=>6400, 'data'=>'', 'ip'=>$longip, 'useragent'=>'', 'bigdata'=>0);
$sessionRows[] = array('sid'=>'expired', 'uid'=>900, 'fid'=>0, 'url'=>'', 'last_date'=>6399, 'data'=>'', 'ip'=>$longip, 'useragent'=>'', 'bigdata'=>0);
$sessionRows[] = array('sid'=>'guest', 'uid'=>0, 'fid'=>0, 'url'=>'', 'last_date'=>$time, 'data'=>'', 'ip'=>$longip, 'useragent'=>'', 'bigdata'=>0);
$sessionRows[] = array('sid'=>'revoked', 'uid'=>901, 'fid'=>0, 'url'=>'', 'last_date'=>$time, 'data'=>'', 'ip'=>$longip, 'useragent'=>'', 'bigdata'=>2);

function cache_get($key) {
	global $cache;
	return array_key_exists($key, $cache) ? $cache[$key] : NULL;
}

function cache_get_primary($key) {
	return cache_get($key);
}

function cache_set($key, $value, $ttl = 0) {
	global $cache;
	$cache[$key] = $value;
	return TRUE;
}

function cache_delete($key) {
	global $cache;
	$exists = array_key_exists($key, $cache);
	unset($cache[$key]);
	return $exists;
}

function online_test_since_from_sql($sql) {
	if(!preg_match('/`last_date`\s*>=\s*(\d+)/', $sql, $matches)) {
		fail('Online SQL omitted the last_date activity window.');
	}
	return intval($matches[1]);
}

function online_test_active_rows($since) {
	global $sessionRows;
	$rows = array();
	foreach($sessionRows as $row) {
		if(intval($row['uid']) <= 0 || intval($row['bigdata']) > 1 || intval($row['last_date']) < $since) continue;
		$rows[] = $row;
	}
	return $rows;
}

function online_test_latest_rows($since) {
	$latest = array();
	foreach(online_test_active_rows($since) as $row) {
		$uid = intval($row['uid']);
		if(!isset($latest[$uid])
			|| intval($row['last_date']) > intval($latest[$uid]['last_date'])
			|| (intval($row['last_date']) === intval($latest[$uid]['last_date']) && strcmp($row['sid'], $latest[$uid]['sid']) > 0)
		) {
			$latest[$uid] = $row;
		}
	}
	$latest = array_values($latest);
	usort($latest, function($a, $b) {
		if(intval($a['last_date']) !== intval($b['last_date'])) return intval($a['last_date']) > intval($b['last_date']) ? -1 : 1;
		if(intval($a['uid']) !== intval($b['uid'])) return intval($a['uid']) < intval($b['uid']) ? -1 : 1;
		return strcmp($b['sid'], $a['sid']);
	});
	return $latest;
}

function db_sql_find_one($sql, $db = NULL) {
	global $queries;
	$queries[] = array('one', $sql);
	$since = online_test_since_from_sql($sql);

	// Deliberately behave like a naive row counter unless the submitted SQL itself
	// groups or distincts UIDs. This keeps the fixture from masking the defect.
	$distinct = stripos($sql, 'GROUP BY `uid`') !== FALSE || stripos($sql, 'COUNT(DISTINCT') !== FALSE;
	$rows = $distinct ? online_test_latest_rows($since) : online_test_active_rows($since);
	$min = NULL;
	foreach($rows as $row) {
		$lastDate = intval($row['last_date']);
		if($min === NULL || $lastDate < $min) $min = $lastDate;
	}
	return array('num'=>count($rows), 'min_last_date'=>$min);
}

function db_sql_find_one_master($sql, $db = NULL) {
	global $primaryQueries, $failMasterCountQuery;
	$primaryQueries++;
	if($failMasterCountQuery) return FALSE;
	return db_sql_find_one($sql, $db);
}

function db_sql_find($sql, $key = NULL, $db = NULL) {
	global $queries, $invalidateDuringRowsQuery;
	$queries[] = array('all', $sql);
	$since = online_test_since_from_sql($sql);
	$perUid = stripos($sql, 'NOT EXISTS') !== FALSE || stripos($sql, 'GROUP BY `uid`') !== FALSE;
	$rows = $perUid ? online_test_latest_rows($since) : online_test_active_rows($since);
	if(!$perUid) {
		usort($rows, function($a, $b) { return intval($b['last_date']) - intval($a['last_date']); });
	}
	$limit = preg_match('/LIMIT\s+(\d+)/i', $sql, $matches) ? intval($matches[1]) : count($rows);
	$rows = array_slice($rows, 0, $limit);

	if($invalidateDuringRowsQuery) {
		$invalidateDuringRowsQuery = FALSE;
		online_member_snapshot_invalidate();
	}
	return $rows;
}

function db_sql_find_master($sql, $key = NULL, $db = NULL) {
	global $primaryQueries, $failMasterRowsQuery;
	$primaryQueries++;
	if($failMasterRowsQuery) return FALSE;
	return db_sql_find($sql, $key, $db);
}

function online_test_condition_matches($row, $condition) {
	foreach($condition as $field=>$expected) {
		$value = isset($row[$field]) ? $row[$field] : NULL;
		if(!is_array($expected)) {
			if((string)$value !== (string)$expected) return FALSE;
			continue;
		}
		foreach($expected as $operator=>$operand) {
			if($operator === '>' && !($value > $operand)) return FALSE;
			if($operator === '>=' && !($value >= $operand)) return FALSE;
			if($operator === '<' && !($value < $operand)) return FALSE;
			if($operator === '<=' && !($value <= $operand)) return FALSE;
		}
	}
	return TRUE;
}

function online_test_sort_rows($rows, $sort) {
	usort($rows, function($a, $b) use ($sort) {
		foreach($sort as $field=>$direction) {
			$comparison = is_numeric($a[$field]) && is_numeric($b[$field])
				? intval($a[$field]) <=> intval($b[$field])
				: strcmp((string)$a[$field], (string)$b[$field]);
			if($comparison !== 0) return intval($direction) === 1 ? $comparison : -$comparison;
		}
		return 0;
	});
	return $rows;
}

function db_find($table, $condition = array(), $sort = array(), $page = 1, $pagesize = 10, $key = '', $columns = array()) {
	global $sessionRows, $sessionDataRows, $fallbackFindQueries;
	$fallbackFindQueries++;
	$source = $table === 'session' ? $sessionRows : ($table === 'session_data' ? array_values($sessionDataRows) : array());
	$rows = array();
	foreach($source as $row) {
		if(online_test_condition_matches($row, $condition)) $rows[] = $row;
	}
	$rows = online_test_sort_rows($rows, $sort);
	return array_slice($rows, (max(1, intval($page)) - 1) * intval($pagesize), intval($pagesize));
}

function db_find_one($table, $condition = array()) {
	global $replicaFindOneQueries;
	$replicaFindOneQueries++;
	$rows = db_find($table, $condition, array(), 1, 1);
	return $rows ? $rows[0] : NULL;
}

function db_find_one_master($table, $condition = array(), $sort = array(), $columns = array(), $db = NULL) {
	global $primaryFindOneQueries, $failMasterFindOne, $failMasterFindOneOnCall, $sessionRows, $sessionDataRows;
	$primaryFindOneQueries++;
	if($failMasterFindOne || ($failMasterFindOneOnCall > 0 && $primaryFindOneQueries === $failMasterFindOneOnCall)) return FALSE;
	$source = $table === 'session' ? $sessionRows : ($table === 'session_data' ? array_values($sessionDataRows) : array());
	foreach($source as $row) {
		if(online_test_condition_matches($row, $condition)) return $row;
	}
	return NULL;
}

function online_test_session_by_sid($sid) {
	global $sessionRows;
	foreach($sessionRows as $row) if($row['sid'] === $sid) return $row;
	return NULL;
}

function db_insert($table, $row) {
	global $sessionRows, $sessionDataRows;
	if($table === 'session') {
		foreach($sessionRows as $existing) if($existing['sid'] === $row['sid']) return FALSE;
		$sessionRows[] = $row;
		return 1;
	}
	if($table === 'session_data') {
		$sessionDataRows[$row['sid']] = $row + array('data'=>'');
		return 1;
	}
	return FALSE;
}

function db_update($table, $condition, $update) {
	global $sessionRows, $sessionDataRows, $time, $tombstoneRaceSid, $tombstoneRacePublishedGeneration;
	if(!$update) return FALSE;
	$updated = 0;
	if($table === 'session') {
		$targetSid = isset($condition['sid']) && !is_array($condition['sid']) ? (string)$condition['sid'] : '';
		if($tombstoneRaceSid !== '' && $targetSid === $tombstoneRaceSid
			&& isset($update['bigdata']) && intval($update['bigdata']) === 2) {
			// A read the guest row. Before A writes its tombstone, B authenticates the same SID,
			// advances generation, and publishes a snapshot which contains the newly active UID.
			foreach($sessionRows as &$raceRow) {
				if($raceRow['sid'] !== $targetSid) continue;
				$raceRow['uid'] = 705;
				$raceRow['fid'] = 0;
				$raceRow['bigdata'] = 0;
				$raceRow['last_date'] = $time;
				break;
			}
			unset($raceRow);
			online_member_snapshot_invalidate();
			$raceSnapshot = online_member_snapshot();
			$raceUids = array_column($raceSnapshot['rows'], 'uid');
			in_array(705, $raceUids, TRUE) || fail('Tombstone race fixture did not publish the concurrent login snapshot.');
			$tombstoneRacePublishedGeneration = cache_get_primary('online_member_generation');
			$tombstoneRaceSid = '';
		}
		foreach($sessionRows as &$row) {
			if(!online_test_condition_matches($row, $condition)) continue;
			foreach($update as $field=>$value) $row[$field] = $value;
			$updated++;
		}
		unset($row);
		return $updated;
	}
	if($table === 'session_data') {
		foreach($sessionDataRows as &$row) {
			if(!online_test_condition_matches($row, $condition)) continue;
			foreach($update as $field=>$value) $row[$field] = $value;
			$updated++;
		}
		unset($row);
		return $updated;
	}
	return FALSE;
}

function db_delete($table, $condition) {
	global $sessionRows, $sessionDataRows;
	$deleted = 0;
	if($table === 'session') {
		$kept = array();
		foreach($sessionRows as $row) {
			if(online_test_condition_matches($row, $condition)) {
				$deleted++;
			} else {
				$kept[] = $row;
			}
		}
		$sessionRows = $kept;
		return $deleted;
	}
	if($table === 'session_data') {
		foreach($sessionDataRows as $sid=>$row) {
			if(!online_test_condition_matches($row, $condition)) continue;
			unset($sessionDataRows[$sid]);
			$deleted++;
		}
		return $deleted;
	}
	return FALSE;
}

function db_exec($sql, $db = NULL) {
	global $sessionRows, $sessionDataRows;
	strpos($sql, 'session_data') !== FALSE && strpos($sql, 'NOT EXISTS') !== FALSE
		OR fail('Online-member GC did not use reference-aware auxiliary cleanup.');
	$expiry = NULL;
	$candidate = NULL;
	if(preg_match('~WHERE `last_date` < (-?[0-9]+) AND NOT EXISTS~D', $sql, $match)) {
		$expiry = intval($match[1]);
	} elseif(preg_match("~WHERE `sid`='([a-f0-9]{32})' AND NOT EXISTS~D", $sql, $match)) {
		$candidate = $match[1];
	} else {
		return FALSE;
	}
	$deleted = 0;
	foreach($sessionDataRows as $dataSid=>$dataRow) {
		if($candidate !== NULL && (string)$dataSid !== $candidate) continue;
		if($expiry !== NULL && intval($dataRow['last_date']) >= $expiry) continue;
		$referenced = FALSE;
		foreach($sessionRows as $sessionRow) {
			if(intval($sessionRow['bigdata']) !== 1) continue;
			$mainData = isset($sessionRow['data']) ? (string)$sessionRow['data'] : '';
			if(($mainData === '' && (string)$sessionRow['sid'] === (string)$dataSid) || $mainData === (string)$dataSid) {
				$referenced = TRUE;
				break;
			}
		}
		if($referenced) continue;
		unset($sessionDataRows[$dataSid]);
		$deleted++;
	}
	return $deleted;
}

function array_diff_value($after, $before) {
	$diff = array();
	foreach($after as $key=>$value) {
		if(!array_key_exists($key, $before) || $before[$key] != $value) $diff[$key] = $value;
	}
	return $diff;
}

function array_value($array, $key, $default = NULL) {
	return isset($array[$key]) ? $array[$key] : $default;
}

function _SESSION($key, $default = NULL) {
	global $g_session;
	return isset($_SESSION[$key]) ? $_SESSION[$key] : (isset($g_session[$key]) ? $g_session[$key] : $default);
}

function _SERVER($key, $default = NULL) {
	return isset($_SERVER[$key]) ? $_SERVER[$key] : $default;
}

function user_read_cache($uid) {
	return array('uid'=>intval($uid), 'username'=>'member-'.intval($uid), 'gid'=>1);
}

function user_read_cache_batch($uids, $consumer = NULL) {
	$users = array();
	foreach((array)$uids as $index=>$uid) {
		$uid = intval($uid);
		if($uid <= 0) continue;
		$users[$uid] = user_read_cache($uid);
		is_callable($consumer) AND $consumer($uid, $users[$uid], $index);
	}
	return $users;
}

function user_count() { return 1000; }
function post_count() { return 3000; }
function thread_count() { return 1000; }
function forum__update($fid, $update) { return TRUE; }
function forum_list_cache_delete() { return TRUE; }
function attach_gc() { return TRUE; }
function queue_gc() { return TRUE; }

include $root.'model/session.func.php';
include $root.'model/runtime.func.php';
include $root.'model/cron.func.php';

$snapshot = online_member_snapshot();
intval($snapshot['count']) === 620 || fail('Snapshot count must be exact across duplicate Sessions and more than 500 people.');
count($snapshot['rows']) === 500 || fail('Snapshot must expose at most 500 people, not 500 Session rows before de-duplication.');
$uids = array();
foreach($snapshot['rows'] as $row) {
	$uid = intval($row['uid']);
	isset($uids[$uid]) && fail('Snapshot returned duplicate member UIDs.');
	$uids[$uid] = TRUE;
	$uid > 0 || fail('Snapshot returned a guest Session.');
	intval($row['bigdata']) <= 1 || fail('Snapshot returned a revoked Session.');
	intval($row['last_date']) >= 6400 || fail('Snapshot returned an expired Session.');
	isset($row['username'], $row['gid'], $row['ip_fmt'], $row['last_date_fmt'])
		|| fail('Snapshot rows must carry the legacy formatted list fields.');
}
count($queries) === 2 || fail('Initial snapshot must use one exact count query and one bounded display query.');
$primaryQueries === 2 || fail('Generation-bound snapshot queries must both use the primary database connection.');
stripos($queries[0][1], 'GROUP BY `uid`') !== FALSE || fail('Count query must derive one row per UID.');
stripos($queries[1][1], 'NOT EXISTS') !== FALSE || fail('Display query must choose one latest Session per UID before LIMIT.');
stripos($queries[1][1], 'LIMIT 500') !== FALSE || fail('Display query must bound presentation rows to 500 people.');

$generation = $snapshot['generation'];
online_count() === 620 || fail('online_count() must read the shared snapshot count.');
online_find_cache() === $snapshot['rows'] || fail('online_find_cache() must read the shared snapshot rows.');
online_list_cache() === $snapshot['rows'] || fail('online_list_cache() must read the shared snapshot rows.');
count($queries) === 2 || fail('Count/find/list consumers must not issue independent population queries.');

$runtime = runtime_init();
intval($runtime['onlines']) === 620 || fail('Runtime count must come from the shared snapshot.');
(string)$runtime['online_member_generation'] === (string)$generation || fail('Runtime must record the shared snapshot generation.');

// Request-local snapshot isolation is intentional: another request may advance the shared
// generation after runtime_init(), but later count/list consumers in this response must not mix
// generations. A local Session mutation uses online_member_snapshot_invalidate() and clears it.
$localQueries = count($queries);
$concurrentGeneration = online_member_snapshot_generation_new();
cache_set('online_member_generation', $concurrentGeneration);
cache_delete('online_member_snapshot');
online_count() === 620 || fail('Concurrent generation change broke request-local snapshot consistency.');
online_list_cache() === $snapshot['rows'] || fail('Request-local count/list consumers mixed generations.');
count($queries) === $localQueries || fail('Request-local fast path re-queried after an external generation change.');
$g_online_member_snapshot = NULL; // simulate the next request
$nextRequestSnapshot = online_member_snapshot();
(string)$nextRequestSnapshot['generation'] === (string)$concurrentGeneration
	|| fail('A new request did not observe the advanced shared generation.');
$generation = $nextRequestSnapshot['generation'];

// A mutation racing snapshot construction must make the builder retry and publish only
// the final generation, rather than resurrecting stale rows after invalidation.
online_member_snapshot_invalidate();
$beforeRaceQueries = count($queries);
$invalidateDuringRowsQuery = TRUE;
$raceSnapshot = online_member_snapshot();
count($queries) === $beforeRaceQueries + 4 || fail('Generation change during build must retry both snapshot queries.');
(string)$raceSnapshot['generation'] === (string)cache_get_primary('online_member_generation')
	|| fail('Racing builder published a stale snapshot generation.');

// Core drivers which expose the primary API must fail closed. A failed primary query may not
// fall back to db_find() (which can be a replica) or publish/cache an empty snapshot as trusted.
online_member_snapshot_invalidate();
$failMasterCountQuery = TRUE;
$fallbackBeforeFailure = $fallbackFindQueries;
$primaryBeforeFailure = $primaryQueries;
$failedSnapshot = online_member_snapshot();
empty($failedSnapshot['available']) || fail('Failed primary count query was marked available.');
intval($failedSnapshot['count']) === 0 && $failedSnapshot['rows'] === array()
	|| fail('Failed primary count query did not return a request-local fail-closed view.');
$primaryQueries === $primaryBeforeFailure + 1 || fail('Failed primary count query should stop before the rows query.');
$fallbackFindQueries === $fallbackBeforeFailure || fail('Supported primary count failure fell back to replica-style db_find().');
cache_get('online_member_snapshot') === NULL || fail('Failed primary count query cached an untrusted snapshot.');
$primaryAfterFailure = $primaryQueries;
online_count() === 0 || fail('Request-local failed snapshot did not remain fail closed.');
$primaryQueries === $primaryAfterFailure || fail('Request-local failed snapshot retried independently for another consumer.');
$failMasterCountQuery = FALSE;
$g_online_member_snapshot = NULL;
!empty(online_member_snapshot()['available']) || fail('Snapshot did not recover after primary count query recovery.');

online_member_snapshot_invalidate();
$failMasterRowsQuery = TRUE;
$fallbackBeforeFailure = $fallbackFindQueries;
$primaryBeforeFailure = $primaryQueries;
$failedSnapshot = online_member_snapshot();
empty($failedSnapshot['available']) || fail('Failed primary rows query was marked available.');
$primaryQueries === $primaryBeforeFailure + 2 || fail('Rows failure must execute exactly the primary count and rows queries.');
$fallbackFindQueries === $fallbackBeforeFailure || fail('Supported primary rows failure fell back to replica-style db_find().');
cache_get('online_member_snapshot') === NULL || fail('Failed primary rows query cached an untrusted snapshot.');
$failMasterRowsQuery = FALSE;
$g_online_member_snapshot = NULL;
!empty(online_member_snapshot()['available']) || fail('Snapshot did not recover after primary rows query recovery.');

// A driver with neither master API is an explicit single-endpoint compatibility case and may
// use the complete-page fallback. It must still remain exact and cache only a complete scan.
$_SERVER['db'] = new OnlineMemberUnsupportedDb();
online_member_snapshot_invalidate();
$primaryBeforeFallback = $primaryQueries;
$fallbackBeforeSupported = $fallbackFindQueries;
$fallbackSnapshot = online_member_snapshot();
!empty($fallbackSnapshot['available']) && intval($fallbackSnapshot['count']) === 620
	|| fail('Unsupported single-endpoint driver fallback lost exact online population semantics.');
$primaryQueries === $primaryBeforeFallback || fail('Unsupported driver unexpectedly invoked a primary SQL API.');
$fallbackFindQueries > $fallbackBeforeSupported || fail('Unsupported driver did not use complete-page fallback.');
$_SERVER['db'] = new OnlineMemberCompatDb();
online_member_snapshot_invalidate();

// Supported-but-failed primary Session reads must not flow into an ordinary replica read or write.
$sessionRows[] = array('sid'=>'primary-read-failure', 'uid'=>703, 'fid'=>0, 'url'=>'', 'last_date'=>$time, 'data'=>'', 'ip'=>$longip, 'useragent'=>'', 'bigdata'=>0);
$failMasterFindOne = TRUE;
$replicaBeforeFailure = $replicaFindOneQueries;
$primaryBeforeFailure = $primaryFindOneQueries;
$generationBeforeFailure = cache_get_primary('online_member_generation');
sess_tombstone('primary-read-failure') === FALSE || fail('Tombstone succeeded after a supported primary read failure.');
$primaryFindOneQueries === $primaryBeforeFailure + 1 || fail('Tombstone did not attempt the supported primary reader.');
$replicaFindOneQueries === $replicaBeforeFailure || fail('Supported primary read failure fell back to a replica read.');
$failedRow = online_test_session_by_sid('primary-read-failure');
intval($failedRow['uid']) === 703 && intval($failedRow['bigdata']) === 0
	|| fail('Tombstone mutated state after its primary pre-read failed.');
cache_get_primary('online_member_generation') === $generationBeforeFailure
	|| fail('Failed tombstone advanced the online generation without a write.');
$failMasterFindOne = FALSE;
db_delete('session', array('sid'=>'primary-read-failure'));

// The pre-read in sess_tombstone() is not a lock. A concurrent login may publish an active
// snapshot after that read but before the tombstone write; successful tombstone completion must
// invalidate that newer generation even though A originally observed a guest row.
$sessionRows[] = array('sid'=>'tombstone-race', 'uid'=>0, 'fid'=>0, 'url'=>'', 'last_date'=>$time, 'data'=>'', 'ip'=>$longip, 'useragent'=>'', 'bigdata'=>0);
$tombstoneRaceSid = 'tombstone-race';
$tombstoneRacePublishedGeneration = NULL;
sess_tombstone('tombstone-race') || fail('Concurrent-login tombstone fixture failed.');
$tombstoneRacePublishedGeneration !== NULL || fail('Concurrent-login tombstone fixture did not publish its intermediate snapshot.');
cache_get_primary('online_member_generation') !== $tombstoneRacePublishedGeneration
	|| fail('Verified tombstone did not invalidate a snapshot published after its unlocked pre-read.');
$g_online_member_snapshot = NULL;
online_count() === 620 || fail('A tombstoned concurrent login remained in the next request snapshot.');

// Once the first tombstone write succeeds, a later verification-read failure must stay a visible
// failure while still invalidating the population which the write may already have changed.
$sessionRows[] = array('sid'=>'tombstone-verify-failure', 'uid'=>706, 'fid'=>0, 'url'=>'', 'last_date'=>$time, 'data'=>'', 'ip'=>$longip, 'useragent'=>'', 'bigdata'=>0);
online_member_snapshot_invalidate();
$verifyFailureSnapshot = online_member_snapshot();
in_array(706, array_column($verifyFailureSnapshot['rows'], 'uid'), TRUE)
	|| fail('Tombstone verification-failure fixture did not publish its active member.');
$verifyFailureGeneration = cache_get_primary('online_member_generation');
$failMasterFindOneOnCall = $primaryFindOneQueries + 2;
sess_tombstone('tombstone-verify-failure') === FALSE
	|| fail('Tombstone verification read failure was incorrectly reported as success.');
$verifyFailureRow = online_test_session_by_sid('tombstone-verify-failure');
intval($verifyFailureRow['uid']) === 0 && intval($verifyFailureRow['bigdata']) === 2
	|| fail('Tombstone write was not preserved before its verification read failed.');
cache_get_primary('online_member_generation') !== $verifyFailureGeneration
	|| fail('Tombstone write did not invalidate population after its verification read failed.');
$failMasterFindOneOnCall = 0;
$g_online_member_snapshot = NULL;
online_count() === 620 || fail('Verification-failed tombstone remained in the next request snapshot.');

// A driver with no primary API retains the legacy single-endpoint Session path.
$_SERVER['db'] = new OnlineMemberUnsupportedDb();
$sessionRows[] = array('sid'=>'unsupported-primary-api', 'uid'=>704, 'fid'=>0, 'url'=>'', 'last_date'=>$time, 'data'=>'', 'ip'=>$longip, 'useragent'=>'', 'bigdata'=>0);
$replicaBeforeFallback = $replicaFindOneQueries;
sess_tombstone('unsupported-primary-api') || fail('Unsupported single-endpoint driver could not tombstone its Session.');
$replicaFindOneQueries > $replicaBeforeFallback || fail('Unsupported driver did not use its ordinary single-endpoint reader.');
$unsupportedRow = online_test_session_by_sid('unsupported-primary-api');
intval($unsupportedRow['uid']) === 0 && intval($unsupportedRow['bigdata']) === 2
	|| fail('Unsupported single-endpoint tombstone did not persist.');
$_SERVER['db'] = new OnlineMemberCompatDb();

// Login: guest -> authenticated at Session write shutdown.
$sessionRows[] = array('sid'=>'login-flow', 'uid'=>0, 'fid'=>0, 'url'=>'', 'last_date'=>$time, 'data'=>'', 'ip'=>$longip, 'useragent'=>'', 'bigdata'=>0);
$g_session = db_find_one('session', array('sid'=>'login-flow'));
$_SESSION = array('uid'=>700, 'fid'=>0);
$beforeGeneration = cache_get_primary('online_member_generation');
sess_write('login-flow', FALSE);
cache_get_primary('online_member_generation') !== $beforeGeneration || fail('Login Session write did not invalidate the online generation.');
online_count() === 621 || fail('Logged-in member was not visible after generation invalidation.');

// Ordinary activity remains online and should not trigger a rebuild on every page request.
$sessionHeartbeatSeed = db_update('session', array('sid'=>'login-flow'), array('last_date'=>$time - 1));
$sessionHeartbeatSeed === 1 || fail('Routine heartbeat fixture setup failed.');
$g_session = db_find_one('session', array('sid'=>'login-flow'));
$_SESSION = array('uid'=>700, 'fid'=>0);
$beforeGeneration = cache_get_primary('online_member_generation');
sess_write('login-flow', FALSE);
cache_get_primary('online_member_generation') === $beforeGeneration || fail('Routine online heartbeat needlessly invalidated the snapshot.');

// Logout: authenticated -> guest.
$g_session = db_find_one('session', array('sid'=>'login-flow'));
$_SESSION = array('uid'=>0, 'fid'=>0);
$beforeGeneration = cache_get_primary('online_member_generation');
sess_write('login-flow', FALSE);
cache_get_primary('online_member_generation') !== $beforeGeneration || fail('Logout Session write did not invalidate the online generation.');
online_count() === 620 || fail('Logged-out member remained in the online snapshot.');

// Reactivation: an authenticated Session outside the time window becomes active again.
$sessionRows[] = array('sid'=>'reactivation', 'uid'=>701, 'fid'=>0, 'url'=>'', 'last_date'=>$time - $conf['online_hold_time'] - 1, 'data'=>'', 'ip'=>$longip, 'useragent'=>'', 'bigdata'=>0);
$g_session = db_find_one('session', array('sid'=>'reactivation'));
$_SESSION = array('uid'=>701, 'fid'=>0);
$beforeGeneration = cache_get_primary('online_member_generation');
sess_write('reactivation', FALSE);
cache_get_primary('online_member_generation') !== $beforeGeneration || fail('Session reactivation did not invalidate the online generation.');
online_count() === 621 || fail('Reactivated member was not added to the online snapshot.');

// Destroy/tombstone must remove the member and advance the generation.
$beforeGeneration = cache_get_primary('online_member_generation');
sess_destroy('reactivation') || fail('Session destroy fixture failed.');
cache_get_primary('online_member_generation') !== $beforeGeneration || fail('Session destroy did not invalidate the online generation.');
online_count() === 620 || fail('Destroyed member remained in the online snapshot.');

// Cached snapshots expire at the earliest member boundary even without a write/GC event.
$boundaryGeneration = cache_get_primary('online_member_generation');
$time = 10001;
online_count() === 619 || fail('Expired boundary member remained visible before cron GC.');
cache_get_primary('online_member_generation') === $boundaryGeneration || fail('Natural snapshot expiry should not fabricate a Session mutation generation.');

// GC always advances generation so all consumers converge after maintenance.
$beforeGeneration = cache_get_primary('online_member_generation');
sess_gc($conf['online_hold_time']);
cache_get_primary('online_member_generation') !== $beforeGeneration || fail('Session GC did not invalidate the online generation.');
online_count() === 619 || fail('GC changed the already window-filtered population unexpectedly.');
$runtime = runtime_init();
intval($runtime['onlines']) === 619 || fail('Runtime did not converge to the post-GC snapshot.');
(string)$runtime['online_member_generation'] === (string)cache_get_primary('online_member_generation')
	|| fail('Runtime did not retain the post-GC generation.');

// Cron is a snapshot consumer/maintenance trigger, not an independent count path.
$sessionRows[] = array('sid'=>'cron-login', 'uid'=>702, 'fid'=>0, 'url'=>'', 'last_date'=>$time, 'data'=>'', 'ip'=>$longip, 'useragent'=>'', 'bigdata'=>0);
$runtime['cron_1_last_date'] = $time - 301;
$runtime['cron_2_last_date'] = $time;
$forumlist = array();
cron_run(0);
intval($runtime['onlines']) === 620 || fail('Cron did not publish the shared post-GC snapshot count.');
(string)$runtime['online_member_generation'] === (string)cache_get_primary('online_member_generation')
	|| fail('Cron runtime count and snapshot generation diverged.');

echo "OK: online member generation snapshot behavior checks passed\n";
