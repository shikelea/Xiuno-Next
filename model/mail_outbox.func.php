<?php

function mail_outbox_table_name() {
	$db = isset($_SERVER['db']) ? $_SERVER['db'] : NULL;
	$tablepre = is_object($db) && isset($db->tablepre) ? (string)$db->tablepre : '';
	if(!preg_match('/^[A-Za-z0-9_]{0,32}$/D', $tablepre)) return FALSE;
	return $tablepre.'mail_outbox';
}

function mail_outbox_primary_link() {
	$db = isset($_SERVER['db']) ? $_SERVER['db'] : NULL;
	if(!is_object($db) || !is_callable(array($db, 'connect_master'))) return FALSE;
	if(!isset($db->wlink) || !($db->wlink instanceof PDO)) {
		if(!$db->connect_master()) return FALSE;
	}
	return isset($db->wlink) && $db->wlink instanceof PDO ? $db->wlink : FALSE;
}

function mail_outbox_payload_key() {
	$key = xn_key();
	return is_string($key) && $key !== '' ? hash('sha256', "xiuno-mail-outbox\0".$key) : FALSE;
}

function mail_outbox_payload_encode($payload) {
	if(!is_array($payload)) return FALSE;
	$key = mail_outbox_payload_key();
	if($key === FALSE) return FALSE;
	$json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	if(!is_string($json) || $json === '') return FALSE;
	return xn_encrypt($json, $key);
}

function mail_outbox_payload_decode($payload) {
	if(!is_string($payload) || $payload === '') return FALSE;
	$key = mail_outbox_payload_key();
	if($key === FALSE) return FALSE;
	$json = xn_decrypt($payload, $key);
	if(!is_string($json) || $json === '') return FALSE;
	$decoded = json_decode($json, TRUE);
	if(!is_array($decoded)
		|| !isset($decoded['v'], $decoded['smtp'], $decoded['username'], $decoded['email'], $decoded['subject'], $decoded['message'])
		|| intval($decoded['v']) !== 1 || !is_array($decoded['smtp'])) return FALSE;
	foreach(array('host', 'port', 'user', 'pass', 'email') as $key_name) {
		if(!array_key_exists($key_name, $decoded['smtp'])) return FALSE;
	}
	if(!is_string($decoded['email']) || !filter_var($decoded['email'], FILTER_VALIDATE_EMAIL)) return FALSE;
	if(!is_string($decoded['username']) || strlen($decoded['username']) > 255) return FALSE;
	if(!is_string($decoded['subject']) || strlen($decoded['subject']) > 1000) return FALSE;
	if(!is_string($decoded['message']) || strlen($decoded['message']) > 65535) return FALSE;
	$decoded['charset'] = isset($decoded['charset']) && is_string($decoded['charset']) ? $decoded['charset'] : 'UTF-8';
	return $decoded;
}

function mail_outbox_enqueue_reset($smtp, $username, $email, $subject, $message, $now) {
	$now = intval($now);
	if($now <= 0 || !is_array($smtp)) return FALSE;
	$payload = mail_outbox_payload_encode(array(
		'v'=>1,
		'smtp'=>$smtp,
		'username'=>(string)$username,
		'email'=>(string)$email,
		'subject'=>(string)$subject,
		'message'=>(string)$message,
		'charset'=>'UTF-8',
	));
	if($payload === FALSE) return FALSE;
	$link = mail_outbox_primary_link();
	$table = mail_outbox_table_name();
	if($link === FALSE || $table === FALSE) return FALSE;
	try {
		$statement = $link->prepare(
			"INSERT INTO `{$table}` (`kind`, `payload`, `available_at`, `expires_at`, `lease_until`, `lease_token`, `attempts`, `create_date`) "
			."VALUES (?, ?, ?, ?, 0, '', 0, ?)"
		);
		if($statement === FALSE || !$statement->execute(array('user_resetpw', $payload, $now, $now + 300, $now))) return FALSE;
		return $statement->rowCount() === 1;
	} catch(Throwable $e) {
		return FALSE;
	}
}

function mail_outbox_claim($now, $lease_seconds = 60) {
	$now = intval($now);
	$lease_seconds = max(15, min(300, intval($lease_seconds)));
	if($now <= 0) return FALSE;
	$link = mail_outbox_primary_link();
	$table = mail_outbox_table_name();
	if($link === FALSE || $table === FALSE) return FALSE;
	try {
		$cleanup = $link->prepare("DELETE FROM `{$table}` WHERE `expires_at` <= ? AND `lease_until` <= ?");
		if($cleanup === FALSE || !$cleanup->execute(array($now, $now))) return FALSE;
		$token = bin2hex(random_bytes(32));
		$claim = $link->prepare(
			"UPDATE `{$table}` SET `lease_token` = ?, `lease_until` = ?, `attempts` = `attempts` + 1 "
			."WHERE `expires_at` > ? AND `available_at` <= ? AND `lease_until` <= ? "
			."ORDER BY `outbox_id` ASC LIMIT 1"
		);
		if($claim === FALSE || !$claim->execute(array($token, $now + $lease_seconds, $now, $now, $now))) return FALSE;
		if($claim->rowCount() === 0) return NULL;
		if($claim->rowCount() !== 1) return FALSE;
		$read = $link->prepare(
			"SELECT `outbox_id`, `kind`, `payload`, `available_at`, `expires_at`, `lease_until`, `attempts` "
			."FROM `{$table}` WHERE `lease_token` = ? LIMIT 1"
		);
		if($read === FALSE || !$read->execute(array($token))) return FALSE;
		$job = $read->fetch(PDO::FETCH_ASSOC);
		if(!is_array($job)) return FALSE;
		$job['outbox_id'] = intval($job['outbox_id']);
		$job['available_at'] = intval($job['available_at']);
		$job['expires_at'] = intval($job['expires_at']);
		$job['lease_until'] = intval($job['lease_until']);
		$job['attempts'] = intval($job['attempts']);
		$job['lease_token'] = $token;
		return $job;
	} catch(Throwable $e) {
		return FALSE;
	}
}

function mail_outbox_delete_claimed($job) {
	if(!is_array($job) || empty($job['outbox_id']) || empty($job['lease_token'])) return FALSE;
	$link = mail_outbox_primary_link();
	$table = mail_outbox_table_name();
	if($link === FALSE || $table === FALSE) return FALSE;
	try {
		$statement = $link->prepare("DELETE FROM `{$table}` WHERE `outbox_id` = ? AND `lease_token` = ?");
		if($statement === FALSE || !$statement->execute(array(intval($job['outbox_id']), $job['lease_token']))) return FALSE;
		return $statement->rowCount() === 1;
	} catch(Throwable $e) {
		return FALSE;
	}
}

function mail_outbox_retry_delay($attempts) {
	$attempts = max(1, intval($attempts));
	if($attempts === 1) return 15;
	if($attempts === 2) return 60;
	return 180;
}

function mail_outbox_release_claimed($job, $available_at) {
	if(!is_array($job) || empty($job['outbox_id']) || empty($job['lease_token'])) return FALSE;
	$link = mail_outbox_primary_link();
	$table = mail_outbox_table_name();
	if($link === FALSE || $table === FALSE) return FALSE;
	try {
		$statement = $link->prepare(
			"UPDATE `{$table}` SET `available_at` = ?, `lease_until` = 0, `lease_token` = '' "
			."WHERE `outbox_id` = ? AND `lease_token` = ?"
		);
		if($statement === FALSE || !$statement->execute(array(intval($available_at), intval($job['outbox_id']), $job['lease_token']))) return FALSE;
		return $statement->rowCount() === 1;
	} catch(Throwable $e) {
		return FALSE;
	}
}

function mail_outbox_worker_lock_acquire() {
	$link = mail_outbox_primary_link();
	$table = mail_outbox_table_name();
	if($link === FALSE || $table === FALSE) return FALSE;
	try {
		$database = $link->query('SELECT DATABASE()')->fetchColumn();
		if(!is_string($database) || $database === '') return FALSE;
		$lock_name = 'xiuno_mail_'.substr(hash('sha256', $database."\0".$table), 0, 40);
		$statement = $link->prepare('SELECT GET_LOCK(?, 0)');
		if($statement === FALSE || !$statement->execute(array($lock_name))) return FALSE;
		$claimed = $statement->fetchColumn();
		if((string)$claimed === '1') return $lock_name;
		return (string)$claimed === '0' ? NULL : FALSE;
	} catch(Throwable $e) {
		return FALSE;
	}
}

function mail_outbox_worker_lock_release($lock_name) {
	if(!is_string($lock_name) || !preg_match('/^xiuno_mail_[a-f0-9]{40}$/D', $lock_name)) return FALSE;
	$link = mail_outbox_primary_link();
	if($link === FALSE) return FALSE;
	try {
		$statement = $link->prepare('SELECT RELEASE_LOCK(?)');
		if($statement === FALSE || !$statement->execute(array($lock_name))) return FALSE;
		return (string)$statement->fetchColumn() === '1';
	} catch(Throwable $e) {
		return FALSE;
	}
}

function mail_outbox_work_one($sender = NULL, $now = NULL) {
	global $errstr;
	$fixed_now = $now !== NULL;
	$now = $now === NULL ? time() : intval($now);
	$job = mail_outbox_claim($now);
	if($job === NULL) return array('status'=>'empty', 'message'=>'');
	if($job === FALSE) return array('status'=>'error', 'message'=>'Unable to claim a mail outbox task.');
	$payload = mail_outbox_payload_decode($job['payload']);
	if($payload === FALSE || $job['kind'] !== 'user_resetpw') {
		$deleted = mail_outbox_delete_claimed($job);
		return array(
			'status'=>'discarded',
			'message'=>$deleted ? 'Discarded an invalid mail outbox task.' : 'Invalid mail task could not be discarded.',
		);
	}
	$sender = $sender === NULL ? 'xn_send_mail' : $sender;
	if(!is_callable($sender)) {
		if(!mail_outbox_release_claimed($job, $now + mail_outbox_retry_delay($job['attempts']))) {
			return array('status'=>'error', 'message'=>'Mail transport is unavailable and the task could not be released.');
		}
		return array('status'=>'error', 'message'=>'Mail transport is unavailable.');
	}
	$errstr = '';
	try {
		$sent = call_user_func(
			$sender,
			$payload['smtp'],
			$payload['username'],
			$payload['email'],
			$payload['subject'],
			$payload['message'],
			$payload['charset']
		);
	} catch(Throwable $e) {
		$sent = FALSE;
		$errstr = $e->getMessage();
	}
	if($sent === TRUE) {
		if(mail_outbox_delete_claimed($job)) return array('status'=>'sent', 'message'=>'');
		return array(
			'status'=>'error',
			'message'=>'SMTP accepted the message but the outbox task could not be removed; duplicate delivery is possible.',
		);
	}
	$retry_now = $fixed_now ? $now : time();
	$next = $retry_now + mail_outbox_retry_delay($job['attempts']);
	$message = is_string($errstr) && $errstr !== '' ? $errstr : 'SMTP delivery failed.';
	if($next >= intval($job['expires_at'])) {
		if(!mail_outbox_delete_claimed($job)) {
			return array('status'=>'error', 'message'=>'Expired failed mail task could not be discarded.');
		}
		return array('status'=>'discarded', 'message'=>$message);
	}
	if(!mail_outbox_release_claimed($job, $next)) {
		return array('status'=>'error', 'message'=>'Failed mail task could not be released for retry.');
	}
	return array('status'=>'retry', 'message'=>$message);
}

?>
