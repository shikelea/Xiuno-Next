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

$confFile = $root . '/conf/conf.php';
$confBackup = is_file($confFile) ? file_get_contents($confFile) : null;
if ($confBackup === false) {
    fwrite(STDERR, "Unable to back up conf/conf.php.\n");
    exit(1);
}

try {
    $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    assert_missing_conf_fails($root, $confFile);

    $migrateDb = safe_database_name($baseName . '_migrate');
    reset_old_database($pdo, $migrateDb);
    write_cli_conf($confFile, $host, $port, $migrateDb, $user, $password, '4.4.5');
    run_cli($root, ['migrate', '--no-interaction']);
    assert_column_type($pdo, $migrateDb, 'bbs_user', 'password', 'varchar(255)');
    assert_migration_recorded($pdo, $migrateDb, '0001_alter_user_password_field');

    $upgradeDb = safe_database_name($baseName . '_upgrade');
    reset_old_database($pdo, $upgradeDb);
    write_cli_conf($confFile, $host, $port, $upgradeDb, $user, $password, '4.0.7');
    run_cli($root, ['upgrade'], "yes\n");
    assert_column_type($pdo, $upgradeDb, 'bbs_user', 'password', 'varchar(255)');
    assert_migration_recorded($pdo, $upgradeDb, '0001_alter_user_password_field');
    assert_kv_exists($pdo, $upgradeDb, 'xn_upgraded_from');
    assert_kv_exists($pdo, $upgradeDb, 'xn_upgraded_date');
    assert_conf_upgraded($confFile);

    echo "OK: legacy upgrade and migration smoke passed\n";
} catch (Throwable $e) {
    fwrite(STDERR, "FAIL: " . $e->getMessage() . "\n");
    exit(1);
} finally {
    if ($confBackup === null) {
        if (is_file($confFile)) {
            unlink($confFile);
        }
    } else {
        file_put_contents($confFile, $confBackup);
    }
}

function assert_missing_conf_fails(string $root, string $confFile): void
{
    $tmp = $confFile . '.upgrade-smoke-backup';
    if (is_file($tmp)) {
        unlink($tmp);
    }
    if (is_file($confFile) && !rename($confFile, $tmp)) {
        throw new RuntimeException('Unable to move conf.php for missing-conf smoke.');
    }

    try {
        $code = run_cli($root, ['migrate', '--no-interaction'], '', false);
        if ($code === 0) {
            throw new RuntimeException('migrate unexpectedly succeeded without conf/conf.php.');
        }
    } finally {
        if (is_file($tmp) && !rename($tmp, $confFile)) {
            throw new RuntimeException('Unable to restore conf.php after missing-conf smoke.');
        }
    }
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

function reset_old_database(PDO $pdo, string $dbname): void
{
    $quoted = quote_identifier($dbname);
    $pdo->exec("DROP DATABASE IF EXISTS $quoted");
    $pdo->exec("CREATE DATABASE $quoted CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE $quoted");
    $pdo->exec("
        CREATE TABLE bbs_user (
            uid int unsigned NOT NULL AUTO_INCREMENT,
            `password` char(32) NOT NULL DEFAULT '',
            PRIMARY KEY (uid)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE bbs_kv (
            k char(32) NOT NULL DEFAULT '',
            v mediumtext NOT NULL,
            expiry int unsigned NOT NULL DEFAULT '0',
            PRIMARY KEY (k)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function write_cli_conf(string $confFile, string $host, string $port, string $dbname, string $user, string $password, string $version): void
{
    $dbHost = $host . ':' . $port;
    $conf = [
        'db' => [
            'type' => 'pdo_mysql',
            'pdo_mysql' => [
                'master' => [
                    'host' => $dbHost,
                    'user' => $user,
                    'password' => $password,
                    'name' => $dbname,
                    'tablepre' => 'bbs_',
                    'charset' => 'utf8mb4',
                    'engine' => 'innodb',
                ],
                'slaves' => [],
            ],
        ],
        'cache' => [
            'enable' => false,
        ],
        'tmp_path' => './tmp/',
        'log_path' => './log/',
        'upload_path' => './upload/',
        'timezone' => 'Asia/Shanghai',
        'auth_key' => 'upgrade-smoke-key',
        'version' => $version,
        'static_version' => '?v=' . $version,
    ];

    $content = "<?php\nreturn " . var_export($conf, true) . ";\n";
    if (file_put_contents($confFile, $content, LOCK_EX) !== strlen($content)) {
        throw new RuntimeException('Unable to write smoke conf.php.');
    }
}

function run_cli(string $root, array $args, string $stdin = '', bool $mustSucceed = true): int
{
    $command = array_merge([PHP_BINARY, $root . '/bin/xiuno'], $args);
    $descriptor = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptor, $pipes, $root);
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start CLI process.');
    }

    fwrite($pipes[0], $stdin);
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $code = proc_close($process);

    if ($mustSucceed && $code !== 0) {
        throw new RuntimeException("CLI command failed: " . implode(' ', $args) . "\n$stdout\n$stderr");
    }

    return $code;
}

function quote_identifier(string $identifier): string
{
    if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $identifier)) {
        throw new RuntimeException("Unsafe MySQL identifier: $identifier");
    }
    return "`$identifier`";
}

function assert_column_type(PDO $pdo, string $dbname, string $table, string $column, string $expected): void
{
    $pdo->exec('USE ' . quote_identifier($dbname));
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

function assert_migration_recorded(PDO $pdo, string $dbname, string $migration): void
{
    $value = kv_value($pdo, $dbname, 'xn_migrations');
    $decoded = json_decode($value, true);
    if (!is_array($decoded) || !in_array($migration, $decoded, true)) {
        throw new RuntimeException("Migration was not recorded: $migration");
    }
}

function assert_kv_exists(PDO $pdo, string $dbname, string $key): void
{
    kv_value($pdo, $dbname, $key);
}

function kv_value(PDO $pdo, string $dbname, string $key): string
{
    $pdo->exec('USE ' . quote_identifier($dbname));
    $stmt = $pdo->prepare('SELECT v FROM bbs_kv WHERE k = ?');
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    if ($value === false) {
        throw new RuntimeException("Missing kv record: $key");
    }
    return (string) $value;
}

function assert_conf_upgraded(string $confFile): void
{
    $conf = include $confFile;
    if (($conf['version'] ?? '') !== '4.4.5') {
        throw new RuntimeException('upgrade did not write target version to conf.php.');
    }
    foreach (['csrf_on', 'disabled_plugin', 'admin_bind_ip', 'static_version'] as $key) {
        if (!array_key_exists($key, $conf)) {
            throw new RuntimeException("upgrade did not backfill config key: $key");
        }
    }
}

function normalize_column_type(string $type): string
{
    $type = strtolower($type);
    $type = preg_replace('/\(\d+\)/', '', $type);
    return trim(preg_replace('/\s+/', ' ', $type));
}
