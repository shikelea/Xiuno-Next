# Xiuno Next 日志草案

本文不是正式开发手册，只记录阶段五的轻量日志约定。

旧代码继续使用 `xn_log($message, $file)`。新代码可以逐步使用 `XiunoLogger` 的 PSR-3 风格入口：

```php
XiunoLogger::info('Plugin {plugin} enabled', array('plugin' => $dir), 'plugin');
XiunoLogger::error('Update failed: {reason}', array('reason' => $message), 'update');
```

## 约定

- 日志仍写入 `$conf['log_path']` 下的按月目录，例如 `log/202605/error.php`。
- `xn_log()` 的参数和生产模式过滤规则保持不变，避免影响旧插件。
- `XiunoLogger` 支持 `emergency`、`alert`、`critical`、`error`、`warning`、`notice`、`info`、`debug` 方法。
- 生产模式下，`XiunoLogger::log()` 只写入 `error` 及以上级别，避免普通信息日志膨胀。
- 消息上下文使用 `{key}` 占位符替换，只替换标量或可转字符串对象。
- 阶段五不引入 Monolog，也不要求额外 Composer 依赖。
