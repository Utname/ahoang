# Apache + mod_php, not PHP-FPM + Nginx: this project already ships a working
# www/.htaccess (HTTPS redirect, front-controller rewrite, dot-file blocking,
# gzip/cache headers) written for Apache — reusing it means zero new rewrite
# rules to port to Nginx syntax and re-verify. See docker/apache-vhost.conf
# for the vhost that turns AllowOverride on for it and fixes HTTPS detection
# behind a reverse proxy (Railway or similar).
FROM php:8.2-apache

# The libFoo-dev packages below are only needed to build the PHP extensions
# further down and are purged again afterward so they don't bloat the final
# image. `curl` (the CLI tool, unrelated to the `curl` PHP extension built
# below from libcurl4-openssl-dev) and `default-mysql-client` (the `mysql`
# CLI) are kept as runtime tools: curl so a Railway Cron service can hit
# /cron/default on a schedule, mysql so app/database/*.sql can be applied by
# shelling into the running container instead of installing a client
# separately.
RUN apt-get update && apt-get install -y --no-install-recommends \
		libicu-dev \
		libzip-dev \
		libpng-dev \
		libjpeg62-turbo-dev \
		libfreetype6-dev \
		libsqlite3-dev \
		libcurl4-openssl-dev \
		libonig-dev \
		unzip \
		git \
		curl \
		default-mysql-client \
	&& docker-php-ext-configure gd --with-freetype --with-jpeg \
	&& docker-php-ext-install -j"$(nproc)" gd \
	&& docker-php-ext-install -j"$(nproc)" intl \
	&& docker-php-ext-install -j"$(nproc)" mbstring \
	&& docker-php-ext-install -j"$(nproc)" mysqli \
	&& docker-php-ext-install -j"$(nproc)" pdo_mysql \
	&& docker-php-ext-install -j"$(nproc)" sqlite3 \
	&& docker-php-ext-install -j"$(nproc)" pdo_sqlite \
	&& docker-php-ext-install -j"$(nproc)" zip \
	&& docker-php-ext-install -j"$(nproc)" curl \
	&& apt-get purge -y --auto-remove libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libsqlite3-dev libcurl4-openssl-dev libonig-dev \
	&& rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite deflate expires headers

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
