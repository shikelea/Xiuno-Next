<?php

namespace Xiuno\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'mail:work', description: '投递已持久化的找回密码邮件任务')]
class MailWorkCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, '本次最多处理的任务数（1-100）', '10');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limitRaw = (string) $input->getOption('limit');
        if (!preg_match('/^\d+$/D', $limitRaw) || (int) $limitRaw < 1 || (int) $limitRaw > 100) {
            $io->error('--limit 必须是 1 至 100 的整数。');
            return Command::FAILURE;
        }

        $appPath = realpath(__DIR__ . '/../../../') . '/';
        if (!is_file($appPath . 'conf/conf.php')) {
            $io->error('未找到配置文件 conf/conf.php，请先完成安装。');
            return Command::FAILURE;
        }
        $this->bootstrap($appPath);

        $workerLock = mail_outbox_worker_lock_acquire();
        if ($workerLock === null) {
            $io->error('同一数据库已有 mail:work 正在运行。');
            return Command::FAILURE;
        }
        if ($workerLock === false) {
            $io->error('无法取得 mail:work 数据库锁。');
            return Command::FAILURE;
        }

        $sent = 0;
        $retried = 0;
        $discarded = 0;
        $workerFailed = false;
        try {
            for ($i = 0; $i < (int) $limitRaw; $i++) {
                $result = mail_outbox_work_one();
                $status = isset($result['status']) ? (string) $result['status'] : 'error';
                $message = isset($result['message']) ? (string) $result['message'] : '';
                if ($status === 'empty') break;
                if ($status === 'sent') {
                    $sent++;
                    continue;
                }
                if ($status === 'retry') {
                    $retried++;
                    $io->warning($message);
                    continue;
                }
                if ($status === 'discarded') {
                    $discarded++;
                    $io->warning($message);
                    continue;
                }
                $io->error($message !== '' ? $message : '邮件任务处理失败。');
                $workerFailed = true;
                break;
            }
        } finally {
            if (!mail_outbox_worker_lock_release($workerLock)) {
                $io->error('无法释放 mail:work 数据库锁。');
                $workerFailed = true;
            }
        }

        if ($workerFailed) return Command::FAILURE;
        $io->text(sprintf('邮件任务：成功 %d，等待重试 %d，丢弃 %d。', $sent, $retried, $discarded));
        return ($retried === 0 && $discarded === 0) ? Command::SUCCESS : Command::FAILURE;
    }

    private function bootstrap(string $appPath): void
    {
        if (!defined('DEBUG')) define('DEBUG', 0);
        if (!defined('APP_PATH')) define('APP_PATH', $appPath);
        if (!defined('XIUNOPHP_PATH')) define('XIUNOPHP_PATH', $appPath . 'xiunophp/');

        $conf = include $appPath . 'conf/conf.php';
        substr($conf['tmp_path'], 0, 2) == './' and $conf['tmp_path'] = APP_PATH . $conf['tmp_path'];
        substr($conf['log_path'], 0, 2) == './' and $conf['log_path'] = APP_PATH . $conf['log_path'];
        substr($conf['upload_path'], 0, 2) == './' and $conf['upload_path'] = APP_PATH . $conf['upload_path'];
        $GLOBALS['conf'] = $conf;
        $_SERVER['conf'] = $conf;

        include_once XIUNOPHP_PATH . 'xiunophp.php';
        include_once XIUNOPHP_PATH . 'xn_send_mail.func.php';
        include_once APP_PATH . 'model/mail_outbox.func.php';
    }
}
