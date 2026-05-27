# Xiuno BBS 开发环境配置指南
本文档由TRAE编写。本文档使用了AI辅助生成。

## 环境要求

Xiuno BBS 的开发环境要求不高，只需要以下基础组件即可：

- **Web 服务器**: Apache / Nginx
- **PHP**: 最低7.2，推荐 8.0 及以上版本，因为这是大势所趋；PHP7.4都已经停止维护了，还不赶紧用PHP 8？
- **MySQL**: 推荐使用较新版本
- **缓存系统**: 可选安装（如 Redis、Memcached 等）

## 推荐开发环境

### XAMPP（推荐）

XAMPP 是最便捷的开发环境解决方案，一键安装即可获得完整的开发环境：

- Apache Web 服务器
- PHP 运行环境
- MySQL 数据库
- phpMyAdmin 管理工具

**下载地址**: https://www.apachefriends.org/

### 其他替代方案

- **Laragon**（Windows）: 轻量级，支持快速切换 PHP/MySQL 版本
- **WAMP**（Windows）: Windows 下的 Apache + MySQL + PHP 集成环境
- **MAMP**（macOS）: Mac 下的开发环境集成包

#### 关于Docker

十分遗憾的是，Xiuno BBS 不支持 Docker 容器化部署。

## 开发模式配置

### 开启调试模式

在项目根目录的 `index.php` 文件中，找到 DEBUG 常量定义：

```php
// 0: 线上模式; 1: 调试模式; 2: 插件开发模式;
!defined('DEBUG') AND define('DEBUG', 0);
```

**开发时请将 DEBUG 设置为 2**：

```php
!defined('DEBUG') AND define('DEBUG', 2);
```

### DEBUG 模式说明

| 值 | 模式 | 说明 |
|---|------|------|
| 0 | 线上模式 | 生产环境使用，关闭所有调试信息 |
| 1 | 调试模式 | 开启错误显示，便于排查问题 |
| 2 | 插件开发模式 | 完整的开发模式，包含调试信息和开发工具 |

## 安装步骤

1. 安装 XAMPP 或其他开发环境
2. 将 Xiuno BBS 项目放置到 Web 根目录
3. 启动 Apache 和 MySQL 服务
4. 浏览器访问项目地址，按照安装向导完成安装
5. 修改 `index.php` 中的 DEBUG 为 2

## 测试说明

### 单元测试

也是很遗憾的是，由于 Xiuno BBS 程序本身的结构特点，**无法进行单元测试**。

在开发插件和主题时，建议采用以下测试方法：

1. **记录测试用例**: 开发前明确功能需求和预期效果
2. **手动测试**: 在浏览器中亲自操作，包括：
   - 点击按钮
   - 填写表单
   - 验证页面跳转
   - 检查数据显示
3. **多场景覆盖**: 测试正常流程和边界情况，例如：
   - 程序本身的位置是在子文件夹里，而不是 Web 根目录
     - 例如：`/htdocs/xiunobbs/`而不是`/htdocs/`导致web服务器对应网址是`localhost/xiunobbs/`而不是`localhost/`
       - 所有前端资源的路径应该是`./plugin/YOUR_PLUGIN_NAME/view/css/style.css`，而不是`/plugin/YOUR_PLUGIN_NAME/view/css/style.css`

## 注意事项

- **生产环境务必将 DEBUG 设置为 0**，避免泄露敏感信息
- 开发时建议开启 PHP 错误显示（不仅是 DEBUG设置成 2，还需要检查php.ini 中 `display_errors = On，因为程序本身不会刻意在DEBUG模式下设置error_reporting(E_ALL)`）
- 建议使用版本控制工具（如 Git）管理代码
