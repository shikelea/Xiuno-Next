<?php

namespace Xiuno\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'upgrade', description: '从旧版 Xiuno BBS 升级到 Xiuno Next')]
class UpgradeCommand extends Command
{
    private const TARGET_VERSION = '4.5.6';

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
        'static_version' => '?v=4.5.6',
    ];

    // 已知旧版主流版本号
    private const KNOWN_OLD_VERSIONS = ['4.0.4', '4.0.5', '4.0.7'];

    protected function configure(): void
    {
        $this->addOption('check', null, InputOption::VALUE_NONE, '只检查升级工具元数据和迁移文件，不连接数据库或修改配置');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $appPath = realpath(__DIR__ . '/../../../') . '/';

        if ($input->getOption('check')) {
            return $this->checkUpgradeMetadata($io, $appPath);
        }

        $confFile = $appPath . 'conf/conf.php';
        if (!is_file($confFile)) {
            $io->error('未找到配置文件 conf/conf.php，请先完成安装。');
            return Command::FAILURE;
        }

        $io->title('Xiuno Next 升级工具');

        $this->bootstrap($appPath);

        $capability = migration_capability();
        if (empty($capability['ok'])) {
            $io->error($capability['error']);
            return Command::FAILURE;
        }

        $confFingerprint = hash_file('sha256', $confFile);
        if (!is_string($confFingerprint) || $confFingerprint === '') {
            $io->error('无法读取 conf/conf.php 以建立升级预检快照。');
            return Command::FAILURE;
        }

        $conf = $GLOBALS['conf'];
        $currentVersion = $conf['version'] ?? 'unknown';
        $io->text("当前版本: $currentVersion");
        $io->text("目标版本: " . self::TARGET_VERSION);
        $io->newLine();

        if ($currentVersion !== 'unknown' && version_compare($currentVersion, self::TARGET_VERSION, '>')) {
            $io->error('当前站点版本高于本升级工具目标版本，已拒绝降级。');
            return Command::FAILURE;
        }

        $isVersionUpgrade = $currentVersion === 'unknown'
            || version_compare($currentVersion, self::TARGET_VERSION, '<');

        try {
            $steps = $this->detectUpgradeSteps();
        } catch (\Throwable $e) {
            $io->error('升级预检失败: ' . $e->getMessage());
            return Command::FAILURE;
        }

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

        $lock = migration_advisory_lock_start();
        if (empty($lock['ok'])) {
            $io->error($lock['error']);
            return Command::FAILURE;
        }

        $status = Command::FAILURE;
        $completed = false;
        $becameCurrent = false;
        $executionError = '';
        $released = false;
        try {
            $lockedFingerprint = hash_file('sha256', $confFile);
            if (!is_string($lockedFingerprint) || !hash_equals($confFingerprint, $lockedFingerprint)) {
                throw new \RuntimeException('conf/conf.php 在预检确认期间发生变化；未执行升级，请重新运行命令。');
            }

            // The lock may have been held by another process while the operator confirmed. Re-read
            // every database-dependent input inside the shared schema boundary before any write.
            $lockedSteps = $this->detectUpgradeSteps();
            if (empty($lockedSteps)) {
                $becameCurrent = true;
                $status = Command::SUCCESS;
            } else {
                $io->section('执行升级');
                $errors = [];

                $this->stepConfigUpgrade($io, $errors);
                if (!empty($errors)) throw new \RuntimeException(implode(' | ', $errors));

                $this->stepSchemaUpgrade($io, $errors);
                if (!empty($errors)) throw new \RuntimeException(implode(' | ', $errors));

                $this->stepRunMigrations($io, $errors);
                if (!empty($errors)) throw new \RuntimeException(implode(' | ', $errors));

                $this->stepCleanup($io);

                if ($isVersionUpgrade) {
                    $upgradedFrom = kv__get('xn_upgraded_from', true);
                    if ($upgradedFrom === false) {
                        throw new \RuntimeException('Upgrade metadata primary read failed.');
                    }
                    if (($upgradedFrom === null && kv_set('xn_upgraded_from', $currentVersion) === false)
                        || kv_set('xn_upgraded_date', date('Y-m-d H:i:s')) === false) {
                        throw new \RuntimeException('Upgrade metadata could not be recorded.');
                    }
                }

                // Version is the final commit marker. MySQL DDL and file writes are not a single
                // transaction; any earlier failure remains a forward-retry condition.
                if (file_replace_var(APP_PATH . 'conf/conf.php', [
                    'version' => self::TARGET_VERSION,
                    'static_version' => self::CONFIG_DEFAULTS['static_version'],
                ]) === false) {
                    throw new \RuntimeException(
                        'conf.php version write failed; schema or metadata may already have changed. '
                        . 'Repair the file write error and retry the upgrade.'
                    );
                }

                $completed = true;
                $status = Command::SUCCESS;
            }
        } catch (\Throwable $e) {
            $executionError = $e->getMessage();
            $status = Command::FAILURE;
        } finally {
            $released = migration_advisory_lock_end();
        }

        if (!$released) {
            $io->error('数据库升级锁未能确认释放；当前进程退出后连接关闭会释放 MySQL advisory lock。');
            return Command::FAILURE;
        }
        if ($executionError !== '') {
            $io->error('升级失败: ' . $executionError);
            return Command::FAILURE;
        }
        if ($becameCurrent) {
            $io->success('数据库与配置已由其他协调进程更新，无需重复升级。');
            return Command::SUCCESS;
        }
        if (!$completed) return $status;

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

    private function reportErrors(SymfonyStyle $io, array $errors): int
    {
        $io->error('升级过程中出现以下错误:');
        $io->listing($errors);
        return Command::FAILURE;
    }

    private function checkUpgradeMetadata(SymfonyStyle $io, string $appPath): int
    {
        if (self::TARGET_VERSION === '' || !preg_match('/^\d+\.\d+\.\d+$/', self::TARGET_VERSION)) {
            $io->error('TARGET_VERSION 格式无效。');
            return Command::FAILURE;
        }

        if (empty(self::CONFIG_DEFAULTS)) {
            $io->error('CONFIG_DEFAULTS 为空，旧版配置补全将失效。');
            return Command::FAILURE;
        }

        $migrationsPath = $appPath . 'database/migrations';
        $files = is_dir($migrationsPath) ? glob($migrationsPath . '/*.php') : [];
        $files = $files ?: [];
        sort($files);

        foreach ($files as $file) {
            try {
                $migration = require $file;
                if (!$this->isValidMigration($migration)) {
                    $io->error(basename($file) . ' 必须返回带 up(string $tablepre): void 方法的对象。');
                    return Command::FAILURE;
                }
            } catch (\Throwable $e) {
                $io->error(basename($file) . ' 加载失败: ' . $e->getMessage());
                return Command::FAILURE;
            }
        }

        $io->success(sprintf(
            '升级工具检查通过：目标版本 %s，配置补全 %d 项，迁移文件 %d 个。',
            self::TARGET_VERSION,
            count(self::CONFIG_DEFAULTS),
            count($files)
        ));
        return Command::SUCCESS;
    }

    private function isValidMigration($migration): bool
    {
        if (!is_object($migration) || !method_exists($migration, 'up')) {
            return false;
        }

        $method = new \ReflectionMethod($migration, 'up');
        $params = $method->getParameters();
        $returnType = $method->getReturnType();
        return $method->isPublic()
            && count($params) === 1
            && $params[0]->hasType()
            && (string) $params[0]->getType() === 'string'
            && $returnType !== null
            && (string) $returnType === 'void';
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

        $needsVersionUpgrade = $version === 'unknown'
            || version_compare($version, self::TARGET_VERSION, '<');
        if ($version !== 'unknown' && version_compare($version, self::TARGET_VERSION, '>')) return [];

        // 1. 检查密码字段类型。Schema 状态未知时必须终止；继续写配置或版本会把
        // 部分升级伪装成完成。
        $passwordColumn = $this->readUserPasswordColumn();
        $type = strtolower((string) ($passwordColumn['Type'] ?? ''));
        if ($type !== 'varchar(255)') {
            $steps[] = "密码安全升级: password 字段从 $type 扩展为 varchar(255)，支持 bcrypt 哈希";
        }

        // 2. 检查缺失的配置项
        $configUpdates = $this->configUpdates($GLOBALS['conf'] ?? []);
        if (!empty($configUpdates)) {
            $steps[] = '配置升级: 修正 ' . count($configUpdates) . ' 个配置项 (' . implode(', ', array_keys($configUpdates)) . ')';
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

        // 5. 只有版本或其他输入真的漂移时才清缓存；完全对齐的目标版本必须保持 no-op。
        if ($needsVersionUpgrade || !empty($steps)) {
            $steps[] = '缓存清理: 清理编译缓存和插件 Hook 缓存';
        }

        return $steps;
    }

    private function configUpdates(array $conf): array
    {
        $updates = [];
        foreach (self::CONFIG_DEFAULTS as $key => $default) {
            if (!array_key_exists($key, $conf)) {
                $updates[$key] = $default;
            }
        }
        if (array_key_exists('static_version', $conf)
            && (string) $conf['static_version'] !== self::CONFIG_DEFAULTS['static_version']) {
            $updates['static_version'] = self::CONFIG_DEFAULTS['static_version'];
        }
        return $updates;
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
            if (!in_array($name, $executed, true)) {
                $pending[] = $file;
            }
        }
        return $pending;
    }

    private function stepConfigUpgrade(SymfonyStyle $io, array &$errors): void
    {
        $io->text('检查配置文件...');

        $confFile = APP_PATH . 'conf/conf.php';
        $additions = $this->configUpdates($GLOBALS['conf'] ?? []);

        if (!empty($additions)) {
            try {
                if (file_replace_var($confFile, $additions) === false) {
                    throw new \RuntimeException('conf.php config write failed');
                }
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
            $row = $this->readUserPasswordColumn();
            $type = strtolower((string) ($row['Type'] ?? ''));
            if ($type !== 'varchar(255)') {
                $io->text('  password 字段需要扩展 (当前: ' . $type . ')');
            }
        } catch (\Throwable $e) {
            $errors[] = '数据库结构检查失败: ' . $e->getMessage();
            return;
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
                if (!$this->isValidMigration($migration)) {
                    $errors[] = basename($file) . ' 必须返回带 up(string $tablepre): void 方法的对象。';
                    return;
                }
                $migration->up($tablepre);
                migration_record_append_locked($name, $executed);
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
		$io->text('清理可再生运行时缓存...');
		if (!function_exists('runtime_cache_clear_regenerable') || !runtime_cache_clear_regenerable()) {
			throw new \RuntimeException('运行时缓存正在被使用或无法安全清理，请在活动请求结束后重试。');
		}
		$io->text('  已清理可再生缓存；任务锁、恢复备份和安全模式标记保持不变');
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

        include_once XIUNOPHP_PATH . 'xiunophp.php';
        include_once APP_PATH . 'model/kv.func.php';
		include_once APP_PATH . 'model/migration.func.php';
		global $g_plugin_file_index_generation, $g_plugin_enabled_paths_generation, $g_plugin_enabled_paths;
		global $g_plugin_file_index_built_generation, $g_plugin_file_index, $g_plugin_include_reader_locks, $g_plugin_include_state_lock;
		include_once APP_PATH . 'model/plugin.func.php';
    }

    private function getTablepre(): string
    {
        return migration_table_prefix();
    }

    private function getExecutedMigrations(): array
    {
        return migration_record_read_primary();
    }

    private function readUserPasswordColumn(): array
    {
        $table = $this->getTablepre() . 'user';
        $row = db_sql_find_one_master("SHOW COLUMNS FROM `{$table}` LIKE 'password'");
        if ($row === false) {
            throw new \RuntimeException('Primary schema read failed for the user password column.');
        }
        if (!is_array($row) || empty($row)) {
            throw new \RuntimeException('The user password column is missing or unreadable.');
        }
        return $row;
    }
}
