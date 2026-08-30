<?php

define('DEBUG', 0);
$_SERVER['time'] = time();
$_SERVER['ip'] = '127.0.0.1';
$fixture_root = rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/')
	. '/xiuno_plugin_sql_compat_' . getmypid() . '_' . substr(hash('sha256', __FILE__ . microtime(TRUE)), 0, 12) . '/';
$_SERVER['conf'] = array('log_path' => $fixture_root);

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

// 兼容事件必须在 DEBUG=0 下落盘（生产可观测性契约）。守卫只写系统临时夹具，
// 不能删除或混入站点自身的 tmp/log 运行证据。
$compat_log_dir = $fixture_root . date('Ym', $_SERVER['time']);
$compat_log = $compat_log_dir . '/plugin_sql_compat.php';
register_shutdown_function(function () use ($compat_log, $compat_log_dir, $fixture_root) {
	is_file($compat_log) AND @unlink($compat_log);
	is_dir($compat_log_dir) AND @rmdir($compat_log_dir);
	is_dir($fixture_root) AND @rmdir($fixture_root);
});

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
if (!is_file($compat_log)) fail('Idempotent DDL events must be written to the plugin_sql_compat log even when DEBUG=0.');
$compat_log_content = file_get_contents($compat_log);
if (strpos($compat_log_content, 'Plugin lifecycle idempotent DDL ignored') === FALSE || strpos($compat_log_content, 'smoke_plugin') === FALSE) {
	fail('plugin_sql_compat log must record the ignored DDL with the owning plugin.');
}

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

// SQL 归一化事件同样必须落盘
if (strpos(file_get_contents($compat_log), 'Plugin lifecycle SQL normalized') === FALSE) {
	fail('SQL normalization events must be written to the plugin_sql_compat log even when DEBUG=0.');
}

echo "OK: plugin SQL compatibility checks passed\n";
