
<?php
!defined('DEBUG') and exit('Access Denied.');
$page = 1; 
$keyword = htmlspecialchars(strip_tags(trim(param('keyword', '')))); 
$PAGESIZE = 10;
$tablepre = $db->tablepre;

/**  
 * 高亮显示关键词（包括汉字）  
 * @param string $subject 原文  
 * @param string $keyword 关键字  
 * @return string 有HTML mark标签高亮的原文  
 */
function highlightKeyword($subject, $keyword) {
	$pattern = '/' . preg_quote($keyword, '/') . '/iu';
	return preg_replace($pattern, '<mark>$0</mark>', $subject);
}

function quicksearch_message_warpper($code, $message, $extra = []) {
	global $IS_HTMX;

	if (!isset($IS_HTMX)) {
		/**
		 * @var bool 是HTMX发起的请求吗？
		 */
		$IS_HTMX = isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'] === 'true';
	}
	$bs_colors = ['primary', 'success', 'info', 'warning', 'danger', 'secondary', 'dark', 'light'];
	if ($IS_HTMX) {
		if (isset($extra['alert_style'])) {
			echo '<div class="mb-0 alert alert-' . (in_array($extra['alert_style'], $bs_colors, true) ? $extra['alert_style'] : 'info') . '">' . $message . '</div>';
		} else {
			echo $message;
		}
	} else {
		message($code, htmlspecialchars(strip_tags($message)), $extra);
	}
}

if (mb_strlen($keyword) == 0) {
	quicksearch_message_warpper(1, '请输入您要搜索的关键词！', ['alert_style' => 'warning']);
	exit;
}
if (mb_strlen($keyword) < 3) {
	quicksearch_message_warpper(1, '请输入至少3个字符方可开始搜索。', ['alert_style' => 'warning']);
	exit;
}
if (mb_strlen($keyword) > 60) {
	quicksearch_message_warpper(2, '您输入的关键词太长了！请精简关键词！', ['alert_style' => 'warning']);
	exit;
}

if ($keyword) {

	$search_result_list = [];
	$all_threadlist = db_find('thread', array('subject' => array('LIKE' => "%$keyword%")), [], 1, 1000, '', array('tid'));
	$get_num = $all_count = 0;
	if (is_array($all_threadlist)) {
		$get_num = $all_count = count($all_threadlist); //全部符合帖子
	}

	$threadlist = db_find('thread', array('subject' => array('LIKE' => $keyword)), [], $page, $PAGESIZE, '', array('tid', 'subject', 'uid'));
	if (count($threadlist) < 1) {
		if (preg_match("/[\x7f-\xff]/", $keyword)) {
			$kw_start3 = xn_substr($keyword, 0, 3);
			$kw_start2 = xn_substr($keyword, 0, 2);
			$kw_end3 = xn_substr($keyword, xn_strlen($keyword) - 3, 3);
			$likestart3 = db_sql_find("SELECT `tid`, `subject`, `uid` FROM " . $tablepre . "_thread WHERE  `subject` LIKE '%$kw_start3%' LIMIT " . $PAGESIZE . ";"); //前3个字符匹配的标题的帖子
			$likeend3 = db_sql_find("SELECT `tid`, `subject`, `uid` FROM " . $tablepre . "_thread WHERE  `subject` LIKE '%$kw_end3%' LIMIT " . $PAGESIZE . ";"); //后3个字符匹配的标题的帖子
			$likestart2 = db_sql_find("SELECT `tid`, `subject`, `uid` FROM " . $tablepre . "_thread WHERE  `subject` LIKE '%$kw_start2%' LIMIT " . $PAGESIZE . ";"); //前2个字符匹配的标题的帖子
			if (
				is_array($threadlist)
				&& is_array($likestart3)
				&& is_array($likeend3)
				&& is_array($likestart2)
			) {
				$threadlist = array_unique(array_merge($threadlist, $likestart3, $likeend3, $likestart2), SORT_REGULAR);
				$get_num = count($threadlist);
			} else {
				quicksearch_message_warpper(4, '未找到帖子，请尝试更换关键字后再试一次。', ['alert_style' => 'info']);
				$get_num = 0;
			}
		} else {
			//无中文特殊处理
			$kw_start5 = xn_substr($keyword, 0, 5);
			$kw_start4 = xn_substr($keyword, 0, 4);
			$kw_end5 = xn_substr($keyword, xn_strlen($keyword) - 5, 5);

			$likestart3 = db_sql_find("SELECT `tid`, `subject`, `uid` FROM " . $tablepre . "_thread WHERE  `subject` LIKE '%$kw_start5%' LIMIT " . $PAGESIZE . ";"); //前5个
			$likeend3 = db_sql_find("SELECT `tid`, `subject`, `uid` FROM " . $tablepre . "_thread WHERE  `subject` LIKE '%$kw_end5%' LIMIT " . $PAGESIZE . ";"); //后5个
			$likestart2 = db_sql_find("SELECT `tid`, `subject`, `uid` FROM " . $tablepre . "_thread WHERE  `subject` LIKE '%$kw_start4%' LIMIT " . $PAGESIZE . ";"); //前4个
			if (
				is_array($threadlist)
				&& is_array($likestart3)
				&& is_array($likeend3)
				&& is_array($likestart2)
			) {
				$threadlist = array_unique(array_merge($threadlist, $likestart3, $likeend3, $likestart2), SORT_REGULAR);
				$get_num = count($threadlist);
			} else {
				quicksearch_message_warpper(4, '未找到帖子，请尝试更换关键字后再试一次。',['alert_style' => 'info']);
				$get_num = 0;
			}
		}
	}
	foreach ($threadlist as $value) {
		$search_result_list[] = [
			'url' => url('thread-' . $value['tid']),
			'subject' => highlightKeyword($value['subject'], $keyword),
		];
	}
	if ($IS_HTMX) {
		if ($get_num) {
			echo '<ul class="list-group list-group-flush">';
			foreach ($search_result_list as $item) {
				echo '<li class="list-group-item border-0"><a href="' . htmlspecialchars($item['url']) . '" data-target="searchModal" hx-on:click="toggleModal(event);Aether.openInS3(\'thread\');">' . $item['subject'] . '</a></li>';
			}
			echo '</ul>';
			if ($all_count - $get_num == 0) {
				echo  '<a href="' . url('search-' . urlencode($keyword)) . '" class="btn bg-primary-subtle btn-block w-100 mt-3">查阅更多搜索结果</a>';
			}
			}
	} elseif ($ajax) {
		message(0, [
			'search_result' => $search_result_list,
			'total_count' => $all_count,
			'more_count' => $all_count - $get_num,
		]);
	} else {
		header('Location: ' . url('search-' . urlencode($keyword)));
	}
}

/*
这段代码是用来实现搜索功能的。首先获取当前页数和搜索关键词，如果搜索关键词为空，则显示提示信息并在3秒后返回上一页；如果搜索关键词过长，则显示提示信息并在3秒后返回上一页。接着根据搜索关键词在数据库中查找符合条件的帖子，并进行处理，最后生成分页链接并展示搜索结果页面。 
 
逐步解释代码： 
1. 首先检查是否定义了DEBUG常量，如果没有则退出程序。 
2. 获取当前页数和搜索关键词。 
3. 如果搜索关键词为空，则显示提示信息并在3秒后返回上一页。 
4. 如果搜索关键词过长，则显示提示信息并在3秒后返回上一页。 
5. 根据搜索关键词在数据库中查找符合条件的帖子数量。 
6. 根据搜索关键词在数据库中查找符合条件的帖子列表。 
7. 如果搜索结果为空，则根据关键词进行特殊处理，再次查找相关帖子。 
8. 生成分页链接。 
9. 最后包含展示搜索结果页面的html文件。
*/
