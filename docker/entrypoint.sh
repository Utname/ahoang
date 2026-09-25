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

# TEMPORARY: stay alive instead of starting Apache so `railway ssh` can get
# into a live container to debug the "More than one MPM loaded" crash
# in-place (SSH can't reach a container that crash-loops and exits).
# Revert to `exec apache2-foreground` once diagnosed.
echo "DEBUG MODE: sleeping instead of starting Apache"
exec sleep infinity
