# 04 — Backend PHP (qualité de code)

> Lu en profondeur : 21 modèles + concerns, 30 enums, 14 services, jobs/notifications/
> observers/rules/support/commands, 14 Resources Filament (forms/tables/pages), providers,
> contrôleurs API et Form Requests. Les findings 🔴 ont été re-vérifiés manuellement.

## Points forts

- **Architecture très propre** : logique métier systématiquement dans `app/Services/`, pages
  Filament qui délèguent (`EditInvoice` → `IssueInvoiceService`), contrôleurs API minces,
  Form Requests partout côté API.
- **Conventions largement respectées** : `declare(strict_types=1)` sur ~100 % du code écrit
  main, aucun `env()` hors config, aucun `Model::all()`, aucun `$request->all()`, aucun
  `DB::raw` dangereux, emails toujours en queue (`PortalNotification implements ShouldQueue`).
- **Modèles exemplaires** : fillable explicite, casts complets (enums, `decimal:2`),
  relations avec génériques, scopes nommés, **morph map enforced** — excellent filet.
- **Anti-double-booking salles bien conçu** : verrou applicatif + backstop GiST bornes
  semi-ouvertes, exception métier mappée 409.
- **Enums single-source** (`HasValues` → CHECK constraints via `Support\Database\Check`).
- Commentaires de code de très haute qualité, systématiquement reliés au PRD/data_model.

## Findings

### 🔴 Critique

**C1. Les achats créés dans l'admin ne génèrent AUCUN ticket — `PurchaseService` n'est
branché nulle part** *(vérifié : grep zéro usage en production)*
- `app/Filament/Resources/Purchases/Pages/CreatePurchase.php` : `CreateRecord` standard
  (INSERT `purchases` seul). `PurchaseService` (`createFromOffer`, `creditManual`) n'est
  utilisé que par les tests. Aucun observer sur `Purchase`, aucune UI de crédit manuel,
  aucune autre voie de création de `Ticket`.
- *Scénario* : l'admin crédite un « pack 10 tickets » (seule voie prévue au MVP, SUIVI
  C5.6/C7.5 pourtant ✅) → le membre a un solde de 0, ne peut rien réserver. **La
  fonctionnalité tickets est morte de bout en bout en prod.** Invisible aux tests actuels
  car les services sont testés isolément.
- *Fix* : hooker `CreatePurchase` sur `PurchaseService::createFromOffer` (via
  `handleRecordCreation()`), + action Filament pour `creditManual`.

**C2. Action Fortify `UpdateUserProfileInformation` cassée — route active**
*(vérifié : colonne `name` écrite lignes 40/53-54, feature activée `config/fortify.php:155`)*
- `users` n'a que `first_name`/`last_name` (raison d'être du `HasName` sur `User`).
  `PUT /user/profile-information` exige un champ `name` puis lève une erreur SQL. Doublon
  divergent avec le vrai flux (`ProfileController` + `UpdateProfileRequest`).
- *Fix* : adapter l'action (first/last) ou désactiver la feature. Au passage : les 4 fichiers
  `app/Actions/Fortify/*` n'ont ni `strict_types` ni `final`.

### 🟠 Majeur

**M1. Aucune stratégie de timezone — demi-journées « 9h–13h » construites en UTC**
*(vérifié : `config/app.php:68` = `'UTC'` sans env ; `RoomAvailabilityService:58-59,86-87,101`)*
- Un external réserve « matin » → créneau bloqué 09:00–13:00 **UTC** = 11h–15h heure de Paris
  en été. L'agenda du portail (ISO → heure locale) affichera le décalage ; un résident
  réservant « 9h–11h Paris » (07:00Z) ne sera pas en conflit avec le « matin » external alors
  qu'ils se marchent dessus physiquement. Idem `after:now`/`after_or_equal:today` (bornes de
  jour UTC) et le DateTimePicker admin.
- *Fix à trancher (mérite un ADR)* : `APP_TIMEZONE=Europe/Paris` (le plus simple en
  mono-tenant français, `timestamptz` gère le stockage), ou conversion explicite partout où
  l'on manipule des heures « métier ».

**M2. Le back-office Bookings contourne complètement `BookingService`**
- `CreateBooking.php`/`EditBooking.php` : create/update Eloquent directs. Conséquences :
  (a) conflit de créneau admin → **`QueryException` GiST brute (500)** au lieu d'un message ;
  (b) passage en `cancelled` via le Select `status` **sans restitution du ticket** consommé ;
  (c) `DeleteAction` supprime physiquement une résa liée à un ticket `used` → jamais restitué.
- C'est l'anti-pattern « logique métier dupliquée/absente côté Filament » (CLAUDE.md §7).
- *Fix* : `handleRecordCreation()` via `BookingService::create` + action « Annuler » dédiée.

**M3. Bureaux nomades : race sans backstop DB**
- `DeskAvailabilityService:70-98` : le `lockForUpdate()->exists()` ne verrouille rien quand
  **aucune ligne n'existe**. Deux requêtes concurrentes sur le même bureau/date/période
  créent deux occupations `present`. `desk_occupations` n'a ni UNIQUE ni exclusion
  (contrairement aux bookings/GiST).
- *Fix* : exclusion GiST équivalente ou index unique partiel
  `(desk_id, date, period) WHERE status='present'` (+ gestion full_day/half-day), avec catch
  du SQLSTATE comme dans `BookingService`. Même famille : `DeskOccupationForm` admin ne
  contrôle ni conflit ni cohérence ticket pour `source=external_ticket`.

**M4. Notifications « facture en retard » ré-émises en boucle** — voir [03](./03-facturation.md) F7.

**M5. Émission/annulation : check d'état hors transaction** — voir [03](./03-facturation.md) F1/F2.

**M6. `alreadyBilled` bloque l'entité entière** — voir [03](./03-facturation.md) F5.

**M7. La contrainte XOR des rôles d'usage n'est appliquée nulle part**
- `app/Rules/ExclusiveUsageRole.php` : uniquement référencée par les tests. Le Select
  multiple `roles` de `UserForm.php:52-62` n'a qu'un `helperText` — un admin peut attribuer
  `resident` + `external` simultanément, cassant les hypothèses de
  `StoreBookingRequest::isExternalBooker()`.
- *Fix* : `->rules([new ExclusiveUsageRole])` sur le Select.

**M8. `PresenceService::presentDays` : ~1 requête SQL par jour de la plage**
- La boucle appelle `isPresent()` → `candidateAbsences()` → `$user->deskAbsences()->get()`
  **ré-exécuté à chaque itération**. `GET /api/presence` sur 3 mois ≈ 90 requêtes identiques.
- *Fix trivial* : charger les absences une fois avant la boucle.

### 🟡 Mineur

1. Montants accumulés en `float` puis `number_format` (voir [03](./03-facturation.md) F14).
2. **SIRET et IBAN-last4 en `->numeric()`** (`CompanyForm.php:52-56,114-117`) : un
   SIRET/last4 commençant par 0 perd ses zéros de tête. TextInput + regex.
3. Docblocks `BelongsTo<resource, $this>` (minuscule — type réservé PHPDoc) : `Booking.php:48`,
   `MemberProfile.php:56`, `DeskOccupation.php:41`, `DeskAbsence.php:42`, `Resource.php:77/86`…
   Génériques faux pour l'analyse statique.
4. **Classes non-`final`** : tous les modèles, classes Filament et providers (~120 classes)
   contre la règle « final par défaut » (services/contrôleurs/policies le sont). Choix
   probablement pragmatique — à documenter ou automatiser.
5. `strict_types` absent : `app/Actions/Fortify/*.php` (4 fichiers), `Http/Controllers/Controller.php`.
6. `InvoicePaymentService::isOverdue()` : condition écrite deux fois (`isPast()` ET
   `greaterThan()` sur le même `endOfDay()`).
7. **Notifications sans `SerializesModels`** : `InvoiceIssued`/`InvoiceOverdue`/
   `AbsenceDeclared` embarquent des modèles complets dans le payload de queue.
8. `PurchaseService::createFromOffer` : retour `array` sans shape documentée, aucune garde
   `ticket_type !== null` (une offre `subscription` créerait des tickets invalides).
9. `DeskAvailabilityService::availableCount()` : hydrate tous les modèles pour un count →
   `->count()` SQL.
10. **`RoomController::availability` : `whereDate('starts_at', …)`** rate une résa à cheval
    sur minuit → le lendemain paraît libre. Chevauchement `[jour 00:00, J+1 00:00)`.
    (+ pas de filtre sur le type de ressource, cf. [02](./02-securite.md) I1.)
11. `TicketController::index` : liste non paginée (les tickets n'expirent jamais) + 2 counts
    fusionnables en un `groupBy`.
12. `NotificationController::markAllAsRead` : un UPDATE par ligne →
    `unreadNotifications()->update(['read_at' => now()])`.
13. **`IcsCalendarService`** : (a) flux sans borne temporelle (toutes les résas passées à
    vie) ; (b) `fold()` coupe à 73 **octets** via `str_split` → peut scinder un caractère
    UTF-8 (accents) en plein milieu, séquence invalide chez certains clients.
14. Type hints manquants : `$user` dans `BookingController::storeResident()/storeExternal()`.
15. UI morte : `ForceDelete(Bulk)Action` sur Users alors que `UserPolicy::forceDelete` = `false` en dur.
16. `BookingService::cancel` : pas de garde d'état (une résa `cancelled` peut être
    « ré-annulée », écrasant `cancelled_at`/`cancel_reason`).
17. **Incohérence salles/bureaux pour les external** : les salles exigent un jour ouvré
    (`FrenchHolidays`), la résa de bureau nomade n'a aucune restriction jour ouvré. À
    vérifier contre le PRD — probablement un oubli.
18. `PaymentForm` `amount` sans `minValue` (voir [03](./03-facturation.md) F11).
19. `successNotificationTitle` sur les actions custom (`EditInvoice:39`, `ViewInvoice:47`) :
    ne s'affiche que si le callback appelle `$action->success()` — à vérifier en Filament 5.
20. `FrenchHolidays::$cache` sans annotation `@var`.

### 💡 Suggestions (refactoring)

- **Dédupliquer le `MorphToSelect` billable + labels User/Company** : le bloc
  `match(true) { … instanceof User => fullName(), … => name }` est copié dans ≥ 6 fichiers
  (InvoiceForm, BookingForm, PurchaseForm, SubscriptionForm + tables). Règle CLAUDE.md §11
  (« pattern ×3 → extraire ») : un `App\Filament\Support\Billable::select()/label()`.
- `User::isExternalBooker()` : combinaison `can(CreatePaidBooking) && !can(CreateOwnBooking)`
  dupliquée entre `StoreBookingRequest` et `RoomController` — à remonter sur le modèle.
- Le motif `CarbonImmutable::parse($date->format('Y-m-d'))` revient ~8 fois → helper
  `toDateImmutable()` (troncature au jour).
- `MonthlyBillingService::generateMonth` : un `get()->groupBy(billable)` unique supprimerait
  le double fetch (acceptable à ~75 entités, mais plus simple).

## Synthèse

Socle de très bonne facture. Les deux critiques sont des **fonctionnalités câblées à moitié**
(tickets jamais générés depuis l'admin ; action Fortify scaffoldée jamais adaptée) —
invisibles aux tests car les services sont testés isolément. Les majeurs se concentrent sur
trois thèmes : **timezone non tranchée**, **divergence admin Filament vs services métier**,
**races/flip-flops du cycle facture**. Traiter C1/C2 immédiatement, puis M1 (ADR timezone)
avant toute mise en prod du module réservation.
