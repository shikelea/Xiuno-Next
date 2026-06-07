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

try {
    $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    apply_sql_mode($pdo, $sqlMode);

    $dbname = safe_database_name($baseName . '_plugin_sql');
    reset_database($pdo, $dbname);
    install_core_schema($pdo, $root);
    seed_thread_table($pdo);
    seed_post_table($pdo);

    smoke_till_post_replies_sql($pdo);
    smoke_haya_post_like_sql($pdo);
    smoke_aitu_source_sql($pdo);
    smoke_huux_notice_user_sql($pdo);
    smoke_tt_read_stately_sql($pdo);
    smoke_tt_offer_forum_sql($pdo);
    smoke_sa_shop_sql($pdo);

    echo "OK: plugin install SQL smoke passed\n";
} catch (Throwable $e) {
    fwrite(STDERR, "FAIL: " . $e->getMessage() . "\n");
    exit(1);
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

function seed_post_table(PDO $pdo): void
{
    $pdo->exec("INSERT INTO bbs_post SET tid=1, uid=1, isfirst=1, create_date=1700000000, userip=2130706433, message='seed message', message_fmt='seed message'");

    $count = (int)$pdo->query('SELECT COUNT(*) FROM bbs_post')->fetchColumn();
    if ($count < 1) {
        throw new RuntimeException('Unable to seed bbs_post before plugin ALTER smoke.');
    }
}

function seed_thread_table(PDO $pdo): void
{
    $pdo->exec("INSERT INTO bbs_thread SET fid=1, tid=1, uid=1, subject='seed thread', create_date=1700000000, last_date=1700000000, firstpid=1, lastpid=1");

    $count = (int)$pdo->query('SELECT COUNT(*) FROM bbs_thread')->fetchColumn();
    if ($count < 1) {
        throw new RuntimeException('Unable to seed bbs_thread before plugin ALTER smoke.');
    }
}

function smoke_till_post_replies_sql(PDO $pdo): void
{
    $pdo->exec("ALTER TABLE bbs_post ADD COLUMN `repeat_follow` LONGTEXT NOT NULL, ADD COLUMN `r_f_c` SMALLINT(6) UNSIGNED DEFAULT 0 NOT NULL, ADD COLUMN `r_f_a` SMALLINT(6) UNSIGNED DEFAULT 0 NOT NULL");
    assert_column_exists($pdo, 'bbs_post', 'repeat_follow');
    assert_column_exists($pdo, 'bbs_post', 'r_f_c');
    assert_column_exists($pdo, 'bbs_post', 'r_f_a');
    assert_seed_post_alter_defaults($pdo);

    $pdo->exec("ALTER TABLE bbs_post DROP COLUMN `repeat_follow`, DROP COLUMN `r_f_c`, DROP COLUMN `r_f_a`");
    assert_column_missing($pdo, 'bbs_post', 'repeat_follow');
    assert_column_missing($pdo, 'bbs_post', 'r_f_c');
    assert_column_missing($pdo, 'bbs_post', 'r_f_a');
}

function smoke_haya_post_like_sql(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE bbs_post_like (
        lid int(11) unsigned NOT NULL AUTO_INCREMENT,
        uid int(11) unsigned NOT NULL DEFAULT '0',
        tid int(11) unsigned NOT NULL DEFAULT '0',
        pid int(11) unsigned NOT NULL DEFAULT '0',
        create_date int(11) unsigned NOT NULL DEFAULT '0',
        PRIMARY KEY (lid),
        KEY pid (pid),
        KEY uid (uid)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8");
    $pdo->exec("ALTER TABLE bbs_post ADD COLUMN likes int(11) NULL DEFAULT '0' COMMENT 'like count'");
    $pdo->exec("ALTER TABLE bbs_thread ADD COLUMN likes int(11) NULL DEFAULT '0' COMMENT 'like count'");

    assert_table_exists($pdo, 'bbs_post_like');
    assert_table_engine($pdo, 'bbs_post_like', 'MyISAM');
    assert_column_exists($pdo, 'bbs_post', 'likes');
    assert_column_exists($pdo, 'bbs_thread', 'likes');
    assert_seed_numeric_default($pdo, 'bbs_post', 'pid', 1, 'likes', 0);
    assert_seed_numeric_default($pdo, 'bbs_thread', 'tid', 1, 'likes', 0);

    $pdo->exec('DROP TABLE IF EXISTS `bbs_post_like`');
    $pdo->exec('ALTER TABLE bbs_post DROP COLUMN likes');
    $pdo->exec('ALTER TABLE bbs_thread DROP COLUMN likes');
    assert_table_missing($pdo, 'bbs_post_like');
    assert_column_missing($pdo, 'bbs_post', 'likes');
    assert_column_missing($pdo, 'bbs_thread', 'likes');
}

function smoke_aitu_source_sql(PDO $pdo): void
{
    $pdo->exec("ALTER TABLE bbs_post ADD COLUMN source VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'source'");
    $pdo->exec("ALTER TABLE bbs_thread ADD COLUMN thumbnail VARCHAR(980) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '0' COMMENT 'thumbnail'");

    assert_column_exists($pdo, 'bbs_post', 'source');
    assert_column_exists($pdo, 'bbs_thread', 'thumbnail');
    assert_seed_string_default($pdo, 'bbs_post', 'pid', 1, 'source', '');
    assert_seed_string_default($pdo, 'bbs_thread', 'tid', 1, 'thumbnail', '0');

    $pdo->exec('ALTER TABLE bbs_post DROP COLUMN source');
    $pdo->exec('ALTER TABLE bbs_thread DROP COLUMN thumbnail');
    assert_column_missing($pdo, 'bbs_post', 'source');
    assert_column_missing($pdo, 'bbs_thread', 'thumbnail');
}

function smoke_huux_notice_user_sql(PDO $pdo): void
{
    $pdo->exec("ALTER TABLE bbs_user ADD COLUMN notices mediumint(8) unsigned NOT NULL default '0'");
    $pdo->exec("ALTER TABLE bbs_user ADD COLUMN unread_notices mediumint(8) unsigned NOT NULL default '0'");

    assert_column_exists($pdo, 'bbs_user', 'notices');
    assert_column_exists($pdo, 'bbs_user', 'unread_notices');
    assert_seed_numeric_default($pdo, 'bbs_user', 'uid', 1, 'notices', 0);
    assert_seed_numeric_default($pdo, 'bbs_user', 'uid', 1, 'unread_notices', 0);

    $pdo->exec('ALTER TABLE bbs_user DROP COLUMN notices');
    $pdo->exec('ALTER TABLE bbs_user DROP COLUMN unread_notices');
    assert_column_missing($pdo, 'bbs_user', 'notices');
    assert_column_missing($pdo, 'bbs_user', 'unread_notices');
}

function smoke_tt_read_stately_sql(PDO $pdo): void
{
    $pdo->exec("ALTER TABLE bbs_group ADD readp int(5) NOT NULL default '1'");
    $pdo->exec("ALTER TABLE bbs_thread ADD readp int(5) NOT NULL default '0'");
    $pdo->exec("ALTER TABLE bbs_group ADD allowPostRead int(5) NOT NULL default '1'");

    assert_column_exists($pdo, 'bbs_group', 'readp');
    assert_column_exists($pdo, 'bbs_group', 'allowPostRead');
    assert_column_exists($pdo, 'bbs_thread', 'readp');
    assert_seed_numeric_default($pdo, 'bbs_group', 'gid', 1, 'readp', 1);
    assert_seed_numeric_default($pdo, 'bbs_group', 'gid', 1, 'allowPostRead', 1);
    assert_seed_numeric_default($pdo, 'bbs_thread', 'tid', 1, 'readp', 0);

    $pdo->exec('ALTER TABLE bbs_group DROP COLUMN readp');
    $pdo->exec('ALTER TABLE bbs_group DROP COLUMN allowPostRead');
    $pdo->exec('ALTER TABLE bbs_thread DROP COLUMN readp');
    assert_column_missing($pdo, 'bbs_group', 'readp');
    assert_column_missing($pdo, 'bbs_group', 'allowPostRead');
    assert_column_missing($pdo, 'bbs_thread', 'readp');
}

function smoke_tt_offer_forum_sql(PDO $pdo): void
{
    $pdo->exec("ALTER TABLE bbs_group ADD allowOffer INT(5) NOT NULL default '0'");
    $pdo->exec("ALTER TABLE bbs_forum ADD allowOffer INT(5) NOT NULL default '0'");
    $pdo->exec("ALTER TABLE bbs_thread ADD offerNum INT(20) NOT NULL default '0'");
    $pdo->exec("ALTER TABLE bbs_thread ADD offerStatus INT(20) NOT NULL default '0'");

    assert_column_exists($pdo, 'bbs_group', 'allowOffer');
    assert_column_exists($pdo, 'bbs_forum', 'allowOffer');
    assert_column_exists($pdo, 'bbs_thread', 'offerNum');
    assert_column_exists($pdo, 'bbs_thread', 'offerStatus');
    assert_seed_numeric_default($pdo, 'bbs_group', 'gid', 1, 'allowOffer', 0);
    assert_seed_numeric_default($pdo, 'bbs_forum', 'fid', 1, 'allowOffer', 0);
    assert_seed_numeric_default($pdo, 'bbs_thread', 'tid', 1, 'offerNum', 0);
    assert_seed_numeric_default($pdo, 'bbs_thread', 'tid', 1, 'offerStatus', 0);

    $pdo->exec('ALTER TABLE bbs_group DROP COLUMN allowOffer');
    $pdo->exec('ALTER TABLE bbs_forum DROP COLUMN allowOffer');
    $pdo->exec('ALTER TABLE bbs_thread DROP COLUMN offerNum');
    $pdo->exec('ALTER TABLE bbs_thread DROP COLUMN offerStatus');
    assert_column_missing($pdo, 'bbs_group', 'allowOffer');
    assert_column_missing($pdo, 'bbs_forum', 'allowOffer');
    assert_column_missing($pdo, 'bbs_thread', 'offerNum');
    assert_column_missing($pdo, 'bbs_thread', 'offerStatus');
}

function assert_seed_post_alter_defaults(PDO $pdo): void
{
    $row = $pdo->query('SELECT repeat_follow, r_f_c, r_f_a FROM bbs_post WHERE pid = 1')->fetch();
    if (!$row) {
        throw new RuntimeException('Missing seeded post after plugin ALTER smoke.');
    }
    if ($row['repeat_follow'] === null) {
        throw new RuntimeException('repeat_follow should be NOT NULL on existing rows after plugin ALTER smoke.');
    }
    if ((int)$row['r_f_c'] !== 0 || (int)$row['r_f_a'] !== 0) {
        throw new RuntimeException('reply follow counters should default to 0 on existing rows after plugin ALTER smoke.');
    }
}

function assert_seed_numeric_default(PDO $pdo, string $table, string $idColumn, int $id, string $column, int $expected): void
{
    $stmt = $pdo->prepare("SELECT `$column` FROM `$table` WHERE `$idColumn` = ?");
    $stmt->execute([$id]);
    $value = $stmt->fetchColumn();
    if ($value === false) {
        throw new RuntimeException("Missing seeded row after plugin ALTER smoke: $table.$idColumn=$id");
    }
    if ((int)$value !== $expected) {
        throw new RuntimeException("$table.$column should default to $expected on existing rows after plugin ALTER smoke.");
    }
}

function assert_seed_string_default(PDO $pdo, string $table, string $idColumn, int $id, string $column, string $expected): void
{
    $stmt = $pdo->prepare("SELECT `$column` FROM `$table` WHERE `$idColumn` = ?");
    $stmt->execute([$id]);
    $value = $stmt->fetchColumn();
    if ($value === false) {
        throw new RuntimeException("Missing seeded row after plugin ALTER smoke: $table.$idColumn=$id");
    }
    if ((string)$value !== $expected) {
        throw new RuntimeException("$table.$column should default to '$expected' on existing rows after plugin ALTER smoke.");
    }
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
