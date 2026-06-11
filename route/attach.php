<?php

!defined('DEBUG') AND exit('Access Denied.');

$action = param(1);

// hook attach_start.php

if(empty($action) || $action == 'create') {
	
	$user = user_read($uid);
	user_login_check();
	$method != 'POST' AND message(-1, lang('method_error'));
	
	$width = param('width', 0);
	$height = param('height', 0);
	$is_image = param('is_image', 0);
	$name = param('name');

	// 🔒 安全修复：先检查原始 POST 数据大小，防止 base64 解码前内存溢出
	// base64 编码会使数据增大约 33%，需要在解码前检查原始大小
	$raw_data = param('data', '', FALSE, FALSE);
	$raw_size = strlen($raw_data);
	// 限制原始数据 30MB（解码后约 22.5MB），避免超大数据消耗内存
	$raw_size > 31457280 AND message(-1, 'Raw data size too large (max 30MB base64)');

	$data = param_base64('data');

	// hook attach_create_start.php

	// 允许的文件后缀名
	//$types = include _include(APP_PATH.'conf/attach.conf.php');
	//$allowtypes = $types['all'];

	empty($group['allowattach']) AND $gid != 1 AND message(-1, '您无权上传');

	empty($data) AND message(-1, lang('data_is_empty'));
	//$data = base64_decode_file_data($data);
	$size = strlen($data);
	$size > 20480000 AND message(-1, lang('filesize_too_large', array('maxsize'=>'20M', 'size'=>$size)));
	
	// 111.php.shtmll 
	$ext = file_ext($name, 7);
	$filetypes = include APP_PATH.'conf/attach.conf.php';
	!in_array($ext, $filetypes['all']) AND $ext = '_'.$ext;
	
	$tmpanme = $uid.'_'.xn_rand(15).'.'.$ext;
	$tmpfile = $conf['upload_path'].'tmp/'.$tmpanme;
	$tmpurl = $conf['upload_url'].'tmp/'.$tmpanme;
	
	$filetype = attach_type($name, $filetypes);

	// hook attach_create_save_before.php

	file_put_contents($tmpfile, $data) OR message(-1, lang('write_to_file_failed'));

	// 保存到 session，发帖成功以后，关联到帖子。
	// save attach information to session, associate to post after create thread.

	// 🔒 安全修复：移除 sess_restart() 调用，避免并发上传时丢失其他附件数据
	// sess_restart() 会丢弃当前 $_SESSION，导致快速连续上传时只保留最后一个附件
	// 解决方案：直接使用当前 session，利用 PHP session 自动锁机制保证并发安全
	// sess_restart(); // 已移除，避免竞态条件

	empty($_SESSION['tmp_files']) AND $_SESSION['tmp_files'] = array();
	$n = count($_SESSION['tmp_files']);
	$filesize = filesize($tmpfile);
	$attach = array(
		'url'=>$tmpurl,
		'path'=>$tmpfile,
		'orgfilename'=>$name,
		'filetype'=>$filetype,
		'filesize'=>$filesize,
		'width'=>$width,
		'height'=>$height,
		'isimage'=>$is_image,
		'downloads'=>0,
		'aid'=>'_'.$n
	);
	$_SESSION['tmp_files'][$n] = $attach;

	unset($attach['path']);
	
	// hook attach_create_end.php
	
	message(0, $attach);

} elseif($action == 'delete') {
	
	$user = user_read($uid);
	user_login_check();
	$method != 'POST' AND message(-1, lang('method_error'));

	$aid = param(2);
	
	// hook attach_delete_start.php
	
	// 临时的文件 id / temp attach id : _0 _1 _2 _3 ...
	if(substr($aid, 0, 1) == '_') {
		$key = intval(substr($aid, 1));
		$tmp_files = _SESSION('tmp_files');
		!isset($tmp_files[$key]) AND message(-1, lang('item_not_exists', array('item'=>$key)));
		$attach = $tmp_files[$key];
		!is_file($attach['path']) AND message(-1, lang('file_not_exists'));
		unlink($attach['path']);
		unset($_SESSION['tmp_files'][$key]);
	} else {
		$aid = intval($aid);
		$attach = attach_read($aid);
		empty($attach) AND message(-1, lang('attach_not_exists'));
		
		$thread = thread_read($attach['tid']);
		empty($thread) AND message(-1, lang('thread_not_exists'));
		$fid = $thread['fid'];
		
		$allowdelete = forum_access_mod($fid, $gid, 'allowdelete');
		$attach['uid'] != $uid AND !$allowdelete AND message(-1, lang('insufficient_privilege'));
		
		$r = attach_delete($aid);
		$r ===  FALSE AND message(-1, lang('delete_failed'));
	}
	
	// hook attach_delete_end.php
	
	message(0, 'delete_successfully');
	
} elseif($action == 'download') {
	
	// hook attach_download_start.php
	
	// 判断权限
	$aid = param(2, 0);
	$attach = attach_read($aid);
	empty($attach) AND message(-1, lang('attach_not_exists'));
	$tid = $attach['tid'];
	$thread = thread_read($tid);
	empty($thread) AND message(-1, lang('thread_not_exists'));
	$fid = $thread['fid'];
	$allowdown = forum_access_user($fid, $gid, 'allowdown');
	empty($allowdown) AND message(-1, lang('insufficient_privilege_to_download'));

	$attachpath = attach_path($attach);
	$attachurl = $conf['upload_url'].'attach/'.$attach['filename'];
	(empty($attachpath) || !is_file($attachpath)) AND message(-1, lang('attach_not_exists'));

	// 🔒 安全修复：防止路径遍历攻击，确保下载的文件在 upload_path/attach/ 目录内
	// 验证附件路径必须在合法的上传目录内，防止读取任意文件（如 conf/conf.php）
	$real_path = realpath($attachpath);
	$safe_dir = realpath($conf['upload_path'].'attach/');

	// 如果路径不存在或不在安全目录内，拒绝访问
	if (!$real_path || strpos($real_path, $safe_dir) !== 0) {
		message(-1, lang('attach_not_exists'));
	}

	$type = 'php';
	
	// hook attach_output_before.php
	
	// php 输出
	if($type == 'php') {

		attach_update($aid, array('downloads+'=>1));
		
		$filesize = $attach['filesize'];
		$download_filename = attach_download_filename($attach['orgfilename']);
		if(stripos($_SERVER["HTTP_USER_AGENT"], 'MSIE') !== FALSE || stripos($_SERVER["HTTP_USER_AGENT"], 'Edge') !== FALSE || stripos($_SERVER["HTTP_USER_AGENT"], 'Trident') !== FALSE) {
			$download_filename = urlencode($download_filename);
			$download_filename = str_replace("+", "%20", $download_filename);
		}
		$timefmt = date('D, d M Y H:i:s', $time).' GMT';
		header('Date: '.$timefmt);
		header('Last-Modified: '.$timefmt);
		header('Expires: '.$timefmt);
	       // header('Cache-control: max-age=0, must-revalidate, post-check=0, pre-check=0');
		header('Cache-control: max-age=86400');
		header('Content-Transfer-Encoding: binary');
		header("Pragma: public");
		header('Content-Disposition: attachment; filename="'.$download_filename.'"');
		header('Content-Type: application/octet-stream');
		//header("Content-Type: application/force-download");	// 后面的会覆盖前面
		
		// hook attach_download_readfile_before.php
		
		readfile($attachpath);
		
		/*if($attach['filetype'] == 'image') {
			// ie6 下会解析图片内容！
			//header('Content-Disposition: inline; filename='.$attach['orgfilename']);
			//header('Content-Type: image/pjpeg');
		} else {
			header('Content-Disposition: attachment; filename='.$attach['orgfilename']);
			header('Content-Type: application/octet-stream');
		}*/
		exit;
	} else {
		
		// hook attach_download_location_before.php
		
		http_location($attachurl);
	}
}

// hook attach_end.php

?>
