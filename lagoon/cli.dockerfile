FROM amazeeio/php:7.2-cli-drupal

RUN apk add chromium
RUN apk add chromium-chromedriver

COPY composer.json composer.lock /app/
COPY scripts /app/scripts
RUN composer install --no-dev
COPY . /app

# copy crontabs for root user
COPY /lagoon/cronjobs /etc/crontabs/root

# start crond with log level 8 in foreground, output to stderr
CMD ["crond", "-f", "-d", "8"]

# Define where the Drupal Root is located
ENV WEBROOT=web