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
$sqlMode = getenv('XIUNO_SQL_MODE') ?: '';

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

    apply_sql_mode($pdo, $sqlMode);

    assert_missing_conf_fails($root, $confFile);

    $migrateDb = safe_database_name($baseName . '_migrate');
    reset_old_database($pdo, $migrateDb);
    write_cli_conf($confFile, $host, $port, $migrateDb, $user, $password, '4.4.5', $sqlMode);
    run_cli($root, ['migrate', '--no-interaction']);
    assert_column_type($pdo, $migrateDb, 'bbs_user', 'password', 'varchar(255)');
    assert_legacy_user_preserved($pdo, $migrateDb);
    assert_legacy_content_preserved($pdo, $migrateDb);
    assert_migration_recorded($pdo, $migrateDb, '0001_alter_user_password_field');

    $upgradeDb = safe_database_name($baseName . '_upgrade');
    reset_old_database($pdo, $upgradeDb);
    write_cli_conf($confFile, $host, $port, $upgradeDb, $user, $password, '4.0.7', $sqlMode);
    run_cli($root, ['upgrade'], "yes\n");
    assert_column_type($pdo, $upgradeDb, 'bbs_user', 'password', 'varchar(255)');
    assert_legacy_user_preserved($pdo, $upgradeDb);
    assert_legacy_content_preserved($pdo, $upgradeDb);
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
            username char(32) NOT NULL DEFAULT '',
            salt char(16) NOT NULL DEFAULT '',
            `password` char(32) NOT NULL DEFAULT '',
            PRIMARY KEY (uid),
            UNIQUE KEY username (username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        INSERT INTO bbs_user SET
            uid = 7,
            username = 'legacy_user',
            salt = 'oldsalt',
            `password` = '1a1dc91c907325c69271ddf0c944bc72'
    ");
    $pdo->exec("
        CREATE TABLE bbs_kv (
            k char(32) NOT NULL DEFAULT '',
            v mediumtext NOT NULL,
            expiry int unsigned NOT NULL DEFAULT '0',
            PRIMARY KEY (k)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        CREATE TABLE bbs_forum (
            fid int unsigned NOT NULL AUTO_INCREMENT,
            `name` char(16) NOT NULL DEFAULT '',
            threads mediumint unsigned NOT NULL DEFAULT '0',
            PRIMARY KEY (fid)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("INSERT INTO bbs_forum SET fid = 3, `name` = 'legacy_forum', threads = 1");
    $pdo->exec("
        CREATE TABLE bbs_forum_access (
            fid int unsigned NOT NULL DEFAULT '0',
            gid int unsigned NOT NULL DEFAULT '0',
            allowread tinyint unsigned NOT NULL DEFAULT '0',
            allowthread tinyint unsigned NOT NULL DEFAULT '0',
            allowpost tinyint unsigned NOT NULL DEFAULT '0',
            allowattach tinyint unsigned NOT NULL DEFAULT '0',
            allowdown tinyint unsigned NOT NULL DEFAULT '0',
            PRIMARY KEY (fid, gid)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        INSERT INTO bbs_forum_access SET
            fid = 3,
            gid = 101,
            allowread = 1,
            allowthread = 1,
            allowpost = 1,
            allowattach = 1,
            allowdown = 1
    ");
    $pdo->exec("
        CREATE TABLE bbs_thread (
            fid smallint NOT NULL DEFAULT '0',
            tid int unsigned NOT NULL AUTO_INCREMENT,
            top tinyint NOT NULL DEFAULT '0',
            uid int unsigned NOT NULL DEFAULT '0',
            subject char(128) NOT NULL DEFAULT '',
            create_date int unsigned NOT NULL DEFAULT '0',
            posts int unsigned NOT NULL DEFAULT '0',
            firstpid int unsigned NOT NULL DEFAULT '0',
            lastuid int unsigned NOT NULL DEFAULT '0',
            lastpid int unsigned NOT NULL DEFAULT '0',
            PRIMARY KEY (tid),
            KEY (fid, tid)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        INSERT INTO bbs_thread SET
            fid = 3,
            tid = 11,
            top = 1,
            uid = 7,
            subject = 'legacy subject',
            create_date = 1700000100,
            posts = 1,
            firstpid = 101,
            lastuid = 7,
            lastpid = 102
    ");
    $pdo->exec("
        CREATE TABLE bbs_thread_top (
            fid smallint NOT NULL DEFAULT '0',
            tid int unsigned NOT NULL DEFAULT '0',
            top int unsigned NOT NULL DEFAULT '0',
            PRIMARY KEY (tid),
            KEY (top, tid),
            KEY (fid, top)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        INSERT INTO bbs_thread_top SET
            fid = 3,
            tid = 11,
            top = 1
    ");
    $pdo->exec("
        CREATE TABLE bbs_post (
            tid int unsigned NOT NULL DEFAULT '0',
            pid int unsigned NOT NULL AUTO_INCREMENT,
            uid int unsigned NOT NULL DEFAULT '0',
            isfirst int unsigned NOT NULL DEFAULT '0',
            create_date int unsigned NOT NULL DEFAULT '0',
            message longtext NOT NULL,
            message_fmt longtext NOT NULL,
            PRIMARY KEY (pid),
            KEY (tid, pid),
            KEY (uid)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        INSERT INTO bbs_post SET
            tid = 11,
            pid = 101,
            uid = 7,
            isfirst = 1,
            create_date = 1700000101,
            message = 'legacy post',
            message_fmt = 'legacy post'
    ");
    $pdo->exec("
        INSERT INTO bbs_post SET
            tid = 11,
            pid = 102,
            uid = 7,
            isfirst = 0,
            create_date = 1700000103,
            message = 'legacy reply',
            message_fmt = 'legacy reply'
    ");
    $pdo->exec("
        CREATE TABLE bbs_attach (
            aid int unsigned NOT NULL AUTO_INCREMENT,
            tid int NOT NULL DEFAULT '0',
            pid int NOT NULL DEFAULT '0',
            uid int NOT NULL DEFAULT '0',
            filesize int unsigned NOT NULL DEFAULT '0',
            filename char(120) NOT NULL DEFAULT '',
            orgfilename char(120) NOT NULL DEFAULT '',
            filetype char(7) NOT NULL DEFAULT '',
            create_date int unsigned NOT NULL DEFAULT '0',
            PRIMARY KEY (aid),
            KEY pid (pid),
            KEY uid (uid)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        INSERT INTO bbs_attach SET
            aid = 501,
            tid = 11,
            pid = 101,
            uid = 7,
            filesize = 1234,
            filename = 'legacy/attach.txt',
            orgfilename = 'attach.txt',
            filetype = 'txt',
            create_date = 1700000102
    ");
    $pdo->exec("
        INSERT INTO bbs_attach SET
            aid = 502,
            tid = 11,
            pid = 102,
            uid = 7,
            filesize = 5678,
            filename = 'legacy/reply.txt',
            orgfilename = 'reply.txt',
            filetype = 'txt',
            create_date = 1700000104
    ");
    $pdo->exec("
        CREATE TABLE bbs_mythread (
            uid int unsigned NOT NULL DEFAULT '0',
            tid int unsigned NOT NULL DEFAULT '0',
            PRIMARY KEY (uid, tid)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("INSERT INTO bbs_mythread SET uid = 7, tid = 11");
    $pdo->exec("
        CREATE TABLE bbs_mypost (
            uid int unsigned NOT NULL DEFAULT '0',
            tid int unsigned NOT NULL DEFAULT '0',
            pid int unsigned NOT NULL DEFAULT '0',
            KEY (tid),
            PRIMARY KEY (uid, pid)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $pdo->exec("
        INSERT INTO bbs_mypost (uid, tid, pid) VALUES
            (7, 11, 101),
            (7, 11, 102)
    ");
}

function write_cli_conf(string $confFile, string $host, string $port, string $dbname, string $user, string $password, string $version, string $sqlMode): void
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
                    'sql_mode' => $sqlMode,
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

function assert_legacy_user_preserved(PDO $pdo, string $dbname): void
{
    $pdo->exec('USE ' . quote_identifier($dbname));
    $stmt = $pdo->prepare('SELECT uid, username, salt, `password` FROM bbs_user WHERE uid = ?');
    $stmt->execute([7]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('Legacy user row was not preserved after migration.');
    }
    if ($row['username'] !== 'legacy_user' || $row['salt'] !== 'oldsalt') {
        throw new RuntimeException('Legacy user identity fields changed after migration.');
    }
    if ($row['password'] !== '1a1dc91c907325c69271ddf0c944bc72') {
        throw new RuntimeException('Legacy md5 password hash changed before login-time upgrade.');
    }
}

function assert_legacy_content_preserved(PDO $pdo, string $dbname): void
{
    $pdo->exec('USE ' . quote_identifier($dbname));
    $sql = "
        SELECT
            f.name AS forum_name,
            t.subject,
            t.top,
            t.posts,
            t.firstpid,
            t.lastpid,
            p.message,
            a.filename,
            a.orgfilename
        FROM bbs_thread t
        INNER JOIN bbs_forum f ON f.fid = t.fid
        INNER JOIN bbs_post p ON p.pid = t.firstpid AND p.tid = t.tid
        INNER JOIN bbs_attach a ON a.pid = p.pid AND a.tid = t.tid
        WHERE t.tid = 11
    ";
    $row = $pdo->query($sql)->fetch();
    if (!$row) {
        throw new RuntimeException('Legacy forum/thread/post/attachment relation was not preserved after migration.');
    }
    if ($row['forum_name'] !== 'legacy_forum' || $row['subject'] !== 'legacy subject') {
        throw new RuntimeException('Legacy forum or thread fields changed after migration.');
    }
    if ((int) $row['top'] !== 1 || (int) $row['posts'] !== 1 || (int) $row['firstpid'] !== 101 || (int) $row['lastpid'] !== 102) {
        throw new RuntimeException('Legacy thread counters or first/last post pointers changed after migration.');
    }
    if ($row['message'] !== 'legacy post') {
        throw new RuntimeException('Legacy post content changed after migration.');
    }
    if ($row['filename'] !== 'legacy/attach.txt' || $row['orgfilename'] !== 'attach.txt') {
        throw new RuntimeException('Legacy attachment metadata changed after migration.');
    }

    $reply = $pdo->query("SELECT message, isfirst FROM bbs_post WHERE tid = 11 AND pid = 102")->fetch();
    if (!$reply || $reply['message'] !== 'legacy reply' || (int) $reply['isfirst'] !== 0) {
        throw new RuntimeException('Legacy reply post was not preserved after migration.');
    }

    $attachments = $pdo->query("SELECT pid, filename, orgfilename FROM bbs_attach WHERE tid = 11 ORDER BY aid")->fetchAll();
    if (count($attachments) !== 2) {
        throw new RuntimeException('Legacy attachment count changed after migration.');
    }
    $expectedAttachments = [
        ['pid' => 101, 'filename' => 'legacy/attach.txt', 'orgfilename' => 'attach.txt'],
        ['pid' => 102, 'filename' => 'legacy/reply.txt', 'orgfilename' => 'reply.txt'],
    ];
    foreach ($expectedAttachments as $offset => $expected) {
        $actual = $attachments[$offset];
        if ((int) $actual['pid'] !== $expected['pid'] || $actual['filename'] !== $expected['filename'] || $actual['orgfilename'] !== $expected['orgfilename']) {
            throw new RuntimeException('Legacy attachment relation changed after migration.');
        }
    }

    $top = $pdo->query("SELECT fid, top FROM bbs_thread_top WHERE tid = 11")->fetch();
    if (!$top || (int) $top['fid'] !== 3 || (int) $top['top'] !== 1) {
        throw new RuntimeException('Legacy thread top relation was not preserved after migration.');
    }

    $forumAccess = $pdo->query("SELECT allowread, allowthread, allowpost, allowattach, allowdown FROM bbs_forum_access WHERE fid = 3 AND gid = 101")->fetch();
    if (!$forumAccess) {
        throw new RuntimeException('Legacy forum access relation was not preserved after migration.');
    }
    foreach (['allowread', 'allowthread', 'allowpost', 'allowattach', 'allowdown'] as $field) {
        if ((int) $forumAccess[$field] !== 1) {
            throw new RuntimeException('Legacy forum access flags changed after migration.');
        }
    }

    $mythread = $pdo->query("SELECT COUNT(*) FROM bbs_mythread WHERE uid = 7 AND tid = 11")->fetchColumn();
    if ((int) $mythread !== 1) {
        throw new RuntimeException('Legacy mythread index was not preserved after migration.');
    }

    $myposts = $pdo->query("SELECT pid FROM bbs_mypost WHERE uid = 7 AND tid = 11 ORDER BY pid")->fetchAll(PDO::FETCH_COLUMN);
    if (array_map('intval', $myposts) !== [101, 102]) {
        throw new RuntimeException('Legacy mypost index was not preserved after migration.');
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
