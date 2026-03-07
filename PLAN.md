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
  - ⚠️ 压测脚本框架已就位，实际基准数据待部署后采集填入。

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

### 阶段五：轻量现代化 (Lightweight Modernization Phase)
**目标**：在**零臃肿**的前提下引入现代实践，为开发者提供更好的工具。

**⚠️ 轻量原则**：每引入一个新依赖，必须回答——"不用它行不行？用原生 PHP 能否实现？"

**⚠️ 兼容性原则**：v4.x 系列将严格保持对旧插件的 API 兼容性。所有核心函数 (`xn_*`, `db_*`) 的签名保持不变。

- [ ] **后端微升级**（不引入重框架）：
  - **日志系统**：自研轻量 PSR-3 兼容 Logger（~100 行），替代现有文件写入，保持 `xn_log()` 接口不变。**不用 Monolog**。
  - **HTTP 客户端**：基于 PHP 原生 cURL 封装轻量 HTTP 类（~200 行），支持 JSON/Form 请求。**不用 Guzzle**。
  - **配置管理**：将 `$conf` 全局变量封装为 `Config::get('key')` 静态方法，内部仍读数组，但提供 IDE 提示和类型安全。
- [ ] **前端精简**（减法而非加法）：
  - **保留 jQuery**：作为底层依赖，确保现有插件可用。
  - **逐步用原生 JS 替代 jQuery**：新功能优先使用 `fetch()`、`querySelector()` 等现代 API。
  - **引入 HTMX 作为渐进增强层**（社区已验证可行：Aether 主题成功实现无刷新跳转、局部更新）：
    - HTMX 仅 14KB gzip，零依赖，无构建步骤，完美契合"轻量"原则。
    - 通过 HTML 属性（`hx-get`、`hx-swap`）实现类 SPA 体验，无需写 JS。
    - 与 Xiuno 的服务端渲染架构天然契合，不引入前端框架。
  - **CSS/JS 压缩**：编写 PHP 脚本实现静态资源合并压缩，**不引入 Node.js 工具链**。
- [ ] **API 持续开发**（阶段三完成了基础接口，此处扩展和完善）：
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
- [ ] **CLI 脚手架**：
  - 开发 `xiuno-cli` 工具，支持 `php xiuno make:plugin` 快速创建插件结构。
- [ ] **自动化测试 (CI/CD)**：
  - 引入 GitHub Actions，对核心功能进行自动化测试，确保 PR 质量。

### 阶段六：生态重建 (Ecosystem Phase)
**目标**：终结碎片化，成为社区公认的权威版本。

> ⚠️ **为什么生态在最后？** 此时平台已安全（阶段四）、已有现代化工具链（阶段五）、已有 CLI 和 CI/CD。这是邀请社区加入的最佳时机——给开发者一个值得投入的平台，而非一个半成品。

- [ ] **插件/主题市场规范**：
  - 制定标准化的 `plugin.json` 元数据规范。
  - 建立官方插件索引源 (Registry)，支持 CLI 安装 (`php xiuno plugin:install`)。
- [ ] **插件兼容性清单**：
  - 维护一份官方《插件兼容性清单》，标注每个主流插件在 Xiuno Next 下的兼容状态。
  - 为插件开发者提供《BS4→BS5 迁移指南》，降低适配成本。
- [ ] **编辑器标准化**（社区教训：UMEditor/TinyMCE/Markdown 碎片化让插件作者苦不堪言）：
  - 核心仅保留纯文本框（保持轻量），富文本编辑器作为**官方推荐插件**而非内置（避免核心膨胀）。
  - 提供统一的编辑器 API 接口（`XiunoEditor.insert()`、`XiunoEditor.getContent()` 等），所有编辑器插件必须实现此接口。
  - 其他插件只需对接统一 API，无需关心底层编辑器实现——彻底终结适配地狱。
- [ ] **文档中心重构**：
  - 使用轻量方案搭建文档站点（优先考虑纯 PHP/Markdown 方案，避免引入 Node.js 依赖）。
  - 自动扫描代码生成 Hook 点列表，解决开发者查阅难的问题。

### 阶段七：持续性能优化 (Continuous Performance Phase)
**目标**：基于阶段三建立的性能基线，持续优化并防止退化。

- [ ] **性能优化**：
  - 首页/帖子列表页面缓存（游客访问直接返回静态 HTML）。
  - 数据库查询分析与索引优化。
  - 可选 Redis 缓存层（保持 MySQL 缓存作为默认，Redis 作为可选加速）。
- [ ] **性能守卫**：
  - CI 中加入性能回归检测，PR 合并前确保核心接口响应时间不退化。

## 4. 立即执行项 (Action Items)

> 按优先级排序，与阶段三（进行中）对齐：

1.  **插件安全模式**：实现安全模式启动机制 + 错误隔离（白屏是最致命的问题，管理员连后台都进不去）。
2.  **BS4 兼容层**：编写 `data-toggle` → `data-bs-toggle` 自动转换垫片（阻塞插件生态的头号问题）。
3.  **建立性能基线**：对当前版本进行首次基准测试，记录数据作为优化参照。
4.  **HTML 最佳实践**：为模板添加 `loading="lazy"`、`aria` 属性（快速见效，几小时内完成）。
5.  **密码安全迁移**：设计 `password_hash` 渐进式迁移方案（阶段四首要任务）。

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