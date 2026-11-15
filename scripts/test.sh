#!/bin/sh
# Run sequentially: backend migration/concurrency tests own edudziennik_test.
set -eu
cd "$(dirname "$0")/.."
docker compose config --quiet
mkdir -p test-results
rm -f test-results/phpunit.xml test-results/vitest.xml test-results/playwright.xml
docker compose exec -T app composer test
docker compose run --rm node npm run test:unit
docker compose exec -T app composer wayfinder
docker compose run --rm node npm run build
# Playwright needs Node + Chromium + PHP in the same network namespace.
# See docs/testing/TEST_STRATEGY.md for the Docker runner.
docker compose -f compose.yaml -f compose.testing.yaml run --build --rm browser
