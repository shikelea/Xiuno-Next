<?php

/**
 * 帖子收藏 更新文件
 *
 * @create 2018-2-24
 * @author deatil
 */
 
!defined('DEBUG') AND exit('Forbidden');

function haya_favorite_db_field_exists($tablename, $fieldname) {
	$isexists = db_sql_find_one("DESCRIBE " . $tablename . " `{$fieldname}`");
	return !empty($isexists) ? true : false;
}

$tablepre = $db->tablepre;

if (haya_favorite_db_field_exists("{$tablepre}thread", "haya_favorites")) {
	db_exec("ALTER TABLE `{$tablepre}thread` CHANGE COLUMN `haya_favorites` `favorites` int(11) NULL DEFAULT '0' COMMENT '收藏数';");
}

xn_unlink(APP_PATH.'plugin/haya_favorite/hook/my_nav_end.htm');
xn_unlink(APP_PATH.'plugin/haya_favorite/hook/admin_user_delete_end.php');

// add 2018-3-8
xn_unlink(APP_PATH.'plugin/haya_favorite/hook/my_common_mobile_my_thread_after.htm');
xn_unlink(APP_PATH.'plugin/haya_favorite/hook/my_common_my_thread_after.htm');
xn_unlink(APP_PATH.'plugin/haya_favorite/view/htm/my_favorite.template.htm');

// add 2018-3-14
if (!haya_favorite_db_field_exists("{$tablepre}user", "favorites")) {
	db_exec("ALTER TABLE {$tablepre}user ADD COLUMN favorites int(11) NULL DEFAULT '0' COMMENT '收藏数';");
}

// add 2018-5-12
xn_unlink(APP_PATH.'plugin/haya_favorite/hook/model_thread_delete_end.php');

// add 2018-5-18
// xn_unlink(APP_PATH.'plugin/haya_favorite/hook/model_user__delete_end.php');

// 更新插件配置
$haya_favorite_config = array(
	"user_favorite" => 10,
	"user_favorite_count" => 20,
	"user_favorite_sort" => 'desc',
	"thread_show_favorite" => 0,
	"favorite_count_type" => 0,
	"show_hot_favorite" => 0,
	"hot_favorite_count" => 10,
	"hot_favorite_find_time" => 30,
	"hot_favorite_cache_time" => 86400,
);
setting_set('haya_favorite', $haya_favorite_config); 

?>