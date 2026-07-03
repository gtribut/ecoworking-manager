#!/usr/bin/env bash
#
# Serveur Laravel pour la suite e2e SPA — lancé automatiquement par Playwright
# (webServer, cf. playwright.config.ts). Migre + seed la base DÉDIÉE `e2e`
# puis sert l'app (SPA buildée dans public/portal/ + catch-all).
#
# DB_DATABASE est forcé ICI MÊME (défense en profondeur) : ce script ne peut
# pas toucher la base de dev, quel que soit le contenu du .env.
#
set -euo pipefail
cd "$(dirname "$0")/../.."

E2E_PORT="${E2E_PORT:-8091}"

export APP_ENV=e2e
export APP_URL="http://127.0.0.1:${E2E_PORT}"
# Domaines vides → panel admin sur /admin, portail + API sans contrainte de
# domaine (même montage que phpunit.xml, ADR-0004).
export ADMIN_DOMAIN=
export PORTAL_DOMAIN=
export DB_DATABASE=e2e
# Jobs synchrones : les emails (magic link) partent immédiatement vers Mailpit.
export QUEUE_CONNECTION=sync
export MAIL_MAILER=smtp
export MAIL_HOST="${E2E_MAIL_HOST:-mailpit}"
export MAIL_PORT="${E2E_MAIL_PORT:-1025}"
# Sanctum SPA (cookies) : l'origine du navigateur de test doit être stateful.
export SANCTUM_STATEFUL_DOMAINS="127.0.0.1:${E2E_PORT},localhost:${E2E_PORT}"
export PULSE_ENABLED=false
# Cache en mémoire par requête : neutralise les rate limiters (throttle login
# Fortify 5/min par email+IP — la suite ferait 429 en re-loguant le même
# compte). Le throttling n'est pas un comportement testé par cette suite.
export CACHE_STORE=array

php artisan migrate:fresh --force --seed --seeder='Database\Seeders\E2eSeeder'

# PAS `artisan serve` : ServeCommand ne transmet au vrai process serveur
# qu'une liste blanche de variables (APP_ENV, PATH, XDEBUG_*…) — tous les
# overrides ci-dessus (DB_DATABASE=e2e, domaines vides…) seraient perdus et
# le serveur repartirait sur le .env de dev. Le serveur PHP natif + le
# routeur server.php de Laravel héritent, eux, de TOUT l'environnement.
export PHP_CLI_SERVER_WORKERS=4
cd public
exec php -S "127.0.0.1:${E2E_PORT}" \
    ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
