<?php
/**
 * PHP 8+ 兼容层
 * 处理旧插件在 PHP 8.x 下的常见兼容性问题，防止 TypeError 等导致 500。
 * 此文件由核心框架自动加载，不依赖任何插件。
 */

if (!defined('DEBUG')) return;

/**
 * Preserve PHP 7 count() behavior for legacy plugin Hook fragments.
 * PHP 7 returned 0 for null and 1 for other non-countable values.
 */
if (!function_exists('xn_count_compat')) {
    function xn_count_compat($value, $mode = COUNT_NORMAL) {
        if (is_array($value) || $value instanceof Countable) {
            return count($value, $mode);
        }
        return $value === NULL ? 0 : 1;
    }
}

/**
 * Keep legacy Hook code running when an optional query result is not an array.
 * PHP 8 turns array_column(NULL, ...) into a terminal TypeError; old packages
 * commonly treated that missing result as an empty list and continued.
 */
if (!function_exists('xn_array_column_compat')) {
    function xn_array_column_compat($array, $column_key, $index_key = NULL) {
        if (!is_array($array)) return array();
        return array_column($array, $column_key, $index_key);
    }
}

/**
 * Preserve the common three-argument legacy array_multisort() form without
 * losing its two by-reference arrays. The compiler only targets calls whose
 * first and third arguments are simple variables and the middle argument is
 * an explicit SORT_ASC/SORT_DESC constant. All other call shapes stay native
 * rather than being guessed through a variadic wrapper.
 */
if (!function_exists('xn_array_multisort_compat')) {
    function xn_array_multisort_compat(&$array, $sort_order, &$array2) {
        if (!is_array($array) || !in_array($sort_order, array(SORT_ASC, SORT_DESC), TRUE)) return FALSE;
        if (is_array($array2)) {
            if (count($array) !== count($array2)) return FALSE;
            return array_multisort($array, $sort_order, $array2);
        }
        if (!is_int($array2)) return FALSE;

        $sort_types = array(SORT_REGULAR, SORT_NUMERIC, SORT_STRING, SORT_LOCALE_STRING);
        defined('SORT_NATURAL') AND $sort_types[] = SORT_NATURAL;
        if (defined('SORT_FLAG_CASE')) {
            $sort_types[] = SORT_STRING | SORT_FLAG_CASE;
            defined('SORT_NATURAL') AND $sort_types[] = SORT_NATURAL | SORT_FLAG_CASE;
        }
        if (!in_array($array2, $sort_types, TRUE)) return FALSE;
        return array_multisort($array, $sort_order, $array2);
    }
}

if (!function_exists('xn_php8_compat_is_ajax')) {
    function xn_php8_compat_is_ajax() {
        if (!empty($_SERVER['ajax'])) return TRUE;
        $requested_with = isset($_SERVER['HTTP_X_REQUESTED_WITH']) ? strtolower(trim($_SERVER['HTTP_X_REQUESTED_WITH'])) : '';
        if ($requested_with === 'xmlhttprequest') return TRUE;
        return !empty($_REQUEST['ajax']);
    }
}

/**
 * 注册全局异常处理器，捕获 PHP 8+ 的 TypeError 等异常。
 * 旧插件常见问题：
 *  - header() 传入非 string 参数 → TypeError
 *  - 对 null 进行数组访问 → TypeError
 *  - implode() 参数顺序错误 → TypeError
 *  - count() 传入非 Countable → TypeError
 *  - array_column() 的数据源为 null → TypeError
 *  - array_multisort() 的关联数据源为 null → TypeError
 */
$_php8_compat_prev_handler = set_exception_handler(function ($e) {
    global $_php8_compat_prev_handler;

    // 只处理 TypeError（PHP 8+ 类型严格化产生的）
    if ($e instanceof TypeError) {
        $msg = $e->getMessage();
        $file = $e->getFile();
        $line = $e->getLine();
		if(function_exists('plugin_safe_mode_handle_throwable') && defined('APP_PATH')) {
			$safe_mode_conf = isset($GLOBALS['conf']) && is_array($GLOBALS['conf']) ? $GLOBALS['conf'] : array();
			plugin_safe_mode_handle_throwable($e, $safe_mode_conf, APP_PATH);
		}

        // Exception handlers cannot resume the failed statement. Record the
        // terminal failure and return an explicit response instead of a 200
        // response with an empty body.
        $logMsg = "[PHP8-Compat] TypeError caught: $msg in $file:$line";
        if (function_exists('xn_log')) {
            xn_log($logMsg, 'php8_compat_error');
        } else {
            error_log($logMsg);
        }

        http_response_code(500);
        if (xn_php8_compat_is_ajax()) {
            if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
            $response_message = 'A plugin compatibility error interrupted the request. Check the server error log before retrying.';
            DEBUG AND $response_message .= " $msg in $file:$line";
            $response = array('code'=>-1, 'message'=>$response_message);
            echo function_exists('xn_json_encode') ? xn_json_encode($response) : json_encode($response);
            exit(1);
        }

        if (DEBUG) {
            echo "<fieldset class=\"fieldset small notice\">"
               . "<b>[PHP8-Compat] TypeError terminated the request</b>"
               . "<div>" . htmlspecialchars($msg) . "</div>"
               . "<div>File: " . htmlspecialchars($file) . ", Line: $line</div>"
               . "</fieldset>";
        } else {
            echo 'Internal Server Error';
        }
        exit(1);
    }

    // 非 TypeError 交给上级处理器或默认行为
    if ($_php8_compat_prev_handler) {
        call_user_func($_php8_compat_prev_handler, $e);
    } else {
        // 没有上级处理器，按默认行为抛出
        throw $e;
    }
});

/**
 * 安全的 header() 包装器 —— 供旧插件兼容使用
 * 自动将非 string 参数转换为 string，避免 TypeError
 */
if (!function_exists('safe_header')) {
    function safe_header($header, $replace = true, $http_response_code = 0) {
        if (!is_string($header)) {
            $header = (string) $header;
        }
        if ($http_response_code > 0) {
            header($header, $replace, $http_response_code);
        } else {
            header($header, $replace);
        }
    }
}

/**
 * PHP 8.0: str_contains / str_starts_with / str_ends_with 兼容
 * 部分新插件可能用了 PHP 8 函数，但服务器仍在 PHP 7.x
 */
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool {
        return $needle === '' || strpos($haystack, $needle) !== false;
    }
}
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool {
        return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
    }
}

/**
 * PHP 8.1: array_is_list 兼容
 */
if (!function_exists('array_is_list')) {
    function array_is_list(array $array): bool {
        if ($array === []) return true;
        return array_keys($array) === range(0, count($array) - 1);
    }
}
