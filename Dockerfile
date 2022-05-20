ARG image_tag=latest
ARG php_version
FROM --platform=linux/amd64 elifesciences/journal_assets_builder:${image_tag} AS assets
FROM elifesciences/journal_composer:${image_tag} AS composer
FROM scottaubrey/elifesciences-php:7.1-fpm@sha256:e8c0964331152be238eb3f294ba35b040760cf3cc1aa8d509a7e3cb167f8c537

ENV PROJECT_FOLDER=/srv/journal
ENV PHP_ENTRYPOINT=web/app.php
WORKDIR ${PROJECT_FOLDER}

USER root
RUN pecl install redis && \
    docker-php-ext-enable redis && \
    rm -rf /tmp/pear/
RUN mkdir -p build var && \
    chown --recursive elife:elife . && \
    chown --recursive www-data:www-data var

COPY --chown=elife:elife .docker/smoke_tests.sh ./
COPY --chown=elife:elife bin/ bin/
COPY --chown=elife:elife web/ web/
COPY --chown=elife:elife app/ app/
COPY --chown=elife:elife build/critical-css/ build/critical-css/
COPY --from=assets --chown=elife:elife /build/rev-manifest.json build/
COPY --from=assets --chown=elife:elife /web/ /srv/journal/web/
COPY --from=composer --chown=elife:elife /app/vendor/ vendor/
COPY --chown=elife:elife src/ src/

USER www-data

HEALTHCHECK --interval=5s CMD HTTP_HOST=localhost assert_fpm /ping 'pong'
