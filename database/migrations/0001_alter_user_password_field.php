<?php

return new class {
    public function up(string $tablepre): void
    {
        if (!preg_match('/^[A-Za-z0-9_]{0,32}$/D', $tablepre)) {
            throw new RuntimeException('Invalid table prefix for password field migration.');
        }

        $table = $tablepre . 'user';
        $row = db_sql_find_one_master("SHOW COLUMNS FROM `{$table}` LIKE 'password'");
        if ($row === false) {
            throw new RuntimeException('Failed to inspect the user password field.');
        }
        if (!is_array($row) || empty($row)) {
            throw new RuntimeException('The user password field is missing.');
        }
        if ($this->satisfiesTarget($row)) {
            return;
        }

        $ok = db_exec("ALTER TABLE `{$tablepre}user` MODIFY `password` varchar(255) NOT NULL DEFAULT '' COMMENT '密码'");
        if ($ok === false) {
            throw new RuntimeException('Failed to alter user password field.');
        }

        $row = db_sql_find_one_master("SHOW COLUMNS FROM `{$table}` LIKE 'password'");
        if ($row === false || !is_array($row) || !$this->satisfiesTarget($row)) {
            throw new RuntimeException('The user password field did not satisfy the migration postcondition.');
        }
    }

    private function satisfiesTarget(array $row): bool
    {
        return strtolower((string) ($row['Type'] ?? '')) === 'varchar(255)'
            && strtoupper((string) ($row['Null'] ?? '')) === 'NO'
            && array_key_exists('Default', $row)
            && (string) $row['Default'] === '';
    }
};
