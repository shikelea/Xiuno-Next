<?php

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Unable to locate project root.\n");
    exit(1);
}

function fail($message) {
    fwrite(STDERR, "FAIL: $message\n");
    exit(1);
}

function read_file_checked($path) {
    $content = file_get_contents($path);
    if ($content === false) {
        fail("Unable to read $path");
    }
    return str_replace(["\r\n", "\r"], "\n", $content);
}

function remove_dir_checked($path) {
    if (!is_dir($path)) {
        return;
    }

    $items = scandir($path);
    if ($items === false) {
        fail("Unable to scan $path");
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $child = $path . '/' . $item;
        if (is_dir($child)) {
            remove_dir_checked($child);
        } elseif (!unlink($child)) {
            fail("Unable to remove $child");
        }
    }

    if (!rmdir($path)) {
        fail("Unable to remove $path");
    }
}

function run_cli_from($cwd, $launcher, $args, &$output = '') {
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($launcher) . ' ' . $args . ' 2>&1';
    $old = getcwd();
    if (!chdir($cwd)) {
        fail("Unable to chdir to $cwd");
    }

    $lines = [];
    exec($command, $lines, $code);
    chdir($old);
    $output = implode("\n", $lines);
    return $code;
}

$makePlugin = read_file_checked($root . '/src/Console/Command/MakePluginCommand.php');
$launcher = read_file_checked($root . '/bin/xiuno');
$composer = json_decode(read_file_checked($root . '/composer.json'), true);
if (!is_array($composer)) {
    fail('composer.json must be valid JSON.');
}
foreach (($composer['autoload']['files'] ?? []) as $file) {
    if (str_replace('\\', '/', $file) === 'xiunophp/xiunophp.php') {
        fail('Composer must not autoload xiunophp.php before conf/conf.php is loaded.');
    }
}
strpos($launcher, "defined('APP_PATH') || define('APP_PATH', \$root . DIRECTORY_SEPARATOR);") !== false
    || fail('bin/xiuno must define APP_PATH before Composer autoload loads xiunophp.php.');
strpos($launcher, "require APP_PATH . 'vendor/autoload.php';") !== false
    || fail('bin/xiuno must load Composer from APP_PATH.');

strpos($makePlugin, "realpath(dirname(__DIR__, 3))") !== false
    || fail('make:plugin must resolve the project root from the command class, not from the current working directory.');
strpos($makePlugin, 'private function writeFile(string $path, string $content): void') !== false
    || fail('make:plugin must centralize checked scaffold file writes.');
strpos($makePlugin, 'removeDirectory($pluginPath)') !== false
    || fail('make:plugin must clean up the scaffold directory after a partial write failure.');
strpos($makePlugin, "require_once \$projectRoot . '/xiunophp/plugin_identifier.func.php';") !== false
    && strpos($makePlugin, 'xn_plugin_dir_is_valid($name)') !== false
    || fail('make:plugin must reuse the core plugin identifier helper instead of carrying a second regex contract.');

$migrate = read_file_checked($root . '/src/Console/Command/MigrateCommand.php');
$upgrade = read_file_checked($root . '/src/Console/Command/UpgradeCommand.php');
foreach (['MigrateCommand.php' => $migrate, 'UpgradeCommand.php' => $upgrade] as $file => $source) {
    strpos($source, "include_once XIUNOPHP_PATH . 'xiunophp.php'") !== false
        || fail("$file must use include_once for xiunophp.php because Composer already autoloads it.");
    strpos($source, "include_once APP_PATH . 'model/kv.func.php'") !== false
        || fail("$file must use include_once for kv.func.php.");
	strpos($source, "include_once APP_PATH . 'model/migration.func.php'") !== false
		|| fail("$file must load the shared command-only migration helper.");
	strpos($source, 'migration_capability()') !== false
		|| fail("$file must fail closed on unsupported database migration capabilities.");
	strpos($source, 'migration_advisory_lock_start()') !== false
		&& strpos($source, 'migration_advisory_lock_end()') !== false
		|| fail("$file must use the shared schema advisory lock.");
    strpos($source, 'private function isValidMigration($migration): bool') !== false
        || fail("$file must validate migration up() signatures.");
    strpos($source, 'new \ReflectionMethod($migration, \'up\')') !== false
        || fail("$file must use ReflectionMethod to validate migration signatures.");
}
strpos($migrate, 'if (!$this->isValidMigration($migration))') !== false
    || fail('migrate must validate migration signatures immediately before executing up().');
strpos($upgrade, 'if (!$this->isValidMigration($migration))') !== false
    || fail('upgrade must validate migration signatures immediately before executing up().');

strpos($migrate, 'private function recordMigration(string $name, array &$executed): bool') !== false
    || fail('migrate must expose a checked migration record result.');
strpos($migrate, 'return migration_record_read_primary();') !== false
	&& strpos($migrate, 'return migration_record_append_locked($name, $executed);') !== false
	&& strpos($migrate, 'return migration_table_prefix();') !== false
	|| fail('migrate must delegate primary records, locked append, and prefix validation to the shared helper.');
strpos($upgrade, 'return migration_record_read_primary();') !== false
	&& strpos($upgrade, 'migration_record_append_locked($name, $executed);') !== false
	&& strpos($upgrade, 'return migration_table_prefix();') !== false
	|| fail('upgrade must delegate primary records, locked append, and prefix validation to the shared helper.');
strpos($upgrade, "kv_get('xn_migrations'") === false
	&& strpos($upgrade, 'db_sql_find("SHOW COLUMNS') === false
	&& strpos($upgrade, 'db_sql_find_one_master("SHOW COLUMNS') !== false
	|| fail('upgrade schema and migration preflight must not fall back to cached or replica reads.');
strpos($upgrade, "kv__get('xn_upgraded_from', true)") !== false
	|| fail('upgrade metadata must be read from the primary database before deciding whether it is absent.');

$upgradeLock = strpos($upgrade, '$lock = migration_advisory_lock_start();');
$upgradeFingerprint = strpos($upgrade, '$lockedFingerprint = hash_file(\'sha256\', $confFile);', $upgradeLock === false ? 0 : $upgradeLock);
$upgradeRecompute = strpos($upgrade, '$lockedSteps = $this->detectUpgradeSteps();', $upgradeLock === false ? 0 : $upgradeLock);
$upgradeFirstWrite = strpos($upgrade, '$this->stepConfigUpgrade($io, $errors);', $upgradeLock === false ? 0 : $upgradeLock);
$upgradeRelease = strpos($upgrade, '$released = migration_advisory_lock_end();', $upgradeLock === false ? 0 : $upgradeLock);
$upgradeLock !== false
	&& $upgradeFingerprint !== false && $upgradeFingerprint > $upgradeLock
	&& $upgradeRecompute !== false && $upgradeRecompute > $upgradeFingerprint
	&& $upgradeFirstWrite !== false && $upgradeFirstWrite > $upgradeRecompute
	&& $upgradeRelease !== false && $upgradeRelease > $upgradeFirstWrite
	|| fail('upgrade must fingerprint and recompute database-dependent steps inside the shared lock before its first write, then release in finally.');
substr_count($upgrade, "hash_file('sha256', \$confFile)") === 2
	&& strpos($upgrade, 'hash_equals($confFingerprint, $lockedFingerprint)') !== false
	&& strpos($upgrade, 'conf/conf.php 在预检确认期间发生变化') !== false
	|| fail('upgrade must reject a changed configuration generation after operator confirmation.');
preg_match(
    "/file_replace_var\\(APP_PATH \\. 'conf\\/conf\\.php', \\[\\s*'version' => self::TARGET_VERSION,\\s*'static_version' => self::CONFIG_DEFAULTS\\['static_version'\\],\\s*\\]\\) === false/s",
    $upgrade
) === 1 || fail('upgrade must write the target version and static asset version together, and fail when that write fails.');
strpos($upgrade, "Upgrade metadata could not be recorded.") !== false
    || fail('upgrade must fail when upgrade metadata cannot be recorded.');
strpos($upgrade, "'allow_unverified_update' => 0") === false
    || fail('upgrade must not restore the removed unverified-update bypass.');
strpos($upgrade, 'private function reportErrors(SymfonyStyle $io, array $errors): int') !== false
    || fail('upgrade must centralize failure reporting for staged upgrade errors.');
strpos($upgrade, "throw new \\RuntimeException(implode(' | ', \$errors));") !== false
	|| fail('upgrade must stop the locked sequence when any staged config, schema, or migration step fails.');

$migration = read_file_checked($root . '/database/migrations/0001_alter_user_password_field.php');
strpos($migration, '$ok = db_exec(') !== false
    || fail('password migration must check db_exec result.');
strpos($migration, "throw new RuntimeException('Failed to alter user password field.')") !== false
    || fail('password migration must throw when db_exec fails.');

$authEpochMigration = read_file_checked($root . '/database/migrations/0002_add_user_auth_epoch.php');
strpos($authEpochMigration, 'SHOW COLUMNS FROM `{$table}`') !== false
    && strpos($authEpochMigration, "LIKE 'auth_epoch'") !== false
    || fail('auth epoch migration must be idempotent and inspect the existing field.');
strpos($authEpochMigration, 'ADD `auth_epoch` int(11) unsigned NOT NULL DEFAULT \'0\'') !== false
    || fail('auth epoch migration must add a non-negative credential generation field.');
strpos($authEpochMigration, "throw new RuntimeException('Failed to add the user auth_epoch field.')") !== false
    || fail('auth epoch migration must throw when ALTER TABLE fails.');

$fixtureToken = bin2hex(random_bytes(8));
$fixtureRoot = rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/') . '/xiuno-cli-guard-' . $fixtureToken;
$fixtureCommandDir = $fixtureRoot . '/src/Console/Command';
$fixtureBinDir = $fixtureRoot . '/bin';
$fixtureXiunoPhpDir = $fixtureRoot . '/xiunophp';
$tmpCwd = $fixtureRoot . '/work/cwd';
if (!mkdir($fixtureCommandDir, 0755, true)
    || !mkdir($fixtureBinDir, 0755, true)
    || !mkdir($fixtureXiunoPhpDir, 0755, true)
    || !mkdir($tmpCwd, 0755, true)) {
    remove_dir_checked($fixtureRoot);
    fail('Unable to create isolated CLI guard project.');
}
register_shutdown_function(function () use ($fixtureRoot) {
    remove_dir_checked($fixtureRoot);
});

$migrationGuard = $fixtureRoot . '/migration-record-guard.php';
$migrationGuardSource = "<?php\n"
    . 'require ' . var_export($root . '/vendor/autoload.php', true) . ";\n"
    . "\$guardRead = [];\n\$guardReads = [];\n\$guardWrites = [];\n"
    . "function kv__get(\$key, \$primary = false) { global \$guardRead, \$guardReads; \$guardReads[] = [\$key, \$primary]; if (\$key !== 'xn_migrations' || \$primary !== true) return false; return \$guardRead; }\n"
    . "function kv_set(\$key, \$value, \$life = 0) { global \$guardRead, \$guardWrites; if (\$key !== 'xn_migrations') return false; \$guardWrites[] = \$value; \$guardRead = \$value; return true; }\n"
    . 'require ' . var_export($root . '/model/migration.func.php', true) . ";\n"
    . 'require ' . var_export($root . '/src/Console/Command/MigrateCommand.php', true) . ";\n"
    . "\$GLOBALS['g_migration_advisory_lock'] = ['active'=>true, 'name'=>'fixture-lock', 'link'=>null];\n"
    . "\$command = new Xiuno\\Console\\Command\\MigrateCommand();\n"
    . "\$load = new ReflectionMethod(\$command, 'getExecutedMigrations');\n\$load->setAccessible(true);\n"
    . "\$record = new ReflectionMethod(\$command, 'recordMigration');\n\$record->setAccessible(true);\n"
    . "\$executed = \$load->invoke(\$command);\n"
    . "\$args = ['0001_alter_user_password_field', &\$executed]; if (!\$record->invokeArgs(\$command, \$args)) exit(11);\n"
    . "\$args = ['0002_add_user_auth_epoch', &\$executed]; if (!\$record->invokeArgs(\$command, \$args)) exit(12);\n"
    . "if (\$executed !== ['0001_alter_user_password_field', '0002_add_user_auth_epoch']) exit(13);\n"
    . "if (\$guardWrites !== [['0001_alter_user_password_field'], ['0001_alter_user_password_field', '0002_add_user_auth_epoch']]) exit(14);\n"
    . "foreach (\$guardReads as \$read) { if (\$read !== ['xn_migrations', true]) exit(19); }\n"
    . "\$guardRead = false; try { \$load->invoke(\$command); exit(15); } catch (Throwable \$e) { if (strpos(\$e->getMessage(), 'primary migration record read failed') === false) exit(16); }\n"
    . "\$guardRead = [123]; try { \$load->invoke(\$command); exit(17); } catch (Throwable \$e) { if (strpos(\$e->getMessage(), 'invalid name') === false) exit(18); }\n"
    . "\$GLOBALS['g_migration_advisory_lock'] = null; \$before = count(\$guardWrites); try { \$args = ['0003_locked_only', &\$executed]; \$record->invokeArgs(\$command, \$args); exit(20); } catch (Throwable \$e) { if (strpos(\$e->getMessage(), 'requires the shared schema lock') === false || count(\$guardWrites) !== \$before) exit(21); }\n";
file_put_contents($migrationGuard, $migrationGuardSource, LOCK_EX) === strlen($migrationGuardSource)
    || fail('Unable to write the migration record behavior guard.');
$migrationOutput = [];
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($migrationGuard) . ' 2>&1', $migrationOutput, $migrationCode);
if ($migrationCode !== 0) {
    fail('Migrate command did not preserve both records in one process (exit ' . $migrationCode . '): ' . implode("\n", $migrationOutput));
}

$fixtureCommand = $fixtureCommandDir . '/MakePluginCommand.php';
copy($root . '/src/Console/Command/MakePluginCommand.php', $fixtureCommand)
    || fail('Unable to copy make:plugin into the isolated CLI guard project.');
copy($root . '/xiunophp/plugin_identifier.func.php', $fixtureXiunoPhpDir . '/plugin_identifier.func.php')
    || fail('Unable to copy the shared plugin identifier helper into the isolated CLI guard project.');
$fixtureLauncher = $fixtureBinDir . '/xiuno';
$fixtureLauncherSource = "<?php\n"
    . 'require ' . var_export($root . '/vendor/autoload.php', true) . ";\n"
    . 'require ' . var_export($fixtureCommand, true) . ";\n"
    . "\$application = new Symfony\\Component\\Console\\Application('Xiuno CLI guard');\n"
    . "\$application->add(new Xiuno\\Console\\Command\\MakePluginCommand());\n"
    . "\$application->run();\n";
file_put_contents($fixtureLauncher, $fixtureLauncherSource, LOCK_EX) === strlen($fixtureLauncherSource)
    || fail('Unable to write the isolated CLI guard launcher.');

$pluginName = 'cli-guard-plugin';
$pluginDir = $fixtureRoot . '/plugin/' . $pluginName;

$output = '';
$code = run_cli_from($tmpCwd, $fixtureLauncher, 'make:plugin ' . $pluginName, $output);
if ($code !== 0) {
    fail("make:plugin failed from a non-root cwd:\n$output");
}
if (!is_file($pluginDir . '/conf.json')) {
    fail('make:plugin did not create the plugin under the isolated project root.');
}
if (is_dir($tmpCwd . '/plugin/' . $pluginName)) {
    fail('make:plugin created the plugin under the current working directory.');
}

$conf = json_decode(file_get_contents($pluginDir . '/conf.json'), true);
if (!is_array($conf) || ($conf['name'] ?? '') !== $pluginName) {
    fail('Generated plugin conf.json is invalid.');
}

foreach (['hook/index_inc_start.php', 'install.php', 'unstall.php', 'upgrade.php'] as $relative) {
    $file = $pluginDir . '/' . $relative;
    $lint = [];
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $lint, $lintCode);
    if ($lintCode !== 0) {
        fail("Generated plugin file does not pass php -l: $relative\n" . implode("\n", $lint));
    }
}

$maxPluginName = str_repeat('a', 63) . '-';
$maxPluginDir = $fixtureRoot . '/plugin/' . $maxPluginName;
$code = run_cli_from($tmpCwd, $fixtureLauncher, 'make:plugin ' . $maxPluginName, $output);
if ($code !== 0 || !is_file($maxPluginDir . '/conf.json')) {
    fail("make:plugin must accept the shared 64-character identifier boundary:\n$output");
}

foreach (['-invalid-leading-hyphen', str_repeat('b', 65), 'invalid.dot'] as $invalidPluginName) {
    $code = run_cli_from($tmpCwd, $fixtureLauncher, 'make:plugin ' . $invalidPluginName, $output);
    if ($code === 0 || is_dir($fixtureRoot . '/plugin/' . $invalidPluginName)) {
        fail('make:plugin must reject identifiers outside the shared runtime contract: ' . $invalidPluginName);
    }
}

remove_dir_checked($fixtureRoot);

echo "OK: CLI and upgrade safety checks passed\n";
