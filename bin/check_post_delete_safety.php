<?php

$root = dirname(__DIR__).'/';

class PostDeleteGuardMessage extends Exception {
	public $response_code;
	public $response_message;

	public function __construct($code, $message) {
		parent::__construct((string)$message);
		$this->response_code = $code;
		$this->response_message = $message;
	}
}

function post_delete_guard_fail($message) {
	fwrite(STDERR, "FAIL: $message\n");
	exit(1);
}

function post_delete_guard_assert($condition, $message) {
	$condition || post_delete_guard_fail($message);
}

// Shared route-fixture shims. The model fixture below uses the same harmless compatibility helpers.
function param($key, $default = '', $htmlspecialchars = TRUE, $addslashes = FALSE) {
	if($key === 1) return 'delete';
	if($key === 2) return 7001;
	return $default;
}

function user_login_check() { return TRUE; }
function forum_read($fid) { return array('fid'=>$fid); }
function forum_access_user($fid, $gid, $access) { return TRUE; }
function forum_access_mod($fid, $gid, $access) { return TRUE; }
function lang($key, $values = array()) { return $key; }

function message($code, $message, $extra = array()) {
	throw new PostDeleteGuardMessage($code, $message);
}

// Exercise the real route in an isolated PHP process because its post/thread model symbols must be
// replaced with fault-injection shims. The parent process below loads the real model functions.
if(isset($argv[1]) && $argv[1] === '--route-fixture') {
	$fixture_route_kind = isset($argv[2]) ? $argv[2] : '';
	$fixture_route_result_name = isset($argv[3]) ? $argv[3] : '';
	$fixture_route_result = $fixture_route_result_name === 'false'
		? FALSE
		: ($fixture_route_result_name === 'zero' ? 0 : 1);
	$fixture_route_calls = array('post_delete'=>0, 'thread_delete'=>0, 'post_primary'=>0, 'thread_primary'=>0, 'recovery_read'=>0);

	define('DEBUG', 1);

	function post_read($pid, $primary = FALSE) {
		global $fixture_route_kind, $fixture_route_calls;
		$primary AND $fixture_route_calls['post_primary']++;
		if($fixture_route_kind === 'post-read-fail') return FALSE;
		if($fixture_route_kind === 'recovery') return array();
		return array(
			'pid'=>$pid,
			'tid'=>700,
			'isfirst'=>$fixture_route_kind === 'first' ? 1 : 0,
			'allowdelete'=>1,
		);
	}

	function thread_read($tid, $primary = FALSE) {
		global $fixture_route_kind, $fixture_route_calls;
		$primary AND $fixture_route_calls['thread_primary']++;
		if($fixture_route_kind === 'thread-read-fail') return FALSE;
		return array('tid'=>$tid, 'fid'=>7, 'closed'=>0);
	}

	function thread_read_by_firstpid_primary($firstpid) {
		global $fixture_route_kind, $fixture_route_calls;
		$fixture_route_calls['recovery_read']++;
		if($fixture_route_kind !== 'recovery') return array();
		return array('tid'=>700, 'fid'=>7, 'uid'=>1, 'firstpid'=>$firstpid, 'closed'=>0);
	}

	function post_delete($pid) {
		global $fixture_route_calls, $fixture_route_result;
		$fixture_route_calls['post_delete']++;
		return $fixture_route_result;
	}

	function thread_delete($tid) {
		global $fixture_route_calls, $fixture_route_result;
		$fixture_route_calls['thread_delete']++;
		return $fixture_route_result;
	}

	$method = 'POST';
	$uid = 1;
	$gid = 1;

	try {
		include $root.'route/post.php';
	} catch(PostDeleteGuardMessage $e) {
		echo json_encode(array(
			'code'=>$e->response_code,
			'message'=>$e->response_message,
			'calls'=>$fixture_route_calls,
		));
		exit(0);
	}

	post_delete_guard_fail('Post delete route returned without a message response.');
}

function post_delete_guard_route_case($kind, $result) {
	$command = escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__)
		.' --route-fixture '.escapeshellarg($kind).' '.escapeshellarg($result);
	$descriptors = array(
		0=>array('pipe', 'r'),
		1=>array('pipe', 'w'),
		2=>array('pipe', 'w'),
	);
	$pipes = array();
	$process = proc_open($command, $descriptors, $pipes);
	is_resource($process) || post_delete_guard_fail('Unable to start the post delete route fixture.');
	fclose($pipes[0]);
	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	$status = proc_close($process);
	$status === 0 || post_delete_guard_fail('Post delete route fixture failed: '.trim($stderr));
	$response = json_decode($stdout, TRUE);
	is_array($response) || post_delete_guard_fail('Post delete route fixture returned invalid JSON: '.$stdout);
	return $response;
}

$route_reply_failure = post_delete_guard_route_case('reply', 'false');
post_delete_guard_assert(
	intval($route_reply_failure['code']) === -1
		&& $route_reply_failure['message'] === 'delete_failed'
		&& $route_reply_failure['calls']['post_delete'] === 1
		&& $route_reply_failure['calls']['thread_delete'] === 0
		&& $route_reply_failure['calls']['post_primary'] === 1
		&& $route_reply_failure['calls']['thread_primary'] === 1,
	'Ordinary post deletion must strictly propagate a FALSE model result as delete_failed.'
);

$route_thread_failure = post_delete_guard_route_case('first', 'false');
post_delete_guard_assert(
	intval($route_thread_failure['code']) === -1
		&& $route_thread_failure['message'] === 'delete_failed'
		&& $route_thread_failure['calls']['thread_delete'] === 1
		&& $route_thread_failure['calls']['post_delete'] === 0
		&& $route_thread_failure['calls']['post_primary'] === 1
		&& $route_thread_failure['calls']['thread_primary'] === 1,
	'First-post deletion must strictly propagate a FALSE thread model result as delete_failed.'
);

$route_idempotent_zero = post_delete_guard_route_case('reply', 'zero');
post_delete_guard_assert(
	intval($route_idempotent_zero['code']) === 0
		&& $route_idempotent_zero['message'] === 'delete_successfully',
	'Post deletion must preserve the database contract that only strict FALSE is a failure.'
);

$route_recovery = post_delete_guard_route_case('recovery', 'success');
post_delete_guard_assert(
	intval($route_recovery['code']) === 0
		&& $route_recovery['calls']['thread_delete'] === 1
		&& $route_recovery['calls']['post_delete'] === 0
		&& $route_recovery['calls']['recovery_read'] === 1,
	'A failed first-post deletion attempt must remain retryable through the original delete URL while the parent thread exists.'
);

$route_post_read_failure = post_delete_guard_route_case('post-read-fail', 'success');
post_delete_guard_assert(
	intval($route_post_read_failure['code']) === -1 && $route_post_read_failure['message'] === 'delete_failed',
	'A primary post read failure must not be reported as post_not_exists or deletion success.'
);

$route_thread_read_failure = post_delete_guard_route_case('thread-read-fail', 'success');
post_delete_guard_assert(
	intval($route_thread_read_failure['code']) === -1 && $route_thread_read_failure['message'] === 'delete_failed',
	'A primary thread read failure must not be reported as thread_not_exists or deletion success.'
);

// In-memory database fixture for the real post/thread deletion functions.
$fixture_threads = array();
$fixture_posts = array();
$fixture_post_delete_fail = array();
$fixture_post_delete_noop = array();
$fixture_post_find_fail = array();
$fixture_post_find_calls = array();
$fixture_post_find_master_calls = array();
$fixture_post_find_replica_calls = array();
$fixture_post_primary_read_fail = array();
$fixture_thread_primary_read_fail = array();
$fixture_primary_read_calls = array('post'=>array(), 'thread'=>array());
$fixture_replica_read_calls = array('post'=>array(), 'thread'=>array());
$fixture_post_delete_calls = array();
$fixture_thread_delete_calls = array();
$fixture_thread_delete_fail = array();
$fixture_thread_delete_noop = array();
$fixture_mythread_delete_calls = array();
$fixture_forum_update_calls = array();
$fixture_user_update_calls = array();
$fixture_runtime_set_calls = array();
$fixture_attach_delete_calls = array();

$conf = array('postlist_pagesize'=>20, 'update_views_on'=>0);
$forumlist = array(7=>array('fid'=>7, 'name'=>'Fixture Forum'));
$uid = 1;
$gid = 1;
$sid = 'post-delete-guard';
$longip = 0;

function xn_strlen($value) { return strlen((string)$value); }
function xn_substr($value, $start, $length) { return substr($value, $start, $length); }
function xn_txt_to_html($value) { return (string)$value; }
function xn_html_safe($value) { return (string)$value; }
function humandate($timestamp) { return (string)$timestamp; }
function url($route) { return $route.'.htm'; }
function array_value($array, $key, $default = NULL) { return isset($array[$key]) ? $array[$key] : $default; }
function user_read_cache($uid) { return array('uid'=>$uid, 'username'=>'fixture', 'avatar_url'=>''); }
function user_guest() { return array('uid'=>0, 'username'=>'guest', 'avatar_url'=>''); }
function user_safe_info($user) { return $user; }
function attach_find_by_pid($pid) { return array(array(), array(), array()); }
function attach_delete_by_pid($pid) {
	global $fixture_attach_delete_calls;
	$fixture_attach_delete_calls[] = $pid;
	return 0;
}
function forum_list_cache_delete() { return TRUE; }
function runtime_set($key, $value) {
	global $fixture_runtime_set_calls;
	$fixture_runtime_set_calls[] = array($key, $value);
	return TRUE;
}
function user_update_group($uid) { return TRUE; }
function forum__update($fid, $update) {
	global $fixture_forum_update_calls;
	$fixture_forum_update_calls[] = array($fid, $update);
	return 1;
}
function user__update($uid, $update, $expected_auth_epoch = NULL, &$db_result = NULL, &$db_uid = NULL) {
	global $fixture_user_update_calls;
	$fixture_user_update_calls[] = array($uid, $update);
	return 1;
}
function mythread_create($uid, $tid) { return 1; }
function mythread_delete($uid, $tid) {
	global $fixture_mythread_delete_calls;
	$fixture_mythread_delete_calls[] = array($uid, $tid);
	return 1;
}
function thread_top_update_by_tid($tid, $fid) { return TRUE; }
function thread_top_cache_delete() { return TRUE; }

function db_insert($table, $row) { return FALSE; }

function db_update($table, $condition, $update) {
	global $fixture_threads, $fixture_posts;
	$store = $table === 'thread' ? 'fixture_threads' : ($table === 'post' ? 'fixture_posts' : '');
	if($store === '') return 1;
	$id_key = $table === 'thread' ? 'tid' : 'pid';
	$id = isset($condition[$id_key]) ? intval($condition[$id_key]) : 0;
	$rows =& $$store;
	if(!isset($rows[$id])) return 0;
	foreach($update as $key=>$value) {
		$last = substr($key, -1);
		if($last === '+' || $last === '-') {
			$field = substr($key, 0, -1);
			$current = isset($rows[$id][$field]) ? intval($rows[$id][$field]) : 0;
			$rows[$id][$field] = $last === '+' ? $current + intval($value) : $current - intval($value);
		} else {
			$rows[$id][$key] = $value;
		}
	}
	return 1;
}

function db_delete($table, $condition) {
	global $fixture_threads, $fixture_posts, $fixture_post_delete_fail,
		$fixture_post_delete_noop, $fixture_post_delete_calls, $fixture_thread_delete_calls,
		$fixture_thread_delete_fail, $fixture_thread_delete_noop;
	if($table === 'post') {
		$pid = intval($condition['pid']);
		$fixture_post_delete_calls[] = $pid;
		if(isset($fixture_post_delete_fail[$pid])) return FALSE;
		if(isset($fixture_post_delete_noop[$pid])) return 0;
		if(!isset($fixture_posts[$pid])) return 0;
		unset($fixture_posts[$pid]);
		return 1;
	}
	if($table === 'thread') {
		$tid = intval($condition['tid']);
		$fixture_thread_delete_calls[] = $tid;
		if(isset($fixture_thread_delete_fail[$tid])) return FALSE;
		if(isset($fixture_thread_delete_noop[$tid])) return 0;
		if(!isset($fixture_threads[$tid])) return 0;
		unset($fixture_threads[$tid]);
		return 1;
	}
	return 1;
}

function db_find_one($table, $condition = array(), $orderby = array(), $columns = array()) {
	global $fixture_threads, $fixture_posts, $fixture_replica_read_calls;
	if($table === 'post') {
		$pid = intval($condition['pid']);
		$fixture_replica_read_calls['post'][] = $pid;
		return isset($fixture_posts[$pid]) ? $fixture_posts[$pid] : array();
	}
	if($table === 'thread') {
		$tid = intval($condition['tid']);
		$fixture_replica_read_calls['thread'][] = $tid;
		return isset($fixture_threads[$tid]) ? $fixture_threads[$tid] : array();
	}
	return array();
}

function db_find_one_master($table, $condition = array(), $orderby = array(), $columns = array()) {
	global $fixture_threads, $fixture_posts, $fixture_post_primary_read_fail,
		$fixture_thread_primary_read_fail, $fixture_primary_read_calls;
	if($table === 'post') {
		$pid = isset($condition['pid']) ? intval($condition['pid']) : 0;
		$fixture_primary_read_calls['post'][] = $pid;
		if(isset($fixture_post_primary_read_fail[$pid])) return FALSE;
		return isset($fixture_posts[$pid]) ? $fixture_posts[$pid] : array();
	}
	if($table === 'thread') {
		$tid = isset($condition['tid']) ? intval($condition['tid']) : 0;
		$fixture_primary_read_calls['thread'][] = $tid;
		if(isset($fixture_thread_primary_read_fail[$tid])) return FALSE;
		return isset($fixture_threads[$tid]) ? $fixture_threads[$tid] : array();
	}
	return array();
}

function db_find($table, $condition = array(), $orderby = array(), $page = 1, $pagesize = 10, $key = '', $columns = array()) {
	global $fixture_post_find_replica_calls;
	if($table === 'post') $fixture_post_find_replica_calls[] = intval(isset($condition['tid']) ? $condition['tid'] : 0);
	return post_delete_guard_db_find_rows($table, $condition, $page, $pagesize, $key);
}

function db_find_master($table, $condition = array(), $orderby = array(), $page = 1, $pagesize = 10, $key = '', $columns = array()) {
	global $fixture_post_find_master_calls;
	if($table === 'post') $fixture_post_find_master_calls[] = intval(isset($condition['tid']) ? $condition['tid'] : 0);
	return post_delete_guard_db_find_rows($table, $condition, $page, $pagesize, $key);
}

function post_delete_guard_db_find_rows($table, $condition, $page, $pagesize, $key) {
	global $fixture_posts, $fixture_post_find_fail, $fixture_post_find_calls;
	if($table !== 'post') return array();
	$tid = isset($condition['tid']) ? intval($condition['tid']) : 0;
	if(!isset($fixture_post_find_calls[$tid])) $fixture_post_find_calls[$tid] = array();
	if(isset($fixture_post_find_fail[$tid])) {
		$fixture_post_find_calls[$tid][] = FALSE;
		return FALSE;
	}
	$rows = array();
	foreach($fixture_posts as $pid=>$post) {
		if(intval($post['tid']) === $tid) $rows[$pid] = $post;
	}
	ksort($rows, SORT_NUMERIC);
	$rows = array_slice($rows, max(0, (intval($page) - 1) * intval($pagesize)), intval($pagesize), TRUE);
	$fixture_post_find_calls[$tid][] = array_keys($rows);
	if($key === '') return array_values($rows);
	return $rows;
}

require_once $root.'model/post.func.php';
require_once $root.'model/thread.func.php';

function post_delete_guard_add_thread($tid, $post_count) {
	global $fixture_threads, $fixture_posts;
	$firstpid = $tid * 1000 + 1;
	$fixture_threads[$tid] = array(
		'tid'=>$tid,
		'fid'=>7,
		'uid'=>10,
		'subject'=>'Fixture '.$tid,
		'create_date'=>100,
		'last_date'=>100,
		'firstpid'=>$firstpid,
		'lastpid'=>0,
		'lastuid'=>10,
		'top'=>0,
		'posts'=>max(0, $post_count - 1),
	);
	for($offset = 1; $offset <= $post_count; $offset++) {
		$pid = $tid * 1000 + $offset;
		$fixture_posts[$pid] = array(
			'pid'=>$pid,
			'tid'=>$tid,
			'uid'=>10,
			'isfirst'=>$offset === 1 ? 1 : 0,
			'doctype'=>0,
			'message_fmt'=>'fixture',
			'create_date'=>100 + $offset,
			'images'=>0,
			'files'=>0,
		);
	}
	return $firstpid;
}

function post_delete_guard_posts_for_tid($tid) {
	global $fixture_posts;
	$rows = array();
	foreach($fixture_posts as $pid=>$post) {
		if(intval($post['tid']) === intval($tid)) $rows[$pid] = $post;
	}
	return $rows;
}

$pagination_tid = 101;
post_delete_guard_add_thread($pagination_tid, 125);
$pagination_result = post_delete_by_tid($pagination_tid);
post_delete_guard_assert($pagination_result === 125, 'Thread post deletion must return the total count across every batch.');
post_delete_guard_assert(empty(post_delete_guard_posts_for_tid($pagination_tid)), 'Thread post deletion must continue beyond the first 50 posts.');
$pagination_reads = $fixture_post_find_calls[$pagination_tid];
post_delete_guard_assert(
	count($pagination_reads) === 5
		&& count($pagination_reads[0]) === 50
		&& count($pagination_reads[1]) === 50
		&& count($pagination_reads[2]) === 27
		&& count($pagination_reads[3]) === 1
		&& $pagination_reads[4] === array(),
	'Thread post deletion must keep reading page one until an explicit empty batch proves completion.'
);
post_delete_guard_assert(
	in_array($pagination_tid, $fixture_post_find_master_calls, TRUE)
		&& !in_array($pagination_tid, $fixture_post_find_replica_calls, TRUE),
	'Destructive post pagination must read from the write connection, never a potentially stale replica.'
);

$post_read_failure_tid = 107;
$post_read_failure_pid = post_delete_guard_add_thread($post_read_failure_tid, 1);
$fixture_post_primary_read_fail[$post_read_failure_pid] = TRUE;
post_delete_guard_assert(
	post_delete($post_read_failure_pid) === FALSE
		&& isset($fixture_posts[$post_read_failure_pid]),
	'A failed primary post read must preserve the post and propagate FALSE.'
);

$thread_read_failure_tid = 108;
post_delete_guard_add_thread($thread_read_failure_tid, 1);
$fixture_thread_primary_read_fail[$thread_read_failure_tid] = TRUE;
post_delete_guard_assert(
	thread_delete($thread_read_failure_tid) === FALSE
		&& isset($fixture_threads[$thread_read_failure_tid])
		&& count(post_delete_guard_posts_for_tid($thread_read_failure_tid)) === 1,
	'A failed primary thread read must preserve the thread and its posts.'
);

$post_parent_read_failure_tid = 109;
$post_parent_read_failure_firstpid = post_delete_guard_add_thread($post_parent_read_failure_tid, 2);
$post_parent_read_failure_replypid = $post_parent_read_failure_firstpid + 1;
$fixture_thread_primary_read_fail[$post_parent_read_failure_tid] = TRUE;
post_delete_guard_assert(
	post_delete($post_parent_read_failure_replypid) === FALSE
		&& isset($fixture_posts[$post_parent_read_failure_replypid]),
	'A failed primary parent-thread read must stop ordinary reply deletion before side effects.'
);

$read_failure_tid = 102;
post_delete_guard_add_thread($read_failure_tid, 1);
$fixture_post_find_fail[$read_failure_tid] = TRUE;
post_delete_guard_assert(
	post_delete_by_tid($read_failure_tid) === FALSE
		&& count(post_delete_guard_posts_for_tid($read_failure_tid)) === 1,
	'A failed post-list read must fail closed without claiming the thread is empty.'
);

$delete_failure_tid = 103;
$delete_failure_firstpid = post_delete_guard_add_thread($delete_failure_tid, 4);
$fixture_post_delete_fail[$delete_failure_firstpid + 1] = TRUE;
post_delete_guard_assert(post_delete_by_tid($delete_failure_tid) === FALSE, 'One child post delete failure must propagate from post_delete_by_tid().');
$delete_failure_rows = post_delete_guard_posts_for_tid($delete_failure_tid);
post_delete_guard_assert(
	isset($delete_failure_rows[$delete_failure_firstpid])
		&& isset($delete_failure_rows[$delete_failure_firstpid + 1])
		&& isset($delete_failure_rows[$delete_failure_firstpid + 2]),
	'Post deletion must keep the first post until every reply is gone so the original delete URL remains retryable.'
);

$no_progress_tid = 104;
$no_progress_firstpid = post_delete_guard_add_thread($no_progress_tid, 2);
$no_progress_pid = $no_progress_firstpid + 1;
$fixture_post_delete_noop[$no_progress_pid] = TRUE;
$post_stats_before = array(
	'user'=>count(array_filter($fixture_user_update_calls, function($call) { return isset($call[1]['posts-']); })),
	'runtime'=>count(array_filter($fixture_runtime_set_calls, function($call) { return $call[0] === 'posts-'; })),
);
post_delete_guard_assert(post_delete_by_tid($no_progress_tid) === FALSE, 'A non-empty batch that repeats a processed pid must fail as no progress.');
post_delete_guard_assert(
	isset($fixture_posts[$no_progress_firstpid])
		&& isset($fixture_posts[$no_progress_pid])
		&& count($fixture_post_find_calls[$no_progress_tid]) === 2
		&& count(array_keys($fixture_post_delete_calls, $no_progress_pid, TRUE)) === 1
		&& count(array_keys($fixture_post_delete_calls, $no_progress_firstpid, TRUE)) === 0,
	'No-progress detection must stop before retrying the same child indefinitely.'
);
post_delete_guard_assert(
	count(array_filter($fixture_user_update_calls, function($call) { return isset($call[1]['posts-']); })) === $post_stats_before['user']
		&& count(array_filter($fixture_runtime_set_calls, function($call) { return $call[0] === 'posts-'; })) === $post_stats_before['runtime'],
	'A zero-row concurrent reply delete must not decrement post statistics.'
);

$parent_failure_tid = 105;
$parent_failure_firstpid = post_delete_guard_add_thread($parent_failure_tid, 3);
$fixture_post_delete_fail[$parent_failure_firstpid + 1] = TRUE;
$thread_delete_calls_before = count($fixture_thread_delete_calls);
$mythread_delete_calls_before = count($fixture_mythread_delete_calls);
post_delete_guard_assert(thread_delete($parent_failure_tid) === FALSE, 'thread_delete() must propagate a child-post deletion failure.');
post_delete_guard_assert(
	isset($fixture_threads[$parent_failure_tid])
		&& isset($fixture_posts[$parent_failure_firstpid])
		&& count($fixture_thread_delete_calls) === $thread_delete_calls_before
		&& count($fixture_mythread_delete_calls) === $mythread_delete_calls_before,
	'A child-post failure must preserve the authoritative parent thread and defer later cleanup.'
);

$thread_row_failure_tid = 110;
post_delete_guard_add_thread($thread_row_failure_tid, 3);
$fixture_thread_delete_fail[$thread_row_failure_tid] = TRUE;
post_delete_guard_assert(
	thread_delete($thread_row_failure_tid) === FALSE
		&& isset($fixture_threads[$thread_row_failure_tid])
		&& empty(post_delete_guard_posts_for_tid($thread_row_failure_tid)),
	'A parent-row delete failure must remain visible after child cleanup.'
);
unset($fixture_thread_delete_fail[$thread_row_failure_tid]);
post_delete_guard_assert(
	thread_delete($thread_row_failure_tid) !== FALSE && !isset($fixture_threads[$thread_row_failure_tid]),
	'A parent-row delete failure must remain model-retryable without reconstructing deleted children.'
);

$thread_row_noop_tid = 111;
post_delete_guard_add_thread($thread_row_noop_tid, 2);
$fixture_thread_delete_noop[$thread_row_noop_tid] = TRUE;
$thread_stats_before = array(
	'forum'=>count(array_filter($fixture_forum_update_calls, function($call) { return isset($call[1]['threads-']); })),
	'user'=>count(array_filter($fixture_user_update_calls, function($call) { return isset($call[1]['threads-']); })),
	'runtime'=>count(array_filter($fixture_runtime_set_calls, function($call) { return $call[0] === 'threads-'; })),
);
post_delete_guard_assert(thread_delete($thread_row_noop_tid) !== FALSE, 'A concurrent zero-row parent delete remains idempotent.');
post_delete_guard_assert(
	count(array_filter($fixture_forum_update_calls, function($call) { return isset($call[1]['threads-']); })) === $thread_stats_before['forum']
		&& count(array_filter($fixture_user_update_calls, function($call) { return isset($call[1]['threads-']); })) === $thread_stats_before['user']
		&& count(array_filter($fixture_runtime_set_calls, function($call) { return $call[0] === 'threads-'; })) === $thread_stats_before['runtime'],
	'A zero-row concurrent thread delete must not decrement thread statistics.'
);

$thread_success_tid = 106;
post_delete_guard_add_thread($thread_success_tid, 55);
post_delete_guard_assert(thread_delete($thread_success_tid) !== FALSE, 'A fully deleted multi-batch thread must complete successfully.');
post_delete_guard_assert(
	!isset($fixture_threads[$thread_success_tid]) && empty(post_delete_guard_posts_for_tid($thread_success_tid)),
	'Success must mean both the parent thread and every child post are absent from the fixture.'
);

$db_source = file_get_contents($root.'xiunophp/db.func.php');
$driver_sources = array(
	file_get_contents($root.'xiunophp/db_mysql.class.php'),
	file_get_contents($root.'xiunophp/db_pdo_mysql.class.php'),
	file_get_contents($root.'xiunophp/db_pdo_sqlite.class.php'),
);
post_delete_guard_assert(
	is_string($db_source)
		&& strpos($db_source, 'function db_find_master(') !== FALSE
		&& strpos($db_source, "!method_exists(\$d, 'find_master')") !== FALSE,
	'Database helpers must expose a fail-closed primary multi-row reader.'
);
foreach($driver_sources as $driver_source) {
	post_delete_guard_assert(
		is_string($driver_source) && strpos($driver_source, 'function find_master(') !== FALSE,
		'Every supported SQL driver must implement primary multi-row reads.'
	);
}

echo "OK: post delete failure propagation checks passed\n";

?>
