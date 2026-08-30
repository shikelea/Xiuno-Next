<?php

// hook model_attach_start.php

// ------------> 最原生的 CURD，无关联其他数据。

function attach__create($arr) {
	// hook model_attach__create_start.php
	$r = db_create('attach', $arr);
	// hook model_attach__create_end.php
	return $r;
}

function attach__update($aid, $arr) {
	// hook model_attach__update_start.php
	$r = db_update('attach', array('aid'=>$aid), $arr);
	// hook model_attach__update_end.php
	return $r;
}

function attach__read($aid) {
	// hook model_attach__read_start.php
	$attach = db_find_one('attach', array('aid'=>$aid));
	// hook model_attach__read_end.php
	return $attach;
}

function attach__delete($aid) {
	// hook model_attach__delete_start.php
	$r = db_delete('attach', array('aid'=>$aid));
	// hook model_attach__delete_end.php
	return $r;
}

function attach__find($cond = array(), $orderby = array(), $page = 1, $pagesize = 20) {
	// hook model_attach__find_start.php
	$attachlist = db_find('attach', $cond, $orderby, $page, $pagesize);
	// hook model_attach__find_end.php
	return $attachlist;
}

// ------------> 关联 CURD，主要是强相关的数据，比如缓存。弱相关的大量数据需要另外处理。

function attach_create($arr) {
	// hook model_attach_create_start.php
	$r = attach__create($arr);
	// hook model_attach_create_end.php
	return $r;
}

function attach_update($aid, $arr) {
	// hook model_attach_update_start.php
	$r = attach__update($aid, $arr);
	// hook model_attach_update_end.php
	return $r;
}

function attach_read($aid) {
	// hook model_attach_read_start.php
	$attach = attach__read($aid);
	attach_format($attach);
	// hook model_attach_read_end.php
	return $attach;
}

function attach_delete($aid) {
	// hook model_attach_delete_start.php
	global $conf;
	$attach = attach_read($aid);
	if(empty($attach)) return FALSE;
	$path = attach_path($attach);
	file_exists($path) AND unlink($path);
	
	$r = attach__delete($aid);
	// hook model_attach_delete_end.php
	return $r;
}

function attach_delete_by_pid($pid) {
	global $conf;
	list($attachlist, $imagelist, $filelist) = attach_find_by_pid($pid);
	// hook model_attach_delete_by_pid_start.php
	foreach($attachlist as $attach) {
		$path = attach_path($attach);
		file_exists($path) AND unlink($path);
		attach__delete($attach['aid']);
	}
	// hook model_attach_delete_by_pid_end.php
	return count($attachlist);
}

function attach_delete_by_uid($uid) {
	global $conf;
	// hook model_attach_delete_by_uid_start.php
	$attachlist = db_find('attach', array('uid'=>$uid), array(), 1, 9000);
	foreach ($attachlist as $attach) {
		$path = attach_path($attach);
		file_exists($path) AND unlink($path);
		attach__delete($attach['aid']);
	}
	// hook model_attach_delete_by_uid_end.php
}

function attach_find($cond = array(), $orderby = array(), $page = 1, $pagesize = 20) {
	// hook model_attach_find_start.php
	$attachlist = attach__find($cond, $orderby, $page, $pagesize);
	if($attachlist) foreach ($attachlist as &$attach) attach_format($attach);
	// hook model_attach_find_end.php
	return $attachlist;
}

// 获取 $filelist $imagelist
function attach_find_by_pid($pid) {
	$attachlist = $imagelist = $filelist = array();
	// hook model_attach_find_by_pid_start.php
	$attachlist = attach__find(array('pid'=>$pid), array(), 1, 1000);
	if($attachlist) {
		foreach ($attachlist as $attach) {
			attach_format($attach);
			$attach['isimage'] ? ($imagelist[] = $attach) : ($filelist[] = $attach);
		}
	}
	// hook model_attach_find_by_pid_end.php
	return array($attachlist, $imagelist, $filelist);
}

// ------------> 其他方法

function attach_format(&$attach) {
	global $conf;
	if(empty($attach)) return;
	// hook model_attach_format_start.php
	$filename = isset($attach['filename']) ? $attach['filename'] : '';
	$attach['create_date_fmt'] = date('Y-n-j', $attach['create_date']);
	$attach['url'] = attach_filename_safe($filename) ? $conf['upload_url'].'attach/'.$filename : '';
	// hook model_attach_format_end.php
}

function attach_count($cond = array()) {
	// hook model_attach_count_start.php
	$cond = db_cond_to_sqladd($cond);
	$n = db_count('attach', $cond);
	// hook model_attach_count_end.php
	return $n;
}

function attach_type($name, $types) {
	// hook model_attach_type_start.php
	$ext = file_ext($name);
	foreach($types as $type=>$exts) {
		if($type == 'all') continue;
		if(in_array($ext, $exts)) {
			return $type;
		}
	}
	// hook model_attach_type_end.php
	return 'other';
}

function attach_orgfilename_error($filename, $max_length = 120) {
	if(!is_string($filename) || $filename === '') return 'attach_filename_invalid';
	if(preg_match('//u', $filename) !== 1) return 'attach_filename_invalid';
	if(preg_match('/\p{Cc}/u', $filename) === 1) return 'attach_filename_invalid';
	if(strpos($filename, '/') !== FALSE || strpos($filename, '\\') !== FALSE) return 'attach_filename_invalid';

	$max_length = intval($max_length);
	if($max_length <= 0) return 'attach_filename_invalid';
	if(function_exists('mb_strlen')) {
		$length = mb_strlen($filename, 'UTF-8');
	} else {
		$length = preg_match_all('/./us', $filename, $matches);
	}
	if($length === FALSE) return 'attach_filename_invalid';
	return $length > $max_length ? 'attach_filename_too_long' : '';
}

function attach_tmp_file_write($path, $data) {
	if(!is_string($path) || $path === '' || !is_string($data)) return FALSE;
	$expected_size = strlen($data);
	$written_size = @file_put_contents($path, $data);
	if($written_size !== $expected_size) {
		@unlink($path);
		return FALSE;
	}
	clearstatcache(TRUE, $path);
	$stored_size = @filesize($path);
	if($stored_size !== $expected_size) {
		@unlink($path);
		return FALSE;
	}
	return $stored_size;
}

function attach_download_filename($filename) {
	$filename = preg_replace('/[\x00-\x1F\x7F]+/', '', (string)$filename);
	$filename = str_replace(array('"', '\\'), array("'", '_'), $filename);
	return $filename === '' ? 'download' : $filename;
}

function attach_filename_safe($filename) {
	$filename = str_replace('\\', '/', (string)$filename);
	if($filename === '' || strpos($filename, "\0") !== FALSE) return FALSE;
	if(strpos($filename, '../') !== FALSE || substr($filename, 0, 1) == '/') return FALSE;
	if(strpos($filename, ':') !== FALSE) return FALSE;
	return preg_match('#^[0-9]{4,8}/[A-Za-z0-9_]+\.[A-Za-z0-9_]+$#', $filename) === 1;
}

function attach_realpath_within($path, $directory) {
	$real_path = realpath($path);
	$real_directory = realpath($directory);
	if($real_path === FALSE || $real_directory === FALSE) return FALSE;
	$real_path = str_replace('\\', '/', $real_path);
	$real_directory = rtrim(str_replace('\\', '/', $real_directory), '/');
	$compare_path = $real_path;
	$compare_directory = $real_directory;
	if(DIRECTORY_SEPARATOR === '\\') {
		$compare_path = strtolower($compare_path);
		$compare_directory = strtolower($compare_directory);
	}
	if($compare_path !== $compare_directory && strpos($compare_path, $compare_directory.'/') !== 0) return FALSE;
	return $real_path;
}

function attach_path($attach) {
	global $conf;
	if(empty($attach['filename']) || !attach_filename_safe($attach['filename'])) return '';
	$path = attach_realpath_within($conf['upload_path'].'attach/'.$attach['filename'], $conf['upload_path'].'attach');
	return $path === FALSE ? '' : $path;
}

function attach_draft_token_normalize($token) {
	$token = is_string($token) ? strtolower($token) : '';
	if($token === '') return '';
	return preg_match('/\A[a-f0-9]{32}\z/D', $token) === 1 ? $token : FALSE;
}

function attach_draft_cleanup($now = NULL) {
	$now = $now === NULL ? time() : intval($now);
	if(!isset($_SESSION['attach_drafts']) || !is_array($_SESSION['attach_drafts'])) $_SESSION['attach_drafts'] = array();
	foreach($_SESSION['attach_drafts'] as $token=>$draft) {
		$updated_at = is_array($draft) && isset($draft['updated_at']) ? intval($draft['updated_at']) : 0;
		$normalized_token = attach_draft_token_normalize($token);
		if($normalized_token === FALSE || $normalized_token === '' || $updated_at <= 0 || $now - $updated_at > 86400) {
			unset($_SESSION['attach_drafts'][$token]);
		}
	}
}

function attach_draft_open($candidate = '', $now = NULL) {
	$now = $now === NULL ? time() : intval($now);
	attach_draft_cleanup($now);
	$candidate = attach_draft_token_normalize($candidate);
	if($candidate !== FALSE && $candidate !== '' && isset($_SESSION['attach_drafts'][$candidate])) {
		$_SESSION['attach_drafts'][$candidate]['updated_at'] = $now;
		return $candidate;
	}
	// Never evict an active editor draft implicitly: its temporary files would remain on disk while
	// the form loses ownership metadata. Existing tabs may reopen at capacity; only a new draft is
	// rejected until an active draft is submitted, cleared, or expires.
	if(count($_SESSION['attach_drafts']) >= 16) return FALSE;
	do {
		$token = function_exists('random_bytes') ? bin2hex(random_bytes(16)) : md5(uniqid('', TRUE).mt_rand());
	} while(isset($_SESSION['attach_drafts'][$token]));
	$_SESSION['attach_drafts'][$token] = array('created_at'=>$now, 'updated_at'=>$now, 'files'=>array());
	return $token;
}

function attach_draft_files($token = '') {
	$token = attach_draft_token_normalize($token);
	if($token === FALSE) return array();
	if($token === '') return (array)_SESSION('tmp_files', array());
	if(!isset($_SESSION['attach_drafts'][$token]) || !is_array($_SESSION['attach_drafts'][$token])) return array();
	$files = isset($_SESSION['attach_drafts'][$token]['files']) ? $_SESSION['attach_drafts'][$token]['files'] : array();
	return is_array($files) ? $files : array();
}

function attach_draft_exists($token = '') {
	$token = attach_draft_token_normalize($token);
	if($token === FALSE) return FALSE;
	if($token === '') return TRUE;
	return isset($_SESSION['attach_drafts'][$token]) && is_array($_SESSION['attach_drafts'][$token]);
}

function attach_draft_file($token, $key) {
	$files = attach_draft_files($token);
	$key = intval($key);
	return isset($files[$key]) && is_array($files[$key]) ? $files[$key] : FALSE;
}

function attach_draft_store($token, $attach) {
	$token = attach_draft_token_normalize($token);
	if($token === FALSE || !is_array($attach)) return FALSE;
	if($token === '') {
		if(!isset($_SESSION['tmp_files']) || !is_array($_SESSION['tmp_files'])) $_SESSION['tmp_files'] = array();
		$files =& $_SESSION['tmp_files'];
	} else {
		if(!isset($_SESSION['attach_drafts'][$token]) || !is_array($_SESSION['attach_drafts'][$token])) return FALSE;
		if(!isset($_SESSION['attach_drafts'][$token]['files']) || !is_array($_SESSION['attach_drafts'][$token]['files'])) $_SESSION['attach_drafts'][$token]['files'] = array();
		$files =& $_SESSION['attach_drafts'][$token]['files'];
		$_SESSION['attach_drafts'][$token]['updated_at'] = time();
	}
	$key = -1;
	foreach(array_keys($files) as $existing_key) {
		if(is_int($existing_key) || preg_match('/\A\d+\z/D', (string)$existing_key) === 1) $key = max($key, intval($existing_key));
	}
	$key++;
	$attach['aid'] = '_'.$key;
	$files[$key] = $attach;
	return $key;
}

function attach_draft_remove($token, $key) {
	$token = attach_draft_token_normalize($token);
	$key = intval($key);
	if($token === FALSE) return FALSE;
	if($token === '') {
		if(!isset($_SESSION['tmp_files'][$key])) return FALSE;
		$attach = $_SESSION['tmp_files'][$key];
		unset($_SESSION['tmp_files'][$key]);
		return $attach;
	}
	if(!isset($_SESSION['attach_drafts'][$token]['files'][$key])) return FALSE;
	$attach = $_SESSION['attach_drafts'][$token]['files'][$key];
	unset($_SESSION['attach_drafts'][$token]['files'][$key]);
	$_SESSION['attach_drafts'][$token]['updated_at'] = time();
	return $attach;
}

function attach_draft_replace($token, $files) {
	$token = attach_draft_token_normalize($token);
	$files = is_array($files) ? $files : array();
	if($token === FALSE) return FALSE;
	if($token === '') {
		$_SESSION['tmp_files'] = $files;
		return TRUE;
	}
	if(!isset($_SESSION['attach_drafts'][$token])) return FALSE;
	$_SESSION['attach_drafts'][$token]['files'] = $files;
	$_SESSION['attach_drafts'][$token]['updated_at'] = time();
	return TRUE;
}

function attach_draft_clear($token) {
	$token = attach_draft_token_normalize($token);
	if($token === FALSE) return FALSE;
	if($token === '') {
		$_SESSION['tmp_files'] = array();
		return TRUE;
	}
	if(!isset($_SESSION['attach_drafts'][$token])) return FALSE;
	unset($_SESSION['attach_drafts'][$token]);
	return TRUE;
}

// 扫描垃圾的附件，每日清理一次
function attach_gc() {
	global $time, $conf;
	// hook model_attach_gc_start.php
	$tmpfiles = glob($conf['upload_path'].'tmp/*.*');
	if(is_array($tmpfiles)) {
		foreach($tmpfiles as $file) {
			// 清理超过一天还没处理的临时文件
			if($time - filemtime($file) > 86400) {
				unlink($file);
			}
		}
	}
	// hook model_attach_gc_end.php
}

function attach_assoc_post_rollback($published, $pid, $tid) {
	$ok = TRUE;
	foreach(array_reverse($published) as $item) {
		$aid = isset($item['aid']) ? intval($item['aid']) : 0;
		$destfile = isset($item['destfile']) ? $item['destfile'] : '';
		if($aid <= 0 || !attach__delete($aid)) {
			xn_log("attach association rollback delete failed, aid:$aid, pid:$pid, tid:$tid", 'php_error');
			$ok = FALSE;
			continue;
		}
		if($destfile !== '' && is_file($destfile) && !@unlink($destfile)) {
			xn_log("attach association rollback unlink failed, aid:$aid, pid:$pid, tid:$tid", 'php_error');
			$ok = FALSE;
		}
	}
	return $ok;
}

// Associate one editor draft atomically: a failed copy, attachment row, or message rewrite keeps
// every original draft source retryable and compensates all files/rows published by this attempt.
function attach_assoc_post($pid, $attach_draft = '') {
	global $uid, $time, $conf;
	$sess_tmp_files = attach_draft_files($attach_draft);
	//if(empty($tmp_files)) return;
	
	$post = post__read($pid);
	if(empty($post)) return FALSE;
	$post_original = array(
		'message'=>isset($post['message']) ? $post['message'] : '',
		'message_fmt'=>isset($post['message_fmt']) ? $post['message_fmt'] : '',
		'images'=>isset($post['images']) ? intval($post['images']) : 0,
		'files'=>isset($post['files']) ? intval($post['files']) : 0,
	);
	
	// hook attach_assoc_post_start.php
	
	$tid = $post['tid'];
	
	// 把临时文件 upload/tmp/xxx.xxx 也处理了
	//preg_match_all('#src="upload/tmp/(\w+\.\w+)"#', $post_original['message_fmt'], $m);
	//$use_tmp_files = $m[1]; // 实际使用的临时文件，不用的全部删除？如果是两个帖子一起编辑？
	
	// 将 session 中的数据和 message 中的数据合并。
	//$tmp_files = array_unique(array_merge($sess_tmp_files, $use_tmp_files));
	
	$attach_dir_save_rule = array_value($conf, 'attach_dir_save_rule', 'Ym');
	
	$tmp_files = $sess_tmp_files;
	$published = array();
	$publish_failed = FALSE;
	if($tmp_files) {
		foreach($tmp_files as $tmp_key=>$file) {
			
			// 将文件移动到 upload/attach 目录
			$filename = file_name($file['url']);
			
			$day = date($attach_dir_save_rule, $time);
			$path = $conf['upload_path'].'attach/'.$day;
			$url = $conf['upload_url'].'attach/'.$day;
			if(!is_dir($path) && (!mkdir($path, 0777, TRUE) && !is_dir($path))) {
				xn_log("mkdir($path) failed, pid:$pid, tid:$tid", 'php_error');
				$publish_failed = TRUE;
				break;
			}
			
			$destfile = $path.'/'.$filename;
			$desturl = $url.'/'.$filename;
			$source_size = is_file($file['path']) ? filesize($file['path']) : FALSE;
			$r = $source_size !== FALSE && xn_copy($file['path'], $destfile);
			$copy_complete = $r && is_file($destfile) && filesize($destfile) === $source_size;
			if(!$copy_complete) {
				is_file($destfile) AND @unlink($destfile);
				xn_log("xn_copy($file[path]), $destfile) failed, pid:$pid, tid:$tid", 'php_error');
				$publish_failed = TRUE;
				break;
			}
			$arr = array(
				'tid'=>$tid,
				'pid'=>$pid,
				'uid'=>$uid,
				'filesize'=>$file['filesize'],
				'width'=>$file['width'],
				'height'=>$file['height'],
				'filename'=>"$day/$filename",
				'orgfilename'=>$file['orgfilename'],
				'filetype'=>$file['filetype'],
				'create_date'=>$time,
				'comment'=>'',
				'downloads'=>0,
				'isimage'=>$file['isimage']
			);
			
			// 插入后，进行关联
			$aid = attach_create($arr);
			if(!$aid) {
				@unlink($destfile);
				xn_log("attach_create() failed, pid:$pid, tid:$tid", 'php_error');
				$publish_failed = TRUE;
				break;
			}
			$published[] = array('aid'=>$aid, 'destfile'=>$destfile, 'source'=>$file['path']);
			$post['message'] = str_replace($file['url'], $desturl, $post['message']);
			$post['message_fmt'] = str_replace($file['url'], $desturl, $post['message_fmt']);
			
		}
	}

	if($publish_failed) {
		attach_assoc_post_rollback($published, $pid, $tid);
		attach_draft_replace($attach_draft, $sess_tmp_files);
		return FALSE;
	}
	
	// The attachment rows are not visible through the normal post renderer until the post counters
	// are published. Keep the original draft sources and ownership metadata until every derived row
	// has committed, so a failed count/read cannot be reported as a successful post with hidden files.
	list($attachlist, $imagelist, $filelist) = attach_find_by_pid($pid);
	if($attachlist === FALSE) {
		xn_log("post attachment count read failed, pid:$pid, tid:$tid", 'php_error');
		attach_assoc_post_rollback($published, $pid, $tid);
		attach_draft_replace($attach_draft, $sess_tmp_files);
		return FALSE;
	}
	$images = count($imagelist);
	$files = count($filelist);

	// The thread row is only a derived first-post aggregate. Publish it before the authoritative
	// post row so its failure is still fully reversible without changing the post body or counters.
	if(!empty($post['isfirst']) && thread__update($tid, array('images'=>$images, 'files'=>$files)) === FALSE) {
		xn_log("thread attachment count update failed, pid:$pid, tid:$tid", 'php_error');
		attach_assoc_post_rollback($published, $pid, $tid);
		attach_draft_replace($attach_draft, $sess_tmp_files);
		return FALSE;
	}

	$post_update = array('images'=>$images, 'files'=>$files);
	if($post_original['message'] !== $post['message'] || $post_original['message_fmt'] !== $post['message_fmt']) {
		$post_update['message'] = $post['message'];
		$post_update['message_fmt'] = $post['message_fmt'];
	}
	if(post__update($pid, $post_update) === FALSE) {
		xn_log("post attachment publish update failed, pid:$pid, tid:$tid", 'php_error');
		if(!empty($post['isfirst'])
			&& thread__update($tid, array('images'=>$post_original['images'], 'files'=>$post_original['files'])) === FALSE) {
			xn_log("thread attachment count rollback failed, pid:$pid, tid:$tid", 'php_error');
		}
		attach_assoc_post_rollback($published, $pid, $tid);
		attach_draft_replace($attach_draft, $sess_tmp_files);
		return FALSE;
	}

	foreach($published as $item) {
		if(is_file($item['source']) && !@unlink($item['source'])) {
			xn_log("temporary attachment cleanup failed, pid:$pid, tid:$tid", 'php_error');
		}
	}
	attach_draft_clear($attach_draft);
	
	// 处理不在 message 中的图片，删除掉没有插入的图片附件
	/*
	list($attachlist, $imagelist, $filelist) = attach_find_by_pid($pid);
	foreach($imagelist as $k=>$attach) {
		$url = $conf['upload_url'].'attach/'.$attach['filename'];
		if(strpos($post['message_fmt'], $url) === FALSE) {
			unset($imagelist[$k]);
			attach_delete($attach['aid']);
		}
	}
	*/
	
	// hook attach_assoc_post_end.php
	
	return TRUE;
}


// hook model_attach_end.php

?>
