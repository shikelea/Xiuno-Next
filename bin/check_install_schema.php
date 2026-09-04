<?php

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Unable to locate project root.\n");
    exit(1);
}

$host = getenv('XIUNO_DB_HOST') ?: '';
$port = getenv('XIUNO_DB_PORT') ?: '3306';
$dbname = getenv('XIUNO_DB_NAME') ?: '';
$user = getenv('XIUNO_DB_USER') ?: '';
$password = getenv('XIUNO_DB_PASSWORD') ?: '';
$expected_mysql_major = getenv('XIUNO_EXPECT_MYSQL_MAJOR') ?: '';
$sqlMode = getenv('XIUNO_SQL_MODE') ?: '';

// 与 legacy_upgrade / plugin_install_sql smoke 语义一致：未显式配置数据库环境时优雅跳过而不是连接失败
if ($host === '' || $dbname === '' || $user === '') {
    echo "SKIP: database environment is not configured.\n";
    exit(0);
}
if (getenv('XIUNO_ALLOW_DESTRUCTIVE_SMOKE') !== '1') {
    echo "SKIP: destructive database smoke is not explicitly enabled.\n";
    exit(0);
}
if (!preg_match('/(^|_)test($|_)/i', $dbname)) {
    fwrite(STDERR, "FAIL: XIUNO_DB_NAME must look like a test database before schema smoke can run.\n");
    exit(1);
}

$dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
$pdo = null;
$ownedDatabase = '';
$failure = null;

try {
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    apply_sql_mode($pdo, $sqlMode);

    $mysql_version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
    assert_mysql_major_version($mysql_version, $expected_mysql_major);

    $ownedDatabase = smoke_database_name($dbname, 'install');
    $database = quote_mysql_identifier($ownedDatabase);
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
    assert_column_type($pdo, 'bbs_user', 'auth_epoch', 'int unsigned');
    assert_column_type($pdo, 'bbs_forum', 'rank', 'tinyint unsigned');
    assert_table_exists($pdo, 'bbs_kv');
    assert_table_exists($pdo, 'bbs_mail_outbox');
    assert_column_type($pdo, 'bbs_mail_outbox', 'payload', 'mediumtext');
    assert_column_type($pdo, 'bbs_mail_outbox', 'lease_token', 'char(64)');
    assert_mail_outbox_state_machine($pdo, $root, $dsn, $user, $password);
} catch (Throwable $e) {
    $failure = $e;
} finally {
    if ($pdo instanceof PDO && $ownedDatabase !== '') {
        try {
            $pdo->exec('DROP DATABASE IF EXISTS ' . quote_mysql_identifier($ownedDatabase));
        } catch (Throwable $cleanupError) {
            $failure = $failure ?: $cleanupError;
        }
    }
}

if ($failure instanceof Throwable) {
    fwrite(STDERR, "Install schema smoke failed: " . $failure->getMessage() . "\n");
    exit(1);
}
echo "Install schema smoke OK on MySQL $mysql_version.\n";

function apply_sql_mode(PDO $pdo, string $sqlMode): void
{
    $sqlMode = strtoupper(trim($sqlMode));
    if ($sqlMode === '') {
        return;
    }
    if (!preg_match('/^[A-Z0-9_,]+$/', $sqlMode)) {
        throw new RuntimeException("Unsafe XIUNO_SQL_MODE value: $sqlMode");
    }
    $pdo->exec("SET SESSION sql_mode = '$sqlMode'");
}

function quote_mysql_identifier(string $identifier): string
{
    if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $identifier)) {
        throw new RuntimeException("Unsafe MySQL identifier: $identifier");
    }

    return "`$identifier`";
}

function smoke_database_name(string $base, string $purpose): string
{
    if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $base)) {
        throw new RuntimeException("Unsafe MySQL database name: $base");
    }
    $suffix = '_' . $purpose . '_' . substr(bin2hex(random_bytes(8)), 0, 12);
    $prefix = substr($base, 0, 64 - strlen($suffix));
    $derived = $prefix . $suffix;
    if ($derived === $base || !preg_match('/^[A-Za-z0-9_]{1,64}$/', $derived)) {
        throw new RuntimeException('Unable to derive an isolated smoke database name.');
    }
    return $derived;
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

function assert_mail_outbox_state_machine(PDO $pdo, string $root, string $dsn, string $user, string $password): void
{
    if (!function_exists('_SERVER')) {
        function _SERVER($key, $default = null) {
            return $_SERVER[$key] ?? $default;
        }
    }
    if (!function_exists('xn_urlencode')) {
        function xn_urlencode($value) {
            $value = urlencode($value);
            return str_replace(['_', '-', '.', '+', '=', '%'], ['_5f', '_2d', '_2e', '_2b', '_3d', '_'], $value);
        }
    }
    if (!function_exists('xn_urldecode')) {
        function xn_urldecode($value) {
            return urldecode(str_replace('_', '%', $value));
        }
    }

    $_SERVER['conf'] = ['auth_key' => bin2hex(random_bytes(32))];
    $_SERVER['db'] = new class($pdo) {
        public $wlink;
        public $tablepre = 'bbs_';

        public function __construct(PDO $pdo) {
            $this->wlink = $pdo;
        }

        public function connect_master() {
            return $this->wlink;
        }
    };

    require_once $root . '/xiunophp/xn_encrypt.func.php';
    require_once $root . '/model/mail_outbox.func.php';

    $workerLock = mail_outbox_worker_lock_acquire();
    if (!is_string($workerLock)) {
        throw new RuntimeException('Mail outbox worker lock could not be acquired.');
    }
    try {
        $secondary = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $contender = $secondary->prepare('SELECT GET_LOCK(?, 0)');
        $contender->execute([$workerLock]);
        if ((string) $contender->fetchColumn() !== '0') {
            throw new RuntimeException('Mail outbox worker lock did not exclude a concurrent connection.');
        }
    } finally {
        if (!mail_outbox_worker_lock_release($workerLock)) {
            throw new RuntimeException('Mail outbox worker lock could not be released.');
        }
    }

    $now = 1700000000;
    $smtp = ['host' => 'smtp.test', 'port' => 587, 'user' => 'mailer', 'pass' => 'secret', 'email' => 'from@example.com'];
    if (!mail_outbox_enqueue_reset($smtp, 'Xiuno Test', 'reset@example.com', 'Reset 123456', 'Code 123456', $now)) {
        throw new RuntimeException('Mail outbox enqueue failed.');
    }
    $stored = $pdo->query('SELECT payload FROM bbs_mail_outbox')->fetchColumn();
    if (!is_string($stored) || strncmp($stored, 'v2.', 3) !== 0
        || strpos($stored, 'reset@example.com') !== false || strpos($stored, '123456') !== false) {
        throw new RuntimeException('Mail outbox did not persist an opaque encrypted payload.');
    }

    $claimed = mail_outbox_claim($now);
    if (!is_array($claimed) || mail_outbox_claim($now) !== null) {
        throw new RuntimeException('Mail outbox claim was not exclusive.');
    }
    if (!mail_outbox_release_claimed($claimed, $now)) {
        throw new RuntimeException('Mail outbox claim could not be released.');
    }

    $delivered = [];
    $success = mail_outbox_work_one(
        function ($smtpArg, $username, $email, $subject, $message, $charset) use (&$delivered) {
            $delivered = [$smtpArg, $username, $email, $subject, $message, $charset];
            return true;
        },
        $now
    );
    if (($success['status'] ?? '') !== 'sent' || $pdo->query('SELECT COUNT(*) FROM bbs_mail_outbox')->fetchColumn() != 0
        || ($delivered[2] ?? '') !== 'reset@example.com' || ($delivered[3] ?? '') !== 'Reset 123456') {
        throw new RuntimeException('Successful mail outbox delivery did not consume the claimed task.');
    }

    $retryNow = time();
    if (!mail_outbox_enqueue_reset($smtp, 'Xiuno Test', 'retry@example.com', 'Retry 654321', 'Code 654321', $retryNow)) {
        throw new RuntimeException('Retry fixture enqueue failed.');
    }
    $GLOBALS['errstr'] = '';
    $deliveryFinishedAt = 0;
    $retry = mail_outbox_work_one(function () use (&$deliveryFinishedAt) {
        sleep(1);
        $deliveryFinishedAt = time();
        $GLOBALS['errstr'] = 'Simulated SMTP failure.';
        return false;
    });
    $row = $pdo->query('SELECT available_at, lease_until, lease_token, attempts FROM bbs_mail_outbox')->fetch();
    if (($retry['status'] ?? '') !== 'retry' || ($retry['message'] ?? '') !== 'Simulated SMTP failure.'
        || !is_array($row) || (int) $row['available_at'] < $deliveryFinishedAt + 15
        || (int) $row['available_at'] > time() + 15 || (int) $row['lease_until'] !== 0
        || $row['lease_token'] !== '' || (int) $row['attempts'] !== 1) {
        throw new RuntimeException('Failed mail outbox delivery did not release the task with the expected retry state.');
    }
}
