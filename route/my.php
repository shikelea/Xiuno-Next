<?php

!defined('DEBUG') AND exit('Access Denied.');

// The operations seam is only for deterministic filesystem fault injection. Production callers
// use the native complete writer and same-directory rename below.
function my_avatar_file_publish_atomic($target, $data, $operations = array(), $require_png = TRUE) {
	if(!is_string($target) || $target === '' || !is_string($data) || ($require_png && $data === '')) return FALSE;
	$directory = dirname($target);
	if(!is_dir($directory)) return FALSE;

	$write = isset($operations['write']) ? $operations['write'] : 'fwrite';
	$replace = isset($operations['replace']) ? $operations['replace'] : 'rename';
	if(!is_callable($write) || !is_callable($replace)) return FALSE;
	$length = strlen($data);
	if($length > 2048000) return FALSE;

	try {
		$suffix = bin2hex(random_bytes(8));
	} catch(Throwable $e) {
		return FALSE;
	}
	$stage = $target.'.avatar-'.getmypid().'-'.$suffix.'.tmp';
	$handle = @fopen($stage, 'xb');
	if($handle === FALSE) return FALSE;

	$offset = 0;
	$write_ok = FALSE;
	try {
		while($offset < $length) {
			$chunk = substr($data, $offset);
			$written = @call_user_func($write, $handle, $chunk);
			if(!is_int($written) || $written <= 0 || $written > strlen($chunk)) break;
			$offset += $written;
		}
		$write_ok = $offset === $length && @fflush($handle);
	} catch(Throwable $e) {
		$write_ok = FALSE;
	}
	$close_ok = @fclose($handle);
	if(!$write_ok || !$close_ok) {
		@unlink($stage);
		return FALSE;
	}

	clearstatcache(TRUE, $stage);
	$staged_size = @filesize($stage);
	$staged_data = @file_get_contents($stage);
	$staged_info = $require_png && is_string($staged_data) && function_exists('getimagesizefromstring')
		? @getimagesizefromstring($staged_data)
		: FALSE;
	$stage_valid = $staged_size === $length
		&& $staged_data === $data
		&& (!$require_png || (is_array($staged_info)
			&& isset($staged_info[2])
			&& (int)$staged_info[2] === IMAGETYPE_PNG));
	if(!$stage_valid) {
		@unlink($stage);
		return FALSE;
	}

	try {
		$published = @call_user_func($replace, $stage, $target) === TRUE;
	} catch(Throwable $e) {
		$published = FALSE;
	}
	if(!$published) {
		@unlink($stage);
		return FALSE;
	}
	clearstatcache(TRUE, $target);
	return TRUE;
}

function my_avatar_file_save($uid, $target, $data, $avatar_time, $operations = array()) {
	if(is_link($target)) return FALSE;
	$had_previous = is_file($target);
	$previous = $had_previous ? @file_get_contents($target) : '';
	if($had_previous && (!is_string($previous) || strlen($previous) > 2048000)) return FALSE;
	if(!my_avatar_file_publish_atomic($target, $data, $operations)) return FALSE;
	try {
		$updated = user_update($uid, array('avatar'=>$avatar_time)) !== FALSE;
	} catch(Throwable $e) {
		$updated = FALSE;
	}
	if($updated) return TRUE;

	// File and database state cannot share one transaction. Compensate every controlled DB failure
	// by atomically restoring the exact previous bytes (or removing a first-time upload), and make a
	// failed compensation visible in the server log instead of claiming a clean rollback.
	$restored = $had_previous
		? my_avatar_file_publish_atomic($target, $previous, array(), FALSE)
		: (@unlink($target) || !file_exists($target));
	if(!$restored) @error_log('xiuno: avatar database update failed and file compensation also failed uid='.intval($uid));
	return FALSE;
}

$action = param(1);

// hook my_start.php

$user = user_read_primary_proven($uid);
user_login_check();

$header['mobile_title'] = $user['username'];
$header['mobile_link'] = url("my");

is_numeric($action) AND $action = '';

$active = $action;

// hook my_action_before.php

if(empty($action)) {
	
	$header['title'] = lang('my_home');
	
	
	
	include _include(APP_PATH.'view/htm/my.htm');
	
/*	
} elseif($action == 'profile') {
	
	if($ajax) {
		// user_safe_info($user);
		message(0, $user);
	} else {
		include _include(APP_PATH.'view/htm/my_profile.htm');
	}
*/
	
} elseif($action == 'password') {
	$header['title'] = lang('modify_password');
	$header['mobile_title'] = lang('modify_password');
	
	if($method == 'GET') {
		
		// hook my_password_get_start.php
		
		include _include(APP_PATH.'view/htm/my_password.htm');
		
	} elseif($method == 'POST') {
		
		// hook my_password_post_start.php
		
		$password_old = param('password_old');
		$password_new = param('password_new');
		$password_new_repeat = param('password_new_repeat');
		$password_new_repeat != $password_new AND message(-1, lang('repeat_password_incorrect'));
		!is_password($password_new, $err) AND message('password_new', $err);
		!user_verify_password($password_old, $user) AND message('password_old', lang('old_password_incorrect'));
		$password_new = user_hash_password($password_new);
		$new_auth_epoch = user_password_change_verified($uid, $password_old, $password_new);
		$new_auth_epoch === FALSE AND message(-1, lang('password_modify_failed'));
		if(!sess_regenerate_id()) {
			user_token_clear();
			message(-1, 'Password changed, but the current session could not be renewed. Please sign in again.');
		}
		user_session_auth_bind($uid, $new_auth_epoch);
		user_token_set($uid, $new_auth_epoch);
		
		// hook my_password_post_end.php
		message(0, lang('password_modify_successfully'));
		
	}
	

} elseif($action == 'thread') {
	$header['title'] = lang('my_thread');
	$header['mobile_title'] = lang('my_thread');

	// hook my_thread_start.php
	
	$page = param(2, 1);
	$pagesize = 20;
	$totalnum = $user['threads'];
	
	// hook my_profile_thread_list_before.php
	
	$pagination = pagination(url('my-thread-{page}'), $totalnum, $page, $pagesize);
	$threadlist = mythread_find_by_uid($uid, $page, $pagesize);
	
	// hook my_thread_end.php
	
	include _include(APP_PATH.'view/htm/my_thread.htm');

	
} elseif($action == 'avatar') {
	$header['title'] = lang('modify_avatar');
	$header['mobile_title'] = lang('modify_avatar');
	
	if($method == 'GET') {
		
		// hook my_avatar_get_start.php
		
		include _include(APP_PATH.'view/htm/my_avatar.htm');
	
	} elseif($method == 'POST') {
		
		// hook my_avatar_post_start.php
		
		$width = param('width');
		$height = param('height');
		$data = param('data', '', FALSE);
		
		empty($data) AND message(-1, lang('data_is_empty'));
		$data = base64_decode_file_data($data);
		$size = strlen($data);
		$size > 2048000 AND message(-1, lang('filesize_too_large', array('maxsize'=>'2M', 'size'=>$size)));
		!function_exists('getimagesizefromstring') AND message(-1, 'Image library unavailable');
		$info = getimagesizefromstring($data);
		empty($info) AND message(-1, lang('doc_type_not_supported'));
		!function_exists('imagecreatefromstring') AND message(-1, 'Image library unavailable');
		$image = imagecreatefromstring($data);
		empty($image) AND message(-1, lang('doc_type_not_supported'));
		imagealphablending($image, FALSE);
		imagesavealpha($image, TRUE);
		ob_start();
		$r = imagepng($image);
		$data = ob_get_clean();
		imagedestroy($image);
		(!$r || empty($data)) AND message(-1, lang('doc_type_not_supported'));
		strlen($data) > 2048000 AND message(-1, lang('filesize_too_large', array('maxsize'=>'2M', 'size'=>strlen($data))));
		
		$filename = "$uid.png";
		$dir = substr(sprintf("%09d", $uid), 0, 3).'/';
		$path = $conf['upload_path'].'avatar/'.$dir;
		$url = $conf['upload_url'].'avatar/'.$dir.$filename;
		!is_dir($path) AND (mkdir($path, 0777, TRUE) OR message(-2, lang('directory_create_failed')));
		
		// hook my_avatar_post_save_before.php
		my_avatar_file_save($uid, $path.$filename, $data, $time) OR message(-1, lang('write_to_file_failed'));
		
		// hook my_avatar_post_end.php
		
		message(0, array('url'=>$url));
		
	}
}

// hook my_end.php

?>
