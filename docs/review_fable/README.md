# Review globale — Ecoworking Manager

> Revue de code et d'architecture complète, réalisée le **2026-07-02** (Claude Fable 5).
> Périmètre : documentation (`docs/`), backend Laravel (`app/`, `database/`, `routes/`, `config/`),
> SPA portail (`portal-spa/`), tests (Pest + Vitest), CI. **Aucune modification de code** — analyse seule.
>
> Méthode : 6 revues spécialisées en parallèle (architecture/docs, backend PHP, sécurité,
> facturation, SPA/API, DB/tests/CI), findings critiques re-vérifiés manuellement,
> suites de tests et linters exécutés pour constater l'état réel.

## Documents

| Doc | Contenu |
|---|---|
| [01-architecture-et-documentation.md](./01-architecture-et-documentation.md) | Architecture hybride, ADRs, cohérence BRIEF↔PRD↔data_model↔SUIVI, périmètre MVP |
| [02-securite.md](./02-securite.md) | Isolation A/B, auth, IDOR, RGPD, stockage, tokens iCal |
| [03-facturation.md](./03-facturation.md) | Conformité §3.6 / CGI art. 289, numérotation, idempotence, avoirs, paiements |
| [04-backend-php.md](./04-backend-php.md) | Qualité code Laravel, services, Filament, timezone, performance |
| [05-spa-portail.md](./05-spa-portail.md) | React/TS, TanStack Query, a11y RGAA, contrats API, UX |
| [06-db-tests-ci.md](./06-db-tests-ci.md) | Migrations, contraintes, factories, couverture de tests, CI |
| [07-chantier-mvp-restant.md](./07-chantier-mvp-restant.md) | Prompt de mission pour développer les modules MVP manquants (C12) |

## État constaté (exécuté le 2026-07-02)

- **235 tests Pest verts** (566 assertions, `sail test --parallel`) — cohérent avec les « 232 » de SUIVI.md (daté 07/06).
- **Pint propre** (`--test`).
- **32 tests Vitest verts** — ⚠️ un test de `PresencePage` a échoué une fois sous forte charge machine (timeout `findByRole`), repasse systématiquement ensuite : sensibilité au timing à surveiller, pas une régression.

## Verdict global

**Projet d'une qualité nettement au-dessus de la moyenne.** Les règles non négociables du
CLAUDE.md sont réellement appliquées : isolation utilisateur auto-scopée et testée, aucun
`Model::all()` / `$request->all()` / `DB::raw` dangereux, argent en `DECIMAL` partout, logique
métier dans des services, contraintes DB en backstop (GiST anti-double-booking, UNIQUE
d'idempotence), audit log en liste blanche, secrets hors code. La documentation (BRIEF/PRD/
data_model/ADRs) est exceptionnelle en traçabilité des décisions. **Aucune faille de sécurité
critique ou élevée n'a été trouvée** — le point historiquement sensible (isolation A/B) est
correctement traité.

Les problèmes réels se concentrent sur **quatre thèmes transverses** :

1. **Fonctionnalités câblées à moitié** — les services métier existent et sont testés, mais
   certains points d'entrée ne les appellent pas : les achats créés dans l'admin ne génèrent
   **aucun ticket** (`PurchaseService` jamais branché — vérifié), le back-office Bookings
   contourne `BookingService` (pas de restitution de ticket à l'annulation admin), l'action
   Fortify de profil écrit une colonne `name` inexistante.
2. **Races et angles morts facturation** — double émission/annulation concurrente possible
   (trou de numérotation CGI art. 289, double avoir), avoir jamais matérialisé en PDF,
   suppression d'un brouillon récurrent rendant l'entité silencieusement infacturable,
   idempotence au grain entité empêchant tout rattrapage.
3. **Timezone non tranchée** — `APP_TIMEZONE=UTC` alors que les demi-journées « 9h–13h »
   sont construites en heure serveur : décalage de 1–2 h avec l'heure de Paris sur tout le
   module réservation. Mérite un ADR avant mise en prod.
4. **Illusion de complétude du MVP** — SUIVI affiche « prochaine étape : C11 puis V1.5 »,
   mais un pan entier du périmètre MVP décrit dans BRIEF §2 / PRD n'a jamais reçu de codes de
   tâches (annuaire + plan SVG, annonces côté portail, validation de documents, dashboard
   admin, gestion tickets admin, anonymisation RGPD…), et le **serving de la SPA en
   production n'est pas câblé** (pas de route catch-all, pas de build `public/portal/`).

## Top 12 des actions prioritaires

| # | Action | Sévérité | Doc |
|---|---|---|---|
| 1 | ✅ **Corrigé le 02/07** — Brancher `PurchaseService` sur la création d'achat Filament (tickets jamais générés) | 🔴 Critique | [04](./04-backend-php.md) |
| 2 | ✅ **Corrigé le 02/07** (feature désactivée, action supprimée) — action Fortify `UpdateUserProfileInformation` (colonne `name` inexistante) | 🔴 Critique | [04](./04-backend-php.md) |
| 3 | ✅ **Corrigé le 02/07** — Verrouiller émission/annulation de facture (`lockForUpdate` + re-check en transaction, F1/F2) | 🔴 Élevé | [03](./03-facturation.md) |
| 4 | ✅ **Corrigé le 02/07** (`InvoiceObserver::deleting` purge les liaisons, F4) — brouillon supprimé ⇒ entité refacturable | 🔴 Élevé | [03](./03-facturation.md) |
| 5 | ✅ **Corrigé le 02/07** — PDF de l'avoir dispatché à l'annulation (F3) | 🔴 Élevé | [03](./03-facturation.md) |
| 6 | ✅ **Tranché le 02/07** — `APP_TIMEZONE=Europe/Paris` (ADR-0010) | 🟠 Majeur | [04](./04-backend-php.md) |
| 7 | Réconcilier le périmètre MVP — plan d'action prêt : [07-chantier-mvp-restant.md](./07-chantier-mvp-restant.md) | 🟠 Majeur | [01](./01-architecture-et-documentation.md) |
| 8 | Câbler le serving SPA prod (catch-all + Blade + build `public/portal/`) — inclus dans C12.1 du doc 07 | 🟠 Majeur | [01](./01-architecture-et-documentation.md) |
| 9 | Activer le rate limiting sur `/api/*` (`throttleApi()` — absent par défaut en Laravel 11+) | 🟠 Majeur | [02](./02-securite.md) |
| 10 | Faire passer le back-office Bookings/DeskOccupations par les services (restitution ticket, conflits en 409 pas en 500) | 🟠 Majeur | [04](./04-backend-php.md) |
| 11 | Idempotence facturation au grain abonnement (pas entité) + résilience par entité du cron | 🟠 Majeur | [03](./03-facturation.md) |
| 12 | Backstop DB pour les bureaux nomades (exclusion GiST ou UNIQUE partiel — race applicative possible) | 🟠 Majeur | [04](./04-backend-php.md), [06](./06-db-tests-ci.md) |

Viennent ensuite (détail dans les docs) : ~~correction de `MonthlyBillingService::alreadyBilled`~~
(✅ corrigé le 02/07, idempotence au grain abonnement, F5) et de l'oscillation
`overdue↔partially_paid` (notifications dupliquées), recalcul de l'ancienne
facture quand un paiement change d'`invoice_id`, `composer.json` `^8.3`→`^8.5`, `down()` de la
migration `activity_log`, tests d'isolation HTTP manquants (liste bookings, DELETE occupations/
absences), gestion 401/419 + `lang/fr` côté SPA, recovery code 2FA inutilisable dans l'UI,
XOR des rôles d'usage non appliqué dans le formulaire admin.

## Ce qui est remarquablement bien fait

- **Sécurité/isolation** : auto-scoping systématique via `$request->user()`, 20 Policies,
  projections API explicites (zéro fuite IBAN/tokens/secrets), morph map verrouillée,
  stockage privé, OAuth Google strict sans auto-provisioning, 2FA obligatoire admin.
- **Argent** : numérotation par compteur verrouillé, brouillons sans numéro, montants figés
  à l'émission, `InvoicePolicy::delete()` → draft only, prix snapshotés (purchases) vs
  recalculés (subscriptions) exactement comme spécifié.
- **DB** : contraintes critiques toutes en place et conformes à data_model §6, `onDelete`
  explicites, `timestamptz`, soft deletes exactement sur les 7 tables prévues.
- **Docs** : hiérarchie des sources énoncée, 26 questions PRD tranchées et datées, ADRs
  honnêtes avec trade-offs réels (ADR-0007 « pas de Redis » est exemplaire).
- **Tests** : Pest sur PostgreSQL réel (locks représentatifs), assertions au centime,
  factories partout, tests d'isolation A/B réels et pertinents.

## Notes de transparence

- L'agent de revue DB a, malgré la consigne read-only, exécuté une vérification empirique
  via tinker sur la base **dev** : il reste **2 users factices soft-deletés** dans la table
  `users` locale (aucun fichier modifié). Un `sail artisan migrate:fresh --seed` les éliminera.
- Les findings 🔴 critiques ont été re-vérifiés manuellement (grep/lecture) avant publication ;
  les findings des agents non re-vérifiés individuellement sont signalés comme tels quand un
  doute subsiste.
