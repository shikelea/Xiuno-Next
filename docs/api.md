# Xiuno Next API

Xiuno Next exposes a small REST-style API for mobile clients, single-page applications, and automation. The current API is still being expanded conservatively: read-only endpoints are preferred first, while write and upload flows are added only after their authentication, permission, and validation boundaries are clear.

## Base URL

Recommended versioned route:

```text
/api/v1/{controller}/{action}
```

Xiuno URL compatibility route:

```text
?api-v1-{controller}-{action}.htm
```

Legacy compatibility route:

```text
?api-{controller}-{action}.htm
```

## Response Shape

All API responses use the same envelope:

```json
{
  "code": 0,
  "message": "OK",
  "data": {}
}
```

`code` is `0` for success. Negative values indicate errors. The `data` shape depends on the endpoint.

Missing or unsafe API routes return a JSON envelope with `code` set to `404`. Current route-level errors are JSON business errors and should not be treated as full HTTP status coverage yet.

## Authentication

`POST /api/v1/user/login` returns a `token`. Authenticated requests may send that token in any of these ways:

```text
token={token}
bbs_token={token}
Authorization: Bearer {token}
```

Endpoints that need a logged-in user use the shared `api_login_required()` / `api_auth_uid()` helpers. Write endpoints also use `api_method_required()` to reject unsupported request methods.

Prefer `Authorization: Bearer {token}` for non-browser clients. Query-string tokens are accepted for compatibility, but they can be captured by access logs or referrers.

If a write endpoint is called from an existing browser session without an explicit API token, Xiuno Next requires the normal CSRF token (`_token` or `X-CSRF-Token`). If an explicit API token is present, the token must validate; invalid tokens do not fall back to the browser session.

## Pagination

Paged endpoints accept:

| Parameter | Default | Limit | Notes |
| --- | ---: | ---: | --- |
| `page` | `1` | minimum `1` | Invalid values are clamped to `1`. |
| `pagesize` | `20` | usually `100` | Search currently caps `pagesize` at `50`. |

Paged endpoints use `api_page_params()` unless noted otherwise.

## Permission Boundaries

Forum and thread data is filtered by the current visitor's read permission. Search and user thread-list endpoints use a bounded candidate window before permission filtering, so their `total` is the visible total within that bounded window rather than a full-table count. This avoids leaking private-forum counts while keeping the query lightweight.

`thread_safe_info()`, `post_safe_info()`, `user_safe_info()`, and `forum_safe_info()` are used to remove sensitive fields before returning model data.

## Endpoints

Read endpoints are documented as `GET` and should be treated as read-only. The current route layer explicitly enforces `POST` on write endpoints; read endpoints are kept side-effect-free except where noted.

### Index

```http
GET /api/v1
```

Returns API metadata, including the Xiuno Next version and active API route version.

### User

```http
POST /api/v1/user/login
```

Parameters:

| Name | Required | Notes |
| --- | --- | --- |
| `email` | yes | Email or username. |
| `password` | yes | Raw password. The server aligns it with the browser login hash flow. |

Returns safe user fields plus `token`.

```http
GET /api/v1/user/read
```

Parameters:

| Name | Required | Notes |
| --- | --- | --- |
| `uid` | no | If omitted, token auth is used as fallback. |
| `token` / `bbs_token` / bearer token | no | Used when `uid` is omitted. |

Returns safe user fields.

```http
GET /api/v1/user/threads
```

Parameters:

| Name | Required | Notes |
| --- | --- | --- |
| `uid` | no | If omitted, token auth is used as fallback. |
| `page` | no | Paged via `api_page_params()`. |
| `pagesize` | no | Paged via `api_page_params()`. |

Returns the target user and the current visitor's visible thread list.

### Forum

```http
GET /api/v1/forum/list
```

Returns forums the current visitor may read.

```http
GET /api/v1/forum/read
```

Parameters:

| Name | Required | Notes |
| --- | --- | --- |
| `fid` | yes | Forum id. |

Returns one forum if the current visitor may read it.

### Thread

```http
GET /api/v1/thread/list
```

Parameters:

| Name | Required | Notes |
| --- | --- | --- |
| `fid` | no | When present, filters by forum id and checks read permission. |
| `page` | no | Paged via `api_page_params()`. |
| `pagesize` | no | Paged via `api_page_params()`. |

Returns a paged thread list.

```http
GET /api/v1/thread/read
```

Parameters:

| Name | Required | Notes |
| --- | --- | --- |
| `tid` | yes | Thread id. |
| `page` | no | Reply page. |
| `pagesize` | no | Reply page size. |

Returns safe thread info and a paged post list. Reading a thread increments its view count.

```http
POST /api/v1/thread/create
```

Authentication required.

Parameters:

| Name | Required | Notes |
| --- | --- | --- |
| `fid` | yes | Forum id. Requires `allowthread`. |
| `subject` | yes | Maximum 128 UTF-8 characters. |
| `message` | yes | Maximum 2,028,000 UTF-8 characters. |
| `doctype` | no | Xiuno post format type. |

Creates a new thread and returns safe thread info.

### Post

```http
POST /api/v1/post/create
```

Authentication required.

Parameters:

| Name | Required | Notes |
| --- | --- | --- |
| `tid` | yes | Thread id. Requires `allowpost`. |
| `message` | yes | Reply body. |
| `doctype` | no | Xiuno post format type. |
| `quotepid` | no | Quoted post id. It is accepted only when it belongs to the target thread. |

Creates a reply and returns safe post info.

### Search

```http
GET /api/v1/search/thread
```

Parameters:

| Name | Required | Notes |
| --- | --- | --- |
| `keyword` / `q` | yes | At least 2 UTF-8 characters after removing SQL wildcard characters `%` and `_`; truncated to 64 characters. |
| `page` | no | Paged via `api_page_params(20, 50)`. |
| `pagesize` | no | Maximum `50`. |

Searches readable thread titles only. Results are filtered by forum read permission.
