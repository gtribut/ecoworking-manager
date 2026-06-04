# Ecoworking

> Outil de gestion sur-mesure pour l'espace de coworking Ecoworking (Lyon).
> Remplace Cosoft. Mono-tenant. Laravel 13 + Filament 5 + SPA React.

---

## 📚 Documentation

Ce README est volontairement **léger** : c'est une page d'accueil. Toute l'information détaillée (setup, déploiement, architecture, stack) vit dans le BRIEF — source de vérité unique pour éviter les doublons désalignés.

Avant de coder ou de demander à Claude Code de coder, lire dans l'ordre :

1. [`CLAUDE.md`](./CLAUDE.md) — contexte permanent + conventions + commandes courantes (lu par Claude Code en début de session)
2. [`docs/BRIEF.md`](./docs/BRIEF.md) — brief technique et fonctionnel complet (setup, infra, déploiement, stack)
3. [`docs/PRD.md`](./docs/PRD.md) — spec fonctionnelle détaillée (portail client + back-office admin)
4. [`docs/data_model.md`](./docs/data_model.md) — modèle de données *(à produire)*
5. [`docs/adr/`](./docs/adr/) — décisions architecturales

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

Une app Laravel **déployée une seule fois** sur Clever Cloud (FR), répondant à deux sous-domaines via routing Laravel : `admin.ecoworking.fr` (Filament 5) et `portail.ecoworking.fr` (SPA React + `/api/*`). Cache, sessions et queues sur PostgreSQL 16 (pas de Redis en MVP, cf. [ADR-0007](./docs/adr/0007-pas-de-redis-en-mvp.md)). Storage S3 sur Cellar.

Services externes : Brevo (email), Sentry (errors), Better Stack (uptime), Healthchecks.io (cron), Google Calendar API (sync salles).

- Schéma détaillé + structure repo : [BRIEF §6 — Architecture applicative](./docs/BRIEF.md#6-architecture-applicative)
- Stack & versions précises : [BRIEF §5 — Stack technique](./docs/BRIEF.md#5-stack-technique) (versions runtime figées dans [ADR-0008](./docs/adr/0008-versions-runtime-modernes.md))

---

## 🧪 Tests

- **Pest** : tests Feature (HTTP) + Unit
- **Playwright** : e2e côté SPA portail
- TDD encouragé sur la logique métier critique (facturation, isolation données, réservations)
- Tests d'isolation systématiques : `tests/Feature/AuthorizationTest.php`

Pas de pourcentage de couverture rigide, mais tout chemin métier critique doit être couvert. Détails : [`CLAUDE.md` §5.2](./CLAUDE.md).

---

## 🚀 Déploiement

Production : **Clever Cloud** via `git push clever main`.

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

---

## 📝 Licence

Propriétaire — Ecoworking. Tous droits réservés. Code non destiné à distribution publique.

---

*Maintenu par Guillaume.*
