# Xiuno Next 配置读取草案

本文不是正式开发手册，只记录阶段五的轻量配置读取约定。

Xiuno Next 仍以 `$conf` 数组作为配置事实来源。为了让新代码减少裸全局数组访问，核心提供了两个轻量入口：

```php
$version = conf_get('version', '0.0.0');
$dbType = XiunoConfig::get('db.type', 'pdo_mysql');
```

## 约定

- 旧代码可以继续读取 `$conf['key']`，阶段五不做大面积替换。
- 新代码优先使用 `conf_get()` 或 `XiunoConfig::get()`。
- 点号路径用于读取嵌套数组，例如 `db.type`。
- 如果全局 `Config` 类名未被插件占用，核心会提供 `Config` 到 `XiunoConfig` 的别名。
- `conf_get()` / `conf_has()` 会先检测同名函数是否已存在，尽量避免和历史扩展撞名。
- 不在阶段五引入复杂配置容器，仍保持零构建、低依赖、过程式友好。
