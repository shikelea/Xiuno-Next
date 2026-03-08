//<?php

        /**
         * @var bool 直接返回帖子的 html
         */
		$return_html = boolval(param('return_html', 0));
		if($IS_HTMX && $return_html) {
			$new_count = $thread['posts']+1;
			header('Hx-Trigger: ' . json_encode([
				'updatePostCount' => ['count' => $new_count], 
			], JSON_FORCE_OBJECT));
			$filelist = []; // 占位用
			ob_start();
			include _include(APP_PATH.'view/htm/post_list.inc.htm');
            ob_end_flush();
            die;
		} 