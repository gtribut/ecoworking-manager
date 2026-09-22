#!/usr/bin/env bash
#
# Documentation de présentation (docs/presentation/) — captures d'écran + PDF.
#
# Captures : Playwright (portal-spa/presentation/capture.spec.ts) dans le
# conteneur Sail, sur la base DÉDIÉE `presentation` reseedée avec DemoSeeder
# par presentation/serve.sh. La base de dev n'est jamais touchée.
# PDF : README.md → HTML → Chromium (portal-spa/presentation/build-pdf.mjs).
#
# Usage : scripts/presentation/build.sh [--pdf-only]
# Prérequis : sail up -d + scripts/e2e/install.sh (navigateurs Playwright).
#
set -euo pipefail
cd "$(dirname "$0")/../.."

PDF_ONLY=0
[[ "${1:-}" == "--pdf-only" ]] && PDF_ONLY=1

in_container() {
    docker compose exec -T -u sail -w /var/www/html/portal-spa \
        -e PLAYWRIGHT_BROWSERS_PATH=/var/www/html/.playwright-browsers "$@"
}

if [[ $PDF_ONLY -eq 0 ]]; then
    echo '— Base `presentation` dédiée (créée si absente)'
    docker compose exec -T pgsql bash -c \
        'psql -U "$POSTGRES_USER" -d postgres -tAc "SELECT 1 FROM pg_database WHERE datname='\''presentation'\''" | grep -q 1 || createdb -U "$POSTGRES_USER" presentation'

    echo '— Build SPA production (tsc + vite → public/portal/)'
    (cd portal-spa && ./node_modules/.bin/tsc -b && ./node_modules/.bin/vite build)

    echo '— Captures Playwright (seed DemoSeeder + serveur gérés par webServer)'
    in_container laravel.test node_modules/.bin/playwright test -c presentation/playwright.config.ts
fi

echo '— PDF'
in_container laravel.test node presentation/build-pdf.mjs
rm -f docs/presentation/.presentation-print.html
