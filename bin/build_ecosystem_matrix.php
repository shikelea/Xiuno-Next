<?php

$root = realpath(__DIR__ . '/..');
if ($root === false) {
    fwrite(STDERR, "Unable to locate project root.\n");
    exit(1);
}

$options = parse_args($argv, $root);
$scan = read_scan($options['input']);
$matrix = build_matrix($scan);

write_json($options['output'], $matrix);
if ($options['markdown'] !== '') {
    write_markdown($options['markdown'], $matrix);
}

echo sprintf(
    "Built ecosystem matrix for %d samples. Blocked: %d, metadata repair: %d, package patch: %d, hook review: %d, core/theme review: %d, likely compatible: %d.\nReport: %s\n",
    $matrix['summary']['sample_count'],
    $matrix['summary']['status_counts']['blocked_by_php_lint'] ?? 0,
    $matrix['summary']['status_counts']['needs_metadata_repair'] ?? 0,
    $matrix['summary']['status_counts']['needs_package_patch'] ?? 0,
    $matrix['summary']['status_counts']['needs_hook_boundary_review'] ?? 0,
    ($matrix['summary']['status_counts']['needs_core_compat_validation'] ?? 0) + ($matrix['summary']['status_counts']['needs_theme_boundary_review'] ?? 0),
    $matrix['summary']['status_counts']['likely_compatible'] ?? 0,
    $options['output']
);
if ($options['markdown'] !== '') {
    echo "Markdown: {$options['markdown']}\n";
}

function parse_args(array $argv, string $root): array
{
    $options = [
        'input' => $root . '/tmp/ecosystem_scan.json',
        'output' => $root . '/tmp/ecosystem_matrix.json',
        'markdown' => $root . '/tmp/ecosystem_matrix.md',
    ];

    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];
        foreach (['input', 'output', 'markdown'] as $key) {
            if ($arg === '--' . $key && isset($argv[$i + 1])) {
                $options[$key] = $argv[++$i];
                continue 2;
            }
            if (str_starts_with($arg, '--' . $key . '=')) {
                $options[$key] = substr($arg, strlen('--' . $key . '='));
                continue 2;
            }
        }
    }

    foreach (['input', 'output', 'markdown'] as $key) {
        if ($options[$key] === '') {
            continue;
        }
        $options[$key] = normalize_path($options[$key], $root);
    }

    return $options;
}

function normalize_path(string $path, string $root): string
{
    $path = str_replace('\\', '/', $path);
    if (preg_match('#^[A-Za-z]:/#', $path) || str_starts_with($path, '/')) {
        return $path;
    }
    return $root . '/' . $path;
}

function read_scan(string $input): array
{
    if (!is_file($input)) {
        fwrite(STDERR, "Ecosystem scan not found: $input\nRun: php bin/scan_ecosystem_samples.php\n");
        exit(1);
    }

    $json = json_decode((string)file_get_contents($input), true);
    if (!is_array($json)) {
        fwrite(STDERR, "Invalid ecosystem scan JSON: " . json_last_error_msg() . "\n");
        exit(1);
    }
    if (empty($json['samples']) || !is_array($json['samples'])) {
        fwrite(STDERR, "Ecosystem scan has no samples.\n");
        exit(1);
    }

    return $json;
}

function build_matrix(array $scan): array
{
    $rows = [];
    foreach ($scan['samples'] as $sample) {
        $rows[] = matrix_row($sample);
    }

    usort($rows, function ($a, $b) {
        return [status_rank($a['status']), -$a['risk_score'], $a['name']] <=> [status_rank($b['status']), -$b['risk_score'], $b['name']];
    });

    $statusCounts = [];
    $typeCounts = [];
    foreach ($rows as $row) {
        $statusCounts[$row['status']] = ($statusCounts[$row['status']] ?? 0) + 1;
        $typeCounts[$row['type']] = ($typeCounts[$row['type']] ?? 0) + 1;
    }

    return [
        'summary' => [
            'generated_at' => date('c'),
            'source_generated_at' => $scan['summary']['generated_at'] ?? null,
            'sample_count' => count($rows),
            'status_counts' => $statusCounts,
            'type_counts' => $typeCounts,
            'field_contract' => [
                'status',
                'issue_types',
                'minimum_xiuno_next',
                'workaround',
                'fix_owner',
            ],
        ],
        'matrix' => $rows,
    ];
}

function matrix_row(array $sample): array
{
    $groups = issue_groups($sample);
    $labels = issue_labels($sample);
    $lintCount = count($sample['php_lint_errors'] ?? []);
    $status = sample_status($sample, $groups, $lintCount);

    return [
        'name' => $sample['name'] ?? '',
        'display_name' => $sample['display_name'] ?? null,
        'type' => !empty($sample['theme_like']) ? 'theme_or_theme_like' : 'plugin',
        'source_version' => $sample['version'] ?? null,
        'source_bbs_version' => $sample['bbs_version'] ?? null,
        'status' => $status,
        'risk_score' => (int)($sample['risk_score'] ?? 0),
        'issue_types' => array_values($groups),
        'issue_labels' => array_values($labels),
        'php_lint_errors' => $lintCount,
        'finding_count' => count($sample['findings'] ?? []),
        'minimum_xiuno_next' => minimum_version($status, $groups),
        'workaround' => workarounds($groups, $lintCount),
        'fix_owner' => fix_owners($groups, $lintCount),
        'notes' => notes($status, $groups, $lintCount),
    ];
}

function issue_groups(array $sample): array
{
    $groups = [];
    foreach ($sample['findings'] ?? [] as $finding) {
        if (!empty($finding['group'])) {
            $groups[$finding['group']] = $finding['group'];
        }
    }
    return $groups;
}

function issue_labels(array $sample): array
{
    $labels = [];
    foreach ($sample['findings'] ?? [] as $finding) {
        if (!empty($finding['label'])) {
            $labels[$finding['label']] = $finding['label'];
        }
    }
    sort($labels);
    return $labels;
}

function sample_status(array $sample, array $groups, int $lintCount): string
{
    if ($lintCount > 0) {
        return 'blocked_by_php_lint';
    }
    if (isset($groups['metadata'])) {
        return 'needs_metadata_repair';
    }
    if (isset($groups['php8'])) {
        return 'needs_package_patch';
    }
    if (isset($groups['hook'])) {
        return 'needs_hook_boundary_review';
    }
    if (isset($groups['theme'])) {
        return 'needs_theme_boundary_review';
    }
    if (isset($groups['bs4']) || isset($groups['csrf'])) {
        return 'needs_core_compat_validation';
    }
    if ((int)($sample['risk_score'] ?? 0) === 0) {
        return 'likely_compatible';
    }
    return 'needs_review';
}

function status_rank(string $status): int
{
    $ranks = [
        'blocked_by_php_lint' => 0,
        'needs_metadata_repair' => 1,
        'needs_package_patch' => 2,
        'needs_hook_boundary_review' => 3,
        'needs_theme_boundary_review' => 4,
        'needs_core_compat_validation' => 5,
        'needs_review' => 6,
        'likely_compatible' => 7,
    ];
    return $ranks[$status] ?? 99;
}

function minimum_version(string $status, array $groups): string
{
    if ($status === 'blocked_by_php_lint' || $status === 'needs_package_patch' || $status === 'needs_metadata_repair') {
        return 'TBD after package patch';
    }
    if (isset($groups['hook'])) {
        return 'TBD after hook contract review';
    }
    if (isset($groups['bs4']) || isset($groups['csrf']) || isset($groups['theme'])) {
        return '4.4.5+';
    }
    return '4.4.5+';
}

function workarounds(array $groups, int $lintCount): array
{
    $items = [];
    if ($lintCount > 0 || isset($groups['php8'])) {
        $items[] = 'Patch removed PHP syntax/functions before enabling on PHP 8+.';
    }
    if (isset($groups['metadata'])) {
        $items[] = 'Repair conf.json metadata before install, enable, or runtime smoke.';
    }
    if (isset($groups['bs4'])) {
        $items[] = 'Verify bs4-compat.css/js is injected and prefer BS5 markup for new changes.';
    }
    if (isset($groups['csrf'])) {
        $items[] = 'Use injected CSRF meta/_token or X-CSRF-TOKEN for POST/AJAX flows.';
    }
    if (isset($groups['theme'])) {
        $items[] = 'Verify header/footer overrides preserve injected assets, CSRF, and theme API declarations.';
    }
    if (isset($groups['hook'])) {
        $items[] = 'Confirm whether missing legacy hook points should be restored in core or patched in the package.';
    }
    return $items ?: ['No workaround currently required by scanner output.'];
}

function fix_owners(array $groups, int $lintCount): array
{
    $owners = [];
    if ($lintCount > 0 || isset($groups['php8'])) {
        $owners['third_party_package'] = 'third_party_package';
    }
    if (isset($groups['metadata'])) {
        $owners['third_party_package_metadata'] = 'third_party_package_metadata';
    }
    if (isset($groups['bs4']) || isset($groups['csrf'])) {
        $owners['core_compat_layer'] = 'core_compat_layer';
    }
    if (isset($groups['theme'])) {
        $owners['theme_package_or_core_theme_api'] = 'theme_package_or_core_theme_api';
    }
    if (isset($groups['hook'])) {
        $owners['core_hook_contract_or_package'] = 'core_hook_contract_or_package';
    }
    return array_values($owners ?: ['none']);
}

function notes(string $status, array $groups, int $lintCount): string
{
    if ($status === 'likely_compatible') {
        return 'Scanner found no known compatibility signals; still needs runtime smoke before public listing.';
    }
    if ($lintCount > 0) {
        return 'PHP syntax errors block reliable runtime validation.';
    }
    if (isset($groups['metadata'])) {
        return 'Package metadata is not valid JSON, so install/enable flow cannot be trusted.';
    }
    if (isset($groups['php8'])) {
        return 'Static scan found PHP 8 removed or risky legacy constructs.';
    }
    if (isset($groups['hook'])) {
        return 'Package references hook points that are not present in the current core hook index.';
    }
    return 'Requires runtime smoke against Xiuno Next before public compatibility status.';
}

function write_json(string $path, array $matrix): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $json = json_encode($matrix, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        fwrite(STDERR, "Unable to encode ecosystem matrix JSON: " . json_last_error_msg() . "\n");
        exit(1);
    }

    file_put_contents($path, $json . PHP_EOL);
}

function write_markdown(string $path, array $matrix): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $lines = [
        '# Ecosystem Compatibility Matrix',
        '',
        'Generated: ' . $matrix['summary']['generated_at'],
        '',
        '| Sample | Type | Status | Risk | Issues | Fix owner |',
        '| --- | --- | --- | ---: | --- | --- |',
    ];

    foreach ($matrix['matrix'] as $row) {
        $lines[] = sprintf(
            '| `%s` | %s | `%s` | %d | %s | %s |',
            str_replace('|', '\\|', $row['name']),
            $row['type'],
            $row['status'],
            $row['risk_score'],
            implode(', ', $row['issue_types']),
            implode(', ', $row['fix_owner'])
        );
    }

    file_put_contents($path, implode(PHP_EOL, $lines) . PHP_EOL);
}
