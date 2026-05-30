<?php

return new class {
    public function up(string $tablepre): void
    {
        $ok = db_exec("ALTER TABLE `{$tablepre}user` MODIFY `password` varchar(255) NOT NULL DEFAULT '' COMMENT '密码'");
        if ($ok === false) {
            throw new RuntimeException('Failed to alter user password field.');
        }
    }
};
