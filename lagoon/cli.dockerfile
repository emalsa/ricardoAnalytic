FROM amazeeio/php:7.2-cli-drupal

RUN apk add php7-exif

COPY scripts /app/scripts
COPY . /app

# Define where the Drupal Root is located
ENV WEBROOT=web