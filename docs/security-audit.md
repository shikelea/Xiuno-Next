# Xiuno Next 安全审查记录

本文记录阶段五轻量现代化期间的历史遗留安全审查。它不是正式安全白皮书，只作为开发决策和后续排期依据。

## 本轮已修复

- 旧 `http_get()` / `http_post()` / `https_get()` / `https_post()` 只允许 HTTP/HTTPS URL，并拒绝控制字符。
- 旧 HTTPS cURL 请求默认开启证书校验，重定向协议限制为 HTTP/HTTPS。
- 旧 HTTP 请求中的 Cookie 和 User-Agent 会移除换行符，降低请求头注入风险。
- `param_base64()` 和 `base64_decode_file_data()` 改为严格 base64 解码，并正确支持无 `data:*;base64,` 前缀的原始 base64。
- `http_location()` 移除 CR/LF，避免响应头注入。
- `xn_zip()` / `xn_unzip()` 以及旧 Zip fallback 增加 zip entry 路径校验，拒绝绝对路径、盘符路径、空路径、NUL 字节和 `../` 路径穿越。

## 继续观察

- 旧 Zip fallback 仍是兼容兜底代码，已补路径校验，但长期建议要求 PHP ZipArchive 扩展作为更新/插件包处理的硬依赖。
- 在线更新已具备 TLS、ZIP 魔数、ZIP 路径校验、覆盖前备份、回滚和 SHA-256 元数据校验；发布约定见 `docs/update-integrity.md`。发布包签名和“缺少 SHA-256 即阻断更新”的严格策略仍待发布流程稳定后启用。
- DB helper 仍以 `addslashes()` 拼 SQL。短期继续收敛直接 SQL 拼接点；长期应设计兼容旧插件的预处理语句迁移层。
- 旧迁移工具位于 `tool/`，包含大量一次性导入 SQL 拼接。默认不进入 Web 请求路径，后续需要单独标注为离线维护工具，并补充使用风险说明。

## 审查命令

```powershell
rg -n "CURLOPT_SSL_VERIFYPEER|eval\s*\(|unserialize\s*\(|create_function\s*\(|addslashes\s*\(|ZipArchive|extractTo" -g "*.php" -g "!vendor/**" -g "!plugin/**"
```

```powershell
php bin/check_lightweight_helpers.php
php bin/check_version.php
```
