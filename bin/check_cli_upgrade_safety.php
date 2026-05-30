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
    return $content;
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

function run_cli_from($cwd, $args, &$output = '') {
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($GLOBALS['root'] . '/bin/xiuno') . ' ' . $args . ' 2>&1';
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
strpos($makePlugin, "realpath(dirname(__DIR__, 3))") !== false
    || fail('make:plugin must resolve the project root from the command class, not from the current working directory.');
strpos($makePlugin, 'private function writeFile(string $path, string $content): void') !== false
    || fail('make:plugin must centralize checked scaffold file writes.');
strpos($makePlugin, 'removeDirectory($pluginPath)') !== false
    || fail('make:plugin must clean up the scaffold directory after a partial write failure.');
strpos($makePlugin, 'preg_match(\'/^\\w{1,32}$/\', $name)') !== false
    || fail('make:plugin must follow the core plugin directory length and word-character boundary.');

$migrate = read_file_checked($root . '/src/Console/Command/MigrateCommand.php');
$upgrade = read_file_checked($root . '/src/Console/Command/UpgradeCommand.php');
foreach (['MigrateCommand.php' => $migrate, 'UpgradeCommand.php' => $upgrade] as $file => $source) {
    strpos($source, "include_once XIUNOPHP_PATH . 'xiunophp.php'") !== false
        || fail("$file must use include_once for xiunophp.php because Composer already autoloads it.");
    strpos($source, "include_once APP_PATH . 'model/kv.func.php'") !== false
        || fail("$file must use include_once for kv.func.php.");
    strpos($source, 'private function isValidMigration($migration): bool') !== false
        || fail("$file must validate migration up() signatures.");
    strpos($source, 'new \ReflectionMethod($migration, \'up\')') !== false
        || fail("$file must use ReflectionMethod to validate migration signatures.");
}

strpos($migrate, 'private function recordMigration(string $name): bool') !== false
    || fail('migrate must expose a checked migration record result.');
strpos($migrate, 'return kv_set(\'xn_migrations\', $executed) !== false') !== false
    || fail('migrate must fail when migration record writing fails.');
strpos($upgrade, 'kv_set(\'xn_migrations\', $executed) === false') !== false
    || fail('upgrade must fail when migration record writing fails.');
strpos($upgrade, "file_replace_var(APP_PATH . 'conf/conf.php', ['version' => self::TARGET_VERSION]) === false") !== false
    || fail('upgrade must fail when the target version cannot be written.');
strpos($upgrade, "Upgrade metadata could not be recorded.") !== false
    || fail('upgrade must fail when upgrade metadata cannot be recorded.');

$migration = read_file_checked($root . '/database/migrations/0001_alter_user_password_field.php');
strpos($migration, '$ok = db_exec(') !== false
    || fail('password migration must check db_exec result.');
strpos($migration, "throw new RuntimeException('Failed to alter user password field.')") !== false
    || fail('password migration must throw when db_exec fails.');

$pluginName = 'cli_guard_plugin';
$pluginDir = $root . '/plugin/' . $pluginName;
$tmpCwd = $root . '/tmp/cli_guard_cwd';
remove_dir_checked($pluginDir);
remove_dir_checked($tmpCwd);
if (!mkdir($tmpCwd, 0755, true)) {
    fail('Unable to create CLI guard cwd.');
}

$output = '';
$code = run_cli_from($tmpCwd, 'make:plugin ' . $pluginName, $output);
if ($code !== 0) {
    remove_dir_checked($tmpCwd);
    fail("make:plugin failed from a non-root cwd:\n$output");
}
if (!is_file($pluginDir . '/conf.json')) {
    remove_dir_checked($tmpCwd);
    fail('make:plugin did not create the plugin under the project root.');
}
if (is_dir($tmpCwd . '/plugin/' . $pluginName)) {
    remove_dir_checked($pluginDir);
    remove_dir_checked($tmpCwd);
    fail('make:plugin created the plugin under the current working directory.');
}

$conf = json_decode(file_get_contents($pluginDir . '/conf.json'), true);
if (!is_array($conf) || ($conf['name'] ?? '') !== $pluginName) {
    remove_dir_checked($pluginDir);
    remove_dir_checked($tmpCwd);
    fail('Generated plugin conf.json is invalid.');
}

foreach (['hook/index_inc_start.php', 'install.php', 'unstall.php', 'upgrade.php'] as $relative) {
    $file = $pluginDir . '/' . $relative;
    $lint = [];
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $lint, $lintCode);
    if ($lintCode !== 0) {
        remove_dir_checked($pluginDir);
        remove_dir_checked($tmpCwd);
        fail("Generated plugin file does not pass php -l: $relative\n" . implode("\n", $lint));
    }
}

remove_dir_checked($pluginDir);
remove_dir_checked($tmpCwd);

echo "OK: CLI and upgrade safety checks passed\n";
