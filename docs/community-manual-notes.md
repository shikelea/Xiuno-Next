# 社区开发手册参考说明

`开发手册/` 是本地参考资料，不纳入仓库，也不作为 Xiuno Next 正式文档直接发布。

这些资料来自社区对原版 Xiuno BBS 开发手册的整理和扩展，适合理解原版的函数、Hook、数据库结构和路由依赖。但它们的基准仍然是 Xiuno BBS 4.0.x，不能直接代表 Xiuno Next 当前实现。

## 使用原则

- 只把它作为历史资料和迁移参考。
- 不在 `开发手册/` 目录里继续维护项目文档。
- 写给开发者的内容统一放入 `docs/`。
- 能从源码生成的资料优先自动生成，例如 `docs/hooks.md`。
- 正式开发文档设计等到生态重建阶段再启动。

## 当前已知差异

- Xiuno Next 要求 PHP 8.0+，Docker 开发环境当前使用 PHP 8.2。
- Xiuno Next 已支持 Docker，原版手册中“不支持 Docker”的说法不适用于本项目。
- 新安装的 `bbs_user.password` 已是 `varchar(255)`，用于 bcrypt 哈希；旧版 MD5+salt 只作为迁移兼容背景。
- Xiuno Next 已有 CSRF、防白屏兼容层、BS4 到 BS5 兼容层、CLI、API、在线更新和主题 API。
- 原版依赖链资料只能作为理解入口，当前 Hook 索引以 `docs/hooks.md` 为准。
