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
- [x] **数据库层适配**：确保 PDO 连接参数符合 PHP 8 标准（默认错误模式变化），兼容 MySQL 8.0 保留字 (`rank`, `group`)。

### 阶段二：规范与工程化 (Standardization Phase) - [完成]
**目标**：引入现代开发标准，降低贡献门槛。
- [x] **引入 Composer**：
  - 创建 `composer.json`，用于自动加载和依赖管理。
- [x] **Docker 化**：
  - 提供标准的 `docker-compose.yml`，一键启动 PHP 8.2 + MySQL 8.0 开发环境。
- [x] **代码风格统一**：
  - 引入 `PHP-CS-Fixer`，遵循 PSR-12 标准（逐步迁移，避免破坏 Hook 点）。

### 阶段三：体验升级与度量基线 (Experience & Baseline Phase) - [完成]
**目标**：快速见效的体验提升 + 建立性能度量基线（无法优化未度量之物）。

**已完成：**
- [x] **API 化改造**：
  - [x] 新增 `route/api/` 目录和基础路由。
  - [x] 实现核心 API (用户登录、帖子列表、详情、发帖、回帖)。
- [x] **默认主题重构**：
  - [x] 升级至 Bootstrap 5，移除 BS3/4 混用代码。
  - [x] 重构核心模板 (`header`, `footer`, `thread_list`, `post_list`) 以适配 BS5 语法。
  - [x] 引入 FontAwesome 图标库，修复后台与前台图标缺失问题。
  - [x] 优化移动端适配，修复导航栏折叠与布局问题。

**进行中：**
- [x] **插件容错与安全模式**（现状：一个有问题的插件直接白屏，管理员连后台都进不去）：
  - **错误隔离**：插件 hook 代码执行时捕获 `Error`/`Exception`（PHP 8 已将 Fatal Error 转为可捕获的 `Error` 异常），出错时跳过该插件并记录日志，而非整站白屏。
  - **安全模式**：支持 `?safe_mode=密钥` 或在 `tmp/` 目录创建 `safe_mode` 文件，跳过所有插件加载，让管理员能进入后台禁用问题插件。
  - **崩溃自动恢复**：连续 N 次 Fatal Error 后自动进入安全模式，并在后台提示"插件 XXX 导致崩溃，已自动禁用"。
  - **启用前检查**：启用插件前执行 `php -l` 语法检查 + PHP 版本兼容性扫描，阻止明显不兼容的插件上线。
- [x] **BS4 插件兼容层**（社区教训：BS5 升级导致大量插件 Modal/Tooltip 失效）：
  - 提供 `data-toggle` → `data-bs-toggle` 等属性的自动转换 JS 垫片，让 BS4 插件无需修改即可在 BS5 下运行。
  - 保留 BS4 常用类名别名（如 `.card-deck`、`.badge-*` 等），避免第三方主题/插件样式断裂。
- [x] **后台管理面板适配**（前台已完成 BS5 迁移，后台仍有残留）：
  - 后台模板同步完成 BS4→BS5 类名迁移和图标修复。
  - 修复社区反馈的后台插件列表卡顿问题。
- [x] **HTML 现代化最佳实践**：
  - 为所有 `<img>` 添加 `loading="lazy"` 和 `decoding="async"`（零成本性能提升）。
  - 图标添加 `aria-hidden="true"`，交互按钮添加 `aria-label`（无障碍基线）。
- [x] **SEO 基础设施**（论坛的生命线是搜索引擎流量）：
  - 自动生成 `sitemap.xml`（基于版块和帖子数据，轻量 PHP 实现）。
  - 为帖子页添加 Open Graph / Twitter Card meta 标签（标题、描述、首图）。
- [x] **建立性能基线**（在任何进一步改动之前，先记录当前状态）：
  - 编写标准化压测脚本（基于 `ab` 或 `wrk`），覆盖首页、帖子列表、帖子详情等核心场景（已提供 `bin/benchmark.bat` 和 `docs/performance_baseline.md`）。
  - 已于 2026-03-06 采集首轮基线数据：核心页面 QPS 均超过 220，TTFB 控制在 220ms 以内。后续重构需以 `docs/performance_baseline.md` 为退化防线。

**代码审查修复（v4.3.1）：**
- [x] **移除 BS4 残留文件**：删除 `view/js/bootstrap.bundle.js`（Bootstrap 4.0.0），避免与 BS5 产生混淆。
- [x] **安全模式路径判断加固**：将 `index.php` 中的崩溃检测逻辑从宽松的子串匹配改为基于 `APP_PATH` 的精确前缀匹配，防止部署路径含 `tmp`/`plugin` 时误触发安全模式。
- [x] **API 响应结构规范化**：`api_output()` 输出从扁平结构改为标准 `{code, message, data}` 嵌套结构，与文档描述一致。
- [x] **BS5 语法清理**：将项目自有文件 `bootstrap-plugin.js` 中残留的 `data-dismiss` 更新为 `data-bs-dismiss`。
- [x] **模板注释修正**：修正 `header_nav.inc.htm` 中过时的 "Bootstrap 4.0" 注释。
- [x] **CLI 脚手架修复**：移除 `make:plugin` 生成的 Hook 示例文件中的 `<?php exit;`，避免误导开发者。

### 阶段四：安全加固 (Security Phase)
**目标**：修补历史遗留安全隐患，达到现代 Web 应用安全基线。

> ⚠️ **为什么安全在生态之前？** 不能在不安全的地基上邀请社区建设。密码还在用 MD5，就开放插件市场，是对用户的不负责任。

- [x] **数据库迁移系统**（安全迁移的前置依赖）：
  - 轻量 Migration 机制（基于版本号的 PHP 文件），支持 `php bin/xiuno migrate` 安全升级数据库结构。
  - 迁移记录存储在 `bbs_kv` 表，无需新建表。迁移文件位于 `database/migrations/`。
- [x] **密码哈希迁移**：
  - 将 MD5+salt 迁移至 `password_hash()` / `password_verify()` (bcrypt)。
  - 实现**渐进式迁移**：用户登录时自动检测旧哈希并升级为 bcrypt，无需重置密码。
  - 提供 `user_verify_password()`、`user_hash_password()`、`user_upgrade_password()` 辅助函数，供插件调用。
  - 保留 `salt` 字段确保旧插件兼容性，双模式校验永久保留。
- [x] **安全响应头**（第一批）：
  - 添加 `X-Content-Type-Options: nosniff`、`X-Frame-Options: SAMEORIGIN`、`Referrer-Policy: strict-origin-when-cross-origin`。
  - CSP 和 HSTS 留待下一批（需审计内联脚本和确认 HTTPS 部署）。
- [x] **XSS/CSRF 加固**：
  - 实现全局 CSRF token 机制：`csrf_token()` 生成、`csrf_check()` 校验，通过 `$.ajaxSetup` 自动附带到所有 AJAX POST 请求。
  - 在 `index.inc.php` 和 `admin/index.inc.php` 中对所有 POST 请求统一校验（API 路由除外，使用自身鉴权）。
  - 修复 `message.htm`（前台/后台/安装）的 XSS：对 `$message` 输出做 `htmlspecialchars`。
  - 修复 `form_text()`、`form_hidden()`、`form_textarea()` 表单辅助函数，对 value 做 HTML 转义。
  - 修复 `post.htm` 编辑表单中 `$form_subject` 和 `$form_message` 的 XSS。
- [x] **SQL 注入防护审计**：
  - 修复 `thread_inc_views()` 中的直接 SQL 拼接，强制 `intval()` 转换参数。
  - 审计确认 `db_cond_to_sqladd()` 的 `addslashes` 在 UTF-8 下安全。
**代码审查修复（v4.4.1）：**
- [x] **API 登录密码校验修复**：API 端收到原始密码但存储格式为 `bcrypt(md5(password))`，需服务端 `md5()` 对齐浏览器行为，否则 API 登录永远失败。
- [x] **表单辅助函数 XSS 补全**：`form_password()` 和 `form_time()` 遗漏了 `htmlspecialchars()` 转义，与已修复的 `form_text/form_hidden/form_textarea` 保持一致。
- [x] **`thread_inc_views()` 表前缀修复**：将硬编码的 `bbs_thread` 改为 `{$GLOBALS['db']->tablepre}thread`，支持自定义表前缀。

**安全加固与兼容性修复（v4.4.2）：**
- [x] **BS4→BS5 自动兼容垫片**：`view/js/bs4-compat.js` 自动将旧插件的 `data-toggle` 等 BS4 属性转换为 BS5 格式，含 MutationObserver 监听动态插入元素。
- [x] **Token 生成加固**：密钥派生从 `md5(xn_key())` 升级为 `hash('sha256', xn_key())`，提升 Token 安全强度。
- [x] **`$_REQUEST` 参数注入修复**：合并顺序改为 `COOKIE → GET → URL → POST`，POST 最高优先级，防止 URL 参数覆盖表单数据。
- [x] **`magic_quotes` 死代码清理**：移除 `set_magic_quotes_runtime()` 调用（PHP 8 已移除该函数）。
- [x] **后台安全状态面板**：后台首页新增 Xiuno Next 信息卡片，显示版本号、CSRF/Bcrypt/HTTPS/DEBUG 状态。
- [x] **安装流程修复**：版本号 4.3.0→4.4.1，目录权限检测改用绝对路径 + 自动创建缺失目录。
- [x] **API 路由修复**：`param('action')` 改为 `param(2)`，修复位置参数解析。
- [x] **退出页面修复**：XSS 转义移入 `jump()` 内部，`message.htm` 恢复原始 HTML 输出。
- [x] **首页发帖按钮**：为登录用户在首页侧栏添加发帖入口。

- [x] **旧版升级路径**（社区教训：碎片化的根源之一是没有官方升级方案）：
  - 实现 `php bin/xiuno upgrade` 一键升级工具，支持从 4.0.4/4.0.5/4.0.7 等主流分支迁移到 Xiuno Next。
  - 自动检测旧版版本号、密码字段类型、缺失配置项、待执行迁移，生成升级预检报告。
  - 升级流程：配置补全 → 数据库结构检查 → 执行迁移 → 缓存/安全模式清理。
  - 配置失败时提供手动修复指引，升级记录存储在 `bbs_kv`（`xn_upgraded_from`、`xn_upgraded_date`）。

**性能优化与在线更新（v4.4.3）：**
- [x] **插件页性能优化**：官方插件市场已关闭，`plugin_official_list_cache()` 直接返回空数组，消除无意义的 HTTP 请求（原先每次加载等待 30s×3 超时）。
- [x] **CSRF 主题兼容**：在 `header.inc.htm` 添加 `<meta name="csrf-token">` 标签，`bs4-compat.js` 自动读取并设置 `$.ajaxSetup`，确保主题覆盖 footer 后 CSRF 不失效。
- [x] **管理操作 JSON 修复**：修复 `$.xget()` 在收到 HTML 响应时返回错误字符串而非原始内容的 bug，置顶/关闭/移动/删除等模态对话框恢复正常。
- [x] **后台一键在线更新**：新增 `admin/route/update.php`，通过 GitHub API 检测最新版本并一键拉取更新。
  - 自动对比当前版本与 GitHub 最新 Release，显示更新日志。
  - 下载 zip → 解压 → 递归覆盖核心文件，自动跳过 `conf/`、`plugin/`、`upload/`、`tmp/` 等用户数据目录。
  - 更新完成后自动写入新版本号并清理缓存。
  - 后台导航栏新增「更新」入口，支持中英文。
- [x] **BS4→BS5 CSS 兼容层**：新增 `view/css/bs4-compat.css`，为所有 BS4 废弃类名提供别名样式。
  - 覆盖 `form-group`、`custom-select`、`custom-control`、`badge-*`、`float-left/right`、`text-left/right`、`font-weight-*`、`sr-only`、`btn-block`、`dropdown-menu-right`、`media`、`jumbotron`、`card-deck`、`card-columns`、`embed-responsive` 等全部 BS4→BS5 变更类名。
  - 旧插件/主题无需修改代码即可正常渲染，在前台和后台 header 中自动加载。
- [x] **CSRF 表单自动兼容**：`bs4-compat.js` 新增自动为所有 `form[method=post]` 注入 `_token` hidden 字段。
  - 通过 MutationObserver 监听动态插入的表单，确保 AJAX 加载的模态窗口表单也被覆盖。
  - 解决传统表单 POST 提交（非 `$.xpost`）无法通过 `csrf_check()` 的问题。
- [x] **BS4 Modal JS API 代理**：`bs4-compat.js` 新增 `jQuery.fn.modal()` 方法代理，将 BS4 的 `.modal('show'/'hide')` 调用转发到 BS5 `bootstrap.Modal` 实例。
- [x] **后台加载 bs4-compat.js**：`admin/view/htm/footer.inc.htm` 新增 `bs4-compat.js` 引用，确保后台插件也受益于兼容层。
- [x] **核心模板 BS4 残留清理**：`post.htm` 和 `mod_move.htm` 中的 `custom-select` 替换为 `form-select`；移除前后台 header 中冗余的内联 BS4 兼容样式。
- [x] **GitHub 加速代理**：在线更新支持代理加速，解决中国大陆服务器无法直连 GitHub 的问题。
  - 内置 3 个加速节点：EdgeOne（腾讯CDN）、GH-Proxy、LLKK。
  - 支持自定义代理地址。
  - 一键测试全部代理连通性和延迟（毫秒级），状态即时显示。
  - 代理设置持久化到 `conf.php`，检查更新和下载更新时自动使用。

**稳定性与版本治理（v4.4.4）：**
- [x] **在线更新 ZIP 校验加固**：
  - 下载后校验 ZIP 魔数，避免代理错误页或 GitHub API 错误响应被当作更新包覆盖。
  - 解压前检查 ZIP 内路径，阻止绝对路径、盘符路径和 `../` 路径穿越。
  - 覆盖核心文件前自动备份原文件到 `tmp/update_backup_*`；备份失败时立即停止覆盖。
  - 后台更新页提供最近备份的一键回滚入口，并记录 ZIP SHA-256 到 `log/update.log`。
  - 使用 `github.com/archive/refs/tags/` 直链替代 API `zipball_url`，减少速率限制与代理兼容问题。
  - 支持从 Release body 或 `SHA256SUMS` / `*.sha256` assets 读取发布包 SHA-256；命中时强制校验，缺失时记录未验证状态。
- [x] **版本号管理修复**：
  - 运行时版本、默认配置、升级工具、README 与兼容层文档统一对齐。
  - 新增 `bin/check_version.php`，发布前检查 `index.php`、`conf.default.php`、`UpgradeCommand` 与文档中的版本一致性。
- [x] **历史遗留安全审查（第一轮）**：
  - 已收敛旧 HTTP helper 的 URL scheme、控制字符、请求头换行、TLS 校验和重定向协议边界。
  - 已加固 base64 解码、`http_location()` 响应头输出和通用 ZIP 解压路径校验。
  - 审查记录见 `docs/security-audit.md`；DB 预处理语句迁移、发布包签名与旧 Zip fallback 去留进入后续排期。

**四层兼容层体系（v4.4.5）：**
- [x] **通用注入器**：
  - `index.php` 通过 `ob_start` 自动向所有 HTML 响应注入 CSRF meta、`bs4-compat.css` 和 `bs4-compat.js`，主题覆盖 `header/footer` 时仍能保留核心兼容能力。
- [x] **PHP 8+ 运行时兼容**：
  - `xiunophp/php8_compat.php` 提供 TypeError 降级日志、`safe_header()` 与常用 polyfill，降低旧插件在 PHP 8+ 下白屏概率。
- [x] **BS4→BS5 CSS/JS 全面兼容**：
  - 补齐 `input-group-prepend/append`、`custom-file`、间距工具类、Tab、`btn-group-toggle`、Modal/Tooltip/Popover/Button jQuery API 代理。
  - 自动处理动态 DOM、CSRF 表单注入、fetch/jQuery POST 请求 token 附带。
- [x] **核心主题 API**：
  - 提供 `theme_register()`、`theme_has()`、`theme_enqueue_style()`、`theme_enqueue_script()` 与 HTMX 消息响应基础能力。

### 阶段五：轻量现代化 (Lightweight Modernization Phase) - [主体完成 / 收尾中]
**目标**：在**零臃肿**的前提下引入现代实践，为开发者提供更好的工具。

**当前状态（2026-05-28）**：阶段五主线已经完成，可以进入收尾冻结。已形成轻量 Helper、前端资源审计、HTMX 只读分页试点、CLI/CI 最小闭环、前端安全守卫和生态样本扫描输入。剩余工作不再阻塞阶段六前置准备，按“阶段五收尾 / 阶段六输入 / 后续性能阶段”分流。

**⚠️ 轻量原则**：每引入一个新依赖，必须回答——"不用它行不行？用原生 PHP 能否实现？"

**⚠️ 兼容性原则**：v4.x 系列将严格保持对旧插件的 API 兼容性。所有核心函数 (`xn_*`, `db_*`) 的签名保持不变。

- [x] **后端微升级（基础完成）**（不引入重框架）：
  - **日志系统**：已提供自研轻量 PSR-3 风格 `XiunoLogger`，保持 `xn_log()` 接口不变。**不用 Monolog**。
  - **HTTP 客户端**：已提供基于 PHP 原生 cURL / stream 的轻量 `XiunoHttp`，支持 JSON/Form 请求；已加固 URL scheme、控制字符、请求头换行、TLS 校验和重定向协议边界。**不用 Guzzle**。
  - **配置管理**：已提供 `XiunoConfig::get('key')` / `conf_get('key')` 轻量读取入口，内部仍读 `$conf` 数组；`Config` 名称空闲时提供兼容别名，后续新代码逐步减少裸全局数组读取。
- [x] **前端精简（主体完成，持续审计）**（减法而非加法）：
  - [x] **前端资源审计脚本**：新增 `php bin/scan_frontend_assets.php`，扫描核心前端资产引用关系并输出本地 `tmp/frontend_assets_scan.json`，删除资源前先看报告，避免误伤兼容层。
  - [x] **历史资源清理（第一轮）**：移除 IE 专用 `es6-shim.js` 加载与文件、未加载且注释化的 `bootstrap-umeditor.css`、非运行时 PSD 源图，以及未被 FontAwesome CSS 引用的冗余字体文件。
  - [x] **本地生态样本验证**：`scan_frontend_assets.php` 支持 `--samples=plugin`，当前 58 个本地插件/主题样本未发现 `vue.js`、`popper-utils.js`、`upload.js`、`filetype.png` 和 `water-small-xiuno.png` 引用，已删除这些未加载资产。
  - [x] **前端资产边界文档**：新增 `docs/frontend-assets.md`，记录扫描方式、删除规则和暂留的兼容资产。
  - **保留 jQuery**：作为底层依赖，确保现有插件可用。
  - **逐步用原生 JS 替代 jQuery**：新功能优先使用 `fetch()`、`querySelector()` 等现代 API。
  - **引入 HTMX 作为渐进增强层**（社区已验证可行：Aether 主题成功实现无刷新跳转、局部更新）：
    - HTMX 仅 14KB gzip，零依赖，无构建步骤，完美契合"轻量"原则。
    - 通过 HTML 属性（`hx-get`、`hx-swap`）实现类 SPA 体验，无需写 JS。
    - 与 Xiuno 的服务端渲染架构天然契合，不引入前端框架。
    - [x] vendoring `htmx.min.js` 2.0.10，并提供 `htmx-xiuno.js` 负责 CSRF 与 `HX-Trigger` 消息桥接。
    - [x] 首个低风险试点：首页与版块页分页使用 `hx-boost` + `hx-select` 局部替换帖子列表区域，保留普通链接降级能力。
    - [x] 第二批只读列表试点：我的帖子、用户帖子分页纳入同一 `#thread-list-region` 局部替换模式。
    - [x] 将线程列表分页 HTMX 属性集中到 `view/htm/htmx_thread_list_pagination_attrs.inc.htm`，避免后续模板复制漂移。
    - [x] HTMX 安全收口：关闭响应脚本/eval 执行；`message()` 的 HTMX 分支改为 `HX-Trigger` + `204 No Content`，避免消息正文被误 swap 进 DOM。
    - [x] CI 增加 `bin/check_htmx_security.php`，防止 HTMX 安全默认值和消息响应语义回退。
    - [x] 建立 HTMX fragment 生命周期约定：swap settle 后触发 `xiuno:fragment-ready`；帖子列表点击、全选、确认链接、管理弹窗入口改为事件委托以适配局部替换。
  - [x] **前端安全审查增量**：
    - 修复发帖页附件上传列表的 DOM XSS 风险：不再把服务端返回的文件名/URL 拼接成 HTML，改为节点构建、文本写入、class 白名单化，并为新窗口链接添加 `rel="noopener noreferrer"`。
    - 加固附件下载 `Content-Disposition` 文件名：移除控制字符并处理引号/反斜杠，避免伪造上传文件名破坏响应头语义。
    - 新增 `bin/check_frontend_security.php` 并接入 CI，防止附件上传列表回退到不安全 HTML 拼接。
    - `bootstrap-plugin.js` 的 Ajax modal 动态脚本执行属于历史插件/主题兼容边界，短期保留；后续在生态兼容矩阵稳定后，再设计更窄的受信任片段协议。
    - 旧 jQuery 版本列为版本治理事项：升级前必须先跑真实插件/主题样本矩阵，不能作为单点安全修复直接推进。
  - [ ] **CSS/JS 压缩（阶段五收尾项）**：编写 PHP 脚本实现静态资源合并压缩，**不引入 Node.js 工具链**。
  - **阶段五收尾**：继续观察帖子列表分页试点与 `xiuno:fragment-ready` 生命周期约定；稳定后再评估帖子详情页分页是否能拆出安全的只读 postlist 区域。
- [ ] **API 持续开发（后续增强项）**（阶段三完成了基础接口，此处扩展和完善）：
  - [x] API 路由最小 smoke test：覆盖默认入口、缺失 action 和非法 action，防止路径拼接边界回退。
  - 扩展 API 覆盖面：用户资料修改、版块管理、附件上传、搜索、通知等。
  - API 版本管理：引入 `/api/v1/` 路径前缀，为未来迭代预留空间。
  - 统一鉴权机制：基于阶段四的安全加固，实现 Token 鉴权 + 接口级权限控制。
  - 请求频率限制（Rate Limiting）：防止 API 滥用，保护服务器性能。
  - 自动生成 API 文档：基于代码注释或约定生成接口文档，降低对接成本。
  
  > 🐛 **源代码 BUG 审计发现（阶段四修复重点）**：
  > ~~1. **PHP 8 废弃特性残留**~~ ✅ 已清理 `set_magic_quotes_runtime` 死代码。
  > ~~2. **CSRF 防护缺失**~~ ✅ 已实现全局 CSRF Token 机制。
  > ~~3. **XSS 过滤脆弱**~~ ✅ 已修复模板输出转义，`jump()` 内部转义。
  > ~~4. **Token 生成机制较弱**~~ ✅ 已从 `md5()` 升级为 `hash('sha256')`。
  > ~~5. **全局参数注入风险**~~ ✅ 已修复 `$_REQUEST` 合并顺序，POST 最高优先级。
- [x] **CLI 脚手架（基础完成）**：
  - 已提供 `php bin/xiuno make:plugin`、`migrate`、`upgrade` 三个基础命令。
  - 已在 CI 中覆盖 CLI 加载、插件脚手架、`migrate --check` 和 `upgrade --check` smoke test。
  - 待完善：无 Composer 依赖时的友好引导、更多插件管理命令。
- [x] **发布/版本治理自动化（最小闭环完成，继续收尾）**：
  - 已将 `php bin/check_version.php` 加入 CI 和发布前检查，防止 `index.php`、`conf.default.php`、升级工具和文档版本漂移。
  - 已固定检查：核心 `php -l`、版本一致性、`xiunophp.min.php` 生成一致性、Hook 文档生成一致性、CLI 命令加载、插件脚手架和 MySQL 8 安装表结构。
  - 收尾项：在线更新流程 smoke test、发布包签名、性能基线退化风险检查。
- [x] **自动化测试 (CI/CD) 最小闭环**：
  - 已引入 GitHub Actions，覆盖版本一致性检查、轻量 Helper smoke test、API 路由 smoke test、核心 PHP 语法检查、Hook 文档生成检查、`xiunophp.min.php` 生成一致性检查、CLI 命令加载、插件脚手架、迁移/升级命令检查模式和 MySQL 8 安装表结构 smoke test。
  - `tool/merge.php` 已固定输出 LF 换行，并通过 `.gitattributes` 约束生成脚本、生成包和 workflow，避免 Windows/Ubuntu 换行差异导致 CI 误报。
  - 待完善：核心 API、在线更新流程与真实插件/主题样本的 smoke test。
- [x] **真实生态样本兼容审计（阶段六输入已建立）**：
  - 以本地 `plugin/` 目录中的社区插件和主题样本作为兼容样本库，但不纳入仓库。
  - 本地兼容审计工具和生成报告只作为维护者工作资料，不纳入仓库；仓库只保留审计结论、字段契约和阶段计划。
  - 已将本地审计结果归一化为兼容矩阵字段：状态、问题类型、最低 Xiuno Next 版本、workaround、修复归属、缺失 Hook 明细和元数据有效性。
  - 2026-05-28 扫描结果：58 个样本（其中 21 个主题型样本），共 983 条兼容发现、22 个 PHP lint 错误；分类以 BS4→BS5（435）和缺失旧 Hook（372）为主，其次是 PHP 8（105）、主题覆盖（33）、CSRF/POST 行为（20）与元数据损坏（18）。
  - 按五类拆分问题：PHP 8 语法/运行时问题、BS4→BS5 前端兼容问题、缺失旧 Hook 契约问题、主题覆盖导致的结构性问题、`conf.json` 元数据损坏问题。
  - 2026-05-28 本地临时 smoke：矩阵中的 6 个 `likely_compatible` 样本均通过编译 smoke；`till_password_strength` 被缺失 Hook 拦截，`ax_comment` 被坏 `conf.json` 拦截，验证矩阵分类有效；临时样本 smoke 工具不纳入仓库。
  - 下一步进入阶段六前置：优先选择高使用率、低侵入修复的样本验证兼容层，不为单个插件写特例。
  - 已恢复低风险历史 Hook 契约：`user_resetpw_password_after.htm` 与 `index_page_end.htm`，优先覆盖旧插件/主题真实引用且不改变核心页面结构的扩展点。
  - 正式输出为插件/主题兼容矩阵；公开清单放到生态重建阶段。

### 阶段六：生态重建 (Ecosystem Phase)
**目标**：终结碎片化，成为社区公认的权威版本。

> ⚠️ **为什么生态在最后？** 此时平台已安全（阶段四）、已有现代化工具链（阶段五）、已有 CLI 和 CI/CD。这是邀请社区加入的最佳时机——给开发者一个值得投入的平台，而非一个半成品。

- [ ] **插件/主题市场规范**：
  - 制定标准化的 `plugin.json` 元数据规范。
  - 建立官方插件索引源 (Registry)，支持 CLI 安装 (`php xiuno plugin:install`)。
  - 原生插件分阶段开放：当前支持 `conf.json` 传统兼容插件；v4.5.x 固定 `plugin.json` 预览规范；v5.0 形成稳定插件市场闭环。
- [ ] **插件与主题兼容矩阵**：
  - 维护一份官方《插件与主题兼容矩阵》，标注每个主流插件/主题在 Xiuno Next 下的兼容状态、最低版本、问题类型和修复建议；内部字段契约见 `docs/ecosystem-compatibility.md`。
  - 兼容矩阵的优先级高于新功能生态扩张：先确认旧生态能活，再邀请开发者写新生态。
  - 为插件和主题开发者分别提供《BS4→BS5 迁移指南》与《Xiuno Next 主题迁移指南》，降低适配成本。
- [ ] **主题开发体系**：
  - 将主题开发作为一等生态入口，而不是插件开发的附属内容。
  - 稳定主题 API：`theme_register()`、资源入队、HTMX 能力声明、消息页覆盖、布局片段约定。
  - 明确主题与插件的边界：主题负责表现层和交互体验，插件负责业务能力与数据扩展，二者通过稳定 Hook/API 协作。
  - 正式主题开发手册放到生态重建阶段，在主题 API 和兼容矩阵稳定后发布。
- [ ] **编辑器标准化**（社区教训：UMEditor/TinyMCE/Markdown 碎片化让插件作者苦不堪言）：
  - 核心仅保留纯文本框（保持轻量），富文本编辑器作为**官方推荐插件**而非内置（避免核心膨胀）。
  - 提供统一的编辑器 API 接口（`XiunoEditor.insert()`、`XiunoEditor.getContent()` 等），所有编辑器插件必须实现此接口。
  - 其他插件只需对接统一 API，无需关心底层编辑器实现——彻底终结适配地狱。
- [ ] **文档中心重构**：
  - 使用轻量方案搭建文档站点（优先考虑纯 PHP/Markdown 方案，避免引入 Node.js 依赖）。
  - [x] 自动扫描代码生成 Hook 点列表，解决开发者查阅难的问题（`php bin/generate_hook_docs.php`）。
  - [x] Hook 索引已覆盖 PHP 注释 Hook 和模板 `<!--{hook ...}-->` Hook，避免开发者查阅时漏掉前端模板扩展点。
  - `开发手册/` 仅作为本地参考资料，不纳入仓库，不在该目录继续维护项目文档。
  - 写给开发者的资料统一放入 `docs/`；当前只维护状态说明、校注和自动生成索引，不提前设计正式开发文档。
  - 正式开发文档中心放到生态重建阶段启动，等 `plugin.json`、主题 API、编辑器 API、插件索引源等边界稳定后再成体系发布。

### 阶段七：持续性能优化 (Continuous Performance Phase)
**目标**：基于阶段三建立的性能基线，持续优化并防止退化。

- [ ] **性能优化**：
  - 首页/帖子列表页面缓存（游客访问直接返回静态 HTML）。
  - 数据库查询分析与索引优化。
  - 可选 Redis 缓存层（保持 MySQL 缓存作为默认，Redis 作为可选加速）。
- [ ] **性能守卫**：
  - CI 中加入性能回归检测，PR 合并前确保核心接口响应时间不退化。

## 4. 立即执行项 (Action Items)

> 按当前风险排序，与阶段五收尾、阶段六前置和发布稳定性对齐：

1. **阶段五收尾冻结**：保留 CSS/JS 压缩、帖子详情页只读 HTMX 评估、在线更新 smoke test 和发布包签名为收尾项；不再把它们作为阶段五主线阻塞项。
2. **在线更新安全加固**：保持 TLS 证书校验开启，已补充 ZIP 路径安全检查、覆盖前备份、最近备份回滚和发布包 SHA-256 元数据校验；下一步补发布包签名，并逐步要求更新/插件包处理路径使用 ZipArchive。
3. **自动化测试最小闭环**：已建立核心 `php -l`、版本一致性、轻量 Helper smoke test、API 路由 smoke test、前端/HTMX 安全守卫、Hook 文档生成检查、`xiunophp.min.php` 生成一致性检查、CLI 命令加载检查、插件脚手架 smoke test、迁移/升级命令检查模式和 MySQL 8 安装表结构 smoke test；生成包已固定 LF 输出以保证跨平台一致；下一步逐步覆盖在线更新流程和真实插件/主题样本。
4. **依赖与 PHP 版本矩阵**：以 PHP 8.0+ 为最低运行线，Docker 默认 PHP 8.2，CI 覆盖 8.0/8.2/8.3/8.4/8.5，并在 CI 中执行 Composer validate/install；Dependabot 仅用于每周检查 Composer 与 GitHub Actions 更新，Composer 主版本升级默认忽略并改由人工评估，依赖升级优先保证旧插件兼容。后续需要决定是否提交 `composer.lock` 来固定应用依赖。
5. **社区资料边界**：`开发手册/` 保留为本地参考且不进仓库；Xiuno Next 差异说明迁移到 `docs/`，避免在参考资料目录里继续工作。
6. **前端精简审计**：持续使用 `php bin/scan_frontend_assets.php --samples=plugin` 检查核心资产与本地插件/主题样本引用；当前扫描已无疑似无用资产，后续只在删除或替换资源前运行。
7. **生态样本兼容矩阵**：已用本地插件/主题样本库形成初步问题分类，并沉淀为矩阵字段（状态、问题类型、最低 Xiuno Next 版本、workaround、修复归属）；本地兼容测试脚本和生成报告不纳入仓库，下一步优先处理 `conf.json` 元数据损坏、缺失旧 Hook 契约和可重复样本 smoke，不急于发布正式开发文档。
8. **原生插件与主题预览规范**：先维护状态说明和 Hook 索引，再补 `plugin.json` 草案、主题 API 草案、CLI 原生模板和插件/主题 smoke test。
9. **数据库层长期治理**：短期继续收敛直接 SQL 拼接点，长期把高风险写路径迁移到兼容旧插件的预处理语句层；在迁移完成前，不宣称 SQL 注入问题已被彻底解决。

## 5. 决策原则 (Decision Principles)

> 在做每一个技术决策时，按以下优先级排序：

1. **不引入** > 引入轻量依赖 > 引入重框架
2. **原生 PHP** > 自研微组件 > 第三方包
3. **过程式 + 静态方法** > 轻量 OOP > 复杂设计模式
4. **零配置可运行** > 需要构建步骤 > 需要 Node.js 环境

## 6. 社区之声 (Community Insights)

> 以下洞察来自社区开发者的真实经验，应作为所有技术决策的参照。

- **"约定优于配置"** — 能用文件扫描解决的，绝不做配置页面；能硬编码的，绝不做动态配置。（@Tillreetree）
- **"碎片化是最大的敌人"** — 同一 Bug 被 5 个分支各修一遍，同一需求被重复造轮 5 次。Xiuno Next 必须成为终结碎片化的那个版本。
- **"技术深度应该用来隐藏复杂性，而不是展示复杂性"** — 用户不需要理解数据结构、配置权限系统、调试复杂逻辑。
- **"Xiuno 的价值不在追赶 WordPress，而在提供另一种可能性：一个普通人也能完全理解、完全掌控的论坛系统"** — 这是我们的核心定位。
- **"编辑器碎片化"** — 社区 4-5 种编辑器共存，插件作者被迫写重复适配代码。必须收敛标准。
- **"Bootstrap 生态锁定"** — 所有插件深度绑定 BS4，升级 BS5 必须提供兼容层，否则就是自绝于生态。

---
*"不破不立，在保持轻量的基础上拥抱未来。"*
