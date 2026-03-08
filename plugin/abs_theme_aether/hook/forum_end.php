<?php exit;

// ? 记录本次页面访问时间（仅在非HTMX请求且非轮询请求时更新）
if (!$IS_HTMX && !(isset($_REQUEST['update_since']) && boolval(param('update_since', 0)))) {
	$_SESSION['htmx_last_threadlist_check_' . $fid] = time();
}

// ? 输出轮询增量更新帖子列表的结果
if ($IS_HTMX && isset($_REQUEST['update_since']) && boolval(param('update_since', 0))) {
	$start = $_SESSION['htmx_last_threadlist_check_' . $fid] ?? time();
	$end = time();
	$refresh_url = url($route . '-1', ['refresh' => 1]);
	$new_cond = [];
	if ($fid > 0) {
		$new_cond['fid'] = $fid;
	}

	// 生成缓存键
	$cache_key = 'new_threads_count_' . md5(serialize($new_cond) . $start . $end);

	// 尝试从缓存获取新帖子数量
	$new_threads_count = cache_get($cache_key);
	
	if (is_null($new_threads_count)) {
		// 缓存未命中，执行数据库查询
		$new_threads_count = count(thread_find_in_time_range(
			$new_cond,
			['tid' => 0],
			$start,
			$end
		));
		
		// 将结果存入缓存，设置较短的过期时间（例如30秒）
		// 这样可以避免在短时间内重复查询相同的时间区间
		cache_set($cache_key, $new_threads_count, 30);
	}

	if ($new_threads_count === 0) {
		http_response_code(204);
	} else {
		include _include(APP_PATH . 'plugin/abs_theme_aether/view/htm/new_threads_alert.htm');
	}

	die;
}

// ? 输出对应页码的帖子列表
if ($IS_HTMX && $IS_IN_PAGINATION):
	// * 如果是刷新操作，更新时间戳
	if (boolval(param('refresh', 0))) {
		$_SESSION['htmx_last_threadlist_check_' . $fid] = time();
	}

		header("Hx-Trigger: " . json_encode(['updatePagination' => process_pagination_to_htmx_trigger($pagination, 'thread')]));
		ob_start();
		?>

		<!--{hook index_threadlist_before.htm}-->
		<?php include _include(APP_PATH . 'view/htm/thread_list.inc.htm'); ?>
		<!--{hook index_threadlist_after.htm}-->

		<?php
		$content = ob_get_contents();
		ob_end_flush();
	
	die;
endif;