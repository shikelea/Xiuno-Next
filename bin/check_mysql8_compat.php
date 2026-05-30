<?php

$root = dirname(__DIR__);
$files = [
    'install/install.sql',
    'install/alter.sql',
    'tool/alter.sql',
];

foreach (glob($root . '/database/migrations/*.php') ?: [] as $migration) {
    $files[] = str_replace('\\', '/', substr($migration, strlen($root) + 1));
}

$mysql8_sensitive_columns = [
    'rank' => true,
    'table' => true,
    'year' => true,
    'month' => true,
    'day' => true,
];

$errors = [];

foreach ($files as $relative) {
    $path = $root . '/' . $relative;
    $sql = file_get_contents($path);
    if ($sql === false) {
        $errors[] = "$relative: unable to read file";
        continue;
    }

    if (preg_match('/\bTYPE\s*=/i', $sql)) {
        $errors[] = "$relative: old TYPE= engine syntax is not allowed";
    }

    if (preg_match("/'0000-00-00(?: 00:00:00)?'/", $sql)) {
        $errors[] = "$relative: zero date defaults are not allowed";
    }

    foreach (find_create_table_blocks($sql) as $block) {
        foreach (preg_split('/\R/', $block['body']) as $line_number => $line) {
            $line = preg_replace('/#.*/', '', $line);
            if (!preg_match('/^\s*([A-Za-z_][A-Za-z0-9_]*)\s+/i', $line, $matches)) {
                continue;
            }

            $column = strtolower($matches[1]);
            if (isset($mysql8_sensitive_columns[$column])) {
                $line = $block['line'] + $line_number + 1;
                $errors[] = "$relative:$line: MySQL 8 sensitive column `$column` must be quoted";
            }
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "FAIL: MySQL 8 compatibility checks failed\n");
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}

echo "OK: MySQL 8 SQL compatibility checks passed\n";

function find_create_table_blocks(string $sql): array
{
    $blocks = [];
    $offset = 0;

    while (preg_match('/CREATE\s+TABLE\s+`?([A-Za-z0-9_]+)`?\s*\(/i', $sql, $matches, PREG_OFFSET_CAPTURE, $offset)) {
        $start = $matches[0][1] + strlen($matches[0][0]);
        $end = find_matching_parenthesis($sql, $start - 1);
        if ($end === null) {
            break;
        }

        $blocks[] = [
            'table' => $matches[1][0],
            'body' => substr($sql, $start, $end - $start),
            'line' => substr_count(substr($sql, 0, $start), "\n") + 1,
        ];
        $offset = $end + 1;
    }

    return $blocks;
}

function find_matching_parenthesis(string $source, int $open_position): ?int
{
    $length = strlen($source);
    $depth = 0;
    $quote = null;

    for ($i = $open_position; $i < $length; $i++) {
        $char = $source[$i];

        if ($quote !== null) {
            if ($char === '\\') {
                $i++;
                continue;
            }
            if ($char === $quote) {
                $quote = null;
            }
            continue;
        }

        if ($char === "'" || $char === '"') {
            $quote = $char;
            continue;
        }

        if ($char === '(') {
            $depth++;
            continue;
        }

        if ($char === ')') {
            $depth--;
            if ($depth === 0) {
                return $i;
            }
        }
    }

    return null;
}
