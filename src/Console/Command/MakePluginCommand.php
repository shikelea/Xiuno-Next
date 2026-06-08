<?php

namespace Xiuno\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'make:plugin', description: '创建一个新的插件结构')]
class MakePluginCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, '插件名称 (例如: my_plugin)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = (string) $input->getArgument('name');
        
        // 验证插件名称
        if (!preg_match('/^\w{1,32}$/', $name)) {
            $io->error('插件名称只能包含字母、数字和下划线。');
            return Command::FAILURE;
        }

        // 确保 plugin 目录存在
        $projectRoot = realpath(dirname(__DIR__, 3));
        if ($projectRoot === false) {
            $io->error('Unable to locate project root.');
            return Command::FAILURE;
        }
        $pluginRoot = $projectRoot . '/plugin';

        if (!is_dir($pluginRoot)) {
            if (!mkdir($pluginRoot, 0755, true)) {
                 $io->error('无法创建 plugin 目录: ' . $pluginRoot);
                 return Command::FAILURE;
            }
        }

        $pluginPath = $pluginRoot . '/' . $name;

        if (is_dir($pluginPath)) {
            $io->error(sprintf('插件目录 "%s" 已存在。', $pluginPath));
            return Command::FAILURE;
        }

        // 创建目录结构
        if (!mkdir($pluginPath, 0755, true)) {
             $io->error('无法创建插件目录。');
             return Command::FAILURE;
        }
        
        // 创建基础文件
        try {
            $this->createPluginConf($pluginPath, $name);
            $this->createHookExample($pluginPath);
            $this->createInstallFiles($pluginPath);
        } catch (\RuntimeException $e) {
            $this->removeDirectory($pluginPath);
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
        
        $io->success(sprintf('插件 "%s" 创建成功！路径: %s', $name, $pluginPath));

        return Command::SUCCESS;
    }

    private function createPluginConf(string $path, string $name): void
    {
        $content = <<<JSON
{
    "name": "{$name}",
    "brief": "插件简介",
    "version": "1.0.0",
    "bbs_version": "4.0",
    "installed": 0,
    "enable": 0,
    "hooks_rank": {},
    "dependencies": {}
}
JSON;
        $this->writeFile($path . '/conf.json', $content);
    }
    
    private function createHookExample(string $path): void
    {
        $hookPath = $path . '/hook';
        if (!is_dir($hookPath)) {
            if (!mkdir($hookPath, 0755)) {
                throw new \RuntimeException('Unable to create hook directory.');
            }
        }
        $content = <<<'PHP'
<?php
// 这是一个 Hook 示例 / This is a hook example
// 在这里写入 PHP 代码，它将被插入到 index_inc_start.php 的钩子位置
// Write your PHP code here; it will be injected at the index_inc_start.php hook point.

?>
PHP;
        $this->writeFile($path . '/hook/index_inc_start.php', $content);
    }

    private function createInstallFiles(string $path): void
    {
        $content = <<<PHP
<?php

/*
    插件安装文件
*/
!defined('DEBUG') AND exit('Forbidden');

// 在这里编写安装逻辑，例如创建表。建议使用 plugin_db_exec_or_throw()，
// 这样安装失败时后台能显示明确的数据库错误。
// \$sql = "CREATE TABLE ...";
// plugin_db_exec_or_throw(\$sql);

?>
PHP;
        $this->writeFile($path . '/install.php', $content);

        $content = <<<PHP
<?php

/*
    插件卸载文件
*/
!defined('DEBUG') AND exit('Forbidden');

// 在这里编写卸载逻辑，例如删除表。DROP TABLE 建议加 IF EXISTS。
// \$sql = "DROP TABLE ...";
// plugin_db_exec_or_throw(\$sql);

?>
PHP;
        $this->writeFile($path . '/unstall.php', $content);
        
        $content = <<<PHP
<?php

/*
    插件升级文件
*/
!defined('DEBUG') AND exit('Forbidden');

// 在这里编写升级逻辑

?>
PHP;
        $this->writeFile($path . '/upgrade.php', $content);
    }

    private function writeFile(string $path, string $content): void
    {
        $written = file_put_contents($path, $content);
        if ($written === false || $written !== strlen($content)) {
            throw new \RuntimeException('Unable to write plugin scaffold file: ' . $path);
        }
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $child = $path . '/' . $item;
            if (is_dir($child)) {
                $this->removeDirectory($child);
            } else {
                @unlink($child);
            }
        }

        @rmdir($path);
    }
}
