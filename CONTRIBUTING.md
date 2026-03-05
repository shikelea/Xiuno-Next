# 贡献指南 (Contributing Guide)

感谢你对 **Xiuno Next** 项目感兴趣！这是一个社区驱动的复兴项目，我们非常欢迎任何形式的贡献，包括提交 Bug、修复代码、完善文档或提出新功能建议。

在参与贡献之前，请花一点时间阅读以下指南。

## 🛠️ 开发环境搭建

你可以选择使用 Docker 快速启动（推荐），也可以手动配置本地环境。

### 方式一：Docker 启动（推荐）

这是最简单的方式，无需在本地安装 PHP 和 MySQL。

1.  **安装 Docker**：确保本地已安装 Docker 和 Docker Compose。
2.  **启动服务**：
    ```bash
    docker-compose up -d
    ```
3.  **安装依赖**：
    容器启动后，需要安装 Composer 依赖：
    ```bash
    docker-compose exec app composer install
    ```
4.  **访问站点**：
    打开浏览器访问 `http://localhost:8080` 进入安装向导。
    *   数据库主机：`db`
    *   数据库名：`xiunobbs`
    *   用户名：`xiuno`
    *   密码：`xiuno_password_changeme`

### 方式二：手动搭建 (Manual Setup)

如果你更习惯使用 XAMPP、宝塔或原生环境，请确保满足以下要求：

*   **PHP 版本**：>= 8.0
*   **PHP 扩展**：`pdo_mysql`, `gd`, `mbstring`, `zip`, `openssl`
*   **MySQL**：>= 5.7
*   **Composer**：必须安装 [Composer](https://getcomposer.org/)

**步骤**：

1.  克隆代码到 Web 目录。
2.  在项目根目录运行 `composer install` 安装依赖。
3.  确保 `conf/`, `log/`, `tmp/`, `upload/` 目录具有可写权限。
4.  访问站点完成安装。

---

## 📦 依赖管理

本项目使用 **Composer** 管理第三方库。

*   **安装依赖**：`composer install`
*   **添加新库**：`composer require vendor/package`
*   **注意**：请勿直接修改 `xiunophp/` 下的核心库文件，尽量通过 Composer 引入替代品。

---

## 📝 编码规范

为了保持代码库的整洁和现代化，请遵守以下规则：

1.  **PHP 8 兼容性**：
    *   严禁使用已废弃的函数（如 `get_magic_quotes_gpc`, `each`, `create_function`）。
    *   注意 PHP 8 的弱类型比较变化和未定义数组键名的警告。
2.  **代码风格**：
    *   尽量遵循 **PSR-12** 编码规范。
    *   保持现有的缩进风格（Tab 缩进）。
    *   *(未来我们将引入 PHP-CS-Fixer 自动格式化)*
3.  **安全性**：
    *   所有 SQL 操作必须使用预处理语句（PDO Prepared Statements）或框架提供的安全封装。
    *   输出到 HTML 时必须进行转义（XSS 防护）。
4.  **性能基线要求**：
    *   我们在 `docs/performance_baseline.md` 维护了各个核心页面的基础性能指标。
    *   提交影响全局渲染或路由的新代码前，建议运行 `bin/benchmark.bat` 进行压测比对，保证 TTFB 退化不超过 15%。

---

## 🔌 API 开发指南

我们使用 RESTful 风格的 API，代码位于 `route/api/` 目录下。

*   **响应格式**: 所有 API 统一返回 JSON 格式：
    ```json
    {
        "code": 0,          // 0: 成功, <0: 错误
        "message": "OK",    // 提示信息
        "data": {}          // 数据载荷
    }
    ```
*   **新增接口**:
    1.  在 `route/api/` 下创建或修改对应的控制器文件（如 `user.php`）。
    2.  使用 `param()` 获取参数。
    3.  使用 `api_output($code, $message, $data)` 输出结果。

## 💻 CLI 工具开发

CLI 工具基于 Symfony Console 组件。

*   **入口文件**: `bin/xiuno`
*   **命令位置**: `src/Console/Command/`
*   **新增命令**:
    1.  继承 `Symfony\Component\Console\Command\Command` 类。
    2.  在 `bin/xiuno` 中注册新命令。

## 💾 Git 提交规范

我们推荐使用 **Conventional Commits** 规范，并**建议使用中文**描述。

格式：`<类型>(<范围>): <描述>`

**示例**：
*   `feat(user): 新增用户手机号注册功能`
*   `fix(install): 修复安装向导在 PHP 8.1 下的报错`
*   `docs(readme): 更新安装说明`
*   `refactor(mail): 重构邮件发送模块`
*   `chore(deps): 升级 phpmailer 版本`

**类型说明**：
*   `feat`: 新功能
*   `fix`: 修复 Bug
*   `docs`: 文档变更
*   `style`: 代码格式调整（不影响逻辑）
*   `refactor`: 代码重构
*   `perf`: 性能优化
*   `test`: 测试相关
*   `chore`: 构建过程或辅助工具变动

---

## 🤝 提交 Pull Request

1.  Fork 本仓库。
2.  基于 `main` 分支创建一个新分支：`git checkout -b my-new-feature`。
3.  提交你的更改。
4.  确保本地测试通过。
5.  提交 PR 到 `main` 分支，并描述你的改动内容。

再次感谢你的贡献！让我们一起让 Xiuno 重获新生！🚀
