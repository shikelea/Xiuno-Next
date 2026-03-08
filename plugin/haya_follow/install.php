<?php

/**
 * 用户关注
 *
 * @create 2018-2-5
 * @author deatil
 */
 
!defined('DEBUG') AND exit('Forbidden');

$tablepre = $db->tablepre;

// 用户关注
$sql = "
CREATE TABLE {$tablepre}follow (
	`uid` int(11) NOT NULL COMMENT '被关注者用户ID',
	`comment` varchar(32) NULL DEFAULT '' COMMENT '关注者对被关注者设置备注',
	`show_dynamic` tinyint(1) NULL DEFAULT '1' COMMENT '关注者对被关注者设置动态',
	`follow_uid` int(11) NOT NULL COMMENT '关注者用户ID',
	`status` tinyint(1) NULL DEFAULT '1' COMMENT '关系，1-单向关注， 2-双向关注',
	`create_date` int(10) NULL DEFAULT '0' COMMENT '添加时间',
	`create_ip` int(10) NULL DEFAULT '0' COMMENT '添加IP',
	KEY `uid_follow_uid` (`uid`, `follow_uid`),
	KEY `follow_uid_show_dynamic` (`follow_uid`, `show_dynamic`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
";
$r = db_exec($sql);

$sql = "
ALTER TABLE {$tablepre}user ADD COLUMN follows int(11) NULL DEFAULT '0' COMMENT '关注数量';
";
$r = db_exec($sql);

$sql = "
ALTER TABLE {$tablepre}user ADD COLUMN followeds int(11) NULL DEFAULT '0' COMMENT '粉丝数量';
";
$r = db_exec($sql);

// 添加插件配置
$haya_follow_config = array(
	"show_my_dynamic" => 0,
	"delete_follower" => 0,
	"my_dynamic_post_num" => 20,
	"timeline_post_pagesize" => 20,
	"follow_user_pagesize" => 20,
	"followed_life_time" => 86400,
	
	"show_user_dynamic" => 0,
	"user_dynamic_pagesize" => 20,
);
setting_set('haya_follow', $haya_follow_config); 

?>