# Xiuno Next 安全审查记录

本文记录阶段五轻量现代化期间的历史遗留安全审查。它不是正式安全白皮书，只作为开发决策和后续排期依据。

## 本轮已修复

- 旧 `http_get()` / `http_post()` / `https_get()` / `https_post()` 只允许 HTTP/HTTPS URL，并拒绝控制字符。
- 旧 HTTPS cURL 请求默认开启证书校验，重定向协议限制为 HTTP/HTTPS。
- 旧 HTTP 请求中的 Cookie 和 User-Agent 会移除换行符，降低请求头注入风险。
- `param_base64()` 和 `base64_decode_file_data()` 改为严格 base64 解码，并正确支持无 `data:*;base64,` 前缀的原始 base64。
- `http_location()` 移除 CR/LF，避免响应头注入。
- `xn_zip()` / `xn_unzip()` 以及旧 Zip fallback 增加 zip entry 路径校验，拒绝绝对路径、盘符路径、空路径、NUL 字节和 `../` 路径穿越。
- 2026-05-29 对照 CNVD/NVD 中 Xiuno BBS 4.x 历史漏洞复查：
  - CVE-2018-8942 / CNVD-2018-07560：后台 `sitename` 保存与前台输出存在同类风险，已修复。`sitename` 保存前转为纯文本并限制长度；前台/后台 `<title>`、meta 文本和首页站点名统一按 HTML 上下文转义；`sitebrief` 保留 HTML 能力但通过 `xn_html_safe()` 白名单清洗。
  - CNVD-2019-01348 / CNVD-2018-26238 / CNVD-2018-08177：当前安装器已依赖 `conf/conf.php` 阻断重装，本轮移除 `DEBUG == 2` 的调试绕过语义，并在安装数据库 POST 分支增加二次阻断。生产部署仍建议删除或 Web 层封禁 `install/`。
  - CVE-2018-15559 / CNVD-2018-16946：核心普通用户发帖路径会在入库时生成 `message_fmt`，文本模式转义，HTML 模式普通用户经 `xn_html_safe()`；未发现原版编辑器存储型 XSS 的普通用户路径直接残留。本轮补上服务端附件列表文件名输出转义。管理员组 HTML 帖、历史恶意 `message_fmt` 和插件 hook 改写仍列为后续治理项。
  - CVE-2019-19998 / CNVD-2020-22683：核心仓库未跟踪原风险描述常见的 `xn_wechat_public` XML 入口，也未发现核心 XML 外部实体解析入口。本地忽略插件样本中存在 `DOMDocument::loadHTML()` 使用，作为生态样本风险记录，不纳入核心发行修复。

## 继续观察

- 旧 Zip fallback 仍是兼容兜底代码，已补路径校验，但长期建议要求 PHP ZipArchive 扩展作为更新/插件包处理的硬依赖。
- 在线更新已具备 TLS、ZIP 魔数、ZIP 路径校验、覆盖前备份、回滚和 SHA-256 元数据校验；发布约定见 `docs/update-integrity.md`。缺少 SHA-256 元数据时默认阻断更新；过渡期必须显式设置 `allow_unverified_update` 才能继续，且仍会写入未验证日志。发布包签名仍待发布流程稳定后启用。
- DB helper 仍以 `addslashes()` 拼 SQL。短期继续收敛直接 SQL 拼接点；长期应设计兼容旧插件的预处理语句迁移层。
- 旧迁移工具位于 `tool/`，包含大量一次性导入 SQL 拼接。默认不进入 Web 请求路径，后续需要单独标注为离线维护工具，并补充使用风险说明。

## 审查命令

```powershell
rg -n "CURLOPT_SSL_VERIFYPEER|eval\s*\(|unserialize\s*\(|create_function\s*\(|addslashes\s*\(|ZipArchive|extractTo" -g "*.php" -g "!vendor/**" -g "!plugin/**"
```

```powershell
php bin/check_lightweight_helpers.php
php bin/check_version.php
php bin/check_frontend_security.php
```

```powershell
rg -n "simplexml_load_string|simplexml_load_file|DOMDocument|LIBXML_NOENT|xml_parser_create" -g "*.php" -g "!vendor/**" -g "!plugin/**"
```

## 2026-05-28 前端 DOM XSS 复查

- 已修复发帖页附件上传完成后的列表渲染：`message.orgfilename` 来自浏览器文件名，服务端按原值写入 JSON，旧代码把 `message.url`、`message.filetype`、`message.orgfilename` 拼接成 HTML 后 `.append()`，存在 DOM XSS/属性注入风险。
- 现改为使用 jQuery 创建节点、`document.createTextNode()` 写入文件名、过滤文件类型 class，并为新窗口附件链接添加 `rel="noopener noreferrer"`。
- 新增 `bin/check_frontend_security.php` 并纳入 CI，用于守住附件上传列表的安全渲染约定，避免后续回退到 HTML 字符串拼接。
- 已加固附件下载响应头：`Content-Disposition` 文件名通过 `attach_download_filename()` 移除控制字符并处理引号/反斜杠，降低伪造文件名造成响应头注入或头部格式破坏的风险；`bin/check_lightweight_helpers.php` 增加对应 smoke test。
- 继续观察：`view/js/bootstrap-plugin.js` 的 Ajax modal 会解析受信任页面片段并执行其中脚本，这是旧插件/主题运行模型的一部分，短期不直接移除；后续应在生态兼容阶段结合插件/主题矩阵逐步收敛信任边界。

## 2026-05-28 生态样本安全/兼容观察

- 本地审计覆盖 58 个社区插件/主题样本，其中 21 个呈主题型特征；样本库、临时测试脚本和生成报告均不纳入仓库。
- 本轮发现 983 条兼容/风险信号、22 个 PHP lint 错误。分类统计：BS4→BS5 435 条、缺失旧 Hook 372 条、PHP 8 105 条、主题 header/footer 覆盖 33 条、CSRF/POST 行为 20 条、元数据损坏 18 条。
- 结论：短期继续增强核心兼容层和兼容矩阵，不对单个第三方插件/主题做仓库内特例；正式插件/主题开发手册仍放到生态重建阶段，在兼容边界稳定后发布。

## 2026-05-29 CNVD 历史漏洞复查

- 重装类漏洞：`install/index.php` 当前在入口和数据库安装 POST 分支均以 `conf/conf.php` 存在为硬阻断，不再保留调试绕过。风险主要转为部署层问题：如果生产环境删除或损坏 `conf/conf.php`，安装器仍可能重新开放；上线文档应继续强调删除或封禁 `install/`。
- 后台站点名 XSS：确认存在并已修复。修复采取“保存端收窄 + 输出端转义 + CI smoke 守护”的方式，避免历史配置值或未来模板回退再次触发。
- 编辑器/帖子 XSS：普通用户路径未见原 CVE 直接残留；`gid == 1` HTML 帖直通、历史 `message_fmt` 和插件 hook 后处理属于高权限/历史数据/生态边界风险，后续在阶段六结合插件与主题兼容矩阵继续收敛。
- XML/XXE：核心无对应 XML 输入解析面；插件和主题开发规范后续需要明确禁止对用户输入使用 `simplexml_load_string()`、`DOMDocument::loadXML()`、`LIBXML_NOENT`，解析 HTML/XML 片段时应禁止 DOCTYPE/ENTITY 并使用 `LIBXML_NONET`。

## 2026-05-29 路由写操作方法加固

- 附件上传/删除路由已强制 POST：`route/attach.php` 的 `create` 与 `delete` 继续受全局 CSRF 保护。
- 后台批量主题操作与论坛删除已强制 POST：覆盖 `admin/route/thread.php?action=operation` 与 `admin/route/forum.php?action=delete`。
- 前台、后台与安装入口加入 GET/POST 白名单，避免旧式 `if(GET) ... else ...` 分支把未知 HTTP 方法误判为写提交。
- 后台主题队列写入已收紧：`thread-scan` 强制 POST，`thread-operation-*` 先校验操作类型再消费队列，队列耗尽后主动销毁。
- 移除后台首页旧版 `custom.xiuno.com` 明文远程版本脚本，后台退出改为 POST。
- 安装完成后写入 `conf/.installed.lock`，让已安装状态在 `conf/conf.php` 之外再有一层阻断。
- 附件读删加入统一文件名/路径校验，头像上传改为验证真实图片并重编码为 PNG。
- 新增 `bin/check_route_method_safety.php` 并纳入 CI，作为阶段六前置硬化闸门的回归守卫。

## 2026-05-29 Admin Thread Queue Hardening

- Admin thread batch scan/operation now carries a per-page `queueid` instead of relying on the single legacy `thread_find_queueid` session slot.
- `thread-scan`, `thread-operation-*`, and `thread-found-*` validate that the submitted queue id belongs to the current admin session before reading, writing, or consuming queue rows.
- Opening a second admin thread search page no longer destroys or overwrites the first page's queue. Completed operations destroy only their own queue id.
- `bin/check_admin_thread_queue_safety.php` is part of CI so this multi-tab/duplicate-click boundary cannot silently regress.

## 2026-05-29 Compatibility Boundary Hardening

- `bs4-compat.js` now limits automatic CSRF header injection to same-origin jQuery/fetch POST requests. Cross-origin requests no longer receive `X-CSRF-TOKEN`.
- Plugin upgrade now reloads the new package metadata after replacement and re-checks dependencies before install. A new missing dependency restores the old package directory and previous plugin state.
- Plugin PHP guards now catch standalone calls to PHP 8 removed functions such as `each()`, `create_function()`, `mysql_*()`, `ereg*()`, `split*()`, and `get_magic_quotes_gpc()` before install/enable/upgrade can mark a package active.
- Install/upgrade dependency checks now also reject target plugin metadata errors, so malformed self `conf.json` data cannot be silently marked installed/enabled. Upgrade preflight still allows the replacement package to repair old self metadata, then re-applies the strict check after loading the new `conf.json`.
- Plugin dependency keys must now match the same safe plugin directory shape as route parameters; invalid dependency names become an explicit blocking status instead of being treated as ordinary missing plugins.
- Plugin write actions now reject stale or repeated state transitions before running lifecycle files, so manual POSTs cannot re-run install/unstall/enable/disable/upgrade in an invalid state.
- Plugin dependency prompts now only link dependencies that have a local or official detail page; missing packages with no known source render as text with status instead of sending admins to a dead `plugin-read-*` page.
- Generic `data-method="post"` failures now use the project's modal alert instead of native `alert()`, so dependency guidance returned by plugin actions can render links and status text.
- Installing a same-type plugin/theme now checks reverse dependencies before auto-uninstalling the old package, skips already uninstalled packages, and runs the old package's `unstall.php` lifecycle with replacement rollback context.
- Plugin lifecycle execution now arms a shutdown rollback guard before including third-party lifecycle files, so direct `message()` / `exit` paths restore pending state/package snapshots and release the shared plugin task lock.
- Same-type plugin/theme replacement now treats automatic old-package uninstall as one rollback batch. If any old package write or `unstall.php` lifecycle fails after earlier candidates were already touched, the new package state and all old same-type states are restored together.
- Admin plugin list/detail templates now escape plugin metadata from `conf.json` before rendering and validate external brief links before placing them in `href` attributes.
- Theme/plugin overwrite resolution now skips symlink overwrite files, and the global compatibility injector rejects non-relative `view_url` schemes/protocol-relative paths before emitting compatibility CSS/JS. If an overwritten theme omits closing `body/html` tags, the injector appends the JS shim rather than silently dropping it.
- Online update now hard-fails ZIP extraction errors, restores the backup if core file copy or `conf/conf.php` version writes fail, and records newly added files so rollback can remove them instead of leaving stale files behind.
- Online update source package selection now requires exactly one top-level directory plus core sentinel files/directories (`index.php`, `conf/conf.default.php`, `admin/`, `model/`, `view/`, `xiunophp/`), avoiding loose partial-package matches.
- Online update rollback now reads the selected backup from POST only, avoiding the old `param(..., 'POST')` confusion where the third argument controlled escaping rather than request source.
- Online update now fails closed when no release SHA-256 metadata is available. Administrators can explicitly enable the legacy permissive path with `allow_unverified_update`, but the update log still records `checksum_verified=0` and the local package hash.
- Online update now validates Release tags before building archive URLs, escapes version strings in the admin page, explicitly enables TLS host-name verification in stream fallbacks, rejects oversized update packages, and verifies that `conf.php` setting writes actually replaced or appended a key.
- Online update redirects are now followed manually instead of relying on automatic cURL/stream redirect behavior. Each `Location` hop must resolve to a public HTTPS URL, cannot downgrade to HTTP, cannot target localhost/private/reserved IPs, and is capped by a redirect count before the response body is accepted.
- Installer DB setup now uses an `install_task` lock with shutdown release, validates database names before `CREATE DATABASE`, creates missing databases with an explicit whitelisted charset/collation clause, and stops when `conf/conf.php` copy or replacement fails.
- Installer POST setup now uses an installer CSRF token and POST-only field reads for language/database/admin fields. Database driver, engine, host, port, and database name are validated before the PDO DSN or `CREATE DATABASE` path can consume them.
- CLI migration execution now revalidates migration object signatures immediately before calling `up()`, not only during `--check`. Destructive legacy upgrade smoke requires explicit `XIUNO_ALLOW_DESTRUCTIVE_SMOKE=1` and a test-looking database name.
- CLI legacy upgrade now stops after each staged failure. If configuration completion fails, schema/migration steps no longer run; if migrations fail, cleanup, upgrade metadata, and version writes no longer continue, reducing half-upgraded states.
- Persistent login cookies now use HttpOnly, SameSite=Lax, HTTPS-aware Secure, and a user-agent fingerprint in newly issued encrypted tokens while still accepting legacy three-field tokens until they naturally rotate.
- Registration and password-reset email codes now use `random_int()`, store an issuance timestamp, expire after five minutes, cap failed verification attempts, and cap sends to five per email/purpose per session hour.
- `user-synlogin` now requires a five-minute incoming token, validates token structure, normalizes `return_url` to public HTTP(S) destinations without credentials/local/private hosts, and appends the encrypted response token with URL encoding instead of raw string concatenation.
- `bin/check_frontend_security.php`, `bin/check_plugin_dependency_status.php`, `bin/check_plugin_task_safety.php`, `bin/check_plugin_package_rollback.php`, `bin/check_update_task_safety.php`, `bin/check_install_safety.php`, `bin/check_cli_upgrade_safety.php`, and `bin/check_user_auth_safety.php` guard these contracts in CI.

## 2026-05-30 Admin Auth Cookie Hardening

- Admin authentication now reads `bbs_admin_token` only from cookies. Request parameters can no longer override the admin cookie during token verification or token rotation.
- Admin token parsing now rejects malformed decrypted payloads before consuming IP/time fields.
- `admin_bind_ip` now expires admin tokens immediately on IP mismatch when binding is enabled; the one-hour timeout remains active for all admin sessions.
- Added shared `xn_setcookie()` / `xn_cookie_secure()` helpers. Admin token cookies, session cleanup cookies, installer language cookies, and the session `cookie_test` probe now use HttpOnly, SameSite=Lax, and HTTPS-aware Secure defaults.
- PHP session cookies now set SameSite=Lax and derive Secure from the same HTTPS/proxy-aware helper.
- `bin/check_admin_auth_safety.php` is part of CI to keep the admin cookie contract, IP-binding behavior, and session cookie flags from regressing.

## 2026-05-30 Remote Request Review Notes

- The online updater main chain remains protected by HTTPS-only requests, manual redirect handling, public-host checks, package size limits, ZIP validation, default SHA-256 fail-closed behavior, and rollback.
- Follow-up closed for updater requests: every update/proxy URL hop now resolves A/AAAA records before connecting, rejects localhost/private/reserved/link-local style targets through the public IP filter, handles IPv4-mapped IPv6 addresses, and pins the cURL connection with `CURLOPT_RESOLVE`. Online updates now require cURL because the stream fallback cannot reliably pin a prevalidated IP address.
- Legacy official plugin marketplace remote operations now fail closed. Download, upgrade package retrieval, payment checks, and QR-code URL requests no longer contact the old `http://plugin.xiuno.com/` endpoints. A future registry/market rebuild must be HTTPS-only and require package checksum/signature metadata before this path can be reopened.
- Shared task locks now write a per-request owner token and `xn_lock_end()` removes only locks owned by the current request. This prevents an old long-running plugin/update/install task from deleting a newer task's lock after the original lock TTL has expired and another request has acquired the lock.
- `data-method="post"` links now share a front-end pending guard. Plugin/theme install, enable, disable, uninstall, upgrade, and other POST-link actions ignore duplicate clicks while a request is in flight, expose `aria-disabled`, and restore the link on failed responses.
- Same-type plugin/theme replacement now re-checks the newly installed package's dependencies after automatic old-package uninstall. If the replacement batch removes something the new package still needs, the new package and every old same-type package touched in the batch are restored before the error is shown.
