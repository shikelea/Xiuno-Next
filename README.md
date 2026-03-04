# Xiuno Next (Xiuno BBS 4.0 Reforged)

![PHP Version](https://img.shields.io/badge/php-8.0%2B-blue)
![License](https://img.shields.io/badge/license-MIT-green)

> **"不破不立，在保持轻量的基础上拥抱未来。"**

## 🚀 项目介绍

**Xiuno Next** 是对经典论坛引擎 Xiuno BBS 4.0 的现代化重构版本。我们致力于在保留其**极速、轻量、高并发**核心优势的同时，引入现代 PHP 生态和开发标准，使其能够稳定运行在 PHP 8.0+ 环境中。

### ✨ 核心特性

- **⚡️ 极速响应**：继承 Xiuno 的高性能基因，基于过程式编程和静态编译 Hook，性能远超同类产品。
- **🐘 PHP 8 兼容**：完全修复了在 PHP 8.0+ 下的 Fatal Errors，并为旧插件提供了兼容层。
- **🐳 Docker Ready**：内置标准的 Docker 开发环境，一键启动，开箱即用。
- **🛠️ 现代化改造**：(进行中) 逐步引入 Composer、PSR 标准和 CLI 工具链。

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

1. 确保服务器环境满足：PHP 8.0+ (需安装 PDO_MySQL, GD, Zip 扩展), MySQL 5.7+.
2. 将代码上传至 Web 目录。
3. 设置 `conf/`, `log/`, `tmp/`, `upload/` 目录为可写权限 (777)。
4. 访问网站首页进行安装。

## 🛡️ 安全审计 (Security Audit)

本项目定期进行代码审查和安全评估。

我们正在逐步改进旧有的安全机制（如 MD5 哈希、SQL 注入防护），欢迎社区提交 PR 协助修复。

## 🗺️ 路线图 (Roadmap)

- [x] **v4.0.5 (Reborn)**: 修复 PHP 8 兼容性，移除过时函数，Docker 化。
- [x] **v4.1.0 (Standard)**: 引入 Composer，规范化依赖管理。
- [x] **v4.2.0 (API)**: 提供 RESTful API，支持前后端分离 (已实现登录、帖子列表、发帖)。
- [ ] **v4.3.0 (Experience)**: 重构默认主题，CLI 脚手架。
- [ ] **v5.0.0 (Next)**: 全新的插件市场和主题引擎。

## 💻 命令行工具 (CLI)

本项目内置了 `xiuno` 命令行工具，用于辅助开发。

**使用方法**:

```bash
# 确保已安装依赖
composer install

# 查看帮助
php bin/xiuno list

# 创建新插件
php bin/xiuno make:plugin <plugin_name>
```

## 🔌 API 文档

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
