# 兼容层技术文档 (Compatibility Layer)

> **版本**: v4.4.5  
> **适用对象**: 主题开发者、插件开发者、核心维护者

## 概述

Xiuno Next 内置了三层兼容层体系，目标是在**不修改第三方插件源码**的前提下，让旧插件和主题在新版核心（PHP 8+、Bootstrap 5）上正常运行。

```
┌─────────────────────────────────────────────┐
│  第四层：主题 API（核心对主题的支持）          │  model/misc.func.php
│  message() HTMX · url() 扩展 · 资源注册       │
├─────────────────────────────────────────────┤
│  第三层：前端兼容（CSS + JS）                  │  view/css/bs4-compat.css
│  BS4→BS5 类映射 · 资源 404 降级 · API 代理     │  view/js/bs4-compat.js
├─────────────────────────────────────────────┤
│  第二层：运行时兼容（PHP）                     │  xiunophp/php8_compat.php
│  TypeError 捕获 · safe_header · polyfill       │
├─────────────────────────────────────────────┤
│  第一层：通用注入器（ob_start）                │  index.php
│  自动注入 CSRF token + bs4-compat 到所有主题   │
└─────────────────────────────────────────────┘
```

---

## 第一层：通用注入器

**文件**: `index.php`（`ob_start` 回调）  
**加载方式**: 核心入口自动启用，无需任何模板 hook。

### 1.1 设计动机

任何主题只要覆盖了 `header.inc.htm` 或 `footer.inc.htm`，核心模板中加载的 CSRF token、`bs4-compat.css`、`bs4-compat.js` 全部丢失。这会导致：
- 所有 POST 操作（签到、私信、点赞等）报「CSRF token 校验失败」
- BS4→BS5 CSS/JS 兼容失效
- 资源降级功能失效

### 1.2 工作原理

```
index.php ob_start(callback)
    ↓
主题输出 HTML（可能有自己的 ob_start 嵌套）
    ↓
callback 处理最终 HTML：
    ├─ </head> 之前注入：
    │   ├─ <meta name="csrf-token">（如果主题未提供）
    │   └─ bs4-compat.css（如果主题未加载）
    │
    └─ </body> 之前注入：
        ├─ var csrf_token + jQuery AJAX 拦截器（如果主题未设置）
        └─ bs4-compat.js（如果主题未加载）
```

### 1.3 去重机制

注入器通过 `strpos()` 检查输出 HTML 中是否已存在对应内容。如果主题（或默认模板）已经加载了某项资源，注入器自动跳过，**不会重复加载**。

### 1.4 嵌套 ob_start 兼容

PHP 输出缓冲支持嵌套。核心的 `ob_start` 在最外层，主题的 `ob_start`（如 HTML 压缩）在内层。内层处理完毕后 flush 到外层，注入器处理最终完整 HTML。

---

## 第二层：PHP 运行时兼容

**文件**: `xiunophp/php8_compat.php`  
**加载方式**: 由 `xiunophp/xiunophp.php` 自动 include，不依赖任何插件。

### 2.1 TypeError 异常捕获

旧插件在 PHP 8+ 下常因类型严格化产生 `TypeError`（如 `header()` 传入非 string），导致 500 白屏。

兼容层通过 `set_exception_handler()` 捕获 `TypeError`：
- **生产模式**: 静默记录日志到 `log/php8_compat.log`
- **调试模式**: 输出可读的警告信息（不再白屏）

```php
// 旧插件代码（会在 PHP 8+ 抛 TypeError）
header('X-Debug-Code: ' . $code);  // $code 是 int，PHP 8 要求 string

// 兼容层自动捕获，降级为警告，不再 500
```

### 2.2 safe_header()

安全的 `header()` 包装器，自动将参数转为 string。

```php
safe_header('X-Custom: ' . $value);  // 即使 $value 不是 string 也不会报错
```

### 2.3 PHP 7↔8 Polyfill

为运行在 PHP 7.x 的服务器提供 PHP 8 新函数：

| 函数 | 原生版本 | 说明 |
|------|---------|------|
| `str_contains($haystack, $needle)` | PHP 8.0 | 字符串包含检测 |
| `str_starts_with($haystack, $needle)` | PHP 8.0 | 字符串前缀检测 |
| `str_ends_with($haystack, $needle)` | PHP 8.0 | 字符串后缀检测 |
| `array_is_list($array)` | PHP 8.1 | 判断是否为连续索引数组 |

---

## 第三层：前端兼容（CSS + JS）

### 3.1 CSS 兼容层

**文件**: `view/css/bs4-compat.css`  
**加载方式**: 由通用注入器自动注入 `</head>` 之前（也在默认 `header.inc.htm` 中加载）。

#### Bootstrap 4 → 5 类名映射

| BS4 类名 | 映射方式 | 说明 |
|----------|---------|------|
| `.form-group` | margin-bottom 保留 | BS5 移除了此类 |
| `.custom-select` | 映射到 `.form-select` 样式 | BS5 改名 |
| `.custom-control-*` | 映射到 `.form-check-*` 样式 | BS5 改名 |
| `.badge-*` | 映射到 `.bg-*` 样式 | BS5 改用 utility |
| `.float-left/right` | 映射到 `float` 属性 | BS5 改为 `.float-start/end` |
| `.font-weight-*` | 映射到 `font-weight` 属性 | BS5 改为 `.fw-*` |
| `.sr-only` | 映射到 visually-hidden 样式 | BS5 改名 |
| `.btn-block` | `width: 100%` | BS5 移除 |
| `.close` | 映射到 `.btn-close` 样式 | BS5 改名 |
| `.media` | flexbox 布局 | BS5 移除此组件 |
| `.jumbotron` | padding + bg 保留 | BS5 移除此组件 |
| `.hidden-xs/sm/md/lg/xl` | 映射到 `display: none` 断点 | BS5 改用 `.d-none` |
| `.input-group-prepend` | `display: flex` 布局 | BS5 移除此包裹层 |
| `.input-group-append` | `display: flex` 布局 | BS5 移除此包裹层 |
| `.custom-file` | 文件上传组件容器 | BS5 改用 `.form-control[type=file]` |
| `.custom-file-input` | 文件上传 input 样式 | BS5 移除 |
| `.custom-file-label` | 文件上传标签样式 | BS5 移除 |
| `.form-row` | flex 行布局 | BS5 改为 `.row.g-3` |

#### 旧插件工具类

| 类名 | 说明 |
|------|------|
| `.text-gray` | 灰色文本（`#6c757d`） |
| `.avatar-1` ~ `.avatar-5` | 头像尺寸（20px ~ 60px，圆形） |
| `.icon-calendar-check-o` | FA4 旧图标名映射 |

#### 资源降级样式

| 类名 | 说明 |
|------|------|
| `.bs4c-bg-fallback` | 背景图加载失败时的渐变降级（由 JS 自动添加） |
| `.bs4c-img-fallback` | `<img>` 加载失败时的占位样式（由 JS 自动添加） |

### 3.2 JS 兼容层

**文件**: `view/js/bs4-compat.js`  
**加载方式**: 由通用注入器自动注入 `</body>` 之前（也在默认 `footer.inc.htm` 中加载）。

#### BS4 data 属性自动转换

自动将 DOM 中的 BS4 `data-*` 属性转换为 BS5 `data-bs-*` 格式：

| BS4 属性 | BS5 属性 |
|----------|----------|
| `data-toggle` | `data-bs-toggle` |
| `data-dismiss` | `data-bs-dismiss` |
| `data-target` | `data-bs-target` |
| `data-backdrop` | `data-bs-backdrop` |
| `data-keyboard` | `data-bs-keyboard` |
| `data-slide` | `data-bs-slide` |
| `data-ride` | `data-bs-ride` |
| `data-parent` | `data-bs-parent` |
| `data-content` | `data-bs-content` |

支持 `MutationObserver` 动态监听，HTMX/Ajax 插入的 DOM 也会被自动转换。

#### CSRF 全局保护

多层 CSRF token 自动注入，确保所有主题下 POST 操作不会失败：

| 机制 | 覆盖场景 |
|------|----------|
| `<meta name="csrf-token">` 注入 | 供 JS 读取 token 值 |
| `var csrf_token` 全局变量 | 旧插件直接使用此变量 |
| `jQuery.ajaxSetup` 拦截器 | 所有 jQuery AJAX POST 自动携带 `X-CSRF-TOKEN` 头 |
| `fetch` API 拦截器 | 现代主题使用 fetch 的 POST 请求 |
| `<form>` hidden field 注入 | 所有 POST 表单自动添加 `_token` 字段 |
| `MutationObserver` | 动态插入的表单也会被自动注入 |

#### BS4 组件 jQuery API 代理

将旧插件中的 jQuery 风格 BS4 API 调用代理到 BS5 原生实例：

| jQuery API | BS5 代理目标 | 支持操作 |
|------------|-------------|----------|
| `.modal()` | `bootstrap.Modal` | show / hide / toggle / dispose |
| `.tooltip()` | `bootstrap.Tooltip` | 初始化 / show / hide / toggle / dispose / enable / disable |
| `.popover()` | `bootstrap.Popover` | 初始化 / show / hide / toggle / dispose / enable / disable |

#### BS4 Button jQuery API 代理

BS5 移除了 jQuery `.button()` 插件，但几乎所有旧插件都依赖它（424 处调用）：

| 操作 | 说明 |
|------|------|
| `.button('loading')` | 禁用按钮 + 显示 `data-loading-text` 文本 |
| `.button('reset')` | 恢复按钮原始文本 + 重新启用 |
| `.button('toggle')` | 切换 `.active` 状态 |
| `.button('disabled')` | 禁用按钮 |
| `.button('enable')` | 启用按钮 |
| `.button('自定义文本')` | 设置按钮文字（保留原始文本供 reset） |

#### xiuno.js 核心方法保底

当主题用自己的 `xiuno.js` 替换核心版本时，以下常用方法可能丢失。兼容层通过 `if(!jQuery.fn.xxx)` 检测，仅在缺失时提供 polyfill：

| 方法 | 插件使用量 | 功能 |
|------|-----------|------|
| `.button()` | 424 | 按钮状态管理（见上） |
| `.alert()` | 301 | 在控件上方显示 BS5 Tooltip 错误提示 |
| `.reset()` | 116 | 重置表单（恢复按钮 + 清除验证） |
| `.location()` | 61 | 延迟跳转（支持 jQuery 动画队列链式调用） |
| `.checked()` | 31 | select/radio/checkbox 值设置与获取 |

#### 资源 404 自动降级

- **背景图检测**: 扫描所有 `[style*="url("]` 元素，用 `new Image()` 探测 URL 可达性。404 时自动添加 `.bs4c-bg-fallback` 类。
- **`<img>` 错误捕获**: 通过 `document.addEventListener('error', ..., true)` 在捕获阶段拦截所有图片加载失败，自动添加 `.bs4c-img-fallback` 类。小于 80px 的图片直接隐藏。
- **MutationObserver**: 动态插入的 DOM 元素也会被自动检测。

---

## 第四层：主题 API

**文件**: `model/misc.func.php`  
**加载方式**: 由核心框架在路由初始化前自动加载。

### 4.1 message() HTMX 原生支持

核心 `message()` 函数现在自动检测 HTMX 请求并返回结构化响应：

```
请求头: HX-Request: true
响应头: HX-Trigger: {"showMessage":{"code":0,"type":"success","message":"操作成功"}}
响应体: 操作成功
```

**type 映射规则**:
| code | type | 含义 |
|------|------|------|
| `0` | `success` | 操作成功 |
| `-1` | `danger` | 系统错误 |
| `>= 1` | `warning` | 业务逻辑错误 |
| 其他 | `info` | 信息提示 |

**主题覆盖**: 如果主题通过 `theme_register()` 声明了 `htmx_message` 能力，核心会跳过通用 HTMX 处理，让主题自己的 `message.htm` 覆盖生效。

```php
// 主题声明自己处理 HTMX 消息
theme_register('aether', ['htmx', 'htmx_message', 'sheet_mode']);
// → 核心 message() 不会拦截 HTMX 请求，aether 的 message.htm 覆盖正常工作
```

**Hook 点**:
| Hook | 位置 | 用途 |
|------|------|------|
| `model_message_htmx_before.php` | HTMX 响应发送前 | 修改 $trigger_data |
| `model_message_htmx_trigger.php` | trigger 数据构建后 | 自定义 trigger 结构 |
| `model_message_htmx_after.php` | HTMX 响应发送后 | 额外清理/日志 |

### 4.2 url() 扩展机制

主题可以注册回调函数，`url()` 生成 URL 时自动调用：

```php
// 注册回调（通常在主题的 index_inc_start.php hook 中）
url_extra_register(function($url_input, $url_output) {
    // $url_input: 原始传入的 URL 标识（如 'index-1-0'）
    // $url_output: 已生成的 URL 字符串（如 '?index-1-0.htm'）
    
    // 只在 HTMX 请求中添加分页标记
    if (isset($_SERVER['HTTP_HX_REQUEST'])) {
        return ['IS_IN_PAGINATION' => 1];
    }
    return null;  // 返回 null 或空数组表示不添加参数
});
```

### 4.3 主题注册 API

#### theme_register($name, $capabilities)

声明主题身份和能力，核心据此做兼容决策。

```php
theme_register('aether', [
    'htmx',           // 主题使用 HTMX
    'htmx_message',   // 主题自己处理 HTMX 消息
    'sheet_mode',     // 主题支持 Sheet 模式
    'material_design' // 主题使用 Material Design
]);
```

#### theme_has($capability)

查询当前激活的主题是否支持某能力。

```php
if (theme_has('htmx')) {
    // 输出 HTMX 相关的 meta 标签
}
```

### 4.4 资源注册 API

#### theme_enqueue_style($handle, $src, $priority = 10)

注册 CSS 文件，由核心在 `</head>` 前自动输出。

```php
theme_enqueue_style('my-theme-core', './plugin/my_theme/view/css/core.css', 5);
theme_enqueue_style('my-theme-extra', './plugin/my_theme/view/css/extra.css', 20);
// priority 越小越先输出
```

#### theme_enqueue_script($handle, $src, $priority = 10, $attrs = [])

注册 JS 文件，由核心在 `</body>` 前自动输出。

```php
theme_enqueue_script('htmx', './plugin/my_theme/view/js/htmx.min.js', 5, [
    'defer' => 'defer'
]);
```

#### theme_render_styles() / theme_render_scripts()

由核心模板自动调用（`header.inc.htm` / `footer.inc.htm`）。如果主题覆盖了这些模板，需要在自己的模板中手动调用。

> **注意**: 覆盖了 `header.inc.htm` / `footer.inc.htm` 的主题无需担心 bs4-compat 资源丢失（通用注入器会自动补充），但如果主题希望通过 Theme API 注册额外资源，仍需在自己的模板中调用这两个函数。

---

## 扩展兼容层

### 添加新的 CSS 兼容规则

在 `view/css/bs4-compat.css` 末尾追加即可，建议加注释说明来源：

```css
/* 插件 xx_plugin 使用的旧类名 */
.old-class-name { /* ... */ }
```

### 添加新的 JS 兼容逻辑

在 `view/js/bs4-compat.js` 中添加新的 IIFE 块：

```javascript
// 兼容描述
(function() {
    'use strict';
    // ...
})();
```

### 添加新的 PHP polyfill

在 `xiunophp/php8_compat.php` 中用 `function_exists()` 包裹：

```php
if (!function_exists('new_function_name')) {
    function new_function_name(...) {
        // polyfill 实现
    }
}
```

---

## 文件清单

| 文件路径 | 类型 | 说明 |
|----------|------|------|
| `xiunophp/php8_compat.php` | PHP | 运行时兼容层（TypeError + polyfill） |
| `xiunophp/xiunophp.php` | PHP | 核心引导（引入 php8_compat.php） |
| `model/misc.func.php` | PHP | 主题 API 函数定义 |
| `view/css/bs4-compat.css` | CSS | 前端 CSS 兼容 |
| `view/js/bs4-compat.js` | JS | 前端 JS 兼容 |
| `index.php` | PHP | 通用注入器（ob_start 回调） |
| `view/htm/header.inc.htm` | HTM | 调用 `theme_render_styles()` |
| `view/htm/footer.inc.htm` | HTM | 调用 `theme_render_scripts()` |
