<?php

// This recovery utility lives below the web root for historical reasons. Never bootstrap the
// application for an HTTP request: doing so would expose an unauthenticated administrator reset.
if(PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit('Command-line access only.');
}

$uid_arg = isset($argv[1]) ? (string)$argv[1] : '';
if(!preg_match('/^[1-9]\d*$/D', $uid_arg)) {
	fwrite(STDERR, "Usage: php tool/resetpw.php <uid> [new-password]\n");
	exit(2);
}

$generated = !isset($argv[2]);
if($generated) {
	$password_plain = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
} else {
	$password_plain = (string)$argv[2];
	if($password_plain === '') {
		fwrite(STDERR, "The new password must not be empty.\n");
		exit(2);
	}
}

$repo_root = dirname(__DIR__);
chdir($repo_root);
define('SKIP_ROUTE', 1);
include $repo_root.'/index.php';

$target_uid = intval($uid_arg);
$user = user_read_primary_proven($target_uid);
if(empty($user)) {
	fwrite(STDERR, "User not found: $target_uid\n");
	exit(1);
}

// Browser and API authentication first normalize the plain password to this MD5 digest for legacy
// wire compatibility, then bcrypt that digest at rest. The recovery path must use the same contract.
$password_hash = user_hash_password(md5($password_plain));
$new_auth_epoch = user_password_commit($target_uid, $password_hash, array('salt'=>''));
if($new_auth_epoch === FALSE) {
	fwrite(STDERR, "Password reset failed for uid $target_uid. No success was reported.\n");
	exit(1);
}

$username = isset($user['username']) ? $user['username'] : (string)$target_uid;
fwrite(STDOUT, "Password reset succeeded for {$username} (uid {$target_uid}); auth epoch {$new_auth_epoch}.\n");
if($generated) fwrite(STDOUT, "Generated password: {$password_plain}\n");

?>
