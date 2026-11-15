#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
if [ ! -f .env ]; then
    printf '%s\n' 'Brak .env. Skopiuj .env.example i ustaw prywatne hasła DB_PASSWORD oraz MARIADB_ROOT_PASSWORD.' >&2
    exit 1
fi
docker compose config --quiet
docker compose --profile dev build
docker compose up -d --wait db
docker compose run --rm app composer install --no-interaction --prefer-dist
docker compose run --rm app php artisan config:clear
# Preserve the encryption key on repeated bootstrap.
bootstrap_app_key=$(sed -n 's/^APP_KEY=//p' .env)
if [ -z "$bootstrap_app_key" ] || [ "$bootstrap_app_key" = '""' ] || [ "$bootstrap_app_key" = "''" ]; then
    docker compose run --rm app php artisan key:generate --no-interaction
fi
docker compose run --rm app composer wayfinder
docker compose run --rm node npm ci
docker compose run --rm node npm run build
docker compose --profile dev up -d --wait
printf '%s\n' 'Bootstrap zakończony: /up i Vite są gotowe. Migracje i seedy uruchamiaj osobno według docs/infrastructure/DOCKER.md.'
