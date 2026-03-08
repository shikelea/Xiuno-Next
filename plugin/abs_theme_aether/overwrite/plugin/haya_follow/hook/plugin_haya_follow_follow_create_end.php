<?php
exit;

if (function_exists("notice_send")) {
	notice_send($user['uid'], $_uid, '<a href="'.url('user-'.$user['uid']).'" target="_blank">'.$user['username'].'</a> 关注了你', 3);
}
	
?>