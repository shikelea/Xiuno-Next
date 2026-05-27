# Xiuno BBS 数据库表结构详解

本文档只记载所有核心表结构，不包括任何由插件拓展出的新字段。

## 核心表结构

### 1. 用户表 (bbs_user)

存储论坛所有注册用户的基本信息。

| 字段名 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| uid | int(11) unsigned | AUTO_INCREMENT | 用户编号，主键 |
| gid | smallint(6) unsigned | 0 | 用户组编号，关联 bbs_group.gid |
| email | char(40) | '' | 邮箱，唯一索引 |
| username | char(32) | '' | 用户名，唯一索引，不可重复 |
| realname | char(16) | '' | 真实姓名，预留字段 |
| password | char(32) | '' | 密码（MD5加密） |
| password_sms | char(16) | '' | 手机短信验证码，预留字段 |
| salt | char(16) | '' | 密码混淆盐值 |
| mobile | char(11) | '' | 手机号，预留字段 |
| qq | char(15) | '' | QQ号，预留字段 |
| threads | int(11) | 0 | 发帖数 |
| posts | int(11) | 0 | 回帖数 |
| credits | int(11) | 0 | 积分，预留字段 |
| golds | int(11) | 0 | 金币，虚拟货币 |
| rmbs | int(11) | 0 | 人民币，预留字段 |
| create_ip | int(11) unsigned | 0 | 创建时IP（ip2long转换） |
| create_date | int(11) unsigned | 0 | 创建时间（UNIX时间戳） |
| login_ip | int(11) unsigned | 0 | 最后登录IP |
| login_date | int(11) unsigned | 0 | 最后登录时间 |
| logins | int(11) unsigned | 0 | 登录次数 |
| avatar | int(11) unsigned | 0 | 用户最后更新头像时间 |

**索引：**
- PRIMARY KEY (uid)
- UNIQUE KEY username (username)
- UNIQUE KEY email (email)
- KEY gid (gid)

**默认数据：**
- 默认管理员账号：uid=1, username=admin, password=d98bb50e808918dd45a8d92feafc4fa3, salt=123456

**细节：**
- `avatar` 字段如果为0，则表示没有头像，如果不是0，则应该从 `view/avatar/` 目录下获取头像文件。
- `username` 字段同时作为用户名和显示名称

头像图片文件名和路径的计算：
```php
$filename = "$uid.png"; //文件名
$dir = substr(sprintf("%09d", $uid), 0, 3).'/'; //目录名
$path = $conf['upload_path'].'avatar/'.$dir; //文件路径
$url = $conf['upload_url'].'avatar/'.$dir.$filename; //文件URL
```

---

### 2. 用户组表 (bbs_group)

定义用户权限组，控制不同用户组的操作权限。

十分遗憾的是，xiuno bbs的用户权限系统做的很失败，跟垃圾一样，没有和其他论坛的权限系统那么完善和可扩展，导致插件需要在自己的设计中考虑到用户权限，并在插件设置那边进行配置。这导致了权限碎片化。

| 字段名 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| gid | smallint(6) unsigned | - | 用户组编号，主键 |
| name | char(20) | '' | 用户组名称 |
| creditsfrom | int(11) | 0 | 积分下限 |
| creditsto | int(11) | 0 | 积分上限 |
| allowread | int(11) | 0 | 允许访问 |
| allowthread | int(11) | 0 | 允许发主题 |
| allowpost | int(11) | 0 | 允许回帖 |
| allowattach | int(11) | 0 | 允许上传附件 |
| allowdown | int(11) | 0 | 允许下载附件 |
| allowtop | int(11) | 0 | 允许置顶 |
| allowupdate | int(11) | 0 | 允许编辑 |
| allowdelete | int(11) | 0 | 允许删除 |
| allowmove | int(11) | 0 | 允许移动 |
| allowbanuser | int(11) | 0 | 允许禁止用户 |
| allowdeleteuser | int(11) | 0 | 允许删除用户 |
| allowviewip | int(11) unsigned | 0 | 允许查看用户敏感信息 |

**预设用户组：**
| gid | 名称 | 权限说明 |
|-----|------|----------|
| 0 | 游客组 | 仅可浏览和回帖 |
| 1 | 管理员组 | 全部权限 |
| 2 | 超级版主组 | 全部权限（除删除用户） |
| 4 | 版主组 | 版块管理权限 |
| 5 | 实习版主组 | 有限管理权限 |
| 6 | 待验证用户组 | 仅可浏览和回帖 |
| 7 | 禁止用户组 | 无任何权限 |
| 101及以上 | 会员 | 普通用户权限 |

---

### 3. 版块表 (bbs_forum)

存储论坛版块信息。

| 字段名 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| fid | int(11) unsigned | AUTO_INCREMENT | 版块ID，主键 |
| name | char(16) | '' | 版块名称 |
| rank | tinyint(3) unsigned | 0 | 显示顺序，倒序排列，数字越大越靠前 |
| threads | mediumint(8) unsigned | 0 | 主题数 |
| todayposts | mediumint(8) unsigned | 0 | 今日发帖数，每日凌晨清零 |
| todaythreads | mediumint(8) unsigned | 0 | 今日发主题数，每日凌晨清零 |
| brief | text | '' | 版块简介，允许HTML |
| announcement | text | '' | 版块公告，允许HTML |
| accesson | int(11) unsigned | 0 | 是否开启权限控制 |
| orderby | tinyint(11) | 0 | 默认列表排序：0=顶贴时间，1=发帖时间 |
| create_date | int(11) unsigned | 0 | 版块创建时间 |
| icon | int(11) unsigned | 0 | 版块图标最后更新时间 |
| moduids | char(120) | '' | 版主UID列表，逗号分隔，最多10个 |
| seo_title | char(64) | '' | SEO标题，预留字段 |
| seo_keywords | char(64) | '' | SEO关键词，预留字段 |

**索引：**
- PRIMARY KEY (fid)

**细节：**
- `icon` 字段如果为0，则表示没有图标，如果不是0，则应该从 `view/forum/` 目录下获取图标文件。

---

### 4. 版块访问权限表 (bbs_forum_access)

当版块开启权限控制时，定义各用户组的访问权限。

| 字段名 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| fid | int(11) unsigned | 0 | 版块ID |
| gid | int(11) unsigned | 0 | 用户组ID |
| allowread | tinyint(1) unsigned | 0 | 允许查看 |
| allowthread | tinyint(1) unsigned | 0 | 允许发主题 |
| allowpost | tinyint(1) unsigned | 0 | 允许回复 |
| allowattach | tinyint(1) unsigned | 0 | 允许上传附件 |
| allowdown | tinyint(1) unsigned | 0 | 允许下载附件 |

**索引：**
- PRIMARY KEY (fid, gid)

**说明：** 此表与 bbs_forum.accesson 字段配合使用，当 accesson=1 时生效。

---

### 5. 主题表 (bbs_thread)

存储论坛主题（帖子）信息。

| 字段名 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| fid | smallint(6) | 0 | 版块ID |
| tid | int(11) unsigned | AUTO_INCREMENT | 主题ID，主键 |
| top | tinyint(1) | 0 | 置顶级别：0=普通，1-3=置顶 |
| uid | int(11) unsigned | 0 | 发帖用户ID |
| userip | int(11) unsigned | 0 | 发帖时用户IP |
| subject | char(128) | '' | 主题标题 |
| create_date | int(11) unsigned | 0 | 发帖时间 |
| last_date | int(11) unsigned | 0 | 最后回复时间 |
| views | int(11) unsigned | 0 | 查看次数 |
| posts | int(11) unsigned | 0 | 回帖数 |
| images | tinyint(6) | 0 | 附件图片数 |
| files | tinyint(6) | 0 | 附件文件数 |
| mods | tinyint(6) | 0 | 版主操作次数 |
| closed | tinyint(1) unsigned | 0 | 是否关闭，关闭后不可回帖编辑 |
| firstpid | int(11) unsigned | 0 | 首帖PID |
| lastuid | int(11) unsigned | 0 | 最近回复用户ID |
| lastpid | int(11) unsigned | 0 | 最后回复PID |

**索引：**
- PRIMARY KEY (tid)
- KEY (lastpid) - 最后回复排序
- KEY (fid, tid) - 发帖时间排序
- KEY (fid, lastpid) - 顶贴时间排序

**置顶级别说明：**
- 0：普通主题
- 1：版块置顶
- 2：未使用，猜测为分类置顶
- 3：全局置顶

**细节：**
- files字段是被使用的，但images字段未必会使用（取决于使用的帖子编辑器插件）

---

### 6. 置顶主题表 (bbs_thread_top)

专门管理置顶主题，提高查询效率。

| 字段名 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| fid | smallint(6) | 0 | 版块ID |
| tid | int(11) unsigned | 0 | 主题ID |
| top | int(11) unsigned | 0 | 置顶级别 |

**索引：**
- PRIMARY KEY (tid)
- KEY (top, tid) - 全局置顶查询
- KEY (fid, top) - 版块置顶查询

---

### 7. 帖子表 (bbs_post)

存储主题的所有帖子内容（包括首帖和回复）。

| 字段名 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| tid | int(11) unsigned | 0 | 主题ID |
| pid | int(11) unsigned | AUTO_INCREMENT | 帖子ID，主键 |
| uid | int(11) unsigned | 0 | 发帖用户ID |
| isfirst | int(11) unsigned | 0 | 是否为首帖 |
| create_date | int(11) unsigned | 0 | 发帖时间 |
| userip | int(11) unsigned | 0 | 发帖时用户IP |
| images | smallint(6) | 0 | 附件图片数 |
| files | smallint(6) | 0 | 附件文件数 |
| doctype | tinyint(3) | 0 | 内容类型 |
| quotepid | int(11) | 0 | 引用的帖子PID |
| message | longtext | '' | 原始内容 |
| message_fmt | longtext | '' | 格式化后的HTML内容 |

**索引：**
- PRIMARY KEY (pid)
- KEY (tid, pid) - 主题内帖子排序
- KEY (uid) - 用户回帖查询

**内容类型 (doctype)：**
- 0：HTML（需要插件支持）
- 1：纯文本
- 2：Markdown（需要插件支持）
- 3：BBCode / UBB（需要插件支持）

---

### 8. 附件表 (bbs_attach)

存储帖子中的附件信息。

| 字段名 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| aid | int(11) unsigned | AUTO_INCREMENT | 附件ID，主键 |
| tid | int(11) | 0 | 主题ID |
| pid | int(11) | 0 | 帖子ID |
| uid | int(11) | 0 | 上传用户ID |
| filesize | int(8) unsigned | 0 | 文件大小（字节） |
| width | mediumint(8) unsigned | 0 | 图片宽度，>0表示图片 |
| height | mediumint(8) unsigned | 0 | 图片高度 |
| filename | char(120) | '' | 保存后的文件名 |
| orgfilename | char(120) | '' | 原始文件名 |
| filetype | char(7) | '' | 文件类型：image/txt/zip |
| create_date | int(11) unsigned | 0 | 上传时间 |
| comment | char(100) | '' | 文件注释 |
| downloads | int(11) | 0 | 下载次数 |
| credits | int(11) | 0 | 需要积分，预留 |
| golds | int(11) | 0 | 需要金币，预留 |
| rmbs | int(11) | 0 | 需要人民币，预留 |
| isimage | tinyint(1) | 0 | 是否为图片 |

**索引：**
- PRIMARY KEY (aid)
- KEY pid (pid) - 帖子附件查询
- KEY uid (uid) - 用户附件查询

---

### 9. 我的主题表 (bbs_mythread)

记录用户参与的主题，用于快速查询用户发帖。

| 字段名 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| uid | int(11) unsigned | 0 | 用户ID |
| tid | int(11) unsigned | 0 | 主题ID |

**索引：**
- PRIMARY KEY (uid, tid) - 联合主键，确保唯一性

**说明：** 每个主题不管回复多少次，只记录一次。数据量大时建议分区。

---

### 10. 我的回帖表 (bbs_mypost)

记录用户的回帖，用于快速查询用户回复。

| 字段名 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| uid | int(11) unsigned | 0 | 用户ID |
| tid | int(11) unsigned | 0 | 主题ID |
| pid | int(11) unsigned | 0 | 帖子ID |

**索引：**
- PRIMARY KEY (uid, pid) - 联合主键
- KEY (tid) - 主题查询

**说明：** 数据量大时建议分区。

---

### 11. 会话表 (bbs_session)

存储用户会话信息。

| 字段名 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| sid | char(32) | '0' | 会话ID，主键 |
| uid | int(11) unsigned | 0 | 用户ID，未登录为0 |
| fid | tinyint(3) unsigned | 0 | 所在版块 |
| url | char(32) | '' | 当前访问URL |
| ip | int(11) unsigned | 0 | 用户IP |
| useragent | char(128) | '' | 用户浏览器信息 |
| data | char(255) | '' | 会话数据 |
| bigdata | tinyint(1) | 0 | 是否有大数据 |
| last_date | int(11) unsigned | 0 | 上次活动时间 |

**索引：**
- PRIMARY KEY (sid)
- KEY ip (ip)
- KEY fid (fid)
- KEY uid_last_date (uid, last_date)

**说明：** 会话数据会缓存到 runtime 表中，提高访问效率。

---

### 12. 会话数据表 (bbs_session_data)

存储会话的超大数据。

| 字段名 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| sid | char(32) | '0' | 会话ID，主键 |
| last_date | int(11) unsigned | 0 | 上次活动时间 |
| data | text | '' | 超大会话数据 |

**索引：**
- PRIMARY KEY (sid)

**说明：** 当 bbs_session.bigdata=1 时，大数据存储在此表。

---

### 13. 版主操作日志表 (bbs_modlog)

记录版主的管理操作。

| 字段名 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| logid | int(11) unsigned | AUTO_INCREMENT | 日志ID，主键 |
| uid | int(11) unsigned | 0 | 版主用户ID |
| tid | int(11) unsigned | 0 | 主题ID |
| pid | int(11) unsigned | 0 | 帖子ID |
| subject | char(32) | '' | 主题标题 |
| comment | char(64) | '' | 版主评价 |
| rmbs | int(11) | 0 | 加减人民币，预留 |
| create_date | int(11) unsigned | 0 | 操作时间 |
| action | char(16) | '' | 操作类型：top/delete/untop |

**索引：**
- PRIMARY KEY (logid)
- KEY (uid, logid) - 版主操作查询
- KEY (tid) - 主题操作查询

**细节：**
- 该表几乎没有被原装xiuno bbs使用，主要由插件使用。

---

### 14. 键值存储表 (bbs_kv)

持久化的键值对数据存储。

| 字段名 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| k | char(32) | '' | 键名，主键 |
| v | mediumtext | '' | 值 |
| expiry | int(11) unsigned | 0 | 过期时间 |

**索引：**
- PRIMARY KEY (k)

**用途：** 存储系统配置、插件数据等持久化信息。

---

### 15. 缓存表 (bbs_cache)

临时数据缓存存储。

| 字段名 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| k | char(32) | '' | 缓存键名，主键 |
| v | mediumtext | '' | 缓存值 |
| expiry | int(11) unsigned | 0 | 过期时间 |

**索引：**
- PRIMARY KEY (k)

**用途：** 
存储临时数据，可定期清理过期数据。如果`conf/conf.php`中设置`$conf['cache']['type']`为`mysql`，则此表用于缓存来保持缓存机制的可用性。

**注意：**
【缺陷】千万不要设置`$conf['cache']['enable']`为`false`，否则论坛功能可能会有故障。

---

### 16. 队列表 (bbs_queue)

临时队列数据存储。

| 字段名 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| queueid | int(11) unsigned | 0 | 队列ID |
| v | int(11) | 0 | 队列数据（仅支持整数） |
| expiry | int(11) unsigned | 0 | 过期时间，0表示不过期 |

**索引：**
- UNIQUE KEY (queueid, v)
- KEY (expiry)

**用途：** 实现简单的队列功能，如消息队列、任务队列等。

---

### 17. 表统计表 (bbs_table_day)

记录大表每日的最大ID和数量，用于数据分区和冷热数据分离。

| 字段名 | 类型 | 默认值 | 说明 |
|--------|------|--------|------|
| year | smallint(11) unsigned | 0 | 年 |
| month | tinyint(11) unsigned | 0 | 月 |
| day | tinyint(11) unsigned | 0 | 日 |
| create_date | int(11) unsigned | 0 | 时间戳 |
| table | char(16) | '' | 表名 |
| maxid | int(11) unsigned | 0 | 最大ID |
| count | int(11) unsigned | 0 | 记录总数 |

**索引：**
- PRIMARY KEY (year, month, day, table)

**说明：** 
- 由计划任务每日凌晨1点执行更新
- 用于优化大数据表的查询性能
- 可有效过滤冷热数据

---

## 表关系图

```
bbs_user (用户)
    ├── bbs_group (用户组) - gid
    ├── bbs_thread (主题) - uid
    ├── bbs_post (帖子) - uid
    ├── bbs_attach (附件) - uid
    ├── bbs_mythread (我的主题) - uid
    ├── bbs_mypost (我的回帖) - uid
    ├── bbs_session (会话) - uid
    └── bbs_modlog (操作日志) - uid

bbs_forum (版块)
    ├── bbs_thread (主题) - fid
    ├── bbs_forum_access (访问权限) - fid
    └── bbs_session (会话) - fid

bbs_thread (主题)
    ├── bbs_post (帖子) - tid
    ├── bbs_attach (附件) - tid
    ├── bbs_thread_top (置顶) - tid
    ├── bbs_mythread (我的主题) - tid
    ├── bbs_mypost (我的回帖) - tid
    └── bbs_modlog (操作日志) - tid

bbs_post (帖子)
    └── bbs_attach (附件) - pid
```

---

## 数据安全

### 密码存储

密码采用 MD5 + Salt 方式加密：
```php
$password_md5 = md5(md5($password) . $salt);
```

【缺陷】

但最大的问题也在这里：

- md5已经被证明存在安全问题，不建议使用
  - 为什么：
    - MD5算法存在碰撞攻击风险，且计算速度过快，容易被暴力破解
  - 那为什么还要继续用md5：
    - 因为一种合规性要求，认为数据要在传输的时候就加密，认为这样可以防止密码被泄漏
      - 而什么加密方式或者说是哈希方式是可以轻松在浏览器里实现的？对，就是md5
      - xiuno bbs用了个jquery的md5函数，可以在浏览器里实现md5加密
        - 而这个就是偶尔发生的“加密后长度有问题”错误的根源
- 每个用户会分配一个专属于用户的salt，存储在用户表里，而不是通过统一salt加密
  - 这导致如果没有小心的编程，在使用user_read或db_find_one等函数的时候，md5 hash与salt都会同时出现
    - 这会导致敏感信息暴露在外的风险
      - 例如在API场合就会有这样的可能性
        - 例如在`thread-{tid}.htm?ajax=1`的API响应，其中thread部分会带有一个user部分，而该部分并没有进行过滤，导致密码hash与salt泄漏
- 在自己设计的时候高度建议使用`password_hash()`函数来加密密码

### IP存储

IP地址使用 `ip2long()` 转换为整数存储，节省空间并提高查询效率。

### 敏感信息

- 用户密码、手机号等敏感信息需谨慎处理
- `allowviewip` 权限控制敏感信息查看
- 建议定期备份重要数据

---

## 扩展字段

以下字段为预留扩展字段，可根据需求进行二次开发：

- `bbs_user.mobile` - 手机号
- `bbs_user.qq` - QQ号
- `bbs_user.credits` - 积分系统 - 经验值
- `bbs_user.golds` - 积分系统 - 虚拟货币
- `bbs_user.rmbs` - 积分系统 - 人民币（用于充值系统）
- `bbs_attach.credits` - 附件下载积分限制
- `bbs_attach.golds` - 附件下载金币限制
- `bbs_modlog.rmbs` - 版主奖励系统
