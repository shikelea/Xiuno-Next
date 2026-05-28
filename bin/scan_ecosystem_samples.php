<?php

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Unable to locate project root.\n");
    exit(1);
}

$sampleRoot = $argv[1] ?? ($root . '/plugin');
$sampleRoot = rtrim(str_replace('\\', '/', $sampleRoot), '/');
if (!is_dir($sampleRoot)) {
    fwrite(STDERR, "Sample directory not found: $sampleRoot\n");
    exit(1);
}

$patterns = [
    'php8' => [
        'each() removed' => '/\beach\s*\(/',
        'create_function() removed' => '/\bcreate_function\s*\(/',
        'get_magic_quotes_gpc() removed' => '/\bget_magic_quotes_gpc\s*\(/',
        'set_magic_quotes_runtime() removed' => '/\bset_magic_quotes_runtime\s*\(/',
        'curly offset syntax' => '/\$\w+\s*\{[^}]+\}/',
    ],
    'bs4' => [
        'data-toggle' => '/\bdata-toggle\s*=/',
        'data-target' => '/\bdata-target\s*=/',
        'data-dismiss' => '/\bdata-dismiss\s*=/',
        'custom-select' => '/\bcustom-select\b/',
        'custom-control' => '/\bcustom-control\b/',
        'custom-file' => '/\bcustom-file\b/',
        'form-group' => '/\bform-group\b/',
        'input-group-prepend/append' => '/\binput-group-(?:prepend|append)\b/',
        'btn-block' => '/\bbtn-block\b/',
        'card-deck/card-columns' => '/\bcard-(?:deck|columns)\b/',
        'legacy float/text utilities' => '/\b(?:float|text)-(?:left|right)\b/',
        'jQuery modal API' => '/\.\s*modal\s*\(/',
    ],
    'csrf' => [
        'POST form' => '/<form\b[^>]*method\s*=\s*["\']?post/i',
        'jQuery post' => '/\$\s*\.\s*post\s*\(/',
        'fetch post' => '/fetch\s*\([^)]*method\s*:\s*["\']POST/i',
    ],
    'theme' => [
        'header hook override' => '/hook\/header_[^\/]+\.htm$/',
        'footer hook override' => '/hook\/footer_[^\/]+\.htm$/',
        'theme registration candidate' => '/\btheme_register\s*\(/',
    ],
];

$coreHooks = collect_core_hooks($root);
$samples = [];
foreach (new DirectoryIterator($sampleRoot) as $entry) {
    if ($entry->isDot() || !$entry->isDir()) {
        continue;
    }

    $dir = $entry->getPathname();
    $name = $entry->getBasename();
    $samples[] = scan_sample($dir, $name, $patterns, $coreHooks);
}

usort($samples, function ($a, $b) {
    return [$b['risk_score'], $a['name']] <=> [$a['risk_score'], $b['name']];
});

$summary = [
    'generated_at' => date('c'),
    'sample_root' => $sampleRoot,
    'sample_count' => count($samples),
    'theme_like_count' => count(array_filter($samples, fn($s) => $s['theme_like'])),
    'php_lint_error_count' => array_sum(array_map(fn($s) => count($s['php_lint_errors']), $samples)),
    'risk_item_count' => array_sum(array_map(fn($s) => count($s['findings']), $samples)),
];

$report = [
    'summary' => $summary,
    'samples' => $samples,
];

$out = $root . '/tmp/ecosystem_scan.json';
if (!is_dir(dirname($out))) {
    mkdir(dirname($out), 0755, true);
}
$json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
if ($json === false) {
    fwrite(STDERR, "Unable to encode ecosystem scan JSON: " . json_last_error_msg() . "\n");
    exit(1);
}
file_put_contents($out, $json . PHP_EOL);

echo sprintf(
    "Scanned %d samples (%d theme-like). Findings: %d, PHP lint errors: %d.\nReport: %s\n",
    $summary['sample_count'],
    $summary['theme_like_count'],
    $summary['risk_item_count'],
    $summary['php_lint_error_count'],
    $out
);

function scan_sample(string $dir, string $name, array $patterns, array $coreHooks): array
{
    $files = [];
    $findings = [];
    $phpLintErrors = [];
    $conf = read_conf_json($dir . '/conf.json');
    $themeLike = stripos($name, 'theme') !== false || stripos((string)($conf['name'] ?? ''), 'theme') !== false || preg_match('/主题/u', (string)($conf['name'] ?? ''));
    if (!empty($conf['_invalid_conf_json'])) {
        $findings[] = [
            'group' => 'metadata',
            'label' => 'invalid conf.json',
            'file' => 'conf.json',
        ];
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }

        $path = str_replace('\\', '/', $file->getPathname());
        $relative = substr($path, strlen(str_replace('\\', '/', $dir)) + 1);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $files[] = $relative;

        $content = file_get_contents($path);
        if ($content === false) {
            continue;
        }

        foreach ($patterns as $group => $groupPatterns) {
            foreach ($groupPatterns as $label => $pattern) {
                $subject = $group === 'theme' && str_ends_with($label, 'override') ? $relative : $content;
                if (preg_match($pattern, $subject)) {
                    $findings[] = [
                        'group' => $group,
                        'label' => $label,
                        'file' => $relative,
                    ];
                    if ($group === 'theme') {
                        $themeLike = true;
                    }
                }
            }
        }

        if ($extension === 'php') {
            $lintError = lint_php($path);
            if ($lintError !== null) {
                $phpLintErrors[] = [
                    'file' => $relative,
                    'error' => $lintError,
                ];
            }
        }
    }

    foreach ($files as $relative) {
        $normalized = str_replace('\\', '/', $relative);
        if (!str_starts_with($normalized, 'hook/')) {
            continue;
        }
        $hookName = basename($normalized);
        if (!isset($coreHooks[$hookName])) {
            $findings[] = [
                'group' => 'hook',
                'label' => 'missing core hook',
                'file' => $relative,
            ];
        }
    }

    $counts = [
        'files' => count($files),
        'php' => count(array_filter($files, fn($f) => str_ends_with(strtolower($f), '.php'))),
        'htm' => count(array_filter($files, fn($f) => str_ends_with(strtolower($f), '.htm'))),
        'hooks' => count(array_filter($files, fn($f) => str_starts_with(str_replace('\\', '/', $f), 'hook/'))),
    ];

    $riskScore = count($findings) + count($phpLintErrors) * 5;

    return [
        'name' => $name,
        'display_name' => $conf['name'] ?? null,
        'version' => $conf['version'] ?? null,
        'bbs_version' => $conf['bbs_version'] ?? null,
        'theme_like' => (bool)$themeLike,
        'counts' => $counts,
        'risk_score' => $riskScore,
        'findings' => $findings,
        'php_lint_errors' => $phpLintErrors,
    ];
}

function collect_core_hooks(string $root): array
{
    $paths = [
        $root . '/model',
        $root . '/route',
        $root . '/view/htm',
        $root . '/admin/route',
        $root . '/admin/view/htm',
        $root . '/lang',
    ];
    $files = [
        $root . '/model.inc.php',
        $root . '/index.inc.php',
        $root . '/admin/index.inc.php',
    ];

    foreach ($paths as $path) {
        if (!is_dir($path)) {
            continue;
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }
    }

    $hooks = [];
    foreach ($files as $file) {
        $content = file_get_contents($file);
        if ($content === false) {
            continue;
        }
        if (preg_match_all('#//\s*hook\s+([A-Za-z0-9_.-]+)#', $content, $matches)) {
            foreach ($matches[1] as $hook) {
                $hooks[$hook] = true;
            }
        }
        if (preg_match_all('#<!--\{hook\s+([A-Za-z0-9_.-]+)\}-->#', $content, $matches)) {
            foreach ($matches[1] as $hook) {
                $hooks[$hook] = true;
            }
        }
    }

    return $hooks;
}

function read_conf_json(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $json = json_decode((string)file_get_contents($path), true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
    if (!is_array($json)) {
        return [
            '_invalid_conf_json' => true,
        ];
    }

    return $json;
}

function lint_php(string $path): ?string
{
    $cmd = 'php -l ' . escapeshellarg($path) . ' 2>&1';
    exec($cmd, $output, $code);
    if ($code === 0) {
        return null;
    }

    return trim(implode("\n", $output));
}
