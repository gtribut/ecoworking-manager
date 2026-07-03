#!/usr/bin/env bash
#
# Suite e2e ADMIN (Filament) — Pest 4 + pest-plugin-browser (ADR-0008).
#
# S'exécute DANS le conteneur Sail (PHP 8.5 + intl) : le plugin démarre un
# serveur HTTP in-process branché sur le kernel de test Laravel, donc la
# suite utilise la base `testing` avec RefreshDatabase — jamais la base de
# dev. Domaines forcés vides (phpunit.e2e.xml) → panel admin servi sur /admin.
#
# Usage : scripts/e2e/admin.sh [args pest] (ex. --filter="login")
#
set -euo pipefail
cd "$(dirname "$0")/../.."

docker compose exec -T -u sail \
    -e PLAYWRIGHT_BROWSERS_PATH=/var/www/html/.playwright-browsers \
    laravel.test php vendor/bin/pest -c phpunit.e2e.xml --colors=always "$@"
