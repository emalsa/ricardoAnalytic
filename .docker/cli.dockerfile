FROM php:7.4.0-cli

RUN apt-get update && apt-get install -y libmcrypt-dev \
    mariadb-client libmagickwand-dev --no-install-recommends \
    libonig-dev \
    cron \
    nano \
    supervisor \
    && docker-php-ext-install pdo_mysql \
    && docker-php-ext-install sockets

RUN apt update \
    && apt-get install -y libfreetype6-dev libjpeg62-turbo-dev libpng-dev \
    && docker-php-ext-configure gd --with-freetype=/usr/include/ --with-jpeg=/usr/include \
    && docker-php-ext-install -j$(nproc) intl pdo_mysql bcmath mbstring exif gd

RUN pecl install imagick && docker-php-ext-enable imagick

# Composer
RUN curl -sS https://getcomposer.org/installer | php -- --version=1.10.0 --install-dir=/usr/local/bin --filename=composer

# Redis
RUN pecl install redis && docker-php-ext-enable redis

# XDebug
RUN pecl install xdebug-2.9.2
RUN docker-php-ext-enable xdebug
COPY ./xdebug.ini ../../../usr/local/etc/php/conf.d/xdebug.ini


# Configure cron
RUN crontab -l | { cat; echo "*/4 * * * * /var/www/bin/drush --root=/var/www/web/ queue-throttle-run article_queue --time-limit=90 --items=30 --unit=minute >> /var/log/cron-article-queue.log 2>&1"; } | crontab -
#RUN crontab -l | { cat; echo "*/5 * * * * /var/www/bin/drush --root=/var/www/web/ queue-throttle-run article_tag_queue --time-limit=60 --items=400 --unit=minute >> /var/log/cron-article-tag.log 2>&1"; } | crontab -
RUN crontab -l | { cat; echo "*/10 * * * * /var/www/bin/drush --root=/var/www/web/ cron >> /var/log/cron.log 2>&1"; } | crontab -

# Configure supervisor
COPY ./supervisord.conf /etc/supervisor/supervisord.conf

RUN ln -s /usr/local/bin/php /usr/bin/php

# Is not public this project
#RUN chmod -R 755 /var/www/

# Start cron and php-fpm
RUN service cron start
CMD cron -f