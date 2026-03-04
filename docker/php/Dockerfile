# 基于官方 PHP 8.2 Alpine 镜像
FROM php:8.2-fpm-alpine

# 安装系统依赖和 PHP 扩展
RUN apk add --no-cache \
    linux-headers \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libzip-dev \
    zip \
    unzip \
    icu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo_mysql mysqli zip opcache intl pcntl bcmath sockets

# 安装 Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 配置 PHP
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# 复制自定义 php.ini 配置（如果需要）
# COPY php.ini $PHP_INI_DIR/conf.d/custom.ini

# 设置工作目录
WORKDIR /var/www/html

# 设置权限（可选，视具体需求而定）
# RUN chown -R www-data:www-data /var/www/html

# 暴露端口
EXPOSE 9000

CMD ["php-fpm"]
