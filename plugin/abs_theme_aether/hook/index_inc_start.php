//<?php


    /**
     * @var bool 是HTMX发起的请求吗？
     */
    $IS_HTMX = isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'] === 'true';
    /**
     * @var bool 是通过翻页器访问的吗？
     */
    $IS_IN_PAGINATION = isset($_REQUEST['IS_IN_PAGINATION']) && boolval($_REQUEST['IS_IN_PAGINATION']);
    /**
     * @var string 改变分页器的按钮
     */
    //$g_pagination_tpl = '<li class="page-item{active}" data-active="{text}" hx-on:click="setActive(\'{text}\',\'*\',\'ul.pagination\')"><a href="{url}" hx-include="[name=\'IS_IN_PAGINATION\']" class="page-link">{text}</a></li>';
    $g_pagination_tpl = '<li class="page-item{active}" data-active="{text}"><a href="{url}" hx-include=".pagination_extra_param" class="page-link">{text}</a></li>';
    /**
     * @var bool 隐藏面包屑
     */
    $hide_breadcrumb = true;

    /**
     * 将翻页器转换成适合HTMX的Hx-Trigger的内容
     *
     * @param string $html 输入为pagination函数的输出结果（Bootstrap分液器）
     * @param string $from 这个翻页器会控制什么列表？支持这些值：
     * thread（代表threadlist，在前台会增加IS_IN_THREADLIST）
     * post（代表postlist，在前台会增加IS_IN_POSTLIST）
     * notice（代表noticelist，在前台会增加IS_IN_NOTICELIST）
     * @return array
     */
    function process_pagination_to_htmx_trigger($html, $from = '') {
        if (empty($html)) {
            return [
                'pageItems' => [],
                'active' => ''
            ];
        }

        // Pre-process the HTML to handle problematic entities and attributes
        $html = str_replace(
            ['data-active=">"', 'data-active="<"', '&gt;', '&lt;'],
            ['data-active="next"', 'data-active="prev"', '>', '<'],
            $html
        );

        // Create a DOMDocument with proper settings
        $dom = new DOMDocument();
        libxml_use_internal_errors(true); // Suppress any parsing errors

        // Wrap the HTML in proper structure and specify encoding
        $html = '<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>';

        // Load HTML with options to avoid adding implied elements
        $dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        // Clear any accumulated errors
        libxml_clear_errors();

        $pages = [];
        $pageItems = [];
        $active = null;

        $lis = $dom->getElementsByTagName('li');
        foreach ($lis as $li) {
            $dataActive = $li->getAttribute('data-active');
            $isActive = strpos($li->getAttribute('class'), 'active') !== false;

            $anchor = $li->getElementsByTagName('a')->item(0);
            if (!$anchor) continue; // Skip if no anchor found

            $href = $anchor->getAttribute('href');
            $text = trim($anchor->textContent);

            $pageItems[] = [
                'text' => $dataActive,
                'url' => $href
            ];

            if ($isActive) {
                $active = $dataActive;
            }
        }

        $arr = [
            'pageItems' => $pageItems,
            'active' => $active
        ];

        switch ($from) {
            case 'thread':
                $arr['from_threadlist'] = true;
                break;
            case 'post':
                $arr['from_postlist'] = true;
                break;
            // hook htmx_process_pagination_to_htmx_trigger_from_case_end.php
            default:
                trigger_error('你忘了给第二个参数赋值 thread/post等', E_USER_NOTICE);
                break;
        }

        return $arr;
    }


    if (!function_exists('checkIsBetweenTime')) {
        /**
         * 判断当前的时分是否在指定的时间段内
         * 
         * 重制版，使用DateTime类消除潜在时间差异
         * 
         * @param string $startTimeStr 开始时间，格式为 'H:i'  
         * @param string $endTimeStr 结束时间，格式为 'H:i'  
         * @param bool $nextDay 是否跨天，如果为 true，则 endTime 是第二天的时间  
         * @param string $custom_timezone 当地时区
         * @return bool 如果当前时间在指定时间段内，返回 true；否则返回 false  
         * @throws Exception 如果时间范围不正确，或如果 endTime 比 startTime 更早且 nextDay 为 false 会扔出异常
         */
        function checkIsBetweenTime($startTimeStr, $endTimeStr, $nextDay = false, $custom_timezone = '') {
            // 设置时间
            $currentTime = new DateTime(); // 现在
            $startTime = DateTime::createFromFormat('H:i', $startTimeStr);
            $endTime = DateTime::createFromFormat('H:i', $endTimeStr);

            // 设置时区；如果不指定的话则使用服务器默认时区
            if (empty($custom_timezone)) {
                $custom_timezone = date_default_timezone_get();
            }
            $tz = new DateTimeZone($custom_timezone);
            $currentTime->setTimezone($tz);
            $startTime->setTimezone($tz);
            $endTime->setTimezone($tz);

            // 防呆机制：如果 endTime 比 startTime 更早且 nextDay 为 false，则抛出异常  
            if (!$nextDay && $endTime < $startTime) {
                throw new Exception("endTime 不能早于 startTime，若确定这样做，请让 nextDay = true");
            }

            // 设置日期为当前日期
            $startTime->setDate($currentTime->format('Y'), $currentTime->format('m'), $currentTime->format('d'));
            $endTime->setDate($currentTime->format('Y'), $currentTime->format('m'), $currentTime->format('d'));

            // 跨天
            if ($nextDay) {
                // 当天午夜
                $midnight = clone $currentTime;
                $midnight->setTime(0, 0, 0);

                // 如果当前时间在开始时间之后且在当前天午夜之前，或者在当前天午夜之后且在结束时间之前
                if (
                    ($currentTime >= $startTime && $currentTime < $midnight->modify('+1 day'))
                    || ($currentTime >= $midnight && $currentTime < $endTime)
                ) {
                    return true;
                }
            } else {
                // 不跨天，则直接比较时间
                if ($currentTime >= $startTime && $currentTime < $endTime) {
                    return true;
                }
            }

            return false;
        }
    }

    // 对应 经验
    $credits1_name = lang('credits1');
    if ($credits1_name === 'lang[credits1]') {
        if (isset($conf['exp_name'])) {
            $credits1_name = $conf['exp_name'];
        } else {
            $credits1_name = 'Exp. ';
        }
    }
    // 对应 金币
    $credits2_name = lang('credits2');
    if ($credits2_name === 'lang[credits2]') {
        if (isset($conf['gold_name'])) {
            $credits2_name = $conf['gold_name'];
        } else {
            $credits2_name = 'Golds';
        }
    }
    // 对应 现金
    $credits3_name = lang('credits3');
    if ($credits3_name === 'lang[credits3]') {
        if (isset($conf['rmb_name'])) {
            $credits3_name = $conf['rmb_name'];
        } else {
            $credits3_name = 'Cash';
        }
    }


    /*=============================================
=                   AETHER HTMX                   =
=============================================*/


    $HTMX_TARGET = '';
    $SHEET_MODE = 'FULL_PAGE';

    // 获取 HTMX 目标区域
    if ($IS_HTMX) {
        // 优先从请求头获取，其次从参数获取
        $HTMX_TARGET = $_SERVER['HTTP_HX_TARGET'] ?? ($_REQUEST['hx-target'] ?? '');

        // 判断具体要输出的部分
        switch ($HTMX_TARGET) {
            // *==========
            case 'S2':
                $SHEET_MODE = 'S2_FULL';
                break;
            case 'S2-Header':
                $SHEET_MODE = 'S2_HEADER_ONLY';
                break;
            case 'S2-Body':
                $SHEET_MODE = 'S2_BODY_ONLY';
                break;
            // *==========
            case 'S3':
                $SHEET_MODE = 'S3_FULL';
                break;
            case 'S3-Header':
                $SHEET_MODE = 'S3_HEADER_ONLY';
                break;
            case 'S3-Body':
                $SHEET_MODE = 'S3_BODY_ONLY';
                break;
            case 'S3-Footer':
                $SHEET_MODE = 'S3_FOOTER_ONLY';
                break;
            // *==========
            case 'S4':
                $SHEET_MODE = 'S4_FULL';
                break;
            case 'S4-Header':
                $SHEET_MODE = 'S4_HEADER_ONLY';
                break;
            case 'S4-Body':
                $SHEET_MODE = 'S4_BODY_ONLY';
                break;
            // *==========
            case 'S5':
            case 'S5-Body':
                $SHEET_MODE = 'S5_BODY_ONLY';
                break;
            // *==========
            case 'S6':
            case 'S6-Body':
                $SHEET_MODE = 'S6_BODY_ONLY';
                break;
            // *==========
            case 'user_aside':
            case 'user_aside_nav':
            case 'user_main':
            case 'user_nav':
            case 'user_body':
                $SHEET_MODE = 'SPECIAL_USER_S2_INNER';
                break;
            case 'my_body':
                $SHEET_MODE = 'SPECIAL_MY_S3_BODY';
                break;
            // *==========
            default:
                $SHEET_MODE = 'PARTIAL';
        }
    } else {
        $SHEET_MODE = 'FULL_PAGE';
    }

    /*
            case 'my_aside':
            case 'my_aside_nav':
            case 'my_main':
            case 'my_nav':
            case 'my_body':
    */

    // 设置 HTML 类名用于 CSS 控制
    if ($IS_HTMX) {
        $conf['extra_html_class'] = 'htmx-request';
    }

/*============  End of AETHER HTMX  =============*/
