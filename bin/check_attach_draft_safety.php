<?php

$root = dirname(__DIR__).DIRECTORY_SEPARATOR;

function fail($message) {
	fwrite(STDERR, "FAIL: $message\n");
	exit(1);
}

function _SESSION($key, $default = NULL) {
	return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
}

function remove_tree($path) {
	if(is_dir($path)) {
		foreach(scandir($path) as $entry) {
			if($entry === '.' || $entry === '..') continue;
			remove_tree($path.DIRECTORY_SEPARATOR.$entry);
		}
		@rmdir($path);
	} elseif(is_file($path)) {
		@unlink($path);
	}
}

function array_value($array, $key, $default = NULL) {
	return is_array($array) && array_key_exists($key, $array) ? $array[$key] : $default;
}

function file_name($path) {
	$path = str_replace('\\', '/', (string)$path);
	return substr($path, strrpos($path, '/') + 1);
}

function xn_copy($source, $destination) {
	global $fixture_copy_fail;
	if(isset($fixture_copy_fail[basename($source)])) return FALSE;
	return copy($source, $destination);
}

function xn_log($message, $channel = 'error') {
	global $fixture_logs;
	$fixture_logs[] = array($channel, $message);
	return TRUE;
}

function post__read($pid) {
	global $fixture_posts;
	return isset($fixture_posts[$pid]) ? $fixture_posts[$pid] : array();
}

function post__update($pid, $update) {
	global $fixture_posts, $fixture_post_update_fail;
	if(!isset($fixture_posts[$pid])) return FALSE;
	if(!empty($fixture_post_update_fail)) return FALSE;
	$fixture_posts[$pid] = array_merge($fixture_posts[$pid], $update);
	return TRUE;
}

function thread__update($tid, $update) {
	global $fixture_threads, $fixture_thread_update_fail;
	if(!empty($fixture_thread_update_fail)) return FALSE;
	if(!isset($fixture_threads[$tid])) $fixture_threads[$tid] = array();
	$fixture_threads[$tid] = array_merge($fixture_threads[$tid], $update);
	return TRUE;
}

function db_create($table, $row) {
	global $fixture_attach_rows, $fixture_db_fail;
	if($table !== 'attach') return FALSE;
	if(isset($fixture_db_fail[$row['orgfilename']])) return FALSE;
	$aid = count($fixture_attach_rows) + 1;
	$row['aid'] = $aid;
	$fixture_attach_rows[$aid] = $row;
	return $aid;
}

function db_delete($table, $condition) {
	global $fixture_attach_rows, $fixture_delete_fail;
	if($table !== 'attach' || !isset($condition['aid'])) return FALSE;
	$aid = intval($condition['aid']);
	if(isset($fixture_delete_fail[$aid]) || !isset($fixture_attach_rows[$aid])) return FALSE;
	unset($fixture_attach_rows[$aid]);
	return TRUE;
}

function db_find($table, $condition, $orderby = array(), $page = 1, $pagesize = 20) {
	global $fixture_attach_rows, $fixture_find_fail;
	if($table !== 'attach') return array();
	if(!empty($fixture_find_fail)) return FALSE;
	$rows = array();
	foreach($fixture_attach_rows as $aid=>$row) {
		$matches = TRUE;
		foreach($condition as $key=>$value) {
			if(!isset($row[$key]) || $row[$key] != $value) $matches = FALSE;
		}
		if($matches) $rows[$aid] = $row;
	}
	return array_slice($rows, 0, $pagesize, TRUE);
}

include $root.'model/attach.func.php';

attach_orgfilename_error('a&b.txt') === '' || fail('a normal filename must retain ampersands as raw data');
attach_orgfilename_error(str_repeat('a', 116).'.txt') === '' || fail('a 120-character filename must satisfy the database contract');
attach_orgfilename_error(str_repeat('测', 116).'.txt') === '' || fail('the filename limit must count UTF-8 characters instead of bytes');
attach_orgfilename_error(str_repeat('a', 117).'.txt') === 'attach_filename_too_long'
	|| fail('a filename longer than the database field must fail before draft storage');
foreach(array('', "invalid\xFF.txt", "control\x00.txt", "line\nfeed.txt", '../escape.txt', 'dir/file.txt', 'dir\\file.txt') as $invalid_name) {
	attach_orgfilename_error($invalid_name) === 'attach_filename_invalid'
		|| fail('invalid original filename must fail closed: '.bin2hex($invalid_name));
}

$attach_route_source = file_get_contents($root.'route/attach.php');
is_string($attach_route_source) || fail('attachment route source must remain readable');
strpos($attach_route_source, "param('name', '', FALSE)") !== FALSE
	|| fail('attachment upload must read the original filename without HTML entity conversion');
strpos($attach_route_source, 'attach_orgfilename_error($name)') !== FALSE
	|| fail('attachment upload must validate the original filename before extension and persistence use');
strpos($attach_route_source, 'attach_tmp_file_write($tmpfile, $data)') !== FALSE
	|| fail('attachment upload must use the complete-write contract');

class AttachWriteContractStream {
	public $context;
	public static $mode = 'complete';
	public static $contents = '';
	public static $unlinked = FALSE;

	public function stream_open($path, $mode, $options, &$opened_path) {
		self::$contents = '';
		self::$unlinked = FALSE;
		return TRUE;
	}

	public function stream_write($data) {
		if(self::$mode === 'partial' && self::$contents !== '') return 0;
		$length = self::$mode === 'partial' ? min(2, strlen($data)) : strlen($data);
		self::$contents .= substr($data, 0, $length);
		return $length;
	}

	public function stream_stat() {
		return array('size'=>$this->reported_size());
	}

	public function url_stat($path, $flags) {
		return array('size'=>$this->reported_size());
	}

	public function unlink($path) {
		self::$contents = '';
		self::$unlinked = TRUE;
		return TRUE;
	}

	private function reported_size() {
		$size = strlen(self::$contents);
		return self::$mode === 'wrong-size' && $size > 0 ? $size - 1 : $size;
	}
}

stream_wrapper_register('xnattach', 'AttachWriteContractStream') || fail('could not register attachment write-contract fixture');
AttachWriteContractStream::$mode = 'partial';
attach_tmp_file_write('xnattach://partial', 'abcdef') === FALSE
	|| fail('a partial temporary-file write must be rejected');
AttachWriteContractStream::$unlinked && AttachWriteContractStream::$contents === ''
	|| fail('a partial temporary-file write must remove its fragment');
AttachWriteContractStream::$mode = 'wrong-size';
attach_tmp_file_write('xnattach://wrong-size', 'abcdef') === FALSE
	|| fail('a final-size mismatch must be rejected');
AttachWriteContractStream::$unlinked && AttachWriteContractStream::$contents === ''
	|| fail('a final-size mismatch must remove the incomplete file');
stream_wrapper_unregister('xnattach');

$_SESSION = array();
$draft_a = attach_draft_open('', 1000);
$draft_b = attach_draft_open('', 1000);
preg_match('/\A[a-f0-9]{32}\z/D', $draft_a) === 1 || fail('draft A must use an unguessable normalized token');
preg_match('/\A[a-f0-9]{32}\z/D', $draft_b) === 1 || fail('draft B must use an unguessable normalized token');
$draft_a !== $draft_b || fail('separate editor tabs must receive separate draft tokens');

$file_a0 = array('path'=>'a0.tmp', 'url'=>'upload/tmp/a0.txt');
$file_a1 = array('path'=>'a1.tmp', 'url'=>'upload/tmp/a1.txt');
$file_b0 = array('path'=>'b0.tmp', 'url'=>'upload/tmp/b0.txt');
attach_draft_store($draft_a, $file_a0) === 0 || fail('first draft A attachment must receive index zero');
attach_draft_store($draft_a, $file_a1) === 1 || fail('second draft A attachment must receive index one');
attach_draft_store($draft_b, $file_b0) === 0 || fail('draft B attachment indexes must be isolated from draft A');
count(attach_draft_files($draft_a)) === 2 || fail('draft A must expose only its own files');
count(attach_draft_files($draft_b)) === 1 || fail('draft B must expose only its own files');
attach_draft_files($draft_a)[0]['aid'] === '_0' || fail('stored attachment must retain its draft-local public id');

$wrong_remove = attach_draft_remove($draft_b, 1);
$wrong_remove === FALSE || fail('one draft must not delete an attachment owned by another draft');
count(attach_draft_files($draft_a)) === 2 || fail('cross-draft delete attempt must preserve the owner draft');
$removed = attach_draft_remove($draft_a, 0);
is_array($removed) && $removed['path'] === 'a0.tmp' || fail('owner draft must be able to remove its attachment');
attach_draft_store($draft_a, array('path'=>'a2.tmp', 'url'=>'upload/tmp/a2.txt')) === 2 || fail('delete followed by upload must not overwrite a surviving sparse attachment id');
isset(attach_draft_files($draft_a)[1], attach_draft_files($draft_a)[2]) || fail('sparse draft ids must both remain present');

attach_draft_store('', array('path'=>'legacy.tmp', 'url'=>'upload/tmp/legacy.txt')) === 0 || fail('legacy clients without draft tokens must retain an isolated compatibility bucket');
count(attach_draft_files('')) === 1 || fail('legacy compatibility bucket must remain readable');
count(attach_draft_files($draft_b)) === 1 || fail('legacy upload must not contaminate a tokenized draft');

attach_draft_clear($draft_a) === TRUE || fail('owner draft clear must succeed');
attach_draft_files($draft_a) === array() || fail('cleared draft must no longer expose files');
count(attach_draft_files($draft_b)) === 1 || fail('clearing draft A must preserve draft B');
attach_draft_store('invalid-token', array()) === FALSE || fail('invalid draft token must fail closed');
attach_draft_remove('invalid-token', 0) === FALSE || fail('invalid draft delete must fail closed');

$reopened_b = attach_draft_open($draft_b, 1100);
$reopened_b === $draft_b || fail('a registered draft token must be reusable for a same-tab reload');
$_SESSION['attach_drafts'][$draft_b]['updated_at'] === 1100 || fail('reopening a draft must refresh its activity timestamp');
attach_draft_cleanup(1100 + 86401);
attach_draft_files($draft_b) === array() || fail('stale draft metadata must expire with the temporary-file retention boundary');

$_SESSION['attach_drafts'] = array();
$capacity_drafts = array();
for($i = 0; $i < 16; $i++) {
	$capacity_drafts[] = attach_draft_open('', 2000 + $i);
}
attach_draft_store($capacity_drafts[0], array('path'=>'oldest-active.tmp', 'url'=>'upload/tmp/oldest-active.txt')) === 0
	|| fail('capacity fixture must retain an attachment in the oldest active draft');
attach_draft_open('', 2020) === FALSE || fail('a seventeenth active draft must fail explicitly instead of evicting another editor tab');
count($_SESSION['attach_drafts']) === 16 || fail('capacity rejection must preserve all active draft metadata');
count(attach_draft_files($capacity_drafts[0])) === 1 || fail('capacity rejection must preserve the oldest active draft attachment ownership');
attach_draft_open($capacity_drafts[0], 2021) === $capacity_drafts[0] || fail('an existing editor tab must remain reopenable at draft capacity');

$fixture_root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'xiuno-attach-draft-'.getmypid().'-'.bin2hex(random_bytes(4));
$upload_root = $fixture_root.DIRECTORY_SEPARATOR.'upload'.DIRECTORY_SEPARATOR;
foreach(array($upload_root.'tmp', $upload_root.'attach') as $directory) {
	if(!mkdir($directory, 0777, TRUE) && !is_dir($directory)) fail('failed to create attachment association fixture');
}
register_shutdown_function(function() use ($fixture_root) { remove_tree($fixture_root); });

$uid = 7;
$time = 1700000000;
$conf = array('upload_path'=>$upload_root, 'upload_url'=>'upload/', 'attach_dir_save_rule'=>'Ym');
$fixture_attach_rows = array();
$fixture_posts = array();
$fixture_threads = array();
$fixture_copy_fail = array();
$fixture_db_fail = array();
$fixture_delete_fail = array();
$fixture_post_update_fail = FALSE;
$fixture_thread_update_fail = FALSE;
$fixture_find_fail = FALSE;
$fixture_logs = array();
$_SESSION = array();

$inside_attach_dir = $upload_root.'attach'.DIRECTORY_SEPARATOR.'202608';
$sibling_attach_dir = $upload_root.'attach_evil'.DIRECTORY_SEPARATOR.'202608';
mkdir($inside_attach_dir, 0777, TRUE) || fail('failed to create canonical attachment fixture directory');
mkdir($sibling_attach_dir, 0777, TRUE) || fail('failed to create sibling-prefix attachment fixture directory');
file_put_contents($inside_attach_dir.DIRECTORY_SEPARATOR.'inside.txt', 'inside');
file_put_contents($sibling_attach_dir.DIRECTORY_SEPARATOR.'outside.txt', 'outside');
$inside_attach_path = attach_realpath_within($inside_attach_dir.DIRECTORY_SEPARATOR.'inside.txt', $upload_root.'attach');
$inside_attach_path !== FALSE && is_file($inside_attach_path)
	|| fail('canonical attachment containment must accept a file below the attachment root');
attach_realpath_within($sibling_attach_dir.DIRECTORY_SEPARATOR.'outside.txt', $upload_root.'attach') === FALSE
	|| fail('attachment containment must reject a real sibling directory sharing the root prefix');
attach_path(array('filename'=>'202608/inside.txt')) === $inside_attach_path
	|| fail('attachment path resolution must return the canonical contained path');
attach_path(array('filename'=>'../attach_evil/202608/outside.txt')) === ''
	|| fail('attachment path resolution must reject traversal-shaped database values');

function fixture_temp_attach($upload_root, $name, $contents) {
	$path = $upload_root.'tmp'.DIRECTORY_SEPARATOR.$name;
	file_put_contents($path, $contents);
	return array(
		'url'=>'upload/tmp/'.$name,
		'path'=>$path,
		'orgfilename'=>$name,
		'filetype'=>'other',
		'filesize'=>strlen($contents),
		'width'=>0,
		'height'=>0,
		'isimage'=>0,
		'downloads'=>0,
	);
}

$assoc_a = attach_draft_open('', $time);
$assoc_b = attach_draft_open('', $time);
$assoc_a_file = fixture_temp_attach($upload_root, 'draft-a.txt', 'draft-a');
$assoc_b_file = fixture_temp_attach($upload_root, 'draft-b.txt', 'draft-b');
attach_draft_store($assoc_a, $assoc_a_file);
attach_draft_store($assoc_b, $assoc_b_file);
$fixture_posts[10] = array('pid'=>10, 'tid'=>100, 'isfirst'=>1, 'message'=>$assoc_a_file['url'], 'message_fmt'=>$assoc_a_file['url']);
attach_assoc_post(10, $assoc_a) === TRUE || fail('successful association must report success');
count($fixture_attach_rows) === 1 || fail('associating draft A must publish exactly one database attachment');
reset($fixture_attach_rows)['orgfilename'] === 'draft-a.txt' || fail('associating draft A must not consume draft B');
attach_draft_exists($assoc_a) === FALSE || fail('successful association must retire the consumed draft token');
count(attach_draft_files($assoc_b)) === 1 || fail('successful association of draft A must preserve draft B metadata');
is_file($assoc_b_file['path']) || fail('successful association of draft A must preserve draft B temporary file');
strpos($fixture_posts[10]['message_fmt'], 'upload/attach/') !== FALSE || fail('successful association must rewrite the created post URL');
intval($fixture_posts[10]['files']) === 1 && intval($fixture_threads[100]['files']) === 1
	|| fail('successful first-post association must publish visible post and thread file counts');

$copy_fail_draft = attach_draft_open('', $time);
$copy_fail_file = fixture_temp_attach($upload_root, 'copy-fail.txt', 'copy-fail');
$fixture_copy_fail['copy-fail.txt'] = TRUE;
attach_draft_store($copy_fail_draft, $copy_fail_file);
$fixture_posts[11] = array('pid'=>11, 'tid'=>101, 'isfirst'=>0, 'message'=>$copy_fail_file['url'], 'message_fmt'=>$copy_fail_file['url']);
attach_assoc_post(11, $copy_fail_draft) === FALSE || fail('copy failure must be reported instead of publishing a broken attachment');
count($fixture_attach_rows) === 1 || fail('copy failure must not create a database attachment row');
is_file($copy_fail_file['path']) && count(attach_draft_files($copy_fail_draft)) === 1 || fail('copy failure must preserve the source file and retryable draft metadata');

$db_fail_draft = attach_draft_open('', $time);
$db_fail_file = fixture_temp_attach($upload_root, 'db-fail.txt', 'db-fail');
$fixture_db_fail['db-fail.txt'] = TRUE;
attach_draft_store($db_fail_draft, $db_fail_file);
$fixture_posts[12] = array('pid'=>12, 'tid'=>102, 'isfirst'=>0, 'message'=>$db_fail_file['url'], 'message_fmt'=>$db_fail_file['url']);
attach_assoc_post(12, $db_fail_draft) === FALSE || fail('database failure must be reported instead of dropping the draft');
count($fixture_attach_rows) === 1 || fail('database failure must not leave a false attachment row');
is_file($db_fail_file['path']) && count(attach_draft_files($db_fail_draft)) === 1 || fail('database failure must preserve the source file and retryable draft metadata');
$published_db_fail = glob($upload_root.'attach'.DIRECTORY_SEPARATOR.date('Ym', $time).DIRECTORY_SEPARATOR.'db-fail.txt');
empty($published_db_fail) || fail('database failure must remove the unreferenced copied destination');

$rewrite_fail_draft = attach_draft_open('', $time);
$rewrite_fail_file = fixture_temp_attach($upload_root, 'rewrite-fail.txt', 'rewrite-fail');
attach_draft_store($rewrite_fail_draft, $rewrite_fail_file);
$fixture_posts[13] = array('pid'=>13, 'tid'=>103, 'isfirst'=>0, 'message'=>$rewrite_fail_file['url'], 'message_fmt'=>$rewrite_fail_file['url']);
$fixture_post_update_fail = TRUE;
attach_assoc_post(13, $rewrite_fail_draft) === FALSE || fail('post URL rewrite failure must fail the association');
$fixture_post_update_fail = FALSE;
count($fixture_attach_rows) === 1 || fail('post URL rewrite failure must compensate the published attachment row');
is_file($rewrite_fail_file['path']) && count(attach_draft_files($rewrite_fail_draft)) === 1
	|| fail('post URL rewrite failure must preserve the original retryable source and draft');
strpos($fixture_posts[13]['message_fmt'], 'upload/tmp/') !== FALSE || fail('failed URL rewrite must preserve the original post body');
$published_rewrite_fail = glob($upload_root.'attach'.DIRECTORY_SEPARATOR.date('Ym', $time).DIRECTORY_SEPARATOR.'rewrite-fail.txt');
empty($published_rewrite_fail) || fail('post URL rewrite failure must remove the compensated destination');

$count_read_fail_draft = attach_draft_open('', $time);
$count_read_fail_file = fixture_temp_attach($upload_root, 'count-read-fail.txt', 'count-read-fail');
attach_draft_store($count_read_fail_draft, $count_read_fail_file);
$fixture_posts[15] = array('pid'=>15, 'tid'=>105, 'isfirst'=>0, 'message'=>$count_read_fail_file['url'], 'message_fmt'=>$count_read_fail_file['url']);
$fixture_find_fail = TRUE;
attach_assoc_post(15, $count_read_fail_draft) === FALSE || fail('attachment count read failure must fail the association');
$fixture_find_fail = FALSE;
count($fixture_attach_rows) === 1 || fail('attachment count read failure must compensate its published row');
strpos($fixture_posts[15]['message_fmt'], 'upload/tmp/') !== FALSE
	&& is_file($count_read_fail_file['path'])
	&& count(attach_draft_files($count_read_fail_draft)) === 1
	|| fail('attachment count read failure must preserve the original post, source, and draft');
$published_count_read_fail = glob($upload_root.'attach'.DIRECTORY_SEPARATOR.date('Ym', $time).DIRECTORY_SEPARATOR.'count-read-fail.txt');
empty($published_count_read_fail) || fail('attachment count read failure must remove the compensated destination');

$thread_count_fail_draft = attach_draft_open('', $time);
$thread_count_fail_file = fixture_temp_attach($upload_root, 'thread-count-fail.txt', 'thread-count-fail');
attach_draft_store($thread_count_fail_draft, $thread_count_fail_file);
$fixture_posts[16] = array('pid'=>16, 'tid'=>106, 'isfirst'=>1, 'message'=>$thread_count_fail_file['url'], 'message_fmt'=>$thread_count_fail_file['url'], 'images'=>0, 'files'=>0);
$fixture_threads[106] = array('images'=>0, 'files'=>0);
$fixture_thread_update_fail = TRUE;
attach_assoc_post(16, $thread_count_fail_draft) === FALSE || fail('first-post thread count failure must fail the association');
$fixture_thread_update_fail = FALSE;
count($fixture_attach_rows) === 1 || fail('thread count failure must compensate its published row');
$fixture_threads[106] === array('images'=>0, 'files'=>0)
	&& strpos($fixture_posts[16]['message_fmt'], 'upload/tmp/') !== FALSE
	&& is_file($thread_count_fail_file['path'])
	&& count(attach_draft_files($thread_count_fail_draft)) === 1
	|| fail('thread count failure must preserve the original thread, post, source, and draft');
$published_thread_count_fail = glob($upload_root.'attach'.DIRECTORY_SEPARATOR.date('Ym', $time).DIRECTORY_SEPARATOR.'thread-count-fail.txt');
empty($published_thread_count_fail) || fail('thread count failure must remove the compensated destination');

$post_count_fail_draft = attach_draft_open('', $time);
$post_count_fail_file = fixture_temp_attach($upload_root, 'post-count-fail.txt', 'post-count-fail');
attach_draft_store($post_count_fail_draft, $post_count_fail_file);
$fixture_posts[17] = array('pid'=>17, 'tid'=>107, 'isfirst'=>1, 'message'=>'body without an embedded attachment URL', 'message_fmt'=>'body without an embedded attachment URL', 'images'=>0, 'files'=>0);
$fixture_threads[107] = array('images'=>0, 'files'=>0);
$fixture_post_update_fail = TRUE;
attach_assoc_post(17, $post_count_fail_draft) === FALSE || fail('post visibility-count failure must fail the association');
$fixture_post_update_fail = FALSE;
count($fixture_attach_rows) === 1 || fail('post visibility-count failure must compensate its published row');
$fixture_threads[107] === array('images'=>0, 'files'=>0)
	&& intval($fixture_posts[17]['files']) === 0
	&& is_file($post_count_fail_file['path'])
	&& count(attach_draft_files($post_count_fail_draft)) === 1
	|| fail('post count failure must restore the thread count and preserve the post, source, and draft');
$published_post_count_fail = glob($upload_root.'attach'.DIRECTORY_SEPARATOR.date('Ym', $time).DIRECTORY_SEPARATOR.'post-count-fail.txt');
empty($published_post_count_fail) || fail('post visibility-count failure must remove the compensated destination');

$mixed_fail_draft = attach_draft_open('', $time);
$mixed_ok_file = fixture_temp_attach($upload_root, 'mixed-ok.txt', 'mixed-ok');
$mixed_fail_file = fixture_temp_attach($upload_root, 'mixed-fail.txt', 'mixed-fail');
attach_draft_store($mixed_fail_draft, $mixed_ok_file);
attach_draft_store($mixed_fail_draft, $mixed_fail_file);
$fixture_copy_fail['mixed-fail.txt'] = TRUE;
$fixture_posts[14] = array(
	'pid'=>14,
	'tid'=>104,
	'isfirst'=>0,
	'message'=>$mixed_ok_file['url'].' '.$mixed_fail_file['url'],
	'message_fmt'=>$mixed_ok_file['url'].' '.$mixed_fail_file['url'],
);
attach_assoc_post(14, $mixed_fail_draft) === FALSE || fail('one failed file must fail the whole draft association');
count($fixture_attach_rows) === 1 || fail('mixed draft failure must compensate earlier rows from the same attempt');
count(attach_draft_files($mixed_fail_draft)) === 2 && is_file($mixed_ok_file['path']) && is_file($mixed_fail_file['path'])
	|| fail('mixed draft failure must keep every original source retryable');
$published_mixed_ok = glob($upload_root.'attach'.DIRECTORY_SEPARATOR.date('Ym', $time).DIRECTORY_SEPARATOR.'mixed-ok.txt');
empty($published_mixed_ok) || fail('mixed draft failure must remove an earlier compensated destination');

$route = file_get_contents($root.'route/attach.php');
$post_model = file_get_contents($root.'model/post.func.php');
$thread_model = file_get_contents($root.'model/thread.func.php');
$post_template = file_get_contents($root.'view/htm/post.htm');
foreach(array($route, $post_model, $thread_model, $post_template) as $source) {
	$source !== FALSE || fail('failed to read attachment draft integration source');
}
strpos($route, "param('attach_draft'") !== FALSE || fail('upload and delete route must accept the form draft token');
	strpos($route, 'attach_draft_store(') !== FALSE || fail('upload route must store files through the draft ownership helper');
	strpos($route, 'attach_draft_remove(') !== FALSE || fail('delete route must enforce draft ownership through the shared helper');
	strpos($route, "lang('attach_upload_forbidden')") !== FALSE || fail('upload authorization failures must use a localized public message');
	strpos($route, "lang('attach_filetype_not_allowed')") !== FALSE || fail('upload file-type failures must use a localized public message');
	strpos($route, "lang('delete_successfully')") !== FALSE || fail('attachment deletion success must resolve the language key instead of returning its name');
strpos($route, 'strpos($real_path, $safe_dir)') === FALSE || fail('attachment download must not use a sibling-prefix string containment check');
substr_count($post_model, 'if(!attach_assoc_post($pid, $attach_draft))') >= 2 || fail('post create/update must propagate selected-draft association failures');
strpos($thread_model, 'if(!attach_assoc_post($pid, $attach_draft))') !== FALSE || fail('thread create must compensate selected-draft association failures');
	strpos($post_template, 'name="attach_draft"') !== FALSE || fail('the full post form must submit its draft token');
	strpos($post_template, 'attach_draft: attach_draft') !== FALSE || fail('upload and delete requests must carry the same draft token');
	strpos($post_template, "lang('attach_draft_limit_reached')") !== FALSE || fail('draft capacity exhaustion must produce a visible localized error');

echo "OK: attachment draft ownership checks passed\n";
