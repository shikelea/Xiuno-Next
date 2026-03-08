<?php
exit;

// 只删除用户的点赞记录，点赞统计可能会不准确，不需要去重新统计点赞数量
haya_post_like_delete_by_uid($uid);

?>