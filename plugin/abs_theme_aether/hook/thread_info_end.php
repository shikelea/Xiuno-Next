//<?php

$IS_IN_POSTLIST = isset($_REQUEST['IS_IN_POSTLIST']) && boolval($_REQUEST['IS_IN_POSTLIST']);
if($IS_HTMX && $IS_IN_PAGINATION && $IS_IN_POSTLIST): 
    
    header("Hx-Trigger: " . json_encode(['updatePagination' => process_pagination_to_htmx_trigger($pagination,'post')]));

    ob_start(); 
?>

<?php include _include(APP_PATH.'view/htm/post_list.inc.htm'); ?>

<?php if(isset($_REQUEST['user']) || isset($_REQUEST['sort'])): ?>

<!--{hook thread_post_list_title_right.htm}-->

<!--{hook htmx_compatibility__haya_post_info__orderby__card_thread.htm}-->

<?php endif; ?>

<?php ob_end_flush(); die; endif; 