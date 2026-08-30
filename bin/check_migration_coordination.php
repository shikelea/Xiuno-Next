<?php

$root = dirname(__DIR__);

function fail($message) {
	fwrite(STDERR, "FAIL: $message\n");
	exit(1);
}

function expect_throw($callback, $needle, $message) {
	try {
		$callback();
	} catch(Throwable $e) {
		strpos($e->getMessage(), $needle) !== FALSE || fail($message.' Wrong error: '.$e->getMessage());
		return;
	}
	fail($message.' No exception was thrown.');
}

$conf = array('db'=>array('type'=>'pdo_mysql'));
$GLOBALS['conf'] = $conf;
$_SERVER['db'] = (object)array('tablepre'=>'bbs_');

$migrationKvValue = NULL;
$migrationKvReadSequence = array();
$migrationKvReads = array();
$migrationKvWrites = array();
$migrationKvWriteFailure = FALSE;
$migrationKvPersistWrites = TRUE;

function kv__get($key, $primary = FALSE) {
	global $migrationKvValue, $migrationKvReadSequence, $migrationKvReads;
	$migrationKvReads[] = array($key, $primary);
	if(!empty($migrationKvReadSequence)) return array_shift($migrationKvReadSequence);
	return $migrationKvValue;
}

function kv_set($key, $value, $life = 0) {
	global $migrationKvValue, $migrationKvWrites, $migrationKvWriteFailure, $migrationKvPersistWrites;
	$migrationKvWrites[] = array($key, $value, $life);
	if($migrationKvWriteFailure) return FALSE;
	if($migrationKvPersistWrites) $migrationKvValue = $value;
	return TRUE;
}

require $root.'/model/migration.func.php';

function reset_migration_kv($value = NULL) {
	global $migrationKvValue, $migrationKvReadSequence, $migrationKvReads, $migrationKvWrites;
	global $migrationKvWriteFailure, $migrationKvPersistWrites, $g_migration_advisory_lock;
	$migrationKvValue = $value;
	$migrationKvReadSequence = array();
	$migrationKvReads = array();
	$migrationKvWrites = array();
	$migrationKvWriteFailure = FALSE;
	$migrationKvPersistWrites = TRUE;
	$g_migration_advisory_lock = NULL;
}

$conf['db']['type'] = 'pdo_sqlite';
$capability = migration_capability();
empty($capability['ok']) && strpos($capability['error'], 'not supported') !== FALSE
	|| fail('pdo_sqlite must be rejected explicitly before migration side effects.');
$conf['db']['type'] = 'mysql';
$capability = migration_capability();
empty($capability['ok']) && strpos($capability['error'], 'require pdo_mysql') !== FALSE
	|| fail('Legacy mysql migration execution must be rejected explicitly.');
$conf['db']['type'] = 'unknown_driver';
empty(migration_capability()['ok']) || fail('Unknown database drivers must be rejected.');
$conf['db']['type'] = 'pdo_mysql';
!empty(migration_capability()['ok']) || fail('A valid pdo_mysql runtime and table prefix must pass capability preflight.');
$_SERVER['db']->tablepre = 'bad-prefix!';
empty(migration_capability()['ok']) || fail('An invalid table prefix must fail capability preflight.');
$_SERVER['db']->tablepre = 'bbs_';

$lockName = migration_advisory_lock_name('xiuno_test', 'bbs_');
strlen($lockName) <= 64 && strpos($lockName, 'xiuno_schema_') === 0
	|| fail('Schema advisory lock names must be deterministic and fit the MySQL limit.');

reset_migration_kv(NULL);
migration_record_read_primary() === array() || fail('A missing migration record must normalize to an empty list.');
$migrationKvReads === array(array('xn_migrations', TRUE))
	|| fail('Migration records must be read from the primary database explicitly.');

reset_migration_kv(array('0001', '0001', '0002'));
migration_record_read_primary() === array('0001', '0002')
	|| fail('Valid migration records must be strictly deduplicated without losing order.');

foreach(array(FALSE, 'corrupt', array('0001', ''), array('0001', 2)) as $invalidRecord) {
	reset_migration_kv($invalidRecord);
	expect_throw(
		function() { migration_record_read_primary(); },
		'migration record',
		'Invalid or failed primary migration records must fail closed.'
	);
}

reset_migration_kv(array('0001'));
$executed = array();
expect_throw(
	function() use (&$executed) { migration_record_append_locked('0002', $executed); },
	'requires the shared schema lock',
	'Appending without the shared schema lock must be rejected.'
);
empty($migrationKvWrites) || fail('An unlocked migration append must not write KV state.');

reset_migration_kv(array('0001'));
$g_migration_advisory_lock = array('active'=>TRUE, 'name'=>'test-lock', 'link'=>NULL);
$executed = array('stale-client-entry');
migration_record_append_locked('0002', $executed);
$executed === array('0001', '0002')
	|| fail('Locked append must merge from the latest primary record, not caller state.');
count($migrationKvWrites) === 1 && $migrationKvWrites[0][1] === array('0001', '0002')
	|| fail('Locked append must write the merged primary record exactly once.');
count($migrationKvReads) === 2
	|| fail('Locked append must read before the write and verify from primary afterward.');

reset_migration_kv(array('0001', '0002'));
$g_migration_advisory_lock = array('active'=>TRUE, 'name'=>'test-lock', 'link'=>NULL);
$executed = array();
migration_record_append_locked('0002', $executed);
empty($migrationKvWrites) && $executed === array('0001', '0002')
	|| fail('A migration already recorded by another locked process must be a no-op.');

reset_migration_kv(array('0001'));
$g_migration_advisory_lock = array('active'=>TRUE, 'name'=>'test-lock', 'link'=>NULL);
$migrationKvWriteFailure = TRUE;
$executed = array('client');
expect_throw(
	function() use (&$executed) { migration_record_append_locked('0002', $executed); },
	'Schema may already have changed',
	'A migration record write failure must expose the forward-retry boundary.'
);
$executed === array('client') || fail('A failed append must not publish unverified caller state.');

reset_migration_kv(array('0001'));
$g_migration_advisory_lock = array('active'=>TRUE, 'name'=>'test-lock', 'link'=>NULL);
$migrationKvReadSequence = array(array('0001'), FALSE);
$executed = array();
expect_throw(
	function() use (&$executed) { migration_record_append_locked('0002', $executed); },
	'could not be verified',
	'A post-write primary read failure must fail closed.'
);

reset_migration_kv(array('0001'));
$g_migration_advisory_lock = array('active'=>TRUE, 'name'=>'test-lock', 'link'=>NULL);
$migrationKvPersistWrites = FALSE;
$executed = array();
expect_throw(
	function() use (&$executed) { migration_record_append_locked('0002', $executed); },
	'was not visible',
	'A write whose value is absent on primary readback must fail closed.'
);

$helperSource = file_get_contents($root.'/model/migration.func.php');
$migrateSource = file_get_contents($root.'/src/Console/Command/MigrateCommand.php');
$upgradeSource = file_get_contents($root.'/src/Console/Command/UpgradeCommand.php');
strpos($helperSource, "prepare('SELECT GET_LOCK(?, 0)')") !== FALSE
	&& strpos($helperSource, "prepare('SELECT RELEASE_LOCK(?)')") !== FALSE
	&& strpos($helperSource, "isset(\$db->wlink) || !(\$db->wlink instanceof PDO)") !== FALSE
	|| fail('The schema lock must use GET_LOCK/RELEASE_LOCK on the pdo_mysql write connection.');
foreach(array($migrateSource, $upgradeSource) as $commandSource) {
	strpos($commandSource, 'migration_capability()') !== FALSE
		&& strpos($commandSource, 'migration_advisory_lock_start()') !== FALSE
		&& strpos($commandSource, 'migration_advisory_lock_end()') !== FALSE
		&& strpos($commandSource, "model/migration.func.php") !== FALSE
		|| fail('Migrate and upgrade must share capability, lock, and migration record helpers.');
}
strpos($upgradeSource, "kv_get('xn_migrations'") === FALSE
	&& strpos($upgradeSource, 'db_sql_find("SHOW COLUMNS') === FALSE
	&& strpos($upgradeSource, "kv__get('xn_upgraded_from', true)") !== FALSE
	&& strpos($upgradeSource, "hash_file('sha256', \$confFile)") !== FALSE
	|| fail('Upgrade must use primary fail-closed state and revalidate its configuration snapshot.');

$schemaRows = array();
$schemaReads = 0;
$schemaWrites = 0;
$schemaWriteFailure = FALSE;
function db_sql_find_one_master($sql) {
	global $schemaRows, $schemaReads;
	$schemaReads++;
	return empty($schemaRows) ? FALSE : array_shift($schemaRows);
}
function db_exec($sql) {
	global $schemaWrites, $schemaWriteFailure;
	$schemaWrites++;
	return $schemaWriteFailure ? FALSE : 1;
}
function reset_password_schema($rows, $writeFailure = FALSE) {
	global $schemaRows, $schemaReads, $schemaWrites, $schemaWriteFailure;
	$schemaRows = $rows;
	$schemaReads = 0;
	$schemaWrites = 0;
	$schemaWriteFailure = $writeFailure;
}

$targetPasswordRow = array('Type'=>'varchar(255)', 'Null'=>'NO', 'Default'=>'');
$legacyPasswordRow = array('Type'=>'char(32)', 'Null'=>'NO', 'Default'=>'');
$passwordMigration = require $root.'/database/migrations/0001_alter_user_password_field.php';

reset_password_schema(array($targetPasswordRow));
$passwordMigration->up('bbs_');
$schemaReads === 1 && $schemaWrites === 0
	|| fail('A password column already satisfying the target must be an idempotent no-op.');

reset_password_schema(array($legacyPasswordRow, $targetPasswordRow));
$passwordMigration->up('bbs_');
$schemaReads === 2 && $schemaWrites === 1
	|| fail('Password migration must inspect, alter once, and verify the postcondition.');

reset_password_schema(array(FALSE));
expect_throw(function() use ($passwordMigration) { $passwordMigration->up('bbs_'); }, 'Failed to inspect', 'Schema read failures must stop 0001.');
$schemaWrites === 0 || fail('A failed schema preflight must not execute ALTER.');

reset_password_schema(array(NULL));
expect_throw(function() use ($passwordMigration) { $passwordMigration->up('bbs_'); }, 'missing', 'A missing password field must stop 0001.');

reset_password_schema(array($legacyPasswordRow), TRUE);
expect_throw(function() use ($passwordMigration) { $passwordMigration->up('bbs_'); }, 'Failed to alter', 'ALTER failure must stop 0001.');

reset_password_schema(array($legacyPasswordRow, $legacyPasswordRow));
expect_throw(function() use ($passwordMigration) { $passwordMigration->up('bbs_'); }, 'postcondition', 'A failed password-field postcondition must stop 0001.');

reset_password_schema(array($legacyPasswordRow));
expect_throw(function() use ($passwordMigration) { $passwordMigration->up('bad-prefix!'); }, 'Invalid table prefix', '0001 must reject unsafe table prefixes.');
$schemaReads === 0 && $schemaWrites === 0 || fail('Invalid prefixes must be rejected before database access.');

$targetAuthEpochRow = array('Type'=>'int unsigned', 'Null'=>'NO', 'Default'=>'0');
$legacyDisplayWidthAuthEpochRow = array('Type'=>'int(11) unsigned', 'Null'=>'NO', 'Default'=>'0');
$invalidAuthEpochRow = array('Type'=>'varchar(32)', 'Null'=>'YES', 'Default'=>NULL);
$authEpochMigration = require $root.'/database/migrations/0002_add_user_auth_epoch.php';

reset_password_schema(array(NULL, $targetAuthEpochRow));
$authEpochMigration->up('bbs_');
$schemaReads === 2 && $schemaWrites === 1
	|| fail('Auth-epoch migration must inspect, add once, and verify the exact postcondition.');

foreach(array($targetAuthEpochRow, $legacyDisplayWidthAuthEpochRow) as $validAuthEpochRow) {
	reset_password_schema(array($validAuthEpochRow));
	$authEpochMigration->up('bbs_');
	$schemaReads === 1 && $schemaWrites === 0
		|| fail('An auth_epoch column satisfying the target must be an idempotent no-op.');
}

reset_password_schema(array($invalidAuthEpochRow));
expect_throw(
	function() use ($authEpochMigration) { $authEpochMigration->up('bbs_'); },
	'does not satisfy',
	'An existing auth_epoch column must not be assumed compatible from its name alone.'
);
$schemaWrites === 0 || fail('Schema drift must fail before auth_epoch DDL.');

reset_password_schema(array(NULL, $invalidAuthEpochRow));
expect_throw(
	function() use ($authEpochMigration) { $authEpochMigration->up('bbs_'); },
	'postcondition',
	'Auth-epoch migration must verify type, nullability, and default after ALTER.'
);
$schemaReads === 2 && $schemaWrites === 1
	|| fail('Auth-epoch postcondition failure must occur after one inspected ALTER.');

reset_password_schema(array(FALSE));
expect_throw(
	function() use ($authEpochMigration) { $authEpochMigration->up('bbs_'); },
	'Failed to inspect',
	'Auth-epoch primary schema read failures must fail closed.'
);
$schemaWrites === 0 || fail('A failed auth_epoch schema read must not execute ALTER.');

echo "OK: migration coordination and idempotency checks passed\n";
