FROM php:7.2-alpine

RUN apk add --update --no-cache make $PHPIZE_DEPS && \
	pecl install xdebug && \
	docker-php-ext-enable xdebug

RUN echo $'\
xdebug.remote_autostart=1\n\
xdebug.remote_enable=1\n\
xdebug.remote_host=host.docker.internal\n\
' >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

RUN echo $'\
date.timezone=UTC\n\
' >> /usr/local/etc/php/conf.d/php.ini

ENV PHP_IDE_CONFIG serverName=icanboogie-tests
ENV COMPOSER_ALLOW_SUPERUSER 1

RUN curl -vs https://raw.githubusercontent.com/composer/getcomposer.org/76a7060ccb93902cd7576b67264ad91c8a2700e2/web/installer | php -- --quiet && \
	mv composer.phar /usr/local/bin/composer
