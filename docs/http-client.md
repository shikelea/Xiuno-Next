# Xiuno Next HTTP 客户端草案

本文不是正式开发手册，只记录阶段五的轻量 HTTP 约定。

旧代码可以继续使用 `http_get()` / `http_post()`。新代码优先使用 `XiunoHttp`：

```php
$r = XiunoHttp::get('https://example.com/api', array(
	'query' => array('page' => 1),
	'timeout' => 10,
));

$r = XiunoHttp::json('https://example.com/api', array('name' => 'xiuno'));
```

## 约定

- 不引入 Guzzle，优先使用 PHP cURL；没有 cURL 时回退到 stream。
- HTTPS 默认开启证书校验，只有明确传入 `verify_tls => false` 时才关闭。
- 返回值统一为数组：`ok`、`code`、`headers`、`body`、`json`、`errno`、`errstr`。
- `post()` 默认发送 `application/x-www-form-urlencoded`，`json()` 默认发送 `application/json`。
- 支持 `timeout`、`connect_timeout`、`headers`、`query`、`user_agent`、`proxy`、`proxy_auth` 等轻量选项。
- 阶段五不替换旧函数签名，后续新功能逐步迁移到 `XiunoHttp`。
