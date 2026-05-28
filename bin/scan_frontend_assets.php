<?php

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Unable to locate project root.\n");
    exit(1);
}

$root = str_replace('\\', '/', $root);
$trackedFiles = git_tracked_files($root);
$assetFiles = array_values(array_filter($trackedFiles, 'is_frontend_asset'));
$sourceFiles = array_values(array_filter($trackedFiles, 'is_scan_source'));

$assets = [];
foreach ($assetFiles as $asset) {
    $references = find_asset_references($asset, $sourceFiles, $root);
    $reasons = compatibility_reasons($asset);
    $status = $references ? 'referenced' : ($reasons ? 'compatibility_keep' : 'possibly_unused');

    $assets[] = [
        'path' => $asset,
        'size' => filesize($root . '/' . $asset),
        'status' => $status,
        'references' => $references,
        'keep_reasons' => $reasons,
    ];
}

usort($assets, fn($a, $b) => [$a['status'], $a['path']] <=> [$b['status'], $b['path']]);

$summary = [
    'generated_at' => date('c'),
    'asset_count' => count($assets),
    'referenced_count' => count(array_filter($assets, fn($a) => $a['status'] === 'referenced')),
    'compatibility_keep_count' => count(array_filter($assets, fn($a) => $a['status'] === 'compatibility_keep')),
    'possibly_unused_count' => count(array_filter($assets, fn($a) => $a['status'] === 'possibly_unused')),
];

$report = [
    'summary' => $summary,
    'assets' => $assets,
];

$out = $root . '/tmp/frontend_assets_scan.json';
if (!is_dir(dirname($out))) {
    mkdir(dirname($out), 0755, true);
}
file_put_contents($out, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);

echo sprintf(
    "Scanned %d frontend assets. Referenced: %d, compatibility keep: %d, possibly unused: %d.\nReport: %s\n",
    $summary['asset_count'],
    $summary['referenced_count'],
    $summary['compatibility_keep_count'],
    $summary['possibly_unused_count'],
    $out
);

foreach ($assets as $asset) {
    if ($asset['status'] !== 'possibly_unused') {
        continue;
    }
    echo "Possibly unused: {$asset['path']}\n";
}

function git_tracked_files(string $root): array
{
    $cwd = getcwd();
    chdir($root);
    exec('git ls-files', $output, $code);
    chdir($cwd);

    if ($code !== 0) {
        fwrite(STDERR, "git ls-files failed.\n");
        exit(1);
    }

    $files = array_map(
        fn($path) => str_replace('\\', '/', trim($path)),
        $output
    );

    return array_values(array_filter($files, fn($path) => is_file($root . '/' . $path)));
}

function is_frontend_asset(string $path): bool
{
    return preg_match('#^(?:view/(?:css|js|img|font)|admin/view/css)/#', $path) === 1;
}

function is_scan_source(string $path): bool
{
    if (preg_match('#^(?:plugin|vendor|tmp|log|upload|开发手册)/#u', $path)) {
        return false;
    }

    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return in_array($extension, [
        'php', 'inc', 'htm', 'html', 'css', 'js', 'md', 'json', 'yml', 'yaml', 'txt', 'sh', 'bat', 'ps1',
    ], true);
}

function find_asset_references(string $asset, array $sourceFiles, string $root): array
{
    $references = [];
    $assetDir = dirname($asset);
    $assetName = basename($asset);
    $publicPath = preg_replace('#^view/#', '', $asset);
    $adminPublicPath = preg_replace('#^admin/view/#', 'view/', $asset);
    $patterns = array_unique(array_filter([$asset, $publicPath, $adminPublicPath]));

    foreach ($sourceFiles as $source) {
        if ($source === $asset) {
            continue;
        }

        $fullPath = $root . '/' . $source;
        $content = file_get_contents($fullPath);
        if ($content === false || preg_match('/\x00/', $content)) {
            continue;
        }

        foreach ($patterns as $pattern) {
            if (strpos($content, $pattern) !== false) {
                $references[] = [
                    'file' => $source,
                    'type' => 'literal',
                    'match' => $pattern,
                ];
                continue 2;
            }
        }

        if (strtolower(pathinfo($source, PATHINFO_EXTENSION)) === 'css') {
            foreach (extract_css_urls($content) as $url) {
                if (str_starts_with($url, 'data:') || preg_match('#^[a-z]+://#i', $url)) {
                    continue;
                }

                $resolved = normalize_path(dirname($source) . '/' . $url);
                if ($resolved === $asset || basename($resolved) === $assetName && dirname($resolved) === $assetDir) {
                    $references[] = [
                        'file' => $source,
                        'type' => 'css_url',
                        'match' => $url,
                    ];
                    continue 2;
                }
            }
        }
    }

    return $references;
}

function extract_css_urls(string $content): array
{
    preg_match_all('/url\(\s*[\'"]?([^\'")?#]+)(?:#[^\'")]*)?[\'"]?\s*\)/i', $content, $matches);
    return $matches[1] ?? [];
}

function normalize_path(string $path): string
{
    $parts = [];
    foreach (explode('/', str_replace('\\', '/', $path)) as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }
        if ($part === '..') {
            array_pop($parts);
            continue;
        }
        $parts[] = $part;
    }
    return implode('/', $parts);
}

function compatibility_reasons(string $asset): array
{
    $reasons = [
        'view/js/jquery-3.1.0.js' => ['Core dependency and plugin compatibility base.'],
        'view/js/bootstrap-plugin.js' => ['Xiuno legacy UI helper API used by core and plugins.'],
        'view/js/bs4-compat.js' => ['BS4 plugin/theme compatibility shim.'],
        'view/css/bs4-compat.css' => ['BS4 plugin/theme compatibility shim.'],
        'view/js/popper.js' => ['Legacy Popper global kept for older plugins using tooltip/popover behavior.'],
        'view/js/popper-utils.js' => ['Legacy Popper utility module; remove only after ecosystem sample audit.'],
        'view/js/vue.js' => ['Legacy bundled framework; remove only after ecosystem sample audit confirms no local users.'],
        'view/js/upload.js' => ['Legacy upload helper may be loaded by attachment/avatar plugins.'],
        'view/img/filetype.png' => ['Legacy file-type sprite may be used by attachment plugins.'],
        'view/img/water-small-xiuno.png' => ['Bundled alternate watermark; keep until branding assets are normalized.'],
    ];

    return $reasons[$asset] ?? [];
}
