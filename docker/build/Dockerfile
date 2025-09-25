FROM php:8.4-fpm-alpine

RUN apk add --no-cache nginx supervisor bash tzdata mc \
	&& docker-php-ext-install mysqli pdo pdo_mysql

ENV TZ=${TIMEZONE}

ENV APP_ROOT=/var/www/html
RUN mkdir -p ${APP_ROOT} /run/php /run/nginx /var/log/supervisor

COPY . ${APP_ROOT}
RUN rm -rf ${APP_ROOT}/.git ${APP_ROOT}/docker

COPY docker/build/php.ini /usr/local/etc/php/conf.d/php.ini
COPY docker/build/nginx.conf /etc/nginx/nginx.conf
COPY docker/build/supervisord.conf /etc/supervisord.conf
COPY docker/build/entrypoint.sh /entrypoint.sh

RUN chown -R www-data:www-data ${APP_ROOT}
RUN chmod +x /entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]