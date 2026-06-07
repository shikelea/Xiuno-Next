<?php

$root = dirname(__DIR__);
$files = [
    'install/install.sql',
    'install/alter.sql',
    'install/upgrade.sql',
    'tool/alter.sql',
];
$sample_root = cli_option_value($argv, '--samples');

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
$sample_files = [];
$sample_notes = [];

foreach ($files as $relative) {
    $path = $root . '/' . $relative;
    $sql = file_get_contents($path);
    if ($sql === false) {
        $errors[] = "$relative: unable to read file";
        continue;
    }
    mysql8_check_sql_text($relative, $sql, $mysql8_sensitive_columns, $errors);
}

mysql8_check_driver_engine_rewrite($root, $errors);
mysql8_check_driver_sql_mode_config($root, $errors);

if($sample_root !== '') {
    $sample_path = normalize_sample_path($root, $sample_root);
    if($sample_path === '' || !is_dir($sample_path)) {
        $errors[] = "samples: directory not found: $sample_root";
    } else {
        $sample_files = mysql8_sample_sql_files($sample_path);
        foreach($sample_files as $file) {
            $relative = str_replace('\\', '/', substr($file, strlen($root) + 1));
            $sql = file_get_contents($file);
            if($sql === false) {
                $errors[] = "$relative: unable to read file";
                continue;
            }
            mysql8_check_sql_text($relative, $sql, $mysql8_sensitive_columns, $errors);
            mysql8_collect_sample_notes($relative, $sql, $sample_notes);
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, "FAIL: MySQL 8 compatibility checks failed\n");
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}

echo "OK: MySQL 8 SQL compatibility checks passed\n";
if($sample_root !== '') {
    echo "Sample SQL files scanned: ".count($sample_files)."\n";
    if($sample_notes !== []) {
        echo "Sample compatibility notes: ".count($sample_notes)."\n";
        $counts = array_count_values(array_column($sample_notes, 'type'));
        foreach($counts as $type=>$count) {
            echo " - $type: $count\n";
        }
    }
}

function cli_option_value(array $argv, string $name): string
{
    foreach($argv as $i=>$arg) {
        if($arg === $name) return isset($argv[$i + 1]) ? (string)$argv[$i + 1] : '';
        if(strpos($arg, $name.'=') === 0) return substr($arg, strlen($name) + 1);
    }
    return '';
}

function normalize_sample_path(string $root, string $sample_root): string
{
    $sample_root = trim(str_replace('\\', '/', $sample_root));
    if($sample_root === '') return '';
    if(preg_match('/^[A-Za-z]:\//', $sample_root) || strpos($sample_root, '/') === 0) return rtrim($sample_root, '/');
    return $root.'/'.trim($sample_root, '/');
}

function mysql8_sample_sql_files(string $sample_path): array
{
    $files = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sample_path, FilesystemIterator::SKIP_DOTS)
    );
    foreach($it as $file) {
        if(!$file->isFile()) continue;
        $name = strtolower($file->getFilename());
        if($name === 'install.php' || $name === 'upgrade.php' || substr($name, -4) === '.sql') {
            $files[] = $file->getPathname();
        }
    }
    sort($files);
    return $files;
}

function mysql8_check_sql_text(string $relative, string $sql, array $mysql8_sensitive_columns, array &$errors): void
{
    if (preg_match('/\)\s*TYPE\s*=\s*(MyISAM|InnoDB|HEAP|MEMORY|ISAM|BDB|MERGE|MRG_MYISAM|CSV|ARCHIVE|BLACKHOLE|FEDERATED)\b/i', $sql)) {
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

function mysql8_collect_sample_notes(string $relative, string $sql, array &$notes): void
{
    if(preg_match('/ENGINE\s*=\s*MyISAM/i', $sql)) {
        $notes[] = ['file'=>$relative, 'type'=>'legacy_myisam_engine'];
    }
    if(preg_match('/DEFAULT\s+CHARSET\s*=\s*utf8\b/i', $sql)) {
        $notes[] = ['file'=>$relative, 'type'=>'legacy_utf8_charset'];
    }
}

function mysql8_check_driver_engine_rewrite(string $root, array &$errors): void
{
    foreach (['xiunophp/db_mysql.class.php', 'xiunophp/db_pdo_mysql.class.php'] as $relative) {
        $source = file_get_contents($root . '/' . $relative);
        if ($source === false) {
            $errors[] = "$relative: unable to read file";
            continue;
        }
        if (strpos($source, "strtoupper(substr(\$sql, 0, 12)) == 'CREATE TABLE'") === false) {
            $errors[] = "$relative: CREATE TABLE engine rewrite guard must inspect the SQL prefix";
        }
    }
}

function mysql8_check_driver_sql_mode_config(string $root, array &$errors): void
{
    $conf = file_get_contents($root . '/conf/conf.default.php');
    if ($conf === false) {
        $errors[] = 'conf/conf.default.php: unable to read file';
    } elseif (substr_count($conf, "'sql_mode' => ''") < 2) {
        $errors[] = 'conf/conf.default.php: mysql and pdo_mysql masters must expose sql_mode defaults';
    }

    foreach (['xiunophp/db_mysql.class.php', 'xiunophp/db_pdo_mysql.class.php'] as $relative) {
        $source = file_get_contents($root . '/' . $relative);
        if ($source === false) {
            $errors[] = "$relative: unable to read file";
            continue;
        }
        foreach ([
            "isset(\$conf['sql_mode']) ? \$conf['sql_mode'] : ''",
            "\$sql_mode = \$this->sql_mode_safe(\$sql_mode);",
            "sql_mode='\$sql_mode'",
            "private function sql_mode_safe(\$sql_mode)",
        ] as $needle) {
            if (strpos($source, $needle) === false) {
                $errors[] = "$relative: missing sql_mode configuration guard: $needle";
            }
        }
    }
}

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
