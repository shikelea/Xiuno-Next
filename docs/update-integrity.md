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
