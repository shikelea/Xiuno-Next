<?php

/**
 * 帖子收藏插件 卸载程序
 *
 * @create 2018-1-25
 * @author deatil
 */

!defined('DEBUG') AND exit('Forbidden');

$tablepre = $db->tablepre;

db_exec("DROP TABLE IF EXISTS {$tablepre}haya_favorite;");

db_exec("ALTER TABLE {$tablepre}thread DROP COLUMN favorites;");
db_exec("ALTER TABLE {$tablepre}user DROP COLUMN favorites;");

// 删除插件配置
setting_delete('haya_favorite'); 

// 清空热门缓存
cache_delete('haya_favorite_favorites');

?>