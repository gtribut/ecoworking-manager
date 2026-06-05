# SUIVI.md — Suivi des phases & tâches

> **Tableau de bord vivant** de l'avancement du projet Ecoworking.
> Source de vérité du **statut** (qui fait quoi, où on en est). Le **périmètre** reste
> défini par [`BRIEF.md` §18](./BRIEF.md#18-découpage-mvp--v1--v2--v3) et le **détail fonctionnel**
> par [`PRD.md`](./PRD.md) ; ce fichier ne fait que tracer l'état d'avancement.
>
> **Dernière mise à jour : 2026-06-05 (C2.1/C2.3/C2.4/C2.5 ✅).**

---

## Comment l'utiliser

- Chaque tâche a un **code stable** (ex. `C1.4`). Pour reprendre le travail en session suivante :
  dire simplement **« continue C1.4 »** (ou « continue la tâche modèles Eloquent »).
- **Mettre à jour le statut au fil de l'eau** (à chaque fin de tâche), et la date en tête de fichier.
- Légende des statuts :

| Symbole | Signification |
|---|---|
| ✅ | Terminé (codé + testé + committé) |
| 🚧 | En cours |
| ⬜ | À faire |
| ⏸️ | En attente d'une décision / dépendance externe |
| 🔮 | Hors MVP (V1.5 / V2 / V3) |

### 📍 Position actuelle

> **C2 quasi complet** : Fortify+2FA (`C2.1`) ✅, Sanctum SPA (`C2.3`) ✅, Policies isolation A/B (`C2.4`) ✅,
> permissions Spatie+XOR (`C2.5`) ✅. **Seul reste `C2.2`** (Socialite Google — ⚠️ checkpoint CLAUDE.md §10
> « nouveau service tiers / DPA RGPD » : en attente du feu vert + des identifiants OAuth). Ensuite : **C3 — Filament**
> (dont enforcement 2FA obligatoire admin, branché au panel).

---

## MVP / V1

### C0 — Fondations projet

| Code | Tâche | Statut | Note |
|---|---|---|---|
| C0.1 | Docs de cadrage (BRIEF, PRD, data_model, ADR 0001-0008) | ✅ | Stabilisés 2026-06-04 |
| C0.2 | Scaffold Laravel 13 + Sail (PHP 8.5, Postgres 18, Mailpit) | ✅ | `compose.yaml`, vérifié HTTP 200 |
| C0.3 | Setup dev local (WSL2, Docker, SSH) | ✅ | Cf. BRIEF §14-15 |
| C0.4 | CI GitHub Actions (lint Pint/Biome + tests Pest + build) | ⬜ | `.github/workflows/ci.yml` (BRIEF §12) |
| C0.5 | Config environnements `.env.example` (Postgres, Mailpit, locale fr) | ✅ | Aligné Sail ; reste secrets prod (V1.5) |

### C1 — Base de données

| Code | Tâche | Statut | Note |
|---|---|---|---|
| C1.1 | Migrations des 25 tables métier + tables système | ✅ | 5 phases, data_model §4 |
| C1.2 | Contraintes DB critiques (GiST anti-double-booking, index partiel domiciliation, CHECK enums, FK différées) | ✅ | data_model §6 |
| C1.3 | Tests de schéma Pest (contraintes, sur PostgreSQL) | ✅ | 40 tests verts |
| C1.4 | **Modèles Eloquent** (relations, casts enum, `$fillable`, scopes) | ✅ | 21 modèles + `User` enrichi ; morphs via morph map ; test relations/casts |
| C1.5 | Factories (toutes les entités + traits `->admin()`, `->resident()`…) | ✅ | 22 factories + traits rôles ; test « chaque factory produit une ligne valide » (69 verts) |
| C1.6 | Seeders de données : catalogue `offers` (8 SKU MVP), `resources` (48 bureaux + 3 salles + event) | ✅ | `OfferSeeder`/`ResourceSeeder` idempotents (updateOrCreate) ; test prix figés + inventaire |
| C1.7 | Morph map + enums PHP centralisés | ✅ | `app/Enums/`, `AppServiceProvider` (+ alias `invoice`/`payment` pour l'audit log) |
| C1.8 | Audit log (`spatie/activitylog`) sur entités sensibles | ✅ | Trait `Auditable` (liste blanche, sans secrets/PII) sur User/Company/Invoice/Subscription/Booking/Payment ; test RGPD |

### C2 — Authentification & autorisation

| Code | Tâche | Statut | Note |
|---|---|---|---|
| C2.1 | Auth admin : Fortify (sessions + 2FA TOTP) | ✅ | Fortify (login/logout/reset/2FA), `views=false` (JSON), inscription désactivée (PRD §3.2), rate-limit 5/min ; secret/recovery sur colonnes C1.1 ; `FortifyAuthTest`. **Reste** : enforcement 2FA obligatoire admin (→ C3.1), audit login/logout + `last_login_at` (→ C8) |
| C2.2 | Socialite Google OAuth (admin) | ⬜ | BRIEF §8 — ⚠️ **checkpoint §10** (service tiers/DPA) : à câbler après feu vert + creds Google |
| C2.3 | Auth portail : Sanctum mode SPA (cookies + CSRF) | ✅ | `statefulApi()`, `routes/api.php` (domaine via `config/domains`), `GET /api/user` (rôles+permissions, sans données sensibles), `/sanctum/csrf-cookie` ; `SESSION_DOMAIN=null` ; `SpaAuthTest` |
| C2.4 | Policies Eloquent (isolation données membre A/B) + tests `AuthorizationTest` | ✅ | 14 Policies (auto-discovery) ; helpers `User::isAdmin/isBillingContact/linkedCompanyIds/canBillFor` ; `AuthorizationTest` (14 cas A/B + billing + §3.6) |
| C2.5 | Rôles & permissions Spatie (gates, middleware rôle) | ✅ | `Permission` enum (§2.8) + `PermissionSeeder` (compo §2.5/§2.6) ; alias middlewares `role`/`permission` ; `ExclusiveUsageRole` (XOR §2.4) ; `RolePermissionTest` |

### C3 — Back-office admin (Filament 5)

| Code | Tâche | Statut | Note |
|---|---|---|---|
| C3.1 | Install Filament 5 + panel sur `admin.ecoworking.fr` | ⬜ | ADR-0002/0004 |
| C3.2 | Resources : User, MemberProfile, Company, Contact | ⬜ | |
| C3.3 | Resources : Offer, Subscription, Purchase | ⬜ | |
| C3.4 | Resources : Resource, Booking, DeskOccupation | ⬜ | |
| C3.5 | Resources : Invoice (+ émission, avoir), Payment | ⬜ | Logique en Services, pas dans la Resource |
| C3.6 | Resources : Announcement, InternalDocument, AdministrativeDocument | ⬜ | |

### C4 — API portail (`/api/*`)

| Code | Tâche | Statut | Note |
|---|---|---|---|
| C4.1 | Controllers API + Form Requests + Resources JSON | ⬜ | `auth:sanctum` obligatoire |
| C4.2 | Endpoints profil membre (lecture/édition) | ⬜ | |
| C4.3 | Endpoints factures (liste, PDF) | ⬜ | |
| C4.4 | Endpoints réservation salle | ⬜ | dépend C7 |
| C4.5 | Endpoints tickets / présence nomade | ⬜ | dépend C7 |

### C5 — SPA portail (React 19 / Vite 8 / TS)

| Code | Tâche | Statut | Note |
|---|---|---|---|
| C5.1 | Init projet `portal-spa/` (Vite, TS strict, Tailwind v4, shadcn, Router v7, TanStack Query) | ⬜ | ADR-0006 |
| C5.2 | Auth (login, CSRF Sanctum, garde de routes) | ⬜ | dépend C2.3 |
| C5.3 | Profil membre (annuaire, photo, prefs) | ⬜ | |
| C5.4 | Mes factures (liste + téléchargement PDF) | ⬜ | |
| C5.5 | Réserver une salle (calendrier + alternative liste a11y) | ⬜ | |
| C5.6 | Acheter / consommer un ticket | ⬜ | |
| C5.7 | Déclarer présence/absence nomade | ⬜ | |
| C5.8 | a11y RGAA AA (axe-core, navigation clavier) | ⬜ | CLAUDE.md §3.5 |

### C6 — Facturation

| Code | Tâche | Statut | Note |
|---|---|---|---|
| C6.1 | `InvoiceNumberingService` (compteur `lockForUpdate`, EW-YYYY-NNNNN) | ⬜ | data_model §6.5, table prête |
| C6.2 | Génération PDF (`barryvdh/laravel-dompdf`) | ⬜ | |
| C6.3 | Calcul HT/TVA/TTC + prorata (bornes incluses, ROUND_HALF_UP) | ⬜ | §6.12 |
| C6.4 | Émission (fige lignes), annulation + avoir auto | ⬜ | §6.6 + `InvoicePolicy::delete()` |
| C6.5 | Idempotence facturation (cron/instant/manuel) | ⬜ | §6.13 |
| C6.6 | Statuts paiement manuels + recalcul `amount_paid` | ⬜ | |

### C7 — Réservations & occupation

| Code | Tâche | Statut | Note |
|---|---|---|---|
| C7.1 | `BookingService` anti-double-booking (`lockForUpdate` + 409, backstop GiST) | ⬜ | §6.8, contrainte DB prête |
| C7.2 | Calcul de disponibilité salles (horaires resident/external) | ⬜ | |
| C7.3 | Présence nomade : dérivation présence résident (assignment − absences) | ⬜ | §4.3 |
| C7.4 | Dispo bureaux external (compteur unassigned − occupations) | ⬜ | §6.1 PRD |
| C7.5 | Crédit/consommation/restitution de tickets | ⬜ | |

### C8 — Notifications & emails

| Code | Tâche | Statut | Note |
|---|---|---|---|
| C8.1 | Emails transactionnels (confirmation résa, facture émise) via Jobs | ⬜ | dev Mailpit, prod Brevo |
| C8.2 | Centre de notifications in-app (driver `database`) | ⬜ | table `notifications` |
| C8.3 | Préférences notif (toggles `notify_email`/`notify_in_app`) | ⬜ | colonnes prêtes |

### C9 — Sync Google Calendar

| Code | Tâche | Statut | Note |
|---|---|---|---|
| C9.1 | Push résa salles → Google Calendar (Job) | ⬜ | BRIEF §10, push only MVP |
| C9.2 | Flux iCal perso + entité (`calendar_token`) | ⬜ | PRD §3.5.8, colonne prête |

### C10 — Observabilité

| Code | Tâche | Statut | Note |
|---|---|---|---|
| C10.1 | Sentry (erreurs + releases) | ⬜ | |
| C10.2 | Better Stack (uptime) + Healthchecks.io (cron) | ⬜ | |
| C10.3 | Laravel Pulse | ⬜ | |

### C11 — Tests & qualité

| Code | Tâche | Statut | Note |
|---|---|---|---|
| C11.1 | Tests de schéma DB (Pest) | ✅ | 40 verts |
| C11.2 | Tests Feature métier (facturation, isolation, résa) | ⬜ | au fil des chantiers |
| C11.3 | Tests e2e Playwright (SPA) + Pest 4 browser (Filament) | ⬜ | ADR-0008 |
| C11.4 | a11y axe-core sur écrans critiques | ⬜ | |
| C11.5 | Pint + Biome propres en CI | 🚧 | Pint OK localement ; CI à brancher (C0.4) |

---

## 🔮 V1.5 — Déploiement & migration (post-MVP)

| Code | Tâche | Statut | Note |
|---|---|---|---|
| D1 | Provisioning Clever Cloud (app, Postgres 18, Cellar, FS Bucket) | 🔮 | BRIEF §11 — **vérifier `btree_gist` activable** |
| D2 | DNS, certifs, env vars prod | 🔮 | |
| D3 | Backups externalisés | 🔮 | |
| D4 | Import Cosoft (`php artisan migration:from-cosoft`) | 🔮 | BRIEF §19 |
| D5 | Période double-saisie (1 mois) + bascule | 🔮 | |
| D6 | PWA (install + cache) | 🔮 | |

## 🔮 V2 — Facturation électronique conforme (avant sept. 2027)

| Code | Tâche | Statut | Note |
|---|---|---|---|
| E1 | Génération Factur-X (PDF/A-3 + XML CII, `atgp/factur-x`) | 🔮 | champs DB déjà prévus (data_model §8) |
| E2 | Mentions obligatoires 2026 | 🔮 | |
| E3 | Choix Plateforme Agréée + intégration API | 🔮 | |
| E4 | Audit conformité expert-comptable (dont Q26 BtoC/BtoB) | ⏸️ | data_model §9.7 |
| E5 | (Optionnel) Stripe Cashier paiements en ligne | 🔮 | |

## 🔮 V3 — Confort & extensions (à la demande)

| Code | Tâche | Statut | Note |
|---|---|---|---|
| F1 | Annonces & événements avancés (RSVP, calendrier dédié) | 🔮 | |
| F2 | Reporting / analytics (CA, taux d'occupation, rétention) | 🔮 | |
| F3 | Intégration Pennylane | 🔮 | |
| F4 | Contrôle d'accès physique / Wifi captive portal | 🔮 | |
| F5 | Push notifications / app mobile | 🔮 | |

---

## Décisions ouvertes à trancher (non bloquantes MVP)

- **Q26** — Facturation BtoC vs BtoB (assujettissement TVA `individual`, Factur-X) → expert-comptable, V2. (data_model §9.7)
- Points 🟡 de modélisation résiduels : cf. [data_model §9](./data_model.md#9-points-ouverts).

---

*Maintenu au fil des sessions. Toute tâche terminée → passer son statut à ✅ et mettre à jour la date en tête.*
