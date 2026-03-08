# Xiuno Next (Xiuno BBS 4.0 Reforged)

![PHP Version](https://img.shields.io/badge/php-8.0%2B-blue)
![License](https://img.shields.io/badge/license-MIT-green)
![Bootstrap](https://img.shields.io/badge/bootstrap-5.0-purple)

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
   git clone https://github.com/your-username/xiuno-next.git
   cd xiuno-next
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

### 方式二：传统部署

1. 确保服务器环境满足：PHP 8.0+ (需安装 PDO_MySQL, GD, Zip 扩展), MySQL 5.7+ / 8.0+.
2. 将代码上传至 Web 目录。
3. 设置 `conf/`, `log/`, `tmp/`, `upload/` 目录为可写权限 (777)。
4. 访问网站首页进行安装。

## 🛡️ 安全特性 (Security)

- **🔐 密码安全**：渐进式密码哈希迁移（MD5+salt → bcrypt），用户登录时自动升级，无需重置密码。
- **🛡️ CSRF 防护**：全局 CSRF Token 机制，自动保护所有表单提交和 AJAX 请求。
- **🔍 XSS 防护**：全面审计模板输出，确保用户输入经过 `htmlspecialchars` 处理。
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
- [x] **v4.4.5 (Compat Layer)**: 三层兼容层体系：PHP 8+ 运行时兼容、CSS/JS 资源降级、核心主题 API（`message()` HTMX 原生支持、`url()` 扩展机制、主题注册/资源/能力 API）。详见 [docs/compat-layer.md](docs/compat-layer.md)。
- [ ] **v4.5.0 (Modernization)**: 轻量现代化：HTMX 渐进增强、API 扩展、CLI 工具完善。
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

## � 性能测试 (Benchmark)

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

详细基线数据见 [docs/performance_baseline.md](docs/performance_baseline.md)。

## �🔌 API 文档

本项目提供了一套标准的 RESTful API，方便开发移动端或单页应用。

**基础 URL**: `http://your-domain.com/?api-{controller}-{action}` (伪静态) 或 `http://your-domain.com/?route=api/{controller}/{action}`

**可用接口**:

*   **用户 (User)**
    *   `POST /api/user/login`: 用户登录 (参数: `email`, `password`)
    *   `GET /api/user/read`: 获取用户信息 (参数: `uid` 或 `token`)
*   **帖子 (Thread)**
    *   `GET /api/thread/list`: 获取帖子列表 (参数: `fid`, `page`)
    *   `GET /api/thread/read`: 获取帖子详情及回复 (参数: `tid`, `page`)
    *   `POST /api/thread/create`: 发布新帖 (参数: `fid`, `subject`, `message`, `doctype`)
*   **回复 (Post)**
    *   `POST /api/post/create`: 发布回复 (参数: `tid`, `message`, `doctype`)

更多详情请参考 [route/api/](route/api/) 目录下的源码。

## 🤝 参与贡献

Xiuno Next 是一个社区驱动的项目，我们需要你的帮助！无论是提交 Bug、修复代码还是完善文档，都非常欢迎。

请查看 [PLAN.md](PLAN.md) 了解我们的详细复兴计划。

## 📄 许可证

本项目遵循 [MIT License](LICENSE)。基于 Xiuno BBS 4.0 二次开发。