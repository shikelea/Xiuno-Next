<?php exit;


if (param(1, '') === 'gettagcatelist') {
    $fid = param(2, 0);
    if ($fid === 0) {
        // 因为从主页进入到发帖页面，fid是0，但实际上name=fid的select会选中第一个
        $fid = 1;
    }
    $forum = forum_read($fid);
    empty($forum) and message(3, lang('forum_not_exists'));
    $checked_tags = [];

    $tid = param(3, 0);
    $checked_tags = tag_thread_find(array('tid'=>$tid), array(), 1, 1000);
    if (!empty($checked_tags)) {
        $checked_tags = array_column($checked_tags,'tagid');
    }

    include _include(APP_PATH . 'plugin/abs_theme_aether/template_parts_plugin/xn_tag/tagcatelist__post.htm');
    die;
}
