# syntax=docker/dockerfile:1

ARG NODE_IMAGE=node:24-bookworm-slim@sha256:01d566e8d94d8c619591d78652babfccd3c6992a3dffa9ed5ab39e11cf6c70e9
ARG PHP_IMAGE=yiisoftware/yii2-php@sha256:daf90c6e6b9a2a107c2ae5a0360deeabe8f998d0ecf913b4447d84d87bbbc6ee

FROM ${NODE_IMAGE} AS frontend
WORKDIR /build/frontend
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY frontend/ ./
ARG VITE_ADMIN_CONTACT_URL=
RUN VITE_ADMIN_CONTACT_URL="$VITE_ADMIN_CONTACT_URL" npm run build

FROM ${PHP_IMAGE} AS dependencies
WORKDIR /app
ENV COMPOSER_ALLOW_SUPERUSER=1
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts --optimize-autoloader \
    && composer check-platform-reqs --no-dev

FROM ${PHP_IMAGE} AS production
WORKDIR /app
ENV YII_ENV=prod \
    YII_DEBUG=false \
    PHP_ENABLE_XDEBUG=0 \
    PHP_USER_ID=0

COPY assets/ ./assets/
COPY CVS/ ./CVS/
COPY commands/ ./commands/
COPY config/ ./config/
COPY controllers/ ./controllers/
COPY filters/ ./filters/
COPY helpers/ ./helpers/
COPY mail/ ./mail/
COPY migrations/ ./migrations/
COPY models/ ./models/
COPY modules/ ./modules/
COPY services/ ./services/
COPY views/ ./views/
COPY widgets/ ./widgets/
COPY web/ ./web/
COPY composer.json composer.lock yii ./
COPY --from=dependencies /app/vendor/ ./vendor/
COPY --from=frontend /build/web/vue-dist/ ./web/vue-dist/
COPY docker/php-production.ini /usr/local/etc/php/conf.d/zz-production.ini
COPY docker/apache-resources.conf /etc/apache2/conf-enabled/samdaught-resources.conf
COPY docker/apache-global.conf /etc/apache2/conf-enabled/samdaught-global.conf
COPY docker/apache-bootstrap.conf /opt/samdaught/apache-bootstrap.conf
COPY docker/apache-https.conf /opt/samdaught/apache-https.conf
COPY docker/apache-bootstrap.conf /etc/apache2/sites-available/000-default.conf
COPY docker/https-bootstrap.html /var/www/maintenance/index.html
COPY docker/https-entrypoint.sh /usr/local/bin/samdaught-entrypoint

RUN mkdir -p runtime/sessions web/assets \
    && chown www-data:www-data runtime runtime/sessions web/assets \
    && chmod 0775 runtime web/assets \
    && chmod 0700 runtime/sessions \
    && sed -i 's/\r$//' yii \
    && chmod 0755 yii \
    && chmod 0755 /usr/local/bin/samdaught-entrypoint \
    && mkdir -p /var/www/certbot \
    && a2enmod ssl rewrite \
    && printf 'Listen 80\n' > /etc/apache2/ports.conf \
    && test -s web/vue-dist/.vite/manifest.json \
    && apache2ctl -t

EXPOSE 80 443
ENTRYPOINT ["/usr/local/bin/samdaught-entrypoint"]
CMD ["apache2-foreground"]
