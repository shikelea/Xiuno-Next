<?php

$root = dirname(__DIR__);

function fail($message) {
	fwrite(STDERR, "FAIL: $message\n");
	exit(1);
}

function read_file_checked($path) {
	$contents = file_get_contents($path);
	$contents === FALSE AND fail("Unable to read $path");
	return $contents;
}

function section_between($source, $start, $end) {
	$start_pos = strpos($source, $start);
	if($start_pos === FALSE) fail("Missing section start: $start");
	$end_pos = strpos($source, $end, $start_pos + strlen($start));
	if($end_pos === FALSE) fail("Missing section end after $start: $end");
	return substr($source, $start_pos, $end_pos - $start_pos);
}

$front_index = read_file_checked($root.'/index.inc.php');
$front_entry = read_file_checked($root.'/index.php');
$admin_index = read_file_checked($root.'/admin/index.inc.php');
$install = read_file_checked($root.'/install/index.php');
$attach = read_file_checked($root.'/route/attach.php');
$attach_model = read_file_checked($root.'/model/attach.func.php');
$my_route = read_file_checked($root.'/route/my.php');
$admin_forum = read_file_checked($root.'/admin/route/forum.php');
$admin_thread = read_file_checked($root.'/admin/route/thread.php');
$admin_route_index = read_file_checked($root.'/admin/route/index.php');
$admin_header = read_file_checked($root.'/admin/view/htm/header_nav.inc.htm');

strpos($front_index, "if(!in_array(\$method, array('GET', 'POST'), TRUE))") !== FALSE
	|| fail('Front controller must reject unsupported HTTP methods before route dispatch.');

strpos($front_entry, "conf/.installed.lock") !== FALSE
	|| fail('Front entry must create the install lock for existing installations.');

strpos($admin_index, "if(!in_array(\$method, array('GET', 'POST'), TRUE))") !== FALSE
	|| fail('Admin controller must reject unsupported HTTP methods before route dispatch.');

strpos($install, "is_file(APP_PATH.'conf/conf.php') || is_file(INSTALL_LOCK_FILE)") !== FALSE
	|| fail('Installer must block all direct access after conf/conf.php or the install lock exists.');

strpos($install, "if(!in_array(\$method, array('GET', 'POST'), TRUE))") !== FALSE
	|| fail('Installer must reject unsupported HTTP methods.');

strpos($install, "define('INSTALL_LOCK_FILE', APP_PATH.'conf/.installed.lock');") !== FALSE
	|| fail('Installer must define an install lock file.');

strpos($install, "file_put_contents(INSTALL_LOCK_FILE") !== FALSE && strpos($install, "lang('write_to_file_failed')") !== FALSE
	|| fail('Installer must write and verify the install lock after successful installation.');

$attach_create = section_between($attach, "if(empty(\$action) || \$action == 'create')", "} elseif(\$action == 'delete')");
strpos($attach_create, "\$method != 'POST' AND message(-1, lang('method_error'));") !== FALSE
	|| fail('Attachment create/upload must require POST.');

$attach_delete = section_between($attach, "} elseif(\$action == 'delete')", "} elseif(\$action == 'download')");
strpos($attach_delete, "\$method != 'POST' AND message(-1, lang('method_error'));") !== FALSE
	|| fail('Attachment delete must require POST.');

strpos($attach_delete, "message(-1, lang('insufficient_privilege'))") !== FALSE
	|| fail('Attachment delete must return an error code on insufficient privilege.');

strpos($attach, "empty(\$thread) AND message(-1, lang('thread_not_exists'));") !== FALSE
	|| fail('Attachment download must stop when the parent thread is missing.');

strpos($attach, "\$attachpath = attach_path(\$attach);") !== FALSE
	|| fail('Attachment download must use canonical attachment path validation.');

strpos($attach_model, 'function attach_filename_safe($filename)') !== FALSE
	|| fail('Attachment model must expose filename validation.');

strpos($attach_model, 'function attach_path($attach)') !== FALSE
	|| fail('Attachment model must expose canonical attachment path resolution.');

$avatar_end = "\n}\n\n// ho".'ok my_'.'end.php';
$avatar = section_between($my_route, "} elseif(\$action == 'avatar')", $avatar_end);
strpos($avatar, "elseif(\$method == 'POST')") !== FALSE
	|| fail('Avatar upload must explicitly require POST.');

strpos($avatar, 'getimagesizefromstring($data)') !== FALSE && strpos($avatar, 'imagepng($image)') !== FALSE
	|| fail('Avatar upload must validate and re-encode image data.');

$forum_delete = section_between($admin_forum, "} elseif(\$action == 'delete')", "function user_names_to_ids");
strpos($forum_delete, "\$method != 'POST' AND message(-1, 'Method Not Allowed');") !== FALSE
	|| fail('Admin forum delete must require POST.');

$thread_scan = section_between($admin_thread, "} elseif(\$action == 'scan')", "} elseif(\$action == 'operation')");
strpos($thread_scan, "\$method != 'POST' AND message(-1, 'Method Not Allowed');") !== FALSE
	|| fail('Admin thread scan must require POST because it writes the queue.');

$thread_operation = section_between($admin_thread, "} elseif(\$action == 'operation')", "} elseif(\$action == 'found')");
strpos($thread_operation, "\$method != 'POST' AND message(-1, 'Method Not Allowed');") !== FALSE
	|| fail('Admin thread batch operation must require POST.');

strpos($thread_operation, "in_array(\$op, array('delete', 'close', 'open'), TRUE)") !== FALSE
	|| fail('Admin thread batch operation must validate op before popping queue items.');

strpos($thread_operation, 'queue_destory($queueid);') !== FALSE
	|| fail('Admin thread batch operation should destroy the queue after it is exhausted.');

strpos($admin_route_index, "\$method != 'POST' AND message(-1, 'Method Not Allowed');") !== FALSE
	|| fail('Admin logout must require POST.');

strpos($admin_header, 'data-method="post"') !== FALSE && strpos($admin_header, "index-logout") !== FALSE
	|| fail('Admin logout link must submit via POST.');

strpos($admin_route_index, 'custom.xiuno.com/version.htm') === FALSE
	|| fail('Admin dashboard must not inject the legacy remote HTTP version script.');

echo "OK: route method safety checks passed\n";
