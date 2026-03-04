<?php

namespace Xiuno\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MakePluginCommand extends Command
{
    protected static $defaultName = 'make:plugin';
    protected static $defaultDescription = '创建一个新的插件结构';

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, '插件名称 (例如: my_plugin)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');
        
        // 验证插件名称
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            $io->error('插件名称只能包含字母、数字和下划线。');
            return Command::FAILURE;
        }

        // 确保 plugin 目录存在
        // 假设脚本在 bin/xiuno 运行，或者从根目录运行
        // 最好使用相对路径
        $pluginRoot = getcwd() . '/plugin';
        
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
        $this->createPluginConf($pluginPath, $name);
        $this->createHookExample($pluginPath);
        $this->createInstallFiles($pluginPath);
        
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
        file_put_contents($path . '/conf.json', $content);
    }
    
    private function createHookExample(string $path): void
    {
        $hookPath = $path . '/hook';
        if (!is_dir($hookPath)) {
            mkdir($hookPath, 0755);
        }
        $content = <<<PHP
<?php exit;
// 这是一个 Hook 示例
// This is a hook example
// 在这里写入 PHP 代码，它将被插入到 index_inc_start.php 的钩子位置
echo "Hello Plugin";
?>
PHP;
        file_put_contents($path . '/hook/index_inc_start.php', $content);
    }

    private function createInstallFiles(string $path): void
    {
        $content = <<<PHP
<?php

/*
    插件安装文件
*/
!defined('DEBUG') AND exit('Forbidden');

// 在这里编写安装逻辑，例如创建表
// \$sql = "CREATE TABLE ...";
// db_exec(\$sql);

?>
PHP;
        file_put_contents($path . '/install.php', $content);

        $content = <<<PHP
<?php

/*
    插件卸载文件
*/
!defined('DEBUG') AND exit('Forbidden');

// 在这里编写卸载逻辑，例如删除表
// \$sql = "DROP TABLE ...";
// db_exec(\$sql);

?>
PHP;
        file_put_contents($path . '/unstall.php', $content);
        
        $content = <<<PHP
<?php

/*
    插件升级文件
*/
!defined('DEBUG') AND exit('Forbidden');

// 在这里编写升级逻辑

?>
PHP;
        file_put_contents($path . '/upgrade.php', $content);
    }
}
