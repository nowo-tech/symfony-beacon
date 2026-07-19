#syntax=docker/dockerfile:1

FROM dunglas/frankenphp:1-php8.5 AS frankenphp_upstream

FROM frankenphp_upstream AS frankenphp_base

WORKDIR /app

ARG NODE_MAJOR=22

RUN apt-get update && apt-get install -y --no-install-recommends \
	acl \
	ca-certificates \
	curl \
	file \
	git \
	gnupg \
	&& curl -fsSL "https://deb.nodesource.com/setup_${NODE_MAJOR}.x" | bash - \
	&& apt-get install -y --no-install-recommends nodejs \
	&& corepack enable \
	&& corepack prepare pnpm@10.12.4 --activate \
	&& install-php-extensions \
		@composer \
		apcu \
		intl \
		opcache \
		pdo_mysql \
		zip \
	&& node --version \
	&& pnpm --version \
	&& rm -rf /var/lib/apt/lists/*

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV PHP_INI_SCAN_DIR=":$PHP_INI_DIR/app.conf.d"
ENV PNPM_HOME="/pnpm"
ENV PATH="$PNPM_HOME:$PATH"
ENV COREPACK_ENABLE_DOWNLOAD_PROMPT=0
ENV CI=1

COPY --link .docker/frankenphp/conf.d/10-app.ini $PHP_INI_DIR/app.conf.d/
COPY --link --chmod=755 .docker/frankenphp/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
COPY --link .docker/frankenphp/Caddyfile /etc/caddy/Caddyfile

ENTRYPOINT ["docker-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile"]

# Dev image
FROM frankenphp_base AS frankenphp_dev

ENV APP_ENV=dev
ENV XDEBUG_MODE=off

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" \
	&& install-php-extensions xdebug

COPY --link .docker/frankenphp/conf.d/20-app.dev.ini $PHP_INI_DIR/app.conf.d/

CMD ["frankenphp", "run", "--config", "/etc/caddy/Caddyfile", "--watch"]

# Prod image
FROM frankenphp_base AS frankenphp_prod

ENV APP_ENV=prod

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --link .docker/frankenphp/conf.d/20-app.prod.ini $PHP_INI_DIR/app.conf.d/

COPY --link composer.* symfony.* package.json ./
RUN composer install --no-cache --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress

COPY --link . ./

RUN mkdir -p var/cache var/log \
	&& pnpm install --frozen-lockfile \
	&& pnpm run build \
	&& composer dump-autoload --classmap-authoritative --no-dev \
	&& composer dump-env prod \
	&& composer run-script --no-dev post-install-cmd \
	&& chmod +x bin/console \
	&& sync
