<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude('plugin')      // 排除插件目录，避免破坏第三方代码
    ->exclude('tmp')         // 排除临时文件
    ->exclude('log')         // 排除日志
    ->exclude('upload')      // 排除上传文件
    ->exclude('view')        // 排除视图模板 (HTML混写)
    ->notPath('xiunophp/xiunophp.min.php') // 排除压缩文件
    ->name('*.php');

$config = new PhpCsFixer\Config();
return $config->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'], // 强制使用 [] 数组语法
        'no_unused_imports' => true,             // 移除未使用的 use
        'ordered_imports' => ['sort_algorithm' => 'alpha'], // use 排序
        'single_quote' => true,                  // 优先使用单引号
        'no_extra_blank_lines' => true,          // 移除多余空行
        'trim_array_spaces' => true,             // 数组去空格
        'binary_operator_spaces' => [
            'default' => 'align_single_space_minimal', // 运算符对齐
        ],
    ])
    ->setFinder($finder)
    ->setUsingCache(false);
