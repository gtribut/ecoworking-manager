#!/usr/bin/env bash
#
# Suite e2e SPA MEMBRE — Playwright TypeScript (ADR-0008).
#
# Cible la SPA telle que servie EN PRODUCTION : build Vite → public/portal/
# + catch-all Laravel (C12.1). Le runner Playwright ET le serveur Laravel
# tournent DANS le conteneur Sail ; la base est la base DÉDIÉE `e2e`
# (migrate:fresh + E2eSeeder à chaque run, cf. portal-spa/e2e/serve.sh) —
# jamais la base de dev.
#
# Usage : scripts/e2e/spa.sh [args playwright] (ex. --grep "magic link")
#
set -euo pipefail
cd "$(dirname "$0")/../.."

echo '— Base e2e dédiée (créée si absente, credentials du conteneur pgsql)'
docker compose exec -T pgsql bash -c \
    'psql -U "$POSTGRES_USER" -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname='\''e2e'\''" | grep -q 1 || createdb -U "$POSTGRES_USER" e2e'

echo '— Build SPA production (tsc + vite → public/portal/)'
(cd portal-spa && ./node_modules/.bin/tsc -b && ./node_modules/.bin/vite build)

echo '— Suite Playwright (conteneur Sail, serveur géré par webServer)'
docker compose exec -T -u sail -w /var/www/html/portal-spa \
    -e PLAYWRIGHT_BROWSERS_PATH=/var/www/html/.playwright-browsers \
    laravel.test node_modules/.bin/playwright test "$@"
