<?php
!defined('DEBUG') AND exit('Forbidden');
$tablepre = $db->tablepre;
$sql="alter table {$tablepre}user alter column credits set default 0;";
db_exec($sql);
$sql="alter table {$tablepre}user alter column golds set default 0;";
db_exec($sql);
$sql="alter table {$tablepre}user alter column rmbs set default 0;";

?>