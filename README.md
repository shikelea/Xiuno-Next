# Xiuno Next (Xiuno BBS 4.0 Reforged)

![PHP Version](https://img.shields.io/badge/php-8.0%2B-blue)
![License](https://img.shields.io/badge/license-MIT-green)
![Bootstrap](https://img.shields.io/badge/bootstrap-5.0-purple)

> **当前状态：可用性仍不佳，不推荐直接用于生产环境。** 项目正在持续开发与兼容性验证，请先在测试环境部署，做好备份并自行评估风险。
>
> **欢迎参与贡献：** 欢迎提交 Issue、复现步骤、测试结果、文档改进和 Pull Request，一起完善 Xiuno Next。

> **"不破不立，在保持轻量的基础上拥抱未来。"**

## 🚀 项目介绍

**Xiuno Next** 是对经典论坛引擎 Xiuno BBS 4.0 的现代化重构版本。项目在保留过程式轻量核心的同时，引入 PHP 8 兼容、安全守卫和可复现测试；当前仍处于开发与生态兼容验证阶段。

### ✨ 核心特性

- **⚡️ 轻量架构**：保留过程式核心和静态编译 Hook；复杂插件/主题组合仍需按实际环境进行性能验证。
- **🧯 故障恢复**：提供插件安全模式、生命周期状态恢复和专项守卫；第三方脚本的数据库及外部副作用不保证自动回滚。
- **🐘 PHP 8 兼容**：核心面向 PHP 8.0.2+ 维护，并通过通用兼容层逐步覆盖旧插件；未验证的第三方包不承诺直接可用。
- **🎨 现代 UI**：默认主题全面升级至 Bootstrap 5，移动端优先设计，体验更佳。
- **🐳 Docker 开发环境**：提供 Compose 配置和 HTTP smoke；生产部署仍需按实际存储、权限和反向代理环境验证。
- **🔌 基础版本化 API**：v1 已覆盖登录、版块/主题读取和发帖等基础场景；并非完整的无头论坛接口。

## 📦 快速开始

### 方式一：Docker 启动（推荐）

无需配置 PHP 环境，只需安装 Docker。

1. **克隆项目**
   ```bash
   git clone https://github.com/shikelea/Xiuno-Next.git
   cd Xiuno-Next
   ```

2. **准备可写运行目录（Linux bind mount）**
   ```bash
   mkdir -p conf log tmp upload
   ```
   只把这四个目录授权给容器内 PHP worker 对应的宿主 UID/GID 或 ACL；不要给整个项目或其他用户开放写权限。Docker Desktop for Windows/macOS 通常无需额外改权限，若安装器提示不可写，再按 `docker compose exec app id www-data` 显示的身份配置目录所有者或 ACL。

3. **启动服务并安装 CLI 依赖**
   ```bash
   docker compose up -d
   docker compose exec app composer install --no-interaction --prefer-dist
   ```

4. **开始安装**
   访问 `http://localhost:8080`，进入安装向导。
   - 数据库主机：`db`
   - 数据库名：`xiunobbs`
   - 用户名：`xiuno`
   - 密码：`xiuno_password_changeme`
   - Compose 会自动预填前三个非敏感连接项；数据库密码不会写入未认证的安装页面，仍需手动输入。
   - 标准 Compose 将源码（包括 `plugin/`）挂载为只读，适合核心开发和不可变部署；后台安装、启用、禁用或卸载第三方包需改用有备份的可写隔离环境，不能直接在此配置中执行。
   - 生产环境安装完成后请删除或在 Web 服务器层封禁 `install/`。

### 方式二：传统部署

1. 确保服务器环境满足：PHP 8.0.2+（需安装 JSON、OpenSSL、PDO、PDO_MySQL、Mbstring、GD 和 Zip 扩展），MySQL 5.7+ / 8.0+。
2. 将代码上传至 Web 目录。
3. 只让 Web/PHP 进程对 `conf/`、`log/`、`tmp/`、`upload/` 具有所需的最小写权限；应用代码、`plugin/` 和其他目录保持只读，禁止使用站点全局 `0777`/Everyone。
4. 准备空的目标数据库；安装器检测到现有 Xiuno 核心表时会中止，不会覆盖已有 schema。旧站请备份后使用下方升级流程。
5. 访问网站首页进行安装。
6. 生产环境安装完成后删除或在 Nginx/Apache 中封禁 `install/`，避免配置文件异常时重新开放安装入口。

生产 Web 服务器还必须拒绝外部访问 `conf/`、`log/`、`tmp/`、`data/`、`bin/` 和 `install/`。建议把 `tmp_path`、`log_path` 配到文档根目录之外；若因旧部署必须放在站点内，不能只依赖 PHP 文件中的 `exit` 作为服务器访问控制。

### 方式三：PHP 内置服务器（仅本地功能调试）

已经安装本机 PHP 与所需扩展时，可在仓库根目录启动安全开发路由：

```bash
php -S 127.0.0.1:8081 -t . bin/dev_router.php
```

随后访问 `http://127.0.0.1:8081/`；未安装站点会进入安装向导，已有 `conf/conf.php` 的测试站点会直接启动。不要省略 `bin/dev_router.php`：它会让缺失的 CSS、JS、图片等资源返回真实 404，并阻止隐藏文件、路径穿越、上传 PHP、插件生命周期/设置/Hook/overwrite PHP 及其他任意 PHP 文件被直接执行。

该服务器只绑定回环地址，适合页面和兼容层功能调试，不用于生产、并发或性能结论。为保持安全默认，本地路由不支持旧插件直接访问自己的 PHP 公共端点；确需验证此类端点时，应使用有备份的隔离 Nginx/PHP-FPM 环境，并继续保持第三方包源码不变。

## 🛡️ 安全特性 (Security)

- **🔐 密码安全**：渐进式密码哈希迁移（MD5+salt → bcrypt）；Session 与长期登录 token 绑定用户凭证代际，改密后旧凭证失效，密码找回授权为短时一次性使用。
- **🛡️ CSRF 防护**：核心表单和同源 AJAX 使用 CSRF Token；第三方主题及插件仍需经过兼容性与对抗测试。
- **🔍 XSS 防护**：持续审计核心模板输出，并为新代码提供统一转义/净化约束。
- **🧱 安装器防护**：安装完成后以 `conf/conf.php` 作为硬阻断，防止历史重装类漏洞回归。
- **💉 SQL 安全演进**：新代码要求参数化查询，遗留字符串拼接路径仍按风险持续迁移，不能视为已全量消除。
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
- [x] **v4.5.1 (Hardening, 预发布候选)**: 完成认证、兼容层、Docker、更新完整性与 CLI 文档加固；真实旧站门禁发现下载首跳和升级后版本元数据漂移，因此仅保留 prerelease，不提升为正式版。
- [x] **v4.5.2 (Release reliability, 预发布候选)**: 修复官方归档下载链，并保证在线升级与回滚同步核心默认配置、运行版本和静态资源版本；最终资产测试发现浏览器安装 CSRF 与 InnoDB 统计缺陷，因此保留为 prerelease。
- [x] **v4.5.3 (Install reliability, 发行版)**: 修复原生表单与同源 AJAX 安装 token 保留、安装前错误页呈现和 InnoDB 精确统计；最终产物通过真实升级、业务连续性与回滚门禁后正式发布。
- [x] **v4.5.4 (Hardening & API, 预发布候选)**: 完成附件内容校验、MySQL 会话转义边界、依赖锁定、登录/找回匿名响应、邮件 outbox、API v1、Linux/Docker/CLI 实证及 10 包兼容验证；最终 Linux 在线更新门禁发现解压目录权限缺陷，因此保留 prerelease，未提升为正式版。
- [ ] **v4.5.5 (Update reliability, 开发中)**: 修复 Linux 在线更新解压和备份目录的显式权限与失败处理；下一步完成唯一发布包、旧站升级、迁移、业务连续性与成套回滚闭环。
- [ ] **v5.0.0 (Next)**: 稳定 Theme API、统一编辑器接口，改善移动端与渐进增强体验；继续保持轻量服务端渲染，不建设插件/主题市场。

> 路线图中的已完成项记录对应版本当时的交付。项目不建设插件/主题市场；历史远程插件下载入口保持 fail-closed，本地插件/主题扩展与兼容能力继续维护。在线更新仅在完整性校验、备份与回滚边界全部通过后作为生产能力发布；Theme API 仍需更多真实生态采用和回归证据。

## 💻 命令行工具 (CLI)

本项目内置了 `xiuno` 命令行工具，用于辅助开发和运维。

**使用方法**:

```bash
# 确保已安装依赖
composer install

# 查看版本、所有命令及单个命令帮助
php bin/xiuno --version
php bin/xiuno list
php bin/xiuno <command> --help

# 只检查迁移/升级元数据，不连接数据库或写入配置
php bin/xiuno migrate --check
php bin/xiuno upgrade --check

# 创建新插件
php bin/xiuno make:plugin <plugin_name>

# 投递找回密码邮件队列（适合每分钟由 cron / 计划任务调用）
php bin/xiuno mail:work

# 生成本地 Hook 点索引（输出到已忽略的 docs/）
php bin/generate_hook_docs.php

# 执行数据库迁移
php bin/xiuno migrate

# 从旧版 Xiuno BBS 升级到 Xiuno Next
php bin/xiuno upgrade
```

`migrate --check` 与 `upgrade --check` 只验证随代码发布的迁移文件和升级元数据，不能代替目标站点的数据库预检。`migrate` 会在取得数据库升级锁后直接执行待迁移项，不另行询问；`upgrade` 会先显示站点预检报告，再以默认“否”询问是否继续，`--no-interaction` 不会自动批准升级。`make:plugin` 只在项目的 `plugin/<name>/` 创建新目录，并拒绝覆盖已有目录。

启用找回密码后，Web 请求只把加密邮件任务写入主数据库，不会同步等待 SMTP。部署者必须每分钟运行一次 `php bin/xiuno mail:work`（Windows 任务计划程序、cron 或 systemd timer 均可），并监控非零退出码；Docker 部署可在 Compose 目录执行 `docker compose exec -T app php bin/xiuno mail:work`。否则页面仍返回统一的匿名成功响应，但邮件会留在 outbox，超过验证码的 5 分钟有效期后不再发送。可用 `--limit=1..100` 控制单次处理量，默认 10；同一数据库只允许一个 worker，重叠运行会以非零状态退出。投递是 at-least-once：失败任务从本次投递结束时起退避重试；若 SMTP 已接收而 worker 恰好在删除任务前崩溃，同一验证码可能重复送达。

命令成功、无需操作或用户在升级确认处取消时退出码为 `0`；参数错误、未知命令、环境检查或执行失败时退出码为 `1`。自动化脚本应同时检查退出码和输出，不能把退出码 `0` 的“升级已取消”当作已经升级。

### 升级指南（Xiuno BBS 4.0.4 / 4.0.5 / 4.0.7 → Xiuno Next 4.5.5）

升级分为“替换核心文件”和“执行站点迁移”两部分，不是无备份的一键覆盖。请先在旧站副本演练；生产升级时停止站点写入，并确保数据库与站点文件来自同一个恢复点。

Linux 上从 `4.5.4` 及更早版本升级到 `4.5.5` 时，必须按下述步骤手工替换核心文件并执行 CLI 升级，不能依赖旧版后台在线更新：旧更新器会在载入 `4.5.5` 修复前因临时目录权限错误而停止。站点进入 `4.5.5` 后，后续在线更新才会使用修复后的目录创建逻辑。

```bash
# 1. 备份数据库和整个站点目录，并确认备份可以读取
mysqldump -u root -p your_db > backup.sql
cp -r /path/to/xiuno /path/to/xiuno_backup

# 2. 在单独目录解压 4.5.5，再将核心文件复制到旧站
# 不得覆盖 conf/、plugin/、upload/、tmp/、log/、本地数据目录和部署配置

# 3. 在站点目录安装运行依赖
composer install --no-dev --prefer-dist

# 4. 先检查发布内的升级元数据，再查看实际命令帮助
php bin/xiuno upgrade --check
php bin/xiuno upgrade --help

# 5. 运行真实站点预检；核对报告后在默认“否”的确认处明确同意
php bin/xiuno upgrade
```

升级工具会自动完成以下操作：

- **版本检测**：识别当前安装的旧版版本号
- **升级预检报告**：连接主数据库，列出配置、字段和迁移变更，确认后才进入写入阶段
- **配置迁移**：自动添加旧版缺失的配置项（如 `csrf_on`、`disabled_plugin` 等）
- **数据库迁移**：扩展 `password` 字段至 `varchar(255)`，并补充登录凭据代际字段
- **密码渐进升级**：用户下次登录时，密码自动从 MD5+salt 升级为 bcrypt，无需重置
- **缓存清理**：只清理可再生的编译缓存和插件 Hook 缓存；任务锁、恢复备份和安全模式标记保持不变
- **完成标记**：前述步骤成功后才把 `conf/conf.php` 的版本与静态资源版本写为 `4.5.5`

CLI 升级不会替你创建数据库或全站文件备份，也不能把 MySQL DDL、配置文件写入和第三方脚本副作用合并为一个可自动回滚的事务。如果升级失败，不要手工把 `conf/conf.php` 的版本改成 `4.5.5`：停止站点写入，保留错误输出，选择从同一恢复点同时还原数据库和全部站点文件，或修复明确的失败原因后重新运行命令。成功后如需降级，同样必须恢复升级前成套的数据库与文件备份；后台在线更新的“最近备份回滚”只处理它记录的核心文件和配置版本，不等同于数据库回滚。

升级完成后重新打开站点，验证登录、发帖/回帖、附件和常用插件/主题，再到后台更新缓存。确认无误后才恢复外部写入。

## 开发与回归测试

默认测试入口只运行不需要外部数据库、浏览器或 Docker 的确定性守卫：

```bash
composer test
```

需要扩展环境时可显式选择 `composer test:browser`、`composer test:db`、
`composer test:docker` 或 `composer test:full`。统一入口会分别汇总 PASS、SKIP 和
FAIL；DB/full 配置默认启用 `--fail-on-skip`，没有真实执行的数据库测试不能被当成通过。
可用 `php bin/run_checks.php --profile=full --list` 查看当前完整检查清单。

数据库 smoke 不会读取应用的生产配置，也不会自动加载环境文件。复制 `.env.test.example`
为已忽略的 `.env.test.local`，填写名称含 `test` 的专用可销毁数据库；确认备份与目标后把
`XIUNO_ALLOW_DESTRUCTIVE_SMOKE` 改为 `1`，再运行：

```bash
php bin/run_checks.php --profile=db --env-file=.env.test.local --fail-on-skip
```

runner 只接受字面量 `XIUNO_* KEY=VALUE`，不会去引号、变量展开或执行命令，因此同一文件可在 PowerShell、CMD 和 Bash 使用。

## 性能测试 (Benchmark)

项目内置跨平台共享的性能契约入口，需要 PHP CLI、curl 和 ApacheBench (`ab`)。目标地址、
版块/主题样本以及数据、插件、缓存标签都必须显式提供；脚本不会再用默认地址或假定
`fid=1` / `tid=1`。

```bash
# Linux：先安装 ApacheBench
sudo apt install apache2-utils   # Ubuntu/Debian
sudo yum install httpd-tools     # CentOS/RHEL

bash bin/benchmark.sh \
  --url=http://127.0.0.1:8081/ --fid=2 --tid=37 \
  --dataset=seed-2026-08 --plugin-set=core-only --cache-state=warm

# Windows（PowerShell 或 CMD，同一参数契约）
bin\benchmark.bat --url=http://127.0.0.1:8081/ --fid=2 --tid=37 --dataset=seed-2026-08 --plugin-set=core-only --cache-state=warm
```

运行前会对首页、版块页和主题页逐一验证：请求必须直接返回 HTTP 200（不跟随跳转）、
`text/html`、唯一有效的 `X-Request-ID`，并通过页面语义和互异性检查。这样安装页、登录页、
不存在的 fid/tid 或三个地址返回同一页面时不会生成“有效”性能数据。默认语义适用于核心页面；
非默认主题可用唯一的 `--home-marker`、`--forum-marker`、`--thread-marker` 显式声明页面标记。

每次运行写入新的 `tmp/benchmark-*/`，其中 `benchmark-manifest.json` 记录 commit、dirty 状态、
操作系统/PHP/工具版本、数据集、插件集、缓存状态、每页最终 HTTP 状态、Request ID、HTML
SHA-256、AB 指标和经过同一契约复验的 TTFB 样本；`bench_*.txt` 保留原始 AB 报告。可用
`--requests`、`--detail-requests`、`--concurrency`、`--ttfb-samples` 调整样本，完整帮助见
`php bin/benchmark.php --help`。

4H8G / PHP 8.2 / MySQL 8.0 下曾测得 220+ QPS、核心页面平均请求延迟不高于 220ms。该数值只作为当时机器、文件系统、数据量和插件集的历史参考，不代表所有 Docker/WSL/网络挂载环境；性能改动应在同一环境记录冷/热缓存前后数据，并将退化控制在 15% 以内。

`cache-state` 是必须如实填写的比较标签。脚本的 HTTP 预检本身会访问三个页面，因此普通吞吐
对比应标记为 `warm`；真正的冷缓存单请求需在每次采样前由外部可审计流程重置缓存，不能把
本脚本预检后的 AB 结果声明为纯冷缓存结果。

## API v1 文档

API v1 使用 Xiuno 的查询路由形式，入口为 `/?api-v1-{resource}-{action}.htm`。它是面向轻量客户端的稳定最小契约，不是完整 JSON:API，也尚未覆盖论坛资源的全部生命周期。未带 `v1` 的 `/?api-*` 路径仅供旧客户端兼容：其错误仍可能返回 HTTP 200，不应作为新集成入口。

### 端点

| 方法 | 路径 | 认证 | 参数与返回 |
| --- | --- | --- | --- |
| GET | `/?api-v1-index.htm` | 否 | 返回核心版本、API 版本和服务器时间 |
| GET | `/?api-v1-forum-list.htm` | 否 | 返回当前用户可读版块的 `total/list` |
| GET | `/?api-v1-forum-read.htm` | 否 | `fid`；返回一个可读版块 |
| GET | `/?api-v1-thread-list.htm` | 否 | 可选 `fid/page/pagesize`；`pagesize` 最大 100 |
| GET | `/?api-v1-thread-read.htm` | 否 | `tid`，可选 `page/pagesize`；返回主题与回复，不增加浏览量 |
| POST | `/?api-v1-thread-create.htm` | 是 | `fid/subject/message`，可选 `doctype`；返回新主题 |
| POST | `/?api-v1-post-create.htm` | 是 | `tid/message`，可选 `doctype/quotepid`；返回新回复 |
| POST | `/?api-v1-user-login.htm` | 否 | `email/password`，密码为原始输入；返回安全用户信息及 token |
| GET | `/?api-v1-user-read.htm` | 否 | 可选 `uid`；省略时读取当前 token/Session 用户 |
| GET | `/?api-v1-user-threads.htm` | 否 | 可选 `uid/page/pagesize`；只返回当前访问者可读主题 |
| GET | `/?api-v1-search-thread.htm` | 否 | `keyword`（或 `q`），可选 `page/pagesize`；`pagesize` 最大 50 |

请求参数当前使用查询参数或 `application/x-www-form-urlencoded` 表单；v1 尚不接受 JSON request body。创建主题和回复时，`doctype` 省略即为 `1`，当前只接受：

- `0`：经过服务端净化的 HTML。
- `1`：纯文本，默认值。

Markdown/UBB 尚未成为核心 v1 格式，传入其他值返回 HTTP 422。历史 legacy API 继续保留原来的 `doctype=0` 默认值。
v1 的 `list` 和 `posts` 集合固定返回 JSON array；legacy 可能继续返回以资源 ID 为键的 JSON object。

### 认证

新客户端应在请求头使用：

```http
Authorization: Bearer <token>
```

`token`/`bbs_token` 参数仅为旧客户端兼容，不建议放入 URL。使用浏览器 Session 发起写请求时，仍必须通过 `_token` 或 `X-CSRF-Token` 提交当前 Session 的 CSRF token；Bearer 请求不依赖 Session CSRF。

### 响应与状态

进入 API 路由后的 v1 响应保留统一 JSON envelope：

```json
{
  "code": 0,
  "message": "OK",
  "data": {}
}
```

v1 使用以下 HTTP 状态。`message` 可能随语言变化，客户端应以 HTTP 状态和 `code` 判断结果，并忽略不认识的新增字段。

| HTTP | 含义 |
| --- | --- |
| 200 | 成功 |
| 401 | 未登录、token 无效或登录凭据错误 |
| 403 | 权限不足或 Session CSRF 校验失败 |
| 404 | API action、版块、主题或用户不存在 |
| 405 | 请求方法不允许；响应包含 `Allow` |
| 409 | 主题关闭或登录期间凭据并发变化 |
| 422 | 必填项、长度、搜索词或 `doctype` 校验失败 |
| 429 | 登录尝试过于频繁 |
| 500 | 创建、token 签发等服务器内部失败 |
| 503 | 数据库等必要服务暂时不可用 |

数据库在 API 路由加载前不可用时，bootstrap 会直接返回 HTTP 503 和包含
`code/message/request_id` 的 JSON 服务诊断；该响应不保证带有 `data` 或 API 版本头，
客户端应把它作为可重试的服务级失败处理。

资源的稳定基础字段为：版块 `fid/name/brief`；主题 `fid/tid/uid/subject/create_date/last_date/views/posts/closed`；回复 `tid/pid/uid/isfirst/create_date/doctype/quotepid/message`；用户 `uid/gid/username/threads/posts/credits/avatar`。格式化与 Hook 可以增加字段，但不得重新暴露 `safe_info()` 已移除的凭据、邮箱、IP 等敏感字段。

## 插件体系状态

当前已经可以开发传统兼容插件，使用 `php bin/xiuno make:plugin <plugin_name>` 生成基础结构。Xiuno Next 原生插件规范仍处于预览前准备阶段，v4.5.x 优先固定 `plugin.json` 草案、Hook 索引和可重复的插件/主题 smoke test。

## 开发者资料

`docs/` 仅用于维护者本地的审计、基线和生成索引，不纳入 Git。对外稳定契约以 `README.md`、`CONTRIBUTING.md`、CLI 帮助和代码内容为准。

## 🤝 参与贡献

Xiuno Next 是一个社区驱动的项目，我们需要你的帮助！无论是提交 Bug、修复代码还是完善文档，都非常欢迎。

## 📄 许可证

本项目遵循 [MIT License](LICENSE.txt)。基于 Xiuno BBS 4.0 二次开发。
