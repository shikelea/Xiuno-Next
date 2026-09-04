<?php

$root = realpath(__DIR__.'/..');
if($root === FALSE) {
	fwrite(STDERR, "FAIL: unable to resolve repository root\n");
	exit(1);
}

function fail_upgrade_config($message) {
	fwrite(STDERR, "FAIL: $message\n");
	exit(1);
}

function expect_upgrade_config_throw($callback, $needle, $message) {
	try {
		$callback();
	} catch(Throwable $e) {
		strpos($e->getMessage(), $needle) !== FALSE
			|| fail_upgrade_config($message.' Wrong error: '.$e->getMessage());
		return;
	}
	fail_upgrade_config($message.' No exception was thrown.');
}

function remove_upgrade_fixture($dir) {
	if(!is_dir($dir)) return;
	$items = scandir($dir);
	if(!is_array($items)) return;
	foreach($items as $item) {
		if($item === '.' || $item === '..') continue;
		$path = $dir.'/'.$item;
		is_dir($path) ? remove_upgrade_fixture($path) : @unlink($path);
	}
	@rmdir($dir);
}

function extract_named_function($source, $name) {
	$tokens = token_get_all($source);
	$count = count($tokens);
	for($i = 0; $i < $count; $i++) {
		if(!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) continue;
		$j = $i + 1;
		while($j < $count && is_array($tokens[$j]) && in_array($tokens[$j][0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), TRUE)) $j++;
		if($j >= $count || !is_array($tokens[$j]) || $tokens[$j][0] !== T_STRING || $tokens[$j][1] !== $name) continue;

		$output = '';
		$depth = 0;
		$opened = FALSE;
		for($k = $i; $k < $count; $k++) {
			$token = $tokens[$k];
			$text = is_array($token) ? $token[1] : $token;
			$output .= $text;
			if($text === '{') {
				$depth++;
				$opened = TRUE;
			} elseif($text === '}') {
				$depth--;
				if($opened && $depth === 0) return $output;
			}
		}
	}
	return FALSE;
}

$fixtureRoot = $root.'/tmp/xiuno-upgrade-config-'.bin2hex(random_bytes(8));
@mkdir($fixtureRoot.'/conf', 0777, TRUE);
@mkdir($fixtureRoot.'/database/migrations', 0777, TRUE);
@mkdir($fixtureRoot.'/tmp', 0777, TRUE);
register_shutdown_function(function() use ($fixtureRoot) { remove_upgrade_fixture($fixtureRoot); });

defined('APP_PATH') || define('APP_PATH', $fixtureRoot.'/');
require_once $root.'/vendor/autoload.php';

$upgradeSchemaValue = array('Type'=>'varchar(255)', 'Null'=>'NO', 'Default'=>'');
$upgradeSchemaReads = array();
$upgradeMigrationRecord = array();
$upgradeMigrationReads = array();
function db_sql_find_one_master($sql) {
	global $upgradeSchemaValue, $upgradeSchemaReads;
	$upgradeSchemaReads[] = $sql;
	return $upgradeSchemaValue;
}
function kv__get($key, $primary = FALSE) {
	global $upgradeMigrationRecord, $upgradeMigrationReads;
	$upgradeMigrationReads[] = array($key, $primary);
	return $key === 'xn_migrations' && $primary === TRUE ? $upgradeMigrationRecord : FALSE;
}
function kv_get($key) {
	throw new RuntimeException('ordinary cached KV reads are forbidden in the upgrade guard');
}
require_once $root.'/model/migration.func.php';

$command = new Xiuno\Console\Command\UpgradeCommand();
$configUpdates = new ReflectionMethod($command, 'configUpdates');
$detectSteps = new ReflectionMethod($command, 'detectUpgradeSteps');
$getExecutedMigrations = new ReflectionMethod($command, 'getExecutedMigrations');
if(version_compare(PHP_VERSION, '8.1.0', '<')) {
	$configUpdates->setAccessible(TRUE);
	$detectSteps->setAccessible(TRUE);
	$getExecutedMigrations->setAccessible(TRUE);
}
$_SERVER['db'] = (object)array('tablepre'=>'bbs_');

$aligned = array(
	'version'=>'4.5.5',
	'csrf_on'=>1,
	'disabled_plugin'=>0,
	'nav_2_on'=>1,
	'nav_2_forum_list_pc_on'=>0,
	'nav_2_forum_list_mobile_on'=>0,
	'admin_bind_ip'=>0,
	'cdn_on'=>0,
	'url_rewrite_on'=>0,
	'static_version'=>'?v=4.5.5',
);
$GLOBALS['conf'] = $aligned;
file_put_contents($fixtureRoot.'/conf/conf.php', "<?php\nreturn ".var_export($aligned, TRUE).";\n") !== FALSE
	|| fail_upgrade_config('unable to create aligned config fixture');
$upgradeSchemaReads = array();
$detectSteps->invoke($command) === array()
	|| fail_upgrade_config('an aligned 4.5.5 config must remain a true no-op');
count($upgradeSchemaReads) === 1 && strpos($upgradeSchemaReads[0], "SHOW COLUMNS") !== FALSE
	|| fail_upgrade_config('upgrade planning must inspect schema through the primary endpoint.');

$staleStatic = $aligned;
$staleStatic['static_version'] = '?v=4.5.0';
$updates = $configUpdates->invoke($command, $staleStatic);
isset($updates['static_version']) && $updates['static_version'] === '?v=4.5.5'
	|| fail_upgrade_config('target-version static_version drift must be repaired');
$GLOBALS['conf'] = $staleStatic;
$steps = $detectSteps->invoke($command);
strpos(implode("\n", $steps), 'static_version') !== FALSE
	|| fail_upgrade_config('target-version static_version drift must appear in the upgrade plan');

$commentOnly = $aligned;
unset($commentOnly['csrf_on']);
file_put_contents($fixtureRoot.'/conf/conf.php', "<?php\n// 'csrf_on' appears only in a comment\nreturn ".var_export($commentOnly, TRUE).";\n") !== FALSE
	|| fail_upgrade_config('unable to create comment-only config fixture');
$updates = $configUpdates->invoke($command, $commentOnly);
array_key_exists('csrf_on', $updates)
	|| fail_upgrade_config('config detection must inspect the loaded array, not source substrings');

$GLOBALS['conf'] = $aligned;
$upgradeSchemaValue = FALSE;
expect_upgrade_config_throw(
	function() use ($detectSteps, $command) { $detectSteps->invoke($command); },
	'Primary schema read failed',
	'primary schema read failure must stop upgrade planning before any write.'
);
$upgradeSchemaValue = array();
expect_upgrade_config_throw(
	function() use ($detectSteps, $command) { $detectSteps->invoke($command); },
	'missing or unreadable',
	'a missing password column must stop upgrade planning before any write.'
);
$upgradeSchemaValue = array('Type'=>'varchar(255)', 'Null'=>'NO', 'Default'=>'');

$upgradeMigrationReads = array();
$upgradeMigrationRecord = array('0001_fixture', '0001_fixture', '0002_fixture');
$getExecutedMigrations->invoke($command) === array('0001_fixture', '0002_fixture')
	|| fail_upgrade_config('UpgradeCommand must consume the shared primary migration-record normalization.');
$upgradeMigrationReads === array(array('xn_migrations', TRUE))
	|| fail_upgrade_config('UpgradeCommand migration records must use an explicit primary read.');
$upgradeMigrationRecord = FALSE;
expect_upgrade_config_throw(
	function() use ($getExecutedMigrations, $command) { $getExecutedMigrations->invoke($command); },
	'primary migration record read failed',
	'a failed primary migration-record read must stop upgrade planning.'
);
$upgradeMigrationRecord = array();

$upgradeSource = file_get_contents($root.'/src/Console/Command/UpgradeCommand.php');
is_string($upgradeSource) || fail_upgrade_config('unable to read UpgradeCommand.php');
strpos($upgradeSource, "version_compare(\$currentVersion, self::TARGET_VERSION, '>')") !== FALSE
	|| fail_upgrade_config('upgrade must explicitly refuse a newer installed version');
strpos($upgradeSource, '当前站点版本高于本升级工具目标版本，已拒绝降级。') !== FALSE
	|| fail_upgrade_config('newer-version refusal must be actionable for the operator');
strpos($upgradeSource, 'migration_capability()') !== FALSE
	&& strpos($upgradeSource, 'migration_advisory_lock_start()') !== FALSE
	&& strpos($upgradeSource, 'migration_advisory_lock_end()') !== FALSE
	&& strpos($upgradeSource, 'migration_record_append_locked($name, $executed)') !== FALSE
	|| fail_upgrade_config('upgrade must use the shared capability, schema lock, and verified record append contract.');
strpos($upgradeSource, 'db_sql_find_one_master("SHOW COLUMNS') !== FALSE
	&& strpos($upgradeSource, 'db_sql_find("SHOW COLUMNS') === FALSE
	&& strpos($upgradeSource, "kv_get('xn_migrations'") === FALSE
	|| fail_upgrade_config('upgrade must fail closed instead of using replica or cached schema/migration reads.');

$lockPosition = strpos($upgradeSource, '$lock = migration_advisory_lock_start();');
$lockedFingerprintPosition = strpos($upgradeSource, '$lockedFingerprint = hash_file(\'sha256\', $confFile);', $lockPosition === FALSE ? 0 : $lockPosition);
$lockedRecomputePosition = strpos($upgradeSource, '$lockedSteps = $this->detectUpgradeSteps();', $lockPosition === FALSE ? 0 : $lockPosition);
$firstWritePosition = strpos($upgradeSource, '$this->stepConfigUpgrade($io, $errors);', $lockPosition === FALSE ? 0 : $lockPosition);
$releasePosition = strpos($upgradeSource, '$released = migration_advisory_lock_end();', $lockPosition === FALSE ? 0 : $lockPosition);
$lockPosition !== FALSE
	&& $lockedFingerprintPosition !== FALSE && $lockedFingerprintPosition > $lockPosition
	&& $lockedRecomputePosition !== FALSE && $lockedRecomputePosition > $lockedFingerprintPosition
	&& $firstWritePosition !== FALSE && $firstWritePosition > $lockedRecomputePosition
	&& $releasePosition !== FALSE && $releasePosition > $firstWritePosition
	|| fail_upgrade_config('upgrade must validate its config snapshot and recompute steps inside the lock before the first write.');
substr_count($upgradeSource, "hash_file('sha256', \$confFile)") === 2
	&& strpos($upgradeSource, 'hash_equals($confFingerprint, $lockedFingerprint)') !== FALSE
	&& strpos($upgradeSource, 'conf/conf.php 在预检确认期间发生变化') !== FALSE
	|| fail_upgrade_config('upgrade must reject a changed configuration generation after confirmation.');

$miscSource = file_get_contents($root.'/xiunophp/misc.func.php');
is_string($miscSource) || fail_upgrade_config('unable to read XiunoPHP misc helpers');
$writerSource = extract_named_function($miscSource, 'file_replace_var_write');
$writerSource !== FALSE || fail_upgrade_config('unable to extract file_replace_var_write');
substr_count($miscSource, 'file_replace_var_write($filepath, $original, $s)') === 2
	|| fail_upgrade_config('PHP and JSON config writers must share the checked writer');

$writerStore = array();
$writerMode = 'full';
function file_replace_var_lock($filepath) {
	global $writerMode;
	if($writerMode === 'lock-fail') return FALSE;
	return fopen('php://temp', 'w+b');
}
function file_replace_var_unlock($lock) {
	if(!is_resource($lock)) return FALSE;
	fclose($lock);
	return TRUE;
}
function file_backname($filepath) { return $filepath.'.backup'; }
function file_backup($filepath) {
	global $writerStore, $writerMode;
	if($writerMode === 'backup-fail') return FALSE;
	$backup = file_backname($filepath);
	if(!array_key_exists($backup, $writerStore)) $writerStore[$backup] = $writerStore[$filepath];
	return TRUE;
}
function file_get_contents_try($filepath) {
	global $writerStore;
	return array_key_exists($filepath, $writerStore) ? $writerStore[$filepath] : FALSE;
}
function file_put_contents_try($filepath, $contents) {
	global $writerStore, $writerMode;
	if($writerMode === 'short') {
		$writerStore[$filepath] = substr($contents, 0, max(0, strlen($contents) - 1));
		return max(0, strlen($contents) - 1);
	}
	$writerStore[$filepath] = $contents;
	return strlen($contents);
}
function file_backup_restore($filepath) {
	global $writerStore;
	$backup = file_backname($filepath);
	if(!array_key_exists($backup, $writerStore)) return FALSE;
	$writerStore[$filepath] = $writerStore[$backup];
	unset($writerStore[$backup]);
	return TRUE;
}
function file_backup_unlink($filepath) {
	global $writerStore, $writerMode;
	if($writerMode === 'unlink-fail') return FALSE;
	$backup = file_backname($filepath);
	if(!array_key_exists($backup, $writerStore)) return FALSE;
	unset($writerStore[$backup]);
	return TRUE;
}

eval($writerSource);
$path = '/virtual/conf.php';
$original = '<?php return array();';
$replacement = '<?php return array("version"=>"4.5.3");';

$writerStore = array($path=>$original);
$writerMode = 'lock-fail';
file_replace_var_write($path, $original, $replacement) === FALSE
	|| fail_upgrade_config('stable write-lock failure must fail before publishing');
$writerStore[$path] === $original || fail_upgrade_config('write-lock failure changed the target');

$writerStore = array($path=>'newer committed generation');
$writerMode = 'full';
file_replace_var_write($path, $original, $replacement) === FALSE
	|| fail_upgrade_config('a stale parsed generation must fail under the stable write lock');
$writerStore[$path] === 'newer committed generation'
	|| fail_upgrade_config('a stale writer overwrote a newer committed generation');

$writerStore = array($path=>$original);
$writerMode = 'backup-fail';
file_replace_var_write($path, $original, $replacement) === FALSE
	|| fail_upgrade_config('backup failure must fail before publishing');
$writerStore[$path] === $original || fail_upgrade_config('backup failure changed the target');

$writerStore = array($path=>$original, file_backname($path)=>'stale-generation');
$writerMode = 'full';
file_replace_var_write($path, $original, $replacement) === FALSE
	|| fail_upgrade_config('an inconsistent existing backup must fail closed');
$writerStore[$path] === $original || fail_upgrade_config('inconsistent backup changed the target');

$writerStore = array($path=>$original);
$writerMode = 'short';
file_replace_var_write($path, $original, $replacement) === FALSE
	|| fail_upgrade_config('a restored short write must still return FALSE');
$writerStore[$path] === $original || fail_upgrade_config('short write did not restore the exact original');

$writerStore = array($path=>$original);
$writerMode = 'unlink-fail';
file_replace_var_write($path, $original, $replacement) === FALSE
	|| fail_upgrade_config('backup retirement failure must fail closed');
$writerStore[$path] === $original || fail_upgrade_config('backup retirement failure did not restore the original');

$writerStore = array($path=>$original);
$writerMode = 'full';
$written = file_replace_var_write($path, $original, $replacement);
$written === strlen($replacement) || fail_upgrade_config('valid checked config write failed');
$writerStore[$path] === $replacement || fail_upgrade_config('valid checked config write did not publish replacement');
!array_key_exists(file_backname($path), $writerStore) || fail_upgrade_config('valid checked config write left a stale backup');

// Real two-process invariant: both writers parse the same original generation while a parent holds
// the stable lock. After release exactly one may commit; the stale second writer must return FALSE
// without changing the winner's bytes.
if(function_exists('proc_open')) {
	$concurrentTarget = $fixtureRoot.'/conf/concurrent.json';
	$concurrentWorker = $fixtureRoot.'/concurrent-writer.php';
	$concurrentOriginal = json_encode(array('generation'=>0));
	file_put_contents($concurrentTarget, $concurrentOriginal) === strlen($concurrentOriginal)
		|| fail_upgrade_config('unable to create concurrent config target');

	$workerSource = <<<'PHP'
<?php
define('DEBUG', 0);
define('XIUNOPHP_PATH', $argv[1].'/xiunophp/');
include XIUNOPHP_PATH.'misc.func.php';
$target = $argv[2];
$conf = array('tmp_path'=>$argv[3].DIRECTORY_SEPARATOR);
$role = $argv[4];
$ready = $argv[5];
$result = $argv[6];
$original = file_get_contents_try($target);
if(!is_string($original)) exit(2);
$replacement = json_encode(array('generation'=>$role));
if(file_put_contents($ready, 'ready') === FALSE) exit(3);
$written = file_replace_var_write($target, $original, $replacement);
if(file_put_contents($result, $written === FALSE ? 'failed' : 'success') === FALSE) exit(4);
exit(0);
PHP;
	file_put_contents($concurrentWorker, $workerSource) !== FALSE
		|| fail_upgrade_config('unable to create concurrent writer worker');

	$targetIdentity = str_replace('\\', '/', realpath($concurrentTarget));
	if(DIRECTORY_SEPARATOR === '\\') $targetIdentity = strtolower($targetIdentity);
	$concurrentLockPath = realpath($fixtureRoot.'/tmp').DIRECTORY_SEPARATOR.'file_replace_'.sha1($targetIdentity).'.lock';
	$parentLock = fopen($concurrentLockPath, 'c+b');
	$parentLock && flock($parentLock, LOCK_EX)
		|| fail_upgrade_config('unable to hold stable config write lock');

	$processes = array();
	foreach(array('A', 'B') as $role) {
		$ready = $fixtureRoot.'/ready-'.$role;
		$result = $fixtureRoot.'/result-'.$role;
		$command = escapeshellarg(PHP_BINARY).' '.escapeshellarg($concurrentWorker)
			.' '.escapeshellarg($root)
			.' '.escapeshellarg($concurrentTarget)
			.' '.escapeshellarg($fixtureRoot.'/tmp')
			.' '.escapeshellarg($role)
			.' '.escapeshellarg($ready)
			.' '.escapeshellarg($result);
		$process = proc_open($command, array(0=>array('pipe', 'r'), 1=>array('pipe', 'w'), 2=>array('pipe', 'w')), $pipes);
		is_resource($process) || fail_upgrade_config('unable to start concurrent config writer '.$role);
		fclose($pipes[0]);
		$processes[$role] = array('process'=>$process, 'pipes'=>$pipes, 'ready'=>$ready, 'result'=>$result, 'exitcode'=>NULL);
	}

	$readyDeadline = microtime(TRUE) + 10;
	do {
		$bothReady = is_file($processes['A']['ready']) && is_file($processes['B']['ready']);
		if($bothReady) break;
		usleep(10000);
	} while(microtime(TRUE) < $readyDeadline);
	$bothReady || fail_upgrade_config('concurrent writers did not parse the shared original generation');

	flock($parentLock, LOCK_UN);
	fclose($parentLock);
	foreach($processes as $role=>&$entry) {
		$deadline = microtime(TRUE) + 10;
		do {
			$status = proc_get_status($entry['process']);
			if(!$status['running']) {
				if($status['exitcode'] !== -1) $entry['exitcode'] = $status['exitcode'];
				break;
			}
			usleep(10000);
		} while(microtime(TRUE) < $deadline);
		$stdout = stream_get_contents($entry['pipes'][1]);
		$stderr = stream_get_contents($entry['pipes'][2]);
		fclose($entry['pipes'][1]);
		fclose($entry['pipes'][2]);
		$closeCode = proc_close($entry['process']);
		$entry['exitcode'] === NULL AND $entry['exitcode'] = $closeCode;
		$entry['exitcode'] === 0
			|| fail_upgrade_config('concurrent writer '.$role.' failed: '.$stdout.$stderr);
	}
	unset($entry);

	$results = array(
		'A'=>file_get_contents($processes['A']['result']),
		'B'=>file_get_contents($processes['B']['result']),
	);
	count(array_filter($results, function($result) { return $result === 'success'; })) === 1
		|| fail_upgrade_config('exactly one stale-generation writer must commit');
	count(array_filter($results, function($result) { return $result === 'failed'; })) === 1
		|| fail_upgrade_config('the second stale-generation writer must return FALSE');
	$winner = $results['A'] === 'success' ? 'A' : 'B';
	file_get_contents($concurrentTarget) === json_encode(array('generation'=>$winner))
		|| fail_upgrade_config('the failed stale writer changed the winning committed bytes');
	!is_file(dirname($concurrentTarget).'/concurrent.backup.json')
		|| fail_upgrade_config('concurrent config write left a stale backup');
	is_file($concurrentLockPath)
		|| fail_upgrade_config('stable config write lock inode must remain reusable after publication');
}

echo "OK: upgrade config safety checks passed\n";
