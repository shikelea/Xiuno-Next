<?php

$root = dirname(__DIR__);

function avatar_publish_fail($message) {
	fwrite(STDERR, "FAIL: $message\n");
	exit(1);
}

function avatar_publish_reset_target($target, $content) {
	file_put_contents($target, $content, LOCK_EX) === strlen($content)
		|| avatar_publish_fail('Unable to reset the avatar publication fixture.');
}

function avatar_publish_assert_clean($target, $label) {
	$stages = glob($target.'.avatar-*.tmp');
	is_array($stages) && empty($stages)
		|| avatar_publish_fail($label.' left an owned avatar staging file behind.');
}

function avatar_publish_remove_fixture($directory) {
	if(!is_dir($directory)) return;
	$items = scandir($directory);
	if(!is_array($items)) return;
	foreach($items as $item) {
		if($item === '.' || $item === '..') continue;
		$path = $directory.'/'.$item;
		if(is_dir($path) && !is_link($path)) {
			avatar_publish_remove_fixture($path);
		} else {
			@unlink($path);
		}
	}
	@rmdir($directory);
}

defined('DEBUG') || define('DEBUG', 1);
$uid = 42;
$method = 'GET';
$time = 1700000000;
$header = array();
$conf = array();
$avatar_publish_update_count = 0;
$avatar_publish_expected_target = '';
$avatar_publish_expected_data = '';
$avatar_publish_update_result = TRUE;

function param($key, $default = NULL, $htmlspecialchars = TRUE) {
	return $key === 1 ? '__avatar_publish_guard__' : $default;
}

function user_read($uid, $primary = FALSE) {
	return array('uid'=>$uid, 'username'=>'fixture');
}

function user_read_primary_proven($uid) {
	return user_read($uid, TRUE);
}

function user_login_check() {
	return TRUE;
}

function url($route) {
	return $route;
}

function user_update($uid, $update) {
	global $avatar_publish_update_count, $avatar_publish_expected_target,
		$avatar_publish_expected_data, $avatar_publish_update_result;
	$avatar_publish_update_count++;
	if($avatar_publish_expected_target !== '') {
		$published = @file_get_contents($avatar_publish_expected_target);
		$published === $avatar_publish_expected_data
			|| avatar_publish_fail('user_update() ran before the complete avatar became the published target.');
	}
	return $avatar_publish_update_result;
}

require $root.'/route/my.php';

function_exists('my_avatar_file_publish_atomic')
	|| avatar_publish_fail('Avatar upload must expose the atomic file publication boundary.');
function_exists('my_avatar_file_save')
	|| avatar_publish_fail('Avatar upload must gate user_update() behind successful file publication.');

$route_source = file_get_contents($root.'/route/my.php');
$route_source !== FALSE || avatar_publish_fail('Unable to read the avatar route source.');
$avatar_start = strpos($route_source, "} elseif(\$action == 'avatar')");
$avatar_end = strpos($route_source, '// hook my_end.php', $avatar_start === FALSE ? 0 : $avatar_start);
$avatar_start !== FALSE && $avatar_end !== FALSE
	|| avatar_publish_fail('Unable to isolate the avatar route.');
$avatar_route = substr($route_source, $avatar_start, $avatar_end - $avatar_start);
strpos($avatar_route, 'my_avatar_file_save(') !== FALSE
	|| avatar_publish_fail('The avatar POST route must use the guarded publication helper.');
strpos($avatar_route, 'file_put_contents($path.$filename, $data)') === FALSE
	|| avatar_publish_fail('The avatar POST route must not write directly to the published target.');

$fixture = sys_get_temp_dir().'/xiuno-avatar-publish-'.getmypid().'-'.bin2hex(random_bytes(5));
mkdir($fixture, 0777, TRUE) || avatar_publish_fail('Unable to create the avatar publication fixture.');
$target = $fixture.'/42.png';
$old_avatar = 'existing-avatar-bytes';
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZVZcAAAAASUVORK5CYII=', TRUE);
is_string($png) && !empty($png) || avatar_publish_fail('Unable to decode the PNG fixture.');
$png_info = getimagesizefromstring($png);
is_array($png_info) && isset($png_info[2]) && (int)$png_info[2] === IMAGETYPE_PNG
	|| avatar_publish_fail('The avatar publication fixture is not a valid PNG.');

try {
	// A positive short fwrite() is not a failure: the writer must continue until every byte is staged.
	avatar_publish_reset_target($target, $old_avatar);
	$avatar_publish_update_count = 0;
	$avatar_publish_expected_target = $target;
	$avatar_publish_expected_data = $png;
	$partial_calls = 0;
	$partial_write = function($handle, $chunk) use (&$partial_calls) {
		$partial_calls++;
		return fwrite($handle, substr($chunk, 0, min(7, strlen($chunk))));
	};
	my_avatar_file_save(42, $target, $png, 1700000001, array('write'=>$partial_write)) === TRUE
		|| avatar_publish_fail('A sequence of positive short writes did not publish successfully.');
	$partial_calls > 1 || avatar_publish_fail('The complete writer did not retry a positive short write.');
	$avatar_publish_update_count === 1
		|| avatar_publish_fail('A successful avatar publication must update the user exactly once.');
	file_get_contents($target) === $png
		|| avatar_publish_fail('Successful avatar publication changed the PNG bytes.');
	avatar_publish_assert_clean($target, 'Successful publication');

	// A writer that stops after a partial prefix must fail, preserve the old target, and never update DB state.
	avatar_publish_reset_target($target, $old_avatar);
	$avatar_publish_update_count = 0;
	$stalled = FALSE;
	$stalled_write = function($handle, $chunk) use (&$stalled) {
		if($stalled) return 0;
		$stalled = TRUE;
		return fwrite($handle, substr($chunk, 0, min(9, strlen($chunk))));
	};
	my_avatar_file_save(42, $target, $png, 1700000002, array('write'=>$stalled_write)) === FALSE
		|| avatar_publish_fail('A stalled partial write was accepted.');
	$avatar_publish_update_count === 0
		|| avatar_publish_fail('A stalled partial write reached user_update().');
	file_get_contents($target) === $old_avatar
		|| avatar_publish_fail('A stalled partial write replaced the old avatar.');
	avatar_publish_assert_clean($target, 'Stalled partial write');

	// A lying writer can report all bytes while writing fewer; the independent size check must reject it.
	avatar_publish_reset_target($target, $old_avatar);
	$avatar_publish_update_count = 0;
	$short_write = function($handle, $chunk) {
		$actual = max(0, strlen($chunk) - 5);
		$actual > 0 && fwrite($handle, substr($chunk, 0, $actual)) === $actual
			|| avatar_publish_fail('Unable to create the short-write fixture.');
		return strlen($chunk);
	};
	my_avatar_file_save(42, $target, $png, 1700000003, array('write'=>$short_write)) === FALSE
		|| avatar_publish_fail('A staged avatar with the wrong byte size was accepted.');
	$avatar_publish_update_count === 0
		|| avatar_publish_fail('A staged size mismatch reached user_update().');
	file_get_contents($target) === $old_avatar
		|| avatar_publish_fail('A staged size mismatch replaced the old avatar.');
	avatar_publish_assert_clean($target, 'Staged size mismatch');

	// Same-length corruption defeats a size-only check; the staged bytes and PNG format must also be verified.
	avatar_publish_reset_target($target, $old_avatar);
	$avatar_publish_update_count = 0;
	$corrupt_write = function($handle, $chunk) {
		$corrupt = strlen($chunk) > 0 ? "X".substr($chunk, 1) : $chunk;
		return fwrite($handle, $corrupt);
	};
	my_avatar_file_save(42, $target, $png, 1700000004, array('write'=>$corrupt_write)) === FALSE
		|| avatar_publish_fail('A same-length corrupted PNG was accepted.');
	$avatar_publish_update_count === 0
		|| avatar_publish_fail('A corrupted PNG reached user_update().');
	file_get_contents($target) === $old_avatar
		|| avatar_publish_fail('A corrupted PNG replaced the old avatar.');
	avatar_publish_assert_clean($target, 'Corrupted PNG');

	// The final same-directory replacement is the commit point; failure leaves the old target intact.
	avatar_publish_reset_target($target, $old_avatar);
	$avatar_publish_update_count = 0;
	$replace_attempts = 0;
	$replace_fail = function($source, $destination) use (&$replace_attempts) {
		$replace_attempts++;
		return FALSE;
	};
	my_avatar_file_save(42, $target, $png, 1700000005, array('replace'=>$replace_fail)) === FALSE
		|| avatar_publish_fail('A failed atomic replacement was accepted.');
	$replace_attempts === 1 || avatar_publish_fail('The final avatar replacement was not attempted exactly once.');
	$avatar_publish_update_count === 0
		|| avatar_publish_fail('A failed atomic replacement reached user_update().');
	file_get_contents($target) === $old_avatar
		|| avatar_publish_fail('A failed atomic replacement did not preserve the old avatar.');
	avatar_publish_assert_clean($target, 'Failed atomic replacement');

	// The file is published before the database cache-buster update. A controlled DB failure must
	// compensate that publication so callers never receive failure with a silently changed avatar.
	avatar_publish_reset_target($target, $old_avatar);
	$avatar_publish_update_count = 0;
	$avatar_publish_update_result = FALSE;
	my_avatar_file_save(42, $target, $png, 1700000006) === FALSE
		|| avatar_publish_fail('A failed avatar database update was accepted.');
	$avatar_publish_update_count === 1
		|| avatar_publish_fail('The database failure fixture did not execute user_update() exactly once.');
	file_get_contents($target) === $old_avatar
		|| avatar_publish_fail('A failed avatar database update did not restore the previous avatar bytes.');
	avatar_publish_assert_clean($target, 'Failed database update rollback');

	@unlink($target);
	$avatar_publish_update_count = 0;
	my_avatar_file_save(42, $target, $png, 1700000007) === FALSE
		|| avatar_publish_fail('A failed first-avatar database update was accepted.');
	!file_exists($target)
		|| avatar_publish_fail('A failed first-avatar database update left a published file behind.');
	avatar_publish_assert_clean($target, 'Failed first-avatar database update rollback');
	$avatar_publish_update_result = TRUE;
} finally {
	avatar_publish_remove_fixture($fixture);
}

echo "OK: avatar atomic publication safety checks passed\n";

?>
