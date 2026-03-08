<?php

/**
 * 帖子点赞 更新文件
 *
 * @create 2018-2-7
 * @author deatil
 */
 
!defined('DEBUG') AND exit('Forbidden');

include APP_PATH.'plugin/haya_post_like/model/haya_post_like_db.func.php';

$tablepre = $db->tablepre;

$haya_post_like_db_haya_post_like = "{$tablepre}haya_post_like";
if (haya_post_like_db_table_exists($haya_post_like_db_haya_post_like)) {
	$sql = "
	DROP TABLE IF EXISTS {$haya_post_like_db_haya_post_like};
	";
	$r = db_exec($sql);
}

$haya_post_like_db_post_like = "{$tablepre}post_like";
if (!haya_post_like_db_table_exists($haya_post_like_db_post_like)) {
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
	$r = db_exec($sql);
}

// update 2018-3-8
if (!haya_post_like_db_field_exists("{$tablepre}thread", "likes")) {
	$sql = "
	ALTER TABLE {$tablepre}thread ADD COLUMN likes int(11) NULL DEFAULT '0' COMMENT '点赞数';
	";
	$r = db_exec($sql);
}

xn_unlink(APP_PATH.'plugin/haya_post_like/hook/admin_user_delete_end.php');
xn_unlink(APP_PATH.'plugin/haya_post_like/hook/model_post_delete_end.php');

// add 2018-3-8
xn_unlink(APP_PATH.'plugin/haya_post_like/hook/my_common_mobile_my_thread_after.htm');
xn_unlink(APP_PATH.'plugin/haya_post_like/hook/my_common_my_thread_after.htm');
xn_unlink(APP_PATH.'plugin/haya_post_like/view/htm/my_post_like.template.htm');

// add 2018-5-12
xn_unlink(APP_PATH.'plugin/haya_post_like/hook/model_thread_delete_end.php');

// add 2018-5-18
// xn_unlink(APP_PATH.'plugin/haya_post_like/hook/model_user__delete_end.php');

// 更新插件配置
$haya_post_like_old_config = setting_get('haya_post_like');
$haya_post_like_new_config = array(
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
$haya_post_like_config = array_merge($haya_post_like_old_config, $haya_post_like_new_config);
setting_set('haya_post_like', $haya_post_like_config); 


?>