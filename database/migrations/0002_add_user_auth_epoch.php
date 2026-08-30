<?php

return new class {
    public function up(string $tablepre): void
    {
        if (!preg_match('/^[A-Za-z0-9_]{0,32}$/D', $tablepre)) {
            throw new RuntimeException('Invalid table prefix for auth epoch migration.');
        }

        $table = $tablepre . 'user';
        $row = db_sql_find_one_master("SHOW COLUMNS FROM `{$table}` LIKE 'auth_epoch'");
        if ($row === false) {
            throw new RuntimeException('Failed to inspect the user auth_epoch field.');
        }
        if (!empty($row)) {
            if (!$this->satisfiesTarget($row)) {
                throw new RuntimeException('The existing user auth_epoch field does not satisfy the required schema.');
            }
            return;
        }

        $ok = db_exec(
            "ALTER TABLE `{$table}` ADD `auth_epoch` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '凭证撤销代际' AFTER `password`"
        );
        if ($ok === false) {
            throw new RuntimeException('Failed to add the user auth_epoch field.');
        }

        $row = db_sql_find_one_master("SHOW COLUMNS FROM `{$table}` LIKE 'auth_epoch'");
        if ($row === false || empty($row) || !$this->satisfiesTarget($row)) {
            throw new RuntimeException('The user auth_epoch field did not satisfy the migration postcondition.');
        }
    }

    private function satisfiesTarget(array $row): bool
    {
        $type = strtolower(trim(preg_replace('/\s+/', ' ', (string) ($row['Type'] ?? ''))));
        return preg_match('/^int(?:\(11\))? unsigned$/D', $type) === 1
            && strtoupper((string) ($row['Null'] ?? '')) === 'NO'
            && array_key_exists('Default', $row)
            && (string) $row['Default'] === '0';
    }
};
