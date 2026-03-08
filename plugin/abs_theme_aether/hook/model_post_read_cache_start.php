
	$cache = cache_get('bbs_post_' . $pid);
	if(is_null($cache)) {
		$cache = post_read($pid);
		cache_set('bbs_post_' . $pid, $cache, 60);
	}
	return $cache;
