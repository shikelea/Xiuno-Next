<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src') // PSR-12 仅适用于 CLI 类；过程式核心和 Hook 保留各文件原风格。
    ->name('*.php');

$config = new PhpCsFixer\Config();
return $config->setRules([
        '@PSR12' => true,
    ])
    ->setFinder($finder)
    ->setUsingCache(false);
