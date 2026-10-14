# Ecoworking

> Outil de gestion sur-mesure pour l'espace de coworking Ecoworking (Lyon).
> Remplace Cosoft. Mono-tenant. Laravel 13 + Filament 5 + SPA React.
>
> Statut : en recette sur jeu de données de démonstration. Mise en production prévue sur Clever Cloud (V1.5).

---

## 📚 Documentation

Ce README est volontairement **léger** : c'est une page d'accueil. Toute l'information détaillée (setup, déploiement, architecture, stack) vit dans le BRIEF — source de vérité unique pour éviter les doublons désalignés.

Avant de coder ou de demander à Claude Code de coder, lire dans l'ordre :

1. [`CLAUDE.md`](./CLAUDE.md) — contexte permanent + conventions + commandes courantes (lu par Claude Code en début de session)
2. [`docs/BRIEF.md`](./docs/BRIEF.md) — brief technique et fonctionnel complet (setup, infra, déploiement, stack)
3. [`docs/PRD.md`](./docs/PRD.md) — spec fonctionnelle détaillée (portail client + back-office admin)
4. [`docs/data_model.md`](./docs/data_model.md) — modèle de données détaillé (schéma Postgres : tables, contraintes, RGPD)
5. [`docs/adr/`](./docs/adr/) — décisions architecturales
6. [`docs/SUIVI.md`](./docs/SUIVI.md) — **suivi des phases & tâches** (où on en est ; pour reprendre une session : « continue C1.4 »)

Pour **présenter l'outil à une personne externe** (vision fonctionnelle + captures d'écran portail et back-office, ~5 min de lecture) : [`docs/presentation/`](./docs/presentation/README.md), aussi en [PDF](./docs/presentation/ecoworking-presentation.pdf). Régénération : `scripts/presentation/build.sh`.

---

## 🚀 Démarrage

| Besoin | Où regarder |
|---|---|
| **Setup système** (WSL2, Docker, PHP, Node, Git, SSH) — 1ère fois | [BRIEF §14 — Setup dev local sous Windows 11](./docs/BRIEF.md#14-setup-dev-local-sous-windows-11) |
| **Quick start** (repo déjà cloné : install + run) | [BRIEF §14 — Quick start](./docs/BRIEF.md#14-setup-dev-local-sous-windows-11) |
| **Mise en ligne** (provisioning + premier déploiement Clever Cloud) | [BRIEF §11 — Provisioning & premier déploiement](./docs/BRIEF.md#11-infrastructure--hébergement) |
| **Variables d'environnement** | [BRIEF §13 — Environnements](./docs/BRIEF.md#13-environnements) |

Accès local (une fois lancé) : Admin Filament → `http://admin.ecoworking.test` · Portail SPA → `http://portail.ecoworking.test` · Mailpit → `http://localhost:8025`

---

## 🧰 Commandes courantes

Référence complète (Sail, tests, lint, génération de code, queues, cache) : [`CLAUDE.md` §8](./CLAUDE.md).

---

## 🏗 Architecture & stack

Une app Laravel **déployée une seule fois** sur Clever Cloud (FR), répondant à deux sous-domaines via routing Laravel : `admin.ecoworking.fr` (Filament 5) et `portail.ecoworking.fr` (SPA React + `/api/*`). Cache, sessions et queues sur PostgreSQL 18 (pas de Redis en MVP, cf. [ADR-0007](./docs/adr/0007-pas-de-redis-en-mvp.md)). Storage S3 sur Cellar.

Stack : PHP 8.5, Laravel 13, Filament 5, PostgreSQL 18 côté serveur ; React 19, TypeScript strict, Vite 8, React Router 7, TanStack Query 5, Tailwind 4 et shadcn/ui côté portail. Tests : Pest, Vitest (+ Testing Library), Playwright (+ axe-core). Lint : Pint, Biome.

Services externes : Brevo (email), Sentry (errors), Better Stack (uptime), Healthchecks.io (cron), Google Calendar API (sync salles).

- Schéma détaillé + structure repo : [BRIEF §6 — Architecture applicative](./docs/BRIEF.md#6-architecture-applicative)
- Stack & versions précises : [BRIEF §5 — Stack technique](./docs/BRIEF.md#5-stack-technique) (versions runtime figées dans [ADR-0008](./docs/adr/0008-versions-runtime-modernes.md))

---

## 🧪 Qualité et tests

### Suites de tests

Chiffres mesurés le 14/10/2026 sur `main` (toutes les suites passent).

| Suite | Périmètre | Tests |
|---|---|---|
| **Pest** | Back-end : API portail, autorisations, facturation, réservations, back-office Filament | 683 |
| **Vitest** + Testing Library | Composants, hooks et formulaires du portail | 293 |
| **Playwright** | Parcours navigateur du portail membre | 52 |
| **Pest browser** | Parcours navigateur du back-office | 6 |

- TDD encouragé sur la logique métier critique (facturation, isolation données, réservations)
- Tests d'isolation systématiques (un membre A ne lit pas les données d'un membre B) : `tests/Feature/AuthorizationTest.php`

Pas de pourcentage de couverture rigide, mais tout chemin métier critique doit être couvert. Détails : [`CLAUDE.md` §5.2](./CLAUDE.md) et [`docs/testing-e2e.md`](./docs/testing-e2e.md).

### Intégration continue

[`.github/workflows/ci.yml`](./.github/workflows/ci.yml), sur chaque push et pull request vers `main` :

- **PHP** : `composer audit` (bloquant), Pint, Pest sur PostgreSQL 18.
- **SPA** : `pnpm audit` (bloquant), Biome, typecheck TypeScript, Vitest, build de production.
- **E2E** : Pest browser (back-office) puis Playwright (portail) sur PostgreSQL et Mailpit. Job encore non bloquant (`continue-on-error`) le temps de stabiliser la suite navigateur.

### Accessibilité

Cible : **RGAA 4.1 niveau AA** (WCAG 2.1 AA) pour le portail membre.

- 34 des 52 tests Playwright exécutent un audit axe-core limité aux règles WCAG 2.0 / 2.1 A et AA (42 analyses par exécution), en thème clair et sombre. Une violation fait échouer le test.
- 6 autres tests vérifient l'absence de scroll horizontal à 390 px, le zoom 200 % et le respect de `prefers-reduced-motion`.
- Règles `a11y` de Biome actives sur le code du portail.
- La grille de l'agenda (FullCalendar) est exclue de l'audit axe, avec une vue liste comme alternative accessible ([ADR-0013](./docs/adr/0013-shadcn-fullcalendar-a11y-agenda.md)).
- La déclaration d'accessibilité du portail indique une conformité **partielle** : l'audit RGAA formel n'a pas encore été réalisé.

### Décisions d'architecture (ADR)

| ADR | Décision |
|---|---|
| [0001](./docs/adr/0001-choix-laravel.md) | Laravel 13 comme backend |
| [0002](./docs/adr/0002-architecture-hybride-filament-spa.md) | Architecture hybride : Filament pour l'admin, SPA React pour le portail |
| [0003](./docs/adr/0003-auth-portail-sanctum-spa-mode.md) | Sanctum en mode SPA pour l'authentification du portail |
| [0004](./docs/adr/0004-sous-domaines-admin-portail.md) | Deux sous-domaines `admin` et `portail` sur un seul déploiement |
| [0005](./docs/adr/0005-strategie-facturation-electronique.md) | Stratégie pour la facturation électronique (réforme 2026-2027) |
| [0006](./docs/adr/0006-routing-et-data-fetching-portail.md) | React Router v7 + TanStack Query pour le portail |
| [0007](./docs/adr/0007-pas-de-redis-en-mvp.md) | Pas de Redis en MVP : cache, sessions et queues sur PostgreSQL |
| [0008](./docs/adr/0008-versions-runtime-modernes.md) | Versions runtime (PHP 8.5, Laravel 13, Filament 5, stack front mai 2026) |
| [0009](./docs/adr/0009-google-oauth-admin-only.md) | Google OAuth réservé à l'admin, domaine restreint, sans création automatique de compte |
| [0010](./docs/adr/0010-timezone-europe-paris.md) | Fuseau applicatif Europe/Paris |
| [0011](./docs/adr/0011-magic-link-jetons-en-table.md) | Lien de connexion magique : jetons à usage unique en table dédiée |
| [0012](./docs/adr/0012-fuseau-horaire-postgres.md) | Décalage horaire porté par le format de date PostgreSQL |
| [0013](./docs/adr/0013-shadcn-fullcalendar-a11y-agenda.md) | shadcn/ui, FullCalendar pour l'agenda et alternative accessible |

---

## 🚀 Déploiement

Production (prévue, pas encore en ligne) : **Clever Cloud** via `git push clever main`.

1. PR créée → CI GitHub Actions (lint + tests + build)
2. Review + merge sur `main` → CI re-run
3. Auto-deploy Clever Cloud sur push `main`
4. Release Sentry taguée automatiquement (source maps uploadées)

Runbook complet (création app, add-ons, domaines, env vars, suivi) : [BRIEF §11](./docs/BRIEF.md#11-infrastructure--hébergement) · flux CI/CD : [BRIEF §12](./docs/BRIEF.md#12-cicd--flux-de-déploiement).

---

## 📐 Conventions

- Branches : `main` (protégée, PR + CI verte), `feature/`, `fix/`, `refactor/`, `chore/`
- Commits conventionnels : `<type>(<scope>): <description>` — types `feat`, `fix`, `refactor`, `docs`, `test`, `chore`, `style`, `perf`, `ci`
- Avant chaque commit : `sail test` ✓, `sail pint --test` ✓, `pnpm biome check` ✓, `pnpm typecheck` ✓, pas de `dd()`/`console.log`/secrets oubliés

Détails complets : [`CLAUDE.md`](./CLAUDE.md) (§4 conventions, §9 git workflow).

---

## 🆘 Troubleshooting

### Sail ne démarre pas
```bash
sail down -v && sail build --no-cache && sail up -d
```

### Erreur "permission denied" sur les fichiers
```bash
sudo chown -R $USER:$USER .   # depuis WSL2
```

### Performance lente (I/O)
Vérifier que le projet est dans `~/projets/` (filesystem Linux) et **pas** dans `/mnt/c/...` (montage Windows). Différence : 5-10× sur l'I/O.

### Sous-domaines non résolus en dev
Vérifier `C:\Windows\System32\drivers\etc\hosts` (`127.0.0.1 admin.ecoworking.test` + `portail.ecoworking.test`), puis `ipconfig /flushdns` (PowerShell admin).

### CORS errors entre SPA et API en dev
Normalement aucune (même origine `portail.ecoworking.test`). Sinon : vérifier `SANCTUM_STATEFUL_DOMAINS` dans `.env`.

### Tests Postgres qui échouent en CI
S'assurer que le service Postgres est démarré dans le workflow. Voir `.github/workflows/ci.yml`.

### Passphrase SSH redemandée à chaque shell
Config d'un ssh-agent persistant (systemd user) : [BRIEF §15 — Persistance de la passphrase SSH](./docs/BRIEF.md#15-outils-git--flow).

### `git push` échoue avec `Permission denied (publickey)` (agent SSH non démarré)
Démarrer l'agent à la volée dans un terminal WSL interactif, charger la clé, puis pousser :
```bash
eval "$(ssh-agent -s)" && ssh-add ~/.ssh/id_ed25519 && git push
```
Solution temporaire (ne survit pas à la fermeture du shell). Setup pérenne : [BRIEF §15 — Démarrage manuel ponctuel / agent persistant](./docs/BRIEF.md#15-outils-git--flow).

---

## 📝 Licence

Propriétaire — Ecoworking. Tous droits réservés. Code non destiné à distribution publique.

---

*Maintenu par Guillaume.*
