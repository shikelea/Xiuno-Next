# Xiuno BBS 复兴计划书 (Project Revival Plan)

## 1. 愿景 (Vision)
打造一个**轻量、极速、现代化**的 PHP 论坛引擎，继承 Xiuno 的高性能基因，融入现代 PHP 生态，重振开发者社区。

## 2. 现状分析 (Analysis)
- **核心优势**：
  - **极速响应**：基于过程式编程和静态编译 Hook，性能远超同类产品。
  - **轻量级**：核心代码精简，依赖少。
  - **插件机制**：通过文件替换实现的 AOP 机制，极为灵活。
- **当前痛点**：
  - **技术栈老旧**：原生不支持 PHP 8.0+，缺乏 Composer、Docker 等现代工具链。
  - **开发体验差**：全局变量满天飞，缺乏单元测试，IDE 提示不友好。
  - **生态断层**：官方停更，社区分裂，插件质量参差不齐。

## 3. 行动路线图 (Roadmap)

### 阶段一：抢救与兼容 (Survival Phase) - [完成]
**目标**：让 Xiuno 在 PHP 8.0+ 环境下稳定运行，且不破坏旧插件兼容性。
- [x] **修复 Fatal Errors**：移除 `get_magic_quotes_gpc` 等已删除函数。
- [x] **Polyfill 补丁**：注入 `each()` 等函数，保证旧插件无需修改即可运行。
- [x] **全量扫描**：检查 `{}` 数组访问、`create_function` 等废弃特性。
- [x] **数据库层适配**：确保 PDO 连接参数符合 PHP 8 标准（默认错误模式变化）。

### 阶段二：规范与工程化 (Standardization Phase)
**目标**：引入现代开发标准，降低贡献门槛。
- [x] **引入 Composer**：
  - 创建 `composer.json`，用于自动加载和依赖管理。
  - 逐步用 Composer 包替代 `xiunophp/` 下的手动 `include` 库（如 PHPMailer）。
- [x] **Docker 化**：
  - 提供标准的 `docker-compose.yml`，一键启动 PHP 8.2 + MySQL 8.0 开发环境。
- [x] **代码风格统一**：
  - 引入 `PHP-CS-Fixer`，遵循 PSR-12 标准（逐步迁移，避免破坏 Hook 点）。

### 阶段三：体验升级 (Experience Phase)
**目标**：提升用户和开发者的使用体验。
- [x] **CLI 脚手架**：
  - 开发 `xiuno-cli` 工具，支持 `php xiuno make:plugin` 快速创建插件结构。
- [x] **API 化改造**：
  - [x] 新增 `route/api/` 目录和基础路由。
  - [x] 实现核心 API (用户登录)。
  - [x] 实现帖子列表 API (列表与详情)。
  - [x] 实现发帖与回帖 API。
- [x] **默认主题重构**：
  - 移除 Bootstrap 3/4 混用代码，升级至 Bootstrap 5，实现真正的移动端优先。
  - 重构核心模板 (`header`, `footer`, `thread_list`, `post_list`) 以适配 BS5 语法 (Flexbox, Utility Classes)。

### 阶段四：生态重建 (Ecosystem Phase)
**目标**：激活社区，建立良性循环。
- [ ] **插件/主题市场规范**：
  - 制定标准化的 `plugin.json` 元数据规范。
  - 建立官方插件索引源 (Registry)，支持 CLI 安装 (`php xiuno plugin:install`).
- [ ] **文档中心重构**：
  - 使用 VitePress 搭建全新的文档站点。
  - 自动扫描代码生成 Hook 点列表，解决开发者查阅难的问题。
- [ ] **自动化测试 (CI/CD)**：
  - 引入 GitHub Actions，对核心功能进行自动化测试，确保 PR 质量。

### 阶段五：技术栈现代化 (Modernization Phase)
**目标**：在保持兼容性的前提下，逐步引入现代技术栈。

**⚠️ 兼容性原则**：v4.x 系列将严格保持对旧插件的 API 兼容性。所有核心函数 (`xn_*`, `db_*`) 的签名保持不变。

- [ ] **后端架构升级**：
  - **日志系统**：引入 Monolog，替换现有的文件写入方式（保持 `xn_log()` 接口不变，内部适配）。
  - **HTTP 客户端**：引入 Guzzle，替换 `xn_http_request()` 的底层实现。
  - **依赖注入**：探索引入轻量级 DI 容器，逐步解耦全局变量 `$conf`, `$db`。
- [ ] **前端渐进式增强**：
  - **保留 jQuery**：作为底层依赖，确保现有插件可用。
  - **引入 Alpine.js**：作为推荐的新开发模式，用于替代复杂的 jQuery DOM 操作，实现更声明式的 UI 交互。
  - **构建工具**：引入 Vite 处理 CSS/JS 资源的打包与压缩。

## 4. 立即执行项 (Action Items)

1.  **发布 v4.3.0**：包含 Bootstrap 5 主题和 API 功能。 - [完成]
2.  **完善 API 文档**：在 README 中补充详细的 API 调用示例。 - [完成]
3.  **启动插件市场规划**：设计 `plugin.json` 规范。

---
*“不破不立，在保持轻量的基础上拥抱未来。”*
