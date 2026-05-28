<?php

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Unable to locate project root.\n");
    exit(1);
}

$root = str_replace('\\', '/', $root);
$trackedFiles = git_tracked_files($root);
$sampleRoots = sample_roots($argv, $root);
$assetFiles = array_values(array_filter($trackedFiles, 'is_frontend_asset'));
$sourceFiles = array_values(array_filter($trackedFiles, 'is_scan_source'));
$sampleSourceFiles = sample_source_files($root, $sampleRoots);
$sourceFiles = array_values(array_unique(array_merge($sourceFiles, $sampleSourceFiles)));

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
    'sample_roots' => $sampleRoots,
    'sample_source_count' => count($sampleSourceFiles),
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
    "Scanned %d frontend assets. Referenced: %d, compatibility keep: %d, possibly unused: %d.\nSample sources: %d.\nReport: %s\n",
    $summary['asset_count'],
    $summary['referenced_count'],
    $summary['compatibility_keep_count'],
    $summary['possibly_unused_count'],
    $summary['sample_source_count'],
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
    if ($path === 'bin/scan_frontend_assets.php' || $path === 'PLAN.md' || $path === 'README.md' || str_starts_with($path, 'docs/')) {
        return false;
    }

    if (preg_match('#^(?:plugin|vendor|tmp|log|upload|开发手册)/#u', $path)) {
        return false;
    }

    return is_source_extension($path);
}

function is_source_extension(string $path): bool
{
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return in_array($extension, [
        'php', 'inc', 'htm', 'html', 'css', 'js', 'md', 'json', 'yml', 'yaml', 'txt', 'sh', 'bat', 'ps1',
    ], true);
}

function sample_roots(array $argv, string $root): array
{
    $roots = [];
    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];
        if ($arg === '--samples' && isset($argv[$i + 1])) {
            $roots = array_merge($roots, explode(',', $argv[++$i]));
            continue;
        }
        if (str_starts_with($arg, '--samples=')) {
            $roots = array_merge($roots, explode(',', substr($arg, strlen('--samples='))));
        }
    }

    $normalized = [];
    foreach ($roots as $path) {
        $path = trim($path);
        if ($path === '') {
            continue;
        }

        $fullPath = realpath(preg_match('#^[A-Za-z]:/#', str_replace('\\', '/', $path)) ? $path : $root . '/' . $path);
        if ($fullPath === false || !is_dir($fullPath)) {
            fwrite(STDERR, "Sample directory not found, skipped: $path\n");
            continue;
        }

        $fullPath = str_replace('\\', '/', $fullPath);
        if (!str_starts_with($fullPath . '/', $root . '/')) {
            fwrite(STDERR, "Sample directory outside project root, skipped: $path\n");
            continue;
        }

        $normalized[] = substr($fullPath, strlen($root) + 1);
    }

    return array_values(array_unique($normalized));
}

function sample_source_files(string $root, array $sampleRoots): array
{
    $files = [];
    foreach ($sampleRoots as $sampleRoot) {
        $dir = $root . '/' . $sampleRoot;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());
            $relative = substr($path, strlen($root) + 1);
            if (is_source_extension($relative)) {
                $files[] = $relative;
            }
        }
    }

    return $files;
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
    ];

    return $reasons[$asset] ?? [];
}
