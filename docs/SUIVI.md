# SUIVI.md — Suivi des phases & tâches

> **Tableau de bord vivant** de l'avancement du projet Ecoworking.
> Source de vérité du **statut** (qui fait quoi, où on en est). Le **périmètre** reste
> défini par [`BRIEF.md` §18](./BRIEF.md#18-découpage-mvp--v1--v2--v3) et le **détail fonctionnel**
> par [`PRD.md`](./PRD.md) ; ce fichier ne fait que tracer l'état d'avancement.
>
> **Dernière mise à jour : 2026-09-16 — C14 démarré : U0 (docs) livré ; suite U1→U5 en session orchestrateur.** Précédent : 2026-09-16 — C14 refonte UI portail planifié (`docs/refonte_ui/`), recette §3.1 faite, §3.2+ suspendue jusqu'à C14. 2026-09-13 (soir) — C13.6 lot B mergé, lot A livré (branche `feature/portail-lot-a`, à relire + rejouer Playwright). Reprise 12/09 après 2 mois. MVP COMPLET ✅ depuis le 03/07 (444 Pest + 72 Vitest + 26 e2e). Chantier ouvert : C13 recette manuelle (`docs/recette.md` + `DemoSeeder`, livrés le 12/09, validés le 13/09 : 446 Pest verts). Ensuite : corrections issues de la recette, puis V1.5 (Clever Cloud, import Cosoft) — cf. `todo_guillaume.md`.

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

> **C2/C3 complets ✅** (auth + back-office Filament). **C7 complet ✅** (Services résa/occupation : `BookingService`
> anti-double-booking `lockForUpdate`+backstop GiST, `RoomAvailabilityService`/`DeskAvailabilityService`, `TicketService`/`PurchaseService`,
> `PresenceService` ; 14 tests). **C4 complet ✅** : C4.1→C4.3 (profil, factures) **+ C4.4/C4.5 débloqués** (API résa salle,
> tickets, bureaux nomades, présence — `auth:sanctum`, auto-scope, 409/422 métier ; 9 tests). **C5 complet ✅** (SPA portail :
> auth/profil/factures + réservation salle (agenda a11y), tickets/bureaux, présence ; a11y RGAA AA ; 26 tests Vitest). **C6
> ✅** : cœur (numérotation/émission/avoir/calcul) via C3.5, **+ C6.2 PDF dompdf**, **C6.6 paiements** (recalcul
> `amount_paid`/statut, overdue). **C6.5 ✅ refondue (2026-06-07)** : facturation récurrente **par entité** (lignes
> regroupées par prestation × qté, prorata sur ligne séparée), idempotence (entité, période) + backstop DB, traçabilité
> `invoice_line_subscriptions` ; 11 tests C6.
> **C8 ✅** notifications/emails (base `PortalNotification` routant les canaux selon préférences ; facture émise/retard +
> absence déclarée câblées ; centre in-app + cloche SPA). **C9.2 ✅** flux iCal d'abonnement (perso + entité, token-capacité ;
> C9.1 push Google → V1.5). **C10 ✅** observabilité (Sentry back+front, Pulse `/pulse` admin-only, ping Healthchecks sur les
> crons, `/up` ; comptes/DSN = `todo_guillaume.md`).
> Suite complète **232 tests Pest verts** (+ 32 Vitest SPA). **C0.4 ✅ CI GitHub Actions** (Pint/Biome/Pest/Vitest/build).
> **C12 ✅ complet (2026-07-03)** : serving SPA prod (C12.1), tickets admin (C12.2), annonces portail (C12.3),
> documents portail (C12.4), annuaire + plan SVG 49 bureaux (C12.5), dashboard admin + occupation (C12.6),
> anonymisation RGPD (C12.7), magic link (C12.8a), audit log/rôles UI + Settings dé-scopé (C12.8b),
> décision D email admin-only (C12.9). Suite complète **442 Pest + 71 Vitest verts**.
> **C11.3/C11.4 ✅** (03/07). **C13 🚧 recette manuelle** (ouvert le 12/09) : `docs/recette.md` (checklist par écran,
> PRD §3/§4/§5 + isolation + emails + crons) et `DemoSeeder` (11 comptes, tous les états métier, vrais services).
> Puis corrections recette → **V1.5** (déploiement Clever Cloud, import Cosoft).

---

## MVP / V1

### C0 — Fondations projet

| Code | Tâche | Statut | Note |
|---|---|---|---|
| C0.1 | Docs de cadrage (BRIEF, PRD, data_model, ADR 0001-0008) | ✅ | Stabilisés 2026-06-04 |
| C0.2 | Scaffold Laravel 13 + Sail (PHP 8.5, Postgres 18, Mailpit) | ✅ | `compose.yaml`, vérifié HTTP 200 |
| C0.3 | Setup dev local (WSL2, Docker, SSH) | ✅ | Cf. BRIEF §14-15 |
| C0.4 | CI GitHub Actions (lint Pint/Biome + tests Pest + build) | ✅ | `.github/workflows/ci.yml` — job **php** (Pint `--test` + Pest sur service Postgres 18) + job **spa** (Biome + `tsc` typecheck + Vitest + build) ; cache composer/pnpm. Fix au passage : `baseUrl` déprécié retiré de `tsconfig.app.json` (TS 6), `*.tsbuildinfo` ignoré |
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
| C2.1 | Auth admin : Fortify (sessions + 2FA TOTP) | ✅ | Fortify (login/logout/reset/2FA), `views=false` (JSON), inscription désactivée (PRD §3.2), rate-limit 5/min ; secret/recovery sur colonnes C1.1 ; `FortifyAuthTest`. Enforcement 2FA admin livré en C3.1 (MFA native Filament). **Reste** : audit login/logout + `last_login_at` (→ C8) |
| C2.2 | Socialite Google OAuth (admin) | ✅ | `GoogleOAuthController` admin-only + domaine restreint + match email, **sans** auto-provisioning (ADR-0009) ; routes `auth/google/*` (domaine admin) ; `GoogleOAuthTest` (Socialite mocké). Client OAuth créé + creds dans LastPass (07/06/26). **Reste** : push env vars Clever Cloud au déploiement + **test live manuel en prod** pour confirmer (`todo_guillaume.md`) |
| C2.3 | Auth portail : Sanctum mode SPA (cookies + CSRF) | ✅ | `statefulApi()`, `routes/api.php` (domaine via `config/domains`), `GET /api/user` (rôles+permissions, sans données sensibles), `/sanctum/csrf-cookie` ; `SESSION_DOMAIN=null` ; `SpaAuthTest` |
| C2.4 | Policies Eloquent (isolation données membre A/B) + tests `AuthorizationTest` | ✅ | 14 Policies (auto-discovery) ; helpers `User::isAdmin/isBillingContact/linkedCompanyIds/canBillFor` ; `AuthorizationTest` (14 cas A/B + billing + §3.6) |
| C2.5 | Rôles & permissions Spatie (gates, middleware rôle) | ✅ | `Permission` enum (§2.8) + `PermissionSeeder` (compo §2.5/§2.6) ; alias middlewares `role`/`permission` ; `ExclusiveUsageRole` (XOR §2.4) ; `RolePermissionTest` |

### C3 — Back-office admin (Filament 5)

| Code | Tâche | Statut | Note |
|---|---|---|---|
| C3.1 | Install Filament 5 + panel sur `admin.ecoworking.fr` | ✅ | Filament 5.6 ; panel domaine (racine) conditionnel via `config/domains` ; `canAccessPanel` admin-only (`FilamentUser`) ; 2FA TOTP **natif Filament** obligatoire (`AppAuthentication` recoverable, `isRequired`), colonnes `app_authentication_*` distinctes de Fortify (ADR-0002 auth séparée) ; `FilamentPanelTest` (10 cas). Audit login/logout + `last_login_at` → C8 |
| C3.2 | Resources : User, MemberProfile, Company, Contact | ✅ | 4 Resources (form en sections + tables filtrables) ; RelationManagers contacts/profils sous Entité ; `User` (mdp conditionnel hashé, rôles Spatie), `Company` (entreprise/particulier conditionnel, SEPA last4 only §3.4, remise), accesseur `Company::name` ; enums `HasLabel`/`HasColor` FR ; `UserPolicy`+`ContactPolicy` (admin-only) + `MemberProfilePolicy` create/delete ; tests Livewire (rendu) + Policies (9 cas) |
| C3.3 | Resources : Offer, Subscription, Purchase | ✅ | Groupe « Catalogue & ventes » ; `Offer` (form conditionnel par type, prix non figé §6.7, `TagsInput` features), `Subscription` (souscripteur+billable `MorphToSelect` User/Company, aucun prix figé §3.6), `Purchase` (snapshot prix pré-rempli depuis l'offre mais éditable §3.6, `created_by`=admin) ; `OfferPolicy` admin-only ; enums catalogue `HasLabel`/`HasColor` ; tests Livewire + snapshot/`created_by` (6 cas) |
| C3.4 | Resources : Resource, Booking, DeskOccupation | ✅ | Groupe « Espaces & réservations » ; `Resource` (model aliasé pour éviter la collision avec `Filament\…\Resource`, form conditionnel par type desk/salle, `TagsInput` features, `KeyValue` opening_hours), `Booking` (salles only — filtre `meeting_room`/`event_room`, billable `MorphToSelect`, snapshot prix si payant, `created_by`=admin), `DeskOccupation` (bureaux only, `created_by`=admin) ; `ResourcePolicy` admin-only ; enums `HasLabel`/`HasColor` FR (ResourceType, ResourceAssignment, BookingStatus, Period, DeskOccupationSource/Status) ; `SpacesResourcesTest` (6 cas) |
| C3.5 | Resources : Invoice (+ émission, avoir), Payment | ✅ | Logique en **Services** (hors Resource) : `InvoiceNumberingService` (compteur `lockForUpdate`, `EW-YYYY-NNNNN`, consommé qu'à l'émission §3.6), `IssueInvoiceService` (pose n°, fige totaux+lignes, snapshot adresse billable, due=+14j), `CancelInvoiceService` (cancelled + avoir miroir lié, montants négatifs), `InvoiceLineCalculator` (HT/TVA/TTC remise côté serveur). `Invoice` : Repeater lignes (calc via `mutateRelationshipData…`), action **Émettre** (page Edit, brouillon), action **Annuler+avoir** (page **View** car émise = édition interdite par Policy), totaux figés en lecture seule ; `Payment` (factures émises only, `created_by`). Enums `InvoiceStatus` `HasLabel`/`HasColor`. `InvoiceIssuanceTest` (7) + `BillingResourcesTest` (7) |
| C3.6 | Resources : Announcement, InternalDocument, AdministrativeDocument | ✅ | Groupe « Communication & documents » ; `Announcement` (bloc événement conditionnel type=event, `FileUpload` cover, `created_by`), `InternalDocument` (versionné, `FileUpload` PDF, `created_by`), `AdministrativeDocument` (rattaché entité, `FileUpload` PDF, `uploaded_by`) ; `AnnouncementPolicy`+`InternalDocumentPolicy` admin-only (`AdministrativeDocumentPolicy` préexistante) ; enums `HasLabel`/`HasColor` (AnnouncementType/Status, Audience, InternalDocumentType, AdministrativeDocumentType) ; `CommunicationResourcesTest` (6 cas) |

### C4 — API portail (`/api/*`)

| Code | Tâche | Statut | Note |
|---|---|---|---|
| C4.1 | Controllers API + Form Requests + Resources JSON | ✅ | `auth:sanctum` obligatoire ; namespaces `App\Http\Controllers\Api`, `App\Http\Requests\Api`, `App\Http\Resources` ; JSON Resources `CompanyResource` (read-only, sans IBAN/mandat/remise), `MemberProfileResource`, `InvoiceResource` (`pdf_available`) ; `Gate::authorize` (Controller de base minimal, pas de trait) |
| C4.2 | Endpoints profil membre (lecture/édition) | ✅ | `GET /api/profile` (user + profile + entité read-only PRD §3.4.3) & `PATCH /api/profile` (partiel) ; **auto-scopé** `$request->user()` (aucun id client) ; `UpdateProfileRequest` (champs perso only — nom read-only, email/mdp = flux dédiés §3.4.5 hors périmètre) ; 409 si pas de `member_profile` ; `ProfileApiTest` (6, dont isolation A/B) |
| C4.3 | Endpoints factures (liste, PDF) | ✅ | `GET /api/invoices` (liste paginée, **émises only**, périmètre miroir `InvoicePolicy` C2.4 : rôle `billing_contact` + entités liées / nom propre — vide sinon) & `GET /api/invoices/{invoice}/pdf` (`Gate::authorize('download')`, stream disque, 404 si PDF pas encore généré) ; `InvoiceApiTest` (7, dont isolation entités + 403 cross-entité) |
| C4.4 | Endpoints réservation salle | ✅ | `RoomController` (catalogue + dispo), `BookingController` (mes résas, création resident/external, annulation) ; 409 conflit, 422 métier ; auto-scope membre |
| C4.5 | Endpoints tickets / présence nomade | ✅ | `TicketController` (soldes+liste), `DeskController` (dispo bureaux external + occupation/ticket), `PresenceController` (présence dérivée + absences) ; `ReservationApiTest` (9, isolation A/B) |

### C5 — SPA portail (React 19 / Vite 8 / TS)

| Code | Tâche | Statut | Note |
|---|---|---|---|
| C5.1 | Init projet `portal-spa/` (Vite, TS strict, Tailwind v4, shadcn, Router v7, TanStack Query) | ✅ | Vite 8/React 19/TS strict ; biome (Tailwind directives + a11y stricte) |
| C5.2 | Auth (login, CSRF Sanctum, garde de routes) | ✅ | AuthContext + RequireAuth + 2FA ; gestion erreurs Laravel |
| C5.3 | Profil membre (annuaire, photo, prefs) | ✅ | lecture/édition (entité read-only) |
| C5.4 | Mes factures (liste + téléchargement PDF) | ✅ | liste paginée + PDF |
| C5.5 | Réserver une salle (calendrier + alternative liste a11y) | ✅ | agenda accessible (liste créneaux), resident (libre) vs external (demi-journée/ticket), 409/422 |
| C5.6 | Acheter / consommer un ticket | ✅ | soldes + réservation bureau nomade external (consommation ticket) ; achat = crédit admin (MVP) |
| C5.7 | Déclarer présence/absence nomade | ✅ | déclaration absence (ponctuelle/plage/récurrence) + liste/suppression |
| C5.8 | a11y RGAA AA (axe-core, navigation clavier) | ✅ | HTML sémantique, labels, aria-live, nav clavier ; axe-core Playwright → C11.4 |

### C6 — Facturation

| Code | Tâche | Statut | Note |
|---|---|---|---|
| C6.1 | `InvoiceNumberingService` (compteur `lockForUpdate`, EW-YYYY-NNNNN) | ✅ | livré en C3.5 |
| C6.2 | Génération PDF (`barryvdh/laravel-dompdf`) | ✅ | `InvoicePdfService` + template facture/avoir (mentions CGI art. 289) + `GenerateInvoicePdfJob` dispatché à l'émission ; `config/company.php` (env) |
| C6.3 | Calcul HT/TVA/TTC + prorata (bornes incluses, ROUND_HALF_UP) | ✅ | `InvoiceLineCalculator` (C3.5) + prorata jours dans `MonthlyBillingService` |
| C6.4 | Émission (fige lignes), annulation + avoir auto | ✅ | livré en C3.5 (`IssueInvoiceService`/`CancelInvoiceService`, `InvoicePolicy::delete()`) |
| C6.5 | Idempotence facturation (cron/instant/manuel) | ✅ | `MonthlyBillingService` **refondu par entité** : `generateMonth` (1 facture/entité distincte) + `generateForEntity` (consolidation, lignes regroupées par prestation = offre + période, `quantity` = nb abos, prorata sur ligne séparée) + `generateForSubscription` (instant T). Idempotence clé **(entité, période)** (check applicatif) + **backstop DB** UNIQUE `(subscription_id, period_start, period_end)`. Traçabilité fine via `invoice_line_subscriptions` (`InvoiceLineSubscription` model + relation `InvoiceLine::subscriptionLinks()`) ; lignes regroupées `related`=NULL. Migration `2026_06_07_090000`. `C6BillingTest` (11) |
| C6.6 | Statuts paiement manuels + recalcul `amount_paid` | ✅ | `InvoicePaymentService` + `PaymentObserver` (recalcul + statut) + commande `invoices:update-overdue` (scheduler) ; `C6BillingTest` (8) |

### C7 — Réservations & occupation

| Code | Tâche | Statut | Note |
|---|---|---|---|
| C7.1 | `BookingService` anti-double-booking (`lockForUpdate` + 409, backstop GiST) | ✅ | bornes semi-ouvertes ; annulation + restitution ticket ; `BookingConflictException`→409 |
| C7.2 | Calcul de disponibilité salles (horaires resident/external) | ✅ | `RoomAvailabilityService` (resident 24/7, external demi-journées jours ouvrés via `FrenchHolidays`) |
| C7.3 | Présence nomade : dérivation présence résident (assignment − absences) | ✅ | `PresenceService` (récurrence hebdo expansée à la lecture) + `AbsenceService` intégré |
| C7.4 | Dispo bureaux external (compteur unassigned − occupations) | ✅ | `DeskAvailabilityService` (chevauchement matin/après-midi/journée) + réservation/annulation |
| C7.5 | Crédit/consommation/restitution de tickets | ✅ | `TicketService` (cohérence type/cible) + `PurchaseService` (génération tickets, prix figé) ; `C7ReservationTest` (14) |

### C8 — Notifications & emails

| Code | Tâche | Statut | Note |
|---|---|---|---|
| C8.1 | Emails transactionnels (confirmation résa, facture émise) via Jobs | ✅ | Base `PortalNotification` (routage canaux selon préférences, `ShouldQueue` → email jamais bloquant) ; notifs `InvoiceIssued`/`InvoiceOverdue` (mail+db, doublage critique) câblées dans `IssueInvoiceService`/`UpdateOverdueInvoicesCommand` ; `AbsenceDeclared` (in-app only) → admins (Q25). `Invoice::recipients()`. dev Mailpit, **prod = driver API Brevo câblé** (`symfony/brevo-mailer` + `http-client`, `Mail::extend`, `BrevoTransportTest`) ; reste prod : `MAIL_MAILER=brevo` + worker queue (todo_guillaume) |
| C8.2 | Centre de notifications in-app (driver `database`) | ✅ | Migration `notifications` ; `NotificationController` (liste paginée auto-scopée + `unread_count`, mark read / read-all) ; SPA `NotificationBell` (cloche+badge, panneau a11y Escape/clic-extérieur, poll 60s) dans le Layout |
| C8.3 | Préférences notif (toggles `notify_email`/`notify_in_app`) | ✅ | Exposées dans `/api/profile` (GET+PATCH) ; honorées par `PortalNotification::via()` ; fieldset « Notifications » sur la page profil SPA. `C8NotificationTest` (12) + Vitest `NotificationBell` (3) |

### C9 — Sync Google Calendar

| Code | Tâche | Statut | Note |
|---|---|---|---|
| C9.1 | Push résa salles → Google Calendar (Job) | 🔮 | **Différé V1.5** (décision 2026-06-07) : nécessite `google/apiclient` + service account Google (creds Guillaume) → branché avec le provisioning Clever Cloud. `bookings.google_calendar_event_id` déjà prêt |
| C9.2 | Flux iCal perso + entité (`calendar_token`) | ✅ | `IcsCalendarService` (RFC 5545, salles only, CRLF + repli 75o, échappement) ; flux `forUser`/`forEntity` (scopé entités liées) ; `CalendarFeedController` (web, **token en URL** = capacité, 404 token inconnu) ; `CalendarSubscriptionController` (API : show/regenerate/revoke, auto-scopé) ; helpers `User::ensureCalendarToken/regenerateCalendarToken` ; SPA `CalendarSubscription` (URLs copiables, régénération/désactivation) sur la page Réservations. `C9CalendarTest` (10) + Vitest (3) |

### C10 — Observabilité

| Code | Tâche | Statut | Note |
|---|---|---|---|
| C10.1 | Sentry (erreurs + releases) | ✅ | `sentry/sentry-laravel` + `Integration::handles()` dans `bootstrap/app.php` ; `@sentry/react` init dans `main.tsx` (gardé par `VITE_SENTRY_DSN`, `sendDefaultPii:false`). No-op si DSN vide → **comptes/DSN + source maps CI = TODO Guillaume** (C10 dans `todo_guillaume.md`) |
| C10.2 | Better Stack (uptime) + Healthchecks.io (cron) | ✅ | Endpoint santé `/up` déjà exposé (cible Better Stack) ; ping Healthchecks (`pingOnSuccess`/`pingOnFailure`) sur les 2 crons (facturation mensuelle, overdue), activé si URL configurée (`config/services.php`). **Comptes + monitors/checks = TODO Guillaume** |
| C10.3 | Laravel Pulse | ✅ | `laravel/pulse` installé + migrations ; gate `viewPulse` = admin-only (`AppServiceProvider`) ; `/pulse` protégé ; `PULSE_ENABLED`. `C10ObservabilityTest` (2) |

### C11 — Tests & qualité

| Code | Tâche | Statut | Note |
|---|---|---|---|
| C11.1 | Tests de schéma DB (Pest) | ✅ | 40 verts |
| C11.2 | Tests Feature métier (facturation, isolation, résa) | 🚧 | C7 résa/tickets (14), API résa (9), facturation C6 (8), isolation A/B (AuthorizationTest) + Vitest SPA (26) ; à compléter au fil des chantiers |
| C11.3 | Tests e2e Playwright (SPA) + Pest 4 browser (Filament) | ✅ | ADR-0008 appliqué : admin = `tests/Browser/` (Pest browser, `phpunit.e2e.xml`, base `testing`, login 2FA TOTP réel) **6 tests** ; SPA = `portal-spa/e2e/` (Playwright, SPA **buildée** servie par Laravel, base dédiée `e2e`, seeds `E2eSeeder`, factures plage 90001+) **14 tests** — parcours critiques C12 inclus (tickets, facture émise+PDF, occupation, RGPD, RSVP, documents, annuaire+plan clavier, magic link via Mailpit). Runbook `docs/testing-e2e.md`, scripts `scripts/e2e/`, job CI `e2e` **non bloquant au départ** (à durcir après stabilisation). Hook `checkA11y` prêt pour C11.4 |
| C11.4 | a11y axe-core sur écrans critiques | ✅ | `@axe-core/playwright` branché sur le hook `checkA11y` (tags WCAG 2.1 A/AA, zéro violation tolérée, rapport lisible, exclusion possible mais justification obligatoire — **aucune utilisée**) ; 14 checks sur tous les écrans critiques (dont plan SVG, magic link, notifications ouvertes) + **thème sombre** (dashboard + résa) ; violations trouvées = contrastes dark uniquement, corrigées (token `brand-300` + `dark:text-neutral-400`, ~35 occurrences) ; runbook §a11y |
| C11.5 | Pint + Biome propres en CI | ✅ | Pint `--test` + Biome `check` branchés dans `.github/workflows/ci.yml` (C0.4) ; verts |

### C12 — Modules MVP restants

> Périmètre issu de la review 2026-07-02 : [`docs/review_fable/07-chantier-mvp-restant.md`](./review_fable/07-chantier-mvp-restant.md).
> Arbitrages A/B/C/D tranchés par Guillaume le 2026-07-02 (tous les lots = MVP ferme).
> Bureaux : **49 actés** (étage 1 = 29, étage 2 = 20) le 2026-07-02 — aligner seeder + PRD dans C12.5.

| Code | Tâche | Statut | Note |
|---|---|---|---|
| C12.1 | Serving SPA en production (build → `public/portal/`, Blade + catch-all, CSRF Sanctum) | ✅ | Vite `base:/portal/` + manifest → `PortalSpaController` (503 si build absent) ; catch-all domaine portail déclaré en dernier (exclusions api/sanctum/up/pulse/portal/auth/calendar) ; welcome supprimée ; fix 500→401 invités API (`redirectGuestsTo(null)`) ; assets gitignorés (build = déploiement → hook Clever Cloud dans `todo_guillaume.md`) ; `PortalSpaServingTest` (9) |
| C12.2 | Tickets côté admin (`TicketResource` + action crédit manuel `PurchaseService::creditManual()`) | ✅ | Resource lecture seule + filtres ; crédit manuel (trace `credited_by`/`credit_reason`) ; consommation manuelle (PRD §4.8.1) via `ManualTicketConsumptionService` (lockForUpdate, bureau/salle, matin/après-midi) ; `TicketPolicy::viewAny` → admin-only ; 13 tests. Hors périmètre noté : annulation d'occupation bureau external / restitution geste commercial (lot ultérieur si besoin) |
| C12.3 | Annonces & événements côté portail (API + RSVP + feature SPA + bloc dashboard) | ✅ | `GET /api/announcements(/{id})` scopé audience (`Audience::roles()`, staff≈resident, 404 hors audience) ; RSVP `POST/DELETE .../registration` (`AnnouncementRegistrationService` : jauge lockForUpdate, réactivation ligne cancelled, event commencé 422, Policy exercée + A/B) ; notif in-app à la publication (PRD §3.8.4, observer, auteur exclu) ; SPA `features/announcements/` + nav + bloc dashboard, a11y ; 23 Pest + 11 Vitest. À trancher plus tard : cover_image côté portail (V2 ?), rendu Markdown du corps |
| C12.4 | Documents internes à valider + administratifs côté portail | ✅ | `GET/POST /api/documents/internal(+/validation,+/pdf)` (scope `applicableTo` par audience, `MemberDocumentValidationPolicy` enfin exercée, version snapshotée, 422 double validation) ; `GET /api/documents/administrative(+/pdf)` (miroir exact du périmètre factures billing_contact) ; téléchargements streaming disque privé + Gate ; règle §5.3 tranchée : NON bloquant à l'accueil (bloc dashboard ou « à jour ») ; SPA `features/documents/` + nav + bloc dashboard ; 24 Pest + 7 Vitest |
| C12.5 | Annuaire coworkers (opt-in) + plan SVG des étages (alternative accessible) | ✅ | `GET /api/directory` (gate `view-annuaire`, external 403, opt-in `show_in_directory` only, projection sans email/tél) + `/api/directory/floor-plan` (`FloorPlanService`, 6 requêtes constantes, occupant masqué si opt-out) ; correspondance = `resources.svg_desk_id` = `desk-N` (migration douce des ids paddés) ; **49 bureaux actés partout** (seeder, SeederTest, PRD, data_model, BRIEF §100) ; SPA `features/directory/` : plan SVG (`?raw`, ids only, clavier) + **alternative texte synchronisée** (`deskLabel()` partagé) ; 15 Pest + 10 Vitest. Resynchroniser l'asset SVG si Guillaume redessine `docs/plan/etages.svg` |
| C12.6 | Dashboard admin (KPIs, alertes) + page « Occupation du jour » | ✅ | 8 widgets minces + `AdminDashboardService`/`DailyOccupancyService` (M8 déjà fixé en `388706c` ; ajout `presentGivenAbsences` bulk 0-requête + test anti-N+1 sur `forDate`) ; KPIs PRD §4.1.2 (CA mois vs N-1, overdue, occupation salles 10 h/j conventionnel), page Occupation par étage (chevauchement `[J,J+1)`, admin-only) ; 31 tests (+2 : 33). Écarts assumés : lien audit log à câbler post-C12.8b, mini-plan SVG post-C12.5, actions rapides non dupliquées |
| C12.7 | Anonymisation RGPD (`AnonymizeUserService` + action Filament) | ✅ | Service transactionnel (lockForUpdate, refus double appel) : PII users+profil écrasée (email → `deleted-<sha256>@ecoworking.invalid`), photo supprimée du disque, mdp/2FA/tokens/sessions/calendar_token révoqués, soft delete + `anonymized_at` ; factures intactes ; audit `anonymized` sans PII (`disableLogging()` pendant l'écrasement) ; `UserPolicy::anonymize` (admin, jamais soi-même, une fois) ; action EditUser confirmation forte ; 11 tests. Note : entrées d'audit antérieures conservent la PII (conforme PRD §5.6-3, à réévaluer si droit à l'oubli strict) |
| C12.8a | Magic link membre (usage unique, 15 min, rate-limité, anti-énumération) | ✅ | ADR-0011 : URL signée 15 min + jeton SHA-256 en table `magic_link_tokens` (usage unique atomique) ; éligibilité (jamais admin/anonymisé) vérifiée à l'envoi ET à la consommation ; invalidation au changement de mdp (`UserObserver`) ; throttle 5/min ; réponse générique anti-énumération ; SPA : étape « lien de connexion » sur LoginPage ; 11 Pest + 3 Vitest |
| C12.8b | Audit log UI + gestion rôles UI + Settings | ✅ | `ActivityResource` lecture seule (filtres modèle/action/auteur/période + recherche dans le diff `attribute_changes`, eager loading morphs, `ActivityPolicy` admin-only) + lien depuis le widget dashboard ; page `RolePermissionMatrix` lecture seule (composition réelle en DB) ; affectation rôles déjà couverte par UserForm (XOR) ; **Settings : décision NON implémenté** (PRD §4.15 + data_model §4.6 amendés — périmètre runtime vide, credentials jamais en DB) ; 12 tests. Écarts assumés à arbitrer si besoin : pas de création de rôles (enum codé), pas d'export CSV, pas d'IP dans l'audit |
| C12.9 | Acter décision D : email membre = admin-only (PRD §3.4.5 + test UserForm) | ✅ | PRD §3.4.5 amendé ; `UserEmailAdminOnlyTest` (3) : édition admin OK, unicité, `PATCH /api/profile` ignore l'email ; docblock `UpdateProfileRequest` aligné |

### C13 — Recette manuelle (pré-V1.5)

> Ouverte le 2026-09-12 à la reprise du projet. Objectif : dérouler à la main tous les parcours
> portail + back-office sur un jeu de démo réaliste, consigner les anomalies (`docs/recette.md` §9),
> les corriger avant le déploiement.

| Code | Tâche | Statut | Note |
|---|---|---|---|
| C13.1 | `DemoSeeder` : jeu de données de recette (dev only) | ✅ | `database/seeders/DemoSeeder.php` — 11 comptes (`demo-password`), 2 entreprises + 1 particulier + 1 external sans ticket + staff + membre anonymisé ; 3 mois de factures émises via `MonthlyBillingService`/`IssueInvoiceService` (payée, partielle, en retard, annulée + avoir, brouillons courants) ; tickets/occupations/résas/absences/annonces/documents. Refuse la prod. Test `DemoSeederTest` (2, 59 assertions) vert le 13/09 ; suite complète 446 Pest verte |
| C13.2 | `docs/recette.md` : checklist de recette | ✅ | 9 sections : préparation, comptes, auth, 12 écrans portail, 13 modules admin, isolation A/B (bloquant), emails Mailpit, crons, journal des anomalies `R-nn` |
| C13.3 | Exécution de la recette (Guillaume) | ⬜ | Cocher au fil de l'eau, remplir le journal §9 |
| C13.4 | Corrections des anomalies `R-nn` | 🚧 | R-01→R-06 corrigées le 13/09 (`5dc146a`, `b1a2959`, `18f622f`, `e44a036`) : 429 FR, bloc docs masqué, worker queue documenté, mot de passe oublié, 2FA membre, accueil PRD §3.3. Suites : 453 Pest + 84 Vitest verts |
| C13.5 | Passe d'écarts PRD §3 portail ↔ code | ✅ | [`review_fable/08-ecarts-prd-portail.md`](./review_fable/08-ecarts-prd-portail.md) — ≈ 55 % conforme ; lots A→G proposés, **à trancher par Guillaume** avant de coder |
| C13.6 | Lots de conformité PRD §3 (A→G) via session orchestrateur | ✅ | Prompt : [`review_fable/09-prompt-orchestrateur-lots.md`](./review_fable/09-prompt-orchestrateur-lots.md) — ordre B → A → C → D → E → F → G, une branche/worktree par lot (`.worktrees/lot-<x>`, wrapper `./sail` sur base `testing_lot<x>`). **PRD** : 7 écarts 🔀 actés (`fff53c3`). **Lot B ✅ 13/09** (`008e3d3`) : `has_desk`, nav/routes/tuiles gatées, Policies strictes, écran « Accès refusé » — 477 Pest + 99 Vitest. **Lot A ✅ 13/09** (merge `967a60e`) : calendrier multi-salles semaine/jour avec occupants (Q4), salle event en lecture seule, modale de résa (journée/demi-journée/perso), `PATCH /api/bookings/{id}`, liste « à venir » + historique, correction du piège timezone dans `BookingPolicy` — 507 Pest + 128 Vitest + e2e 23/23 après review Opus. ⏸️ Décisions ouvertes : opt-out annuaire dans le calendrier, fuseau Postgres (cf. doc 08). **Lot C ✅ 13/09** (merge `1d1dd51`) : `PATCH /api/absences`, récurrence bornée, note, bureau attitré, Policies bornées au début (SQL), `DeskAbsence`/`MemberProfile` auditables, Resource Filament absences — 534 Pest + 135 Vitest. **Lot D ✅ 13/09** (merge `94515c1`) : tri/filtres/recherche factures, `GET /api/billing/entity`, bloc entité partagé, IBAN-4 + mode de paiement sous Policy — 563 Pest + 150 Vitest. **Lot E ✅ 13/09** (merge `8a81195`) : mes bureaux réservés + annulation avec restitution, Policy external, fériés côté dispo, détail tickets, mailto — 556 Pest + 143 Vitest. **Lot F ✅ 13/09** (merge `5d0b870`) : mot de passe portail, bio markdown, photo de profil (privée, 3 tailles), email d'accueil (jeton 3 j, table dédiée) — 629 Pest + 181 Vitest. **Lot G ✅ 14/09** (merge `1f92dfb`) : menu profil/thème, footer + pages légales + `/accessibilite`, toasts, hors-ligne, `QueryError`, états vides, cloche paginée, code-splitting, lint a11y strict, notifications document/résa admin/absence admin, purge 90 j — 657 Pest + 212 Vitest. **Les 7 lots sont mergés** : décisions ouvertes et actions manuelles dans `todo_guillaume.md` §C13.6 et doc 08 « Avancement des lots ». Suivi détaillé par lot en fin du doc 08 |

---

### C14 — Refonte UI/UX du portail (post-recette §3.1)

> Ouvert le 2026-09-16 : UI jugée datée et calendrier des salles inutilisable en recette. Plan, décisions
> D1→D8 et prompt orchestrateur dans [`refonte_ui/`](./refonte_ui/01-plan-c14.md). Maquettes validées :
> https://claude.ai/artifact/NtkNBV4suphHbzQ7AKhFjT. Recette §3 à rejouer après ; §4→§7 indépendants.

| Code | Tâche | Statut | Notes |
|---|---|---|---|
| C14.0 | U0 docs : ADR-0013, PRD (écart nav ré-acté, §3.5.2), BRIEF §5 | ✅ | Sonnet — mergé 16/09 (`5ac65d4`) : ADR-0013, PRD §3.1/§3.5.2/§3.9 ré-actés, BRIEF §5.3, artboards `refonte_ui/maquettes/` |
| C14.1 | U1 design system : shadcn/ui réel (Tailwind v4), Geist fontsource, migration `components/ui` | ✅ | Opus — mergé 16/09 (`971f8d2`) : shadcn CLI 4.21 (style radix-nova, `radix-ui`, `tw-animate-css`), 25 primitives kebab-case + `NativeSelect` (react-hook-form) + `Spinner` conservé, jetons mappés sur le vert brand oklch (contrastes AA documentés dans `styles.css`), Geist auto-hébergée, `Modal`→`Dialog`, `ConfirmButton`→`AlertDialog`, ~94 call sites migrés ; review Opus soldée (persistance sidebar, TooltipProvider, focus des champs, `onInteractOutside`). 663 Pest + 226 Vitest + e2e 23/24 (échec « session expirée » préexistant sur main, à stabiliser en U2) |
| C14.2 | U2 shell : sidebar + top bar + bottom nav mobile, largeur par page | ✅ | Opus — mergé 16/09 (`05e2efc`) : `AppSidebar` (groupes principal/Administratif, gating inchangé via `useNavEntries`, état persisté), `TopBar` 60 px (titre via `PageHeader` en portail, contact, thème, cloche), bloc profil sur DropdownMenu, `BottomNav` 5 onglets + Sheet « Plus » (substitution testée sur 4 profils), `PageContainer` full/wide/narrow, `PublicLayout` restylé ; review Opus soldée (skip link vers le contenu après la top bar, nom du lien logo en mode icône). 663 Pest + 242 Vitest + e2e 24/24 (« session expirée » stabilisé). ⏸️ Point back pour Guillaume : `/api/user` n'expose pas l'entité → bloc profil affiche l'email |
| C14.3 | U3 agenda salles FullCalendar v7 (repli v6), alternative liste + bouton | ⬜ | Opus — ∥ C14.4/C14.5 |
| C14.4 | U4a pages : dashboard bento, factures DataTable, profil onglets | ⬜ | Sonnet |
| C14.5 | U4b pages : tickets, documents, actualités, annuaire + plan, présence | ✅ | Sonnet — mergé 16/09 (`2da6d16`) : Card/Badge/Table shadcn, `DeskDetailPanel` en Sheet (retour de focus câblé), `EmptyState` restylé, Skeletons de bloc, badge de présence du jour (règle : présent via `present_days`, absent seulement si une absence couvre le jour) ; review Opus soldée (contrastes liens `text-brand-700 dark:text-brand-300`, onglets annuaire, `th scope=row`, bouton absence sans bureau). 663 Pest + 252 Vitest + e2e 24/24 |
| C14.6 | U5 finition : axe clair/sombre, Lighthouse, recette §3 mise à jour, docs | ⬜ | Sonnet |

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
