<?php

$root = dirname(__DIR__);
$output = $root . '/docs/hooks.md';
$scanExtensions = array('php', 'htm');
$excludeTopDirs = array(
    '.git' => true,
    'cache' => true,
    'log' => true,
    'plugin' => true,
    'tmp' => true,
    'upload' => true,
    'vendor' => true,
    '开发手册' => true,
);

$hooks = array();
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file->isFile()) {
        continue;
    }

    $path = $file->getPathname();
    $relative = str_replace('\\', '/', substr($path, strlen($root) + 1));
    $top = strtok($relative, '/');
    if (isset($excludeTopDirs[$top])) {
        continue;
    }

    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (!in_array($extension, $scanExtensions, true)) {
        continue;
    }

    $lines = file($path);
    if ($lines === false) {
        continue;
    }

    foreach ($lines as $lineNumber => $line) {
        if (preg_match_all('/\/\/\s*hook\s+([A-Za-z0-9_.-]+(?:\.php)?)/', $line, $matches)) {
            foreach ($matches[1] as $hookName) {
                $hooks[] = array(
                    'hook' => $hookName,
                    'file' => $relative,
                    'line' => $lineNumber + 1,
                );
            }
        }
    }
}

usort($hooks, function ($a, $b) {
    $byHook = strcmp($a['hook'], $b['hook']);
    if ($byHook !== 0) {
        return $byHook;
    }

    $byFile = strcmp($a['file'], $b['file']);
    if ($byFile !== 0) {
        return $byFile;
    }

    return $a['line'] <=> $b['line'];
});

$uniqueFiles = array();
foreach ($hooks as $hook) {
    $uniqueFiles[$hook['file']] = true;
}

$markdown = array();
$markdown[] = '# Xiuno Next Hook 点索引';
$markdown[] = '';
$markdown[] = '> 此文件由 `php bin/generate_hook_docs.php` 生成。修改 Hook 注释后请重新运行脚本。';
$markdown[] = '';
$markdown[] = '- Hook 数量：' . count($hooks);
$markdown[] = '- 涉及文件：' . count($uniqueFiles);
$markdown[] = '';
$markdown[] = '| Hook | 文件 | 行号 |';
$markdown[] = '|---|---|---:|';

foreach ($hooks as $hook) {
    $markdown[] = sprintf(
        '| `%s` | `%s` | %d |',
        $hook['hook'],
        $hook['file'],
        $hook['line']
    );
}

if (!is_dir(dirname($output))) {
    mkdir(dirname($output), 0755, true);
}

file_put_contents($output, implode(PHP_EOL, $markdown) . PHP_EOL);
echo sprintf("Generated %s with %d hooks.\n", $output, count($hooks));
