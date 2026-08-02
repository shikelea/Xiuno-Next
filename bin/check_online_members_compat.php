<?php

$root = dirname(__DIR__).'/';
$sessionSource = file_get_contents($root.'model/session.func.php');
$runtimeSource = file_get_contents($root.'model/runtime.func.php');
$cronSource = file_get_contents($root.'model/cron.func.php');

function fail($message) {
	fwrite(STDERR, "FAIL: $message\n");
	exit(1);
}

if($sessionSource === FALSE || $runtimeSource === FALSE || $cronSource === FALSE) {
	fail('failed to read online member compatibility sources');
}

$condition = "online_member_condition()";
substr_count($sessionSource, $condition) >= 3
	|| fail('Online count, find, and list APIs must share online_member_condition().');
strpos($sessionSource, "'uid'=>array('>'=>0)") !== FALSE
	|| fail('Online member condition must exclude guest sessions.');
strpos($sessionSource, "'bigdata'=>array('<='=>1)") !== FALSE
	|| fail('Online member condition must exclude revoked tombstones.');
strpos($runtimeSource, "max(0, online_count())") !== FALSE
	|| fail('Runtime initialization must allow zero online members.');
strpos($runtimeSource, "online_member_compat_version") !== FALSE
	|| fail('Runtime cache must refresh online member compatibility state.');
strpos($cronSource, "max(0, online_count())") !== FALSE
	|| fail('Cron refresh must allow zero online members.');
strpos($cronSource, "online_member_compat_version") !== FALSE
	|| fail('Cron refresh must persist online member compatibility state.');

$sessionRows = array(
	array('sid'=>'guest', 'uid'=>0, 'bigdata'=>0, 'last_date'=>400),
	array('sid'=>'member-small', 'uid'=>7, 'bigdata'=>0, 'last_date'=>300, 'ip'=>2130706433),
	array('sid'=>'member-small-duplicate', 'uid'=>7, 'bigdata'=>0, 'last_date'=>250, 'ip'=>2130706433),
	array('sid'=>'member-large', 'uid'=>8, 'bigdata'=>1, 'last_date'=>200, 'ip'=>2130706433),
	array('sid'=>'revoked', 'uid'=>9, 'bigdata'=>2, 'last_date'=>100, 'ip'=>2130706433),
);
$cache = array();
$queries = array();

function db_count($table, $cond = array()) {
	global $sessionRows, $queries;
	$queries[] = array('count', $table, $cond);
	$n = 0;
	foreach($sessionRows as $row) {
		if($table !== 'session') continue;
		if(isset($cond['uid']['>']) && intval($row['uid']) <= intval($cond['uid']['>'])) continue;
		if(isset($cond['bigdata']['<=']) && intval($row['bigdata']) > intval($cond['bigdata']['<='])) continue;
		$n++;
	}
	return $n;
}

function db_find($table, $cond = array(), $sort = array(), $page = 1, $pagesize = 0, $keytype = '') {
	global $sessionRows, $queries;
	$queries[] = array('find', $table, $cond);
	$out = array();
	foreach($sessionRows as $row) {
		if($table !== 'session') continue;
		if(isset($cond['uid']['>']) && intval($row['uid']) <= intval($cond['uid']['>'])) continue;
		if(isset($cond['bigdata']['<=']) && intval($row['bigdata']) > intval($cond['bigdata']['<='])) continue;
		$out[] = $row;
	}
	return $out;
}

function cache_get($key) {
	global $cache;
	return array_key_exists($key, $cache) ? $cache[$key] : NULL;
}

function cache_set($key, $value, $ttl = 0) {
	global $cache;
	$cache[$key] = $value;
	return TRUE;
}

function user_read_cache($uid) {
	return array('uid'=>$uid, 'username'=>'member-'.$uid, 'gid'=>1);
}

include $root.'model/session.func.php';

online_count() === 2 || fail('Online count must include unique active authenticated members.');
$found = online_find_cache();
count($found) === 2 || fail('Online find must use the same unique active member population.');
$listed = online_list_cache();
count($listed) === 2 || fail('Online list must exclude guests, revoked sessions, and duplicate sessions.');
$uids = array();
foreach($listed as $row) {
	intval($row['uid']) > 0 || fail('Online list returned a guest session.');
	intval($row['bigdata']) <= 1 || fail('Online list returned a revoked session.');
	$uid = intval($row['uid']);
	isset($uids[$uid]) && fail('Online list returned duplicate member UIDs.');
	$uids[$uid] = TRUE;
}
count($queries) === 3 || fail('Expected one count and two list/find queries.');

echo "OK: online member compatibility checks passed\n";
