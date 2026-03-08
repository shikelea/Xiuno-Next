
if($IS_HTMX && $IS_IN_PAGINATION ): 
    header("Hx-Trigger: " . json_encode(['updatePagination' => process_pagination_to_htmx_trigger($pagination,'thread')]));
    ob_start(); 
?>

<?php include _include(APP_PATH.'view/htm/thread_list.inc.htm');?>

<?php ob_end_flush(); die; endif; 