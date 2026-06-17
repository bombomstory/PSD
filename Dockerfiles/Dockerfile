FROM php:8.1-apache

# ติดตั้ง mysqli extension
RUN docker-php-ext-install mysqli

# ติดตั้ง dependencies สำหรับ ZIP และเครื่องมือพื้นฐาน
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    && docker-php-ext-install zip \
    && docker-php-ext-install pdo pdo_mysql
	
# เปิด mod_rewrite (optional)
RUN a2enmod rewrite

# กำหนด DocumentRoot (optional)
WORKDIR /var/www/html

