# 插件/主题兼容性审计报告

**日期**: 2026-03-07  
**扫描范围**: `plugin/` 目录下 15 个插件/主题，以及核心模板

---

## 一、Bootstrap 4→5 HTML 属性问题

**影响范围**: 87 处匹配，38 个文件  
**严重程度**: ⚠️ 中（bs4-compat.js 已部分兼容）

现有 `bs4-compat.js` 垫片自动转换 `data-toggle` → `data-bs-toggle` 等 4 个属性，
但以下插件仍使用 BS4 属性，依赖垫片的运行时转换：

| 插件 | 问题 | 位置 |
|------|------|------|
| `zaesky_threadrank` | `data-toggle="tab"` | `hook/index_site_brief_after.htm` (2处) |
| `xn_search` | `data-toggle="tooltip"` | `hook/header_nav_user_start.htm` |
| `huux_notice` | `data-toggle="tooltip"` | `hook/header_nav_user_start.htm` |
| `tt_credits` | `data-toggle="tooltip"` | `hook/thread_list_inc_subject_after.htm` |
| `haya_favorite` | `data-toggle="dropdown"`, `data-dismiss` | `view/htm/`, `hook/` (3处) |

**结论**: bs4-compat.js 已覆盖，不需要修改插件代码，但垫片必须在 BS5 JS 之前加载。

---

## 二、Bootstrap 4→5 CSS 类名变更（最大问题）

**影响范围**: 237+ 处匹配，88+ 个文件  
**严重程度**: 🔴 高（无 CSS 兼容层，样式直接失效）

### 受影响的 BS4 类名 → BS5 对应关系

| BS4 类名 | BS5 替代 | 影响插件数 |
|-----------|----------|-----------|
| `form-group` | `mb-3` | 几乎所有设置页面 |
| `custom-select` | `form-select` | `xn_search`, `haya_*`, 核心 `post.htm` |
| `custom-control` | `form-check` | `haya_post_like`, `haya_favorite`, `xn_search` |
| `badge-danger/pill/primary` | `bg-danger/rounded-pill/bg-primary` | `huux_notice`, `tt_ranklist`, `zaesky_*` |
| `float-left/right` | `float-start/end` | `haya_post_like` |
| `text-left/right` | `text-start/end` | `xn_search` |
| `dropdown-menu-right` | `dropdown-menu-end` | `haya_favorite`, `xn_digest` |
| `font-weight-bold` | `fw-bold` | 少量 |
| `sr-only` | `visually-hidden` | 少量 |
| `btn-block` | `d-grid` 包裹 | `xn_digest`, `tt_credits` |
| `media` | 已移除 | `haya_post_like` |

### 核心模板也存在残留

- `view/htm/post.htm` 第 61 行: `class="custom-select"`

---

## 三、Bootstrap Modal JS API 问题

**影响范围**: ~10 处  
**严重程度**: ⚠️ 中

| 插件 | 问题 |
|------|------|
| `tt_credits` | JS 中使用 `$('.modal').modal('hide')` (BS4 API) |
| `ax_notice_sx` | `data-dismiss="modal"` (需 `data-bs-dismiss`) |

**注**: bs4-compat.js 已转换 `data-dismiss` 属性，但 JS 调用 `.modal()` 需要 BS5 API。

---

## 四、CSRF 表单兼容问题

**影响范围**: 49 个表单 POST，0 个包含 `_token`  
**严重程度**: 🔴 高

**发现**: 所有插件表单 `<form method="post">` 均不包含 CSRF hidden 字段。

- **AJAX 提交**（`$.xpost`）：已通过 `$.ajaxSetup` 自动附带 `X-CSRF-TOKEN` 头 → ✅ 兼容
- **传统表单提交**：无 `_token` 字段，也无 `X-CSRF-TOKEN` 头 → ❌ 会被 `csrf_check()` 拦截

**核心模板同样缺失**: `post.htm`, `user_login.htm`, `user_create.htm` 等 12 个核心表单也没有 `_token` hidden 字段。

**原因分析**: 核心表单实际通过 JS (`$.xpost`) 提交，所以通过 header 传 CSRF token。但如果 JS 出错回退到原生提交，会失败。

---

## 五、PHP 8 兼容性

**严重程度**: ✅ 低

- 无 `each()`, `mysql_*`, `ereg()` 等 PHP 8 已移除的函数调用
- `count()` 在部分插件中可能接收 `null`（PHP 8.0 会 warning）

---

## 六、修复建议（按优先级排序）

### P0：CSS 兼容层（一次性解决所有 BS4 CSS 类名问题）
在核心 CSS 中添加 BS4→BS5 类名别名，使旧插件无需修改即可正常显示。

### P1：CSRF 表单兼容
为所有 `<form method="post">` 自动注入 `_token` hidden 字段（通过 JS 全局拦截或 PHP 辅助函数）。

### P2：核心模板残留清理
修复 `post.htm` 等核心模板中的 BS4 类名残留。

### P3：Modal JS API 兼容
在 bs4-compat.js 中添加 `.modal()` 等 jQuery 方法的 BS5 代理。
