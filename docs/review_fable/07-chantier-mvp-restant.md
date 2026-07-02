# 07 — Chantier « MVP restant » (prompt pour une prochaine session)

> **Comment utiliser ce document** : dans une prochaine session Claude Code, dire simplement
> « lance le chantier MVP restant (docs/review_fable/07) » — tout le contexte nécessaire est
> ci-dessous. Avant de démarrer, trancher les arbitrages de la section 2 (5 minutes).
>
> Origine : la review du 2026-07-02 ([01-architecture-et-documentation.md](./01-architecture-et-documentation.md) §4)
> a montré qu'un pan du périmètre MVP décrit dans BRIEF §2 et le PRD n'a jamais reçu de
> codes de tâches dans SUIVI.md — SUIVI affichait « prochaine étape : C11 puis V1.5 » alors
> que ces modules n'existent pas dans le code.

---

## Prompt de mission

Tu travailles sur `ecoworking-manager`. Lis d'abord `CLAUDE.md`, `docs/SUIVI.md`,
puis les sections du PRD citées pour chaque lot ci-dessous. Le périmètre fonctionnel de
référence est **BRIEF §2 + PRD** ; ce document liste ce qui manque, par lots priorisés.

**Première action obligatoire** : créer une section **C12 — Modules MVP restants** dans
`docs/SUIVI.md` avec les codes de tâches ci-dessous (en excluant les lots que Guillaume a
dé-scopés, section 2), puis avancer lot par lot selon le cycle standard du projet
(TDD, Policies + tests d'isolation A/B pour tout nouvel endpoint, a11y RGAA AA côté SPA,
commit conventionnel par lot, mise à jour SUIVI au fil de l'eau).

**Règles de la maison à ne pas oublier** (elles ont déjà sauvé le projet plusieurs fois) :
- tout endpoint portail : `auth:sanctum` + auto-scope `$request->user()` + Policy + test A/B ;
- pas de logique métier dans les Resources Filament → services ;
- toute décision structurante → ADR ; toute action manuelle Guillaume → `todo_guillaume.md`.

---

## 1. État des lieux (vérifié le 2026-07-02)

Fait et solide : auth (C2), back-office des 15 Resources (C3), API portail C4, SPA C5
(profil/factures/résa/tickets/présence/notifs/iCal), facturation C6, réservations C7,
notifications C8, iCal C9.2, observabilité C10. 238 tests Pest + 32 Vitest verts.

Manquant (MVP « papier ») : les lots ci-dessous. Aucun n'a de code de tâche, d'endpoint,
ni de ligne de code — sauf mention contraire.

## 2. Arbitrages — ✅ TRANCHÉS par Guillaume le 2026-07-02

| # | Question | Décision |
|---|---|---|
| A | Plan interactif SVG des étages (PRD §3.7.2) | ✅ **MVP** — à faire dans C12.5, avec son alternative accessible obligatoire |
| B | Magic link (PRD §3.2, Q6) | ✅ **Maintenant** (MVP) — nouveau lot C12.8a |
| C | Audit log UI / gestion rôles UI / Settings (PRD §4.13-4.15) | ✅ **MVP** — nouveau lot C12.8b |
| D | Flux changement email membre (PRD §3.4.5) | ✅ **Admin-only** : seul un admin peut modifier l'email d'un membre (via le back-office User). Le membre ne peut PAS changer son email lui-même — aucun flux self-service à construire. Le reset de mot de passe par email (Fortify) existe déjà et suffit |

Conséquences de D : vérifier simplement que `UserForm` (Filament) permet bien à l'admin
d'éditer l'email (unicité validée) et qu'aucun endpoint portail n'expose l'email en
écriture (déjà le cas : `/api/profile` l'exclut, la feature Fortify
`updateProfileInformation` a été désactivée le 02/07). Amender PRD §3.4.5 pour acter la
décision. **Tous les lots ci-dessous sont donc MVP ferme.**

## 3. Lots de travail proposés (ordre recommandé)

### C12.1 — Serving SPA en production 🔴 (bloquant déploiement)
Réf. : BRIEF §6 « Build & serving SPA », ADR-0004.
- Build Vite vers `public/portal/` (config `base`, manifest), vue Blade `portal-spa`
  injectant les assets, route catch-all `Route::domain(portail)->get('/{any?}', …)`
  excluant `/api` et `/sanctum`, suppression/contrainte de la route `/` welcome.
- Vérifier le flux CSRF Sanctum en même origine + un test Pest (la route sert le HTML,
  `/api/*` non intercepté).

### C12.2 — Tickets côté admin (compléter le fix review C1)
Réf. : PRD §4.8.1.
- `TicketResource` Filament (liste filtrable par membre/type/statut, lecture seule sur le
  cycle de vie — le statut vit via les services).
- Action « Crédit manuel » branchée sur `PurchaseService::creditManual()` (le service
  existe et est testé, il n'a **aucune UI**) : membre, type, quantité, raison.
- Action « Consommation manuelle » si PRD la prévoit (vérifier §4.8.1).
- Tests Livewire + test que le crédit manuel trace `credited_by`/`credit_reason`.

### C12.3 — Annonces & événements côté portail
Réf. : PRD §3.3.2, §4.11 (la Resource admin existe déjà).
- Endpoints : `GET /api/announcements` (publiées, scopées par audience),
  inscriptions événements (`POST/DELETE /api/announcements/{id}/registration`,
  table `announcement_registrations` déjà migrée, `AnnouncementRegistrationPolicy`
  existe mais n'est **jamais exercée** — la tester A/B).
- SPA : feature `announcements/` (liste + détail + RSVP), bloc « à la une » sur le dashboard.
- Emails/notifs éventuels selon PRD (vérifier si notification de publication est MVP).

### C12.4 — Documents (internes à valider + administratifs) côté portail
Réf. : PRD §3.3.2, §3.6.3, §5.3 (Resources admin existantes).
- Endpoints : liste des documents internes applicables + validation
  (`member_document_validations` migrée, `MemberDocumentValidationPolicy` jamais exercée),
  liste + téléchargement des documents administratifs de mes entités (miroir du périmètre
  `billing_contact`/entités liées — s'inspirer d'`InvoiceController`).
- SPA : bloc « documents à valider » sur le dashboard (PRD §3.3, bloquant à l'accueil ?
  vérifier la règle exacte §5.3), page documents.
- ⚠️ Téléchargements : disque privé + `Gate::authorize`, comme les PDF factures.

### C12.5 — Annuaire des coworkers + plan SVG des étages (arbitrage A : MVP)
Réf. : PRD §3.7, §4.12.
- Endpoint `GET /api/directory` : **uniquement** les membres opt-in
  (`directory_opt_in` — le champ existe et l'opt-in est déjà testé dans AuthorizationTest),
  projection minimale (nom, photo, entreprise, bio) — pas d'email/téléphone sans opt-in
  explicite du PRD.
- SPA : feature `directory/` (liste + recherche). Plan SVG : alternative accessible
  obligatoire (équivalent texte de l'occupation, CLAUDE.md §3.5).

### C12.6 — Dashboard admin + vue « Occupation du jour »
Réf. : PRD §4.1, §4.8.4.
- Widgets Filament : KPIs (membres actifs, CA du mois émis, factures overdue, résas du
  jour), alertes (impayés, fins d'abonnement). Logique dans des services/queries testées,
  widgets minces.
- Page Filament custom « Occupation du jour » : par étage, bureaux assignés/présents/libres
  + salles (réutiliser `PresenceService`/`DeskAvailabilityService` — attention au finding
  perf M8 de la review, corriger le N+1 avant de l'appeler en boucle sur 48 bureaux).

### C12.7 — Anonymisation RGPD (action admin)
Réf. : PRD §5.6, CLAUDE.md §3.4.
- `AnonymizeUserService` : soft delete + écrasement PII (nom, email, téléphone, photo,
  bio…), conservation des factures (10 ans), `anonymized_at` (colonne déjà migrée),
  révocation tokens/sessions/calendar_token.
- Action Filament avec confirmation forte + entrée d'audit log.
- Tests : PII effacée, factures intactes, l'utilisateur ne peut plus se connecter.

### C12.8a — Magic link (arbitrage B : maintenant)
Réf. : PRD §3.2 (Q6 « V1 »).
- Envoi d'un lien de connexion à usage unique par email (membre portail uniquement, jamais
  admin — l'admin garde mdp + 2FA obligatoire). Token signé à expiration courte (~15 min),
  usage unique, rate-limité, invalidé au changement de mot de passe.
- Endpoints : demande (`POST /magic-link`, réponse identique que l'email existe ou non —
  anti-énumération) + consommation (route signée, domaine portail).
- SPA : option « recevoir un lien de connexion » sur la page login.
- Tests : usage unique, expiration, pas d'escalade admin, anti-énumération, rate limit.

### C12.8b — Audit log UI, gestion des rôles UI, Settings (arbitrage C : MVP)
Réf. : PRD §4.13, §4.14, §4.15.
- Audit log : Resource Filament en lecture seule sur `activity_log` (filtres sujet/causer/
  event/date). Les données s'écrivent déjà (trait `Auditable`).
- Rôles : UI d'affectation des rôles (la contrainte XOR `ExclusiveUsageRole` est appliquée
  dans UserForm depuis le 02/07) + vue de la matrice rôles → permissions (lecture seule,
  la composition est seedée).
- Settings : vérifier le périmètre exact PRD §4.15 avant d'installer `spatie/laravel-settings`
  (data_model §4.6 liste une table `settings` non créée — l'installer ou amender le doc).

### C12.9 — Acter la décision D (email admin-only)
- Amender PRD §3.4.5 : changement d'email = acte admin (back-office), pas de flux membre.
- Vérifier l'édition d'email dans `UserForm` (unicité) + test ; confirmer qu'aucun endpoint
  portail n'accepte l'email en écriture (déjà le cas au 02/07).

## 4. Définition de « MVP terminé »

Après C12 : mettre à jour BRIEF §18 + SUIVI (position actuelle), puis dérouler C11.3/C11.4
(e2e Playwright + axe-core) sur les écrans critiques **y compris les nouveaux**, et
seulement ensuite ouvrir V1.5 (déploiement). Les corrections issues de la review
([README](./README.md) top 12, notamment les findings facturation F1-F7 non traités et le
rate limiting API) devraient être intercalées avant la mise en prod.
