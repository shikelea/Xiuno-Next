//<?php 

if ($method === 'GET') {
    /**
     * @var bool 是从删除帖子（post-delete-{pid}.htm）页面来的吗？
     */
    $IS_FROM__ROUTE_POST__ACTION_DELETE = true;
    /**
     * @var bool 要被删除的物品是回帖吗？与`$IS_ITEM_A_THREAD`互斥
     */
    $IS_ITEM_A_POST = false;
    /**
     * @var bool 要被删除的物品是主题帖吗？与`$IS_ITEM_A_POST`互斥
     */
    $IS_ITEM_A_THREAD = false;
    if ($pid === 0) {
        message(-1, lang('attempt_to_delete_the_void')); // 试图删除「虚无」的勇者啊，你失败了
    }
    $post = post_read($pid);
    if (empty($post)) {
        message(-1, lang('post_not_exists'));
        die;
    } else {
        $IS_ITEM_A_POST = true;
    }
	$tid = $post['tid'];
	$thread = thread_read($tid);
    if (empty($thread)) {
        message(-1, lang('thread_not_exists'));
        die;
    } else {
        $isfirst = $post['isfirst'];
        if ($isfirst) {
            $IS_ITEM_A_POST = false;
            $IS_ITEM_A_THREAD = true;
        }
    }
    include _include(APP_PATH.'view/htm/mod_delete.htm');
    die;
}