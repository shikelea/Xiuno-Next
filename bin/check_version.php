<?php

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Unable to locate project root.\n");
    exit(1);
}

$checks = [
    'index.php' => '/\$conf\[\'version\'\]\s*=\s*\'([^\']+)\'/',
    'conf/conf.default.php' => '/\'version\'\s*=>\s*\'([^\']+)\'/',
    'src/Console/Command/UpgradeCommand.php' => '/TARGET_VERSION\s*=\s*\'([^\']+)\'/',
    'bin/xiuno' => '/XIUNO_CLI_VERSION\s*=\s*\'([^\']+)\'/',
    'conf/conf.default.php static_version' => '/\'static_version\'\s*=>\s*\'\?v=([^\']+)\'/',
    'src/Console/Command/UpgradeCommand.php static_version' => '/\'static_version\'\s*=>\s*\'\?v=([^\']+)\'/',
];

$versions = [];
foreach ($checks as $file => $pattern) {
    $realFile = explode(' ', $file, 2)[0];
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $realFile);
    $content = is_file($path) ? file_get_contents($path) : false;
    if ($content === false || !preg_match($pattern, $content, $match)) {
        $versions[$file] = null;
        continue;
    }
    $versions[$file] = $match[1];
}

$target = $versions['index.php'] ?? null;
$errors = [];
if (!$target) {
    $errors[] = 'index.php does not expose a release version.';
}

foreach ($versions as $file => $version) {
    if ($version === null) {
        $errors[] = "$file: version not found";
    } elseif ($target !== null && $version !== $target) {
        $errors[] = "$file: $version != $target";
    }
}

$docChecks = [
    'README.md' => "v$target",
];
foreach ($docChecks as $file => $needle) {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
    $content = is_file($path) ? file_get_contents($path) : false;
    if ($content === false || strpos($content, $needle) === false) {
        $errors[] = "$file: missing $needle";
    }
}

if ($target !== null && preg_match('/^\d+\.\d+\.\d+$/', $target)) {
    $tagRef = 'refs/tags/v' . $target . '^{commit}';
    $tagOutput = [];
    $tagStatus = 0;
    exec('git -C ' . escapeshellarg($root) . ' rev-parse --verify ' . escapeshellarg($tagRef) . ' 2>&1', $tagOutput, $tagStatus);
    if ($tagStatus === 0 && !empty($tagOutput[0])) {
        $headOutput = [];
        $headStatus = 0;
        exec('git -C ' . escapeshellarg($root) . ' rev-parse HEAD 2>&1', $headOutput, $headStatus);
        if ($headStatus !== 0 || empty($headOutput[0]) || trim($headOutput[0]) !== trim($tagOutput[0])) {
            $errors[] = "v$target already points to a different commit; bump the release version.";
        }
    }
}

if ($errors) {
    fwrite(STDERR, "Version consistency check failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, " - $error\n");
    }
    exit(1);
}

echo "Version consistency OK: $target\n";
