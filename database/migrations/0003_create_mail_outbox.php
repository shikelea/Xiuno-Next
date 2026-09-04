<?php

return new class {
    public function up(string $tablepre): void
    {
        if (!preg_match('/^[A-Za-z0-9_]{0,32}$/D', $tablepre)) {
            throw new RuntimeException('Invalid table prefix for mail outbox migration.');
        }

        $table = $tablepre . 'mail_outbox';
        $tables = db_sql_find_master('SHOW TABLES');
        if ($tables === false) {
            throw new RuntimeException('Failed to inspect the mail outbox table.');
        }
        $exists = false;
        foreach ($tables as $row) {
            if (in_array($table, array_map('strval', array_values($row)), true)) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            $ok = db_exec(
                "CREATE TABLE `{$table}` ("
                . "`outbox_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,"
                . "`kind` varchar(32) NOT NULL DEFAULT '',"
                . "`payload` mediumtext NOT NULL,"
                . "`available_at` int(11) unsigned NOT NULL DEFAULT '0',"
                . "`expires_at` int(11) unsigned NOT NULL DEFAULT '0',"
                . "`lease_until` int(11) unsigned NOT NULL DEFAULT '0',"
                . "`lease_token` char(64) NOT NULL DEFAULT '',"
                . "`attempts` tinyint(3) unsigned NOT NULL DEFAULT '0',"
                . "`create_date` int(11) unsigned NOT NULL DEFAULT '0',"
                . "PRIMARY KEY (`outbox_id`),"
                . "KEY `due` (`available_at`,`lease_until`),"
                . "KEY `expires` (`expires_at`)"
                . ") ENGINE=MyISAM DEFAULT CHARSET=utf8"
            );
            if ($ok === false) {
                throw new RuntimeException('Failed to create the mail outbox table.');
            }
        }

        $columns = db_sql_find_master("SHOW COLUMNS FROM `{$table}`");
        if ($columns === false) {
            throw new RuntimeException('Failed to verify the mail outbox table.');
        }
        $byName = [];
        foreach ($columns as $column) {
            if (isset($column['Field'])) $byName[(string) $column['Field']] = $column;
        }
        foreach (['outbox_id', 'kind', 'payload', 'available_at', 'expires_at', 'lease_until', 'lease_token', 'attempts', 'create_date'] as $name) {
            if (!isset($byName[$name])) {
                throw new RuntimeException("The mail outbox table is missing required column {$name}.");
            }
        }
        if (strtolower((string) ($byName['payload']['Type'] ?? '')) !== 'mediumtext'
            || strtolower((string) ($byName['lease_token']['Type'] ?? '')) !== 'char(64)'
            || stripos((string) ($byName['outbox_id']['Extra'] ?? ''), 'auto_increment') === false) {
            throw new RuntimeException('The existing mail outbox table does not satisfy the required schema.');
        }
    }
};
