#!/usr/bin/env bash
#
# Serveur Laravel pour les CAPTURES D'ÉCRAN de docs/presentation/ — lancé par
# Playwright (webServer, cf. presentation/playwright.config.ts).
#
# Même montage que e2e/serve.sh (domaines vides → admin sur /admin, portail et
# API sur le même host) mais sur la base DÉDIÉE `presentation`, reseedée avec
# DemoSeeder (jeu de données réaliste, dates relatives à aujourd'hui).
# La base de dev n'est jamais touchée : DB_DATABASE est forcé ici même.
#
set -euo pipefail
cd "$(dirname "$0")/../.."

PRESENTATION_PORT="${PRESENTATION_PORT:-8092}"

export APP_ENV=presentation
export APP_URL="http://127.0.0.1:${PRESENTATION_PORT}"
export ADMIN_DOMAIN=
export PORTAL_DOMAIN=
export DB_DATABASE=presentation
export QUEUE_CONNECTION=sync
export MAIL_MAILER=log
export SANCTUM_STATEFUL_DOMAINS="127.0.0.1:${PRESENTATION_PORT},localhost:${PRESENTATION_PORT}"
export PULSE_ENABLED=false
export CACHE_STORE=array
# Mot de passe admin JETABLE, propre à cette base (jamais celui du .env).
export SEED_ADMIN_PASSWORD="${PRESENTATION_ADMIN_PASSWORD:-presentation-admin}"

php artisan migrate:fresh --force --seed --seeder='Database\Seeders\DemoSeeder'

# 2FA admin obligatoire (AdminPanelProvider) : on enrôle l'admin avec un secret
# TOTP FIXE connu du script de capture (presentation/totp.ts), qui calcule le
# code à la volée au login. Secret jetable, base de démo uniquement.
php artisan tinker --execute='
$admin = App\Models\User::query()->where("email", Database\Seeders\DemoSeeder::ADMIN_EMAIL)->firstOrFail();
$admin->app_authentication_secret = "PRESENTATIONTOTP2345ABCDEFGHIJKL";
$admin->app_authentication_recovery_codes = [];
$admin->save();
echo "Admin 2FA enrôlé (secret fixe de présentation)\n";
'

# Serveur PHP natif (pas `artisan serve` : il ne transmet pas l'environnement).
export PHP_CLI_SERVER_WORKERS=4
cd public
exec php -S "127.0.0.1:${PRESENTATION_PORT}" \
    ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
