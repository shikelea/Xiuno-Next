<?php

namespace Xiuno\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MigrateCommand extends Command
{
    protected static $defaultName = 'migrate';
    protected static $defaultDescription = '执行数据库迁移';

    protected function configure(): void
    {
        $this->addOption('check', null, InputOption::VALUE_NONE, '只检查迁移文件是否可加载，不连接数据库或写入状态');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $appPath = realpath(__DIR__ . '/../../../') . '/';

        if ($input->getOption('check')) {
            return $this->checkMigrations($io, $appPath);
        }

        $confFile = $appPath . 'conf/conf.php';
        if (!is_file($confFile)) {
            $io->error('未找到配置文件 conf/conf.php，请先完成安装。');
            return Command::FAILURE;
        }

        $this->bootstrap($appPath);

        $migrationsPath = $appPath . 'database/migrations';
        if (!is_dir($migrationsPath)) {
            $io->warning('迁移目录 database/migrations/ 不存在，无迁移可执行。');
            return Command::SUCCESS;
        }

        $files = glob($migrationsPath . '/*.php');
        if (empty($files)) {
            $io->info('没有迁移文件。');
            return Command::SUCCESS;
        }
        sort($files);

        $executed = $this->getExecutedMigrations();
        $pending = [];
        foreach ($files as $file) {
            $name = basename($file, '.php');
            if (!in_array($name, $executed)) {
                $pending[$name] = $file;
            }
        }

        if (empty($pending)) {
            $io->success('数据库已是最新，无需迁移。');
            return Command::SUCCESS;
        }

        $io->info(sprintf('发现 %d 个待执行迁移。', count($pending)));

        foreach ($pending as $name => $file) {
            $io->text("执行: $name ...");
            try {
                $migration = require $file;
                $tablepre = $_SERVER['db']->tablepre ?? 'bbs_';
                $migration->up($tablepre);
                $this->recordMigration($name);
                $io->text("  完成: $name");
            } catch (\Throwable $e) {
                $io->error("迁移 $name 失败: " . $e->getMessage());
                return Command::FAILURE;
            }
        }

        $io->success(sprintf('成功执行 %d 个迁移。', count($pending)));
        return Command::SUCCESS;
    }

    private function checkMigrations(SymfonyStyle $io, string $appPath): int
    {
        $migrationsPath = $appPath . 'database/migrations';
        if (!is_dir($migrationsPath)) {
            $io->warning('迁移目录 database/migrations/ 不存在，无迁移可检查。');
            return Command::SUCCESS;
        }

        $files = glob($migrationsPath . '/*.php');
        if (empty($files)) {
            $io->info('没有迁移文件。');
            return Command::SUCCESS;
        }
        sort($files);

        foreach ($files as $file) {
            try {
                $migration = require $file;
                if (!is_object($migration) || !method_exists($migration, 'up')) {
                    $io->error(basename($file) . ' 必须返回带 up(string $tablepre): void 方法的对象。');
                    return Command::FAILURE;
                }
            } catch (\Throwable $e) {
                $io->error(basename($file) . ' 加载失败: ' . $e->getMessage());
                return Command::FAILURE;
            }
        }

        $io->success(sprintf('迁移文件检查通过：%d 个文件。', count($files)));
        return Command::SUCCESS;
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

    private function getExecutedMigrations(): array
    {
        $val = kv_get('xn_migrations');
        return is_array($val) ? $val : [];
    }

    private function recordMigration(string $name): void
    {
        $executed = $this->getExecutedMigrations();
        $executed[] = $name;
        kv_set('xn_migrations', $executed);
    }
}
