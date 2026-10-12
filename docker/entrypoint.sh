#!/bin/sh
set -e

# Railway (and most container PaaS) assigns a port at runtime via $PORT and
# routes external traffic to whatever the container listens on — it's not
# always 80, and isn't known until the container actually starts, so it can't
# be baked in at build time. ports.conf and the vhost both ship with a
# __PORT__ placeholder (see docker/ports.conf, docker/apache-vhost.conf);
# this substitutes it once, at container start, before Apache reads either
# file. Defaults to 80 so `docker run` locally (or any host that doesn't set
# $PORT) still works unchanged.
PORT="${PORT:-80}"
sed -i "s/__PORT__/${PORT}/g" /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf

# Doing this at build time (in the Dockerfile) turned out to be unreliable —
# a Railway build reused a stale cached layer from before this fix existed,
# silently shipping an image with both mpm_event and mpm_prefork enabled
# (confirmed via `railway ssh`: both symlinks carried the base image's
# original install timestamp, proving the Dockerfile's a2dismod/a2enmod
# RUN step never actually executed in that image). Running it here instead,
# on every container start, has no build cache to go stale.
a2dismod mpm_event mpm_worker >/dev/null 2>&1 || true
a2enmod mpm_prefork rewrite deflate expires headers >/dev/null 2>&1 || true

# Labels render from print_templates.html_source, so copy the .latte files from
# this image into the database on every start. Only when a database is
# configured; a failure must not stop the web server from starting.
if [ -n "${MYSQLHOST:-}" ]; then
	php /var/www/html/bin/sync-print-templates.php || echo "print template sync failed" >&2
fi

exec apache2-foreground
