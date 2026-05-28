# Xiuno Next 插件与主题生态状态草案

本文回答一个现实问题：开发者什么时候可以构建 Xiuno Next 的原生插件？

本文不是正式插件开发手册，只记录当前状态和阶段性判断。正式开发文档设计放到生态重建阶段启动。

主题开发同样是一等生态入口，不作为插件开发的附属章节处理。正式主题开发手册需要等待主题 API、兼容矩阵和资源加载约定稳定后再发布。

## 短答案

现在已经可以开发传统兼容插件。使用 `php bin/xiuno make:plugin <name>` 可以生成 `conf.json`、`hook/`、`install.php`、`unstall.php`、`upgrade.php` 的基础结构。

但“Xiuno Next 原生插件”还不应该对外承诺为稳定标准。建议把它分成三个阶段：

1. 当前阶段：兼容插件可用
   - 面向熟悉原版 Xiuno 的开发者。
   - 使用 `conf.json` 和 Hook 文件。
   - 适合小功能、后台菜单扩展、样式补丁、模型层增强。
   - 需要自行确认 PHP 8、CSRF、BS4 到 BS5 兼容。

2. v4.5.x：原生插件预览
   - 固定 `plugin.json` 草案。
   - 明确 PHP 版本、Xiuno Next 最低版本、能力声明、依赖声明。
   - 提供 Hook 索引、兼容清单和 CLI smoke test。
   - 推荐给愿意跟随规范变化的早期插件作者。

3. v5.0：原生插件稳定
   - 插件市场或插件索引源可用。
   - `plugin.json`、主题 API、编辑器 API、兼容测试清单稳定。
   - 插件安装、启用、禁用、升级、依赖检查都有命令行和后台闭环。

## 当前可依赖的能力

- PHP 8.0+ 运行线。
- 旧插件 Hook 机制仍保留。
- 插件错误隔离和安全模式降低白屏风险。
- BS4 到 BS5 CSS/JS 兼容层覆盖常见旧插件写法。
- CSRF token 可通过通用注入器和兼容层自动补齐常见表单/AJAX 请求。
- 主题 API 已提供 `theme_register()`、`theme_has()`、`theme_enqueue_style()`、`theme_enqueue_script()`。
- Hook 点索引可通过 `php bin/generate_hook_docs.php` 生成，输出到 `docs/hooks.md`。

## 目前不建议承诺稳定的部分

- `plugin.json` 尚未成为运行时读取标准，当前后台仍以 `conf.json` 为主。
- 官方插件索引源还未恢复。
- 插件依赖解析只覆盖旧结构，缺少语义化版本范围和能力检查。
- 富文本编辑器 API 尚未标准化。
- API 插件、主题插件、后台插件之间还没有统一的能力声明格式。

## 给开发者的建议

- 如果目标是近期可用，先写 `conf.json` 兼容插件。
- 如果目标是长期维护，目录结构可以提前预留 `plugin.json`，但必须同时保留 `conf.json`。
- 新插件最低按 PHP 8.0 编写，避免使用只在 PHP 8.1+ 才有的语法，除非插件明确声明更高要求。
- 表单 POST 和 AJAX 请求要考虑 CSRF；能走核心 helper 就不要手写 token。
- 前端优先使用 Bootstrap 5 语法；只有为了兼容旧主题或旧插件时才依赖兼容层。
- 涉及用户密码时只调用核心密码 helper，不直接读写 MD5+salt 逻辑。

## 下一步

- 固定 `plugin.json` 字段草案。
- 让 `make:plugin` 支持生成 Next 原生插件模板。
- 固定主题 API 草案，明确 `theme_register()`、资源入队、HTMX 能力声明和模板覆盖边界。
- 在 CI 中增加插件/主题样本的轻量 smoke test。
- 从 `docs/hooks.md` 选出最常用 Hook，整理成开发者友好的插件/主题入门资料。
