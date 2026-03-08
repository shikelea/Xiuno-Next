<?php
/**
 * PHP 8+ 兼容层
 * 处理旧插件在 PHP 8.x 下的常见兼容性问题，防止 TypeError 等导致 500。
 * 此文件由核心框架自动加载，不依赖任何插件。
 */

if (!defined('DEBUG')) return;

/**
 * 注册全局异常处理器，捕获 PHP 8+ 的 TypeError 等异常。
 * 旧插件常见问题：
 *  - header() 传入非 string 参数 → TypeError
 *  - 对 null 进行数组访问 → TypeError
 *  - implode() 参数顺序错误 → TypeError
 *  - count() 传入非 Countable → TypeError
 */
$_php8_compat_prev_handler = set_exception_handler(function ($e) {
    global $_php8_compat_prev_handler;

    // 只处理 TypeError（PHP 8+ 类型严格化产生的）
    if ($e instanceof TypeError) {
        $msg = $e->getMessage();
        $file = $e->getFile();
        $line = $e->getLine();

        // 记录日志，不中断执行
        $logMsg = "[PHP8-Compat] TypeError caught: $msg in $file:$line";
        xn_log($logMsg, 'php8_compat');

        if (DEBUG) {
            // DEBUG 模式下输出警告而非致命错误
            echo "<fieldset class=\"fieldset small notice\">"
               . "<b>[PHP8-Compat] TypeError (degraded to warning)</b>"
               . "<div>" . htmlspecialchars($msg) . "</div>"
               . "<div>File: " . htmlspecialchars($file) . ", Line: $line</div>"
               . "</fieldset>";
        }

        // 输出警告信息后脚本终止，但不会产生空白 500 页面
        return;
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
