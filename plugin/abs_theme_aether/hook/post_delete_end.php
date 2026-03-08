//<?php

    if ($isfirst) {
        header('HX-Retarget: #S2');               // 替换整个 body
        header('HX-Swap: innerHTML');             // 完全替换内容
        header('HX-Push-Url: ' . url('forum-' . $fid)); // 更新浏览器 URL
        header('HX-Trigger: ' . json_encode([
            "showSnackBar" => ["type" => "success", "title" => lang('tips_title'), "subtitle" => "", "content" => lang('delete_successfully'), "delay" => 5000],
            'closeModal' => true,
        ], JSON_FORCE_OBJECT));

        /*=============================================
        =                   以下内容来自route/forum.php                   =
        =============================================*/

        // hook forum_start.php
        $page = 1;
        $orderby = 'lastpid';
        $extra = array(); // 给插件预留

        $active = 'default';
        $extra['orderby'] = $orderby;

        $forum = forum_read($fid);
        empty($forum) and message(3, lang('forum_not_exists'));
        forum_access_user($fid, $gid, 'allowread') or message(-1, lang('insufficient_visit_forum_privilege'));
        $pagesize = $conf['pagesize'];

        // hook forum_top_list_before.php

        $toplist = $page == 1 ? thread_top_find($fid) : array();

        // 从默认的地方读取主题列表
        $thread_list_from_default = 1;

        // hook forum_thread_list_before.php

        if ($thread_list_from_default) {
            $pagination = pagination(url("forum-$fid-{page}", $extra), $forum['threads'], $page, $pagesize);
            $threadlist = thread_find_by_fid($fid, $page, $pagesize, $orderby);
        }

        $header['title'] = $forum['seo_title'] ? $forum['seo_title'] : $forum['name'] . '-' . $conf['sitename'];
        $header['mobile_title'] = $forum['name'];
        $header['mobile_link'] = url("forum-$fid");
        $header['keywords'] = '';
        $header['description'] = $forum['brief'];

        $_SESSION['fid'] = $fid;

        // hook forum_end.php

        include _include(APP_PATH . 'view/htm/forum.htm');

        die;

        /*============  End of 以上内容来自route/forum.php  =============*/
    } else {
        $new_count = $thread['posts']-1;
        header('HX-Trigger: ' . json_encode([
        'removePost' => ['pid' => $pid],
        'updatePostCount' => ['count' => $new_count], 
        'closeModal' => true
        ], JSON_FORCE_OBJECT));
    }
