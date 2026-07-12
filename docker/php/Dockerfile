# PHP 8.2-FPM for Laravel 9 (soccer_php)
FROM php:8.2-fpm

# 系统依赖
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    wget \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    zip \
    unzip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# PHP 核心扩展
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    opcache

# PECL 扩展：redis / amqp(RabbitMQ)
RUN pecl install redis && docker-php-ext-enable redis
RUN pecl install amqp && docker-php-ext-enable amqp

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# PHP 配置
COPY docker/php/php.ini /usr/local/etc/php/conf.d/soccer.ini

WORKDIR /var/www

CMD ["php-fpm"]
