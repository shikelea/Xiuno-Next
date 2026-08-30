<?php

if(!extension_loaded('pdo_sqlite')) {
	echo "SKIP: pdo_sqlite extension is not available.\n";
	exit(0);
}

defined('DEBUG') OR define('DEBUG', 0);
$root = dirname(__DIR__).'/';
require_once $root.'xiunophp/db_pdo_sqlite.class.php';

function sqlite_driver_fail($message) {
	fwrite(STDERR, "FAIL: $message\n");
	exit(1);
}

$config = array(
	'master'=>array(
		'host'=>':memory:',
		'user'=>'',
		'password'=>'',
		'name'=>'',
		'charset'=>'',
		'engine'=>'',
		'tablepre'=>'bbs_',
	),
	'slaves'=>array(),
);

$db = new db_pdo_sqlite($config);
method_exists($db, 'sql_find_one_master') || sqlite_driver_fail('SQLite driver is missing the primary single-row reader.');
method_exists($db, 'sql_find_master') || sqlite_driver_fail('SQLite driver is missing the primary multi-row reader.');
$db->connect() || sqlite_driver_fail('SQLite in-memory connection failed: '.$db->errstr);
$db->wlink === $db->rlink || sqlite_driver_fail('Single-endpoint SQLite must share one read/write connection.');

$db->exec('CREATE TABLE bbs_probe (id INTEGER PRIMARY KEY, value TEXT NOT NULL)') !== FALSE
	|| sqlite_driver_fail('SQLite primary-read fixture table could not be created.');
$db->exec("INSERT INTO bbs_probe (id, value) VALUES (1, 'first')") !== FALSE
	|| sqlite_driver_fail('SQLite primary-read fixture row 1 could not be inserted.');
$db->exec("INSERT INTO bbs_probe (id, value) VALUES (2, 'second')") !== FALSE
	|| sqlite_driver_fail('SQLite primary-read fixture row 2 could not be inserted.');

$one = $db->sql_find_one_master('SELECT id, value FROM bbs_probe WHERE id = 1');
is_array($one) && intval($one['id']) === 1 && $one['value'] === 'first'
	|| sqlite_driver_fail('SQLite primary single-row reader returned the wrong shape.');
$missing = $db->sql_find_one_master('SELECT id, value FROM bbs_probe WHERE id = 99');
$missing === NULL || sqlite_driver_fail('SQLite primary single-row reader must return NULL for a missing row.');
$many = $db->sql_find_master('SELECT id, value FROM bbs_probe ORDER BY id');
is_array($many) && count($many) === 2 && intval($many[1]['id']) === 2 && $many[1]['value'] === 'second'
	|| sqlite_driver_fail('SQLite primary multi-row reader returned the wrong rows.');
count($db->sqls) >= 6 || sqlite_driver_fail('SQLite query diagnostics did not retain executed SQL.');

echo "OK: SQLite connection and primary-read contract checks passed\n";
