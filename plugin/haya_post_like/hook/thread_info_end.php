<?php
exit;

if (isset($haya_post_like_config['open_post'])
	&& $haya_post_like_config['open_post'] == 1
) {
	$hot_like_post_size = intval($haya_post_like_config['hot_like_post_size']) + 1;
	$hot_like_post_low_count = intval($haya_post_like_config['hot_like_post_low_count']);
	
	$haya_post_like_post_ids = array();
	if (!empty($postlist)) {
		foreach ($postlist as $haya_post_like_post) {
			$haya_post_like_post_ids[] = $haya_post_like_post['pid'];
		}
	}
	
	$haya_post_like_life_time = isset($haya_post_like_config['hot_like_life_time']) ? intval($haya_post_like_config['hot_like_life_time']) : 86400;
	$haya_post_like_hot_posts = haya_post_like_find_hot_posts_by_tid_cache($thread['tid'], $hot_like_post_size, $hot_like_post_low_count, $haya_post_like_life_time);
	
	if (!empty($haya_post_like_hot_posts)) {
		if (isset($haya_post_like_config['hot_like_isfirst'])
			&& $haya_post_like_config['hot_like_isfirst'] == 1
		) {
			$hot_like_isfirst = true;
		} else {
			$hot_like_isfirst = false;
		}
		
		$haya_post_like_hot_post_isfirst = false;
		foreach ($haya_post_like_hot_posts as $haya_post_like_hot_post_key => $haya_post_like_hot_post) {
			if ($haya_post_like_hot_post['isfirst'] == 1 && !$hot_like_isfirst) {
				unset($haya_post_like_hot_posts[$haya_post_like_hot_post_key]);
				$haya_post_like_hot_post_isfirst = true;
			} else {
				$haya_post_like_post_ids[] = $haya_post_like_hot_post['pid'];
				
				// 移除楼层
				$haya_post_like_hot_posts[$haya_post_like_hot_post_key]['floor'] = '';
			}
		}
		
		if (!$haya_post_like_hot_post_isfirst && (count($haya_post_like_hot_posts)) >= $hot_like_post_size) {
			array_pop($haya_post_like_hot_posts);
		}
	}
	
	$haya_post_like_pids = haya_post_like_find_by_pids_and_uid($haya_post_like_post_ids, $uid, count($haya_post_like_post_ids));
}

?>