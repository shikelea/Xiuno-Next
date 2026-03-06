<?php

namespace Xiuno\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class UpgradeCommand extends Command
{
    protected static $defaultName = 'upgrade';
    protected static $defaultDescription = '从旧版 Xiuno BBS 升级到 Xiuno Next';

    private const TARGET_VERSION = '4.4.1';

    // 旧版可能缺失的配置项及其默认值
    private const CONFIG_DEFAULTS = [
        'csrf_on' => 1,
        'disabled_plugin' => 0,
        'nav_2_on' => 1,
        'nav_2_forum_list_pc_on' => 0,
        'nav_2_forum_list_mobile_on' => 0,
        'admin_bind_ip' => 0,
        'cdn_on' => 0,
        'url_rewrite_on' => 0,
        'static_version' => '?1.0',
    ];

    // 已知旧版主流版本号
    private const KNOWN_OLD_VERSIONS = ['4.0.4', '4.0.5', '4.0.7'];

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $appPath = realpath(__DIR__ . '/../../../') . '/';

        $confFile = $appPath . 'conf/conf.php';
        if (!is_file($confFile)) {
            $io->error('未找到配置文件 conf/conf.php，请先完成安装。');
            return Command::FAILURE;
        }

        $io->title('Xiuno Next 升级工具');

        $this->bootstrap($appPath);

        $conf = $GLOBALS['conf'];
        $currentVersion = $conf['version'] ?? 'unknown';
        $io->text("当前版本: $currentVersion");
        $io->text("目标版本: " . self::TARGET_VERSION);
        $io->newLine();

        $steps = $this->detectUpgradeSteps();

        if (empty($steps)) {
            $io->success('当前版本已是最新，无需升级。');
            return Command::SUCCESS;
        }

        $io->section('升级预检报告');
        $io->listing($steps);

        $io->warning('请确保已备份数据库和文件后再继续。');
        if (!$io->confirm('是否继续升级？', false)) {
            $io->text('升级已取消。');
            return Command::SUCCESS;
        }

        $io->section('执行升级');
        $errors = [];

        $this->stepConfigUpgrade($io, $errors);
        $this->stepSchemaUpgrade($io, $errors);
        $this->stepRunMigrations($io, $errors);
        $this->stepCleanup($io);

        if (!empty($errors)) {
            $io->error('升级过程中出现以下错误:');
            $io->listing($errors);
            return Command::FAILURE;
        }

        kv_set('xn_upgraded_from', $currentVersion);
        kv_set('xn_upgraded_date', date('Y-m-d H:i:s'));

        $io->newLine();
        $io->success('升级成功！当前版本: ' . self::TARGET_VERSION);
        $io->text([
            '后续步骤:',
            '  1. 访问站点确认功能正常',
            '  2. 登录后台清理缓存 (管理 > 工具 > 更新缓存)',
            '  3. 用户登录时密码会自动从 MD5 升级为 bcrypt，无需手动操作',
        ]);

        return Command::SUCCESS;
    }

    /**
     * 检测当前安装的旧版版本号
     */
    private function detectOldVersion(): string
    {
        $conf = $GLOBALS['conf'];
        $version = $conf['version'] ?? 'unknown';

        // 某些早期版本配置中没有 version 字段
        if ($version === 'unknown') {
            $confFile = APP_PATH . 'conf/conf.php';
            $content = file_get_contents($confFile);
            if (preg_match("/'version'\s*=>\s*'([^']+)'/", $content, $m)) {
                $version = $m[1];
            }
        }

        return $version;
    }

    /**
     * 检测并生成升级预检报告
     */
    private function detectUpgradeSteps(): array
    {
        $steps = [];
        $version = $this->detectOldVersion();

        // 版本比较
        if ($version !== 'unknown' && version_compare($version, self::TARGET_VERSION, '>=')) {
            return []; // 已是最新
        }

        // 1. 检查密码字段类型
        $tablepre = $this->getTablepre();
        try {
            $rows = db_sql_find("SHOW COLUMNS FROM `{$tablepre}user` LIKE 'password'");
            if ($rows && is_array($rows) && !empty($rows)) {
                $type = strtolower($rows[0]['Type'] ?? '');
                if (strpos($type, 'char(32)') !== false || strpos($type, 'char(64)') !== false) {
                    $steps[] = "密码安全升级: password 字段从 $type 扩展为 varchar(255)，支持 bcrypt 哈希";
                }
            }
        } catch (\Throwable $e) {
            // 非致命，继续
        }

        // 2. 检查缺失的配置项
        $confFile = APP_PATH . 'conf/conf.php';
        $confContent = file_get_contents($confFile);
        $missingKeys = [];
        foreach (self::CONFIG_DEFAULTS as $key => $default) {
            if (strpos($confContent, "'$key'") === false) {
                $missingKeys[] = $key;
            }
        }
        if (!empty($missingKeys)) {
            $steps[] = '配置升级: 添加 ' . count($missingKeys) . ' 个缺失配置项 (' . implode(', ', $missingKeys) . ')';
        }

        // 3. 检查待执行的数据库迁移
        $pendingMigrations = $this->getPendingMigrations();
        if (!empty($pendingMigrations)) {
            $names = array_map(function ($f) { return basename($f, '.php'); }, $pendingMigrations);
            $steps[] = '数据库迁移: ' . count($pendingMigrations) . ' 个待执行 (' . implode(', ', $names) . ')';
        }

        // 4. 版本特定提示
        if (in_array($version, ['4.0.4', '4.0.5', '4.0.7'])) {
            $steps[] = "版本跨度: 从 $version 升级到 " . self::TARGET_VERSION . '（含安全加固、BS5 升级、API 支持）';
        }

        // 5. 缓存清理（总是需要）
        $steps[] = '缓存清理: 清理编译缓存和插件 Hook 缓存';

        return $steps;
    }

    /**
     * 获取待执行的迁移文件列表
     */
    private function getPendingMigrations(): array
    {
        $migrationsPath = APP_PATH . 'database/migrations';
        if (!is_dir($migrationsPath)) {
            return [];
        }
        $files = glob($migrationsPath . '/*.php');
        if (empty($files)) {
            return [];
        }
        sort($files);

        $executed = $this->getExecutedMigrations();
        $pending = [];
        foreach ($files as $file) {
            $name = basename($file, '.php');
            if (!in_array($name, $executed)) {
                $pending[] = $file;
            }
        }
        return $pending;
    }

    private function stepConfigUpgrade(SymfonyStyle $io, array &$errors): void
    {
        $io->text('检查配置文件...');

        $confFile = APP_PATH . 'conf/conf.php';
        $confContent = file_get_contents($confFile);

        $additions = [];
        foreach (self::CONFIG_DEFAULTS as $key => $default) {
            if (strpos($confContent, "'$key'") === false) {
                $additions[$key] = $default;
            }
        }

        if (!empty($additions)) {
            try {
                file_replace_var($confFile, $additions);
                $io->text('  配置文件已更新: 添加了 ' . implode(', ', array_keys($additions)));
            } catch (\Throwable $e) {
                $errors[] = '配置更新失败: ' . $e->getMessage();
                $io->text('  [手动修复] 请在 conf/conf.php 中添加以下配置:');
                foreach ($additions as $k => $v) {
                    $val = is_string($v) ? "'$v'" : $v;
                    $io->text("    '$k' => $val,");
                }
            }
        } else {
            $io->text('  配置文件无需更新');
        }
    }

    private function stepSchemaUpgrade(SymfonyStyle $io, array &$errors): void
    {
        $io->text('检查数据库结构...');
        $tablepre = $this->getTablepre();

        try {
            $row = db_sql_find("SHOW COLUMNS FROM `{$tablepre}user` LIKE 'password'");
            if ($row && is_array($row) && !empty($row)) {
                $type = strtolower($row[0]['Type'] ?? '');
                if (strpos($type, 'varchar') === false) {
                    $io->text('  password 字段需要扩展 (当前: ' . $type . ')');
                }
            }
        } catch (\Throwable $e) {
            // Schema check is informational
        }

        $io->text('  数据库结构检查完成（迁移将在下一步执行）');
    }

    private function stepRunMigrations(SymfonyStyle $io, array &$errors): void
    {
        $io->text('执行数据库迁移...');

        $pending = $this->getPendingMigrations();
        if (empty($pending)) {
            $io->text('  所有迁移已是最新');
            return;
        }

        $executed = $this->getExecutedMigrations();
        $tablepre = $this->getTablepre();
        $runCount = 0;

        foreach ($pending as $file) {
            $name = basename($file, '.php');
            try {
                $migration = require $file;
                $migration->up($tablepre);
                $executed[] = $name;
                kv_set('xn_migrations', $executed);
                $io->text("  完成: $name");
                $runCount++;
            } catch (\Throwable $e) {
                $errors[] = "迁移 $name 失败: " . $e->getMessage();
                return;
            }
        }

        $io->text("  成功执行 $runCount 个迁移");
    }

    /**
     * 清理旧文件和缓存
     */
    private function stepCleanup(SymfonyStyle $io): void
    {
        $io->text('清理缓存和临时文件...');

        $tmpPath = $GLOBALS['conf']['tmp_path'];
        $cacheFiles = ['model.min.php', 'plugin_hook.php', 'safe_mode.php'];
        $cleaned = 0;
        foreach ($cacheFiles as $cacheFile) {
            $path = $tmpPath . $cacheFile;
            if (is_file($path)) {
                @unlink($path);
                $cleaned++;
            }
        }
        // safe_mode 标记文件（无扩展名）
        $safeModeFile = $tmpPath . 'safe_mode';
        if (is_file($safeModeFile)) {
            @unlink($safeModeFile);
            $cleaned++;
        }

        $io->text("  清理了 $cleaned 个缓存/临时文件");
    }

    private function bootstrap(string $appPath): void
    {
        if (!defined('DEBUG')) define('DEBUG', 0);
        if (!defined('APP_PATH')) define('APP_PATH', $appPath);
        if (!defined('XIUNOPHP_PATH')) define('XIUNOPHP_PATH', $appPath . 'xiunophp/');

        $conf = (include $appPath . 'conf/conf.php');
        substr($conf['tmp_path'], 0, 2) == './' and $conf['tmp_path'] = APP_PATH . $conf['tmp_path'];
        substr($conf['log_path'], 0, 2) == './' and $conf['log_path'] = APP_PATH . $conf['log_path'];
        substr($conf['upload_path'], 0, 2) == './' and $conf['upload_path'] = APP_PATH . $conf['upload_path'];

        $GLOBALS['conf'] = $conf;
        $_SERVER['conf'] = $conf;

        include XIUNOPHP_PATH . 'xiunophp.php';
        include APP_PATH . 'model/kv.func.php';
    }

    private function getTablepre(): string
    {
        return $_SERVER['db']->tablepre ?? 'bbs_';
    }

    private function getExecutedMigrations(): array
    {
        $val = kv_get('xn_migrations');
        return is_array($val) ? $val : [];
    }
}
