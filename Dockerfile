# Dockerfile — builds the custom PHP image used by the "app" service
# This is NOT MySQL or Nginx — this image only runs the PHP interpreter (FPM mode).

# Start from the official PHP 8.5 image, "fpm" variant.
# FPM = FastCGI Process Manager — it listens for requests on port 9000
# and executes PHP scripts; it does NOT serve HTTP directly (Nginx handles that).
FROM php:8.5-fpm

# Install system libraries + compile/enable PHP extensions.
# Vanilla PHP cannot talk to MySQL or handle zip files out of the box —
# these extensions add that capability.
#
# libpng-dev / libzip-dev / unzip -> system libraries the PHP extensions below need to compile
# docker-php-ext-install           -> helper script baked into the official PHP image
#                                      that compiles and enables PHP extensions
# pdo          -> generic database-access interface used by index.php
# pdo_mysql    -> the MySQL driver for PDO
# mysqli       -> alternative/older MySQL extension (some libraries expect this one)
# zip          -> lets PHP read/write .zip files
# apt-get clean / rm -rf ...       -> deletes package cache, keeps final image smaller
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libzip-dev \
    unzip \
  && docker-php-ext-install pdo pdo_mysql mysqli zip \
  && apt-get clean && rm -rf /var/lib/apt/lists/*

# Every command from here on (and the running container itself)
# starts in this directory. This matches the path Nginx expects
# in fastcgi_param SCRIPT_FILENAME (see nginx/default.conf).
WORKDIR /var/www/html
