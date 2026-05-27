# Xiuno Next 开发手册校注

这个目录收录的是社区老师整理、扩展过的 Xiuno BBS 原版开发资料。它很有价值，尤其适合理解原版的数据结构、全局函数、路由依赖链和插件开发习惯。

但这些资料的基准仍然是原版 Xiuno BBS 4.0.x。用于 Xiuno Next 时，需要叠加下面的校注，避免把旧结论当成当前实现。

## 当前结论

- 原始手册应作为“历史资料 + 迁移参考”，不要直接作为 Xiuno Next 的权威文档发布。
- Xiuno Next 的运行下限以 `composer.json` 为准：PHP 8.0+。Docker 开发环境当前使用 PHP 8.2，CI 覆盖 PHP 8.0、8.2、8.3、8.4、8.5。
- 原始手册中“Xiuno BBS 不支持 Docker”的说法只适用于旧版。Xiuno Next 已提供 `docker-compose.yml` 和 `docker/php/Dockerfile`。
- 原始数据库手册里的 `bbs_user.password char(32)`、MD5+salt 说明只适用于旧版。Xiuno Next 新安装已使用 `varchar(255)`，并通过 `password_hash()` / `password_verify()` 支持 bcrypt。
- `salt` 字段仍保留，主要用于旧数据渐进迁移和旧插件兼容，不应作为新密码方案的核心依据。
- 备份/恢复资料仍然可用，但 Xiuno Next 还需要额外强调：升级前备份 `conf/`、`upload/`、数据库和当前代码；后台在线更新已经增加覆盖前备份和最近备份回滚。
- 依赖链文档对理解原版入口很有帮助，但 Xiuno Next 已增加 API、CLI、CSRF、兼容层、在线更新、主题 API 等新路径，后续需要从当前源码重新生成新版依赖图。

## 需要改写或补充的主题

1. 开发环境指南
   - 把 PHP 要求改为 Xiuno Next: PHP 8.0+。
   - 补充 Docker 启动方式和数据库连接信息。
   - 保留 XAMPP/Laragon 等传统环境说明，但标注为可选。

2. 数据库表结构
   - 更新 `bbs_user.password` 为 `varchar(255)`。
   - 增加 bcrypt 渐进迁移说明。
   - 标注旧版 MD5+salt 的安全风险和兼容边界。

3. 备份与恢复
   - 增加 Xiuno Next 在线更新备份目录 `tmp/update_backup_*`。
   - 增加回滚入口和发布包校验的说明。
   - 强调 Composer 依赖和本地 `conf/conf.php` 不应被发布包覆盖。

4. 依赖链与 Hook 文档
   - 以现有资料为底稿，重新扫描当前代码。
   - 补充 `route/api/`、`admin/route/update.php`、`bin/xiuno`、`xiunophp/php8_compat.php`、`view/js/bs4-compat.js`。
   - 区分原版 Hook、Xiuno Next 兼容层注入点、主题 API 三类入口。

## 文档维护原则

- 尽量保留社区资料原文，不在原文里混入大量新版判断。
- Xiuno Next 的差异用校注、勘误和新版索引承载。
- 能从源码自动生成的内容，后续优先用脚本生成，减少手写依赖链过期。
