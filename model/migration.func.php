<?php

// CLI schema coordination. The migration and upgrade commands load this helper explicitly so the
// web request bootstrap does not pay for command-only state or database advisory locks.
$g_migration_advisory_lock = NULL;
$g_migration_advisory_lock_shutdown_registered = FALSE;

function migration_driver_type() {
	$conf = isset($GLOBALS['conf']) && is_array($GLOBALS['conf']) ? $GLOBALS['conf'] : array();
	return isset($conf['db']['type']) && is_string($conf['db']['type'])
		? strtolower($conf['db']['type'])
		: '';
}

function migration_table_prefix() {
	$db = isset($_SERVER['db']) ? $_SERVER['db'] : NULL;
	$tablepre = is_object($db) && isset($db->tablepre) ? (string)$db->tablepre : '';
	if(!preg_match('/^[A-Za-z0-9_]{0,32}$/D', $tablepre)) {
		throw new RuntimeException('Invalid table prefix in database configuration.');
	}
	return $tablepre;
}

// Runtime migrations currently contain MySQL DDL. Keeping the old SQLite runtime class available
// is separate from claiming that the schema migration dialect and locking contract support it.
function migration_capability() {
	$driver = migration_driver_type();
	if($driver !== 'pdo_mysql') {
		if($driver === 'pdo_sqlite') {
			$error = 'pdo_sqlite database migrations are not supported by Xiuno Next 4.5.3; no database or configuration changes were made.';
		} elseif($driver === '') {
			$error = 'The configured database driver could not be identified; no database or configuration changes were made.';
		} else {
			$error = 'Database migrations require pdo_mysql; configured driver '.$driver.' is not supported and no database or configuration changes were made.';
		}
		return array('ok'=>FALSE, 'driver'=>$driver, 'error'=>$error);
	}

	$db = isset($_SERVER['db']) ? $_SERVER['db'] : NULL;
	if(!is_object($db)) {
		return array('ok'=>FALSE, 'driver'=>$driver, 'error'=>'The pdo_mysql database runtime is unavailable; no database or configuration changes were made.');
	}
	try {
		migration_table_prefix();
	} catch(Throwable $e) {
		return array('ok'=>FALSE, 'driver'=>$driver, 'error'=>$e->getMessage());
	}
	return array('ok'=>TRUE, 'driver'=>$driver, 'error'=>'');
}

function migration_advisory_lock_name($database, $tablepre) {
	$database = (string)$database;
	$tablepre = (string)$tablepre;
	if($database === '') throw new RuntimeException('Unable to identify the target database before migration.');
	if(!preg_match('/^[A-Za-z0-9_]{0,32}$/D', $tablepre)) throw new RuntimeException('Invalid table prefix in database configuration.');
	return 'xiuno_schema_'.substr(hash('sha256', $database."\0".$tablepre), 0, 40);
}

function migration_advisory_lock_start() {
	global $g_migration_advisory_lock, $g_migration_advisory_lock_shutdown_registered;
	if(!empty($g_migration_advisory_lock)) {
		return array('ok'=>FALSE, 'error'=>'The schema migration lock is already held by this process.');
	}

	$capability = migration_capability();
	if(empty($capability['ok'])) return $capability;
	$db = $_SERVER['db'];
	$link = NULL;
	$lock_name = '';
	$acquired = FALSE;
	try {
		if(!isset($db->wlink) || !($db->wlink instanceof PDO)) {
			if(!is_callable(array($db, 'connect_master'))) {
				return array('ok'=>FALSE, 'error'=>'Unable to use the pdo_mysql write connection for the schema migration lock.');
			}
			if(!$db->connect_master()) {
				return array('ok'=>FALSE, 'error'=>'Unable to connect to the pdo_mysql primary database for the schema migration lock.');
			}
		}
		if(!isset($db->wlink) || !($db->wlink instanceof PDO)) {
			return array('ok'=>FALSE, 'error'=>'Unable to use the pdo_mysql write connection for the schema migration lock.');
		}

		$link = $db->wlink;
		$database_statement = $link->query('SELECT DATABASE()');
		if($database_statement === FALSE) {
			return array('ok'=>FALSE, 'error'=>'Unable to identify the target database before migration.');
		}
		$database = $database_statement->fetchColumn();
		$database_statement->closeCursor();
		$lock_name = migration_advisory_lock_name($database, migration_table_prefix());

		$statement = $link->prepare('SELECT GET_LOCK(?, 0)');
		if($statement === FALSE || !$statement->execute(array($lock_name))) {
			return array('ok'=>FALSE, 'error'=>'Unable to acquire the schema migration lock.');
		}
		$acquired = $statement->fetchColumn();
		$statement->closeCursor();
		if(intval($acquired) !== 1) {
			return array('ok'=>FALSE, 'error'=>'Another migrate, upgrade, or update process is already changing this schema.');
		}
		$acquired = TRUE;

		$g_migration_advisory_lock = array('active'=>TRUE, 'link'=>$link, 'name'=>$lock_name);
		if(!$g_migration_advisory_lock_shutdown_registered) {
			register_shutdown_function('migration_advisory_lock_end');
			$g_migration_advisory_lock_shutdown_registered = TRUE;
		}
		return array('ok'=>TRUE, 'error'=>'', 'name'=>$lock_name);
	} catch(Throwable $e) {
		if($acquired && $link instanceof PDO && $lock_name !== '') {
			try {
				$release = $link->prepare('SELECT RELEASE_LOCK(?)');
				if($release) {
					$release->execute(array($lock_name));
					$release->closeCursor();
				}
			} catch(Throwable $ignored) {
			}
		}
		return array('ok'=>FALSE, 'error'=>'Unable to acquire the schema migration lock: '.$e->getMessage());
	}
}

function migration_advisory_lock_is_held() {
	global $g_migration_advisory_lock;
	return is_array($g_migration_advisory_lock)
		&& !empty($g_migration_advisory_lock['active'])
		&& !empty($g_migration_advisory_lock['name']);
}

function migration_advisory_lock_end() {
	global $g_migration_advisory_lock;
	if(!migration_advisory_lock_is_held()) {
		$g_migration_advisory_lock = NULL;
		return TRUE;
	}
	$guard = $g_migration_advisory_lock;
	$g_migration_advisory_lock = NULL;
	try {
		if(!isset($guard['link']) || !($guard['link'] instanceof PDO)) return FALSE;
		$statement = $guard['link']->prepare('SELECT RELEASE_LOCK(?)');
		if($statement === FALSE || !$statement->execute(array($guard['name']))) return FALSE;
		$released = $statement->fetchColumn();
		$statement->closeCursor();
		return intval($released) === 1;
	} catch(Throwable $e) {
		return FALSE;
	}
}

function migration_record_read_primary() {
	if(!function_exists('kv__get')) throw new RuntimeException('primary migration record reader is unavailable');
	$value = kv__get('xn_migrations', TRUE);
	if($value === FALSE) throw new RuntimeException('primary migration record read failed');
	if($value === NULL) return array();
	if(!is_array($value)) throw new RuntimeException('migration record is not an array');

	$executed = array();
	foreach($value as $name) {
		if(!is_string($name) || $name === '') throw new RuntimeException('migration record contains an invalid name');
		if(!in_array($name, $executed, TRUE)) $executed[] = $name;
	}
	return $executed;
}

function migration_record_append_locked($name, &$executed) {
	if(!migration_advisory_lock_is_held()) throw new RuntimeException('migration record append requires the shared schema lock');
	if(!is_string($name) || $name === '') throw new RuntimeException('migration name is invalid');

	$fresh = migration_record_read_primary();
	if(in_array($name, $fresh, TRUE)) {
		$executed = $fresh;
		return TRUE;
	}
	$next = $fresh;
	$next[] = $name;
	if(!function_exists('kv_set') || kv_set('xn_migrations', $next) === FALSE) {
		throw new RuntimeException('Schema may already have changed, but the migration record write failed; repair the database/KV error and retry the idempotent migration.');
	}

	try {
		$verified = migration_record_read_primary();
	} catch(Throwable $e) {
		throw new RuntimeException('Schema may already have changed, but the migration record could not be verified from the primary database; repair the database/KV error and retry the idempotent migration.', 0, $e);
	}
	if(!in_array($name, $verified, TRUE)) {
		throw new RuntimeException('Schema may already have changed, but the migration record was not visible on the primary database; repair the database/KV error and retry the idempotent migration.');
	}
	$executed = $verified;
	return TRUE;
}

?>
