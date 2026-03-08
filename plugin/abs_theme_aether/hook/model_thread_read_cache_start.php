
$cache = cache_get('bbs_thread_' . $tid);
	if(is_null($cache)) {
		$cache = thread_read($tid);
		cache_set('bbs_thread_' . $tid, $cache, 60);
	}
	return $cache;
