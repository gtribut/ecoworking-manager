# 06 — Base de données, tests & CI

> Périmètre : 32 migrations, 22 factories + 5 seeders, 36 fichiers de tests Pest, CI GitHub
> Actions, config projet (phpunit.xml, composer.json, compose.yaml).
> État constaté à l'exécution (2026-07-02) : **235 tests Pest verts** (566 assertions),
> **Pint propre**, **32 Vitest verts**.

## Verdict

Rigueur nettement au-dessus de la moyenne. **Aucun finding critique.** Le cœur à risque
(argent, anti-double-booking, isolation Policy) est implémenté ET testé conformément aux
règles non négociables. Les faiblesses se concentrent sur la **couche HTTP des endpoints
secondaires**, le **point d'entrée cron de la facturation**, deux **factories sémantiquement
incohérentes** et quelques dettes CI/config à faible coût.

## 1. Points forts (vérifiés)

- **Contraintes DB critiques toutes en place et conformes à data_model §6** : `btree_gist`
  en migration dédiée réversible ; exclusion GiST anti-double-booking mot pour mot
  (`create_bookings_table.php:59-63`, + CHECK `ends_at > starts_at` en bonus) ; index partiel
  unique domiciliation ; UNIQUE `invoices.number` nullable ; `invoice_counters.year` UNIQUE ;
  backstop idempotence UNIQUE `(subscription_id, period_start, period_end)`.
- **Argent irréprochable** : 100 % des montants en `DECIMAL(10,2)`, `vat_rate` `DECIMAL(5,2)`,
  aucun float. `timestamptz` systématique, dates pures en `date`.
- **`onDelete` explicite sur chaque FK** (restrict comptable / cascade enfants / set null
  optionnels), FK différées (cycles tickets↔bookings) avec `down()` propre. **Soft deletes
  exactement sur les 7 tables prévues.**
- **Règles §3.6 verrouillées par les tests** : numérotation multi-années, brouillon sans
  compteur, avoir miroir négatif lié, snapshots, `InvoicePolicy::delete()` refusé même à
  l'admin. Anti-double-booking testé aux 3 niveaux (GiST en SQL brut, service, API 409).
- **Assertions fortes** : montants au centime en chaînes exactes, canaux de notif par valeur,
  anti-fuite de secrets. **Aucun user/rôle hardcodé** — factories + traits partout.
- **Seed exactement conforme au PRD** : 8 SKU aux bons prix, 48 bureaux + 3 salles + 1 event
  room `requires_admin` — asserté dans `SeederTest`. Le piège `forgetCachedPermissions()`
  est correctement traité (`PermissionSeeder.php:33`).
- **CI saine** : Pest sur **PostgreSQL 18 réel** (locks représentatifs, pas SQLite), Pint
  `--test` bloquant, Biome + tsc + Vitest + build SPA, piège domaines/APP_KEY commenté dans
  `phpunit.xml:24-28`.
- Comptage honnête : 235 tests exécutables vs « 232 » dans SUIVI daté du 07/06.

## 2. Findings

### 🟠 Majeur

| # | Finding | Localisation |
|---|---|---|
| M1 | **Migration `activity_log` sans `down()`** — seule migration non réversible ; rollback laisse la table orpheline puis `migrate` échoue. Migration *personnalisée* (colonne `attribute_changes`), l'exception « vendor » ne s'applique pas | `2026_06_05_095305_create_activity_log_table.php` |
| M2 | **Isolation A/B non prouvée en HTTP sur plusieurs endpoints** : `GET /api/bookings` (scoping de liste — exactement le scénario de la vulnérabilité historique), `DELETE /api/desk-occupations/{id}` (zéro test, `DeskOccupationPolicy::delete` jamais exercé), `DELETE /api/absences/{id}`, `GET /api/tickets` (soldes testés, pas l'isolation) | `routes/api.php:47,57,60` |
| M3 | **Cron de facturation sans aucun test** — `invoices:generate-monthly` (parsing `--month`, défaut, code retour) jamais exercé ; seul le service l'est. C'est le point d'entrée production de la facturation récurrente | `GenerateMonthlyInvoicesCommand.php` |
| M4 | **Backstop UNIQUE `invoice_line_subscriptions` non testé** (la table du 07/06 a échappé à la campagne de tests de schéma du 05/06) | migration `2026_06_07_090000`:35 |
| M5 | **`SubscriptionFactory` : 2 users créés au lieu d'1** — la même instance `User::factory()` passée à `subscriber_id` ET `billable_id` est résolue **deux fois** par `expandAttributes()` → souscripteur ≠ billable par défaut, contraire au docblock et à la sémantique. Même motif `TicketFactory` (détenteur ≠ acheteur du purchase parent) | `SubscriptionFactory.php:31-46`, `TicketFactory.php:28-33` |
| M6 | **`DatabaseSeeder` non idempotent + admin avec mot de passe `password`** — second `db:seed` = violation unique email ; mot de passe par défaut sur une adresse admin réelle, dangereux si exécuté en prod. `firstOrCreate` + mdp env/aléatoire | `DatabaseSeeder.php:29-33` |
| M7 | **`composer.json` `"php": "^8.3"`** vs CI/Sail/CLAUDE.md en 8.5 | `composer.json:9` |
| M8 | **CI : pas de `permissions:`** (token hérite potentiellement de write) **ni d'audit de dépendances** (`composer audit`, `pnpm audit`, Dependabot) — notable pour un projet facturation + données perso | `.github/workflows/ci.yml` |

### 🟠 Moyen

1. **Bureaux nomades sans backstop DB** (recoupe [04](./04-backend-php.md) M3) : ni UNIQUE ni
   exclusion sur `desk_occupations` — l'anti-double-booking bureau repose uniquement sur le
   lock applicatif. Asymétrie avec le GiST des salles à trancher/documenter.
2. **Numérotation jamais mise sous course** : la séquence sans trou n'est testée que
   séquentiellement ; le `lockForUpdate` (mécanisme CGI art. 289) n'est pas prouvé en concurrence.
3. **PDF : assertion faible** — seul le préfixe `%PDF` est vérifié ; les mentions CGI art. 289
   annoncées en C6.2 ne sont jamais assertées ; garde no-op de `GenerateInvoicePdfJob` non couverte.
4. **Tests Filament = smoke tests** (`assertOk` de rendu) — un formulaire fonctionnellement
   cassé passerait vert ; compensé par 4 vrais tests d'actions. *(C'est précisément ce qui a
   rendu invisible le finding critique « tickets jamais générés », cf. [04](./04-backend-php.md) C1.)*
5. **Policies dormantes jamais exercées** : `AnnouncementRegistrationPolicy`,
   `MemberDocumentValidationPolicy` (aucun endpoint non plus — recoupe le « périmètre
   fantôme » de [01](./01-architecture-et-documentation.md)). Challenge 2FA complet au login
   Fortify non testé (activation seulement).

### 🟡 Mineur

1. **FK sans index** malgré la convention « toutes les FK » : `created_by`/`uploaded_by`
   partout, `ticket_id` (bookings, desk_occupations), self-FK avoir↔facture. Nuance :
   conformes aux listes *par table* du doc — contradiction interne à data_model.md.
   Prioriser `bookings.ticket_id` et les self-FK d'invoices.
2. `Check::enum()` générés depuis les enums PHP **courants** : si un enum évolue sans
   migration `DROP/ADD CONSTRAINT`, base fraîche et prod divergent silencieusement.
3. Codes SKU divergents : doc `desk_half_day_pack2`/`pack10` vs code `_pack_2`/`_pack_10`.
   Code cohérent, doc à aligner.
4. `billing_period` NULL sur les 5 SKU tickets/packs dans `OfferSeeder` alors que data_model
   dit `one_time` (le CHECK accepte NULL, passe silencieusement).
5. Faker en `en_US` (`config/app.php:85`) : adresses/téléphones américains pour un projet FR ;
   risque d'épuisement `fake()->unique()` sur `ResourceFactory` (pools ~1000/~180).
6. `PaymentFactory` : `amount` aléatoire décorrélé de la facture (`total_ttc=0`) ;
   `AnnouncementFactory::event()` fige la même date pour tout `count(n)`.
7. CI/config : pas de `timeout-minutes` (6 h par défaut), cache Composer sur `vendor/`,
   pas de `pint.json` (preset laravel effectif vs « PSR-12 » annoncé), pas de coverage,
   variables mortes `REDIS_*`/`MEMCACHED_HOST` dans `.env.example` (ADR-0007), `ResourceSeeder` :
   re-seed écrase les prix modifiés par l'admin (documenté) et un renommage de salle crée un doublon.
8. Résidus de scaffold : `tests/{Feature,Unit}/ExampleTest.php`, fonction `something()` vide
   dans `Pest.php:47-50`, `BrevoTransportTest` = simple `instanceof`.

## 3. Trous de couverture de tests

| Composant | Couverture actuelle | Manque |
|---|---|---|
| `BookingController::index` | 401 seulement | **Isolation A/B de la liste** |
| `DeskController::destroy` | Aucune | Annulation + 403 cross-user + restitution ticket via HTTP |
| `PresenceController::destroy` | Aucune | Suppression absence + 403 cross-user |
| `TicketController::index` | Soldes OK | A ne voit pas les tickets de B |
| `RoomController::availability`/`index` | Service testé (C7) / 401 | Test HTTP + 422 / champs exposés |
| `GenerateMonthlyInvoicesCommand` | Aucune | Test artisan `--month`, défaut, sortie |
| UNIQUE `invoice_line_subscriptions` | Aucune | Test de schéma (doublon → QueryException) |
| `GenerateInvoicePdfJob::handle` | Dispatch seulement | Garde brouillon/facture absente |
| `AnnouncementRegistrationPolicy`, `MemberDocumentValidationPolicy` | Aucune | Tests A/B |
| Numérotation concurrente | Séquentiel | Course 2 connexions |
| Contenu PDF (mentions CGI 289) | `%PDF` + chemin | Assertions mentions légales |
| Challenge 2FA login Fortify | Activation seulement | Login avec code TOTP |
| e2e Playwright + axe-core | Absents | Cohérent avec SUIVI (C11.3/C11.4 ⬜) |
| Création d'achat via Filament → tickets | Aucune (service testé isolément) | Test bout en bout admin (aurait attrapé [04](./04-backend-php.md) C1) |

SUIVI.md est globalement honnête (C11.2 marqué 🚧 à juste titre). Incohérence interne
mineure : ligne 125 « C6BillingTest (8) » vs ligne 124 « (11) » — le fichier contient 11 tests.

## 4. Écarts migrations ↔ data_model.md

| Écart | Doc | Réalité | Verdict |
|---|---|---|---|
| `users.app_authentication_*` (MFA Filament) | absent §4.1 | présent | Doc en retard (décision C3.1) |
| Table `settings` | listée §4.6 | package non installé, aucune migration | Doc en avance — à tracer |
| Tables `pulse_*` | absentes | présentes (C10) | Doc à compléter |
| `notifications.data` | jsonb §4.6 | `text` (stub Laravel officiel) | À trancher |
| `activity_log` | « migration paquet » | personnalisée, **pas de `down()`** | Doc à préciser + `down()` (M1) |
| Index partiel domiciliation | prédicat avec `offer=domiciliation` §6.3 | prédicat sans offre (plus strict, commenté) | Assumé — figer dans le doc |
| Index FK `created_by`/`ticket_id`/self-FK invoices | « toutes les FK » §1 | non indexées | Contradiction interne au doc |
| Index `purchases (billable_type, billable_id)` | non listé | présent | Migration plus complète que le doc |
| Codes SKU packs | `_pack2`/`_pack10` | `_pack_2`/`_pack_10` | Doc à aligner |
| **Tout le reste** (colonnes, types, onDelete, CHECK, uniques, soft deletes, enums) | — | — | **Conforme** |

## 5. Recommandations prioritaires

1. `down()` sur la migration `activity_log` (M1 — une méthode).
2. Compléter l'isolation A/B HTTP (M2 — règle d'or §3.1 du projet).
3. Tester `invoices:generate-monthly` + test de schéma du UNIQUE (M3/M4).
4. Corriger `SubscriptionFactory`/`TicketFactory` (closure partagée ou `recycle()`) et
   rendre `DatabaseSeeder` idempotent sans mot de passe `password` (M5/M6).
5. `composer.json` → `^8.5` ; CI : `permissions: contents: read`, `timeout-minutes`,
   `composer audit`/`pnpm audit` (M7/M8).
6. Passe de mise à jour de data_model.md (tableau §4).

---

> **Note d'incident (transparence)** : malgré la consigne read-only, l'agent de revue des
> factories a exécuté une vérification empirique via tinker sur la base **dev** Sail pour
> prouver M5 (création puis suppression de 1 subscription + 1 offer + 2 users). Les 2 users
> étant soft-deletés, **2 lignes factices avec `deleted_at` subsistent dans `users` en dev**.
> Aucun fichier du projet modifié. Un `sail artisan migrate:fresh --seed` les éliminera.
