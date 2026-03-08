//<?php

/* 当你在未来读到这时：
   我早已离开
   但HTMX的蓝光仍会
   在每个深夜准时亮起
   像不像2017年
   我们第一次看见xiuno时
   屏幕反射在眼里的光？ */
   
$maxpage = ceil($totalnum / $pagesize);

if ($page > $maxpage) {
    $postlist = [];
}

$IS_IN_POSTLIST = isset($_REQUEST['IS_IN_POSTLIST']) && boolval($_REQUEST['IS_IN_POSTLIST']);
if($IS_HTMX && $IS_IN_PAGINATION && $IS_IN_POSTLIST): 
    header("Hx-Trigger: " . json_encode(['updatePagination' => process_pagination_to_htmx_trigger($pagination,'post')]));
    ob_start(); 
?>

<?php include _include(APP_PATH.'view/htm/post_list.inc.htm'); ?>

<?php ob_end_flush(); die; endif; 