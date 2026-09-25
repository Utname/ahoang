# Apache + mod_php, not PHP-FPM + Nginx: this project already ships a working
# www/.htaccess (HTTPS redirect, front-controller rewrite, dot-file blocking,
# gzip/cache headers) written for Apache — reusing it means zero new rewrite
# rules to port to Nginx syntax and re-verify. See docker/apache-vhost.conf
# for the vhost that turns AllowOverride on for it and fixes HTTPS detection
# behind a reverse proxy (Railway or similar).
FROM php:8.2-apache

# docker-php-ext-install (the bundled helper) reliably broke on sqlite3 with
# "Cannot find config.m4" in this image regardless of extension order or
# whether calls were combined or split — mlocati/docker-php-extension-installer
# is the standard, far more robust replacement (used across huge numbers of
# PHP Docker images): it resolves each extension's system package
# dependencies itself, so the manual libFoo-dev list this used to need is
# gone. `curl` (the CLI tool, unrelated to the `curl` PHP extension) and
# `default-mysql-client` are kept as runtime tools: curl so a Railway Cron
# service can hit /cron/default on a schedule, mysql so app/database/*.sql
# can be applied by shelling into the running container instead of
# installing a client separately.
RUN apt-get update && apt-get install -y --no-install-recommends \
		curl \
		unzip \
		git \
		default-mysql-client \
	&& curl -sSLf -o /usr/local/bin/install-php-extensions https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions \
	&& chmod +x /usr/local/bin/install-php-extensions \
	&& install-php-extensions gd intl mbstring mysqli pdo_mysql pdo_sqlite sqlite3 zip curl \
	&& rm -rf /var/lib/apt/lists/*

# install-php-extensions leaves mpm_event enabled alongside the base image's
# default mpm_prefork, and Apache refuses to start at all with two MPMs
# loaded ("More than one MPM loaded"). Classic mod_php (this Dockerfile
# doesn't use PHP-FPM) requires mpm_prefork specifically, since mod_php
# itself isn't thread-safe. This used to be a build-time RUN step here, but
# a Railway build reused a stale cached layer from before the fix existed
# and silently shipped an image with both MPMs still enabled (confirmed via
# `railway ssh` — the symlinks carried the base image's original install
# timestamp) — so it now runs in docker/entrypoint.sh on every container
# start instead, where there's no build cache to go stale.

COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/ports.conf /etc/apache2/ports.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

# No require-dev in composer.json, so --no-dev has nothing to exclude today,
# but stays here since a real deploy should never install dev-only deps even
# if some get added later.
RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader

# Directories app/Bootstrap.php expects to exist and be writable at runtime
# (Tracy logs, Nette's cache/SQLite journal, uploaded product
# images/files/favicons, the resized-image cache, generated XML feeds).
# FilesService::createDirIfNotExists() would create most of these on first
# request anyway, but it silently swallows mkdir/chmod failures — doing it
# here, once, with a build that fails loudly if it can't, is safer than
# discovering a permissions problem via a silently-broken upload in
# production.
RUN mkdir -p temp log \
		www/files/images www/files/files www/files/favicon www/files/temp \
		www/media www/xml \
	&& chown -R www-data:www-data /var/www/html \
	&& chmod -R 775 temp log www/files www/media www/xml

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
