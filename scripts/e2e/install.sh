#!/usr/bin/env bash
#
# Outillage e2e (C11.3) — installation one-shot / après rebuild du conteneur.
#
# 1. Dépendances npm racine (dont `playwright`, requis par pest-plugin-browser)
# 2. Dépendances pnpm de la SPA (dont `@playwright/test`)
# 3. Binaires Chromium → .playwright-browsers/ (gitignoré, partagé hôte/conteneur)
# 4. Librairies système Chromium DANS le conteneur Sail (root du conteneur —
#    aucun sudo requis côté hôte). À relancer après tout rebuild de l'image
#    Sail (`sail build`), les paquets apt du conteneur ne persistent pas.
#
set -euo pipefail
cd "$(dirname "$0")/../.."

echo '— npm install (racine : CLI playwright pour pest-plugin-browser)'
npm install --no-audit --no-fund

echo '— pnpm install (portal-spa : @playwright/test)'
(cd portal-spa && pnpm install)

echo '— Téléchargement Chromium → .playwright-browsers/'
PLAYWRIGHT_BROWSERS_PATH="$PWD/.playwright-browsers" ./node_modules/.bin/playwright install chromium

echo '— Librairies système Chromium dans le conteneur Sail (root conteneur)'
docker compose exec -T -u root laravel.test bash -c \
    'cd /var/www/html && ./node_modules/.bin/playwright install-deps chromium'

echo 'OK — outillage e2e prêt. Suites : scripts/e2e/admin.sh et scripts/e2e/spa.sh'
