//<?php

	// 编辑器支持 HTML 编辑
	if(isset($post['doctype']) && $post['doctype'] == 1) {
		$post['message'] = htmlspecialchars($post['message_fmt']);
	}

/*=============================================
=                   以下内容来自route/thread.php                   =
=============================================*/

	$page = 1;
	$keyword = '';
	$pagesize = $conf['postlist_pagesize'];

	// hook thread_info_start.php

	$thread = thread_read($tid);
	empty($thread) and message(-1, lang('thread_not_exists'));

	$fid = $thread['fid'];
	$forum = forum_read($fid);
	empty($forum) and message(3, lang('forum_not_exists'));

	$postlist = post_find_by_tid($tid, $page, $pagesize);
	empty($postlist) and message(4, lang('post_not_exists'));

	if ($page == 1) {
		empty($postlist[$thread['firstpid']]) and message(-1, lang('data_malformation'));
		$first = $postlist[$thread['firstpid']];
		unset($postlist[$thread['firstpid']]);
		$attachlist = $imagelist = $filelist = array();
	} else {
		$first = post_read($thread['firstpid']);
	}

	$allowpost = forum_access_user($fid, $gid, 'allowpost') ? 1 : 0;
	$allowupdate = forum_access_mod($fid, $gid, 'allowupdate') ? 1 : 0;
	$allowdelete = forum_access_mod($fid, $gid, 'allowdelete') ? 1 : 0;

	forum_access_user($fid, $gid, 'allowread') or message(-1, lang('user_group_insufficient_privilege'));

	$pagination = '';

	$header['title'] = $thread['subject'] . '-' . $forum['name'] . '-' . $conf['sitename'];
	//$header['mobile_title'] = lang('thread_detail');
	$header['mobile_title'] = $forum['name'];;
	$header['mobile_link'] = url("forum-$fid");
	$header['keywords'] = '';
	$header['description'] = $thread['subject'];
	$_SESSION['fid'] = $fid;

	// hook thread_info_end.php

    /**
     * @var bool 告诉message函数在这之后会有HTML内容输出
     */
    $HOLD_ON_FOR_LATER_HTML = true;
    header('HX-Push-Url: ' . url('thread-' . $tid)); // 更新浏览器 URL
	header("HX-Retarget: #S3");
	$SHEET_MODE = "S3_FULL";
    header('HX-Trigger-After-Swap: ' . json_encode([
        'showSnackBar' => [
            'type'    => 'success',
            'title'   => lang('tips_title'),
            'subtitle'=> '',
            'content' => lang('update_successfully'),
            'delay'   => 5000
        ]
    ], JSON_FORCE_OBJECT));

	include _include(APP_PATH . 'view/htm/thread.htm');
    die;

/*============  End of 以上内容来自route/thread.php  =============*/