<?php

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Unable to locate project root.\n");
    exit(1);
}

$host = getenv('XIUNO_DB_HOST') ?: '127.0.0.1';
$port = getenv('XIUNO_DB_PORT') ?: '3306';
$dbname = getenv('XIUNO_DB_NAME') ?: 'xiuno_test';
$user = getenv('XIUNO_DB_USER') ?: 'root';
$password = getenv('XIUNO_DB_PASSWORD') ?: 'root';
$expected_mysql_major = getenv('XIUNO_EXPECT_MYSQL_MAJOR') ?: '';

$dsn = "mysql:host=$host;port=$port;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $mysql_version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
    assert_mysql_major_version($mysql_version, $expected_mysql_major);

    $database = quote_mysql_identifier($dbname);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE $database");

    $sql = file_get_contents($root . '/install/install.sql');
    if ($sql === false) {
        throw new RuntimeException('Unable to read install/install.sql');
    }

    foreach (split_sql($sql) as $statement) {
        $pdo->exec($statement);
    }

    assert_column_type($pdo, 'bbs_user', 'password', 'varchar(255)');
    assert_column_type($pdo, 'bbs_forum', 'rank', 'tinyint unsigned');
    assert_table_exists($pdo, 'bbs_kv');

    echo "Install schema smoke OK on MySQL $mysql_version.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Install schema smoke failed: " . $e->getMessage() . "\n");
    exit(1);
}

function quote_mysql_identifier(string $identifier): string
{
    if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $identifier)) {
        throw new RuntimeException("Unsafe MySQL identifier: $identifier");
    }

    return "`$identifier`";
}

function assert_mysql_major_version(string $version, string $expected): void
{
    if ($expected === '') {
        return;
    }

    if (!preg_match('/^(\d+)\./', $version, $matches)) {
        throw new RuntimeException("Unable to parse MySQL version: $version");
    }

    if ($matches[1] !== $expected) {
        throw new RuntimeException("MySQL major version is {$matches[1]}, expected $expected ($version)");
    }
}

function split_sql(string $sql): array
{
    $sql = str_replace(["\r\n", "\r"], "\n", $sql);
    $statements = [];

    foreach (explode(";\n", $sql) as $statement) {
        $statement = trim($statement);
        if ($statement === '') {
            continue;
        }
        $statements[] = $statement;
    }

    return $statements;
}

function assert_table_exists(PDO $pdo, string $table): void
{
    $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
    $stmt->execute([$table]);
    if (!$stmt->fetchColumn()) {
        throw new RuntimeException("Missing table: $table");
    }
}

function assert_column_type(PDO $pdo, string $table, string $column, string $expected): void
{
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $stmt->execute([$column]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException("Missing column: $table.$column");
    }

    $actual = normalize_column_type($row['Type'] ?? '');
    $expected = normalize_column_type($expected);
    if ($actual !== $expected) {
        throw new RuntimeException("$table.$column type is $actual, expected $expected");
    }
}

function normalize_column_type(string $type): string
{
    $type = strtolower($type);
    $type = preg_replace('/\(\d+\)/', '', $type);
    return trim(preg_replace('/\s+/', ' ', $type));
}
