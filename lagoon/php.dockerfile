ARG CLI_IMAGE
FROM ${CLI_IMAGE} as cli

FROM amazeeio/php:7.2-fpm

RUN apk add chromium
RUN apk add chromium-chromedriver

COPY --from=cli /app /app
