# Brief projet — Outil de gestion Ecoworking

> **Statut** : v1 validée — prêt à servir d'input pour un PRD détaillé dans Claude Code.
> **Auteur** : Guillaume (Ecoworking / DOWiNO)
> **Dernière mise à jour** : 2026-06-04

---

## Sommaire

1. [Contexte & objectifs](#1-contexte--objectifs)
2. [Périmètre fonctionnel](#2-périmètre-fonctionnel)
3. [Volumétrie cible](#3-volumétrie-cible)
4. [Décisions structurantes](#4-décisions-structurantes)
5. [Stack technique](#5-stack-technique)
6. [Architecture applicative](#6-architecture-applicative)
7. [Modèle de données — entités principales](#7-modèle-de-données--entités-principales)
8. [Authentification & autorisation](#8-authentification--autorisation)
9. [Facturation électronique (réforme 2026-2027)](#9-facturation-électronique-réforme-2026-2027)
10. [Sync Google Calendar](#10-sync-google-calendar)
11. [Infrastructure & hébergement](#11-infrastructure--hébergement)
12. [CI/CD & flux de déploiement](#12-cicd--flux-de-déploiement)
13. [Environnements](#13-environnements)
14. [Setup dev local sous Windows 11](#14-setup-dev-local-sous-windows-11)
15. [Outils Git & flow](#15-outils-git--flow)
16. [Monitoring & observabilité](#16-monitoring--observabilité)
17. [Sécurité & RGPD](#17-sécurité--rgpd)
18. [Découpage MVP / V1 / V2 / V3](#18-découpage-mvp--v1--v2--v3)
19. [Migration depuis Cosoft](#19-migration-depuis-cosoft)
20. [Conventions de code](#20-conventions-de-code)
21. [Coûts mensuels estimés](#21-coûts-mensuels-estimés)
22. [Annexes — ressources d'apprentissage](#22-annexes--ressources-dapprentissage)

---

## 1. Contexte & objectifs

### Objectif
Remplacer l'outil actuel **Cosoft** (utilisé pour gérer le coworking Ecoworking) par un outil sur-mesure, plus aligné aux besoins métier, maintenable dans le temps, conforme à la réforme française de la facturation électronique, et qui sert de support de montée en compétence sur une stack moderne.

### Pourquoi un développement sur-mesure
- Insatisfaction des outils du marché (Cosoft actuel, Cobot précédemment)
- Autonomie et souveraineté technique
- Maîtrise des coûts long terme (vs SaaS récurrent)
- Apprentissage stratégique pour le porteur du projet
- Cohérence avec l'ethos Ecoworking (SCOP, soutien à l'écosystème indépendant français)

### Profil porteur du projet
- 1 dev (Guillaume), en marge de son activité principale chez DOWiNO
- Stack actuelle maîtrisée : PHP/MySQL vanilla, JS/TS avec Vue.js, HTML/CSS
- Volonté de monter en compétence sur : Laravel 13 moderne, React, PostgreSQL, CI-CD, Docker, Claude Code, stack moderne globalement

### Horizon temporel
- Dev en soir/weekend
- Cible MVP utilisable : ~6 mois
- Cible production réelle : ~7-8 mois
- Pas de pression bloquante avant septembre 2027 (obligation d'émission Factur-X pour TPE/PME)

---

## 2. Périmètre fonctionnel (résumé)

> **Détail complet** : voir [`docs/PRD.md`](./PRD.md) — Product Requirements Document avec spec fonctionnelle exhaustive (portail client + back-office admin).

### Modules en MVP

- **Gestion client** : membres, entités juridiques (entreprises), contacts
- **Catalogue & abonnements** : offres mensuelles + ponctuelles (tickets nomades + packs) + service de domiciliation juridique (abonnement d'entité)
- **Facturation** : génération PDF, numérotation chronologique, statuts manuels (Factur-X en V2 avant sept 2027)
- **Réservation de ressources** : salles de réunion + postes nomades, sync Google Calendar sortante
- **Annonces & événements** : push admin, inscription optionnelle
- **Portail membre** : profil, factures, réservations, annuaire des coworkers, documents à valider
- **Plan interactif des étages** : annuaire visuel des coworkers par bureau
- **Documents** : internes (charte, CGU, droit image) + administratifs (contrats par entité juridique)
- **Administration Ecoworking** : back-office complet via Filament + audit log

### Non-objectifs (hors scope explicitement)

- Multi-tenancy (un seul coworking : Ecoworking, à Lyon)
- Application mobile native (PWA suffit)
- Paiement en ligne en MVP (Stripe Cashier en V2 si décidé)
- Contrôle d'accès physique (badges, serrures connectées) — V3 si besoin
- Wifi captive portal — V3 si besoin
- CRM commercial évolué (pipeline, opportunités) — non pertinent
- Module RH — non pertinent
- Comptabilité complète (déléguée à l'expert-comptable + Pennylane/Tiime/etc.)

---

## 3. Volumétrie cible

| Métrique | Valeur actuelle | Cible 24 mois |
|---|---|---|
| Résidents (abonnement résident, bureau attitré) | 40-50 | 60-80 |
| Additionals (personnes sup. d'un abo entreprise) | ~5-10 | ~10-15 |
| Externals en BDD (compte créé) | ~50 | ~80-100 |
| Externals **actifs** (avec consommation mensuelle) | ~10-15 | ~15-25 |
| Total comptes portail membres | ~95-110 | ~150-200 |
| Entreprises / entités juridiques | ~75 | ~120 |
| Bureaux (desks) | **48 total** (1-2 staff + ~40-50 résidents + reste libre pour external) | identique |
| Salles de réunion | 3 | 3 |
| Salle event | 1 | 1 |
| Tickets consommés / mois (bureaux + salles, externals) | **10-30** | doubler |
| Tickets consommés / an | ~200-400 | ~500-800 |
| Factures émises / an | ~600-800 | ~1200 |
| Trafic web concurrent | Très faible (admin solo + 30-50 connexions/jour membres) | Idem |

### Répartition du chiffre d'affaires

| Source | % du CA |
|---|---|
| Résidents (abonnements mensuels) | ~90% |
| Additionals (intégrés dans abos entreprise) | inclus dans % résidents |
| Externals (tickets ponctuels + résa salles payantes) | 5-10% max |
| Salle event (résa admin) | marginal |

**Conclusion** : volumétrie **très faible** au global, et **CA très concentré sur les résidents**. L'infra XS Clever Cloud + Postgres S est largement dimensionnée.

> **Implication produit** : prioriser l'UX du **flow résident** (90% du CA). Le flow external doit être fonctionnellement complet mais ne mérite pas un sur-investissement UX.

---

## 4. Décisions structurantes

| Décision | Choix | Justification |
|---|---|---|
| Architecture globale | Build sur-mesure | Insatisfaction outils marché, autonomie, apprentissage |
| Multi-tenancy | Non, mono-tenant | Un seul coworking, mono-tenant simplifie tout |
| Backend | **Laravel 13 + PHP 8.5+** | Vélocité, écosystème mature, syntaxe PHP familière, Filament |
| Admin UI | **Filament 5** | Gain massif (2-3 mois) sur l'admin CRUD-heavy |
| Portail membre | **SPA React + TS + Vite + Sanctum SPA mode** | Vraie expérience React moderne + auth propre cookies |
| URLs | **`admin.ecoworking.fr` + `portail.ecoworking.fr`** sur un seul déploiement | Isolation cookies (sécurité++), DNS clair, routing Laravel par sous-domaine |
| Repo | Monorepo Laravel unique (SPA dans `/portal-spa`) | Un seul déploiement, complexité ops minimale |
| Base de données | **PostgreSQL 16** | Standard moderne, riche en features (JSONB, full-text, etc.) |
| ORM | **Eloquent** (Laravel) | Natif Laravel, productivité maximale |
| Hébergement | **Clever Cloud** (FR) | PaaS français RGPD, déploiement git push, Postgres managé |
| Email | **Brevo** (transactionnel) | FR, RGPD, API simple, free tier ~300 mails/jour |
| Stockage S3 | **Cellar** (Clever Cloud) | Aligné PaaS, S3-compatible |
| CI/CD | **GitHub Actions** | Standard, 2000 min/mois gratuit privé, intégration Claude Code |
| Monitoring | **Sentry + Better Stack + Laravel Pulse + Healthchecks.io** | Errors + uptime + perf interne + cron |
| Tests | **Pest v4** (PHP, intègre Playwright pour browser tests) + **Playwright 1.57+** (e2e front SPA isolés) | Modernes, lisibles, alignés bonnes pratiques. Pest 4 permet de piloter Playwright depuis PHP avec helpers Laravel natifs (`visit()`, `inDarkMode()`, `assertNoAccessibilityIssues()`) |
| Lint/format | **Laravel Pint** + **Biome** | Rapides, modernes, zéro config |
| Paiement en ligne | Aucun en MVP, **Cashier Stripe** envisagé V2 | Process actuel manuel acceptable, V2 si besoin |
| Facturation électronique | **Stratégie B** : génération côté app, transmission via PA en V2 | Contrôle données + délégation conformité réseau |
| PWA | Oui (install bureau + cache assets), **pas de push notif** | UX++ sans complexité Web Push |
| Staging | Non, **local + prod uniquement** | Solo dev, scope contrôlé |

---

## 5. Stack technique

### 5.1 Backend Laravel

**Cœur**
- PHP 8.5+ (active support jusqu'à dec 2027)
- Laravel 13 (current stable)
- Composer 2.x

**Packages essentiels**
| Package | Usage |
|---|---|
| `laravel/fortify` | Auth scaffolding (login, register, 2FA TOTP) |
| `laravel/sanctum` | Auth SPA + tokens API |
| `laravel/socialite` | OAuth Google pour admins |
| `filament/filament` ^5 | Panel admin |
| `spatie/laravel-permission` | Rôles & permissions |
| `spatie/laravel-activitylog` | Audit log automatique |
| `spatie/laravel-backup` | Backups DB + storage |
| `spatie/laravel-settings` | Settings typés |
| `spatie/laravel-data` | DTOs typés |
| `intervention/image` | Redimensionnement des images à l'upload (photos profil, couvertures annonces → tailles fixes stockées sur Cellar) |
| `barryvdh/laravel-dompdf` | Génération PDF facture (MVP — retenu, cf. PRD §7.4) |
| `spatie/browsershot` | PDF haute qualité via Chrome headless (V2) |
| `google/apiclient` | Sync Google Calendar |
| `sentry/sentry-laravel` | Monitoring erreurs |
| `laravel/pulse` | Métriques perf interne |
| `laravel/telescope` | Debug local (dev only) |
| `atgp/factur-x` | Génération Factur-X (V2) — à valider lib selon évolution |
| `pestphp/pest` | Tests |
| `pestphp/pest-plugin-laravel` | Helpers Laravel pour Pest |

**Stockage**
- Disk `s3` configuré sur Cellar Clever Cloud
- Disk `public` pour assets locaux dev

**Queues**
- Driver `database` (table `jobs` dans Postgres, géré par Laravel 13 nativement)
- Workers via `php artisan queue:work` (process Clever Cloud dédié en prod)
- Monitoring via Laravel Pulse (intégré) et Telescope (en dev)
- Jobs typés : `SendInvoiceEmailJob`, `SyncBookingToGoogleCalendarJob`, `GenerateMonthlyInvoicesJob`, etc.

### 5.2 Frontend admin — Filament 5

- Filament 5.x avec design system natif
- **Panel servi exclusivement sur `admin.ecoworking.fr`** via `$panel->domain('admin.ecoworking.fr')` dans le Panel Provider
- Path racine `/` (pas de `/admin` puisque déjà isolé par sous-domaine)
- Pas de personnalisation visuelle agressive en MVP (UI standard suffit)
- Resources Filament pour chaque entité (Member, Company, Invoice, etc.)
- Widgets dashboard : occupation salles aujourd'hui, abonnements actifs, factures en retard, CA mois
- Pages custom Filament si besoin (ex. vue "occupation jour")

### 5.3 Frontend portail — SPA React

**Cœur**
- **Vite 8.0+** (build & dev server, Rolldown unifié — 10-30× plus rapide qu'avec Rollup, sorti mars 2026)
- **React 19.2+** + **TypeScript 6.0+** strict
- Hébergée dans `/portal-spa` du repo Laravel
- **Servie exclusivement sur `portail.ecoworking.fr`** via `Route::domain('portail.ecoworking.fr')` dans `routes/web.php`
- Endpoints API `/api/*` sur le même sous-domaine (même-origine → pas de CORS)

**Routing**
- **React Router v7.14+** (mode library, déclaratif)
- ⚠️ Note de migration : depuis v7, le package s'appelle simplement `react-router` (plus `react-router-dom`). Import : `import { ... } from "react-router"`
- Cf. ADR-0006 pour le choix vs TanStack Router

**State serveur & data fetching**
- **TanStack Query 5.100+** (cache, optimistic updates, refetch)
- Axios ou fetch natif pour l'HTTP (axios pratique pour CSRF + intercepteurs)

**UI**
- **Tailwind CSS v4.3+** (Lightning CSS, plus de PostCSS, plus de `@tailwind` directives → utiliser `@import "tailwindcss"` + bloc `@theme` pour les customs)
- Plugin **`@tailwindcss/vite`** (intégration native avec Vite 8)
- shadcn/ui (composants Radix UI, accessibles par défaut, customisables)
- lucide-react (icônes)
- **Sonner** (toast notifications, shadcn-compatible)
- Composants natifs shadcn/ui : `Skeleton` (loading states), `Dialog`, `Form`, etc.

**Accessibilité (RGAA cible)**
- **Biome v2.4+** (lint + format unifié, Rust-based, remplace ESLint + Prettier) avec règles a11y équivalent jsx-a11y strict
- `axe-core` pour audits automatisés intégrés aux tests Playwright
- Pa11y / Lighthouse pour audits manuels périodiques
- Cible : conformité **RGAA 4.1 niveau AA** (basé WCAG 2.1 AA)
- Déclaration d'accessibilité à publier sur `/accessibilite`

**Formulaires & validation**
- React Hook Form
- Zod v4+ (schémas validation, partagés idéalement avec backend via génération mais OK manuel pour MVP)
- `@hookform/resolvers/zod`

**Dates & i18n**
- 🟡 **Option moderne** : Temporal API native (disponible par défaut avec Node 26) au lieu de Day.js — précieux pour ce projet (gestion créneaux, prorata, abonnements)
- Fallback Day.js avec locale `fr` si Temporal pose problème côté browsers anciens
- Intl natif pour formats nombres/devises

**PWA**
- `vite-plugin-pwa` (workbox sous le capot)
- Manifest : install bureau, mode standalone, theme color Ecoworking
- Cache strategy : `NetworkFirst` pour API, `CacheFirst` pour assets
- Pas de push notifications (V3+)

**Monitoring**
- `@sentry/react`
- Source maps uploadées en CI

### 5.4 Base de données

- PostgreSQL 16
- Extensions à activer : `citext` (email case-insensitive), `pg_trgm` (recherche fuzzy si besoin), `unaccent` (recherche FR)
- Connexion pooling : laravel sait gérer, sinon `pgbouncer` côté Clever Cloud (managé)

### 5.5 Outillage transverse

| Outil | Usage |
|---|---|
| Sentry | Errors front + back + perf |
| Better Stack | Uptime monitoring externe |
| Healthchecks.io | Surveillance cron jobs |
| Brevo | Email transactionnel |
| ImageKit | ❌ Écarté MVP (réservé V3) — images servies depuis Cellar, redim. via `intervention/image` (cf. PRD §7.4 Q1) |
| Google Cloud Console | Credentials OAuth + Calendar API |

---

## 6. Architecture applicative

### Schéma général

```
                  ┌───────────────────────────┐
                  │  Navigateur (admin/membre)│
                  └───────────────────────────┘
                              │ HTTPS
                              ▼
              ┌────────────────────────────────────┐
              │     Clever Cloud (FR-PAR)          │
              │                                    │
              │  ┌──────────────────────────────┐  │
              │  │ Laravel 13 (PHP 8.5)         │  │
              │  │                              │  │
              │  │  admin.ecoworking.fr         │  │
              │  │    → Filament 5 (panel)     │  │
              │  │  portail.ecoworking.fr       │  │
              │  │    → SPA React + /api/*      │  │
              │  │  workers → queue:work        │  │
              │  └──────────────────────────────┘  │
              │         │                          │
              │   ┌─────▼────┐                     │
              │   │ Postgres │   (cache, sessions, │
              │   │   16     │    queues : tout    │
              │   └──────────┘    sur Postgres)    │
              │              ┌──────────┐          │
              │              │  Cellar  │          │
              │              │   (S3)   │          │
              │              └──────────┘          │
              └────────────────────────────────────┘
                              │
              ┌───────────────┴────────────────┐
              ▼                                ▼
      ┌──────────────┐                ┌─────────────────┐
      │   Brevo      │                │ Google Calendar │
      │  (email)     │                │     API         │
      └──────────────┘                └─────────────────┘

      ┌──────────────┐    ┌──────────────┐    ┌──────────────┐
      │   Sentry     │    │ Better Stack │    │ Healthchecks │
      │ (errors+perf)│    │   (uptime)   │    │    (cron)    │
      └──────────────┘    └──────────────┘    └──────────────┘
```

### Structure repo

```
ecoworking-manager/
├── app/                          # Code Laravel
│   ├── Filament/                 # Resources & pages admin
│   ├── Http/
│   │   ├── Controllers/Api/      # Controllers API (portail)
│   │   ├── Middleware/
│   │   └── Requests/             # Form Requests (validation)
│   ├── Models/
│   ├── Policies/
│   ├── Services/                 # Logique métier réutilisable
│   ├── Jobs/                     # Jobs queue
│   ├── Mail/
│   └── Notifications/
├── bootstrap/
├── config/
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
├── portal-spa/                   # SPA React (projet Vite séparé)
│   ├── src/
│   ├── public/
│   ├── package.json
│   └── vite.config.ts
├── public/
│   └── portal/                   # Build SPA exposé (généré)
├── resources/
│   ├── css/                      # Tailwind admin (Filament)
│   ├── js/                       # Scripts admin Filament
│   └── views/
├── routes/
│   ├── api.php                   # Routes /api/* (portail SPA)
│   ├── web.php                   # Routes web (admin redirect)
│   └── console.php
├── storage/
├── tests/
│   ├── Feature/
│   ├── Unit/
│   └── e2e/                      # Playwright tests
├── .env.example
├── composer.json
├── package.json                  # Pour admin Filament uniquement
├── pint.json
├── biome.json
├── docker-compose.yml            # Sail-based
└── README.md
```

### Routing par sous-domaine

L'app Laravel est déployée **une seule fois sur Clever Cloud**, mais répond à deux sous-domaines pointés sur la même instance.

| Sous-domaine | Sert | Auth |
|---|---|---|
| `admin.ecoworking.fr` | Panel Filament 5 (UI admin) | Session Laravel classique + Fortify + 2FA + Google OAuth optionnel |
| `portail.ecoworking.fr` | SPA React + endpoints `/api/*` (même origine) | Sanctum mode SPA (cookies + CSRF) |

**Implémentation Laravel** :

```php
// Filament Panel Provider : restreindre le panel au sous-domaine admin
$panel
    ->id('admin')
    ->path('') // racine, pas /admin
    ->domain('admin.ecoworking.fr')
    ->...
```

```php
// routes/web.php : SPA portail sur portail.ecoworking.fr
Route::domain('portail.ecoworking.fr')->group(function () {
    Route::get('/{any?}', fn () => view('portal-spa'))
        ->where('any', '^(?!api/).*$');
});

// routes/api.php : API sur le même sous-domaine portail
Route::domain('portail.ecoworking.fr')->middleware('auth:sanctum')->group(function () {
    // ... endpoints API
});
```

**Isolation cookies** (bénéfice sécurité) :
- Cookies admin scopés à `admin.ecoworking.fr` (pas de fuite vers le portail)
- Cookies portail scopés à `portail.ecoworking.fr` (pas de fuite vers l'admin)
- Pas de cookie partagé sur `.ecoworking.fr`
- Implémentation : middleware `SetCookieDomain` qui ajuste `config('session.domain')` selon le Host

**Sanctum** : `SANCTUM_STATEFUL_DOMAINS=portail.ecoworking.fr` (seul le portail consomme l'API authentifiée par cookie).

### API REST conventions

- Toutes les routes portail sous `/api/` sur `portail.ecoworking.fr` (même origine que la SPA → pas de CORS)
- Protégées par middleware `auth:sanctum`
- Verbes REST : `GET`, `POST`, `PATCH`, `DELETE`
- Ressources : pluriel (`/api/bookings`, `/api/invoices`)
- Réponses JSON via Eloquent API Resources
- Pagination : `?page=X&per_page=Y`, format standard Laravel
- Filtres : `?filter[status]=active`, package `spatie/laravel-query-builder` recommandé
- Erreurs : codes HTTP standards + body `{ message, errors: { field: [msgs] } }` (format Laravel natif)
- Versionning : pas de v1/v2 en MVP (mono-client, on évoluera en cassant si besoin)

### Build & serving SPA

- Vite build → output dans `public/portal/` (ou directement servi depuis `portal-spa/dist` selon config)
- Une view Blade minimale `portal-spa.blade.php` charge le manifest Vite et inclut `<div id="app">`
- Assets statiques servis par Caddy/Nginx Clever Cloud (cache long terme via hash de fichier)
- En dev : Vite dev server sur `:5173` (HMR), Laravel sur `:80` (Sail), proxy Vite `/api → http://localhost:80/api`
- En dev, on simule les sous-domaines via `/etc/hosts` Windows + WSL2 : `127.0.0.1 admin.ecoworking.test` et `127.0.0.1 portail.ecoworking.test`

---

## 7. Modèle de données — entités principales

> **Détail complet** : à produire dans [`docs/data_model.md`](./data_model.md) (Guillaume travaille le schéma macro en parallèle).
> **Spec fonctionnelle** des entités côté usage : voir [`docs/PRD.md`](./PRD.md).

Inventaire des tables prévues (vue technique pour l'infra et les migrations) :

| Table | Description rapide |
|---|---|
| `users` | Auth pour tous (admins + membres + externes) |
| `member_profiles` | 1-1 avec users, données spécifiques membre (incl. `desk_id` pour plan étages) |
| `companies` | Entités juridiques avec **champ `entity_type`** : `company` (SIRET) ou `individual` (particulier sans SIRET) |
| `contacts` | Personnes liées aux entreprises (facturation, etc.) |
| `offers` | Catalogue (abos + tickets + packs) |
| `subscriptions` | Abonnements actifs/historiques (membres + domiciliation d'entité ; souscripteur polymorphe User/Company) |
| `purchases` | Achats ponctuels (tickets, packs) |
| `resources` | Salles + bureaux (bookable). Type : `desk` (`assigned_resident` / `assigned_staff` / `unassigned`) / `meeting_room` (3 unités) / `event_room` (1 unité, admin only) |
| `bookings` | Réservations de salles (meeting_room + event_room uniquement) |
| `desk_occupations` | Occupations effectives des bureaux par demi-journée (champ `period` : morning/afternoon/full_day ; sources : `resident_default`, `external_ticket`) |
| `desk_absences` | Déclarations d'absence des résidents/staff sur leur bureau (date / plage / récurrence) |
| `tickets` | Tickets achetés (types : desk_half_day, meeting_room_half_day ; le créneau matin/après-midi est choisi à la réservation, pas au type) |
| `invoices` | Factures émises |
| `invoice_lines` | Lignes de factures |
| `payments` | Encaissements (statuts manuels) |
| `announcements` | Posts & events admin |
| `announcement_registrations` | Inscriptions aux events |
| `internal_documents` | Documents internes (charte, CGU, droit image) avec versionning |
| `member_document_validations` | Suivi des validations utilisateurs |
| `administrative_documents` | Contrats, avenants, contrats de domiciliation par entité |
| `consents` | Consentements RGPD |
| `activity_log` | Audit log (spatie) |
| `roles`, `permissions`, ... | Tables spatie/permission |
| Tables Laravel natives | sessions, cache, jobs, failed_jobs, password_resets, personal_access_tokens |

Relations clés (vue d'ensemble) :
- User 1-1 MemberProfile (optionnel selon rôle)
- MemberProfile n-1 Company (optionnel)
- MemberProfile n-1 Resource (`desk_id`, optionnel — bureau attribué pour plan étages)
- Subscription : **souscripteur polymorphe** (User pour les abos membres, Company pour la domiciliation d'entité) + billable polymorphe (User ou Company)
- Booking n-1 Resource, n-1 User, polymorphique billable
- Invoice 1-n InvoiceLine, 1-n Payment, polymorphique billable
- InvoiceLine polymorphique related (Subscription, Purchase, Booking, null)
- InternalDocument 1-n MemberDocumentValidation (n-1 User)

---

## 8. Authentification & autorisation

### Auth admin (Filament)

- Login email + password via Fortify
- 2FA TOTP obligatoire (Fortify built-in)
- Device memory 30 jours (cookie signé)
- OAuth Google additionnel via Socialite (si admin a un compte Google d'entreprise)
- Sessions Laravel classiques (cookies)
- Logout détruit la session

### Auth portail membre (SPA React)

- **Sanctum mode SPA** : sessions cookies + CSRF automatique
- Flow :
  1. SPA fait `GET /sanctum/csrf-cookie` pour récupérer le XSRF-TOKEN
  2. SPA fait `POST /login` avec email/password (route Fortify)
  3. Cookie de session HttpOnly est posé
  4. Toutes les requêtes `/api/*` envoient automatiquement le cookie
  5. Middleware `auth:sanctum` valide
- **2FA optionnel** pour les membres (recommandé mais non-bloquant)
- Magic link email envisagé pour V1.5 (UX++)
- Reset password standard Fortify

### Autorisation

- Rôles via `spatie/laravel-permission` : `admin`, `resident`, `additional`, `external`, `billing_contact`. Détail des cas de cumul et exclusivités : voir [`docs/PRD.md`](./PRD.md) section 2.
- Permissions granulaires si besoin futur (V2+)
- Policies Eloquent pour chaque modèle critique (Invoice, Booking, etc.)
- Filament respecte les Policies via `canView()`, `canEdit()`, etc. sur les Resources

### Sécurité auth additionnelle

- Rate limiting Fortify activé (5 tentatives / 60s)
- Hash Argon2id préféré à bcrypt si dispo (config Laravel)
- Audit log automatique des login/logout via Fortify events + spatie/activitylog

---

## 9. Facturation électronique (réforme 2026-2027)

### Calendrier réel applicable à Ecoworking (TPE/PME)

- **1er septembre 2026** : obligation de **réception** de factures électroniques structurées (la TPE Ecoworking doit pouvoir recevoir une facture Factur-X de son fournisseur EDF par exemple)
- **1er septembre 2027** : obligation d'**émission** au format Factur-X / UBL / CII pour TPE/PME, via une Plateforme Agréée

### Stratégie retenue : B (génération côté app + PA pour transmission V2)

**MVP (avant septembre 2027)** :
- Génération factures PDF classique (sans XML)
- Numérotation chronologique sans trou : `EW-YYYY-NNNNN` (compteur en DB avec transaction)
- Mentions obligatoires CGI art. 289 complètes
- Nouvelles mentions 2026 préparées dans la donnée même si non utilisées (catégorie opération, option TVA débits, adresse livraison si différente)
- Statuts paiement gérés manuellement par l'admin

**V2 (avant septembre 2027)** :
- Génération Factur-X (PDF/A-3 avec XML CII embedded)
- Lib : à figer (probablement `atgp/factur-x` ou équivalent)
- Choix de la PA : à figer 1er trimestre 2027 avec expert-comptable
- Branchement API PA pour transmission + réception webhook statut
- Réception factures électroniques entrantes (depuis sept 2026 obligation, à confirmer process)

### Tables impactées

- `invoices` : champs `factur_x_xml_path`, `pa_transmission_id`, `pa_transmission_status`, etc.
- `companies` : `siret` obligatoire pour clients FR, `vat_number` si TVA intracom

### Discussion à mener avec expert-comptable

- Choix PA (Pennylane est-elle immatriculée Plateforme Agréée au moment de la bascule ? Sellsy ? Qonto ? Docaposte ? Liste officielle sur impots.gouv.fr à consulter au moment du choix)
- Workflow : facturation 100% dans l'outil custom + push PA, OU facturation déléguée Pennylane et l'outil custom ne sert que de "trigger"
- Archivage légal 10 ans : à anticiper (stockage + accès)

---

## 10. Sync Google Calendar

### Objectif
Permettre la visualisation de l'occupation des salles depuis Google Calendar (lecture seule pour les membres), en dehors de l'outil.

### Architecture
- **DB primary** : table `bookings` = source de vérité
- **Sync sortante uniquement** : push vers Google Calendar à chaque création/modif/suppression
- **Pas de sync entrante** : modifs directes du calendrier Google ignorées (règle : on ne modifie qu'à travers l'app)

### Implémentation
- Calendrier Google dédié "Ecoworking — Salles" créé une fois manuellement
- Service account Google ou OAuth admin → credentials stockés en config
- Jobs Laravel asynchrones via `queue:work` :
  - `SyncBookingToGoogleCalendar` (create/update/delete)
  - Retry 3 fois avec backoff exponentiel
- Stockage `google_calendar_event_id` dans `bookings` pour matching
- Partage public en lecture (lien iCal) ou export public pour intégration site Ecoworking

### Postes nomades
Pas de sync Google Calendar pour les postes nomades (pas pertinent), juste un compteur de disponibilité dans l'app.

---

## 11. Infrastructure & hébergement

### Provider : Clever Cloud (FR)

**Pourquoi Clever Cloud** :
- PaaS français, datacenters Paris, RGPD-natif
- Déploiement `git push clever main` (simplissime)
- Postgres managé + Cellar (S3) dans la même UI
- Pas d'ops système à gérer (OS, certs SSL, mises à jour)
- Support FR si besoin

**Limitations à connaître** :
- **Pas de free tier permanent** (depuis août 2023) : crédits de démarrage uniquement
- Le filesystem n'est pas persistant entre déploiements → tout passe par S3
- Customization Nginx/Caddy limitée vs VPS

### Services à provisionner

| Service | Plan | Coût estimé/mois |
|---|---|---|
| Application instance PHP (XS) | XS Scaler | ~10€ |
| PostgreSQL S | DB S | ~15€ |
| Cellar (S3) | Pay-as-you-go | ~5€ |
| Bandwidth | Inclus | 0€ |
| **Total infra** | | **~30€** |

### DNS & domaines

**Architecture deux sous-domaines, un seul déploiement** :

| Sous-domaine | Type DNS | Cible | Sert |
|---|---|---|---|
| `admin.ecoworking.fr` | CNAME | `<app-id>.cleverapps.io` | Filament admin |
| `portail.ecoworking.fr` | CNAME | `<app-id>.cleverapps.io` | SPA membre + API |

Les deux sous-domaines pointent **vers la même application Clever Cloud**. Le routing par sous-domaine se fait au niveau Laravel (cf. section 6, "Routing par sous-domaine").

**Configuration Clever Cloud** :
- Ajouter les deux sous-domaines comme "Custom domains" dans l'app Clever Cloud
- Certificat SSL Let's Encrypt automatique pour chacun
- HTTPS forcé (redirection 80→443)
- HSTS activé en prod

**Apex `ecoworking.fr`** :
- **Hors scope de ce projet** : site marketing existant, hébergé séparément
- Pas de modification DNS sur l'apex
- Une éventuelle refonte du site marketing fera l'objet d'un projet distinct (potentiellement aligné Teetsh côté stack : Astro + Strapi sur Cloudflare Pages, par exemple)
- Aucune dépendance entre les deux : le projet outil de gestion n'interagit pas avec le site marketing

**Dev local — équivalent** :
- Ajouter dans le fichier hosts Windows (`C:\Windows\System32\drivers\etc\hosts`) :
  ```
  127.0.0.1   admin.ecoworking.test
  127.0.0.1   portail.ecoworking.test
  ```
- Configurer Sail/Nginx pour répondre aux deux hosts
- En dev, certificat self-signed via `mkcert` ou simplement HTTP
- Flush DNS Windows après édition du hosts : `ipconfig /flushdns` (PowerShell admin)

> ⚠️ `.test` est un TLD **réservé** garanti non-routable (RFC 6761), à préférer à `.local` (collisions mDNS) ou `.dev` (forcé en HTTPS via HSTS preload par les navigateurs).

### Phase de dev — pas de cloud nécessaire

Le dev se fait 100% en local (Laravel Sail Docker). Le premier déploiement Clever Cloud peut se faire 4-6 mois après le début du dev, quand il y a une vraie démo à montrer.

Pour une démo intermédiaire avant prod, alternatives gratuites :
- **Tunnel localhost** : `ngrok`, `cloudflared`, ou Laravel Herd `share`
- **Hébergement temporaire** : Fly.io free tier ou Railway crédits gratuits

### Provisioning & premier déploiement Clever Cloud (pas-à-pas CLI)

> Runbook complet pour la **mise en ligne initiale**. À faire **plus tard**, quand une première version tourne en local. Pour démarrer le dev, le setup local (§14) suffit.
>
> Ce qu'on provisionne : 1 application PHP/Laravel (sert `admin.` ET `portail.ecoworking.fr` via routing sous-domaine), 1 add-on **PostgreSQL 16**, 1 add-on **Cellar** (S3 : mandats SEPA, photos profil, PDF factures), 1 add-on **FS Bucket** (volume disque pour les écritures `storage/`). Coût estimé **~30 €/mois** (cf. §21).

#### 11.1 Créer le compte

1. https://www.clever-cloud.com/ → "Sign in" → inscription par email, valider l'email
2. Organisation : nom `Ecoworking` (namespace propre), pays France, type Personnel/Entreprise selon facturation
3. Ajouter un moyen de paiement (CB / SEPA). Pas de débit avant la fin du 1er mois — on peut configurer sans payer

#### 11.2 Installer le CLI `clever-tools`

```bash
npm install -g clever-tools
clever --version
clever login          # ouvre un navigateur, valide → token sauvegardé localement
```

#### 11.3 Créer les add-ons (avant l'app, qui aura besoin de leurs credentials)

```bash
# PostgreSQL 16 — plan XS (~7€/mois, 512 MB RAM, 10 Go)
clever addon create postgresql-addon ecoworking-pg \
    --plan xs_sml --region par --version 16

# Cellar (S3-compatible) — gratuit jusqu'à 25 Go, puis ~1€/100Go/mois
clever addon create cellar-addon ecoworking-cellar --region par
```

Postgres XS est largement dimensionné pour la volumétrie (cf. §3 : ~100 comptes, ~75 entités, ~800 factures/an). Récupérer les credentials :

```bash
clever addon env ecoworking-pg        # POSTGRESQL_ADDON_*
clever addon env ecoworking-cellar    # CELLAR_ADDON_*
```

#### 11.4 Créer l'application PHP

```bash
# Depuis le repo Laravel cloné en local
clever create --type php ecoworking-app --region par --org "Ecoworking"
```

Crée l'app, ajoute un remote git `clever`, et permet de déployer via `git push clever main`. Lier les add-ons :

```bash
clever service link-addon ecoworking-pg
clever service link-addon ecoworking-cellar
```

Les variables des add-ons sont alors **injectées automatiquement** dans l'environnement de l'app (pas de copier-coller de credentials).

#### 11.5 Configurer l'app (variables d'environnement)

```bash
# Versions runtime (cf. ADR-0008)
clever env set CC_PHP_VERSION 8.5
clever env set CC_NODE_VERSION 26

# Application Laravel
clever env set APP_ENV production
clever env set APP_DEBUG false
clever env set APP_KEY "base64:..."                 # via php artisan key:generate
clever env set APP_URL https://portail.ecoworking.fr  # base URL Laravel (liens des emails membres) ; aligné sur §13. L'admin est servi via Route::domain

# Base de données (références aux variables injectées par l'add-on, quotes simples)
clever env set DB_CONNECTION pgsql
clever env set DB_HOST '$POSTGRESQL_ADDON_HOST'
clever env set DB_PORT '$POSTGRESQL_ADDON_PORT'
clever env set DB_DATABASE '$POSTGRESQL_ADDON_DB'
clever env set DB_USERNAME '$POSTGRESQL_ADDON_USER'
clever env set DB_PASSWORD '$POSTGRESQL_ADDON_PASSWORD'

# Cache/sessions/queues sur Postgres (cf. ADR-0007 : pas de Redis en MVP)
clever env set CACHE_STORE database
clever env set SESSION_DRIVER database
clever env set QUEUE_CONNECTION database

# Sanctum SPA + isolation cookies (cf. ADR-0003 / ADR-0004)
clever env set SANCTUM_STATEFUL_DOMAINS "portail.ecoworking.fr"
clever env set SESSION_DOMAIN ""                    # NULL → cookie scopé au host courant

# Cellar (storage S3)
clever env set FILESYSTEM_DISK s3
clever env set AWS_ACCESS_KEY_ID '$CELLAR_ADDON_KEY_ID'
clever env set AWS_SECRET_ACCESS_KEY '$CELLAR_ADDON_KEY_SECRET'
clever env set AWS_DEFAULT_REGION eu-west-1          # aligné sur §13 ; cosmétique pour Cellar (AWS_ENDPOINT fait foi)
clever env set AWS_BUCKET ecoworking-storage        # à créer dans la console Cellar
clever env set AWS_ENDPOINT '$CELLAR_ADDON_HOST'

# Build / run
clever env set CC_POST_BUILD_HOOK "./bin/post-build.sh"
clever env set CC_RUN_COMMAND "php artisan migrate --force && php-fpm"
```

> ⚠️ Les `'$POSTGRESQL_ADDON_HOST'` (quotes simples) sont des **références** résolues au runtime par Clever Cloud — ne pas mettre la valeur en dur.

> **À compléter** : le bloc ci-dessus ne couvre que les variables liées aux add-ons + runtime. Il faut **aussi** positionner les variables applicatives restantes documentées en **§13** : `APP_NAME`, `APP_TIMEZONE`, `APP_LOCALE`, `ADMIN_DOMAIN` / `PORTAL_DOMAIN` / `FILAMENT_DOMAIN`, `SESSION_LIFETIME`, mail prod (`MAIL_*` Brevo / `BREVO_API_KEY`), `SENTRY_LARAVEL_DSN` + `SENTRY_TRACES_SAMPLE_RATE`, et les `GOOGLE_*` (OAuth admin + Calendar). Les `CC_*` (`CC_PHP_VERSION`, `CC_NODE_VERSION`, `CC_POST_BUILD_HOOK`, `CC_RUN_COMMAND`) sont **spécifiques Clever Cloud** et n'ont donc pas leur place dans `.env.example`.

**Créer le bucket Cellar** (console web) : Add-ons → `ecoworking-cellar` → onglet "Buckets" → "New bucket" → nom `ecoworking-storage`, ACL `private`.

#### 11.6 Configurer les sous-domaines

Console Clever Cloud → app `ecoworking-app` → "Domain names" → "Add domain name" : ajouter `portail.ecoworking.fr` (toggle "primary", cohérent avec `APP_URL`) puis `admin.ecoworking.fr`. Clever fournit une cible DNS du type `app_xxxxx.cleverapps.io`. Le "primary domain" Clever (canonique du vhost) et `APP_URL` (base de génération des liens Laravel) sont deux notions distinctes mais qu'on garde alignées sur le portail.

Côté DNS du registrar `ecoworking.fr` :

```
admin.ecoworking.fr     CNAME    app_xxxxx.cleverapps.io.
portail.ecoworking.fr   CNAME    app_xxxxx.cleverapps.io.
```

⚠️ Ne **pas** toucher l'apex `ecoworking.fr` (site marketing existant, hors scope). Le certificat Let's Encrypt est généré automatiquement quelques minutes après propagation des CNAMEs (vérifier `dig admin.ecoworking.fr`).

#### 11.7 Premier déploiement

```bash
git push clever main           # depuis le repo local, après commit propre sur main
clever logs --follow           # suivre le build/deploy en direct
```

Clever clone le code, `composer install`, build des assets Node si script présent, puis lance `CC_RUN_COMMAND` (incluant `php artisan migrate --force`). Une fois ✔ vert :

```bash
curl -I https://admin.ecoworking.fr     # 200 ou 302 (redirect login Filament)
```

#### 11.8 Suivi quotidien

```bash
clever logs --follow           # logs live
clever logs --since 10m        # X dernières minutes
clever status                  # état de l'app
clever restart                 # restart sans redéployer
clever ssh                     # accès SSH debug (rare)
```

Pour les add-ons, la console web est plus pratique : Add-ons → `ecoworking-pg` → onglets "Metrics" (CPU/mémoire/disque) et "Logs" (slow queries).

### Backups

- **Postgres** : snapshots automatiques quotidiens via Clever Cloud
- **Cellar (S3)** : redondance native Clever
- **Backup externalisé** (best practice) : `spatie/laravel-backup` quotidien → dump SQL + storage zip → bucket Scaleway Object Storage (région différente) ou Backblaze B2
- **Backup manuel avant grosse opération** : bouton "Trigger a backup now" (console → Add-ons → `ecoworking-pg` → "Backups", rétention 7 j en plan XS)
- Test de restauration trimestriel

**Sécurité compte Clever Cloud** :
- **2FA recommandé** sur le compte (Profil → Security → Enable 2FA)
- Restrictions IP optionnelles sur Postgres pour durcir (moins critique car l'app accède en réseau privé)

---

## 12. CI/CD & flux de déploiement

### Plateforme : GitHub

**Pourquoi GitHub** :
- Standard du marché, énorme écosystème
- GitHub Actions : 2000 min/mois gratuit en repo privé, illimité public
- Intégration Claude Code excellente
- Marketplace Actions énorme

**Compte** : à créer au nom perso de Guillaume (pas via DOWiNO) puisque projet personnel/Ecoworking.

### Structure repo

- Repo unique : `gtribut/ecoworking-manager` (privé)
- README avec setup local
- `.github/workflows/` pour les Actions

### Stratégie de branches

- `main` : production (protected, requires PR + green CI)
- `feature/<nom>` : nouvelles fonctionnalités → PR vers main
- `fix/<nom>` : corrections → PR vers main
- Pas de branche `dev` (pas de staging)

### Pipelines GitHub Actions

**`.github/workflows/ci.yml`** (sur PR vers main) :
1. Setup PHP 8.5 + Node 26
2. Composer install
3. npm install (admin + portal-spa)
4. Pint check (lint PHP)
5. Biome check (lint TS/React)
6. Pest tests (avec service Postgres en container)
7. Build assets Vite (admin + portal)
8. Playwright tests e2e (optionnel sur PR, obligatoire sur main)

**`.github/workflows/deploy.yml`** (sur push main) :
1. Tag de version automatique (semver via commits conventionnels, optionnel)
2. Push vers le remote Clever Cloud (`git push clever main`)
3. Clever Cloud build + déploie
4. Notification Sentry release
5. Notification Slack/Discord (optionnel)

### Secrets GitHub

- `CLEVER_TOKEN` : pour push automatique
- `SENTRY_AUTH_TOKEN` : pour upload source maps + tag release
- `BREVO_API_KEY` : injecté dans `.env` Clever via clever cli si besoin

### Déploiement Clever Cloud

Option 1 (recommandée MVP) : **git push direct**
- Remote git Clever exposé
- Push = build & deploy automatique
- Simple à configurer

Option 2 (V2 si besoin de contrôle) : **Docker image**
- Build image en CI
- Push vers Clever Cloud Docker registry
- Plus de contrôle mais plus de boilerplate

---

## 13. Environnements

### Local (dev)

- Docker Compose via Laravel Sail
- Hot reload Vite pour SPA + portal
- Hot reload Laravel via `php artisan serve` ou Sail
- Postgres + Mailpit (mailcatcher) dans des containers
- Logs en stdout + Laravel Telescope pour le debug

### Production

- Clever Cloud
- Postgres managé, Cellar S3
- Sentry actif
- Better Stack uptime
- Pas de Telescope (off via `APP_ENV=production`)
- Logs : stdout + remontée Sentry pour erreurs

### Variables d'environnement

`.env.example` à versionner avec toutes les clés (valeurs vides ou placeholder). En production, ces variables sont positionnées via `clever env set` (cf. **§11.5** pour celles liées aux add-ons/runtime ; les autres se reportent à l'identique depuis ce bloc) :

```env
# App
APP_NAME="Ecoworking"
APP_ENV=local                          # ou production
APP_KEY=                               # généré via artisan
APP_DEBUG=true                         # false en prod
APP_URL=https://portail.ecoworking.fr  # URL principale (la SPA membre)
APP_TIMEZONE=Europe/Paris
APP_LOCALE=fr

# Domaines par fonction (utilisés par les middlewares et configs)
ADMIN_DOMAIN=admin.ecoworking.fr       # en dev : admin.ecoworking.test
PORTAL_DOMAIN=portail.ecoworking.fr    # en dev : portail.ecoworking.test

# DB
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=ecoworking
DB_USERNAME=
DB_PASSWORD=

# Redis : non utilisé en MVP — tout sur Postgres (cf. ADR-0007)
# Cache, sessions, queues
CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database

# Mail (dev: mailpit, prod: Brevo)
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
BREVO_API_KEY=                         # prod

# Storage
FILESYSTEM_DISK=local                  # local en dev, s3 en prod
AWS_ACCESS_KEY_ID=                     # Cellar creds en prod
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=eu-west-1
AWS_BUCKET=
AWS_ENDPOINT=                          # endpoint Cellar Clever

# Sessions — isolation par sous-domaine
# Note : SESSION_DOMAIN est laissé NULL pour scoper le cookie au host courant
# (chaque sous-domaine a ses propres cookies, pas de partage)
SESSION_DOMAIN=                        # NULL pour isolation par host
SESSION_LIFETIME=240                   # 4h pour admin ; configurable par contexte

# Sanctum (uniquement le portail consomme l'API par cookie)
SANCTUM_STATEFUL_DOMAINS=portail.ecoworking.fr,portail.ecoworking.test:5173,localhost:5173

# Filament (panel restreint au sous-domaine admin)
FILAMENT_DOMAIN=admin.ecoworking.fr

# Sentry
SENTRY_LARAVEL_DSN=
SENTRY_TRACES_SAMPLE_RATE=0.2

# Google OAuth (admin uniquement)
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=https://admin.ecoworking.fr/auth/google/callback

# Google Calendar (sync salles)
GOOGLE_CALENDAR_ID=
GOOGLE_SERVICE_ACCOUNT_JSON=           # path ou JSON inline
```

---

## 14. Setup dev local sous Windows 11

### Architecture cible

```
Windows 11 (host)
└── WSL2 + Ubuntu 24.04 LTS
    └── Docker Desktop (WSL2 backend)
        └── Laravel Sail containers
            ├── PHP 8.5 + Composer
            ├── PostgreSQL 16
            └── Mailpit
```

**Pourquoi WSL2 obligatoire** :
- Laravel Sail (et tout Docker performant) requiert WSL2 sur Windows
- Performance I/O dramatiquement meilleure dans WSL2 que sur le filesystem Windows
- Toute la chaîne (git, node, php, composer) tourne idéalement dans WSL2

### Outils à installer

#### Système / shell

1. **WSL2 + Ubuntu 24.04** (Microsoft Store ou `wsl --install -d Ubuntu-24.04`)
2. **Windows Terminal** (Microsoft Store, déjà installé Windows 11 récent)
3. **PowerShell 7** (Microsoft Store) — pour les opérations côté Windows
4. **Docker Desktop** (https://docker.com/products/docker-desktop) avec WSL2 backend activé

#### Dev tools (à installer DANS Ubuntu WSL2)

Dans le terminal Ubuntu WSL2 :

```bash
# Mise à jour
sudo apt update && sudo apt upgrade -y

# Outils de base
sudo apt install -y curl wget git unzip zip build-essential ca-certificates

# PHP 8.5 (utile pour composer hors Sail, mais Sail tourne en container)
sudo add-apt-repository ppa:ondrej/php
sudo apt update
sudo apt install -y php8.5-cli php8.5-mbstring php8.5-xml php8.5-curl php8.5-pgsql php8.5-gd php8.5-zip

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Node 26 (Current, LTS oct 2026) via fnm (rapide, gère plusieurs versions)
curl -fsSL https://fnm.vercel.app/install | bash
# reload shell, puis:
fnm install 26
fnm use 26
fnm default 26

# pnpm (plus rapide que npm, mais npm marche aussi)
npm install -g pnpm
```

#### IDE & extensions

**VS Code** (sur Windows, mais utilisé via Remote WSL) :
- Télécharger : https://code.visualstudio.com/
- Extensions essentielles (ID `publisher.extension` entre backticks) :
  - `ms-vscode-remote.remote-wsl` — Remote WSL (indispensable)
  - `bmewburn.vscode-intelephense-client` — PHP Intelephense (autocomplete)
  - `open-southeners.laravel-pint` — Laravel Pint (formatter)
  - `biomejs.biome` — Biome (lint/format TS/React)
  - `bradlc.vscode-tailwindcss` — Tailwind CSS IntelliSense
  - `eamodio.gitlens` — GitLens (historique git enrichi)
  - `usernamehw.errorlens` — Error Lens (erreurs inline)
  - `github.vscode-pull-request-github` — GitHub Pull Requests
  - `ms-azuretools.vscode-docker` — Docker (visualisation containers)
  - `mikestead.dotenv` — coloration `.env`
  - Optionnelles : Pretty TypeScript Errors, Conventional Commits, Pest snippets

Ouvrir le projet via `code .` depuis WSL2 dans le dossier projet.

**Alternatives IDE** : PhpStorm (payant, ~100€/an, excellent pour Laravel) — à considérer si tu veux le meilleur outillage PHP. Sinon VS Code suffit largement.

#### Outils CLI utiles

```bash
# CLIs additionnels (dans WSL2)

# GitHub CLI (gh)
type -p curl >/dev/null || sudo apt install curl -y
curl -fsSL https://cli.github.com/packages/githubcli-archive-keyring.gpg | sudo dd of=/usr/share/keyrings/githubcli-archive-keyring.gpg
echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/githubcli-archive-keyring.gpg] https://cli.github.com/packages stable main" | sudo tee /etc/apt/sources.list.d/github-cli.list > /dev/null
sudo apt update && sudo apt install gh -y
gh auth login

# Clever Cloud CLI
curl -sS https://clever-tools.clever-cloud.com/releases/latest/clever-tools-latest_linux.tar.gz | tar -xz
sudo mv clever-tools-latest_linux/clever /usr/local/bin/
clever login

# httpie (alternative à curl, plus lisible pour tester API)
sudo apt install -y httpie

# jq (parser JSON en ligne de commande)
sudo apt install -y jq
```

### Initialiser le projet Laravel (bootstrap initial, une seule fois)

> À n'utiliser que pour **créer** le projet la première fois (avant qu'il existe dans le repo). Si le repo est déjà cloné, voir « Quick start » ci-dessous.

```bash
# Dans WSL2, cd dans ton dossier projets (idéalement sous ~/projets, pas sur /mnt/c)
mkdir -p ~/projets && cd ~/projets

# Créer le projet
curl -s "https://laravel.build/ecoworking-manager?with=pgsql,mailpit" | bash

cd ecoworking-manager

# Lancer Sail
./vendor/bin/sail up -d

# Migrate
./vendor/bin/sail artisan migrate

# Alias sail
echo "alias sail='[ -f sail ] && sh sail || sh vendor/bin/sail'" >> ~/.bashrc
source ~/.bashrc
```

### Quick start (repo déjà cloné, env configuré)

Le flow courant une fois le setup système (ci-dessus) fait :

```bash
git clone git@github.com:gtribut/ecoworking-manager.git
cd ecoworking-manager

# Dépendances
composer install
(cd portal-spa && pnpm install)

# Env + base
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed

# SPA en dev (autre terminal)
(cd portal-spa && pnpm dev)
```

Accès local :
- **Admin Filament** : http://admin.ecoworking.test
- **Portail SPA** : http://portail.ecoworking.test
- **Mailpit** (emails dev) : http://localhost:8025

### Notifications de fin de tâche (déjà en place)

Tu utilises déjà PowerShell 7 + BurntToast pour les toasts Windows. Tu peux les déclencher depuis WSL2 via :

```bash
# Depuis WSL2, déclencher une notif Windows
powershell.exe -Command "New-BurntToastNotification -Text 'Build OK', 'ecoworking déployé'"
```

À mettre dans un alias `notif` pour faciliter.

### Performance — règles d'or

1. **Toujours travailler dans le filesystem Linux** (`~/projets/`), pas sur `/mnt/c/...` (Windows monté). Différence : 5-10× sur les opérations I/O.
2. **Allouer assez de RAM/CPU à WSL2** : créer `~/.wslconfig` (sur Windows, dans `C:\Users\<user>\`) :

```ini
[wsl2]
memory=8GB
processors=4
swap=2GB
```

3. **Docker Desktop** : activer "Use the WSL 2 based engine" dans les settings.

---

## 15. Outils Git & flow

### Reco principale : VS Code + Git CLI + GitHub Desktop

**Daily ops (VS Code intégré)** :
- Source Control panel pour commits/pushes/pulls courants
- GitLens pour blame/history
- GitHub Pull Requests extension pour gérer les PR depuis VS Code

**Power ops (Git CLI dans WSL2)** :
- `git rebase -i` interactif
- `git reflog` pour récupération
- `git bisect` pour traquer un bug
- `gh` CLI (GitHub) pour créer/checker des PR rapidement

**GUI complément (GitHub Desktop)** :
- Installation : https://desktop.github.com/
- Gratuit, officiel GitHub
- Utile pour : visualisation des diffs, rollbacks visuels, gestion multi-repos
- Tourne sur Windows directement (pas dans WSL2)

### Alternative power user : Fork

Si VS Code Source Control te frustre rapidement (interface limitée pour gros diffs ou rebase interactifs visuels) :
- **Fork** : ~50€ achat unique, Windows + Mac, excellent
- Site : https://git-fork.com/
- À tester en trial avant d'acheter

### Configuration Git initiale

Dans WSL2 :

```bash
git config --global user.name "Guillaume <nom>"
git config --global user.email "<email perso>"
git config --global init.defaultBranch main
git config --global pull.rebase true
git config --global core.autocrlf input
git config --global core.editor "code --wait"

# Aliases utiles
git config --global alias.st status
git config --global alias.co checkout
git config --global alias.br branch
git config --global alias.lg "log --oneline --graph --decorate --all"
git config --global alias.unstage "reset HEAD --"
git config --global alias.amend "commit --amend --no-edit"
```

### Auth GitHub : SSH ou HTTPS + token

**SSH (recommandé, plus simple à long terme)** :

```bash
# Générer une clé ed25519
ssh-keygen -t ed25519 -C "<email>"

# Démarrer ssh-agent
eval "$(ssh-agent -s)"
ssh-add ~/.ssh/id_ed25519

# Afficher la clé publique
cat ~/.ssh/id_ed25519.pub

# Coller sur https://github.com/settings/keys
```

**Bonus sécurité** : signer les commits avec GPG ou SSH (en V2, non critique MVP).

#### Persistance de la passphrase SSH (ssh-agent via systemd user)

> **Contexte** : la clé `~/.ssh/id_ed25519` est protégée par passphrase. Sans agent persistant, sous WSL2 chaque nouveau shell relance un `ssh-agent` distinct (agents orphelins) et redemande la passphrase. La solution retenue : un **service utilisateur systemd** qui héberge un agent unique, partagé par tous les shells, qui survit à la fermeture des fenêtres et redémarre au boot. WSL2 a systemd actif (`systemctl --user` opérationnel), ce qui rend cette approche native.
>
> **Important** : un ssh-agent ne stocke la clé déchiffrée qu'**en RAM**, jamais sur disque. La passphrase n'est donc **pas** conservée après un reboot complet — comportement visé : **une seule saisie de passphrase par démarrage de la machine**, puis tous les shells réutilisent l'agent. (Pour « zéro saisie même après reboot », seule l'option agent OpenSSH **Windows** relayé dans WSL le permet — non retenue ici car plus de pièces à maintenir.)

Mise en place (faite le 2026-06-04) :

1. **Service** `~/.config/systemd/user/ssh-agent.service` :

   ```ini
   [Unit]
   Description=SSH key agent (per-user)
   Documentation=man:ssh-agent(1)

   [Service]
   Type=simple
   Environment=SSH_AUTH_SOCK=%t/ssh-agent.socket
   ExecStart=/usr/bin/ssh-agent -D -a $SSH_AUTH_SOCK
   # -D : foreground (requis par Type=simple) ; -a : socket à chemin fixe

   [Install]
   WantedBy=default.target
   ```

   (`%t` = `XDG_RUNTIME_DIR`, soit `/run/user/<uid>` → socket `/run/user/1000/ssh-agent.socket`.)

2. **Pointer les shells vers cet agent** — ajouté à `~/.bashrc` :

   ```bash
   # --- ssh-agent géré par systemd user (ssh-agent.service) ---
   export SSH_AUTH_SOCK="${XDG_RUNTIME_DIR}/ssh-agent.socket"
   ```

3. **Chargement auto de la clé au premier usage** — `~/.ssh/config` (perms `600`) :

   ```sshconfig
   Host *
       AddKeysToAgent yes
       IdentityFile ~/.ssh/id_ed25519

   Host github.com
       HostName github.com
       User git
       IdentityFile ~/.ssh/id_ed25519
       IdentitiesOnly yes
   ```

4. **Activer le service + lingering** (l'agent persiste sans session ouverte et au boot) :

   ```bash
   loginctl enable-linger "$(id -un)"
   systemctl --user daemon-reload
   systemctl --user enable --now ssh-agent.service
   ```

Vérification (dans un **nouveau** terminal) :

```bash
echo $SSH_AUTH_SOCK     # → /run/user/1000/ssh-agent.socket
ssh -T git@github.com   # demande la passphrase 1×, puis "Hi <user>! You've successfully authenticated..."
ssh-add -l              # liste la clé chargée
```

Dépannage utile :
- **`ssh_askpass: ... No such file or directory`** lors d'un `ssh-add` manuel : le shell n'a pas de TTY et `DISPLAY` est défini → forcer la saisie au terminal avec `SSH_ASKPASS_REQUIRE=never DISPLAY= ssh-add ~/.ssh/id_ed25519`, ou lancer la commande dans un vrai terminal interactif.
- **Plusieurs agents orphelins** accumulés (anciens `eval "$(ssh-agent -s)"`) : `pkill ssh-agent` puis rouvrir un terminal (le service en relance un seul, propre).

### Convention de commits

Format conventionnel (facilite changelog auto et revue) :

```
<type>(<scope>): <description courte>

[body optionnel]

[footer optionnel]
```

Types : `feat`, `fix`, `refactor`, `docs`, `test`, `chore`, `style`, `perf`, `ci`.

Exemples :
- `feat(booking): ajout de la résa de salles avec sync Google`
- `fix(invoice): correction de la numérotation chronologique`
- `chore(deps): mise à jour laravel/sanctum vers 4.x`

### Hooks Git

- **husky** ou **pre-commit native Laravel** pour lancer Pint + Biome avant commit
- Configurable côté projet :
  ```bash
  ./vendor/bin/sail composer require --dev guava/git-hooks
  ```

---

## 16. Monitoring & observabilité

### Sentry (errors + perf)

- Compte gratuit (5k events/mois) suffit en MVP
- 2 projets : `ecoworking-laravel` et `ecoworking-portal-react`
- Source maps uploadées en CI pour traces lisibles
- Sample rate perf : 0.2 (20% des requêtes tracées en prod, ajuster selon volume)

### Better Stack (uptime)

- Free tier suffit (3 monitors, 1 min interval)
- Monitor sur `https://app.ecoworking.fr/up` (endpoint dédié dans Laravel)
- Alerts email + (optionnel) Slack/SMS

### Laravel Pulse (perf interne)

- Inclus dans le projet
- Dashboard accessible `/pulse` (admin only)
- Tracking : slow requests, slow queries, exceptions, queue throughput

### Healthchecks.io (cron jobs)

- Free tier 20 checks
- Un check par cron job (facturation mensuelle, sync calendar, backup, etc.)
- Alerte si pas de ping dans la fenêtre attendue

### Logs

- Stdout containers Clever Cloud (visualisables via UI Clever)
- Pas de log management externe en MVP (overkill)
- Remontée erreurs critiques via Sentry breadcrumbs

---

## 17. Sécurité & RGPD

### Headers HTTP

À configurer via middleware `\Spatie\Csp\AddCspHeaders` ou manuellement :
- `Strict-Transport-Security: max-age=63072000; includeSubDomains; preload`
- `Content-Security-Policy: ...` (strict, nonces sur scripts inline)
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: geolocation=(), microphone=(), camera=()`

### TLS

- HTTPS forcé (redirect 80→443) sur `admin.ecoworking.fr` et `portail.ecoworking.fr`
- TLS 1.2 minimum (1.3 préféré)
- HSTS preload pour les deux sous-domaines

### Isolation cookies par sous-domaine

**Bénéfice sécurité majeur** du choix architecture deux sous-domaines :

- Cookies admin (`admin.ecoworking.fr`) **ne fuient pas** vers le portail
- Cookies portail (`portail.ecoworking.fr`) **ne fuient pas** vers l'admin
- Une compromission côté portail (XSS membre par exemple) **ne donne pas accès** à la session admin et vice-versa
- Implémentation : `SESSION_DOMAIN` laissé à NULL → Laravel scope automatiquement le cookie au host courant
- Cookies Sanctum stateful identiques (scope automatique au host)

### Auth

- Argon2id ou bcrypt pour passwords
- 2FA TOTP obligatoire admin, optionnel membres
- Rate limiting login/2FA/forgot-password
- Session timeout admin : 4h inactivité, déconnexion auto
- Session timeout membre : 7j (cookie remember_me)

### Validation

- Form Requests Laravel partout (jamais de Validator inline)
- Validation côté front (Zod) pour UX, mais **toute** validation aussi côté back
- Sanitization XSS automatique via Blade `{{ }}`

### Audit log

- `spatie/activitylog` configuré sur entités sensibles : User, Company, Invoice, Subscription, Booking, Payment
- Champs trackés : qui, quand, action, valeurs old/new, IP, user agent

### Isolation données

- Policies Eloquent systématiques (jamais de query brute sans scope)
- Tests d'isolation à écrire dans `tests/Feature/AuthorizationTest.php`
- Global Scope par défaut sur les ressources membres : `whereHas('owner', fn($q) => $q->where('user_id', auth()->id()))`

### Backups

- Quotidiens via `spatie/laravel-backup`
- Stockés sur Cellar + externalisés sur Scaleway Object Storage (région différente)
- Retention : 30 jours rolling
- Test de restauration manuelle trimestriel

### RGPD

- Page "Mes données" dans portail membre :
  - Export complet (JSON ou ZIP) — droit d'accès
  - Suppression de compte — droit à l'oubli (avec anonymisation préservant intégrité comptable : factures conservées 10 ans avec données anonymisées)
- Consents trackés en DB (`consents` table) : marketing emails, photo publication, etc.
- Privacy Policy + DPA disponibles publiquement
- DPA signés avec sous-traitants : Clever Cloud, Sentry, Brevo, Better Stack, (Google si Calendar API)
- Cookies : seuls cookies fonctionnels (session, CSRF, 2FA device) → pas de bandeau cookies nécessaire si pas de tracking

---

## 18. Découpage MVP / V1 / V2 / V3

### MVP — V1 (3-4 mois de dev)

- Setup projet, CI, environnements
- Modèle de données complet (toutes tables MVP)
- Auth admin (Fortify + 2FA + Google OAuth)
- Filament admin : Resources pour User, MemberProfile, Company, Contact, Offer, Subscription, Purchase, Resource, Booking, Invoice
- Génération PDF factures (sans Factur-X)
- Numérotation chronologique
- Statuts paiement manuels
- Auth portail (Sanctum SPA + Fortify)
- SPA portail React : login, profil, mes factures, réserver salle, acheter ticket, déclarer présence nomade
- Sync Google Calendar (push only)
- Emails transactionnels (confirmation résa, facture émise)
- Sentry + Better Stack + Pulse + Healthchecks

### V1.5 — Déploiement & migration (4-6 semaines)

- Déploiement Clever Cloud prod
- Configuration DNS, certifs, env vars
- Backups externalisés
- Import depuis Cosoft (script artisan dédié)
- Période de double-saisie (1 mois)
- Bascule complète
- PWA (install bureau + cache)

### V2 — Facturation électronique conforme (3-4 mois, avant sept 2027)

- Génération Factur-X (PDF/A-3 + XML CII)
- Mentions obligatoires nouvelles 2026 implémentées
- Choix PA + intégration API
- Réception factures électroniques (si applicable)
- Audit complet conformité avec expert-comptable
- Optionnel : Stripe Cashier pour paiements en ligne (CB + SEPA récurrent)

### V3 — Confort & extensions (à la demande)

- Annonces & événements avancés (RSVP, calendrier dédié, etc.)
- Reporting / analytics (CA par offre, taux d'occupation, rétention membres)
- Integration Pennylane (sync paiements bancaires reçus)
- Contrôle d'accès physique (UniFi door access ou autre) si Ecoworking en équipe
- Wifi captive portal
- Push notifications (si vrai besoin)
- App mobile native ou Capacitor PWA-to-native (si vrai besoin)

---

## 19. Migration depuis Cosoft

### Étape 1 : audit Cosoft (à faire ASAP)

- Contacter support Cosoft pour formats d'export disponibles (CSV ? API ? Dump SQL ?)
- Lister toutes les entités migrables : clients, contacts, contrats, factures historiques, abonnements en cours, soldes
- Identifier les données non migrables (et stratégie : archivage PDF en lecture seule, ou perte assumée)

### Étape 2 : script d'import

- Commande Artisan dédiée : `php artisan migration:from-cosoft --file=<export.csv>`
- Validation stricte : refuser les imports avec données invalides plutôt que de polluer la DB
- Logging détaillé pour audit

### Étape 3 : période de double-saisie

- 1 mois de double-saisie Cosoft + nouvel outil (lourd mais sécurisant)
- Comparaison hebdo des soldes et données critiques
- Identifier les écarts → fix scripts d'import si nécessaire

### Étape 4 : bascule

- Date fixe (idéalement fin de mois calendaire)
- Gel des données Cosoft (lecture seule)
- Bascule totale sur nouvel outil

### Étape 5 : archivage Cosoft

- Export complet PDF + CSV de toutes les factures historiques
- Stockage long terme (S3 + sauvegarde locale)
- Accès admin uniquement pour consultation historique
- Conservation 10 ans pour conformité fiscale

---

## 20. Conventions de code

### PHP / Laravel

- PSR-12 enforced via Laravel Pint
- Namespace `App\` standard
- Models : singulier (`User`, `Booking`, `Invoice`)
- Tables : pluriel (`users`, `bookings`, `invoices`)
- Eloquent relations : nommage explicite (`bookings()`, `memberProfile()`, `billableEntity()`)
- Form Requests : `StoreXxxRequest`, `UpdateXxxRequest`
- Services métier : `App\Services\` (logique réutilisable, hors HTTP)
- Jobs : `App\Jobs\`, suffixe `Job` (ex. `SendInvoiceJob`)
- Mail : `App\Mail\`, suffixe `Mail`
- Tests : Pest, fichiers `tests/Feature/*Test.php` et `tests/Unit/*Test.php`

### TypeScript / React

- TS strict mode (`"strict": true` dans `tsconfig.json`)
- Composants fonctionnels uniquement, hooks
- Naming : composants `PascalCase`, hooks `useCamelCase`, utilitaires `camelCase`
- Imports absolus via alias (`@/components/...`)
- Organisation par feature (`features/bookings/`, `features/invoices/`) plutôt que par type (`components/`, `hooks/`)
- État serveur : TanStack Query exclusivement (pas de `useState` pour données API)
- État UI local : `useState` ou `useReducer`
- Pas de Redux/Zustand en MVP (overkill, TanStack Query suffit)

### Commits

Conventionnels (cf. section Git).

### Documentation

- README projet : setup local, commandes courantes, troubleshooting
- ADR (Architecture Decision Records) dans `/docs/adr/` pour les décisions structurantes : choix Laravel, choix Sanctum SPA mode, etc.
- API documentation : OpenAPI auto-généré via `scribe` ou maintenu manuellement si simple

---

## 21. Coûts mensuels estimés

| Service | MVP (mois 1-4) | V1 prod (à partir mois 5) | V2 (avec PA & paiements) |
|---|---|---|---|
| Clever Cloud (app + Postgres + Cellar) | 0 € (dev local) | ~30 € | ~30 € |
| Brevo (email transactionnel) | 0 € (free tier <300/j) | 0-9 € | 9 € |
| Sentry | 0 € (free tier <5k events) | 0-26 € | 26 € |
| Better Stack | 0 € | 0 € (free tier) | 0 € |
| Healthchecks.io | 0 € | 0 € | 0 € |
| GitHub (CI/CD) | 0 € | 0 € | 0 € |
| Domain `.fr` | ~10 €/an | ~10 €/an | ~10 €/an |
| Backup externe (Scaleway Object Storage) | 0 € | ~2 € | ~2 € |
| Plateforme Agréée (e-invoicing) | — | — | ~10-30 € |
| Stripe (si paiements V2) | — | — | 1.4% + 0.25€ par tx |
| **Total mensuel** | **~0 €** | **~30-65 €** | **~70-105 €** |

---

## 22. Annexes — ressources d'apprentissage

### Laravel 13 (semaines 1-2)
- **Laravel Bootcamp** (officiel, gratuit) : https://bootcamp.laravel.com/
- **Laracasts** (~150€/an mais essentiel) : https://laracasts.com/ — séries "30 Days to Learn Laravel" et "Laravel 13 from Scratch"
- **Laravel: Up & Running** (Matt Stauffer, 4e édition Laravel 13) — livre de référence
- Documentation officielle : https://laravel.com/docs/13.x

### Filament 5 (semaine 3)
- **Filament Bootcamp** (officiel, gratuit) : https://filamentphp.com/tricks
- Documentation : https://filamentphp.com/docs/5.x
- Plugins communauté : https://filamentphp.com/plugins

### Sanctum SPA mode (semaine 3-4)
- Doc officielle Sanctum : https://laravel.com/docs/13.x/sanctum#spa-authentication
- Tutoriel : recherche "Laravel Sanctum SPA React" sur YouTube/blogs

### React 19.2 + Vite 8 + TanStack 5
- **React docs** : https://react.dev/
- **React Router v7** : https://reactrouter.com/
- **TanStack Query 5** : https://tanstack.com/query/latest
- **Vite 8 announcement** : https://vite.dev/blog/announcing-vite8
- **Tailwind CSS v4** : https://tailwindcss.com/blog/tailwindcss-v4
- **shadcn/ui** : https://ui.shadcn.com/
- **Biome v2** : https://biomejs.dev/

### PostgreSQL
- **PostgreSQL Tutorial** (gratuit) : https://www.postgresqltutorial.com/
- **The Art of PostgreSQL** (Dimitri Fontaine) — livre avancé

### Sécurité Laravel
- **OWASP Cheat Sheet** : https://cheatsheetseries.owasp.org/
- Audit récent DOWiNO (référence interne Guillaume) — bonnes pratiques RGPD/auth/headers

### Claude Code optimisation
- Documentation Claude Code : https://docs.claude.com/claude-code
- Pratique : créer un `CLAUDE.md` à la racine du projet avec conventions, structure et patterns

---

## Notes finales pour le PRD

Ce brief doit servir de **contexte technique** pour le PRD. Le PRD se concentrera sur :

1. Specifications fonctionnelles détaillées (user stories, edge cases)
2. Wireframes / maquettes UI (Filament admin + portail SPA)
3. Modèle de données complet (toutes les colonnes, types, contraintes)
4. API endpoints détaillés (verbes, URLs, payloads, réponses)
5. Workflows métier précis (cycle facturation mensuelle, gestion litiges paiement, etc.)
6. Critères d'acceptation par feature
7. Plan de test détaillé
8. Plan de migration Cosoft step-by-step

Toute décision technique non documentée ici est **TBD** et doit être discutée explicitement avant implémentation.

---

*Document généré avec Claude — itéré sur 8 échanges avec Guillaume entre le 14 mai 2026 et le 14 mai 2026.*
*Version 1 — à mettre à jour au fur et à mesure des décisions futures.*
