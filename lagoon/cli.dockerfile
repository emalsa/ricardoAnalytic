FROM amazeeio/php:7.2-cli-drupal

RUN apk add chromium
RUN apk add chromium-chromedriver

COPY composer.json composer.lock /app/
COPY scripts /app/scripts
RUN composer install --no-dev
COPY . /app

# Define where the Drupal Root is located
ENV WEBROOT=web