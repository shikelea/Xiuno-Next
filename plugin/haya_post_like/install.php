<?php

/**
 * 帖子点赞
 *
 * @create 2018-1-31
 * @author deatil
 */
 
!defined('DEBUG') AND exit('Forbidden');

$tablepre = $db->tablepre;

// 帖子点赞
$sql = "
CREATE TABLE {$tablepre}post_like (
	`tid` int(11) NOT NULL DEFAULT '0' COMMENT '帖子ID',
	`pid` int(11) NOT NULL DEFAULT '0' COMMENT '回帖ID',
	`uid` int(11) NOT NULL DEFAULT '0' COMMENT '用户ID',
	`create_date` int(10) NULL DEFAULT '0' COMMENT '添加时间',
	`create_ip` int(10) NULL DEFAULT '0' COMMENT '添加IP',
	KEY `tid_uid` (`tid`, `uid`),
	KEY `pid_uid` (`pid`, `uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
";
db_exec($sql);

db_exec("ALTER TABLE {$tablepre}post ADD COLUMN likes int(11) NULL DEFAULT '0' COMMENT '点赞数';");
db_exec("ALTER TABLE {$tablepre}thread ADD COLUMN likes int(11) NULL DEFAULT '0' COMMENT '点赞数';");

// 添加插件配置
$haya_post_like_config = array(
	"open_thread" => 0,
	"list_show_likes" => 0,
	"thread_like_position" => 2,
	"open_post" => 1,
	"post_like_position" => 2,
	"post_loved_color" => "secondary",
	"open_hot_like" => 1,
	"hot_like_post_low_count" => 10,
	"hot_like_post_size" => 5,
	"hot_like_isfirst" => 0,
	"hot_like_life_time" => 86400,
	"delete_time" => 0,
	"like_is_delete" => 1,
	"open_my_post_like" => 0,
	"my_post_like_pagesize" => 10,
	"post_like_count_type" => 0,
);
setting_set('haya_post_like', $haya_post_like_config); 

// 添加热门回复缓存
cache_set('haya_post_like', '');

?>