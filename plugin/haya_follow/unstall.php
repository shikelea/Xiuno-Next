<?php

/**
 * 用户关注
 *
 * @create 2018-2-5
 * @author deatil
 */

!defined('DEBUG') AND exit('Forbidden');

$tablepre = $db->tablepre;

$sql = "
DROP TABLE IF EXISTS {$tablepre}follow;
";
$r = db_exec($sql);

$sql = "
ALTER TABLE {$tablepre}user DROP COLUMN follows;
";
$r = db_exec($sql);

$sql = "
ALTER TABLE {$tablepre}user DROP COLUMN followeds;
";
$r = db_exec($sql);

// 删除插件配置
setting_delete('haya_follow'); 

// 清空缓存
cache_delete('haya_follow');

?>