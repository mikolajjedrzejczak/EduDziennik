#!/bin/sh
set -eu
# Only writable runtime directories; source files and .env keep their owner.
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R u+rwX,g+rwX storage bootstrap/cache
exec docker-php-entrypoint "$@"
