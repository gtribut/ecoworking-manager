# Ecoworking

> Outil de gestion sur-mesure pour l'espace de coworking Ecoworking (Lyon).
> Remplace Cosoft. Mono-tenant. Laravel 13 + Filament 5 + SPA React.

---

## 📚 Documentation

Avant de coder ou de demander à Claude Code de coder, lire dans l'ordre :

1. [`CLAUDE.md`](./CLAUDE.md) — contexte permanent (lu par Claude Code en début de session)
2. [`docs/BRIEF.md`](./docs/BRIEF.md) — brief technique et fonctionnel complet
3. [`docs/PRD.md`](./docs/PRD.md) — spec fonctionnelle détaillée (portail client + back-office admin)
4. [`docs/data_model.md`](./docs/data_model.md) — modèle de données *(à produire)*
5. [`docs/adr/`](./docs/adr/) — décisions architecturales

---

## 🚀 Quick start (env déjà configuré)

Prérequis : WSL2 + Docker Desktop + Node 26 + Composer (cf. [Setup initial Windows 11](#-setup-initial-windows-11) si premier run).

```bash
# Cloner et entrer dans le projet
git clone git@github.com:<user>/ecoworking.git
cd ecoworking

# Installer les dépendances
composer install
(cd portal-spa && pnpm install)

# Initialiser l'env
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed

# Lancer le dev server SPA (dans un autre terminal)
(cd portal-spa && pnpm dev)
```

Accès :
- **Admin Filament** : http://admin.ecoworking.test (cf. `/etc/hosts` ci-dessous)
- **Portail SPA** : http://portail.ecoworking.test
- **Mailpit** (catcheur emails dev) : http://localhost:8025

---

## 🛠 Setup initial Windows 11

À faire **une seule fois**.

### 1. WSL2 + Ubuntu 24.04

```powershell
# PowerShell admin (Windows)
wsl --install -d Ubuntu-24.04
```

Configurer `~/.wslconfig` (côté Windows, dans `C:\Users\<user>\.wslconfig`) :

```ini
[wsl2]
memory=8GB
processors=4
swap=2GB
```

### 2. Docker Desktop

- Télécharger : https://www.docker.com/products/docker-desktop
- Settings → General → cocher "Use the WSL 2 based engine"
- Settings → Resources → WSL Integration → activer pour Ubuntu-24.04

### 3. Outils dans WSL2 (Ubuntu)

```bash
# Bases
sudo apt update && sudo apt upgrade -y
sudo apt install -y curl wget git unzip zip build-essential

# PHP 8.5 (utilisé hors Sail occasionnellement)
sudo add-apt-repository ppa:ondrej/php
sudo apt update
sudo apt install -y php8.5-cli php8.5-mbstring php8.5-xml php8.5-curl \
    php8.5-pgsql php8.5-gd php8.5-zip

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Node 26 via fnm
curl -fsSL https://fnm.vercel.app/install | bash
exec $SHELL
fnm install 26 && fnm default 26

# pnpm
npm install -g pnpm

# GitHub CLI
type -p curl >/dev/null || sudo apt install curl -y
curl -fsSL https://cli.github.com/packages/githubcli-archive-keyring.gpg \
  | sudo dd of=/usr/share/keyrings/githubcli-archive-keyring.gpg
echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/githubcli-archive-keyring.gpg] \
  https://cli.github.com/packages stable main" \
  | sudo tee /etc/apt/sources.list.d/github-cli.list > /dev/null
sudo apt update && sudo apt install gh -y
gh auth login

# Clever Cloud CLI (pour deploy)
curl -sS https://clever-tools.clever-cloud.com/releases/latest/clever-tools-latest_linux.tar.gz | tar -xz
sudo mv clever-tools-latest_linux/clever /usr/local/bin/
clever login

# Utilitaires
sudo apt install -y httpie jq
```

### 4. Config Git

```bash
git config --global user.name "Guillaume TRIBUT"
git config --global user.email "g.tribut@gmail.com"
git config --global init.defaultBranch main
git config --global pull.rebase true
git config --global core.autocrlf input
git config --global core.editor "code --wait"
```

### 5. Clé SSH GitHub

```bash
ssh-keygen -t ed25519 -C "<ton-email>"
eval "$(ssh-agent -s)"
ssh-add ~/.ssh/id_ed25519
cat ~/.ssh/id_ed25519.pub
# → coller sur https://github.com/settings/keys
```

### 6. VS Code + extensions

Sur **Windows**, installer VS Code : https://code.visualstudio.com/

Extensions essentielles :
- `ms-vscode-remote.remote-wsl` — Remote WSL (indispensable)
- `bmewburn.vscode-intelephense-client` — Intelephense PHP
- `open-southeners.laravel-pint` — Laravel Pint
- `biomejs.biome` — Biome lint/format
- `bradlc.vscode-tailwindcss` — Tailwind IntelliSense
- `eamodio.gitlens` — GitLens
- `usernamehw.errorlens` — Error Lens
- `github.vscode-pull-request-github` — GitHub PR
- `ms-azuretools.vscode-docker` — Docker

Ouvrir le projet via : `code .` depuis WSL2 dans le dossier projet.

### 7. Fichier hosts pour les sous-domaines locaux

Éditer `C:\Windows\System32\drivers\etc\hosts` (avec un éditeur en admin) et ajouter :

```
127.0.0.1   admin.ecoworking.test
127.0.0.1   portail.ecoworking.test
```

> ⚠️ `.test` est un TLD réservé garanti non-routable, à préférer à `.local` ou `.dev` (qui ont des effets de bord HSTS).

### 8. Premier run du projet

```bash
# Dans WSL2, dossier projet
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
```

Tester :
```bash
curl http://admin.ecoworking.test
curl http://portail.ecoworking.test
```

### 9. Setup Clever Cloud (hébergement prod)

> Tu peux faire ce setup **plus tard**, quand tu as une première version qui tourne en local et que tu veux la voir en ligne. Pour démarrer le dev, le setup local de l'étape 8 suffit.

**Récap de ce qu'on veut chez Clever Cloud** :
- 1 application PHP/Laravel (servira `admin.ecoworking.fr` ET `portail.ecoworking.fr` via routing sous-domaine)
- 1 add-on **PostgreSQL 16**
- 1 add-on **Cellar** (stockage S3 pour mandats SEPA, photos profil, PDF factures)
- 1 add-on **FS Bucket** (volume disque pour les écritures Laravel `storage/`)

**Coût estimé : ~30€/mois**

#### Étape 9.1 — Créer le compte

1. Aller sur https://www.clever-cloud.com/ → "Sign in" → choisir l'inscription par email
2. Valider l'email reçu
3. Renseigner les infos d'organisation :
   - **Nom de l'organisation** : `Ecoworking` (créera un namespace propre, séparé de tes éventuels autres projets perso)
   - **Pays** : France
   - **Type** : Personnel ou Entreprise selon ta facturation
4. Ajouter un moyen de paiement (CB, prélèvement SEPA). Pas de paiement avant la fin du premier mois — tu peux explorer/configurer sans débourser

#### Étape 9.2 — Installer le CLI `clever-tools`

Depuis ton terminal WSL :

```bash
# Installation globale via npm
npm install -g clever-tools

# Vérifier l'installation
clever --version

# Lien avec ton compte
clever login
# Ouvre un navigateur, valide → te donne un token sauvegardé localement
```

Le CLI permet de tout faire en ligne de commande, alternative à la console web.

#### Étape 9.3 — Créer les add-ons (base de données + stockage)

À faire **avant** l'application Laravel, parce que l'app va avoir besoin de leurs credentials.

```bash
# PostgreSQL 16 — plan XS suffisant pour démarrer (~7€/mois, 512 MB RAM, 10 Go stockage)
clever addon create postgresql-addon ecoworking-pg \
    --plan xs_sml \
    --region par \
    --version 16

# Cellar (stockage S3-compatible — pour mandats SEPA PDF, photos profil, etc.)
# Gratuit jusqu'à 25 Go, ensuite ~1€/100Go/mois
clever addon create cellar-addon ecoworking-cellar \
    --region par
```

Note : Postgres XS est largement dimensionné pour la volumétrie (cf. BRIEF §3 : ~100 comptes, ~75 entités juridiques, ~800 factures/an). Tu pourras passer en S ou M plus tard si besoin.

**Récupérer les credentials** pour la suite :

```bash
# Postgres
clever addon env ecoworking-pg

# Cellar
clever addon env ecoworking-cellar
```

Ça va afficher des variables comme `POSTGRESQL_ADDON_URI`, `CELLAR_ADDON_HOST`, `CELLAR_ADDON_KEY_ID`, `CELLAR_ADDON_KEY_SECRET`, etc. Garde-les sous le coude.

#### Étape 9.4 — Créer l'application PHP

```bash
# Depuis ton dossier projet local (le repo Laravel cloné)
clever create --type php ecoworking-app --region par --org "Ecoworking"
```

Cette commande :
- Crée l'app sur Clever Cloud
- Ajoute un git remote nommé `clever` sur ton repo local
- Te permet de déployer avec `git push clever main`

Lier les add-ons à l'app :

```bash
clever service link-addon ecoworking-pg
clever service link-addon ecoworking-cellar
```

À partir de là, les variables des add-ons sont **automatiquement injectées** dans l'environnement de l'app au démarrage. Pas besoin de copier-coller des credentials.

#### Étape 9.5 — Configurer l'app

Variables d'environnement de l'application (à fixer une par une) :

```bash
# Versions runtime
clever env set CC_PHP_VERSION 8.5
clever env set CC_NODE_VERSION 26

# Application Laravel
clever env set APP_ENV production
clever env set APP_DEBUG false
clever env set APP_KEY "base64:..."  # Généré par php artisan key:generate puis copié
clever env set APP_URL https://admin.ecoworking.fr  # primaire, l'autre via Route::domain

# Base de données (les ADDON_URI sont injectés auto, mais Laravel attend DB_*)
clever env set DB_CONNECTION pgsql
clever env set DB_HOST '$POSTGRESQL_ADDON_HOST'
clever env set DB_PORT '$POSTGRESQL_ADDON_PORT'
clever env set DB_DATABASE '$POSTGRESQL_ADDON_DB'
clever env set DB_USERNAME '$POSTGRESQL_ADDON_USER'
clever env set DB_PASSWORD '$POSTGRESQL_ADDON_PASSWORD'

# Cache/sessions/queues sur Postgres (cf. ADR-0007 : pas de Redis en MVP)
clever env set CACHE_DRIVER database
clever env set SESSION_DRIVER database
clever env set QUEUE_CONNECTION database

# Sanctum SPA (cf. ADR-0003)
clever env set SANCTUM_STATEFUL_DOMAINS "admin.ecoworking.fr,portail.ecoworking.fr"
clever env set SESSION_DOMAIN .ecoworking.fr

# Cellar (storage S3)
clever env set FILESYSTEM_DISK cellar
clever env set AWS_ACCESS_KEY_ID '$CELLAR_ADDON_KEY_ID'
clever env set AWS_SECRET_ACCESS_KEY '$CELLAR_ADDON_KEY_SECRET'
clever env set AWS_DEFAULT_REGION par
clever env set AWS_BUCKET ecoworking-storage  # à créer dans la console Cellar
clever env set AWS_ENDPOINT '$CELLAR_ADDON_HOST'

# Build/post-deploy
clever env set CC_POST_BUILD_HOOK "./bin/post-build.sh"
clever env set CC_RUN_COMMAND "php artisan migrate --force && php-fpm"
```

Note : les `'$POSTGRESQL_ADDON_HOST'` avec quotes simples sont des **références** aux variables injectées par l'add-on — Clever Cloud les résout au runtime. Ne pas mettre la vraie valeur en dur.

**Créer le bucket Cellar** (côté console web) :
1. Console Clever Cloud → Add-ons → `ecoworking-cellar`
2. Onglet "Buckets" → "New bucket" → nom : `ecoworking-storage`, ACL : `private`

#### Étape 9.6 — Configurer les sous-domaines

Toujours dans la console Clever Cloud sur ton app `ecoworking-app` :

1. Onglet "Domain names" → "Add domain name"
2. Ajouter `admin.ecoworking.fr` (laisser le toggle "Show as primary" activé)
3. Ajouter `portail.ecoworking.fr`
4. Pour les deux : Clever Cloud te donne un nom DNS du type `app_xxxxx.cleverapps.io` ou similaire

**Côté DNS Ecoworking** (chez ton registrar du domaine `ecoworking.fr`) :

```
admin.ecoworking.fr     CNAME    app_xxxxx.cleverapps.io.
portail.ecoworking.fr   CNAME    app_xxxxx.cleverapps.io.
```

Attention : ne **pas** toucher l'apex `ecoworking.fr` (c'est ton site marketing existant, hors scope).

Le certif Let's Encrypt est généré **automatiquement** par Clever Cloud sous quelques minutes après que les CNAMEs sont propagés (vérifier avec `dig admin.ecoworking.fr`).

#### Étape 9.7 — Premier déploiement

```bash
# Depuis le repo local, après commit propre sur main
git push clever main
```

Clever Cloud va :
1. Cloner le code
2. Installer les deps PHP (composer install)
3. Installer les deps Node + build assets (npm ci + npm run build) si script présent
4. Lancer la `CC_RUN_COMMAND` (qui inclut `php artisan migrate --force`)

Suivre le déploiement en direct :
```bash
clever logs --follow
```

Une fois le déploiement OK (✔ vert dans la console), tester :
```bash
curl -I https://admin.ecoworking.fr
# Doit répondre 200 ou 302 (redirect login Filament)
```

#### Étape 9.8 — Suivi quotidien

```bash
# Logs en direct
clever logs --follow

# Logs des X dernières minutes
clever logs --since 10m

# Status de l'app
clever status

# Restart sans redéployer
clever restart

# Accès SSH pour debug (rare en pratique)
clever ssh
```

Pour les **add-ons**, la console web est plus pratique :
- Console → Add-ons → `ecoworking-pg` → onglet "Metrics" pour voir CPU/mémoire/disque/requêtes
- Console → Add-ons → `ecoworking-pg` → onglet "Logs" pour voir les slow queries

#### Étape 9.9 — Backups & sécurité

- **Postgres backups automatiques** : Clever Cloud fait des snapshots quotidiens automatiquement (7 jours rétention en plan XS). Visible dans console → Add-ons → `ecoworking-pg` → "Backups"
- **Backups manuels avant chaque grosse opération** : bouton "Trigger a backup now" dans la console
- **2FA recommandé** sur le compte Clever Cloud (Profil → Security → Enable 2FA)
- **Restrictions IP optionnelles** sur Postgres si tu veux durcir (mais l'app Clever accède en réseau privé, donc moins critique)

---

## 🧰 Commandes courantes

> Alias recommandé dans `~/.bashrc` :
> `alias sail='[ -f sail ] && sh sail || sh vendor/bin/sail'`

### Cycle dev quotidien

```bash
sail up -d                                  # démarrer les containers
sail artisan migrate                        # appliquer les migrations
sail artisan migrate:fresh --seed           # reset complet DB + seeds
sail tinker                                 # REPL Laravel
sail artisan queue:work                     # démarrer un worker de queues

# Dans portal-spa/, en parallèle
cd portal-spa
pnpm dev                                    # Vite dev server (HMR)
```

### Tests

```bash
sail test                                   # tous les tests Pest
sail test --filter=BookingTest              # un test spécifique
sail test --parallel                        # parallèle (plus rapide)
sail artisan test --coverage                # avec couverture (nécessite Xdebug)

# E2E (Playwright)
cd portal-spa
pnpm e2e                                    # tous les tests e2e
pnpm e2e --ui                               # mode UI interactif
```

### Lint & format

```bash
sail pint                                   # format PHP (modifie)
sail pint --test                            # check PHP (échoue si non-formaté)

cd portal-spa
pnpm biome check                            # lint TS/React
pnpm biome format --write                   # format TS/React
pnpm typecheck                              # check TypeScript strict
```

### Génération de code

```bash
sail artisan make:model Booking -mfsr       # model + migration + factory + seeder + request
sail artisan make:filament-resource Booking # resource Filament
sail artisan make:policy BookingPolicy --model=Booking
sail artisan make:request StoreBookingRequest
sail artisan make:job SendInvoiceJob
```

### Maintenance

```bash
sail artisan optimize:clear                 # vider tous les caches
sail composer update                        # mettre à jour les deps PHP
(cd portal-spa && pnpm update)              # mettre à jour les deps TS
sail down                                   # stopper les containers
sail down -v                                # stopper + supprimer volumes (reset DB)
```

---

## 🏗 Architecture (résumé)

```
┌─────────────────────────────────────────────┐
│ Clever Cloud (FR-PAR)                       │
│  ┌───────────────────────────────────────┐  │
│  │ Laravel 13 + PHP 8.5                  │  │
│  │  admin.ecoworking.fr  → Filament 5   │  │
│  │  portail.ecoworking.fr → SPA + /api/* │  │
│  └───────────────────────────────────────┘  │
│        │                            │            │
│  ┌─────▼────┐                  ┌─────▼────┐       │
│  │ Postgres │                  │  Cellar  │       │
│  │   16     │                  │   (S3)   │       │
│  │  +cache  │                  │          │       │
│  │  +sess.  │                  │          │       │
│  │  +queues │                  │          │       │
│  └──────────┘                  └──────────┘       │
└─────────────────────────────────────────────┘
```

Services externes : Brevo (email), Sentry (errors), Better Stack (uptime), Healthchecks.io (cron), Google Calendar API (sync salles).

Détails complets : voir [`docs/BRIEF.md`](./docs/BRIEF.md).

---

## 🧪 Tests

Stratégie :
- **Pest** pour les tests Feature (HTTP) et Unit
- **Playwright** pour les tests e2e côté SPA portail
- TDD encouragé pour la logique métier critique (facturation, isolation données, réservations)
- Tests d'isolation systématiques : `tests/Feature/AuthorizationTest.php`
- Factories à jour pour toutes les entités

Cible de couverture : pas de pourcentage rigide, mais tout chemin métier critique doit être couvert (facturation, paiement, isolation, réservation conflits).

---

## 🚀 Déploiement

Production : **Clever Cloud** via `git push clever main`.

### Flow
1. PR créée → CI GitHub Actions (lint + tests + build)
2. Review + merge sur `main` → CI re-run
3. Auto-deploy Clever Cloud sur push `main`
4. Release Sentry taguée automatiquement (source maps uploadées)

### Variables d'env prod

Configurer via `clever env set <KEY> <VALUE>` ou la console Clever Cloud.
Liste complète : section 13 du [`BRIEF.md`](./docs/BRIEF.md).

### Domaines

- `admin.ecoworking.fr` → CNAME vers l'app Clever Cloud
- `portail.ecoworking.fr` → CNAME vers la même app
- Les deux avec certif Let's Encrypt auto (Clever Cloud)

---

## 📐 Conventions

### Branches
- `main` : production (protégée, PR + CI verte requise)
- `feature/<nom>` : nouvelles fonctionnalités
- `fix/<nom>` : corrections de bugs
- `refactor/<nom>` : refactos sans changement fonctionnel
- `chore/<nom>` : dépendances, config, tooling

### Commits conventionnels

Format : `<type>(<scope>): <description>`

Types : `feat`, `fix`, `refactor`, `docs`, `test`, `chore`, `style`, `perf`, `ci`

Exemples :
```
feat(booking): ajout sync Google Calendar sur création de résa
fix(invoice): correction race condition sur numérotation
refactor(auth): extraction logique 2FA dans un Service
test(booking): tests isolation user A vs user B
```

### Avant chaque commit

- [ ] `sail test` passe
- [ ] `sail pint --test` propre
- [ ] `pnpm biome check` propre côté SPA
- [ ] `pnpm typecheck` propre côté SPA
- [ ] Pas de `dd()`, `dump()`, `console.log` oubliés
- [ ] Pas de `.env` ou secrets commités

Plus de détails : voir [`CLAUDE.md`](./CLAUDE.md).

---

## 🆘 Troubleshooting

### Sail ne démarre pas
```bash
sail down -v && sail build --no-cache && sail up -d
```

### Erreur "permission denied" sur les fichiers
```bash
# Depuis WSL2
sudo chown -R $USER:$USER .
```

### Performance lente (I/O)
Vérifier que le projet est bien dans `~/projects/` (filesystem Linux) et **pas** dans `/mnt/c/...` (montage Windows). Différence : 5-10× sur les opérations I/O.

### Sub-domaines non résolus en dev
Vérifier `C:\Windows\System32\drivers\etc\hosts` :
```
127.0.0.1   admin.ecoworking.test
127.0.0.1   portail.ecoworking.test
```
Flush DNS Windows : `ipconfig /flushdns` (PowerShell admin).

### CORS errors entre SPA et API en dev
Normalement aucun, puisque même origine (`portail.ecoworking.test`). Si erreur : vérifier `SANCTUM_STATEFUL_DOMAINS` dans `.env`.

### Tests Postgres qui échouent en CI
S'assurer que le service Postgres est bien démarré dans le workflow GitHub Actions. Voir `.github/workflows/ci.yml`.

---

## 📦 Stack

| Couche | Techno |
|---|---|
| Backend | Laravel 13, PHP 8.5+ |
| Admin UI | Filament 5 |
| Portail UI | React 19 + TypeScript + Vite 6 |
| Routing portail | React Router v7 |
| State serveur | TanStack Query |
| UI | Tailwind CSS 4 + shadcn/ui |
| DB | PostgreSQL 16 (cache, sessions et queues inclus) |
| Storage | Cellar (S3-compatible, Clever Cloud) |
| Auth admin | Fortify + 2FA + Socialite Google |
| Auth portail | Sanctum SPA mode |
| Tests PHP | Pest |
| Tests e2e | Playwright |
| Lint PHP | Pint |
| Lint TS | Biome |
| Email | Brevo |
| Errors | Sentry |
| Uptime | Better Stack |
| Cron | Healthchecks.io |
| Hosting | Clever Cloud (FR) |
| CI/CD | GitHub Actions |
| Dev local | Laravel Sail (WSL2 + Docker) |

---

## 📝 Licence

Propriétaire — Ecoworking. Tous droits réservés. Code non destiné à distribution publique.

---

*Maintenu par Guillaume.*
