elseif($action == 'ranklist') {
    if($method == 'GET')
        include _include(APP_PATH.'plugin/tt_ranklist/view/htm/my_ranklist.htm');
} elseif($action=='ranklist_posts') {
    if($method == 'GET')
        include _include(APP_PATH.'plugin/tt_ranklist/view/htm/my_ranklist_posts.htm');
} elseif($action=='ranklist_credits') {
    if($method == 'GET')
        include _include(APP_PATH.'plugin/tt_ranklist/view/htm/my_ranklist_credits.htm');
} elseif($action=='ranklist_golds') {
    if($method == 'GET')
        include _include(APP_PATH.'plugin/tt_ranklist/view/htm/my_ranklist_golds.htm');
} elseif($action=='ranklist_rmbs') {
    if($method == 'GET')
        include _include(APP_PATH.'plugin/tt_ranklist/view/htm/my_ranklist_rmbs.htm');
}