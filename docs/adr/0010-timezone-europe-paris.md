# ADR-0010 : Timezone applicative Europe/Paris

## Statut

Accepté — 2026-07-02

## Contexte

L'application tournait avec `'timezone' => 'UTC'` (défaut Laravel) alors que toutes les
heures « métier » sont exprimées en heure locale du coworking (Lyon) :

- les demi-journées external « 9h–13h / 14h–18h » (`RoomAvailabilityService`,
  `DeskAvailabilityService`) étaient construites via `setTime(9, 0)` → 09:00 **UTC**,
  soit 10h/11h heure de Paris selon la saison ;
- les règles de validation `after:now` / `after_or_equal:today` et les bornes de jour
  (`startOfDay`) basculaient au mauvais moment (minuit UTC ≠ minuit Paris) ;
- l'agenda SPA (qui affiche en heure locale du navigateur) et les DateTimePickers admin
  auraient montré des créneaux décalés de 1–2 h par rapport à l'intention.

Constat de la review du 2026-07-02 (`docs/review_fable/04-backend-php.md` M1). Le projet
est **mono-tenant, 100 % français** : il n'y aura jamais deux fuseaux à réconcilier.

## Décision

**`APP_TIMEZONE=Europe/Paris`** (nouvelle variable d'env, défaut du code identique dans
`config/app.php`). PHP/Carbon travaillent en heure de Paris (UTC+1/+2 selon DST, géré par
la base tz IANA) :

- `now()`, `setTime()`, `startOfDay()`, les validations de dates et les casts `datetime`
  raisonnent en heure locale du coworking — « 9h » signifie 9h à Lyon, été comme hiver ;
- le **stockage reste en `timestamptz`** (data_model §1) : PostgreSQL conserve l'instant
  absolu, aucune migration de données nécessaire ;
- les exports qui exigent l'UTC continuent de convertir **explicitement**
  (`IcsCalendarService::icsDateTime()` fait déjà `->utc()->format('Ymd\THis\Z')`).

## Alternatives considérées

- **Rester en UTC et convertir en `Europe/Paris` à chaque manipulation « métier »**
  (HALF_DAYS, bornes de jour, échéances) : plus « puriste », mais multiplie les points de
  conversion à ne jamais oublier (services, Form Requests, Filament, scheduler) pour un
  bénéfice nul en mono-tenant français. Rejeté (sur-ingénierie, CLAUDE.md §11).
- **Hardcoder `Europe/Paris` sans variable d'env** : suffisant, mais l'env var suit la
  convention du projet (config pilotée par env, §3.3) et facilite un éventuel debug.

## Conséquences

- `.env.example` : ajout `APP_TIMEZONE=Europe/Paris` (→ sync `.env` local + LastPass,
  cf. `todo_guillaume.md` 02/07/26).
- Le scheduler (`routes/console.php`) s'exécute désormais en heure de Paris : le cron de
  facturation « 1er du mois 06:00 » tombe à 6h locale (comportement voulu).
- Les timestamps sérialisés par l'API (`ISO 8601` avec offset `+02:00`/`+01:00`) restent
  correctement interprétés par la SPA (`Date` JS gère l'offset).
- Test de non-régression : les créneaux external générés doivent correspondre à
  9h/13h/14h/18h **Europe/Paris** (`C7ReservationTest`).
