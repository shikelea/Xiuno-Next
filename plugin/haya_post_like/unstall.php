<?php

/**
 * 帖子点赞 卸载程序
 *
 * @create 2018-1-31
 * @author deatil
 */

!defined('DEBUG') AND exit('Forbidden');

$tablepre = $db->tablepre;

db_exec("DROP TABLE IF EXISTS {$tablepre}post_like;");

db_exec("ALTER TABLE {$tablepre}post DROP COLUMN likes;");
db_exec("ALTER TABLE {$tablepre}thread DROP COLUMN likes;");

// 删除插件配置
setting_delete('haya_post_like'); 

// 删除热门回复缓存
cache_delete('haya_post_like');

?>