# 在线更新完整性校验

阶段五开始，在线更新会在下载 ZIP 后计算 SHA-256，并尝试从 GitHub Release 元数据中读取期望 hash。

## 支持的发布写法

Release body 可以写：

```text
SHA256: <64位sha256>
```

也可以在 Release assets 上传以下任一文件：

- `SHA256SUMS`
- `SHA256SUMS.txt`
- `checksums.txt`
- `*.sha256`
- `*.sha256.txt`

校验文件支持常见格式：

```text
<64位sha256>  v4.4.5.zip
```

## 当前策略

- 找到 SHA-256 时：必须匹配，否则停止更新。
- 未找到 SHA-256 时：默认停止更新，并在错误信息中显示本地计算的 `zip_sha256`，便于发布者补齐校验信息。
- 过渡期如果必须使用未提供 SHA-256 的包，站点管理员可以在 `conf/conf.php` 中显式设置 `'allow_unverified_update' => 1`。此模式仍会把本地计算的 `zip_sha256` 和 `checksum_verified=0` 写入 `log/update.log`，只建议用于临时恢复或受控测试。

## 下载边界

- 在线更新请求只接受公网 HTTPS URL；自定义 GitHub 加速代理必须是 HTTPS，且不能包含用户信息、查询参数或片段。
- 更新检查和 ZIP 下载不依赖 cURL/stream 的自动重定向。Xiuno Next 会逐跳读取 `Location`，解析相对地址，并拒绝 HTTP 降级、控制字符、私有地址、localhost 和超出次数的重定向链。
- 下载体仍受 `UPDATE_MAX_ZIP_BYTES` 限制；超过限制时在写入临时 ZIP 前中止。
