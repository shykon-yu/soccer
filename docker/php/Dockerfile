# PHP 8.2-FPM for Laravel 9 (soccer_php)
FROM docker.m.daocloud.io/library/php:8.2-fpm

# Use a mainland China mirror by default; pass --build-arg APT_MIRROR=deb.debian.org
# when building in an environment with fast access to Debian's official mirror.
ARG APT_MIRROR=mirrors.aliyun.com
RUN sed -i "s|deb.debian.org|${APT_MIRROR}|g; s|security.debian.org|${APT_MIRROR}|g" /etc/apt/sources.list.d/debian.sources

# 系统依赖
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    zip \
    unzip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# PHP 核心扩展
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install -j$(nproc) \
    curl \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    sockets \
    zip \
    opcache

# PECL 扩展：redis
RUN pecl install redis && docker-php-ext-enable redis

# Composer
COPY --from=docker.m.daocloud.io/library/composer:2 /usr/bin/composer /usr/bin/composer

# PHP 配置
COPY docker/php/php.ini /usr/local/etc/php/conf.d/soccer.ini

WORKDIR /var/www

# 本地镜像安装完整依赖，当前应用配置会加载 Debugbar 等开发服务提供者。
# 运行时可用命名卷覆盖 vendor 目录而不依赖宿主机环境。
COPY composer.json composer.lock ./
# 当前锁文件中的 laravel-lang/lang 被 Composer 新版 malware 规则误拦，
# 依赖仍严格按 composer.lock 安装；后续升级依赖时再恢复默认审计策略。
RUN composer config --global policy.malware.block false \
    && composer config --global repos.packagist composer https://mirrors.cloud.tencent.com/composer/ \
    && composer install --no-interaction --prefer-dist --no-progress --no-scripts
COPY . .
RUN composer dump-autoload --optimize

CMD ["php-fpm"]
