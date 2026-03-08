<?php
exit;

// 只删除用户的收藏记录，收藏统计可能会不准确，不需要去重新统计收藏数量
haya_favorite_delete_by_uid($uid);

?>