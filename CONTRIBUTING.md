# 贡献指南 (Contributing Guide)

感谢你对 **Xiuno Next** 项目感兴趣！这是一个社区驱动的复兴项目，我们非常欢迎任何形式的贡献，包括提交 Bug、修复代码、完善文档或提出新功能建议。

在参与贡献之前，请花一点时间阅读以下指南。

## 🛠️ 开发环境搭建

你可以选择使用 Docker 快速启动（推荐），也可以手动配置本地环境。

### 方式一：Docker 启动（推荐）

这是最简单的方式，无需在本地安装 PHP 和 MySQL。

1.  **安装 Docker**：确保本地已安装 Docker 和 Docker Compose。
2.  **准备 Linux bind mount 运行目录**（Docker Desktop for Windows/macOS 通常可跳过）：
    ```bash
    mkdir -p conf log tmp upload
    ```
    只使用匹配容器 PHP worker 的宿主 UID/GID 或 ACL 授权这四个目录；不要对整个项目使用 `0777`/Everyone。需要确认容器身份时运行 `docker compose exec app id www-data`。
3.  **启动服务**：
    ```bash
    docker compose up -d
    ```
4.  **安装依赖**：
    容器通过独立的可写 `vendor-data` 卷保存 Composer 依赖，源码挂载仍保持只读：
    ```bash
    docker compose exec app composer install
    ```
5.  **访问站点**：
    打开浏览器访问 `http://localhost:8080` 进入安装向导。
    *   数据库主机：`db`
    *   数据库名：`xiunobbs`
    *   用户名：`xiuno`
    *   密码：`xiuno_password_changeme`
    *   Compose 会让安装器预填前三个非敏感连接项；数据库密码必须手动输入，不会从环境变量回显到安装页面。
    *   标准 Compose 的 `plugin/` 为只读不可变目录；涉及第三方包安装、启停、卸载或升级的兼容测试，必须使用有快照和目录摘要校验的可写隔离副本。

提交涉及安装器、认证、Session 或 Docker Nginx 的改动前，应在**未安装的干净工作树**运行完整容器回归：

```bash
bash bin/check_docker_http_smoke.sh
```

该脚本会创建并销毁独立 MySQL 数据卷，覆盖 Nginx 配置与敏感路径、Web 安装、用户名登录、退出再登录、空密码拒绝、修改密码和新密码重登。检测到现有 `conf/conf.php` 或安装锁时会拒绝运行，避免破坏本地站点。

### 方式二：手动搭建 (Manual Setup)

如果你更习惯使用 XAMPP、宝塔或原生环境，请确保满足以下要求：

*   **PHP 版本**：>= 8.0.2
*   **PHP 扩展**：`json`, `openssl`, `pdo`, `pdo_mysql`, `mbstring`, `gd`, `zip`
*   **MySQL**：>= 5.7
*   **Composer**：必须安装 [Composer](https://getcomposer.org/)

**步骤**：

1.  克隆代码到 Web 目录。
2.  在项目根目录运行 `composer install` 安装依赖。
3.  确保 `conf/`, `log/`, `tmp/`, `upload/` 目录具有可写权限。
4.  访问站点完成安装。

写权限必须属于实际 Web/PHP 进程且限制在上述运行目录；核心源码与 `plugin/` 默认只读。生产服务器应在 Web 层阻断 `conf/`、`log/`、`tmp/`、`data/`、`bin/`、`install/`，并优先把 `tmp_path`、`log_path` 配到文档根之外。

本地 PHP 已满足扩展要求时，可在仓库根目录使用可跟踪的安全开发路由：

```bash
php -S 127.0.0.1:8081 -t . bin/dev_router.php
```

不要直接运行没有 router 的 `php -S`。`bin/dev_router.php` 只执行前台、后台和安装器三个显式核心入口；缺失静态资源、隐藏/穿越路径、上传 PHP、插件生命周期/设置/Hook/overwrite PHP 及其他任意 PHP 文件均返回 404。它仅用于单机功能回归，不能替代 Nginx/PHP-FPM 的并发、性能和部署边界测试；旧插件若依赖直接 PHP 公共端点，需在有备份的隔离 Nginx/PHP-FPM 环境验证，不得为通过测试修改第三方包源码。

---

## 📦 依赖管理

本项目使用 **Composer** 管理第三方库。

*   **安装依赖**：`composer install`
*   **添加新库**：`composer require vendor/package`
*   **XiunoPHP 核心**：普通业务功能不要顺手修改 `xiunophp/`；只有确认属于框架级修复时才改对应源文件。涉及聚合 bundle 的改动必须在 `tool/` 目录执行 `php merge.php`，并确认 `xiunophp.php` / `xiunophp.min.php` 的生成差异稳定且确属本次变更。外部依赖仍优先通过 Composer 管理。

---

## 📝 编码规范

为了保持代码库的整洁和现代化，请遵守以下规则：

1.  **PHP 8 兼容性**：
    *   严禁使用已废弃的函数（如 `get_magic_quotes_gpc`, `each`, `create_function`）。
    *   注意 PHP 8 的弱类型比较变化和未定义数组键名的警告。
2.  **代码风格**：
    *   以现有过程式架构和 Tab 缩进为准；PSR-12 只在不与现有文件风格和 Hook 结构冲突时采用。
    *   不要为了单个修复批量格式化无关文件，也不要顺手引入新的 formatter 或代码风格基础设施。
3.  **安全性**：
    *   所有 SQL 操作必须使用预处理语句（PDO Prepared Statements）或框架提供的安全封装。
    *   输出到 HTML 时必须进行转义（XSS 防护）。
4.  **性能基线要求**：
    *   4H8G / PHP 8.2 / MySQL 8.0 下曾测得核心页面 220+ QPS、平均请求延迟不高于 220ms；这是特定机器、文件系统、数据量和插件集的历史参考值，不是跨环境 SLA。
    *   提交影响全局渲染或路由的新代码前，在同一 OS、文件系统/挂载、数据库快照、插件集和缓存状态下，用 `bin/benchmark.sh`（Linux）或 `bin\benchmark.bat`（Windows）做前后对比。URL、fid、tid、dataset、plugin-set、cache-state 六项必须显式填写；不得用默认空站或固定 `fid=1` / `tid=1` 代替可复现数据集。
    *   benchmark 只接受直接 HTTP 200、HTML、有效 Request ID 和可区分的首页/版块/主题语义，AB 中出现失败或非 2xx 响应也会失败。提交性能结论时附上 `benchmark-manifest.json` 和三个原始 AB 报告；manifest 会记录 commit/dirty、环境、标签、最终状态与页面 hash。
    *   预检会访问页面并预热路径，普通吞吐对比应标记 `--cache-state=warm`。若声称冷缓存结果，必须另有每个样本前重置缓存的可审计步骤。QPS、AB 平均请求延迟或验证后 TTFB 任一退化超过 15% 都应说明原因。

提交前至少运行 `composer test`。需要数据库、真实 Chromium 或 Docker 的改动必须再运行
对应的 `composer test:db`、`composer test:browser` 或 `composer test:docker`；统一测试入口
会汇总 PASS/SKIP/FAIL，禁止只看退出码后把环境 SKIP 写成“已通过”。

数据库检查必须使用名称含 `test` 的专用可销毁库。复制 `.env.test.example` 为
`.env.test.local`，核对目标后显式运行
`php bin/run_checks.php --profile=db --env-file=.env.test.local --fail-on-skip`；环境文件不会自动加载，
只有字面量 `XIUNO_* KEY=VALUE` 会传给子检查。真实执行前还必须把
`XIUNO_ALLOW_DESTRUCTIVE_SMOKE=1` 作为明确的破坏性测试授权。

---

## 🔌 API 开发指南

版本化 API 代码位于 `route/api/`，公开 v1 契约和端点表见 README。新接口只能加入 `/api-v1-*` 契约；未带版本的 `/api-*` 路径是 legacy 兼容面，不能用修改 legacy 行为的方式实现 v1 语义。

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
    3.  使用 `api_output($code, $message, $data, $http_status)` 输出结果；第四个参数只改变 v1 的 HTTP 状态，legacy 错误继续兼容 HTTP 200。
    4.  v1 读取分支使用 `api_is_v1() AND api_method_required('GET')`，写接口调用 `api_method_required('POST')` 和 `api_login_required()`；Session 写入继续经过 CSRF，API 客户端优先使用 Bearer token。
    5.  创建内容统一调用 `api_post_doctype()`；v1 只接受核心实际支持的 `0/1` 且默认纯文本 `1`，不得通过注释中的未来格式扩张公开契约。
    6.  新增或修改端点时同步 README，并扩展现有 `bin/check_api_routes.php`；真实 HTTP 行为优先加入现有 Docker smoke，不新建一套 API 测试框架。

## 💻 CLI 工具开发

CLI 工具基于 Symfony Console 组件。

*   **入口文件**: `bin/xiuno`
*   **命令位置**: `src/Console/Command/`
*   **新增命令**:
    1.  继承 `Symfony\Component\Console\Command\Command` 类。
    2.  在 `bin/xiuno` 中注册新命令。

## 💾 数据库迁移

当需要修改数据库表结构时，请使用迁移系统而非手动执行 SQL。

*   **迁移文件位置**: `database/migrations/`
*   **文件命名**: `{序号}_{描述}.php`，如 `0001_alter_user_password_field.php`
*   **执行迁移**: `php bin/xiuno migrate`
*   **编写迁移**:
    ```php
    <?php
    return new class {
        public function up(string $tablepre): void {
            $ok = db_exec("ALTER TABLE `{$tablepre}your_table` ...");
            if ($ok === false) {
                throw new RuntimeException('Failed to alter your_table.');
            }
        }
    };
    ```

    `up()` 正常返回后迁移才会被记录为完成，因此每一条 DDL/DML 都必须检查
    `db_exec() === false` 并抛出异常；禁止吞掉数据库错误后继续执行或落迁移记录。

## 🔐 密码安全

*   **新代码禁止使用 MD5 作为密码存储哈希**，统一使用以下辅助函数；现有浏览器/API 的 MD5 wire digest 仅是兼容输入，落库仍必须 bcrypt：
    *   `user_hash_password($password)` — 生成 bcrypt 哈希
    *   `user_verify_password($password, $user)` — 校验密码（自动兼容旧 MD5 和新 bcrypt）
    *   `user_upgrade_password($uid, $password)` — 将旧哈希升级为 bcrypt
*   `user_password_commit($uid, $password_hash, $update = [])` — 提交密码变更并原子递增用户凭证代际；改密入口不得直接 `user_update(...password...)`
*   任何“以旧密码为证明”的入口必须在用户凭证锁内重新验证：登录走 `user_login_credentials_refresh()`，前台自改密走 `user_password_change_verified()`。Session 和 token 只能绑定该次证明返回的精确 `auth_epoch`，禁止重新读取后自动继承并发改密产生的新代际。
*   Session 与长期登录 token 必须绑定用户 `auth_epoch`。前台自改密成功后只可轮换并保留当前 Session；找回密码和后台代改必须让目标用户全部旧凭证失效。
*   项目支持数据库主从分离，因此认证、授权签发、密码 CAS、一次性授权、迁移写后检查和锁内整行 KV 读改写必须使用主库强一致读取：用户记录走 `db_find_one_master()`（模型层传入 primary 标记），原始单行 SQL 走 `db_sql_find_one_master()`，持久 KV 走 `kv__get($key, TRUE)`，MySQL-backed 安全计数走 `cache_get_primary()`。禁止用普通副本读验证刚完成的主库写入；提交是否成功应以精确 affected rows 为准。
*   密码找回的最终授权必须包含 uid、邮箱绑定、签发时间、随机 nonce 和签发时的 `auth_epoch`，使用短 TTL；最终改密要在同一个用户锁内重新读取账号、校验代际、一次性消费授权并按预期代际提交密码。签发后发生任何其他改密时，旧授权必须失效；禁止用 Session 布尔值表示“已经验证”。
*   邮件验证码发送频率必须通过带过期时间的共享 KV 同时按“动作 + 邮箱”和客户端 IP 限制，并在稳定的确定性锁下读改写；Session 只能保存验证码交互状态，不能作为跨会话限流边界。
*   找回密码邮件必须先以站点密钥加密写入主库 outbox，再由显式 CLI worker 投递；Web 请求不得等待 SMTP，也不得自行启动后台子进程。worker 使用数据库 advisory lock 阻止同库重叠运行，并用带过期时间的原子领取令牌处理任务；失败从投递结束时起在验证码有效期内退避重试。SMTP 无幂等提交协议，因此只能承诺 at-least-once，不能宣称严格 exactly-once。
*   `xn_lock_start()` 的文件锁只协调共享同一 `tmp_path` 的进程。多 Web 节点若各用本地临时目录，不得把它假设成分布式锁；部署者必须共享该锁目录，或在进入多节点支持前提供后端原子计数/消费实现。
*   紧急密码恢复工具 `php tool/resetpw.php <uid> [new-password]` 仅允许 CLI 调用；省略密码时生成随机值。不得把它改回 Web 可调用入口，也不得绕过 `user_password_commit()`。

## 🎨 主题与插件兼容层开发

本项目内置了通用注入、PHP 8 运行时、BS4→BS5 前端和主题 API 四层兼容体系，用于让旧插件和第三方主题渐进运行。兼容层降低迁移成本，但不等于所有第三方包已经通过验证。

**开发原则**：

*   **第三方包保持只读**：兼容测试和核心修复不得修改 `plugin/` 中的插件或主题源码；测试副本中的第三方包也必须保持原样，并在回归前后核对目录摘要。
*   **只修通用契约**：只有能安全覆盖一类旧包的问题才进入核心兼容层。包名特判、源码字面量替换、目录名猜测、私有 DOM 选择器和以成功提示 HTML 推断业务状态，都不是可扩展的兼容契约。
*   **包级缺陷单独记录**：第三方包内部的表单选择器错误、私有缓存失效、资源相对路径或业务数据问题，应进入兼容矩阵或由包维护者修复，不能在核心硬编码包名代偿。
*   **通用注入器保底**：`index.php` 的输出注入器只为可识别的 HTML 页面补 CSRF token 与 bs4-compat 资源；它不能替代主题对 jQuery、Bootstrap、Xiuno API 和完整页结构的依赖声明。
*   **主题能力声明**：主题应通过 `theme_register()` 声明自己的能力（如 `htmx_message`），核心据此决定是否使用通用 fallback。
*   **资源注册优先**：主题加载 CSS/JS 应尽量使用 `theme_enqueue_style()` / `theme_enqueue_script()` 而非硬编码 `<link>` / `<script>` 标签。
*   **Hook 优于 Overwrite**：新增兼容逻辑时，优先使用 `// hook` 钩子点注入，减少对核心模板的覆盖。

**设置兼容约束**：

*   默认值只能在插件原本就会执行的安装、升级或后台设置上下文中捕获；前台读取设置时不得为了发现 schema 额外执行第三方 `conf.php`。
*   GET 和校验失败的 POST 不得触发数据库“自愈”写入。关联数组按 schema 递归补缺，列表值整体替换，并保留用户显式保存的 `false`、`0`、空字符串及未知扩展键。
*   设置键只能来自插件实际成功读写或 schema 显式声明；不得由 `{目录名}_setting` 等命名约定猜测真实 KV 键。无法唯一解析时必须 fail-closed 并记录兼容结论。
*   升级核心前已经安装、且数据库中只保存了部分字段的旧包，不会因查看设置页自动写库；管理员需要在该包设置页成功保存一次，或重新经过受控安装/升级成功边界，才会持久化补齐默认值。

**生命周期与回归边界**：

*   当前生命周期保护只能恢复核心记录的安装/启用状态和受保护的包文件，不能承诺回滚第三方脚本执行过的 DDL、业务数据、上传文件、网络请求或其他外部副作用。文档和界面应使用“状态恢复”，不要宣称事务回滚。
*   每个确认的兼容缺陷都要有可执行回归。加载顺序、动态 DOM、组件语义和 CSRF 同源边界必须增加真实浏览器行为测试，不能只检查源码中是否存在某个字符串。
*   修改前端源文件后运行 `php bin/build_frontend_assets.php`，并用 `php bin/build_frontend_assets.php --check` 确认压缩资产与源码一致。

**相关文件**：

| 文件 | 作用 |
|------|------|
| `index.php` | 通用注入器（`ob_start` 回调，自动注入 CSRF + bs4-compat 到所有主题） |
| `view/css/bs4-compat.css` | CSS 兼容层（BS4→BS5 类映射 + 资源降级样式） |
| `view/js/bs4-compat.js` | JS 兼容层（data 属性转换 + CSRF 全局保护 + Modal/Tooltip/Popover API 代理 + 资源 404 降级） |
| `xiunophp/php8_compat.php` | PHP 8+ 兼容层（TypeError 捕获 + polyfill） |
| `model/misc.func.php` | 主题 API（`theme_register` / `url_extra_register` / `theme_enqueue_*`） |

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
