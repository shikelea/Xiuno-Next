<?php

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Unable to locate project root.\n");
    exit(1);
}

$host = getenv('XIUNO_DB_HOST') ?: '';
$port = getenv('XIUNO_DB_PORT') ?: '3306';
$baseName = getenv('XIUNO_DB_NAME') ?: '';
$user = getenv('XIUNO_DB_USER') ?: '';
$password = getenv('XIUNO_DB_PASSWORD') ?: '';

if ($host === '' || $baseName === '' || $user === '') {
    echo "SKIP: database environment is not configured.\n";
    exit(0);
}
if (getenv('XIUNO_ALLOW_DESTRUCTIVE_SMOKE') !== '1') {
    echo "SKIP: destructive database smoke is not explicitly enabled.\n";
    exit(0);
}
if (!preg_match('/(^|_)test($|_)/i', $baseName)) {
    fwrite(STDERR, "FAIL: XIUNO_DB_NAME must look like a test database before destructive smoke can run.\n");
    exit(1);
}

try {
    $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $dbname = safe_database_name($baseName . '_plugin_sql');
    reset_database($pdo, $dbname);
    install_core_schema($pdo, $root);

    smoke_till_post_replies_sql($pdo);
    smoke_sa_shop_sql($pdo);

    echo "OK: plugin install SQL smoke passed\n";
} catch (Throwable $e) {
    fwrite(STDERR, "FAIL: " . $e->getMessage() . "\n");
    exit(1);
}

function safe_database_name(string $name): string
{
    $name = preg_replace('/[^A-Za-z0-9_]/', '_', $name);
    $name = substr($name, 0, 48);
    if ($name === '' || !preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        throw new RuntimeException('Unsafe smoke database name.');
    }
    return $name;
}

function reset_database(PDO $pdo, string $dbname): void
{
    $quoted = quote_identifier($dbname);
    $pdo->exec("DROP DATABASE IF EXISTS $quoted");
    $pdo->exec("CREATE DATABASE $quoted CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE $quoted");
}

function install_core_schema(PDO $pdo, string $root): void
{
    $sql = file_get_contents($root . '/install/install.sql');
    if ($sql === false) {
        throw new RuntimeException('Unable to read install/install.sql');
    }
    foreach (split_sql($sql) as $statement) {
        $pdo->exec($statement);
    }
}

function smoke_till_post_replies_sql(PDO $pdo): void
{
    $pdo->exec("ALTER TABLE bbs_post ADD COLUMN `repeat_follow` LONGTEXT NOT NULL, ADD COLUMN `r_f_c` SMALLINT(6) UNSIGNED DEFAULT 0 NOT NULL, ADD COLUMN `r_f_a` SMALLINT(6) UNSIGNED DEFAULT 0 NOT NULL");
    assert_column_exists($pdo, 'bbs_post', 'repeat_follow');
    assert_column_exists($pdo, 'bbs_post', 'r_f_c');
    assert_column_exists($pdo, 'bbs_post', 'r_f_a');

    $pdo->exec("ALTER TABLE bbs_post DROP COLUMN `repeat_follow`, DROP COLUMN `r_f_c`, DROP COLUMN `r_f_a`");
    assert_column_missing($pdo, 'bbs_post', 'repeat_follow');
    assert_column_missing($pdo, 'bbs_post', 'r_f_c');
    assert_column_missing($pdo, 'bbs_post', 'r_f_a');
}

function smoke_sa_shop_sql(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS `bbs_sashop` (
        `id` int(10) NOT NULL AUTO_INCREMENT,
        `title` text NOT NULL,
        `message` text NOT NULL,
        `saimg` text NOT NULL,
        `sum` int(10) DEFAULT '0',
        `time` int(20) DEFAULT '0',
        PRIMARY KEY (id),
        UNIQUE KEY (id)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8");
    $pdo->exec("CREATE TABLE IF NOT EXISTS `bbs_sasplist` (
        `id` int(10) NOT NULL AUTO_INCREMENT,
        `uid` int(10) DEFAULT '0',
        `title` text NOT NULL,
        `sum` int(10) DEFAULT '0',
        `time` int(20) DEFAULT '0',
        PRIMARY KEY (id),
        UNIQUE KEY (id)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8");
    assert_table_exists($pdo, 'bbs_sashop');
    assert_table_exists($pdo, 'bbs_sasplist');
    assert_table_engine($pdo, 'bbs_sashop', 'MyISAM');
    assert_table_engine($pdo, 'bbs_sasplist', 'MyISAM');

    $pdo->exec('DROP TABLE IF EXISTS `bbs_sashop`, `bbs_sasplist`');
    assert_table_missing($pdo, 'bbs_sashop');
    assert_table_missing($pdo, 'bbs_sasplist');
}

function split_sql(string $sql): array
{
    $sql = str_replace(["\r\n", "\r"], "\n", $sql);
    $statements = [];
    foreach (explode(";\n", $sql) as $statement) {
        $statement = trim($statement);
        if ($statement !== '') {
            $statements[] = $statement;
        }
    }
    return $statements;
}

function quote_identifier(string $identifier): string
{
    if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $identifier)) {
        throw new RuntimeException("Unsafe MySQL identifier: $identifier");
    }
    return "`$identifier`";
}

function assert_table_exists(PDO $pdo, string $table): void
{
    $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
    $stmt->execute([$table]);
    if (!$stmt->fetchColumn()) {
        throw new RuntimeException("Missing table: $table");
    }
}

function assert_table_missing(PDO $pdo, string $table): void
{
    $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
    $stmt->execute([$table]);
    if ($stmt->fetchColumn()) {
        throw new RuntimeException("Table should have been removed: $table");
    }
}

function assert_column_exists(PDO $pdo, string $table, string $column): void
{
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $stmt->execute([$column]);
    if (!$stmt->fetch()) {
        throw new RuntimeException("Missing column: $table.$column");
    }
}

function assert_column_missing(PDO $pdo, string $table, string $column): void
{
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $stmt->execute([$column]);
    if ($stmt->fetch()) {
        throw new RuntimeException("Column should have been removed: $table.$column");
    }
}

function assert_table_engine(PDO $pdo, string $table, string $expected): void
{
    $stmt = $pdo->prepare('SHOW TABLE STATUS LIKE ?');
    $stmt->execute([$table]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException("Missing table status: $table");
    }
    $actual = (string)($row['Engine'] ?? '');
    if (strcasecmp($actual, $expected) !== 0) {
        throw new RuntimeException("$table engine is $actual, expected $expected");
    }
}
