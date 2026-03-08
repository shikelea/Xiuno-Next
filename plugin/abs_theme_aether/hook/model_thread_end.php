//<?php
    /**
     * 寻找特定时间范围内的帖子
     * 
     * 会寻找create_date在特定时间范围内的帖子，而不是last_date
     * 
     * 该函数默认找100个帖子，这样可以用count函数来判断“99+”的情景
     *
     * @param array $cond 寻找条件
     * @param array $orderby 按哪列排序
     * @param int $start_timestamp 开始时间点的时间戳
     * @param int $end_timestamp 结束时间点的时间戳
     * @param int $limit 寻找多少个帖子
     * @return array|null|mixed 找得到帖子的时候就是array
     * @throws Exception 
     */
    function thread_find_in_time_range($cond = array(), $orderby = array(), $start_timestamp = 0, $end_timestamp = 0, $limit = 100) {
        // hook model_thread_find_in_time_range_start.php
        if (DEBUG) {
            if ($start_timestamp === 0 || $end_timestamp === 0) {

                // * 是的，生产环境里连Exception都不能扔出去
                throw new Exception('请确保在调用 thread_find_in_time_range 函数时提供了 start_timestamp和end_timestamp');
            }
        } else {
            if ($start_timestamp === 0 || $end_timestamp === 0) {
                // * 纯粹用来擦屁股的逻辑，因为你们被PHP 7.2惯坏了
                trigger_error('请确保在调用 thread_find_in_time_range 函数时提供了 start_timestamp和end_timestamp', E_USER_NOTICE);
                $start_timestamp = time() - 60;
                $end_timestamp = time();
            }
        }
        $time_range_cond = ['create_date' => ['>' => $start_timestamp, '<=' => $end_timestamp]];
        $real_cond = array_merge($cond, $time_range_cond);
        $threadlist = thread_find($real_cond, $orderby, 1, $limit);
        // hook model_thread_find_in_time_range_end.php

        return $threadlist;
    }
