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

## 🗺️ 路线图 (Roadmap)

- [x] **v4.0.5 (Reborn)**: 修复 PHP 8 兼容性，移除过时函数，Docker 化。
- [x] **v4.1.0 (Standard)**: 引入 Composer，规范化依赖管理。
- [ ] **v4.2.0 (API)**: 提供 RESTful API，支持前后端分离 (进行中)。
- [ ] **v5.0.0 (Next)**: 全新的插件市场和主题引擎。

## 🤝 参与贡献

Xiuno Next 是一个社区驱动的项目，我们需要你的帮助！无论是提交 Bug、修复代码还是完善文档，都非常欢迎。

请查看 [PLAN.md](PLAN.md) 了解我们的详细复兴计划。

## 📄 许可证

本项目遵循 [MIT License](LICENSE)。基于 Xiuno BBS 4.0 二次开发。
