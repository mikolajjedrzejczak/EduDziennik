#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
docker compose config --quiet
docker compose --profile dev up -d --wait
docker compose exec -T app php tests/Infrastructure/persistence.php write
# Recreate containers, retaining all named volumes. No down -v.
docker compose --profile dev down
docker compose --profile dev up -d --wait
docker compose exec -T app php tests/Infrastructure/persistence.php read
docker compose exec -T app php tests/Infrastructure/persistence.php cleanup
docker compose exec -T nginx wget -q -O /dev/null http://127.0.0.1/up
docker compose exec -T node node -e 'fetch(`http://127.0.0.1:${process.env.VITE_PORT}/@vite/client`).then(r=>{if(!r.ok)process.exit(1)}).catch(()=>process.exit(1))'
docker compose exec -T node node tests/Infrastructure/hmr.mjs
printf '%s\n' 'Compose, odtworzenie kontenerów, trwałość rekordu, HTTP /up i HMR Vite: OK.'
