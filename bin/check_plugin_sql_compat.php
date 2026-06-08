<?php

define('DEBUG', 0);
$_SERVER['time'] = time();
$_SERVER['ip'] = '127.0.0.1';
$_SERVER['conf'] = array('log_path' => __DIR__ . '/../tmp/');

require __DIR__ . '/../xiunophp/array.func.php';
require __DIR__ . '/../xiunophp/logger.func.php';
require __DIR__ . '/../xiunophp/misc.func.php';
require __DIR__ . '/../xiunophp/db.func.php';

class FakePluginSqlDb
{
	public $errno = 0;
	public $errstr = '';
	public $tablepre = 'bbs_';
	public $next = 0;
	public $calls = 0;
	public $lastSql = '';

	public function exec($sql)
	{
		$this->calls++;
		$this->lastSql = $sql;
		return $this->next;
	}
}

function fail($message)
{
	fwrite(STDERR, "FAIL: $message\n");
	exit(1);
}

$db = new FakePluginSqlDb();
$_SERVER['db'] = $db;

$db->next = 0;
$r = db_exec('CREATE TABLE bbs_smoke (id int)');
if ($r !== TRUE) fail('Successful DDL with zero affected rows must be normalized to TRUE.');

$db->next = 0;
$r = db_exec('ALTER TABLE bbs_user ADD COLUMN smoke int');
if ($r !== TRUE) fail('Successful ALTER with zero affected rows must be normalized to TRUE.');

$db->next = 0;
$r = db_exec('UPDATE bbs_user SET threads=threads');
if ($r !== 0) fail('DML zero affected rows must keep returning 0.');

$GLOBALS['plugin_lifecycle_guard'] = array('dir' => 'smoke_plugin', 'action' => 'install');
$db->next = FALSE;
$db->errno = 1060;
$db->errstr = "Duplicate column name 'smoke'";
$r = db_exec('ALTER TABLE bbs_user ADD COLUMN smoke int');
if ($r !== TRUE) fail('Duplicate column inside plugin lifecycle must be treated as idempotent DDL.');

$db->next = FALSE;
$db->errno = 1050;
$db->errstr = "Table 'bbs_smoke' already exists";
$r = db_exec('CREATE TABLE bbs_smoke (id int)');
if ($r !== TRUE) fail('Existing table inside plugin lifecycle must be treated as idempotent DDL.');

$db->next = 0;
$r = db_exec('CREATE TABLE bbs_smoke (`n` int(11) COLLATE utf8_general_ci DEFAULT NULL, `s` varchar(32) COLLATE utf8_general_ci DEFAULT NULL)');
if ($r !== TRUE) fail('Normalized numeric COLLATE DDL must still be treated as successful DDL.');
if (strpos($db->lastSql, 'int(11) COLLATE') !== FALSE) fail('Plugin lifecycle SQL must remove COLLATE from numeric columns.');
if (strpos($db->lastSql, 'varchar(32) COLLATE utf8_general_ci') === FALSE) fail('Plugin lifecycle SQL must keep COLLATE on character columns.');

$r = db_exec('CREATE TABLE bbs_smoke (`n` int(11) COLLATE utf8_general_ci DEFAULT NULL COMMENT \'int COLLATE utf8_general_ci\', `s` varchar(32) DEFAULT "int COLLATE utf8_general_ci")');
if (strpos($db->lastSql, "COMMENT 'int COLLATE utf8_general_ci'") === FALSE) fail('Plugin lifecycle SQL normalization must not rewrite single-quoted literals.');
if (strpos($db->lastSql, 'DEFAULT "int COLLATE utf8_general_ci"') === FALSE) fail('Plugin lifecycle SQL normalization must not rewrite double-quoted literals.');

$db->next = FALSE;
$db->errno = 1146;
$db->errstr = "Table 'bbs_missing' doesn't exist";
$r = db_exec('ALTER TABLE bbs_missing ADD COLUMN smoke int');
if ($r !== FALSE) fail('Non-idempotent DDL errors must still fail inside plugin lifecycle.');

$GLOBALS['plugin_lifecycle_guard'] = NULL;
$db->next = FALSE;
$db->errno = 1060;
$db->errstr = "Duplicate column name 'smoke'";
$r = db_exec('ALTER TABLE bbs_user ADD COLUMN smoke int');
if ($r !== FALSE) fail('Duplicate column outside plugin lifecycle must still fail.');

echo "OK: plugin SQL compatibility checks passed\n";
