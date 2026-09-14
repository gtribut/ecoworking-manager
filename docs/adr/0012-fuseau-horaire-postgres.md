# ADR-0012 : Décalage horaire porté par le format de date Postgres

## Statut

Accepté — 2026-09-14

## Contexte

L'application tourne en `Europe/Paris` (ADR-0010) et la session Postgres était en `UTC`. Les colonnes temporelles sont des `timestamptz` (instants absolus).

Eloquent sérialise les `Carbon` avec le format du *query grammar*, soit `Y-m-d H:i:s` — **sans décalage horaire**. Deux `Carbon` désignant le **même instant** produisaient donc deux chaînes différentes, que Postgres interprétait toutes deux comme de l'UTC :

| Origine du `Carbon` | Instant visé | Chaîne envoyée | Interprété par PG (session UTC) | Verdict |
|---|---|---|---|---|
| PHP en heure de Paris (`now()`, `halfDayBounds()`, seeders, Filament) | 12:00 UTC | `14:00:00` | 14:00 UTC | ❌ **+2 h** |
| SPA (`toISOString()`, ISO UTC) | 12:00 UTC | `12:00:00` | 12:00 UTC | ✅ |

Conséquences observées : les réservations créées par l'admin, par les `external` (demi-journées) ou par les seeders s'affichaient décalées de 2 h dans le calendrier du portail. Le même format faussait aussi les **comparaisons** `where` (`Connection::prepareBindings()` utilise le format du grammar), ce qui avait déjà été contourné au cas par cas — `BookingPolicy` comparait `starts_at->isFuture()` en PHP et laissait une résa déjà commencée annulable ~2 h.

En lecture, le bug se compensait partiellement : `createFromFormat('Y-m-d H:i:s', '… +00')` échoue en « Trailing data », Laravel retombe sur `Date::parse()` qui honore le décalage et rend un `Carbon` en **UTC**. Un `->format('H:i')` affichait donc l'heure UTC — ce qui, combiné à une écriture fausse de +2 h, **redonnait par hasard la bonne heure murale**. Plusieurs tests reposaient sur cette double compensation.

## Décision

Deux changements **indissociables** :

1. **`App\Database\Query\PostgresGrammar`** — format de date `Y-m-d H:i:sP`, avec décalage. Branché sur l'événement `ConnectionEstablished` (`AppServiceProvider`) pour ne pas résoudre la connexion au boot.
2. **`'timezone' => env('APP_TIMEZONE', 'Europe/Paris')`** sur la connexion `pgsql` — la session Postgres passe en heure de Paris, donc les `timestamptz` relus reviennent en heure locale et `->format('H:i')` affiche l'heure attendue (PDF, emails, iCal, Filament).

> ⚠️ **L'ordre du raisonnement importe.** Le réglage n°2, **posé seul**, ne corrigerait rien : il ne ferait qu'**inverser** le bug, en cassant les écritures de la SPA (ISO UTC) aujourd'hui correctes tout en réparant celles de PHP. C'est le n°1 qui rend le fuseau de session **sans effet sur les écritures** — et donc le n°2 sûr.

Le point d'accroche est le **query grammar** et non un `$dateFormat` posé sur les modèles, car lui seul couvre les deux chemins :

- écritures, via `Model::getDateFormat()` qui retombe sur le grammar ;
- comparaisons `where`, via `Connection::prepareBindings()`.

## Conséquences

### Bénéfices

- Un instant écrit est le même quel que soit le fuseau du `Carbon` d'origine — les deux chemins (PHP/Paris et SPA/UTC) convergent.
- Les comparaisons SQL portant un `Carbon` en heure de Paris visent le bon instant : plus besoin de contourner au cas par cas.
- Les lectures rendent des `Carbon` en heure de Paris, comportement idiomatique attendu avec `APP_TIMEZONE=Europe/Paris`.
- Le fuseau de la session Postgres n'est plus structurant : changer `APP_TIMEZONE` reste cohérent de bout en bout.

### Trade-offs assumés

- **Les données écrites avant ce correctif sont décalées de +2 h** et doivent être réécrites. Traité par un `migrate:fresh --seed` : la décision a justement été prise **avant le provisioning Clever Cloud**, quand la base de dev est encore jetable. Après la mise en production, le même correctif aurait imposé une migration de données à risque sur des données de facturation.
- Un `Carbon` naïf (sans fuseau explicite) prend le fuseau PHP par défaut, donc Paris. C'est le comportement voulu, mais cela reste un implicite à connaître.
- Les colonnes `date` (facturation : `issued_at`, `due_at`, `paid_at`, périodes) sont **insensibles** au décalage : Postgres l'ignore en castant vers `date`, sans glissement d'un jour. Verrouillé par un test dédié, le cas limite « minuit à Paris » compris.

## Alternatives considérées

### `'timezone' => 'Europe/Paris'` seul (correctif candidat de `docs/review_fable/08`)

**Pourquoi écarté** : inverse le bug au lieu de le corriger — les écritures de la SPA, aujourd'hui justes, deviendraient fausses. C'est le piège principal de ce chantier.

### `protected $dateFormat` sur chaque modèle

**Pourquoi écarté** : ne couvre pas `Connection::prepareBindings()`, donc laisse les comparaisons `where` fausses ; et impose de ne pas oublier ~25 modèles, puis chaque nouveau.

### Convertir en UTC à chaque écriture dans le code métier

**Pourquoi écarté** : correctif diffus, à réappliquer à chaque nouvel appel, exactement le type d'oubli silencieux que l'on cherche à éliminer.

## Références

- `app/Database/Query/PostgresGrammar.php`, `config/database.php` (`pgsql.timezone`), `app/Providers/AppServiceProvider.php`
- `tests/Feature/Database/TimezoneRoundTripTest.php` — écriture, comparaison `where`, non-régression des colonnes `date`
- ADR-0010 (timezone `Europe/Paris`)
- `docs/review_fable/08-ecarts-prd-portail.md` — « Écarts hors lot »
