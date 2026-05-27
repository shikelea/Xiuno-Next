# 在Xiuno BBS中新定义的全局变量与函数

## 表单函数

**特别注意**

- 这些函数被硬编码为输出 Bootstrap 4 风格的 HTML 表单代码，灵活性较低。
- 推荐直接编写 HTML 代码，然后在 `value` 属性中使用 `<?= $value; ?>` 输出变量值。
- **务必在后端再次校验用户输入的内容，防止注入攻击**

### `form_radio_yes_no()`

#### 说明
```php
function form_radio_yes_no(string $name, int $checked = 0): string
```
生成"是/否"单选按钮组，返回 Bootstrap 4 风格的 HTML 代码。

#### 参数
- `$name`：单选按钮的 name 属性值
- `$checked`：默认选中值，1 表示"是"，0 表示"否"，默认为 0

#### 返回值
- 返回包含两个单选按钮的 HTML 字符串

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
include './model/form.func.php';

// 生成"是/否"单选按钮
echo form_radio_yes_no('is_admin', 0);
// 输出: <label class="custom-input custom-radio"><input type="radio" name="is_admin" value="1" /> 是</label>
//       <label class="custom-input custom-radio"><input type="radio" name="is_admin" value="0" checked="checked" /> 否</label>

// 默认选中"是"
echo form_radio_yes_no('enable_cache', 1);
```

### `form_radio()`

#### 说明
```php
function form_radio(string $name, array $arr, int $checked = 0): string
```
生成单选按钮组，返回 Bootstrap 4 风格的 HTML 代码。

#### 参数
- `$name`：单选按钮的 name 属性值
- `$arr`：选项数组，键为选项值，值为显示文本
- `$checked`：默认选中值，默认为 0

#### 返回值
- 返回包含单选按钮组的 HTML 字符串

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
include './model/form.func.php';

// 生成状态选择单选按钮
$statusOptions = [
    0 => '禁用',
    1 => '启用',
    2 => '审核中'
];
echo form_radio('status', $statusOptions, 1);

// 生成性别选择
$genderOptions = [
    'male' => '男',
    'female' => '女'
];
echo form_radio('gender', $genderOptions, 'male');

// 如果 $arr 为空，默认生成"否/是"选项
echo form_radio('agree', [], 1);
```

### `form_checkbox()`

#### 说明
```php
function form_checkbox(string $name, int $checked = 0, string $txt = '', mixed $val = 1): string
```
生成单个复选框，返回 Bootstrap 4 风格的 HTML 代码。

#### 参数
- `$name`：复选框的 name 属性值
- `$checked`：是否选中，1 表示选中，0 表示未选中，默认为 0
- `$txt`：复选框旁边的文本说明，默认为空
- `$val`：复选框的 value 属性值，默认为 1

#### 返回值
- 返回包含复选框的 HTML 字符串

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
include './model/form.func.php';

// 生成"记住我"复选框
echo form_checkbox('remember', 1, '记住我');

// 生成未选中的复选框
echo form_checkbox('subscribe', 0, '订阅邮件通知');

// 自定义 value 值
echo form_checkbox('agree', 1, '我同意用户协议', 'yes');
```

### `form_multi_checkbox()`

#### 说明
```php
function form_multi_checkbox(string $name, array $arr, array $checked = []): string
```
生成多个复选框，返回 Bootstrap 4 风格的 HTML 代码。

> **注意**：此函数的输出可能不符合预期，建议直接编写 HTML 代码。

#### 参数
- `$name`：复选框的 name 属性值（通常以 `[]` 结尾，以便 PHP 接收为数组）
- `$arr`：选项数组，键为选项值，值为显示文本
- `$checked`：默认选中的值数组，默认为空数组

#### 返回值
- 返回包含多个复选框的 HTML 字符串

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
include './model/form.func.php';

// 生成兴趣爱好多选框
$hobbies = [
    'reading' => '阅读',
    'music' => '音乐',
    'sports' => '运动',
    'travel' => '旅行'
];
$selected = ['reading', 'music'];
echo form_multi_checkbox('hobbies[]', $hobbies, $selected);

// 生成权限选择
$permissions = [
    'read' => '读取',
    'write' => '写入',
    'delete' => '删除'
];
$userPerms = ['read', 'write'];
echo form_multi_checkbox('perms[]', $permissions, $userPerms);
```

### `form_select()`

#### 说明
```php
function form_select(string $name, array $arr, int $checked = 0, mixed $id = TRUE): string
```
生成下拉选择框，返回 Bootstrap 4 风格的 HTML 代码。

#### 参数
- `$name`：下拉框的 name 属性值
- `$arr`：选项数组，键为选项值，值为显示文本
- `$checked`：默认选中值，默认为 0
- `$id`：是否生成 id 属性
  - `TRUE`：生成与 name 相同的 id 属性（默认）
  - 字符串：使用该字符串作为 id 属性值
  - `FALSE`：不生成 id 属性

#### 返回值
- 返回包含下拉选择框的 HTML 字符串
- 如果 `$arr` 为空，返回空字符串

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
include './model/form.func.php';

// 生成分类选择下拉框
$categories = [
    1 => '技术文章',
    2 => '生活随笔',
    3 => '项目分享'
];
echo form_select('cate_id', $categories, 1);

// 不生成 id 属性
echo form_select('status', [0=>'禁用', 1=>'启用'], 1, FALSE);

// 自定义 id 属性
echo form_select('type', ['a'=>'类型A', 'b'=>'类型B'], 'a', 'my_select_id');
```

### `form_options()`

#### 说明
```php
function form_options(array $arr, int $checked = 0): string
```
生成下拉选择框的 `<option>` 选项列表，通常配合 `<select>` 标签使用。它被`form_select()`函数使用。

#### 参数
- `$arr`：选项数组，键为选项值，值为显示文本
- `$checked`：默认选中值，默认为 0

#### 返回值
- 返回包含 `<option>` 标签的 HTML 字符串

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
include './model/form.func.php';

// 手动构建下拉框
$options = [
    1 => '选项一',
    2 => '选项二',
    3 => '选项三'
];
echo '<select name="myselect" class="custom-select">';
echo form_options($options, 2);
echo '</select>';

// 配合其他框架使用
echo '<select name="status" id="status">';
echo form_options([0=>'草稿', 1=>'发布', 2=>'归档'], 1);
echo '</select>';
```

### `form_text()`

#### 说明
```php
function form_text(string $name, string $value, mixed $width = FALSE, string $holdplacer = ''): string
```
生成单行文本输入框，返回 Bootstrap 4 风格的 HTML 代码。

#### 参数
- `$name`：输入框的 name 和 id 属性值
- `$value`：输入框的默认值
- `$width`：输入框宽度
  - `FALSE`：不设置宽度（默认）
  - 数字：自动添加 `px` 单位
  - 字符串：直接作为 CSS 宽度值
- `$holdplacer`：占位符文本（placeholder），默认为空

#### 返回值
- 返回包含文本输入框的 HTML 字符串

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
include './model/form.func.php';

// 基本文本输入框
echo form_text('username', '');

// 带默认值和占位符
echo form_text('email', 'user@example.com', FALSE, '请输入邮箱');

// 设置宽度为 300px
echo form_text('title', '', 300, '请输入标题');

// 使用 CSS 单位设置宽度
echo form_text('keyword', '', '50%', '搜索关键词');
```

### `form_hidden()`

#### 说明
```php
function form_hidden(string $name, string $value): string
```
生成隐藏字段，用于在表单中传递不需要用户看到的值。

#### 参数
- `$name`：隐藏字段的 name 和 id 属性值
- `$value`：隐藏字段的值

#### 返回值
- 返回包含隐藏字段的 HTML 字符串

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
include './model/form.func.php';

// 生成隐藏字段
echo form_hidden('user_id', '12345');
echo form_hidden('action', 'update');

// 在表单中使用
echo '<form method="post">';
echo form_hidden('id', $row['id']);
echo form_text('title', $row['title']);
echo '<button type="submit">提交</button>';
echo '</form>';
```

### `form_textarea()`

#### 说明
```php
function form_textarea(string $name, string $value, mixed $width = FALSE, mixed $height = FALSE): string
```
生成多行文本输入框，返回 Bootstrap 4 风格的 HTML 代码。

#### 参数
- `$name`：文本框的 name 和 id 属性值
- `$value`：文本框的默认内容
- `$width`：文本框宽度
  - `FALSE`：不设置宽度（默认）
  - 数字：自动添加 `px` 单位
  - 字符串：直接作为 CSS 宽度值
- `$height`：文本框高度
  - `FALSE`：不设置高度（默认）
  - 数字：自动添加 `px` 单位
  - 字符串：直接作为 CSS 高度值

#### 返回值
- 返回包含文本框的 HTML 字符串

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
include './model/form.func.php';

// 基本文本框
echo form_textarea('content', '');

// 带默认内容
echo form_textarea('description', '这是默认内容');

// 设置宽度和高度
echo form_textarea('content', '', 500, 200);

// 使用 CSS 单位
echo form_textarea('summary', '', '100%', '150px');
```

### `form_password()`

#### 说明
```php
function form_password(string $name, string $value, mixed $width = FALSE): string
```
生成密码输入框，返回 Bootstrap 4 风格的 HTML 代码。

#### 参数
- `$name`：密码框的 name 和 id 属性值
- `$value`：密码框的默认值（通常为空）
- `$width`：密码框宽度
  - `FALSE`：不设置宽度（默认）
  - 数字：自动添加 `px` 单位
  - 字符串：直接作为 CSS 宽度值

#### 返回值
- 返回包含密码输入框的 HTML 字符串

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
include './model/form.func.php';

// 基本密码输入框
echo form_password('password', '');

// 设置宽度
echo form_password('password', '', 300);

// 登录表单示例
echo '<form method="post">';
echo form_text('username', '');
echo form_password('password', '');
echo '<button type="submit">登录</button>';
echo '</form>';
```

### `form_time()`

#### 说明
```php
function form_time(string $name, string $value, mixed $width = FALSE): string
```
生成时间输入框，返回 Bootstrap 4 风格的 HTML 代码。

**注意**：此函数仅生成普通的文本输入框，不包含时间选择器功能。高度建议使用`<input type="datetime-local">`。

#### 参数
- `$name`：输入框的 name 和 id 属性值
- `$value`：输入框的默认值（时间字符串）
- `$width`：输入框宽度
  - `FALSE`：不设置宽度（默认）
  - 数字：自动添加 `px` 单位
  - 字符串：直接作为 CSS 宽度值

#### 返回值
- 返回包含文本输入框的 HTML 字符串

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
include './model/form.func.php';

// 基本时间输入框
echo form_time('create_time', '');

// 带默认时间值
echo form_time('publish_time', date('Y-m-d H:i:s'));

// 设置宽度
echo form_time('event_time', '', 200);

// 配合前端时间选择器使用
echo '<link rel="stylesheet" href="datepicker.css">';
echo form_time('start_time', '', 200);
echo '<script src="datepicker.js"></script>';
echo '<script>$("#start_time").datepicker();</script>';
```

## 图片函数

### `image_ext()`

#### 说明
```php
function image_ext(string $filename): string
```
获取文件扩展名(不包含点号),返回小写的扩展名。

> 【推荐】可以使用 PHP 内置的 `pathinfo($filename, PATHINFO_EXTENSION)` 获取扩展名

#### 参数
- `$filename`:文件名或文件路径

#### 返回值
- 返回小写的文件扩展名(不包含点号)
- 如果文件名中没有扩展名,返回空字符串

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 获取图片扩展名
$ext = image_ext('photo.jpg');      // 返回: "jpg"
$ext = image_ext('avatar.PNG');     // 返回: "png"
$ext = image_ext('/path/to/image.GIF'); // 返回: "gif"

// 检查是否为允许的图片格式
$allowed = ['jpg', 'jpeg', 'png', 'gif'];
if (in_array(image_ext($_FILES['file']['name']), $allowed)) {
    echo "允许上传";
}
```

### `image_safe_name`

#### 说明
```php
function image_safe_name(string $filename, string $dir): string
```
获取安全的文件名,如果文件存在,则加时间戳和随机数,避免重复。

此函数会将文件名中的特殊字符替换为下划线,只保留最后一个点号作为扩展名分隔符。

#### 参数
- `$filename`:原始文件名
- `$dir`:目标目录路径,用于检查文件是否已存在

#### 返回值
- 返回安全的文件名
- 如果文件已存在,返回带时间戳和随机数的文件名

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 获取安全文件名
$safeName = image_safe_name('user avatar.jpg', './upload/');
// 返回: "user_avatar.jpg" 或 "user_avatar1234567890_123.jpg"(如果文件已存在)

// 处理特殊字符
$safeName = image_safe_name('photo@2024.png', './upload/');
// 返回: "photo_2024.png"

// 用于文件上传
$uploadDir = './upload/avatar/';
$safeName = image_safe_name($_FILES['avatar']['name'], $uploadDir);
move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadDir . $safeName);
```


### `image_thumb_name`

#### 说明
```php
function image_thumb_name(string $filename): string
```
生成缩略图的文件名,在原文件名(不含扩展名)后添加 `_thumb` 后缀。

#### 参数
- `$filename`:原始文件名

#### 返回值
- 返回缩略图文件名,格式为 `原文件名_thumb.扩展名`

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 生成缩略图文件名
$thumbName = image_thumb_name('avatar.jpg');
// 返回: "avatar_thumb.jpg"

$thumbName = image_thumb_name('photo.png');
// 返回: "photo_thumb.png"

// 配合 image_thumb() 使用
$sourceFile = './upload/avatar.jpg';
$thumbFile = './upload/' . image_thumb_name('avatar.jpg');
$result = image_thumb($sourceFile, $thumbFile, 100, 100);
```


### `image_rand_name`

#### 说明
```php
function image_rand_name(int $k): string
```
生成随机文件名,格式为 `时间戳_随机数_k`。

#### 参数
- `$k`:附加的标识数字,通常用于区分不同用途的文件

#### 返回值
- 返回随机文件名,格式为 `时间戳_10位随机数_k`

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 生成随机文件名
$randName = image_rand_name(1);
// 返回类似: "1234567890_1234567890_1"

// 用于上传文件
$ext = image_ext($_FILES['file']['name']);
$randName = image_rand_name(1) . '.' . $ext;
// 返回: "1234567890_1234567890_1.jpg"

// 推荐的现代写法
$randName = bin2hex(random_bytes(16)) . '.' . $ext;
// 返回: "a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6.jpg"
```


### `image_set_dir`

#### 说明
```php
function image_set_dir(int $id, string $dir): string
```
根据 ID 创建分级目录结构,用于分散存储大量文件,避免单个目录下文件过多。

目录格式为 `000/000/` 到 `999/999/`,支持最多十亿个文件(每个子目录最多 1000 个文件)。

#### 参数
- `$id`:文件/数据的 ID,会被格式化为 9 位数字
- `$dir`:基础目录路径

#### 返回值
- 返回创建的相对路径,如 `000/001`

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 创建分级目录
$subdir = image_set_dir(123, './upload');
// 创建目录: ./upload/000/000/
// 返回: "000/000"

$subdir = image_set_dir(1000, './upload');
// 创建目录: ./upload/000/001/
// 返回: "000/001"

// 保存文件到分级目录
$id = 12345;
$subdir = image_set_dir($id, './upload/avatar');
$filepath = './upload/avatar/' . $subdir . '/' . $id . '.jpg';
move_uploaded_file($_FILES['avatar']['tmp_name'], $filepath);
```


### `image_get_dir`

#### 说明
```php
function image_get_dir(int $id): string
```
根据 ID 获取分级目录路径(不创建目录)。

此函数与 `image_set_dir()` 功能相似,但不会创建目录,仅返回路径。用于访问已存储的文件。

#### 参数
- `$id`:文件/数据的 ID,会被格式化为 9 位数字

#### 返回值
- 返回相对路径,如 `000/001`

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 获取分级目录路径
$path = image_get_dir(123);
// 返回: "000/000"

$path = image_get_dir(1000);
// 返回: "000/001"

// 获取已存储文件的路径
$id = 12345;
$subdir = image_get_dir($id);
$filepath = './upload/avatar/' . $subdir . '/' . $id . '.jpg';

if (file_exists($filepath)) {
    // 文件存在,可以读取或删除
    $imageData = file_get_contents($filepath);
}

// 删除文件
$filepath = './upload/' . image_get_dir($id) . '/' . $id . '.jpg';
if (is_file($filepath)) {
    unlink($filepath);
}
```


### `image_thumb`

#### 说明
```php
function image_thumb(string $sourcefile, string $destfile, int $forcedwidth = 80, int $forcedheight = 80): array
```
生成图片缩略图,按比例缩放图片到指定尺寸范围内。

此函数会保持图片的宽高比例,将图片缩放到不超过指定的宽度和高度。支持 JPEG、GIF、PNG、BMP 格式。

#### 参数
- `$sourcefile`:源图片路径
- `$destfile`:目标图片路径
- `$forcedwidth`:最大宽度,默认为 80
- `$forcedheight`:最大高度,默认为 80

#### 返回值
- 返回关联数组:
  - `filesize`:缩略图文件大小(字节)
  - `width`:缩略图宽度
  - `height`:缩略图高度
- 如果失败,返回 `['filesize'=>0, 'width'=>0, 'height'=>0]`

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 生成缩略图
$result = image_thumb('./upload/avatar.jpg', './upload/avatar_thumb.jpg', 100, 100);
// 返回: ['filesize'=>12345, 'width'=>100, 'height'=>75]

// 检查是否成功
if ($result['filesize'] > 0) {
    echo "缩略图生成成功";
    echo "尺寸: {$result['width']} x {$result['height']}";
    echo "大小: " . round($result['filesize'] / 1024, 2) . " KB";
}

// 生成不同尺寸的缩略图
image_thumb('./upload/photo.jpg', './upload/photo_small.jpg', 80, 80);
image_thumb('./upload/photo.jpg', './upload/photo_medium.jpg', 200, 200);
image_thumb('./upload/photo.jpg', './upload/photo_large.jpg', 400, 400);
```


### `image_clip`

#### 说明
```php
function image_clip(string $sourcefile, string $destfile, int $clipx, int $clipy, int $clipwidth, int $clipheight): int
```
图片裁切,从源图片的指定位置裁切指定尺寸的区域。

此函数用于从图片中裁切出特定区域,常用于头像裁切、图片编辑等场景。

#### 参数
- `$sourcefile`:源图片路径
- `$destfile`:裁切后保存的目标路径
- `$clipx`:裁切起始点的 X 坐标(左上角)
- `$clipy`:裁切起始点的 Y 坐标(左上角)
- `$clipwidth`:裁切区域的宽度
- `$clipheight`:裁切区域的高度

#### 返回值
- 返回裁切后图片的文件大小(字节)
- 如果失败,返回 0

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 从图片中心裁切 100x100 的区域
$sourceFile = './upload/photo.jpg';
$imgInfo = getimagesize($sourceFile);
$centerX = ($imgInfo[0] - 100) / 2;
$centerY = ($imgInfo[1] - 100) / 2;

$filesize = image_clip($sourceFile, './upload/avatar.jpg', $centerX, $centerY, 100, 100);

if ($filesize > 0) {
    echo "裁切成功,文件大小: " . round($filesize / 1024, 2) . " KB";
}

// 从左上角裁切
image_clip('./upload/photo.jpg', './upload/cropped.jpg', 0, 0, 200, 150);

// 用户头像裁切(从前端获取裁切坐标)
$clipX = intval($_POST['x']);
$clipY = intval($_POST['y']);
$clipWidth = intval($_POST['width']);
$clipHeight = intval($_POST['height']);
image_clip('./upload/temp.jpg', './upload/avatar.jpg', $clipX, $clipY, $clipWidth, $clipHeight);
```


### `image_clip_thumb`

#### 说明
```php
function image_clip_thumb(string $sourcefile, string $destfile, int $forcedwidth = 80, int $forcedheight = 80): int
```
先裁切后缩略,生成固定尺寸的缩略图。

此函数会先从图片中心裁切出符合目标宽高比的区域,然后再缩放到指定尺寸。与 `image_thumb()` 不同,此函数会生成精确的指定尺寸,不保持原宽高比。

#### 参数
- `$sourcefile`:源图片路径
- `$destfile`:目标图片路径
- `$forcedwidth`:目标宽度,默认为 80
- `$forcedheight`:目标高度,默认为 80

#### 返回值
- 返回缩略图文件大小(字节)
- 如果失败,返回 0

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 生成固定尺寸的头像缩略图
$filesize = image_clip_thumb('./upload/photo.jpg', './upload/avatar_thumb.jpg', 100, 100);

if ($filesize > 0) {
    echo "缩略图生成成功,大小: " . round($filesize / 1024, 2) . " KB";
}

// 生成不同尺寸的固定缩略图
image_clip_thumb('./upload/photo.jpg', './upload/thumb_small.jpg', 50, 50);
image_clip_thumb('./upload/photo.jpg', './upload/thumb_medium.jpg', 150, 150);
image_clip_thumb('./upload/photo.jpg', './upload/thumb_large.jpg', 300, 300);

// 用于用户头像上传
$sourceFile = $_FILES['avatar']['tmp_name'];
$destFile = './upload/avatar/' . $uid . '_thumb.jpg';
image_clip_thumb($sourceFile, $destFile, 200, 200);
```

### `image_safe_thumb`

#### 说明
```php
function image_safe_thumb(string $sourcefile, int $id, string $ext, string $dir1, int $forcedwidth, int $forcedheight, int $randomname = 0): array
```
安全缩略图生成函数,自动创建分级目录并生成缩略图。

这是一个综合性的图片处理函数,集成了目录创建、文件命名、缩略图生成等功能。适用于用户头像、附件等需要按 ID 分级存储的场景。

#### 参数
- `$sourcefile`:源图片路径
- `$id`:文件 ID,用于生成分级目录和文件名
- `$ext`:文件扩展名(包含点号,如 `.jpg`)
- `$dir1`:基础目录路径
- `$forcedwidth`:缩略图最大宽度
- `$forcedheight`:缩略图最大高度
- `$randomname`:是否使用随机文件名,默认为 0(使用 ID 作为文件名)

#### 返回值
- 返回关联数组:
  - `filesize`:缩略图文件大小(字节)
  - `width`:缩略图宽度
  - `height`:缩略图高度
  - `fileurl`:相对文件 URL,如 `000/001/123.jpg`

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 用户上传头像
$uid = 12345;
$sourceFile = $_FILES['avatar']['tmp_name'];
$ext = '.' . image_ext($_FILES['avatar']['name']);

$result = image_safe_thumb($sourceFile, $uid, $ext, './upload/avatar/', 200, 200);

if ($result['filesize'] > 0) {
    echo "头像上传成功";
    echo "文件路径: " . $result['fileurl'];
    echo "尺寸: {$result['width']} x {$result['height']}";
    echo "大小: " . round($result['filesize'] / 1024, 2) . " KB";
}

// 使用随机文件名
$result = image_safe_thumb($sourceFile, $uid, $ext, './upload/photo/', 800, 600, 1);
// 文件名类似: 000/001/a1b2c3d4e5f6.jpg

// 附件上传
$aid = 67890;
$result = image_safe_thumb($_FILES['attachment']['tmp_name'], $aid, '.png', './upload/attach/', 1024, 768);
```


## 拼音函数

### `pinyin()`

#### 说明
```php
function pinyin($s, $sep = ' '): string
```
最全的PHP汉字转拼音函数 （共25961字，包括 20902基本字 + 5059生僻字）

> 作者：wuzhaohuan <kongphp@gmail.com> <blog.520.at>

#### 参数
- `s`：待转换的字符串
- `sep`：分隔符，默认为空格

#### 返回值
- 将汉字转换成拼音后的字符串

#### 示例
```php
var_dump(pinyin('最全的PHP汉字转拼音库，比百度词典还全（dict.baidu.com）'));
```

## 杂项函数

### `xn_log_post_data()`

#### 说明
```php
function xn_log_post_data(): void
```
将POST数据记录到日志文件中。

不会记录键为`password`、`password_new`、`password_old`的POST数据。
#### 参数
- 无
#### 返回值
- 无
#### 示例
```php
<?php
include './xiunophp/xiunophp.php';
xn_log_post_data();
```
### `error_handle()`

#### 说明
```php
function error_handle(int $errno, string $errstr, string $errfile, int $errline): bool
```
为xiuno bbs设计的全局错误处理函数。

#### 参数
- `$errno`：错误号
- `$errstr`：错误信息
- `$errfile`：错误文件名
- `$errline`：错误行号

#### 返回值
- 无

#### 示例
```php
```
### `param_word()`

#### 说明
```php
function param_word(string $key, int $len = 32): string
```
安全获取单词类参数，仅保留 `[a-zA-Z0-9_]` 字符。

此函数用于过滤用户输入中的特殊字符，适合用于获取用户名、标识符等场景。

#### 参数
- `$key`：`$_REQUEST` 中的键名
- `$len`：最大长度，默认为 32

#### 返回值
- 返回过滤后的安全字符串，仅包含字母、数字和下划线

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 假设 URL 为: ?username=admin123&tag=test_tag
$username = param_word('username');  // 返回 "admin123"
$tag = param_word('tag', 10);        // 返回 "test_tag"，最大10字符

// 如果输入包含特殊字符，会被过滤
// URL: ?name=user@#$%name
$name = param_word('name');          // 返回 "username"
```
### `param_base64()`

#### 说明
```php
function param_base64(string $key, int $len = 0): string
```
获取并解码 Base64 编码的参数数据，支持 Data URL 格式（如 `data:image/png;base64,xxxxx`）。

此函数常用于处理前端上传的 Base64 图片数据。

> 【推荐】若要上传文件，请务必使用enctype="multipart/form-data"而不是enctype="application/x-www-form-urlencoded"然后把数据通过base64编码，这样会导致你多传输了三分之一的数据量，得不偿失啊。

#### 参数
- `$key`：`$_REQUEST` 中的键名
- `$len`：返回数据的最大长度，默认为 0（不限制）

#### 返回值
- 返回解码后的原始二进制数据字符串
- 如果参数为空，返回空字符串

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 假设前端通过 AJAX 发送 Base64 图片数据
// POST: image=data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEA...
$imageData = param_base64('image');

// 限制数据长度为 1MB
$imageData = param_base64('image', 1048576);

// 保存为文件
if ($imageData) {
    file_put_contents('./upload/image.png', $imageData);
}
```
### `param_json()`

#### 说明
```php
function param_json(string $key): array|string
```
获取并解码 JSON 格式的参数数据。

> 【推荐】使用json_decode(param($key,''),true)而不是使用本函数来确保输入内容是字符串再进行解码。

#### 参数
- `$key`：`$_REQUEST` 中的键名

#### 返回值
- 返回解码后的数组或字符串
- 如果参数为空或解码失败，返回空字符串

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 假设 URL 为: ?data={"name":"John","age":30}
$data = param_json('data');
// 返回: ['name' => 'John', 'age' => 30]

if (is_array($data)) {
    echo $data['name'];  // 输出: John
    echo $data['age'];   // 输出: 30
}
```
### `param_url()`

#### 说明
```php
function param_url(string $key): string
```
获取并解码 URL 编码的参数数据。使用 XiunoPHP 自定义的 `xn_urldecode()` 函数进行解码。

> 【推荐】此函数用于处理 XiunoPHP 特殊编码格式的 URL 数据。对于标准 URL 编码，建议直接使用 PHP 内置的 `urldecode()` 函数。

#### 参数
- `$key`：`$_REQUEST` 中的键名

#### 返回值
- 返回解码后的字符串

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 假设 URL 为: ?redirect=http_3a_2f_2fexample_2ecom_2fpath
// XiunoPHP 使用 _ 替代 % 的特殊编码格式
$url = param_url('redirect');
// 返回解码后的 URL
```
### `xn_safe_word()`

#### 说明
```php
function xn_safe_word(string $s, int $len): string
```
安全过滤字符串，仅保留 `[a-zA-Z0-9_]` 字符（即 `\w` 匹配的字符）。

> 你可以直接使用 `preg_replace('/\W+/', '', $s)` 或 `preg_replace('/[^a-zA-Z0-9_]/', '', $s)` 实现相同效果。

#### 参数
- `$s`：待过滤的字符串
- `$len`：返回字符串的最大长度

#### 返回值
- 返回过滤后的安全字符串

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 过滤特殊字符
$safe = xn_safe_word('user@#$%name_123', 20);
// 返回: "username_123"

// 限制长度
$safe = xn_safe_word('very_long_username_here', 10);
// 返回: "very_long_" (前10个字符)

// 用于生成安全的文件名或标识符
$filename = xn_safe_word($_POST['filename'], 50);
```
### `param_force()`

#### 说明
```php
function param_force(mixed $val, mixed $defval, bool $htmlspecialchars = TRUE, bool $addslashes = FALSE): mixed
```
强制类型转换和安全处理参数值。支持字符串和一维数组的处理。这是param函数的内部实现逻辑，被抽离成了独立函数。

#### 参数
- `$val`：待处理的值
- `$defval`：默认值，用于确定返回类型。如果是数组，则处理数组类型
- `$htmlspecialchars`：是否进行 HTML 实体转义，默认为 `TRUE`
- `$addslashes`：是否添加斜杠转义，默认为 `FALSE`

#### 返回值
- 根据 `$defval` 的类型返回相应类型的值
- 如果 `$defval` 是字符串，返回处理后的字符串
- 如果 `$defval` 是整数，返回整数值
- 如果 `$defval` 是数组，返回处理后的数组

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 字符串类型处理
$val = param_force($_POST['name'], '', true, false);
// 返回 HTML 转义后的字符串

// 整数类型处理
$id = param_force($_POST['id'], 0);
// 返回整数值

// 数组类型处理
$ids = param_force($_POST['ids'], array(0));
// 返回整数数组

// 不进行 HTML 转义
$content = param_force($_POST['content'], '', false);
```
### `jump()`

#### 说明
```php
function jump(string $message, string $url = '', int $delay = 3): string
```
生成跳转链接和自动跳转脚本。用于`message`函数的“页面提示后自动跳转”。

> 如果不需要这么花哨的话，也可以使用 HTTP 重定向 `header('Location: url')`。

#### 参数
- `$message`：显示的提示消息
- `$url`：跳转目标 URL，如果为 `back` 则返回上一页；该参数不可被字符串拼接攻击手段绕过（已经测试，无法用该参数实现刷新页面自身）
- `$delay`：延迟跳转时间（秒），默认为 3 秒

#### 返回值
- 返回包含链接和自动跳转脚本的 HTML 字符串
- 如果是 AJAX 请求，仅返回消息文本

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 3秒后跳转到首页
echo jump('操作成功，正在跳转...', './', 3);

// 返回上一页
echo jump('操作失败，点击返回', 'back');

// 自定义跳转
echo jump('登录成功', 'user-profile.htm', 2);
```
### `xn_json_number_array_to_string()`

#### 说明
```php
function xn_json_number_array_to_string(array $arr): string
```
将数字索引数组转换为 JSON 字符串。

> 【已弃用】此函数在源码中已被注释掉，不再使用。请直接使用 PHP 内置的 `json_encode()` 函数。

#### 参数
- `$arr`：数字索引数组

#### 返回值
- 返回 JSON 格式的字符串

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 推荐使用 json_encode() 替代
$arr = [1, 2, 3, 4, 5];
$json = json_encode($arr);  // 返回: "[1,2,3,4,5]"
```
### `xn_json_assoc_array_to_string()`

#### 说明
```php
function xn_json_assoc_array_to_string(array $arr): string
```
将关联数组转换为 JSON 字符串。

> 【已弃用】此函数在源码中已被注释掉，不再使用。请直接使用 PHP 内置的 `json_encode()` 函数。

#### 参数
- `$arr`：关联数组

#### 返回值
- 返回 JSON 格式的字符串

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 推荐使用 json_encode() 替代
$arr = ['name' => 'John', 'age' => 30];
$json = json_encode($arr);  // 返回: '{"name":"John","age":30}'
```
### `is_number_array()`

#### 说明
```php
function is_number_array(array $arr): bool
```
判断数组是否为数字索引数组（从 0 开始连续递增）。

> 【已弃用】此函数在源码中已被注释掉。在 PHP 8 中，可以使用 `array_is_list()` 函数（PHP 8.1+）或 `array_keys($arr) === range(0, count($arr) - 1)` 实现。

#### 参数
- `$arr`：待检测的数组

#### 返回值
- 如果是数字索引数组返回 `TRUE`，否则返回 `FALSE`

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// PHP 8.1+ 推荐使用
$arr1 = [1, 2, 3, 4];
$isList = array_is_list($arr1);  // true

$arr2 = ['name' => 'John', 'age' => 30];
$isList = array_is_list($arr2);  // false

// 兼容写法
function is_number_array($arr) {
    return array_keys($arr) === range(0, count($arr) - 1);
}
```
### `ucs2_to_utf8()`

#### 说明
```php
function ucs2_to_utf8(string $s): string
```
将 UCS-2 编码的字符串转换为 UTF-8 编码。

> 【已弃用】此函数在源码中已被注释掉。在 PHP 8 中，请使用 `iconv('UCS-2', 'UTF-8', $s)` 或 `mb_convert_encoding($s, 'UTF-8', 'UCS-2')` 实现。

#### 参数
- `$s`：UCS-2 编码的字符串

#### 返回值
- 返回 UTF-8 编码的字符串

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 推荐使用 iconv 或 mb_convert_encoding
$ucs2String = "\x4E\x2D\x65\x87";  // UCS-2 编码的"中文"
$utf8String = iconv('UCS-2', 'UTF-8', $ucs2String);

// 或使用 mbstring
$utf8String = mb_convert_encoding($ucs2String, 'UTF-8', 'UCS-2');
```
### `pagination()`

#### 说明
```php
function pagination(string $url, int $totalnum, int $page, int $pagesize = 20): string
```
生成 Bootstrap 风格的分页 HTML 代码。

#### 参数
- `$url`：分页链接模板，使用 `{page}` 作为页码占位符
- `$totalnum`：总记录数
- `$page`：当前页码
- `$pagesize`：每页显示数量，默认为 20

#### 返回值
- 返回 Bootstrap 风格的分页 HTML 字符串
- 如果总页数小于 2，返回空字符串

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 生成分页
$total = 150;  // 总记录数
$page = 3;     // 当前页
$url = 'thread-list-{page}.htm';

$html = pagination($url, $total, $page, 20);
echo '<ul class="pagination">' . $html . '</ul>';

// 输出示例:
// <li class="page-item"><a href="thread-list-2.htm" class="page-link">◀</a></li>
// <li class="page-item"><a href="thread-list-1.htm" class="page-link">1</a></li>
// <li class="page-item active"><a href="thread-list-3.htm" class="page-link">3</a></li>
// ...
```
### `pager()`

#### 说明
```php
function pager(string $url, int $totalnum, int $page, int $pagesize = 20): string
```
生成简单的上一页/下一页分页 HTML 代码。比 `pagination()` 更省资源，不需要 `count()` 查询。

> 【推荐】此函数适用于大数据量场景，避免了 `COUNT(*)` 查询的开销。在现代项目中，建议使用前端框架的分页组件。

#### 参数
- `$url`：分页链接模板，使用 `{page}` 作为页码占位符
- `$totalnum`：总记录数
- `$page`：当前页码
- `$pagesize`：每页显示数量，默认为 20

#### 返回值
- 返回简单的分页 HTML 字符串，包含上一页、当前页/总页数、下一页
- 如果总页数小于 2，返回空字符串

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 生成简单分页
$total = 150;
$page = 3;
$url = 'thread-list-{page}.htm';

echo pager($url, $total, $page, 20);
// 输出: <li><a href="thread-list-2.htm">上一页</a></li> 3 / 8 <li><a href="thread-list-4.htm">下一页</a></li>
```
### `pages()`

#### 说明
```php
function pages(string $url, int $totalnum, int $page, int $pagesize = 20): string
```
生成分页 HTML 代码（非 Bootstrap 风格）。

> 【已弃用】此函数在源码中已被注释掉。请使用 `pagination()` 函数替代。

#### 参数
- `$url`：分页链接模板，使用 `{page}` 作为页码占位符
- `$totalnum`：总记录数
- `$page`：当前页码
- `$pagesize`：每页显示数量，默认为 20

#### 返回值
- 返回分页 HTML 字符串
- 如果总页数小于 2，返回空字符串

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 推荐使用 pagination() 替代
$total = 150;
$page = 3;
$url = 'thread-list-{page}.htm';

echo '<ul class="pagination">' . pagination($url, $total, $page, 20) . '</ul>';
```
### `simple_pages()`

#### 说明
```php
function simple_pages(string $url, int $totalnum, int $page, int $pagesize = 20): string
```
生成简单的上一页/下一页分页 HTML 代码。

> 【已弃用】此函数在源码中已被注释掉。请使用 `pager()` 函数替代。

#### 参数
- `$url`：分页链接模板，使用 `{page}` 作为页码占位符
- `$totalnum`：总记录数
- `$page`：当前页码
- `$pagesize`：每页显示数量，默认为 20

#### 返回值
- 返回简单的分页 HTML 字符串
- 如果总页数小于 2，返回空字符串

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 推荐使用 pager() 替代
$total = 150;
$page = 3;
$url = 'thread-list-{page}.htm';

echo pager($url, $total, $page, 20);
```
### `page()`

#### 说明
```php
function page(int $page, int $n, int $pagesize): int
```
计算并返回合法的页码，确保页码在有效范围内。

> 【已弃用】此函数在源码中已被注释掉。请使用 `mid()` 函数替代，或直接使用 `max(1, min($page, $totalPages))`。

#### 参数
- `$page`：当前页码
- `$n`：总记录数
- `$pagesize`：每页显示数量

#### 返回值
- 返回合法的页码（1 到总页数之间）

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 推荐使用 mid() 替代
$page = 10;
$total = 50;
$pagesize = 20;
$totalPages = ceil($total / $pagesize);  // 3

// 确保页码在有效范围内
$validPage = mid($page, 1, $totalPages);  // 返回 3
```
### `mid()`

#### 说明
```php
function mid(int $n, int $min, int $max): int
```
将数值限制在指定范围内（类似 `clamp` 函数）。

#### 参数
- `$n`：待限制的数值
- `$min`：最小值
- `$max`：最大值

#### 返回值
- 返回限制在 `$min` 和 `$max` 之间的值

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 限制页码范围
$page = mid($_GET['page'], 1, 100);  // 确保页码在 1-100 之间

// 限制年龄范围
$age = mid(150, 0, 120);  // 返回 120

// 限制评分范围
$score = mid(-5, 0, 100);  // 返回 0
```
### `ip()`

#### 说明
```php
function ip(): string
```
获取客户端 IP 地址。支持从 CDN 获取真实 IP。

> 【注意】此函数在开启 CDN 时可能存在 IP 伪造风险。在生产环境中可能需要自行实现更安全的 IP 获取方式。

#### 参数
- 无

#### 返回值
- 返回客户端 IP 地址字符串

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 获取客户端 IP
$clientIp = ip();
echo "客户端 IP: " . $clientIp;

// 用于日志记录
xn_log("用户登录，IP: " . ip(), 'login');

// 用于访问限制
if (ip() === '192.168.1.100') {
    // 管理员操作
}
```
### `get__browser()`

#### 说明
```php
function get__browser(): array
```
获取客户端浏览器信息，包括设备类型、浏览器名称和版本。

> 【推荐】此函数效果极差，但却是那个时代能做到的最佳选择。建议使用专门的 User Agent 解析库如 `mobiledetect/mobiledetectlib`。

#### 参数
- 无

#### 返回值
- 返回包含浏览器信息的数组：
  - `device`：设备类型（`pc`|`mobile`|`pad`）
  - `name`：浏览器名称（`chrome`|`firefox`|`ie`|`opera`|`robot`）
  - `version`：浏览器版本号

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

$browser = get__browser();

// 检测设备类型
if ($browser['device'] === 'mobile') {
    // 移动端适配
}

// 检测 IE 浏览器
if ($browser['name'] === 'ie' && $browser['version'] < 9) {
    // 提示用户升级浏览器
}

// 检测爬虫
if ($browser['name'] === 'robot') {
    // 爬虫处理逻辑
}
```

#### 备注

##### 参考：
- http://www.cnblogs.com/wangchao928/p/4166805.html
- http://www.useragentstring.com/pages/Internet%20Explorer/
- https://github.com/serbanghita/Mobile-Detect/blob/master/Mobile_Detect.php

##### 各种旧浏览器的User Agent样本

###### IE
- Mozilla/4.0 (compatible; MSIE 5.0; Windows NT)
- Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)
- Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.2)
- Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.0)

**Win7+ie9：**
- Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; Win64; x64; Trident/5.0; .NET CLR 2.0.50727; SLCC2; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; InfoPath.3; .NET4.0C; Tablet PC 2.0; .NET4.0E)

**win7+ie11，模拟 78910 头是一样的**
mozilla/5.0 (windows nt 6.1; wow64; trident/7.0; rv:11.0) like gecko

**Win7+ie8：**
Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1; WOW64; Trident/4.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; .NET4.0C; InfoPath.3)

**WinXP+ie8：**
Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 5.1; Trident/4.0; GTB7.0)

**WinXP+ie7：**
Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1)

**WinXP+ie6：**
Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1)

**傲游3.1.7在Win7+ie9,高速模式:**
Mozilla/5.0 (Windows; U; Windows NT 6.1; ) AppleWebKit/534.12 (KHTML, like Gecko) Maxthon/3.0 Safari/534.12

**傲游3.1.7在Win7+ie9,IE内核兼容模式:**
Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; InfoPath.3; .NET4.0C; .NET4.0E)

###### 搜狗
**搜狗3.0在Win7+ie9,IE内核兼容模式:**
Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; InfoPath.3; .NET4.0C; .NET4.0E; SE 2.X MetaSr 1.0)

**搜狗3.0在Win7+ie9,高速模式:**
Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/534.3 (KHTML, like Gecko) Chrome/6.0.472.33 Safari/534.3 SE 2.X MetaSr 1.0

###### 360
**360浏览器3.0在Win7+ie9:**
Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; InfoPath.3; .NET4.0C; .NET4.0E)

###### QQ 浏览器
**QQ 浏览器6.9(11079)在Win7+ie9,极速模式:**
Mozilla/5.0 (Windows NT 6.1) AppleWebKit/535.1 (KHTML, like Gecko) Chrome/13.0.782.41 Safari/535.1 QQBrowser/6.9.11079.201

**QQ浏览器6.9(11079)在Win7+ie9,IE内核兼容模式:**
Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; InfoPath.3; .NET4.0C; .NET4.0E) QQBrowser/6.9.11079.201

###### 阿云浏览器
**阿云浏览器 1.3.0.1724 Beta 在Win7+ie9:**
Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; WOW64; Trident/5.0)

###### MIUI V5
Mozilla/5.0 (Linux; U; Android <android-version>; <location>; <MODEL> Build/<ProductLine>) AppleWebKit/534.30 (KHTML, like Gecko) Version/4.0 Mobile Safari/534.30 XiaoMi/MiuiBrowser/1.0


### `check_browser()`

#### 说明
```php
function check_browser(array $browser): void
```
检查浏览器兼容性，如果不兼容则显示提示页面并退出。

> 【已弃用】现在没人用IE浏览器了

#### 参数
- `$browser`：浏览器信息数组，由 `get__browser()` 返回

#### 返回值
- 无返回值，如果不兼容则直接退出程序

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

$browser = get__browser();
check_browser($browser);  // 如果是 IE8 以下，显示提示页面并退出

// 继续执行正常逻辑
echo "欢迎使用本系统";
```
### `browser_lang()`

#### 说明
```php
function browser_lang(): string
```
获取浏览器首选语言。

> 【推荐】此函数仅支持有限的几种语言。在现代项目中，建议使用 `Locale::acceptFromHttp($_SERVER['HTTP_ACCEPT_LANGUAGE'])` 或解析 `Accept-Language` 头获取更完整的语言信息。

#### 参数
- 无

#### 返回值
- 返回浏览器语言代码，如 `zh-cn`、`ko-kr`

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

$lang = browser_lang();
echo "浏览器语言: " . $lang;  // 输出: zh-cn

// 根据语言加载不同的语言包
if ($lang === 'ko-kr') {
    include './lang/ko.php';
} else {
    include './lang/zh-cn.php';
}
```
### `file_backname()`

#### 说明
```php
function file_backname(string $filepath): string
```
生成备份文件名。在原文件名和扩展名之间添加 `.backup`。

#### 参数
- `$filepath`：原文件路径

#### 返回值
- 返回备份文件名

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

$backupName = file_backname('config/config.php');
// 返回: config/config.backup.php

$backupName = file_backname('data/settings.json');
// 返回: data/settings.backup.json
```
### `is_backfile()`

#### 说明
```php
function is_backfile(string $filepath): bool
```
判断文件是否为备份文件（文件名中包含 `.backup.`）。

#### 参数
- `$filepath`：文件路径

#### 返回值
- 如果是备份文件返回 `TRUE`，否则返回 `FALSE`

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

is_backfile('config/config.backup.php');  // 返回 true
is_backfile('config/config.php');         // 返回 false

// 用于过滤备份文件
$files = glob('config/*.php');
foreach ($files as $file) {
    if (!is_backfile($file)) {
        // 处理非备份文件
    }
}
```
### `file_backup()`

#### 说明
```php
function file_backup(string $filepath): bool
```
创建文件备份。如果备份已存在，则不重复创建。

#### 参数
- `$filepath`：要备份的文件路径

#### 返回值
- 备份成功返回 `TRUE`，失败返回 `FALSE`

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 备份配置文件
if (file_backup('config/config.php')) {
    echo "备份成功";
    // 备份文件: config/config.backup.php
}

// 修改配置前先备份
file_backup('conf/conf.php');
file_put_contents('conf/conf.php', $newContent);
```
### `file_backup_restore()`

#### 说明
```php
function file_backup_restore(string $filepath): bool
```
从备份文件还原。将备份文件复制回原文件，并删除备份。

#### 参数
- `$filepath`：原文件路径

#### 返回值
- 还原成功返回 `TRUE`，失败返回 `FALSE`

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 如果修改失败，还原备份
if (!file_put_contents('config/config.php', $newContent)) {
    file_backup_restore('config/config.php');
    echo "还原成功";
}

// 手动还原配置
file_backup_restore('conf/conf.php');
```
### `file_backup_unlink()`

#### 说明
```php
function file_backup_unlink(string $filepath): bool
```
删除备份文件。

#### 参数
- `$filepath`：原文件路径

#### 返回值
- 删除成功返回 `TRUE`，失败返回 `FALSE`

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 确认修改成功后删除备份
if (file_put_contents('config/config.php', $newContent)) {
    file_backup_unlink('config/config.php');
}

// 清理所有备份文件
$files = glob('config/*.backup.php');
foreach ($files as $file) {
    unlink($file);
}
```
### `move_upload_file()`

#### 说明
```php
function move_upload_file(string $srcfile, string $destfile): bool
```
移动上传的文件到目标位置。内部使用 `xn_copy()` 实现。

> 【推荐】建议直接使用 `move_uploaded_file()` 函数，它有额外的安全检查，确保文件确实是通过 HTTP POST 上传的。

#### 参数
- `$srcfile`：源文件路径（临时文件）
- `$destfile`：目标文件路径

#### 返回值
- 移动成功返回 `TRUE`，失败返回 `FALSE`

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 处理文件上传
if (!empty($_FILES['avatar'])) {
    $tmpName = $_FILES['avatar']['tmp_name'];
    $destFile = './upload/avatar/' . $_FILES['avatar']['name'];
    
    if (move_upload_file($tmpName, $destFile)) {
        echo "上传成功";
    }
}

// 推荐使用 PHP 内置函数
if (is_uploaded_file($tmpName)) {
    move_uploaded_file($tmpName, $destFile);
}
```
### `t()`

#### 说明
```php
function t(string $name = ''): void
```
在 HTTP 响应头中发送调试计时信息。

> 【已弃用】此函数在源码中已被注释掉。

#### 参数
- `$name`：计时标记名称

#### 返回值
- 无

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 推荐使用现代性能分析工具
$startTime = microtime(true);

// ... 执行代码 ...

$endTime = microtime(true);
echo "执行时间: " . ($endTime - $startTime) . " 秒";
```
### `xn_url_parse()`

#### 说明
```php
function xn_url_parse(string $request_url): array
```
解析 Xiuno BBS 特殊格式的 URL。支持多种 URL 格式：
- `http://domain.com/user-login.htm?a=b&c=d`
- `http://domain.com/?user-login.htm?a=b&c=d`
- `http://domain.com/user/login?a=b&c=d`

#### 参数
- `$request_url`：请求的 URL 路径

#### 返回值
- 返回解析后的数组：
  - 数字索引：路径段（如 `user`、`login`，参考param函数的文档）
  - 字符串索引：URL 参数

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 解析 URL
$arr = xn_url_parse('user-login.htm?id=123');
// 返回: ['user', 'login', 'id' => 123]

$arr = xn_url_parse('thread-list-1.htm');
// 返回: ['thread', 'list', '1']

// 获取控制器和动作
$controller = $arr[0] ?? 'index';  // user
$action = $arr[1] ?? 'index';      // login
```
### `xn_url_add_arg()`

#### 说明
```php
function xn_url_add_arg(string $url, string $k, mixed $v): string
```
向 URL 添加参数。支持 Xiuno BBS 的特殊 URL 格式。

#### 参数
- `$url`：原始 URL
- `$k`：参数名
- `$v`：参数值

#### 返回值
- 返回添加参数后的 URL

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 向 .htm 格式 URL 添加参数
$url = xn_url_add_arg('user-login.htm', 'id', 123);
// 返回: user-login-123.htm

// 向普通 URL 添加参数
$url = xn_url_add_arg('list.php', 'page', 2);
// 返回: list.php?page=2

// 向已有参数的 URL 添加参数
$url = xn_url_add_arg('list.php?type=new', 'page', 2);
// 返回: list.php?type=new&page=2
```
### `xn_url_parse_path_format()`

#### 说明
```php
function xn_url_parse_path_format(string $s): array
```
解析路径格式的 URL（如 `/user/login?a=1&b=2`）。

#### 参数
- `$s`：URL 路径字符串

#### 返回值
- 返回解析后的数组：
  - 数字索引：路径段（如 `user`、`login`）
  - 其他键值对：查询参数

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

$arr = xn_url_parse_path_format('/user/login?a=1&b=2');
// 返回: ['user', 'login', 'a' => '1', 'b' => '2']

$arr = xn_url_parse_path_format('/thread/123');
// 返回: ['thread', '123']

// 用于 RESTful 风格路由
$controller = $arr[0] ?? 'index';
$action = $arr[1] ?? 'index';
$id = $arr[2] ?? null;
```
### `xn_copy()`

#### 说明
```php
function xn_copy(string $src, string $dest): bool
```
复制文件。仅支持文件复制，不支持目录。

> 【推荐】可以直接使用 `copy()` 函数。此函数仅是对 `copy()` 的简单封装。

#### 参数
- `$src`：源文件路径
- `$dest`：目标文件路径

#### 返回值
- 复制成功返回 `TRUE`，失败返回 `FALSE`

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 复制文件
if (xn_copy('upload/temp.jpg', 'upload/avatar.jpg')) {
    echo "复制成功";
}

// 复制配置文件
xn_copy('config/config.php', 'config/config.bak.php');
```
### `xn_mkdir()`

#### 说明
```php
function xn_mkdir(string $dir, ?int $mod = NULL, ?bool $recusive = NULL): bool
```
创建目录。

> 【推荐】建议直接使用 `mkdir($dir, 0755, true)` 创建递归目录。

#### 参数
- `$dir`：目录路径
- `$mod`：目录权限，默认为 `NULL`（使用系统默认）
- `$recusive`：是否递归创建，默认为 `NULL`

#### 返回值
- 创建成功返回 `TRUE`，目录已存在或创建失败返回 `FALSE`

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 创建目录
xn_mkdir('upload/avatar', 0755, true);

// 推荐使用 PHP 内置函数
mkdir('upload/avatar', 0755, true);
```
### `xn_rmdir()`

#### 说明
```php
function xn_rmdir(string $dir): bool
```
删除空目录。

> 【推荐】建议直接使用 `rmdir()` 函数。
>
> 【注意】此函数只能删除空目录。

#### 参数
- `$dir`：目录路径

#### 返回值
- 删除成功返回 `TRUE`，失败返回 `FALSE`

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 删除空目录
if (xn_rmdir('upload/temp')) {
    echo "删除成功";
}

// 删除非空目录需要先清空
// 推荐使用 rmdir_recusive() 函数
```
### `xn_unlink()`

#### 说明
```php
function xn_unlink(string $file): bool
```
删除文件。

> 【推荐】建议直接使用 `unlink()` 函数。此函数仅是对 `unlink()` 的简单封装。

#### 参数
- `$file`：文件路径

#### 返回值
- 删除成功返回 `TRUE`，文件不存在或删除失败返回 `FALSE`

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 删除文件
if (xn_unlink('upload/temp.jpg')) {
    echo "删除成功";
}

// 删除临时文件
xn_unlink('temp/cache.tmp');
```
### `xn_filemtime()`

#### 说明
```php
function xn_filemtime(string $file): int
```
获取文件的修改时间。

> 【推荐】建议直接使用 `filemtime()` 函数。此函数仅是对 `filemtime()` 的简单封装，增加了文件存在性检查。

#### 参数
- `$file`：文件路径

#### 返回值
- 返回文件的修改时间戳
- 如果文件不存在，返回 0

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 获取文件修改时间
$mtime = xn_filemtime('config/config.php');
echo "修改时间: " . date('Y-m-d H:i:s', $mtime);

// 检查缓存是否过期
$cacheTime = xn_filemtime('cache/data.json');
if (time() - $cacheTime > 3600) {
    // 缓存过期，重新生成
}
```
### `xn_set_dir()`

#### 说明
```php
function xn_set_dir(int $id, string $dir = './'): string
```
根据 ID 创建分级目录结构。目录格式为 `000/000/` 到 `999/999/`。

此函数用于将大量文件分散存储，避免单个目录下文件过多。

#### 参数
- `$id`：文件/数据的 ID
- `$dir`：基础目录路径，默认为当前目录

#### 返回值
- 返回创建的相对路径，如 `000/001`

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 创建分级目录
$path = xn_set_dir(123, './upload');
// 创建目录: ./upload/000/000/
// 返回: 000/000

$path = xn_set_dir(1000, './upload');
// 创建目录: ./upload/000/001/
// 返回: 000/001

// 保存文件到分级目录
$id = 12345;
$subdir = xn_set_dir($id, './upload');
$filepath = './upload/' . $subdir . '/' . $id . '.jpg';
file_put_contents($filepath, $imageData);
```
### `xn_get_dir()`

#### 说明
```php
function xn_get_dir(int $id): string
```
根据 ID 获取分级目录路径（不创建目录）。

#### 参数
- `$id`：文件/数据的 ID

#### 返回值
- 返回相对路径，如 `000/001`

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 获取分级目录路径
$path = xn_get_dir(123);
// 返回: 000/000

$path = xn_get_dir(1000);
// 返回: 000/001

// 获取已存储文件的路径
$id = 12345;
$subdir = xn_get_dir($id);
$filepath = './upload/' . $subdir . '/' . $id . '.jpg';

if (file_exists($filepath)) {
    // 文件存在
}
```
### `copy_recusive()`

#### 说明
```php
function copy_recusive(string $src, string $dst): void
```
递归复制目录及其内容。

#### 参数
- `$src`：源目录路径
- `$dst`：目标目录路径

#### 返回值
- 无

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 递归复制目录
copy_recusive('./template/default', './template/custom');

// 备份整个插件目录
copy_recusive('./plugin/myplugin', './backup/myplugin');
```
### `xn_shutdown_handle()`

#### 说明
```php
function xn_shutdown_handle(): void
```
脚本结束时的回调函数。当前为空函数，可自定义扩展。

#### 参数
- 无

#### 返回值
- 无

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 注册关闭函数
register_shutdown_function('xn_shutdown_handle');

// 或自定义关闭处理
register_shutdown_function(function() {
    // 清理临时文件
    // 记录日志
    // 关闭资源
});
```
### `xn_debug_info()`

#### 说明
```php
function xn_debug_info(): string
```
获取调试信息，包括执行时间、SQL 语句、请求参数等。

**用途**：仅在 DEBUG 模式下输出调试信息，用于开发调试。

#### 参数
- 无

#### 返回值
- 返回调试信息 HTML 字符串
- 如果 DEBUG 级别小于 2，返回空字符串

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 在页面底部输出调试信息
echo xn_debug_info();

// 调试信息包括：
// - 处理时间
// - 执行的 SQL 语句
// - $_REQUEST 内容
// - $_SESSION 内容
```
### `base64_decode_file_data()`

#### 说明
```php
function base64_decode_file_data(string $data): string
```
解码 Base64 编码的文件数据，支持 Data URL 格式。

**用途**：用于处理前端通过 Canvas 或 FileReader 生成的 Base64 图片数据。

> 【推荐】上传文件时要用enctype="multipart/form-data"而不是enctype="application/x-www-form-urlencoded"然后把文件用base64编码后上传。

#### 参数
- `$data`：Base64 编码的数据，可包含 `data:image/png;base64,` 前缀

#### 返回值
- 返回解码后的原始二进制数据

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 处理前端上传的 Base64 图片
$base64Data = $_POST['image'];  // data:image/png;base64,iVBORw0KGgo...
$imageData = base64_decode_file_data($base64Data);

// 保存为文件
file_put_contents('./upload/avatar.png', $imageData);

// 或直接输出
header('Content-Type: image/png');
echo $imageData;
```
### `http_404()`

#### 说明
```php
function http_404(): void
```
输出 404 错误页面并终止脚本。

#### 参数
- 无

#### 返回值
- 无，直接输出并退出

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 检查资源是否存在
$thread = thread_read($tid);
if (empty($thread)) {
    http_404();  // 输出 404 并退出
}

// 继续处理
echo "帖子内容...";
```
### `http_403()`

#### 说明
```php
function http_403(): void
```
输出 403 禁止访问页面并终止脚本。

#### 参数
- 无

#### 返回值
- 无，直接输出并退出

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 检查用户权限
if (!$user['is_admin']) {
    http_403();  // 无权限，输出 403 并退出
}

// 继续处理管理员操作
echo "管理面板...";
```
### `http_location()`

#### 说明
```php
function http_location(string $url): void
```
发送 HTTP 重定向头并终止脚本。

#### 参数
- `$url`：重定向目标 URL

#### 返回值
- 无，直接重定向并退出

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 登录成功后跳转
if ($login_success) {
    http_location('user-profile.htm');
}

// 跳转到外部链接
http_location('https://example.com');
```
### `http_referer()`

#### 说明
```php
function http_referer(): string
```
获取安全的来源页面 URL。只返回站内 URL，防止开放重定向攻击。

此函数会过滤登录、登出、注册等页面，防止敏感操作后被重定向回这些页面。

#### 参数
- 无

#### 返回值
- 返回安全的来源 URL
- 如果来源不安全或为空，返回 `./`

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 获取来源页面
$referer = http_referer();

// 操作完成后跳回
echo '<a href="' . $referer . '">返回</a>';

// 或用于表单隐藏字段
echo '<input type="hidden" name="referer" value="' . $referer . '">';
```
### `str_push()`

#### 说明
```php
function str_push(string $str, string $v, string $sep = '_'): string
```
向字符串追加值，使用分隔符连接。如果值已存在则不重复添加。

#### 参数
- `$str`：原字符串
- `$v`：要追加的值
- `$sep`：分隔符，默认为 `_`

#### 返回值
- 返回追加后的字符串

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 追加标签
$tags = 'php';
$tags = str_push($tags, 'mysql');    // 返回: "php_mysql"
$tags = str_push($tags, 'php');      // 不重复，返回: "php_mysql"

// 使用其他分隔符
$list = '1,2,3';
$list = str_push($list, '4', ',');   // 返回: "1,2,3,4"
```
### `y2f()`

#### 说明
```php
function y2f(float $rmb): int
```
将元转换为分（人民币单位转换）。

**用途**：用于金额计算（两位小数定点数），避免浮点数精度问题。

#### 参数
- `$rmb`：金额（元）

#### 返回值
- 返回金额（分）

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 元转分
$fen = y2f(1.23);    // 返回: 123
$fen = y2f(100.00);  // 返回: 10000

// 用于金额计算
$price = y2f(19.99);  // 1999 分
$quantity = 3;
$total = $price * $quantity;  // 5997 分 = 59.97 元
```
### `f2y()`

#### 说明
```php
function f2y(int $rmb, string $round = 'float'): float|int
```
将分转换为元（人民币单位转换）。

**用途**：用于显示金额（两位小数定点数），将存储的分转换为用户友好的元。

#### 参数
- `$rmb`：金额（分）
- `$round`：舍入方式，可选值：
  - `float`：返回浮点数（默认）
  - `round`：四舍五入
  - `ceil`：向上取整
  - `floor`：向下取整

#### 返回值
- 返回金额（元）

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 分转元
$yuan = f2y(123);        // 返回: 1.23
$yuan = f2y(10000);      // 返回: 100.00

// 不同的舍入方式
$yuan = f2y(123, 'float');  // 返回: 1.23
$yuan = f2y(123, 'round');  // 返回: 1
$yuan = f2y(123, 'ceil');   // 返回: 2
$yuan = f2y(123, 'floor');  // 返回: 1
```
### `array_to_sqladd()`

#### 说明
```php
function array_to_sqladd(array $arr): string
```
将数组转换为 SQL UPDATE 语句的 SET 部分。

> 【已弃用】此函数在源码中已被注释掉。请使用db系列函数。

#### 参数
- `$arr`：关联数组，键为字段名，值为字段值

#### 返回值
- 返回 SQL SET 语句字符串

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 推荐使用预处理语句
$data = ['name' => 'John', 'age' => 30];
$pdo = new PDO('mysql:host=localhost;dbname=test', 'user', 'pass');
$stmt = $pdo->prepare('UPDATE users SET name = ?, age = ? WHERE id = ?');
$stmt->execute([$data['name'], $data['age'], 1]);
```
### `cond_to_sqladd()`

#### 说明
```php
function cond_to_sqladd(array $cond): string
```
将条件数组转换为 SQL WHERE 语句。

> 【已弃用】此函数在源码中已被注释掉。请使用db系列函数。

#### 参数
- `$cond`：条件数组，键为字段名，值为条件值

#### 返回值
- 返回 SQL WHERE 语句字符串

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 推荐使用预处理语句
$cond = ['status' => 1, 'type' => 'post'];
$pdo = new PDO('mysql:host=localhost;dbname=test', 'user', 'pass');
$stmt = $pdo->prepare('SELECT * FROM content WHERE status = ? AND type = ?');
$stmt->execute([$cond['status'], $cond['type']]);
```
### `orderby_to_sqladd()`

#### 说明
```php
function orderby_to_sqladd(array $orderby): string
```
将排序数组转换为 SQL ORDER BY 语句。

> 【已弃用】此函数在源码中已被注释掉。请使用db系列函数。

#### 参数
- `$orderby`：排序数组，键为字段名，值为排序方向（`ASC` 或 `DESC`）

#### 返回值
- 返回 SQL ORDER BY 语句字符串

#### 示例
```php
<?php
include './xiunophp/xiunophp.php';

// 推荐使用白名单验证排序字段
$allowedFields = ['id', 'create_time', 'views'];
$orderby = [];
foreach ($_GET['sort'] as $field => $direction) {
    if (in_array($field, $allowedFields)) {
        $orderby[$field] = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
    }
}
```
## 杂项函数第二部分

### `check_runlevel()`

#### 说明
```php
function check_runlevel()
```
检测站点的运行级别，根据不同级别执行相应的限制。

#### 参数
无

#### 返回值
无

#### 示例
```php
// 在页面初始化时调用
check_runlevel();
```

### `xn_lock_start()`

#### 说明
```php
function xn_lock_start($lockname = '', $life = 10)
```
创建文件锁，防止并发操作。

#### 参数
- `$lockname`：锁名称
- `$life`：锁的生命周期（秒），超过此时间自动删除锁

#### 返回值
- 成功返回写入的字节数，失败返回 `FALSE`

#### 示例
```php
// 创建锁
if(xn_lock_start('update_thread', 5)) {
    // 执行需要锁定的操作
    // ...
    // 操作完成后删除锁
    xn_lock_end('update_thread');
} else {
    // 锁定失败，可能有其他进程正在执行
    message(1, '操作正在进行中，请稍后再试');
}
```

### `xn_lock_end()`

#### 说明
```php
function xn_lock_end($lockname = '')
```
删除文件锁。

#### 参数
- `$lockname`：锁名称

#### 返回值
无

#### 示例
```php
// 操作完成后删除锁
xn_lock_end('update_thread');
```

### `xn_html_safe()`

#### 说明
```php
function xn_html_safe($doc, $arg = array())
```
过滤HTML内容，防止XSS攻击。

#### 参数
- `$doc`：HTML文档内容
- `$arg`：配置参数数组，如 `array('table_max_width' => 746)`

#### 返回值
- 返回过滤后的安全HTML内容

#### 示例
```php
// 过滤用户输入的HTML
$safe_html = xn_html_safe($user_input);

// 自定义表格最大宽度
$safe_html = xn_html_safe($user_input, array('table_max_width' => 800));
```

## 杂项变量

### `$get_magic_quotes_gpc`

#### 说明
```php
bool $get_magic_quotes_gpc = get_magic_quotes_gpc();
```
是否开启了magic quotes gpc。
> 【已弃用】**请尽你最大努力避免使用。** `get_magic_quotes_gpc()`函数于PHP 8.0.0删除。如果你发现xiunophp.php中按以上签名定义了它的话，请及时替换成false，如：
> ```php
> $get_magic_quotes_gpc = false;
> ```   
> 其附近还有:
> ```php
> version_compare(PHP_VERSION, '5.3.0', '<') AND set_magic_quotes_runtime(0);
> ```
> 也应一并删除。

## 其他使用的库

- XML_HTMLSax3
  - 位置：`./xiunophp/xn_html_safe.func.php`
  - 用途：用于xn_html_safe函数处理HTML内容，防止XSS攻击。
  - 仓库：`https://github.com/pear/XML_HTMLSax3`
  - 许可证：未知
- PHPMailer 5.2.1
  - 位置：`./xiunophp/xn_send_mail.func.php`
  - 用途：用于发送邮件。
  - 仓库：`https://github.com/PHPMailer/PHPMailer`
  - 许可证：LGPL
- php_zip
  - 位置：`./xiunophp/xn_zip_old.func.php`
  - 用途：用于在PHP不支持ZipArchive扩展时，使用php_zip库压缩文件。
  - 仓库：无
  - 许可证：未知
  - 原作者写的备注：该类库收集于互联网，版权未知，由 axiuno@gmail.com 修正了部分64位下的 bug, 如果谁知道请麻烦告知作者。