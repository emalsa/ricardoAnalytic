ARG CLI_IMAGE
FROM ${CLI_IMAGE} as cli

FROM amazeeio/php:7.2-fpm

sudo apt-get install -y php7.0-bz2

COPY --from=cli /app /app
