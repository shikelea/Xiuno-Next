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
foreach (glob($root . '/tool/*_to_xn*.php') ?: [] as $migration_tool) {
    $files[] = str_replace('\\', '/', substr($migration_tool, strlen($root) + 1));
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
mysql8_check_driver_exact_count($root, $errors);
mysql8_check_driver_sql_mode_config($root, $errors);
mysql8_check_strict_mode_smoke_config($root, $errors);
mysql8_check_plugin_sql_non_empty_fixture($root, $errors);

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
        mysql8_check_text_blob_default_definitions($relative, $block['body'], $errors, $block['line'] - 1);
        mysql8_check_unsigned_type_order_definitions($relative, $block['body'], $errors, $block['line']);
    }
    mysql8_check_alter_text_blob_defaults($relative, $sql, $errors);
    mysql8_check_alter_unsigned_type_order($relative, $sql, $errors);

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

function mysql8_check_text_blob_default_definitions(string $relative, string $body, array &$errors, int $base_line): void
{
    $definition = '';
    $definition_line = 0;
    $lines = preg_split('/\R/', $body);

    foreach ($lines as $line_number => $line) {
        $clean = mysql8_strip_sql_line_comment($line);
        if (trim($clean) === '') {
            continue;
        }

        if ($definition === '') {
            $definition_line = $line_number;
        }
        $definition .= ' ' . $clean;

        while (($comma = mysql8_find_unquoted_comma_position($definition)) !== null) {
            mysql8_check_text_blob_definition($relative, substr($definition, 0, $comma), $errors, $base_line + $definition_line);
            $definition = ltrim(substr($definition, $comma + 1));
            $definition_line = $line_number;
        }
    }

    if ($definition !== '') {
        mysql8_check_text_blob_definition($relative, $definition, $errors, $base_line + $definition_line);
    }
}

function mysql8_check_unsigned_type_order_definitions(string $relative, string $body, array &$errors, int $base_line): void
{
    $definition = '';
    $definition_line = 0;
    $lines = preg_split('/\R/', $body);

    foreach ($lines as $line_number => $line) {
        $clean = mysql8_strip_sql_line_comment($line);
        if (trim($clean) === '') {
            continue;
        }

        if ($definition === '') {
            $definition_line = $line_number;
        }
        $definition .= ' ' . $clean;

        while (($comma = mysql8_find_unquoted_comma_position($definition)) !== null) {
            mysql8_check_unsigned_type_order_definition($relative, substr($definition, 0, $comma), $errors, $base_line + $definition_line);
            $definition = ltrim(substr($definition, $comma + 1));
            $definition_line = $line_number;
        }
    }

    if ($definition !== '') {
        mysql8_check_unsigned_type_order_definition($relative, $definition, $errors, $base_line + $definition_line);
    }
}

function mysql8_check_alter_text_blob_defaults(string $relative, string $sql, array &$errors): void
{
    $statement = '';
    $statement_line = 0;

    foreach (preg_split('/\R/', $sql) as $line_number => $line) {
        $clean = mysql8_strip_sql_line_comment($line);
        if (trim($clean) === '') {
            continue;
        }
        if ($statement === '') {
            $statement_line = $line_number + 1;
        }
        $statement .= ' ' . $clean;

        if (!mysql8_has_unquoted_semicolon($clean)) {
            continue;
        }
        $alter = mysql8_extract_alter_table_fragment($statement);
        if ($alter !== null && mysql8_alter_statement_has_text_blob_default($alter)) {
            $errors[] = sprintf(
                '%s:%d: MySQL 8 text/blob columns must not use literal DEFAULT values',
                $relative,
                $statement_line
            );
        }
        $statement = '';
        $statement_line = 0;
    }

    $alter = mysql8_extract_alter_table_fragment($statement);
    if ($alter !== null && mysql8_alter_statement_has_text_blob_default($alter)) {
        $errors[] = sprintf(
            '%s:%d: MySQL 8 text/blob columns must not use literal DEFAULT values',
            $relative,
            $statement_line
        );
    }
}

function mysql8_check_alter_unsigned_type_order(string $relative, string $sql, array &$errors): void
{
    $statement = '';
    $statement_line = 0;

    foreach (preg_split('/\R/', $sql) as $line_number => $line) {
        $clean = mysql8_strip_sql_line_comment($line);
        if (trim($clean) === '') {
            continue;
        }
        if ($statement === '') {
            $statement_line = $line_number + 1;
        }
        $statement .= ' ' . $clean;

        if (!mysql8_has_unquoted_semicolon($clean)) {
            continue;
        }
        $alter = mysql8_extract_alter_table_fragment($statement);
        if ($alter !== null && mysql8_alter_statement_has_invalid_unsigned_type_order($alter)) {
            $errors[] = sprintf(
                '%s:%d: MySQL numeric columns must use type before UNSIGNED',
                $relative,
                $statement_line
            );
        }
        $statement = '';
        $statement_line = 0;
    }

    $alter = mysql8_extract_alter_table_fragment($statement);
    if ($alter !== null && mysql8_alter_statement_has_invalid_unsigned_type_order($alter)) {
        $errors[] = sprintf(
            '%s:%d: MySQL numeric columns must use type before UNSIGNED',
            $relative,
            $statement_line
        );
    }
}

function mysql8_extract_alter_table_fragment(string $statement): ?string
{
    $position = stripos($statement, 'ALTER TABLE');
    if ($position === false) {
        return null;
    }

    return substr($statement, $position);
}

function mysql8_check_text_blob_definition(string $relative, string $definition, array &$errors, int $line): void
{
    if (mysql8_text_blob_definition_has_literal_default($definition)) {
        $errors[] = sprintf(
            '%s:%d: MySQL 8 text/blob columns must not use literal DEFAULT values',
            $relative,
            $line
        );
    }
}

function mysql8_check_unsigned_type_order_definition(string $relative, string $definition, array &$errors, int $line): void
{
    if (mysql8_definition_has_invalid_unsigned_type_order($definition)) {
        $errors[] = sprintf(
            '%s:%d: MySQL numeric columns must use type before UNSIGNED',
            $relative,
            $line
        );
    }
}

function mysql8_alter_statement_has_text_blob_default(string $statement): bool
{
    return mysql8_alter_add_parenthesized_clause_has_text_blob_default($statement)
        || mysql8_direct_alter_clause_has_text_blob_default($statement);
}

function mysql8_alter_statement_has_invalid_unsigned_type_order(string $statement): bool
{
    return mysql8_alter_add_parenthesized_clause_has_invalid_unsigned_type_order($statement)
        || mysql8_direct_alter_clause_has_invalid_unsigned_type_order($statement);
}

function mysql8_identifier_pattern(): string
{
    return '(?:`(?:``|[^`])+`|[A-Za-z_][A-Za-z0-9_]*)';
}

function mysql8_unsigned_numeric_type_pattern(): string
{
    return '(?:tinyint|smallint|mediumint|int|integer|bigint|decimal|dec|numeric|fixed|float|double|real)';
}

function mysql8_text_blob_definition_has_literal_default(string $definition): bool
{
    $identifier = mysql8_identifier_pattern();
    return preg_match('/^\s*' . $identifier . '\s+(?:tinytext|mediumtext|longtext|text|tinyblob|mediumblob|longblob|blob)\b/i', $definition)
        && mysql8_definition_has_literal_default($definition);
}

function mysql8_definition_has_invalid_unsigned_type_order(string $definition): bool
{
    $identifier = mysql8_identifier_pattern();
    return preg_match('/^\s*' . $identifier . '\s+UNSIGNED\s+' . mysql8_unsigned_numeric_type_pattern() . '\b/i', $definition) === 1;
}

function mysql8_definition_has_literal_default(string $definition): bool
{
    $default = mysql8_find_unquoted_keyword($definition, 'DEFAULT');
    if ($default === null) {
        return false;
    }

    $value = ltrim(substr($definition, $default + strlen('DEFAULT')));
    if ($value === '') {
        return false;
    }
    return !preg_match('/^(NULL\b|\()/i', $value);
}

function mysql8_find_unquoted_keyword(string $source, string $keyword): ?int
{
    $quote = null;
    $length = strlen($source);
    $keyword_length = strlen($keyword);

    for ($i = 0; $i < $length; $i++) {
        $char = $source[$i];
        if ($quote !== null) {
            if ($quote === '`' && $char === '`' && ($source[$i + 1] ?? '') === '`') {
                $i++;
                continue;
            }
            if (($quote === "'" || $quote === '"') && $char === $quote && ($source[$i + 1] ?? '') === $quote) {
                $i++;
                continue;
            }
            if ($char === '\\') {
                $i++;
                continue;
            }
            if ($char === $quote) {
                $quote = null;
            }
            continue;
        }
        if ($char === "'" || $char === '"' || $char === '`') {
            $quote = $char;
            continue;
        }
        if (strncasecmp(substr($source, $i, $keyword_length), $keyword, $keyword_length) !== 0) {
            continue;
        }

        $before = $i === 0 ? '' : $source[$i - 1];
        $after = $source[$i + $keyword_length] ?? '';
        if (($before === '' || !preg_match('/[A-Za-z0-9_]/', $before))
            && ($after === '' || !preg_match('/[A-Za-z0-9_]/', $after))) {
            return $i;
        }
    }

    return null;
}

function mysql8_alter_add_parenthesized_clause_has_text_blob_default(string $statement): bool
{
    $offset = 0;
    while (preg_match('/\bADD\s+(?:COLUMN\s+)?\(/i', $statement, $match, PREG_OFFSET_CAPTURE, $offset)) {
        $start = $match[0][1];
        if (mysql8_sql_offset_is_quoted($statement, $start)) {
            $offset = $start + strlen($match[0][0]);
            continue;
        }
        $open = $start + strlen($match[0][0]) - 1;
        $close = find_matching_parenthesis($statement, $open);
        if ($close === null) {
            return false;
        }
        $body = substr($statement, $open + 1, $close - $open - 1);
        if (mysql8_text_blob_definition_body_has_literal_default($body)) {
            return true;
        }
        $offset = $close + 1;
    }
    return false;
}

function mysql8_direct_alter_clause_has_text_blob_default(string $statement): bool
{
    $identifier = mysql8_identifier_pattern();
    $pattern = '/\b(?:(?:ADD|MODIFY)\s+(?:COLUMN\s+)?' . $identifier . '|CHANGE\s+(?:COLUMN\s+)?' . $identifier . '\s+' . $identifier . ')\s+(?:tinytext|mediumtext|longtext|text|tinyblob|mediumblob|longblob|blob)\b/i';
    $offset = 0;

    while (preg_match($pattern, $statement, $match, PREG_OFFSET_CAPTURE, $offset)) {
        $start = $match[0][1];
        $clause = mysql8_sql_segment_until_clause_delimiter(substr($statement, $start));
        if (mysql8_definition_has_literal_default($clause)) {
            return true;
        }
        $offset = $start + strlen($match[0][0]);
    }

    return false;
}

function mysql8_alter_add_parenthesized_clause_has_invalid_unsigned_type_order(string $statement): bool
{
    $offset = 0;
    while (preg_match('/\bADD\s+(?:COLUMN\s+)?\(/i', $statement, $match, PREG_OFFSET_CAPTURE, $offset)) {
        $start = $match[0][1];
        if (mysql8_sql_offset_is_quoted($statement, $start)) {
            $offset = $start + strlen($match[0][0]);
            continue;
        }
        $open = $start + strlen($match[0][0]) - 1;
        $close = find_matching_parenthesis($statement, $open);
        if ($close === null) {
            return false;
        }
        $body = substr($statement, $open + 1, $close - $open - 1);
        if (mysql8_definition_body_has_invalid_unsigned_type_order($body)) {
            return true;
        }
        $offset = $close + 1;
    }
    return false;
}

function mysql8_direct_alter_clause_has_invalid_unsigned_type_order(string $statement): bool
{
    $identifier = mysql8_identifier_pattern();
    $pattern = '/\b(?:(?:ADD|MODIFY)\s+(?:COLUMN\s+)?' . $identifier . '|CHANGE\s+(?:COLUMN\s+)?' . $identifier . '\s+' . $identifier . ')\s+UNSIGNED\s+' . mysql8_unsigned_numeric_type_pattern() . '\b/i';
    $offset = 0;

    while (preg_match($pattern, $statement, $match, PREG_OFFSET_CAPTURE, $offset)) {
        $start = $match[0][1];
        if (!mysql8_sql_offset_is_quoted($statement, $start)) {
            return true;
        }
        $offset = $start + strlen($match[0][0]);
    }

    return false;
}

function mysql8_sql_segment_until_clause_delimiter(string $source): string
{
    $quote = null;
    $depth = 0;
    $length = strlen($source);

    for ($i = 0; $i < $length; $i++) {
        $char = $source[$i];
        if ($quote !== null) {
            if ($quote === '`' && $char === '`' && ($source[$i + 1] ?? '') === '`') {
                $i++;
                continue;
            }
            if (($quote === "'" || $quote === '"') && $char === $quote && ($source[$i + 1] ?? '') === $quote) {
                $i++;
                continue;
            }
            if ($char === '\\') {
                $i++;
                continue;
            }
            if ($char === $quote) {
                $quote = null;
            }
            continue;
        }
        if ($char === "'" || $char === '"' || $char === '`') {
            $quote = $char;
            continue;
        }
        if ($char === '(') {
            $depth++;
            continue;
        }
        if ($char === ')' && $depth > 0) {
            $depth--;
            continue;
        }
        if (($char === ',' || $char === ';') && $depth === 0) {
            return substr($source, 0, $i);
        }
    }

    return $source;
}

function mysql8_sql_offset_is_quoted(string $source, int $offset): bool
{
    $quote = null;
    $length = min(strlen($source), $offset);

    for ($i = 0; $i < $length; $i++) {
        $char = $source[$i];
        if ($quote !== null) {
            if ($quote === '`' && $char === '`' && ($source[$i + 1] ?? '') === '`') {
                $i++;
                continue;
            }
            if (($quote === "'" || $quote === '"') && $char === $quote && ($source[$i + 1] ?? '') === $quote) {
                $i++;
                continue;
            }
            if ($char === '\\') {
                $i++;
                continue;
            }
            if ($char === $quote) {
                $quote = null;
            }
            continue;
        }

        if ($char === "'" || $char === '"' || $char === '`') {
            $quote = $char;
        }
    }

    return $quote !== null;
}

function mysql8_text_blob_definition_body_has_literal_default(string $body): bool
{
    $definition = '';
    foreach (preg_split('/\R/', $body) as $line) {
        $clean = mysql8_strip_sql_line_comment($line);
        if (trim($clean) === '') {
            continue;
        }
        $definition .= ' ' . $clean;
        while (($comma = mysql8_find_unquoted_comma_position($definition)) !== null) {
            if (mysql8_text_blob_definition_has_literal_default(substr($definition, 0, $comma))) {
                return true;
            }
            $definition = ltrim(substr($definition, $comma + 1));
        }
    }

    return $definition !== '' && mysql8_text_blob_definition_has_literal_default($definition);
}

function mysql8_definition_body_has_invalid_unsigned_type_order(string $body): bool
{
    $definition = '';
    foreach (preg_split('/\R/', $body) as $line) {
        $clean = mysql8_strip_sql_line_comment($line);
        if (trim($clean) === '') {
            continue;
        }
        $definition .= ' ' . $clean;
        while (($comma = mysql8_find_unquoted_comma_position($definition)) !== null) {
            if (mysql8_definition_has_invalid_unsigned_type_order(substr($definition, 0, $comma))) {
                return true;
            }
            $definition = ltrim(substr($definition, $comma + 1));
        }
    }

    return $definition !== '' && mysql8_definition_has_invalid_unsigned_type_order($definition);
}

function mysql8_has_unquoted_comma(string $line): bool
{
    return mysql8_find_unquoted_comma_position($line) !== null;
}

function mysql8_find_unquoted_comma_position(string $line): ?int
{
    $quote = null;
    $depth = 0;
    $length = strlen($line);

    for ($i = 0; $i < $length; $i++) {
        $char = $line[$i];
        if ($quote !== null) {
            if ($quote === '`' && $char === '`' && ($line[$i + 1] ?? '') === '`') {
                $i++;
                continue;
            }
            if ($char === '\\') {
                $i++;
                continue;
            }
            if ($char === $quote) {
                $quote = null;
            }
            continue;
        }
        if ($char === "'" || $char === '"' || $char === '`') {
            $quote = $char;
            continue;
        }
        if ($char === '(') {
            $depth++;
            continue;
        }
        if ($char === ')' && $depth > 0) {
            $depth--;
            continue;
        }
        if ($char === ',' && $depth === 0) {
            return $i;
        }
    }

    return null;
}

function mysql8_has_unquoted_semicolon(string $line): bool
{
    $quote = null;
    $length = strlen($line);

    for ($i = 0; $i < $length; $i++) {
        $char = $line[$i];
        if ($quote !== null) {
            if ($quote === '`' && $char === '`' && ($line[$i + 1] ?? '') === '`') {
                $i++;
                continue;
            }
            if ($char === '\\') {
                $i++;
                continue;
            }
            if ($char === $quote) {
                $quote = null;
            }
            continue;
        }
        if ($char === "'" || $char === '"' || $char === '`') {
            $quote = $char;
            continue;
        }
        if ($char === ';') {
            return true;
        }
    }

    return false;
}

function mysql8_strip_sql_line_comment(string $line): string
{
    $quote = null;
    $length = strlen($line);

    for ($i = 0; $i < $length; $i++) {
        $char = $line[$i];

        if ($quote !== null) {
            if ($quote === '`' && $char === '`' && ($line[$i + 1] ?? '') === '`') {
                $i++;
                continue;
            }
            if ($char === '\\') {
                $i++;
                continue;
            }
            if ($char === $quote) {
                $quote = null;
            }
            continue;
        }

        if ($char === "'" || $char === '"' || $char === '`') {
            $quote = $char;
            continue;
        }

        if ($char === '#') {
            return substr($line, 0, $i);
        }
        if ($char === '-' && ($line[$i + 1] ?? '') === '-' && preg_match('/\s/', $line[$i + 2] ?? '')) {
            return substr($line, 0, $i);
        }
    }

    return $line;
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

function mysql8_check_driver_exact_count(string $root, array &$errors): void
{
    $relative = 'xiunophp/db_pdo_mysql.class.php';
    $source = file_get_contents($root . '/' . $relative);
    if ($source === false) {
        $errors[] = "$relative: unable to read file";
        return;
    }
    if (!preg_match('/public function count\(\$table, \$cond = array\(\)\) \{(.*?)\n\t\}/s', $source, $match)) {
        $errors[] = "$relative: unable to inspect count implementation";
        return;
    }
    if (strpos($match[1], 'SELECT COUNT(*) AS num') === false || stripos($match[1], 'information_schema') !== false) {
        $errors[] = "$relative: count must use exact COUNT(*) instead of InnoDB TABLE_ROWS estimates";
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
            "in_array(\$charset, array('utf8', 'utf8mb4'), TRUE)",
            "\$sql_mode = \$this->sql_mode_safe(\$sql_mode);",
            "sql_mode='\$sql_mode'",
            "private function sql_mode_safe(\$sql_mode)",
            "array_diff(explode(',', \$sql_mode), array('NO_BACKSLASH_ESCAPES'))",
        ] as $needle) {
            if (strpos($source, $needle) === false) {
                $errors[] = "$relative: missing sql_mode configuration guard: $needle";
            }
        }
        if (strpos($source, '$charset AND') !== false) {
            $errors[] = "$relative: connection setup must apply a safe sql_mode even when charset config is empty";
        }
    }
}

function mysql8_check_strict_mode_smoke_config(string $root, array &$errors): void
{
    foreach ([
        'bin/check_install_schema.php',
        'bin/check_legacy_upgrade_smoke.php',
        'bin/check_plugin_install_sql_smoke.php',
    ] as $relative) {
        $source = file_get_contents($root . '/' . $relative);
        if ($source === false) {
            $errors[] = "$relative: unable to read file";
            continue;
        }
        foreach ([
            "getenv('XIUNO_SQL_MODE') ?: ''",
            'function apply_sql_mode(PDO $pdo, string $sqlMode): void',
            "SET SESSION sql_mode = '\$sqlMode'",
        ] as $needle) {
            if (strpos($source, $needle) === false) {
                $errors[] = "$relative: missing strict sql_mode smoke support: $needle";
            }
        }
    }

    $workflow = file_get_contents($root . '/.github/workflows/ci.yml');
    if ($workflow === false) {
        $errors[] = '.github/workflows/ci.yml: unable to read file';
        return;
    }
    $strictMode = 'XIUNO_SQL_MODE: STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,ONLY_FULL_GROUP_BY,NO_ENGINE_SUBSTITUTION';
    if (strpos($workflow, $strictMode) === false
        || strpos($workflow, 'php bin/run_checks.php --profile=db --fail-on-skip') === false) {
        $errors[] = '.github/workflows/ci.yml: strict sql_mode must run the complete fail-closed database profile';
    }

    $manifest = require $root . '/bin/checks.manifest.php';
    $databaseChecks = array();
    foreach (($manifest['checks'] ?? array()) as $check) {
        if(($check['group'] ?? '') === 'database') $databaseChecks[] = $check['name'] ?? '';
    }
    foreach (array('check_install_schema.php', 'check_legacy_upgrade_smoke.php', 'check_plugin_install_sql_smoke.php') as $requiredCheck) {
        if(!in_array($requiredCheck, $databaseChecks, true)) {
            $errors[] = "bin/checks.manifest.php: strict database profile is missing $requiredCheck";
        }
    }
}

function mysql8_check_plugin_sql_non_empty_fixture(string $root, array &$errors): void
{
    $relative = 'bin/check_plugin_install_sql_smoke.php';
    $source = file_get_contents($root . '/' . $relative);
    if ($source === false) {
        $errors[] = "$relative: unable to read file";
        return;
    }
    foreach ([
        'seed_post_table($pdo);',
        'function seed_post_table(PDO $pdo): void',
        'INSERT INTO bbs_post SET',
        'assert_seed_post_alter_defaults($pdo);',
        'function assert_seed_post_alter_defaults(PDO $pdo): void',
    ] as $needle) {
        if (strpos($source, $needle) === false) {
            $errors[] = "$relative: plugin SQL smoke must cover non-empty post table ALTER: $needle";
        }
    }
}

function find_create_table_blocks(string $sql): array
{
    $blocks = [];
    $offset = 0;

    while (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?(?:`?\{\$tablepre\}|`?)([A-Za-z0-9_]+)`?\s*\(/i', $sql, $matches, PREG_OFFSET_CAPTURE, $offset)) {
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
            if ($quote === '`' && $char === '`' && ($source[$i + 1] ?? '') === '`') {
                $i++;
                continue;
            }
            if (($quote === "'" || $quote === '"') && $char === $quote && ($source[$i + 1] ?? '') === $quote) {
                $i++;
                continue;
            }
            if ($char === '\\') {
                $i++;
                continue;
            }
            if ($char === $quote) {
                $quote = null;
            }
            continue;
        }

        if ($char === "'" || $char === '"' || $char === '`') {
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
