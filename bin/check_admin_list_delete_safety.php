<?php

$root = dirname(__DIR__);
$forum_route = file_get_contents($root.'/admin/route/forum.php');
$group_route = file_get_contents($root.'/admin/route/group.php');
$forum_template = file_get_contents($root.'/admin/view/htm/forum_list.htm');
$group_template = file_get_contents($root.'/admin/view/htm/group_list.htm');

function fail($message) {
	fwrite(STDERR, "FAIL: $message\n");
	exit(1);
}

function assert_contains($source, $needle, $message) {
	strpos($source, $needle) !== FALSE || fail($message);
}

function assert_not_contains($source, $needle, $message) {
	strpos($source, $needle) === FALSE || fail($message);
}

assert_contains($forum_route, "\$fidarr = forum_post_array('fid');", 'Forum list must require the main fid array.');
assert_contains($forum_route, "forum_post_array_keys_match(\$fidarr", 'Forum list must validate companion array keys.');
assert_contains($forum_route, "\$deleteids = forum_delete_ids(\$deletefidarr, \$forumlist);", 'Forum list must derive delete ids from explicit delete_fid fields.');
assert_contains($forum_route, 'function forum_post_array(', 'Forum list must have a strict POST array helper.');
assert_contains($forum_route, 'function forum_delete_ids(', 'Forum list must have an explicit delete-id helper.');
assert_not_contains($forum_route, 'array_diff_key($forumlist, $fidarr)', 'Forum list must not delete rows just because they were omitted from POST.');

assert_contains($forum_template, '<div class="delete-inputs"></div>', 'Forum list template must include a hidden delete input container.');
assert_contains($forum_template, 'delete_fid[', 'Forum list delete UI must submit explicit delete_fid markers.');
assert_contains($forum_template, "jform.find('.delete-inputs').append", 'Forum list delete UI must append delete markers before removing rows.');

assert_contains($group_route, "\$gidarr = group_post_array('_gid');", 'Group list must require the main _gid array.');
assert_contains($group_route, "group_post_array_keys_match(\$gidarr", 'Group list must validate companion array keys.');
assert_contains($group_route, "\$deleteids = group_delete_ids(\$deletegidarr, \$grouplist);", 'Group list must derive delete ids from explicit delete_gid fields.');
assert_contains($group_route, 'function group_post_array(', 'Group list must have a strict POST array helper.');
assert_contains($group_route, 'function group_delete_ids(', 'Group list must have an explicit delete-id helper.');
assert_not_contains($group_route, 'array_diff_key($grouplist, $gidarr)', 'Group list must not delete rows just because they were omitted from POST.');

assert_contains($group_template, '<div class="delete-inputs"></div>', 'Group list template must include a hidden delete input container.');
assert_contains($group_template, 'delete_gid[', 'Group list delete UI must submit explicit delete_gid markers.');
assert_contains($group_template, "jform.find('.delete-inputs').append", 'Group list delete UI must append delete markers before removing rows.');

echo "OK: admin list delete safety checks passed\n";
