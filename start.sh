#!/bin/sh
# start.sh — substitutes PORT env var into Nginx config then starts supervisord.
# Render assigns a random PORT — we inject it into Nginx's listen directive
# using envsubst (part of gettext-base) before starting the server.

# Default PORT to 10000 if Render hasn't set it yet
PORT=${PORT:-10000}

echo "Starting with PORT=$PORT"

# envsubst replaces ${PORT} in the template with the actual port number
# and writes the result to the real Nginx config location
envsubst '${PORT}' < /etc/nginx/conf.d/render.conf.template > /etc/nginx/conf.d/default.conf

# Remove the template so Nginx doesn't try to load it
rm /etc/nginx/conf.d/render.conf.template

# Start supervisord which launches both Nginx and PHP-FPM
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
