# XiunoPHP 开发手册 清扫版

## 前言
作者：
- axiuno<axiuno@gmail.com> 2015-2016
- Geticer 2026

本清扫版本的组织方式更接近PHP手册中的风格，并增加大量新内容，作为Xiuno BBS开发参考资料使用。（也差不多只有Xiuno BBS、WellCMS等几个程序采用了）

### 每个函数页面包括四部分：
- 说明：
  - 包括函数签名（需要重新写，写成PHP手册风格的）和（对应原文件的）一句话介绍。
  - 也在这里写推荐做的事情，如直接使用现代php的等价版本，因为在XiunoPHP的那个时代（大约2016年）是php 5和7混合的时代，现在（2026年）应尽量多的使用php 8的新函数，会让生活变得更轻松。
- 参数：
  - 对应原文件的参数部分。可适当扩展。
- 返回值：
  - 对应原文件的返回值部分。可适当扩展。
- 示例：
  - 对应原文件的示例部分。
  - 应当自己重新测试所有的演示代码
  
### 演示代码测试方法：
文件路径：Xiuno BBS根目录/code_example.php

### 其他：
- [PurePHP - 一个受 ReactJS 启发的 PHP 模板引擎](https://yonlj.github.io/purephp/zh/)

## XiunoPHP 入门

### 什么是 XiunoPHP？
XiunoPHP并不是一个框架，它只是初始化了一些常用的全局变量，增加了一些常用的函数，可以认为是对PHP 的一些功能的补充。合并后，它只有一个文件xiunophp.min.php，可以不做任何配置，直接 include 后就可以使用。
你可以用任意方式（过程式、面向对象、函数式）使用它（即使它本身只支持面向过程一种方式），甚至和其他框架混用。在你需要某个函数和变量的时候，它刚好存在，用它就 OK 了。

#### 如何获取？
原始的XiunoPHP4.0基于宽松的 MIT 协议发布，现在已经无法下载了。但好在Xiuno BBS 4.0.4附带了XiunoPHP4.0。
如何使用？
include 后直接使用里面的函数即可。
```
<?php
include './xiunophp.min.php';
$s = xn_encrypt('hello, world!');echo $s;
```


### 关于 URL 格式
XiunoPHP 除了支持原生的 URL 格式，还支持以下两种伪静态 URL 格式，
- http://examplle.com/?route-action.htm&uid=123&more=param
- http://examplle.com/route-action.htm?uid=123&more=param

- http://examplle.com/?route/action&uid=123&more=param
- http://examplle.com/route/action?uid=123&more=param

在xiuno bbs里，后面两种模式不可用。

获取参数的方法：
```php
include './xiunophp/xiunophp.php'; 
print_r($_REQUEST); 
/* Array(         
  0 => 'user',         
  1 => 'login',         
  'uid' => 123, 
) */  
  
// 获取参数 
$a = param(0);          // "user" 
$c = param(1);          // "login" 
$uid = param('uid', 0); // 123  
```
第二个参数为默认的数据类型和值，用来安全获取参数，具体参考 `param()`


### 编码规范

#### **一言以蔽之：尽量使用PSR-12标准。**

允许 Tab、snake_case、单引号偏好，但强制要求：
- 所有新的PHP文件，除了Hook机制所用的文件，其他文件都以  `<?php`  开头（符合 PSR-1）
- 类名用 PascalCase（哪怕方法用 snake_case）
- 禁止使用已废弃特性（如  `mysql_*` 、 `ereg` 、 `e  修饰符`等）
- 必须开启  `E_ALL`  开发环境检查，并尽可能解决掉所有的Warning和Notice，保证代码质量和健壮性
- 新模块/新类必须使用 4 空格缩进
- 新方法名推荐 camelCase（但不强制旧代码改）
- 在Xiuno BBS的语境下请继续使用原生PHP的输出方式，如 `echo` 等，不建议引入模板引擎（况且引入了也没法用）。

旧的规范依旧在这里提供，作为理解旧代码时的参考，可以一瞥原作者的设计决策。


#### 1. 变量名，函数名全部为： 小写 +　下划线，比如：
```php
<?php 
$uid = 0; 
$username = ''; 
mysql_connect(); 
mysql_query(); 
mysql_fetch_assoc(); 
?> 
```

#### 2. 常量全部大写：
```php
<?php 
define('DEBUG', 1); 
define('APP_NAME', 'www'); 
?> 
```

#### 3. 空格，缩进，换行，参考以下格式：
一个 TAB = 8 个空格，尽量减少 TAB 缩进。
```php
<?php
function array_addslashes(&$var) {
        if (is_array($var)) {
                foreach ($var as $k => &$v) {
                        array_addslashes($v);
                }
        } else {
                $var = addslashes($var);
        }
        return $var;
}
?>
```

#### 4. 单引号、双引号：
在PHP 当中，尽量使用单引号，解析速度比双引号快。

如果里面包含变量，为了代码的美观，可以使用双引号。

在双引号中的数组 key 不应该加单引号。

在单引号中仅仅转义 `\`，其他字符都不转义，如 `\t\r\n $`。

以下为正确用例：
```php
<?php 
$sitename = '我在北京吸雾霾'; 
$info = "站点名称：$sitename"; 
$info = "用户名：$user[name]"; 
?> 
```

#### 5. 类、继承、接口、构造、析构、魔术方法：
尽量不要使用 PHP 高级特性，但是需要懂（为了阅读他人代码）。

高级特性消耗自己和同事更多的学习成本，往往与带来的好处不成正比。

不是刚需，不要用。


#### 6. 正则表达式：
尽量使用单引号，分隔符为` #` 。

禁止使用 `e` 修饰符，如果刚需，请使用 `preg_replace_callback()` 代替。

尽量使用 `\w \s \S` 内置的表示方法，不要啰嗦的去写 `[0-9a-zA-Z_]` 。

> 为什么不用 `/` 作为分隔符？
> 因为 WEB 开发过程中，字符串中出现 `/` 的概率太高。

以下正则格式符合标准：
```php
preg_match('#\w+@\w+\.\w+#is', $email); 
```

#### 7. `include` `include_once` `require` `require_once`：
尽量使用 `include`，速度快，并且不会中断业务逻辑。

`require` 在文件不存在或不可读的时候，会暴力终止业务逻辑。

路径采用相对路径，一般相对于网站根目录 `./` 。

切换路径的时候使用：
```php
chdir('../'); 
include './xiunophp/xiunophp.php'; 
```


#### 8.` error_reporting`：
在本地开发环境下使用，使用 `E_ALL`，消灭所有 NOTICE。

线上环境使用 0，并且配置 php.ini `error_log` 记录到服务器日志，避免错误信息外泄。


#### 9. 模板：
不要用 Smarty 等任何类型的模板“引擎”，他们不时真正意义上的引擎，只是一堆正则替换而已。而且效率低下，学习的时间成本高，浪费脑细胞。

直接使用PHP 的 原生标签，比如：
```php
<?php include "./pc/view/header.inc.htm"; ?> 
Hello, <?php echo $username; ?>! 
<?php include "./pc/view/footer.inc.htm"; ?> 
```


#### 10. 目录约定：
为了便于部署和排查，约定以下目录用途（非强制）：
- Web 目录：`/home/wwwroot/example.com`
- Web 日志：`/home/wwwlog`
- MySQL 数据：`/home/mysql`
- 备份目录：`/home/backup`
- Nginx 配置文件：`/usr/local/nginx/conf/nginx.conf`
- MySQL 配置文件：`/etc/my.cnf`
- PHP 配置文件：`/usr/local/php/etc/php.ini`
- PHP-CGI 配置文件：`/usr/local/php/etc/php-fpm.conf`


## 全局变量
注意：XiunoPHP 在被 include 后，会初始化十几个常用的全局变量，请注意命名不要冲突。

请把以下全局变量当作是一种常量看待，就会感觉到合理许多了。

**再次提醒：不要重新定义这些全局变量。**

### `$starttime`

#### 说明
```php
float $starttime = microtime(1);
```
脚本执行的开始时间，单位为毫秒，例如：`1448335223.1877`。

一般用来计算程序执行时间。

### `$time`

#### 说明
```php
int $time = time();
```
脚本执行的开始时间（UNIX时间戳），单位为秒，例如：`1448336550`。

### `$conf`

#### 说明
```php
array $conf = include './conf/conf.php'; 
```
应用的全局配置，格式为数组，一般通过配置文件包含返回。


### `$ip`

#### 说明
```php
string $ip = ip();
```
客户端的 IP 地址，例如：`192.168.1.100`。

在开启 CDN 后，它会获取 CDN 转发过来的 IP 。

### `$longip`

#### 说明
```php
int $longip = ip2long($ip);
```
客户端的 IP 地址的 long 格式，例如：`2130706433`。

一般用 4 个字节的 unsigned int 记录到数据库。


### `$ajax`

#### 说明
```php
bool $ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower(trim($_SERVER['HTTP_X_REQUESTED_WITH'])) == 'xmlhttprequest';
```
是否为 AJAX 请求。

浏览器在发送 AJAX 请求的时候，会发送特定的头信息，会被当做判断依据。

### `$method`

#### 说明
```php
string $method = $_SERVER['REQUEST_METHOD'];
```
判断请求的方法，可能的值包括：GET/POST/PUT/DELETE/OPTIONS/HEAD/CONNECT/TRACE/PATCH。

判断浏览器发送的 HTTP 协议中的 METHOD 值。


### `$db`

#### 说明
```php
object|null $db = !empty($conf['db']) ? db_new($conf['db']) : NULL; 
```
默认数据库实例。

如果配置文件设置了数据库相关的配置，则框架会自动实例化一个 DB 类并赋值到 `$db` 变量中。

通常**无需直接使用这个变量**，除非在升级，转换需要多个连接等特殊情况的时候。

**强烈建议使用 `db_find()`、`db_find_one()`、`db_exec()`、`db_count()` 等db系列函数**，它们都使用了 `$db` 变量。


#### 示例
```php
<?php 
  
$conf = include './conf/conf.php'; 
include './xiunophp/xiunophp.php'; 
  
$arr = $db->find_one("SELECT * FROM bbs_user LIMIT 1"); 
print_r($arr);

// 强烈建议使用 db 系列函数，效果一致
$arr = db_find_one("SELECT * FROM bbs_user LIMIT 1"); 
print_r($arr);
  
?>
```


### `$cache`

#### 说明
```php
object|null $cache = !empty($conf['cache']) ? cache_new($conf['cache']) : NULL;
```
默认缓存实例。

如果配置文件设置了缓存相关的配置，则框架会自动实例化一个 Cache 类到 `$cache` 变量中。

通常**无需直接使用这个变量**，除非在升级，转换需要多个连接等特殊情况的时候。

**强烈建议使用 `cache_set()`、`cache_get()` 等cache系列函数**，它们都使用了 `$cache` 变量。

使用时，需要配置好 Cache 服务和 PHP 相关的 Cache 扩展。


#### 示例
```php
<?php 
$conf = include './conf/conf.php'; 
include './xiunophp/xiunophp.php'; 
  
$cache->set('key1', 'value1', 60); 
echo $cache->get('key1'); 

// 强烈建议使用 cache 系列函数，效果一致
cache_set('key1', 'value1', 60); 
echo cache_get('key1');
?>
```


### `$errno`

#### 说明
```php
int $errno = 0;
```
错误号，为 0 时，表示正常，非 0 时表示错误。

在某些函数被调用后发生错误，该全局变量会被设置，有点类似于 Linux C 的 `errno` 。

因为 PHP 是同步的（区别于异步）并且线程安全的，所以使用这种方法来返回错误会很方便。

一般配合 `$errstr` 一起使用，函数内通过 `xn_error($errno, $errstr)` 设置错误。

相比起抛出异常来，这种处理方式会轻量级很多。

> 【已弃用】请使用更合理的错误处理方式，包括抛出异常等。


#### 示例
```php
<?php 
include './xiunophp/xiunophp.php'; 
include './xiunophp/xn_send_mail.php'; 
  
$r = xn_send_mail(array(), 'username', 'test@gmail.com', '标题', '内容'); 
if($r === FALSE) { 
        echo "Errno:".$errno.", Errstr:".$errstr; 
} 
?>
```


### `$errstr`

#### 说明
```php
string $errstr = '';
```
错误字符串，与 `$errno` 配合使用。

> 【已弃用】请使用更合理的错误处理方式，包括抛出异常等。


## 数据库函数

Xiuno BBS 将 PDO 等数据库操作函数再次封装成 `db` 系列函数，开发者无需关心 PDO 等底层细节，也方便用户使用不同的数据库引擎，如 MySQL、SQLite 等。


### 通用参数说明

以下参数在多个数据库函数中通用，在此统一说明。


#### cond 条件数组详解

在使用数据库操作函数时，条件数组 `$cond` 用于构建 SQL 查询的 WHERE 子句。

条件数组的键对应数据库中的字段名称，而值则可以采用多种形式来表达查询条件：

- **简单等值匹配**
  - 当值不是数组时，表示对字段进行等值匹配。例如，`['id' => 123]` 将生成 `WHERE id = 123`。
- **多值匹配**
  - 当值是一个包含多个元素的数组时，表示字段的值需要匹配这些元素中的任何一个。例如，`['id' => array(1, 2, 3)]` 将生成 `WHERE (id=1 OR id=2 OR id=3)`。
- **范围匹配**
  - 当值是一个关联数组，其中键为比较运算符（如 `>`、`<`、`=` 等），值为比较值时，表示字段的值需要满足特定的范围或比较条件。例如，`array('age' => array('>' => 18, '<=' => 30))` 将生成 `WHERE age > 18 AND age <= 30`。
  - 支持小于、大于、小于等于、大于等于等比较运算符。
- **模糊匹配**
  - 当值是一个关联数组，且键为 `LIKE` (全大写) 时，表示字段的值需要部分匹配给定的字符串。例如，`['name' => array('LIKE' => 'John')]` 将生成 `WHERE name LIKE '%John%'`。你不需要特意写百分号，它会自动添加。


#### orderby 排序数组详解

排序数组 `$orderby` 用于构建 SQL 查询的 ORDER BY 子句，以确定查询结果的排序方式。数组的键对应数据库中的字段名称，而值决定了排序的方向：

- 如果值为 1，则表示该字段按**升序排列** `ASC`。例如，`['name' => 1]` 将对应 `ORDER BY name ASC`。
- 如果值**不是** 1（通常是 0 或 -1），则表示该字段按**降序排列** `DESC`。例如，`['date' => -1]` 将对应 `ORDER BY date DESC`。
  - 务必注意这一点，**如果不是 1，则表示降序排列。**
  - 因为这个确实有误导性，**高度建议自行定义两个常量来消除误会**，如 `const ASC = 1; const DESC = 0;`。

排序数组可以包含多个字段，以实现多级排序。例如，`['name' => 1, 'date' => -1]` 将首先按 name 字段升序排序，如果 name 字段值相同，则按 date 字段降序排序。排序的顺序将按照数组中的顺序进行。


#### key 参数详解

当指定了 `$key` 参数时（用于 `db_find()`、`db_sql_find()`），返回数组的键名则为 `$key` 参数指定的字段内容。例如，`db_find('bbs_thread', array(), array(), 1, 20, 'tid')` 返回的数组的键将会是 `tid` 的内容。在 foreach 循环等场合可能有用。

示例返回结构：
```php
array(
    80 => array(
        'tid' => 80,
        'subject' => '第一主题',
        'content' => '这是第一个帖子的内容',
    ),
    81 => array(
        'tid' => 81,
        'subject' => '第二主题',
        'content' => '这是第二个帖子的内容',
    ),
)
```


#### col 参数详解

用于指定要查询的字段列表（用于 `db_find()`、`db_find_one()`），默认为空数组，表示查询所有字段。例如：`db_find('bbs_thread', array(), array(), 1, 20, '', array('tid','subject'))` 将只会返回 tid 和 subject 字段的内容。适合"我不想要那么多数据"的场景。

示例返回结构：
```php
array(
    0 => array(
        'tid' => 80,
        'subject' => '第一主题'
    ),
    1 => array(
        'tid' => 81,
        'subject' => '第二主题'
    ),
)
```


#### 关于 SELECT * 还是指定字段

在 Xiuno BBS 的架构中，由于缓存层的存在，即使使用了 `SELECT *`，也不会对性能造成太大的影响，因为缓存能够减少数据库的直接访问次数。

**使用 `SELECT *` 的优点：**
- 不需要列出所有所需的字段名，减少了代码维护的工作量。
- 当数据库表结构发生变化时（如增加新字段），不需要修改查询代码。
- 在缓存机制完善的情况下，可以减少数据库的直接访问。

**使用 `SELECT *` 的缺点：**
- 在某些情况下，可能会暴露敏感信息，如密码等。
  - 所以 Xiuno BBS 选择在获取之后进行处理，如 `user_format` 函数会删掉密码字段。

**综上所述：** 在 Xiuno BBS 中，你可以放心地使用 `SELECT *`（即不指定 `$col` 参数）。


### DB 配置
在使用 DB 相关函数的时候，需要配置数据库相关信息。一般存放在配置文件中。


#### 说明
```php
$conf['db'] = array(
    'type' => 'pdo_mysql',
    'pdo_mysql' => array(
        'master' => array(
            'host' => 'localhost',
            'user' => 'root',
            'password' => 'root',
            'name' => 'test',
            'tablepre' => 'bbs_',
            'charset' => 'utf8',
            'engine' => 'myisam',
        ),
        'slaves' => array(),
    ),
);
```


#### 参数
- `type`: 数据库类型，目前支持 `pdo_mysql`
- `pdo_mysql.master`: 主库配置
  - `host`: 数据库主机地址
  - `user`: 数据库用户名
  - `password`: 数据库密码
  - `name`: 数据库名
  - `tablepre`: 表前缀
  - `charset`: 字符集
  - `engine`: 存储引擎（myisam 或 innodb）
- `pdo_mysql.slaves`: 从库配置数组（用于读写分离）


#### 示例
```php
<?php
$conf = include './conf.php';
include './xiunophp/xiunophp.php';
// 下面就可以使用 db 系列函数了
db_create(...);
db_update(...);
?>
```


### `db_insert()`

#### 说明
```php
function db_insert(string $table, array $arr, object|null $d = NULL): int|false
```
插入一条记录到指定表。


#### 参数
- `$table`: 表名（不含表前缀，函数会自动添加）
- `$arr`: 要插入的数据数组，键为字段名，值为字段值
- `$d`: 数据库连接实例，一般可以省略，使用全局默认连接


#### 返回值
- 成功返回插入的自增 ID
- 失败返回 FALSE


#### 示例
```php
<?php
$conf = include './conf.php';
include './xiunophp/xiunophp.php';
$arr = array(
    'username'=>'Jack',
    'email'=>'jack@email.com',
    'gid'=>123,
);
$uid = db_insert('user', $arr);
if($uid === FALSE) {
    echo $errstr;
} else {
    echo "创建成功，uid : $uid";
}
?>
```


### `db_create()`

#### 说明
```php
function db_create(string $table, array $arr, object|null $d = NULL): int|false
```
创建一条记录，功能与 `db_insert()` 等价。


#### 参数
- `$table`: 表名（不含表前缀）
- `$arr`: 要插入的数据数组
- `$d`: 数据库连接实例


#### 返回值
- 成功返回插入的自增 ID
- 失败返回 FALSE


#### 示例
```php
<?php
$conf = include './conf.php';
include './xiunophp/xiunophp.php';
$arr = array(
    'username'=>'Jack',
    'email'=>'jack@email.com',
    'gid'=>123,
);
$uid = db_create('user', $arr);
if($uid === FALSE) {
    echo $errstr;
} else {
    echo "创建成功，uid : $uid";
}
?>
```


### `db_replace()`

#### 说明
```php
function db_replace(string $table, array $arr, object|null $d = NULL): int|false
```
以替换的方式插入一条记录。如果记录存在（根据主键或唯一键判断），则更新；不存在则插入。


#### 参数
- `$table`: 表名（不含表前缀）
- `$arr`: 要替换的数据数组，必须包含主键或唯一键字段
- `$d`: 数据库连接实例


#### 返回值
- 成功返回插入的 ID 或受影响的行数
- 失败返回 FALSE


#### 示例
```php
<?php
$conf = include './conf.php';
include './xiunophp/xiunophp.php';
$arr = array(
    'uid'=>1,
    'username'=>'Jack',
    'email'=>'jack@email.com',
);
$uid = db_replace('user', $arr);
if($uid === FALSE) {
    echo $errstr;
} else {
    echo "替换创建成功，uid : $uid";
}
?>
```


### `db_update()`

#### 说明
```php
function db_update(string $table, array $cond, array $update, object|null $d = NULL): int|false
```
更新表中满足条件的记录。


#### 参数
- `$table`: 表名（不含表前缀）
- `$cond`: 条件数组，详见"cond 条件数组详解"
- `$update`: 要更新的数据数组
- `$d`: 数据库连接实例


#### 返回值
- 成功返回受影响的行数
- 失败返回 FALSE


#### 示例
```php
<?php
$conf = include './conf.php';
include './xiunophp/xiunophp.php';
// 将 uid=123 所在的行的 username 字段值更新为 Mike
$r = db_update('user', array('uid'=>123), array('username'=>'Mike'));
if($r === FALSE) {
    echo $errstr;
} else {
    echo "更新成功";
}
// 条件组合示例
$r = db_update('user', array('gid'=>1, 'create_date'=>array('>'=>100, '<'=>1000)), array('status'=>1));
?>
```


### `db_delete()`

#### 说明
```php
function db_delete(string $table, array $cond, object|null $d = NULL): int|false
```
删除表中满足条件的记录。

> **【慎用！】** 此操作不可逆。


#### 参数
- `$table`: 表名（不含表前缀）
- `$cond`: 条件数组，详见"cond 条件数组详解"
- `$d`: 数据库连接实例


#### 返回值
- 成功返回受影响的行数
- 失败返回 FALSE


#### 示例
```php
<?php
$conf = include './conf.php';
include './xiunophp/xiunophp.php';
$r = db_delete('user', array('uid'=>123));
if($r === FALSE) {
    echo $errstr;
} else {
    echo "删除成功";
}
?>
```


### `db_find_one()`

#### 说明
```php
function db_find_one(string $table, array $cond = array(), array $orderby = array(), array $col = array(), object|null $d = NULL): array|false
```
查询数据库，返回一条记录。

推荐和缓存机制一起使用，减少直接读取数据库的次数，优化性能。


#### 参数
- `$table`: 表名（不含表前缀）
- `$cond`: 条件数组，详见"cond 条件数组详解"
- `$orderby`: 排序数组，详见"orderby 排序数组详解"
- `$col`: 要查询的字段列表，详见"col 参数详解"
- `$d`: 数据库连接实例


#### 返回值
- 成功返回一维关联数组（一条记录）
- 失败返回 FALSE


#### 示例
```php
<?php
$conf = include './conf.php';
include './xiunophp/xiunophp.php';
// 查找 uid=1 的用户记录，返回关联数组
$arr = db_find_one('user', array('uid'=>123));
print_r($arr);
// 按创建时间倒序查找第一条记录
$arr = db_find_one('user', array(), array('create_date'=>-1));
?>
```


### `db_find()`

#### 说明
```php
function db_find(string $table, array $cond = array(), array $orderby = array(), int $page = 1, int $pagesize = 10, string $key = '', array $col = array(), object|null $d = NULL): array|false
```
查询数据库，返回多条记录，支持分页。

推荐和缓存机制一起使用，减少直接读取数据库的次数，优化性能。


#### 参数
- `$table`: 表名（不含表前缀）
- `$cond`: 条件数组，详见"cond 条件数组详解"
- `$orderby`: 排序数组，详见"orderby 排序数组详解"
- `$page`: 页码，从 1 开始
- `$pagesize`: 每页记录条数
- `$key`: 返回数组的键名，详见"key 参数详解"
- `$col`: 要查询的字段列表，详见"col 参数详解"
- `$d`: 数据库连接实例


#### 返回值
- 成功返回二维数组（多条记录）
- 失败返回 FALSE


#### 示例
```php
<?php
$conf = include './conf.php';
include './xiunophp/xiunophp.php';
// 查找 gid=1 的用户记录，返回所有符合的记录
$arrlist = db_find('user', array('gid'=>1));
print_r($arrlist);
// 查找 gid=1 && uid > 1 && uid < 100 的用户记录，按 uid 正序排序
$arrlist = db_find('user', array('gid'=>1, 'uid'=>array('>'=>1, '<'=>100)), array('uid'=>1));
print_r($arrlist);
// 分页查询，每页 20 条，取第 1 页
$arrlist = db_find('user', array(), array('uid'=>-1), 1, 20);
?>
```


### `db_count()`

#### 说明
```php
function db_count(string $table, array $cond = array(), object|null $d = NULL): int|false
```
统计表中满足条件的记录数。适合用于查询数据总数量。


#### 参数
- `$table`: 表名（不含表前缀）
- `$cond`: 条件数组，详见"cond 条件数组详解"
- `$d`: 数据库连接实例


#### 返回值
- 成功返回总行数
- 失败返回 FALSE


#### 示例
```php
<?php
$conf = include './conf.php';
include './xiunophp/xiunophp.php';
$n = db_count('user');
if($n === FALSE) {
    echo $errstr;
} else {
    echo "总行数为：$n";
}
// 统计 gid=1 的用户数量
$n = db_count('user', array('gid'=>1));
?>
```


### `db_maxid()`

#### 说明
```php
function db_maxid(string $table, string $field, array $cond = array(), object|null $d = NULL): int|false
```
查找表中某列的最大值。最适合用于在插入数据时，定义数据的主键ID为“当前最大ID加一”的场合。

不建议使用该函数查询数据总数量，因为ID不能反映中间有删除数据的情况。


#### 参数
- `$table`: 表名（不含表前缀）
- `$field`: 列名
- `$cond`: 条件数组，详见"cond 条件数组详解"
- `$d`: 数据库连接实例


#### 返回值
- 成功返回最大值
- 失败返回 FALSE


#### 示例
```php
<?php
$conf = include './conf.php';
include './xiunophp/xiunophp.php';
$maxuid = db_maxid('user', 'uid');
if($maxuid === FALSE) {
    echo $errstr;
} else {
    echo "最大 UID 为：$maxuid";
}
?>
```


### `db_connect()`

#### 说明
```php
function db_connect(object|null $d = NULL): bool
```
测试数据库服务器能否连通。


#### 参数
- `$d`: 数据库连接实例


#### 返回值
- 成功返回 TRUE
- 失败返回 FALSE


#### 示例
```php
<?php
$conf = include './conf.php';
include './xiunophp/xiunophp.php';
$r = db_connect();
if($r === FALSE) {
    echo $errstr;
} else {
    echo "成功";
}
?>
```


### `db_truncate()`

#### 说明
```php
function db_truncate(string $table, object|null $d = NULL): bool
```
清空表数据。

> **【慎用！】** 此操作不可逆。


#### 参数
- `$table`: 表名（不含表前缀）
- `$d`: 数据库连接实例


#### 返回值
- 成功返回 TRUE
- 失败返回 FALSE


#### 示例
```php
<?php
$conf = include './conf.php';
include './xiunophp/xiunophp.php';
$r = db_truncate('user');
if($r === FALSE) {
    echo $errstr;
} else {
    echo "表数据已经清空";
}
?>
```


### `db_sql_find()`

#### 说明
```php
function db_sql_find(string $sql, string|null $key = NULL, object|null $d = NULL): array|false
```
通过原生 SQL 语句查询多条记录。


#### 参数
- `$sql`: SQL 语句，目前仅支持 MySQL
  - 不支持预编译语句（Prepared Statement）！可能会引入SQL注入攻击面，请务必在实际执行之前多次检查即将传入该函数的SQL是否有良好转义
- `$key`: 使用指定列的值作为返回数组的键，详见"key 参数详解"
- `$d`: 数据库连接实例


#### 返回值
- 成功返回二维数组（多条记录）
- 失败返回 FALSE


#### 示例
```php
<?php
$conf = include './conf.php';
include './xiunophp/xiunophp.php';
$arrlist = db_sql_find("SELECT * FROM bbs_user LIMIT 10");
if($arrlist === FALSE) {
    echo $errstr;
} else {
    print_r($arrlist);
}
?>
```


### `db_sql_find_one()`

#### 说明
```php
function db_sql_find_one(string $sql, object|null $d = NULL): array|false
```
通过原生 SQL 语句查询单条记录。


#### 参数
- `$sql`: SQL 语句，目前仅支持 MySQL
  - 不支持预编译语句（Prepared Statement）！可能会引入SQL注入攻击面，请务必在实际执行之前多次检查即将传入该函数的SQL是否有良好转义
- `$d`: 数据库连接实例


#### 返回值
- 成功返回一维关联数组（一条记录）
- 失败返回 FALSE


#### 示例
```php
<?php
$conf = include './conf.php';
include './xiunophp/xiunophp.php';
$arr = db_sql_find_one("SELECT * FROM bbs_user WHERE uid=1");
if($arr === FALSE) {
    echo $errstr;
} else {
    print_r($arr);
}
?>
```


### `db_exec()`

#### 说明
```php
function db_exec(string $sql, object|null $d = NULL): int|false
```
执行一条原生 SQL 语句。


#### 参数
- `$sql`: SQL 语句，目前仅支持 MySQL
  - 不支持预编译语句（Prepared Statement）！可能会引入SQL注入攻击面，应只在创建表、修改表结构、删除表的时候使用
- `$d`: 数据库连接实例


#### 返回值
- 成功返回受影响的行数
- 失败返回 FALSE


#### 示例
```php
<?php
$conf = include './conf.php';
include './xiunophp/xiunophp.php';
$r = db_exec("ALTER TABLE bbs_user DROP COLUMN username");
if($r === FALSE) {
    echo $errstr;
} else {
    echo "执行成功。";
}
?>
```


### `db_new()`

#### 说明
```php
function db_new(array $dbconf): object|false
```
根据配置参数实例化一个数据库连接对象。

一般不需要调用此函数，除非需要多个数据库连接。


#### 参数
- `$dbconf`: 数据库配置数组，格式与 `$conf['db']` 相同


#### 返回值
- 成功返回数据库连接对象
- 失败返回 FALSE


#### 示例
```php
<?php
$dbconf = array(
    'type' => 'pdo_mysql',
    'pdo_mysql' => array(
        'master' => array(
            'host' => 'localhost',
            'user' => 'root',
            'password' => 'root',
            'name' => 'test',
            'tablepre' => 'bbs_',
            'charset' => 'utf8',
            'engine' => 'innodb',
        ),
        'slaves' => array(),
    ),
);
include './xiunophp/xiunophp.php';
$newdb = db_new($dbconf);
echo $newdb->version();
?>
```


### `db_close()`

#### 说明
```php
function db_close(object|null $db = NULL): bool
```
关闭数据库连接。


#### 参数
- `$db`: 数据库连接实例


#### 返回值
- 成功返回 TRUE
- 失败返回 FALSE


#### 示例
```php
<?php
$dbconf = array(
    'type' => 'pdo_mysql',
    'pdo_mysql' => array(
        'master' => array(
            'host' => 'localhost',
            'user' => 'root',
            'password' => 'root',
            'name' => 'test',
            'tablepre' => 'bbs_',
            'charset' => 'utf8',
            'engine' => 'innodb',
        ),
        'slaves' => array(),
    ),
);
include './xiunophp/xiunophp.php';
$newdb = db_new($dbconf);
db_close($newdb);
?>
```

## 缓存函数

### CACHE 配置

在使用缓存相关函数之前，需要在配置文件中设置缓存相关信息。

#### 示例配置
```php
$conf['cache'] = array(
    'enable' => 1,
    'type' => 'redis', // 支持 redis, memcached, pdo_mysql, mysql, xcache, apc, yac
    'redis' => array(
        'host' => '127.0.0.1',
        'port' => 6379,
        'timeout' => 0,
        'pconnect' => 1,
        'password' => '',
        'db' => 0,
        'cachepre' => 'xiuno_',
    ),
);
```

### `cache_set()`

#### 说明
```php
function cache_set(string $k, mixed $v, int $life = 0, object|null $c = NULL): bool
```
设置缓存。

#### 参数
- `$k`: 缓存键名
- `$v`: 缓存值
- `$life`: 缓存生命周期（秒），0 表示永久
- `$c`: 缓存实例，默认为全局缓存实例

#### 返回值
- 成功返回 TRUE
- 失败返回 FALSE

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
// 设置缓存，有效期 60 秒
$r = cache_set('key1', 'value1', 60);
echo $r ? '成功' : '失败';

// 设置永久缓存
$r = cache_set('key2', array('a'=>1, 'b'=>2));
```

### `cache_get()`

#### 说明
```php
function cache_get(string $k, object|null $c = NULL): mixed
```
获取缓存。

#### 参数
- `$k`: 缓存键名
- `$c`: 缓存实例，默认为全局缓存实例

#### 返回值
- 成功返回缓存值
- 失败或缓存不存在返回 FALSE

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
// 获取缓存
$value = cache_get('key1');
if($value !== FALSE) {
    echo "缓存值: $value";
} else {
    echo "缓存不存在";
}
```

### `cache_delete()`

#### 说明
```php
function cache_delete(string $k, object|null $c = NULL): bool
```
删除缓存。

#### 参数
- `$k`: 缓存键名
- `$c`: 缓存实例，默认为全局缓存实例

#### 返回值
- 成功返回 TRUE
- 失败返回 FALSE

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
// 删除缓存
$r = cache_delete('key1');
echo $r ? '成功' : '失败';
```

### `cache_truncate()`

#### 说明
```php
function cache_truncate(object|null $c = NULL): bool
```
清空所有缓存。

> 【注意】尽量避免调用此方法，不会清理保存在 kv 中的数据，逐条 cache_delete() 比较保险。

#### 参数
- `$c`: 缓存实例，默认为全局缓存实例

#### 返回值
- 成功返回 TRUE
- 失败返回 FALSE

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
// 清空所有缓存
$r = cache_truncate();
echo $r ? '成功' : '失败';
```

### `cache_new()`

#### 说明
```php
function cache_new(array $cacheconf): object|null
```
根据配置参数实例化一个缓存连接对象。

一般不需要调用此函数，除非需要多个缓存连接。

#### 参数
- `$cacheconf`: 缓存配置数组

#### 返回值
- 成功返回缓存连接对象
- 失败返回 FALSE

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
$cacheconf = array(
    'enable' => 1,
    'type' => 'redis',
    'redis' => array(
        'host' => '127.0.0.1',
        'port' => 6379,
        'cachepre' => 'xiuno_',
    ),
);
$cache = cache_new($cacheconf);
// 使用自定义缓存实例
cache_set('key1', 'value1', 60, $cache);
```

## 数组增强

### `array_value()`

#### 说明
```php
function array_value(array $arr, string|int $key, mixed $default =''): mixed
```
以无 Notice 的方式返回数组中指定的键对应的值。

> 【推荐替代做法】使用空合并运算符或三目运算符容易理解，性能也更高 `isset($arr['key']) ? $arr['key'] : 'default'` 或 `$arr['key'] ?? 'default'` 

#### 参数
- `$arr`:数组
- `$key`：键名
- `$default` ：在不存在的时候所用的默认值

#### 返回值
- 如果键存在，返回对应的值
- 如果键不存在，返回默认值

#### 示例

### `array_filter_empty()`

#### 说明
```php
function array_filter_empty(array $arr): array
```

过滤过滤数组中值为空的元素。
> 【推荐替代做法】使用`array_filter()`函数仅需过滤，当不提供第二个参数时就会移除 null、false、空字符串、0、字符串'0'、空数组[]

#### 参数
- `$arr`:数组

#### 返回值
- 返回过滤后的数组

#### 示例

### `array_addslashes()`

#### 说明
```php
function array_addslashes(array &$var): void
```
对数组的每一个元素应用 addslashes()函数。

#### 参数
- `$var`：要操作的数组

#### 返回值
数组会就地修改，不返回任何值。

#### 示例

### `array_stripslashes()`

#### 说明
```php
function array_stripslashes(array &$var): void
```
对数组的每一个元素应用 stripslashes()函数。

#### 参数
- `$var`：要操作的数组

#### 返回值
数组会就地修改，不返回任何值。

#### 示例

### `array_htmlspecialchars()`

#### 说明
```php
function array_htmlspecialchars(array &$var): void
```
对数组的每一个元素应用 htmlspecialchars()函数。

#### 参数
- `$var`：要操作的数组

#### 返回值
数组会就地修改，不返回任何值。

#### 示例

### `array_trim()`

#### 说明
```php
function array_trim(array &$var): void
```
对数组的每一个元素应用 trim()函数。

#### 参数
- `$var`：要操作的数组

#### 返回值
数组会就地修改，不返回任何值。

#### 示例

### `array_diff_value()`

#### 说明
```php
function array_diff_value(array $arr1, array $arr2): array
```
比较数组的值，如果不相同则保留，以第一个数组为准。

与 array_diff() 不同在于，支持关联数组。

> 【不推荐使用】经过测试，和php原生的array_diff效果一致，且array_diff支持更多参数。

#### 参数
- `$arr1`：基准数组
- `$arr2`：比较的数组

#### 返回值
- 返回一个数组，包含基准数组中值与比较数组中值不同的键值对。

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
$arr1 = array('id'=>1, 'name'=>'Alice','id'=>2, 'name'=>'Charlie');
$arr2 = array('id'=>1, 'name'=>'Bob','id'=>2, 'name'=>'Charlie');
$arr = array_diff_value($arr1, $arr2);
/* 结果：
array('name'=>'Bob')
*/
```

### 介绍：什么叫“arrlist”
在XiunoPHP的语境下，**“arrlist” 是一个缩写，特指“二维关联数组”**。简单来说，就是一个数组，它的每一个元素本身也是一个关联数组。这种数据结构通常用来表示从数据库中查询出的多条记录。

让我们用一个具体的例子来说明。假设我们从一个名为 `bbs_user` 的数据库表中查询了几个用户，`db_find()` 函数返回的结果就是一个标准的 `arrlist`：

```php
<?php
// 这是一个 arrlist (二维关联数组)
$arrlist = array(
    0 => array(
        'uid' => 1,
        'username' => 'admin',
        'gid' => 1,
        'create_date' => 1448335223,
    ),
    1 => array(
        'uid' => 2,
        'username' => 'jack',
        'gid' => 2,
        'create_date' => 1448335224,
    ),
    2 => array(
        'uid' => 3,
        'username' => 'rose',
        'gid' => 2,
        'create_date' => 1448335225,
    ),
);
?>
```

`$arrlist` 这个变量就是一个典型的 `arrlist`。它的键是数字索引（0, 1, 2...），值则是多个“行”数据，每一行都是一个关联数组（键为字段名，值为字段内容）。

之所以要发明 `arrlist_` 系列函数，是因为在PHP原生的函数库中，针对这种常见数据结构的操作（如排序、筛选、重组）并没有提供专门的函数，需要开发者手动编写循环来实现。XiunoPHP提供的 `arrlist_` 系列函数，正是为了简化对这些“二维关联数组”的操作，让代码更加简洁、易读。

因此，当你看到 `arrlist_multisort()`、`arrlist_cond_orderby()`、`arrlist_key_values()` 等函数时，请记住，它们都是为处理这种从数据库查询结果中返回的“二维关联数组”而设计的。


### `array_assoc_slice()`

#### 说明
```php
function array_assoc_slice(array $arrlist, int $start, int $length = 0): array
```
array_slice() 的关联数组版本。

#### 参数
- `$arrlist`：要操作的二维数组
- `$start`：开始位置（可以为负数，表示从后往前数）
- `$length`：截取长度（可以为负数，表示从后往前数）

#### 返回值
- `$length` 为 0 时，返回从 `$start` 开始到数组末尾的元素
- `$length` 为正数时，返回从 `$start` 开始的 `$length` 个元素
- `$length` 为负数时，返回从 `$start` 开始的 `$length` 个元素（从后往前数）

#### 示例
```php
<?php
$conf = include './conf.php';
include './xiunophp/xiunophp.php';
$arrlist = array('a'=>1, 'b'=>2, 'c'=>3, 'd'=>4);
$arrlist = array_assoc_slice($arrlist, 0, 2);
print_r($arrlist);
/* 结果：
array('a'=>1, 'b'=>2);
*/
```

### `arrlist_multisort()`

#### 说明
```php
function arrlist_multisort(array $arrlist, string $col, bool $asc = TRUE): array
```
对二维的关联数组进行排序。

一般应用在对从数据库中取出来的二维数组进行排序。

#### 参数
- `$arrlist`：要操作的二维数组
- `$col`：要排序的列名，必须是二维数组中的键名
- `$asc`：是否升序排序，默认为 TRUE，传入false表示降序排序
  - 在实践中建议自行定义两个常量，分别表示升序和降序排序，来防止自己搞混

#### 返回值
- 返回排序后的二维数组

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
$data = array(
    ['volume' => 67, 'edition' => 2],
    ['volume' => 86, 'edition' => 1],
    ['volume' => 85, 'edition' => 6],
    ['volume' => 98, 'edition' => 2],
    ['volume' => 86, 'edition' => 6],
    ['volume' => 67, 'edition' => 7],
);
$r = arrlist_multisort($data, 'edition', TRUE);
print_r($r);
/* 结果：
Array
(
[0] => Array
(
[volume] => 86
[edition] => 1
)
[1] => Array
(
[volume] => 67
[edition] => 2
)
[2] => Array
(
[volume] => 98
[edition] => 2
)
[3] => Array
(
[volume] => 85
[edition] => 6
)
[4] => Array
(
[volume] => 86
[edition] => 6
)
[5] => Array
(
[volume] => 67
[edition] => 7
)
)
*/
```


### `arrlist_cond_orderby()`

#### 说明
```php
function arrlist_cond_orderby($arrlist, $cond = array(), $orderby = array(), $page = 1, $pagesize = 20): array
```
对二维关联数组进行查找，排序，筛选，只支持一种条件排序。

#### 参数
- `$arrlist`: 二维数组
- `$cond`: 条件，详见“数据库函数”章节
- `$orderby`: 排序条件，详见“数据库函数”章节
- `$page`: 页码
- `$pagesize`: 每页数量

#### 返回值
- 返回排序后的二维数组

#### 示例
```php
<?php
$conf = include './conf.php';
include './xiunophp/xiunophp.php';
$arrlist = array(
  ['a'=>1,'b'=>1,'c'=>1],
  ['a'=>2,'b'=>2,'c'=>2],
  ['a'=>3,'b'=>3,'c'=>3],
);
// 查找 a=1，结果集按照 b 倒序，取第一页数据，每页 20 条
$arrlist = arrlist_cond_orderby($arrlist, array('a'=>1), array('b'=>-1), 1, 20);
print_r($arrlist);
```

### `arrlist_key_values()`

#### 说明
```php
function arrlist_key_values($arrlist, $key, $value = NULL): array
```
以指定的 `$key`及 `$value`，从二维关联数组中整理整理出一维关联数组。

#### 参数
- `$arrlist`: 二维数组
- `$key`: 结果的键
- `$value`: 结果的值

#### 返回值
- 返回一维关联数组

#### 示例
```php
<?php
$conf = include './conf.php';
include './xiunophp/xiunophp.php';
$arrlist = array(
array('id'=>1, 'name'=>'Jack', 'age'=>30),
array('id'=>2, 'name'=>'Rose', 'age'=>25),
);
$arrlist = arrlist_key_values($arrlist, 'name', 'id');
print_r($arrlist);
/* 结果：
array(
'Jack'=>1,
'Rose'=>2,
);
*/
```

### `arrlist_values()`

#### 说明
```php
function arrlist_values($arrlist, $key): array
```
以指定的 `$key`，从二维关联数组中获取一个自然数字索引的数组。

#### 参数
- `$arrlist`: 二维数组
- `$key`: 结果的键

#### 返回值
- 返回一维数组

#### 示例
```php
<?php
$conf = include './conf.php';
include './xiunophp/xiunophp.php';
$arrlist = array(
array('id'=>1, 'name'=>'Jack'),
array('id'=>2, 'name'=>'Rose'),
);
$arr = arrlist_values($arrlist, 'name');
print_r($arr);
/* 结果：
array('Jack', 'Rose');
*/
```

### `arrlist_sum()`

#### 说明
```php
function arrlist_sum(array $arrlist, string $key): int
```
从一个二维数组中对某一列求和。

#### 参数
- `$arrlist`: 二维数组
- `$key`: 要求和的列名

#### 返回值
- 返回指定列的和
- 如果数组为空，返回 0

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
$arrlist = array(
    array('id'=>1, 'score'=>85),
    array('id'=>2, 'score'=>90),
    array('id'=>3, 'score'=>75),
);
$total = arrlist_sum($arrlist, 'score');
echo $total; // 结果：250
```

### `arrlist_max()`

#### 说明
```php
function arrlist_max(array $arrlist, string $key): mixed
```
从一个二维数组中对某一列求最大值。

#### 参数
- `$arrlist`: 二维数组
- `$key`: 要求最大值的列名

#### 返回值
- 返回指定列的最大值
- 如果数组为空，返回 0

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
$arrlist = array(
    array('id'=>1, 'score'=>85),
    array('id'=>2, 'score'=>90),
    array('id'=>3, 'score'=>75),
);
$max = arrlist_max($arrlist, 'score');
echo $max; // 结果：90
```

### `arrlist_min()`

#### 说明
```php
function arrlist_min(array $arrlist, string $key): mixed
```
从一个二维数组中对某一列求最小值。

#### 参数
- `$arrlist`: 二维数组
- `$key`: 要求最小值的列名

#### 返回值
- 返回指定列的最小值
- 如果数组为空，返回 0

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
$arrlist = array(
    array('id'=>1, 'score'=>85),
    array('id'=>2, 'score'=>90),
    array('id'=>3, 'score'=>75),
);
$min = arrlist_min($arrlist, 'score');
echo $min; // 结果：75
```

### `arrlist_change_key()`

#### 说明
```php
function arrlist_change_key(array $arrlist, string $key = '', string $pre = ''): array
```
将二维数组的键更换为某一列的值。在对多维数组排序后，数字键会丢失，需要此函数。

#### 参数
- `$arrlist`: 二维数组
- `$key`: 要作为新键的列名
- `$pre`: 新键的前缀

#### 返回值
- 返回键为指定列值的二维数组
- 如果 `$key` 为空，则返回原数组

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
$arrlist = array(
    array('id'=>1, 'name'=>'Jack'),
    array('id'=>2, 'name'=>'Rose'),
);
// 使用 id 列作为新键
$new_arrlist = arrlist_change_key($arrlist, 'id');
print_r($new_arrlist);
/* 结果：
array(
    1 => array('id'=>1, 'name'=>'Jack'),
    2 => array('id'=>2, 'name'=>'Rose'),
);
*/

// 使用 id 列作为新键，并添加前缀
$new_arrlist2 = arrlist_change_key($arrlist, 'id', 'user_');
print_r($new_arrlist2);
/* 结果：
array(
    'user_1' => array('id'=>1, 'name'=>'Jack'),
    'user_2' => array('id'=>2, 'name'=>'Rose'),
);
*/
```

### `arrlist_keep_keys()`

#### 说明
```php
function arrlist_keep_keys(array $arrlist, array $keys): array
```
保留二维数组中指定的键。

#### 参数
- `$arrlist`: 二维数组
- `$keys`: 要保留的键名数组

#### 返回值
- 返回只包含指定键的二维数组

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
$arrlist = array(
    array('id'=>1, 'name'=>'Jack', 'age'=>30),
    array('id'=>2, 'name'=>'Rose', 'age'=>25),
);
// 只保留 id 和 name 键
$new_arrlist = arrlist_keep_keys($arrlist, array('id', 'name'));
print_r($new_arrlist);
/* 结果：
array(
    0 => array('id'=>1, 'name'=>'Jack'),
    1 => array('id'=>2, 'name'=>'Rose'),
);
*/
```

### `arrlist_chunk()`

#### 说明
```php
function arrlist_chunk(array $arrlist, string $key): array
```
根据某一列的值对二维数组进行分组。

#### 参数
- `$arrlist`: 二维数组
- `$key`: 用于分组的列名

#### 返回值
- 返回以指定列值为键的二维数组，每个键对应一个分组后的二维数组

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
$arrlist = array(
    array('id'=>1, 'name'=>'Jack', 'gid'=>1),
    array('id'=>2, 'name'=>'Rose', 'gid'=>2),
    array('id'=>3, 'name'=>'Tom', 'gid'=>1),
    array('id'=>4, 'name'=>'Jerry', 'gid'=>2),
);
// 按 gid 列分组
$grouped = arrlist_chunk($arrlist, 'gid');
print_r($grouped);
/* 结果：
array(
    1 => array(
        0 => array('id'=>1, 'name'=>'Jack', 'gid'=>1),
        1 => array('id'=>3, 'name'=>'Tom', 'gid'=>1),
    ),
    2 => array(
        0 => array('id'=>2, 'name'=>'Rose', 'gid'=>2),
        1 => array('id'=>4, 'name'=>'Jerry', 'gid'=>2),
    ),
);
*/
```

## 杂项函数

### `xn_strlen()`

#### 说明
```php
xn_strlen(string $s): int
```
UTF-8 的编码方式求字符串长度。

> 【已弃用】请直接使用mb_strlen()函数

#### 参数
- `$s` - 字符串

#### 返回值
返回 `$s` 的字数。

#### 示例

### `xn_substr()`

#### 说明
```php
xn_substr(string $s, int $start, int $len)
: string
```
UTF-8 的编码方式截取字符串。
> 【已弃用】请直接使用mb_substr()函数

#### 参数
- `$s`：要处理的字符串
- `$start`：开始位置
- `$len`：截取长度（可以为负数，表示从后往前数)

#### 返回值

#### 示例
```php
```

### `xn_urlencode()`

#### 说明
```php
function xn_urlencode(string $s): string
```
将 URL 编码中的 % 替换为 _，用于兼容某些路由规则。

> 【推荐替代做法】新项目推荐直接使用原生 urlencode() / urldecode()

#### 参数
- `$s`: 要编码的字符串

#### 返回值
- 返回编码后的字符串

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
$s = 'Hello World!';
$encoded = xn_urlencode($s);
echo $encoded; // 结果：Hello_World_21
```

### `xn_urldecode()`

#### 说明
```php
function xn_urldecode(string $s): string
```
解码由 xn_urlencode() 编码的字符串。

> 【推荐替代做法】新项目推荐直接使用原生 urlencode() / urldecode()

#### 参数
- `$s`: 要解码的字符串

#### 返回值
- 返回解码后的字符串

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
$s = 'Hello_World_21';
$decoded = xn_urldecode($s);
echo $decoded; // 结果：Hello World!
```

### `xn_json_encode()`

#### 说明
```php
function xn_json_encode(mixed $arg): string
```
对变量进行 JSON 编码，不对中文进行 UNICODE 转义。

> 【已弃用】请直接使用 json_encode($arr, JSON_UNESCAPED_UNICODE)

> 【你知道吗】在该函数内还有一套针对PHP版本小于5.2的手动拼接JSON的代码

#### 参数
- `$arg`：要编码的变量

#### 返回值
- 编码后的 JSON 字符串

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
$arr = ['name'=>'中文'];
$json = xn_json_encode($arr);
echo $json;
```

### `xn_json_decode()`

#### 说明
```php
function xn_json_decode(string $json): array
```
对 JSON 格式的字符串进行解码，返回数组（非对象）。

> 【已弃用】请直接使用json_decode($json, true)

#### 参数
- `$json`：要解码的 JSON 字符串

#### 返回值
- 解码后的数组
```php
$arr = xn_json_decode($json);
```

### `xn_encrypt()`

#### 说明
```php
function xn_encrypt(string $txt, string $key = ''): string
```
对字符串的可逆加密函数。根据 $key 随机生成，结果可以直接 URL 传递。

此函数有 C 语言实现并且有所增强的 PHP 扩展版本(`xiuno.so`)，比 PHP 速度快 200+ 倍，对安全性和速度要求较高的情况下可以使用，可以定制 key，将 key 存放于 `.so` （内存）中（key 明文存放于文件不够安全），可以绑定 cpuid，防止 so 被拷贝后运行，有需要的请与我联系：axiuno@gmail.com

> 【已弃用】推荐使用 openssl_encrypt() 或专业的加密库（如 defuse/php-encryption）

#### 参数
- `$txt`：要加密的字符串
- `$key`：加密密钥

#### 返回值
- 加密后的字符串

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
$txt = "1\n21232f297a57a5a743894a0e4a801fc3\n127.0.0.1";
$result = xn_encrypt($txt, 'KSLEKFJ2K2KK');
echo $result;
```

### `xn_decrypt()`

#### 说明
```php
function xn_decrypt(string $txt, string $key = ''): string
```
解密由 xn_encrypt() 加密的字符串。
> 【已弃用】推荐使用 openssl_decrypt() 或专业的解密库（如 defuse/php-encryption）

#### 参数
- `$txt`：要解密的字符串
- `$key`：加密密钥

#### 返回值
- 解密后的字符串

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
$txt = "nUg0N_2F1sxjgARMREdcpfdONmDLiEL79QiyD9rSKLqr8wklbn7_2bdreFHInHGrdqU_2F";
$result = xn_decrypt($txt, 'KSLEKFJ2K2KK');
echo $result;
```


### `xn_message()`

#### 说明
```php
function xn_message(int|string $code, mixed $message): array|string
function message(int|string $code, mixed $message): array|string
```
根据请求的方式，输出不同格式的内容，并且终止会话。

如果为 AJAX 请求，则输出 json 格式数据：{code: $code, message: $message}。

如果为普通请求，则输出 $message。

**强制性要求**：在xiuno bbs中必须使用`message()`函数，不能使用`xn_message()`函数。

> 【注意】使用时请务必前端后端商量好code和message的定义。
> 
> 根据共识：
> - `$code = 0` 成功
> - `$code = 1` 用户导致的错误，如参数错误、登录失败等
> - `$code = -1` 服务器导致的错误，如数据库错误等
> - `$message` 通常为string类型，错误信息
>
> 但是在Xiuno BBS里有一类特例：
> - `$code = 字符串` 表示在前端某个ID为该字符串的元素中，通过tooltip的方式显示`$message`。
> - `$code = -101` 用于Xiuno BBS的`$.ajax_modal`函数，表示将本函数输出的JSON数据中的message字段按HTML方式解析，显示在弹窗中，并隐藏modal-footer。
> - `$message` 为array或其他类型，为插件定义的非标准自定义用法。
> 
> 【注意】JSON输出的code字段，即使是int类型，也有可能因为PHP版本不同等细节输出成字符串，需要在前端进行判断和转换。
> 例如`{"code": "0", "message": "操作成功"}` 如果直接比较 `if(code === 0)`的话可能会失败

#### 参数
- `$code`：错误码
- `$message`：错误信息

#### 返回值
- JSON 格式的字符串或普通字符串或HTML字符串

#### 示例

### `xn_error()`

#### 说明
```php
function xn_error(int $no, string $str, bool $return = FALSE): mixed
```
对全局变量 $errno, $errstr 进行设置，并且默认返回 FALSE。

> 【已弃用】请使用正常的错误处理方式

#### 参数
- `$no`：错误码
- `$str`：错误字符串
- `$return`：返回值

#### 返回值
- 将会返回在`$return`定义的内容，默认为false

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
function check_email($email) {
if(empty($email)) {
return xn_error(-1, 'Email 为空');
}
}
my_func('') OR echo $errstr;
```

### `xn_log()`

#### 说明
```php
function xn_log(string $s, string $file = 'error'): void
```

记录信息到日志。

默认存到 `$conf['log_path']` ，一个月一个目录。

#### 参数
- `$s`：记录到日志的信息
- `$file`：记录的文件名

#### 返回值
- 不返回任何内容

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
xn_log("the log");
```

#### 日志格式
日志会存储在`$conf['log_path']`所指的目录，起点是程序根目录（也就是index.php中定义的`APP_PATH`），例如`'log_path' => './log/'`表示网站根目录的log目录。

在该目录里会有“YYYYMM”格式的文件夹若干，类似“202604”。

在每个月的目录中会有若干日志文件（文件名为`$file`参数的值，加上后缀`.php`），根据共识，会有这些文件存在：

- `admin_login.php` ：管理员登录日志
- `admin_login_error.php` ：管理员登录错误日志
- `db_error.php` ：数据库错误日志
- `db_exec.php` ：数据库执行日志
- `debug_error.php` ：调试错误日志
- `php_error.php` ：PHP错误日志

但也可能有更多插件定义的日志文件。

单个日志文件并不是真的PHP文件，而是用制表符分隔的CSV文件。可以重命名对应文件的后缀名为`.csv`来使用Excel打开。

- 列1：固定为`<?php exit;?>`，用于阻止PHP输出内容，应忽略
- 列2：时间戳，格式为YYYY-M-D H:M:S
- 列3：发起请求的客户端IP地址
- 列4：请求的网址
- 列5：请求的UID，如果是游客的话这里的值是0
- 列6：日志的内容（`$s`的值）


### `xn_txt_to_html()`

#### 说明
```php
function xn_txt_to_html(string $s):string
```
将文本格式转换为 HTML 格式。

相当于先进行`htmlspecialchars`，然后进行以下`str_replace`：
- 单个空格替换为`&nbsp;`
- 制表符`\t`替换为四个`&nbsp;`
- Windows风格换行`\r\n`替换为Linux风格换行`\n`
- Linux风格换行`\n`替换为`<br>`

可以使用`nl2br(htmlspecialchars($s))`作为替代品。


#### 参数
- `$s`：文本数据

#### 返回值
- 经过处理的文本数据

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
$s = 'TEXT 文本数据：\r\n 内容';
$s2 = xn_txt_to_html($s);
echo $s2;
// 结果：TEXT 文本数据：<br>&nbsp;内容
```

### `xn_rand()`

#### 说明
```php
function xn_rand($n = 16): string 
```
产生随机字符串。

字符集是：`23456789ABCDEFGHJKMNPQRSTUVWXYZ` （去掉了容易混淆的 `0`、`1`、`I`、`L`、`O` 等）。

> 【警告】本函数并不会生成安全加密的值，并且不可用于加密或者要求返回值不可猜测的目的。

#### 参数
- `$n`：位数

#### 返回值
- 伪随机字符串

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
$s = xn_rand(16);
echo $s;
// 结果可能为：M2E9HX8DP3T6WJ5K
```

### `xn_is_writable()`

#### 说明
```php
function xn_is_writable(string $file): bool
```
检测文件或目录是否可写，兼容 Windows。

是`is_writable`函数的替代版本。

#### 参数
- `$file`：文件/目录路径

#### 返回值

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
$file = './log/';    // 目录以斜杠结尾
//$file = './log/test.txt';    // 文件
$r = xn_is_writable($file);
```

### `humandate()`

#### 说明
```php
function humandate(int $timestamp, array $lan = array()): string
```
友好的显示日期。

只支持显示大约最近一年的日期。超过一年则显示为“YYYY-MM-DD”格式。不支持未来的时间。

可自行定义名为`custom_humandate`的全局函数来覆盖默认行为。函数签名如下：
```php
function custom_humandate(int $timestamp, array $lan = array()): string
```


#### 参数
- `$timestamp`：UNIX 时间戳
- `$lan`：语言包

`$lan`参数在函数内的默认值：
```php
$lan = array(
'month_ago'=>'月前',
'day_ago'=>'天前',
'hour_ago'=>'月前',
'minute_ago'=>'月前',
'second_ago'=>'月前',
);
```

#### 返回值
- 友好的日期字符串

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
$n = time() - 86401;
echo humandate($n);
// 结果： 1 天前
```

### `humannumber()`

#### 说明
```php
function humannumber(int $n): string
```
友好的数字显示。

仅实现了一个功能：将大于等于五位数的数字显示为“万”。且不支持语言包。

支持通过`custom_humannumber`函数覆盖默认行为。函数签名如下：
```php
function custom_humannumber(int $n): string
```

#### 参数
- `$n`：数字

#### 返回值
- 友好的数字字符串

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
echo humannumber(20000);
// 结果：2万
```

### `humansize()`

#### 说明
```php
function humansize(int $n): string
```
友好的文件大小显示。

仅支持“KB”、“MB”、“GB”，不支持“TB”及以上。

支持通过`custom_humansize`函数覆盖默认行为。函数签名如下：
```php
function custom_humansize(int $n): string
```

#### 参数
- `$n`：文件大小

#### 返回值
- 友好的文件大小字符串

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
echo humansize(4100000);
// 结果：4.1MB
```

### `param()`

#### 说明
```php
function param(int|string $key, mixed $defval = '', bool $htmlspecialchars = TRUE, bool $addslashes = FALSE): mixed
```
从 `$_REQUEST` 超全局变量中安全地获取请求参数。

这是在Xiuno BBS中获取用户输入内容的**推荐方式**，具有以下优势：

1. **防止XSS攻击**：默认对字符串值应用 `htmlspecialchars` 函数，当用户输入包含恶意脚本时，这些脚本会被转义，从而失去执行能力。
2. **避免未定义变量错误**：当请求中没有特定的参数时，可以指定默认值，避免了因变量未定义而导致的错误。
3. **自动类型转换**：根据提供的默认值自动进行类型转换，例如，如果默认值是整数，那么即使用户提交的是字符串，`param` 函数也会尝试将其转换为整数。
4. **统一获取方式**：从 `$_REQUEST` 超全局变量中获取数据，可以同时处理 GET、POST 和其他 HTTP 请求方法传递的参数，无需思考该用哪个超全局变量。

> **注意**：param函数不适用于处理文件上传，因为文件上传的数据存储在 `$_FILES` 超全局变量中，而 `$_FILES` 并不包含在 `$_REQUEST` 中。对于文件上传，需要直接访问 `$_FILES` 数组来获取上传文件的相关信息。


#### 参数
- `$key` (int|string): 需要获取的请求参数的名称或索引。
  - 当为整数时，对应URL路径中的位置（如 `param(0)` 获取路由的第一部分）
  - 当为字符串时，对应 `$_REQUEST` 中的键名
- `$defval` (mixed): 如果请求中不存在 `$key` 参数，则返回的默认值。默认为空字符串。
  - 该参数同时决定了返回值的类型转换方式
- `$htmlspecialchars` (bool): 是否对字符串值应用 `htmlspecialchars` 函数，防止XSS攻击。默认为 `TRUE`。
- `$addslashes` (bool): 是否对字符串值应用 `addslashes` 函数，防止SQL注入。默认为 `FALSE`。


#### 返回值
- 根据 `$key` 获取到的值，并转换成 `$defval` 参数的数据类型
- 若未指定 `$defval`，则直接返回 `$_REQUEST[$key]` 的值和应有的数据类型
- 如果该值不存在或为空，则返回 `$defval` 定义的默认值


#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 基本用法：获取参数，不存在时返回NULL
$value = param('test');

// 指定默认值：不存在时返回空字符串
$value = param('test', '');

// 强制转换为整型
$age = param('age', 0);

// 强制转换为数组
$hobbies = param('hobbies', array());

// 数组元素强制转换为字符串
$colors = param('colors', array(''));

// 数组元素强制转换为整型
$numbers = param('numbers', array(0));

// 获取URL路径参数
// URL: localhost/a-b-c.htm?some_data=123
$a = param(0);          // "a"
$b = param(1);          // "b"
$c = param(2);          // "c"
$some_data = param('some_data', '');  // "123"

// 禁用htmlspecialchars（用于需要保留HTML内容的场景）
$html_content = param('content', '', false);

// 同时启用addslashes（用于直接拼接到SQL的场景，不推荐）
$raw_value = param('raw', '', true, true);
?>
```

### `lang()`

#### 说明
```php
function lang(string $key, array $arr = array()): string
```
从语言包中获取值。

语言包中的占位符是花括号内写的变量名，例如 `{username}`。

> **推荐使用sprintf函数**搭配lang函数使用，**更灵活（支持任意顺序）**也更健壮。这样语言包内只需要写入纯占位符就好。

#### 参数
-  `$key`：键名
-  `$arr`：替换语言包原文中花括号所指的占位符的数组

#### 返回值
-  翻译字符串

#### 示例
```php
<?php
// ========== Xiuno BBS风格示例 ==========
$lang = array(
'name'=>'名字',
'user_login_successfully'=>'用户 {username} 登陆成功！'
);
include './xiunophp/xiunophp.php';
echo lang('name');
// 结果：名字
echo lang('user_login_successfully', array('username'=>'张三'));
// 结果：用户 张三 登陆成功！

// ========== sprintf风格示例 ==========
$lang = array(
'likes_demo'=>'%1$s 喜欢 %2$s，%1$s 也喜欢 %3$s',
'checkout_demo'=>'价格：%2$.2f 元，数量：%1$d 个'
);
echo sprintf(lang('likes_demo'), '张三', '李四', '王五');
// 结果：张三 喜欢 李四，张三 也喜欢 王五
echo sprintf(lang('checkout_demo'), 99.99, 2);
// 结果：价格：99.99 元，数量：2 个
```

### `url()`

#### 说明
```php
function url(string $url, array $extra = array()): string
```
根据 `$conf['url_rewrite_on']` 指定的格式生成 URL。

#### 参数
- `$url`：URL
- `$extra`：附加参数
  - 用于在 URL 中添加额外的查询参数。
  - 格式为 `array('key'=>'value')`，例如 `array('page'=>2, 'limit'=>10)`。

#### 返回值
- 符合Xiuno BBS URL格式的字符串

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
echo url('user-profile',['username'=>'张三']);
/* 结果：
当 `$conf['url_rewrite_on'] = 0`： ?user-profile.htm&username=张三
当 `$conf['url_rewrite_on'] = 1`： user-profile.htm?username=张三
当 `$conf['url_rewrite_on'] = 2`： ?/user/profile&username=张三
当 `$conf['url_rewrite_on'] = 3`： /user/profile?username=张三
*/
```

### `pagination()`

#### 说明
```php
function pagination($url, $totalnum, $page, $pagesize = 20): string
```
翻页函数，生成翻页的 HTML 代码字符串。

可以通过全局变量`$g_pagination_tpl`来自定义翻页项的模板。

默认模板为Bootstrap风格：
```html
<li class="page-item {active}"><a href="{url}" class="page-link">{text}</a></li>
```
其中：
- `{active}`：是否为当前页，若为当前页则为 `"active"`，否则为空字符串。
- `{url}`：翻页链接的 URL
- `{text}`：翻页链接的文本，通常是页码数字本身，或箭头符号。
均应保持原样，因为会在函数内部进行替换。

#### 参数
- `$url`：翻页链接的 URL，其中包含占位符 `{page}`，用于替换为当前页码。
- `$totalnum`：总页数
- `$page`：当前页码
- `$pagesize`：每页显示的条数，默认为20

#### 返回值
- 翻页的 HTML 代码字符串

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
echo pagination( url("user-list-{page}"), 30, 1, 10);
/* 结果：
<li class="page-item active"><a href="user-list-1.htm" class="page-link">1</a></li>
<li class="page-item "><a href="user-list-2.htm" class="page-link">2</a></li>
<li class="page-item "><a href="user-list-3.htm" class="page-link">3</a></li>
*/
```

### `is_robot()`

#### 说明
```php
function is_robot(): bool
```
判断是否为机器人。

仅为粗暴判断HTTP_USER_AGENT是否包含机器人关键词`bot, spider, slurp`，不保证100%准确。

#### 参数
- 无

#### 返回值
- 是否为机器人，返回值为布尔值

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
echo is_robot();
```

### `http_get()`

#### 说明
```php
function http_get($url, $timeout = 5, $times = 3): string
```
发起HTTP GET请求，并返回内容。

> 建议手动发起CURL请求，而不是使用该函数。使用CURL可以控制请求的每个细节。

#### 参数
- `$url`：URL
- `$timeout`：超时时间，默认5秒
- `$times`：重试次数，默认3次

#### 返回值
- 请求内容

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
echo http_get('https://www.example.com');
// 结果：请求内容HTML代码
```

### `http_post()`

#### 说明
```php
function http_post($url, $post = '', $cookie='', $timeout = 10, $times = 3): string
```
发起HTTP POST请求，并返回内容。

> 建议手动发起CURL请求，而不是使用该函数。使用CURL可以控制请求的每个细节。

#### 参数
- `$url`：URL
- `$post`：POST数据，格式为http_build_query()返回的字符串，例如 `username=张三&password=123456`
- `$cookie`：Cookie，格式为http_build_query()返回的字符串，例如 `sid=123&token=123`
- `$timeout`：超时时间，默认10秒
- `$times`：重试次数，默认3次

#### 返回值
- 请求内容

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
echo http_post('https://www.example.com', 'username=张三&password=123456');
// 结果：请求内容HTML代码
```

### `https_get()`

#### 说明
```php
function https_get($url, $cookie = '', $timeout = 5): string
```
发起HTTPS GET请求，并返回内容。

> 建议手动发起CURL请求，而不是使用该函数。使用CURL可以控制请求的每个细节。

#### 参数
- `$url`：URL
- `$cookie`：Cookie，格式为键值对字符串，例如 `session_id=123456`
- `$timeout`：超时时间，默认5秒
- `$times`：重试次数，默认3次

#### 返回值
- 请求内容

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
echo https_get('https://www.example.com');
// 结果：请求内容HTML代码
```

### `https_post()`

#### 说明
```php
function https_post($url, $post = '', $cookie = '', $timeout=30): string
```
发起HTTPS POST请求，并返回内容。

> 建议手动发起CURL请求，而不是使用该函数。使用CURL可以控制请求的每个细节。

#### 参数
- `$url`：URL
- `$post`：POST数据，格式为http_build_query()返回的字符串，例如 `username=张三&password=123456`
- `$cookie`：Cookie，格式为http_build_query()返回的字符串，例如 `sid=123&token=123`
- `$timeout`：超时时间，默认30秒
- `$times`：重试次数，默认3次

#### 返回值
- 请求内容

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
echo https_post('https://www.example.com', 'username=张三&password=123456');
// 结果：请求内容HTML代码
```

### `http_multi_get()`

#### 说明
```php
function http_multi_get($urls): array
```

多线程的方式通过一个 HTTP GET 获取内容。

一般在命令行下执行，做抓取比较有用。

#### 参数
- `$urls`：URL数组

#### 返回值
- 请求内容数组，每个元素为一个URL的请求内容HTML代码

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
$urlarr = array(
"http://bbs.example.com/thread-1.htm",
"http://bbs.example.com/thread-2.htm",
"http://bbs.example.com/thread-3.htm",
);
echo http_multi_get($urlarr);
/* 结果：
array(
0=>'<!DOCTYPE html>...',
1=>'<!DOCTYPE html>...',
2=>'<!DOCTYPE html>...',
);
*/
```

### `file_replace_var()`

#### 说明
```php
function file_replace_var($filepath, $replace = array(), $pretty = FALSE): bool
```
替换文件内容中的变量内容，支持 PHP、 JSON 格式的文件。

#### 参数
- `$filepath`：文件路径
- `$replace`：替换的内容
- `$pretty`：是否保持优美格式

#### 返回值

#### 示例
文件 1.conf
```php
return array(
'key1'=>'value1',
'key2'=>'value2',
);
```
文件 1.json
```json
{
'key1': "value1",
'key2': "value2",
}
```

开始演示
```php
<?php
include './xiunophp/xiunophp.php';
file_replace_var('1.php', array('key1'=>'aaa'));
file_replace_var('1.json', array('key1'=>'aaa'));

/*
1.php 结果：
return array(
'key1'=>'aaa',
'key2'=>'value2',
);
1.json 结果：
{
'key1': "aaa",
'key2': "value2",
}
*/
```

### `file_get_contents_try()`

#### 说明
```php
function file_get_contents_try($file, $times = 3): string

获取文件的内容。

在并发的环境中容易读取失败，所以请使用本函数，会自动重试。
```

#### 参数
- `$file`：文件路径
- `$times`：重试次数

#### 返回值
- 文件内容

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
$s = file_get_contents_try('1.txt');
echo $s;
```

### `file_put_contents_try()`

#### 说明
```php
function file_put_contents_try($file, $s, $times = 3): bool
```
将内容写入文件。

在并发的环境中会锁定文件，避免写入失败，会自动重试。

#### 参数
- `$file`：文件路径
- `$s`：要写入的内容
- `$times`：重试次数

#### 返回值
- 是否成功写入文件

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
$file_put_contents_try('1.txt', 'hello world');
// 结果：true   
```

### `in_string()`

#### 说明
```php
function in_string($s, $str): bool
```

判断一个字符串是否在另外一个字符串中。
默认分割符为英文半角逗号：`,`

> 【已弃用】在PHP 8.0 及以上版本：使用 `str_contains()` 函数是最佳实践。

#### 参数
- `$s`：要搜索的子字符串
- `$str`：要搜索的字符串

#### 返回值
- 是否包含子字符串

#### 示例
```php
?php
include './xiunophp/xiunophp.php';
$r = in_string('ab', 'ab,abc,abcd');
echo $r;
```

### `file_ext()`

#### 说明
```php
function file_ext($filename, $max = 16): string
```

获取文件的后缀，不包含点，可以设置最大长度。

> 【已弃用】在PHP 8.0 及以上版本：使用 `pathinfo($filename, PATHINFO_EXTENSION)` 函数是最佳实践。

> 【注意】PHP 没有内置逻辑去“智能判断”多重扩展中哪个才是‘真实’的。它只是按最后一个点分割。
> 如果你有特殊需求（例如黑名单检测、安全过滤），建议：
> - 使用 pathinfo() 获取扩展；
> - 再配合白名单或黑名单进行校验；
> - 不要仅依赖前端或用户提供的文件名做安全判断。

> 【注意】再次重申：
> - **永远不要信任用户上传的文件扩展名；**
> - 使用 finfo_open() 检查 MIME 类型；
> - 重命名上传文件（如用哈希值），避免直接使用原始文件名；
> - 将上传目录设置为不可执行（如通过 .htaccess 或 Nginx 配置）。

#### 参数
- `$filename`：文件名
- `$max`：最大长度（为了安全）

#### 返回值
- 文件后缀，不包含点

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
echo file_ext("ILOVEYOU.TXT.VBS");
// 结果：vbs
```

### `file_pre()`

#### 说明
```php
function file_pre($filename, $max = 32): string
```
获取文件名的前缀部分，不包含点，可以设置最大长度。

#### 参数
- `$filename`：文件名
- `$max`：最大长度（为了安全）

#### 返回值
- 文件前缀，不包含点

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
echo file_pre("abc.jpg.php");
// 结果：abc.jpg
```

### `file_name()`

#### 说明
```php
function file_name($path): string
```

从路径中获取文件名

> 【已弃用】请使用`basename()` 函数。

#### 参数
- `$path`：文件路径

#### 返回值
- 文件名，不包含路径部分

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
echo file_name("./view/img/logo.jpg");
// 结果：logo.jpg
```

### `http_url_path()`

#### 说明
```php
function http_url_path(): string
```
获取当前的 URL 路径（不包含文件名和参数）

#### 参数
- 无

#### 返回值
- 当前的 URL 路径（不包含文件名和参数）

#### 示例
```php
<?php
// URL：http://bbs.xiuno.com/admin/forum-list.htm#123
include './xiunophp/xiunophp.php';
echo http_url_path();
// 结果：http://bbs.xiuno.com/admin/
```

### `glob_recursive()`

#### 说明
```php
function glob_recursive($pattern, $flags = 0): array
```
递归搜索目录，返回所有匹配的文件路径。

#### 参数
- `$pattern`：匹配表达式，支持通配符，与 `glob()` 参数一致
- `$flags`：标志，与 `glob()` 参数一致

#### 返回值
- 匹配的文件路径数组，每个元素为一个文件路径

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
glob_recursive('./*');
// 结果：
array(
'./index.php',
'./admin'
'./admin/index.php'
...
);
```

### `rmdir_recusive()`

#### 说明
```php
function rmdir_recusive($dir, $keepdir = 0): bool
```

`rmdir()` 的递归增强版本，支持无限级子目录删除。用于弥补 `rmdir()` 只能删除空目录的问题。

#### 参数
- `$dir`：要删除的目录路径
- `$keepdir`：是否保留目录本身目录，默认不保留

#### 返回值
- 是否成功删除目录或子目录  

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
$r = rmdir_recusive("./tmp/", 1);
```

### `copy_recusive()`

#### 说明
```php
function copy_recusive($src, $dst): bool
```
`copy()` 的递归增强版本，支持无限级子目录复制。

#### 参数
- `$src`：源目录路径
- `$dst`：目标目录路径

#### 返回值
- 是否成功复制目录或子目录  

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
copy_recusive('./plugin/xn_ad/', './plugin/xn_ad_backup/');
```

### `_GET()` `_POST()` `_COOKIE()` `_REQUEST()` `_ENV()` `_SERVER()` `GLOBALS()` `G()` `_SESSION()`

#### 说明
```php
function _GET(string $k): mixed|null
function _POST(string $k): mixed|null
function _COOKIE(string $k): string|null
function _REQUEST(string $k): string|null
function _ENV(string $k): string|null
function _SERVER(string $k): string|null
function GLOBALS(string $k): string|null
function G(string $k): string|null
function _SESSION(string $k, $v = FALSE): mixed|null
```

无 Notice 方式的获取超级全局变量中的键值。

#### 参数
- `$k`：键名
- `$v`：默认值，默认 FALSE

#### 返回值
- 键值，或默认值

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
$uid = _SESSION('uid');
$sid = _COOKIE('sid');
```

## 自定义全局变量
请自行定义出来。

### `$IS_HTMX`

#### 说明
```php
bool $IS_HTMX = isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'] === 'true';
```
是否为 HTMX 请求。