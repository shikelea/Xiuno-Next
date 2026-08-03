# Xiuno Next (Xiuno BBS 4.0 Reforged)

![PHP Version](https://img.shields.io/badge/php-8.0%2B-blue)
![License](https://img.shields.io/badge/license-MIT-green)
![Bootstrap](https://img.shields.io/badge/bootstrap-5.0-purple)

> **当前状态：可用性仍不佳，不推荐直接用于生产环境。** 项目正在持续开发与兼容性验证，请先在测试环境部署，做好备份并自行评估风险。
>
> **欢迎参与贡献：** 欢迎提交 Issue、复现步骤、测试结果、文档改进和 Pull Request，一起完善 Xiuno Next。

> **"不破不立，在保持轻量的基础上拥抱未来。"**

## 🚀 项目介绍

**Xiuno Next** 是对经典论坛引擎 Xiuno BBS 4.0 的现代化重构版本。我们致力于在保留其**极速、轻量、高并发**核心优势的同时，引入现代 PHP 生态和开发标准，使其能够稳定运行在 PHP 8.0+ 环境中。

### ✨ 核心特性

- **⚡️ 极速响应**：继承 Xiuno 的高性能基因，基于过程式编程和静态编译 Hook，性能远超同类产品。
- **�️ 插件容错**：插件错误隔离 + 安全模式，单个插件出错不会导致整站白屏，管理员可快速恢复。
- **�🐘 PHP 8 兼容**：完全修复了在 PHP 8.0+ 下的 Fatal Errors，并为旧插件提供了兼容层。
- **🎨 现代 UI**：默认主题全面升级至 Bootstrap 5，移动端优先设计，体验更佳。
- **🐳 Docker Ready**：内置标准的 Docker 开发环境，一键启动，开箱即用。
- **🔌 RESTful API**：内置标准 API 接口，支持前后端分离开发。

## 📦 快速开始

### 方式一：Docker 启动（推荐）

无需配置 PHP 环境，只需安装 Docker。

1. **克隆项目**
   ```bash
   git clone https://github.com/shikelea/Xiuno-Next.git
   cd Xiuno-Next
   ```

2. **启动服务**
   ```bash
   docker-compose up -d
   ```

3. **开始安装**
   访问 `http://localhost:8080`，进入安装向导。
   - 数据库主机：`db`
   - 数据库名：`xiunobbs`
   - 用户名：`xiuno`
   - 密码：`xiuno_password_changeme`
   - 生产环境安装完成后请删除或在 Web 服务器层封禁 `install/`。

### 方式二：传统部署

1. 确保服务器环境满足：PHP 8.0+ (需安装 PDO_MySQL, GD, Zip 扩展), MySQL 5.7+ / 8.0+.
2. 将代码上传至 Web 目录。
3. 设置 `conf/`, `log/`, `tmp/`, `upload/` 目录为可写权限 (777)。
4. 访问网站首页进行安装。
5. 生产环境安装完成后删除或在 Nginx/Apache 中封禁 `install/`，避免配置文件异常时重新开放安装入口。

## 🛡️ 安全特性 (Security)

- **🔐 密码安全**：渐进式密码哈希迁移（MD5+salt → bcrypt），用户登录时自动升级，无需重置密码。
- **🛡️ CSRF 防护**：全局 CSRF Token 机制，自动保护所有表单提交和 AJAX 请求。
- **🔍 XSS 防护**：全面审计模板输出，确保用户输入经过 `htmlspecialchars` 处理。
- **🧱 安装器防护**：安装完成后以 `conf/conf.php` 作为硬阻断，防止历史重装类漏洞回归。
- **💉 SQL 注入防护**：修复参数拼接，强制类型转换和参数化查询。
- **🚦 安全响应头**：`X-Content-Type-Options`、`X-Frame-Options`、`Referrer-Policy` 等标准安全头。
- **📊 数据库迁移系统**：基于版本号的轻量 Migration 机制，安全升级数据库结构。

欢迎社区提交安全相关的 PR 或报告漏洞。

## 🗺️ 路线图 (Roadmap)

- [x] **v4.0.5 (Reborn)**: 修复 PHP 8 兼容性，移除过时函数，Docker 化。
- [x] **v4.1.0 (Standard)**: 引入 Composer，规范化依赖管理。
- [x] **v4.2.0 (API)**: 提供 RESTful API，支持前后端分离 (已实现登录、帖子列表、发帖)。
- [x] **v4.3.0 (Experience)**: 重构默认主题 (Bootstrap 5)，修复后台样式，CLI 脚手架，建立核心场景性能基准，完善 SEO 基础。
- [x] **v4.3.1 (Audit)**: 代码审查修复：API 响应结构规范化、安全模式路径加固、BS4 残留清理、CLI 脚手架修复。
- [x] **v4.4.0 (Security)**: 安全加固第一批：数据库迁移系统、密码哈希迁移 (MD5→bcrypt)、安全响应头。
- [x] **v4.4.1 (Security)**: 安全加固第二批：CSRF 防护、XSS 修复、SQL 注入加固、旧版一键升级工具。
- [x] **v4.4.2 (Hardening)**: BS4→BS5 兼容垫片、Token 加固、参数注入修复、后台安全面板、安装/API/退出修复。
- [x] **v4.4.3 (Performance & Compat)**: 插件页性能优化、CSRF 主题兼容、管理操作修复、后台一键在线更新（含 GitHub 加速代理）、BS4→BS5 全面兼容层。
- [x] **v4.4.4 (Stability)**: 在线更新 ZIP 校验加固、版本号管理修复。
- [x] **v4.4.5 (Compat Layer)**: 四层兼容层体系：通用注入器（`ob_start` 自动向所有主题注入 CSRF token + bs4-compat）、PHP 8+ 运行时兼容、BS4→BS5 CSS/JS 全面兼容（`input-group-prepend/append`、`custom-file`、`modal/tooltip/popover` API 代理、CSRF 全局保护）、核心主题 API。
- [x] **v4.5.0 (Modernization, 主线完成 / 发行版)**: 轻量现代化主线已完成：轻量 Helper、前端资源审计、HTMX 只读分页试点、CLI/CI 最小闭环、前端安全守卫和生态样本兼容审计结论。API 扩展、发布包签名和兼容矩阵沉淀继续作为 v4.5.x / 阶段六前置项推进。
- [ ] **v4.5.1 (Hardening, 发布候选)**: 修复 GitHub #6 用户名登录回归、Docker 安装入口、密码修改校验和在线更新代理信任边界；清理版本漂移、CI 守卫和仓库文档边界。
- [ ] **v5.0.0 (Next)**: 全新的插件市场和主题引擎。

## 💻 命令行工具 (CLI)

本项目内置了 `xiuno` 命令行工具，用于辅助开发和运维。

**使用方法**:

```bash
# 确保已安装依赖
composer install

# 查看所有可用命令
php bin/xiuno list

# 创建新插件
php bin/xiuno make:plugin <plugin_name>

# 生成本地 Hook 点索引（输出到已忽略的 docs/）
php bin/generate_hook_docs.php

# 执行数据库迁移
php bin/xiuno migrate

# 从旧版 Xiuno BBS 升级到 Xiuno Next
php bin/xiuno upgrade
```

### 升级指南 (从 4.0.x 升级)

支持从 Xiuno BBS 4.0.4 / 4.0.5 / 4.0.7 等主流版本一键升级到 Xiuno Next。

```bash
# 1. 备份！备份数据库和所有文件
mysqldump -u root -p your_db > backup.sql
cp -r /path/to/xiuno /path/to/xiuno_backup

# 2. 将 Xiuno Next 代码覆盖到站点目录（保留 conf/conf.php 和 upload/ 目录）

# 3. 安装 Composer 依赖
composer install

# 4. 运行升级工具
php bin/xiuno upgrade
```

升级工具会自动完成以下操作：
- **版本检测**：识别当前安装的旧版版本号
- **升级预检报告**：列出所有待执行的变更（配置补全、数据库迁移等），确认后再执行
- **配置迁移**：自动添加旧版缺失的配置项（如 `csrf_on`、`disabled_plugin` 等）
- **数据库迁移**：扩展 `password` 字段至 `varchar(255)` 以支持 bcrypt 哈希
- **密码渐进升级**：用户下次登录时，密码自动从 MD5+salt 升级为 bcrypt，无需重置
- **缓存清理**：清理编译缓存、插件 Hook 缓存和安全模式标记

## 性能测试 (Benchmark)

项目内置了性能压测脚本，用于采集基线数据和检测性能退化。

```bash
# Linux（需要 Apache Benchmark）
sudo apt install apache2-utils   # Ubuntu/Debian
sudo yum install httpd-tools     # CentOS/RHEL

chmod +x bin/benchmark.sh
bash bin/benchmark.sh http://你的域名或IP/

# Windows
bin\benchmark.bat
```

脚本会自动压测 3 个核心页面（首页、帖子列表、帖子详情），输出 QPS 和 TTFB 汇总，结果保存在 `tmp/bench_*.txt`。

当前 4H8G / PHP 8.2 / MySQL 8.0 本机基线为 220+ QPS，核心页面 TTFB 不高于 220ms；影响全局路由或渲染的改动应将退化控制在 15% 以内。

## API 文档

本项目提供 RESTful API，实现位于 `route/api/`；统一返回 `{code, message, data}`，并支持 token 鉴权与标准分页参数。

## 插件体系状态

当前已经可以开发传统兼容插件，使用 `php bin/xiuno make:plugin <plugin_name>` 生成基础结构。Xiuno Next 原生插件规范仍处于预览前准备阶段，v4.5.x 优先固定 `plugin.json` 草案、Hook 索引和可重复的插件/主题 smoke test。

## 开发者资料

`docs/` 仅用于维护者本地的审计、基线和生成索引，不纳入 Git。对外稳定契约以 `README.md`、`CONTRIBUTING.md`、CLI 帮助和代码内容为准。

## 🤝 参与贡献

Xiuno Next 是一个社区驱动的项目，我们需要你的帮助！无论是提交 Bug、修复代码还是完善文档，都非常欢迎。

## 📄 许可证

本项目遵循 [MIT License](LICENSE.txt)。基于 Xiuno BBS 4.0 二次开发。
