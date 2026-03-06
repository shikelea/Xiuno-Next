<?php

return new class {
    public function up(string $tablepre): void
    {
        db_exec("ALTER TABLE `{$tablepre}user` MODIFY `password` varchar(255) NOT NULL DEFAULT '' COMMENT '密码'");
    }
};
