<?php

$root = dirname(__DIR__) . '/';
$fixture = [
    'summary' => [
        'generated_at' => '2026-05-28T00:00:00+00:00',
    ],
    'samples' => [
        [
            'name' => 'legacy_php_plugin',
            'display_name' => 'Legacy PHP Plugin',
            'version' => '1.0',
            'bbs_version' => '4.0.4',
            'theme_like' => false,
            'risk_score' => 6,
            'findings' => [
                ['group' => 'php8', 'label' => 'each() removed', 'file' => 'hook/post.htm'],
            ],
            'php_lint_errors' => [
                ['file' => 'install.php', 'error' => 'Parse error'],
            ],
        ],
        [
            'name' => 'bs4_theme',
            'display_name' => 'BS4 Theme',
            'version' => '1.0',
            'bbs_version' => '4.0.4',
            'theme_like' => true,
            'risk_score' => 3,
            'findings' => [
                ['group' => 'bs4', 'label' => 'data-toggle', 'file' => 'hook/header.htm'],
                ['group' => 'theme', 'label' => 'header hook override', 'file' => 'hook/header_start.htm'],
            ],
            'php_lint_errors' => [],
        ],
        [
            'name' => 'clean_plugin',
            'display_name' => 'Clean Plugin',
            'version' => '1.0',
            'bbs_version' => '4.0.4',
            'theme_like' => false,
            'risk_score' => 0,
            'findings' => [],
            'php_lint_errors' => [],
        ],
    ],
];

if (!is_dir($root . 'tmp')) {
    mkdir($root . 'tmp', 0755, true);
}
$input = $root . 'tmp/ecosystem_scan_fixture.json';
$output = $root . 'tmp/ecosystem_matrix_fixture.json';
$markdown = $root . 'tmp/ecosystem_matrix_fixture.md';
file_put_contents($input, json_encode($fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

$cmd = sprintf(
    'php %s --input=%s --output=%s --markdown=%s 2>&1',
    escapeshellarg($root . 'bin/build_ecosystem_matrix.php'),
    escapeshellarg($input),
    escapeshellarg($output),
    escapeshellarg($markdown)
);
exec($cmd, $log, $code);
if ($code !== 0) {
    fwrite(STDERR, implode(PHP_EOL, $log) . PHP_EOL);
    exit(1);
}

$matrix = json_decode((string)file_get_contents($output), true);
$errors = [];
if (($matrix['summary']['sample_count'] ?? 0) !== 3) {
    $errors[] = 'matrix sample count mismatch';
}
if (($matrix['summary']['status_counts']['blocked_by_php_lint'] ?? 0) !== 1) {
    $errors[] = 'blocked_by_php_lint status count mismatch';
}
if (($matrix['summary']['status_counts']['needs_theme_boundary_review'] ?? 0) !== 1) {
    $errors[] = 'needs_theme_boundary_review status count mismatch';
}
if (($matrix['summary']['status_counts']['likely_compatible'] ?? 0) !== 1) {
    $errors[] = 'likely_compatible status count mismatch';
}
if (!is_file($markdown)) {
    $errors[] = 'matrix markdown output missing';
}

@unlink($input);
@unlink($output);
@unlink($markdown);

if (!empty($errors)) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

echo "Ecosystem matrix smoke OK\n";
exit(0);
