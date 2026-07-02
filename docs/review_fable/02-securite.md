# 02 — Sécurité & isolation des données

> Audit défensif : contrôleurs API, Form Requests, Resources JSON, les 20 Policies, routing,
> auth (Fortify/Sanctum/Google OAuth), flux iCal, Filament, stockage, audit log, config.

## Synthèse

**Très bon niveau de sécurité, aucune vulnérabilité critique ou élevée trouvée.** L'obsession
d'isolation du CLAUDE.md est visiblement appliquée : auto-scoping systématique, Policies
partout, projections explicites, aucun `Model::all()` dangereux, aucun `$request->all()` vers
Eloquent, aucun `DB::raw` avec entrée utilisateur. Les tests d'isolation croisée existent
réellement et couvrent les bons cas. Les points relevés sont du durcissement (moyen/faible/info).

## Ce qui est bien fait

- **Auto-scoping rigoureux** : tous les contrôleurs API dérivent l'identité de
  `$request->user()`, jamais d'ID utilisateur accepté du client.
  `NotificationController::markAsRead` fait `$request->user()->notifications()->findOrFail($id)`
  → pas d'IDOR. Idem Profile/Ticket/Presence.
- **Policies + Gate sur toutes les actions par objet** (`BookingController::destroy`,
  `DeskController::destroy`, `PresenceController::destroy`, `InvoiceController::downloadPdf`).
  Route model binding + Policy = pas d'IDOR sur les mutations.
- **Isolation facturation en double barrière** : `InvoiceController::scopeToBillingPerimeter()`
  réplique `InvoicePolicy::view()` (rôle `billing_contact` + `linkedCompanyIds`), avec
  `whereRaw('1 = 0')` (constante, sûre) par défaut. Le PDF repasse par
  `Gate::authorize('download')`. Brouillons jamais exposés (`whereNotNull('number')`).
- **Projections explicites** : `CurrentUserController` et toutes les `App\Http\Resources\*`
  listent les champs à la main. Aucune fuite de `calendar_token`, secrets 2FA, `password`,
  IBAN, remises négociées ou notes admin.
- **RGPD/IBAN** : seulement `sepa_iban_last4` en base et en formulaire. Secrets 2FA chiffrés
  (`encrypted`), `#[Hidden]` sur les champs sensibles. Audit log en **liste blanche**
  (`auditLogAttributes()`), jamais de PII sensible. `Sentry.send_default_pii=false`.
- **Auth admin** : `canAccessPanel()` = `isAdmin()`, 2FA TOTP obligatoire. OAuth Google
  strict (email vérifié + `hosted_domain` revérifié côté serveur + match compte existant,
  **pas d'auto-provisioning**). Rate limiting login 5/min (email|IP) et two-factor.
- **Stockage privé par défaut** (`local` → `storage/app/private`) : uploads Filament et PDF
  factures sur disque privé ; le portail sert les PDF via `Storage::download` derrière un
  Gate, pas d'URL publique.
- **Morph map verrouillée** (`enforceMorphMap`), anti-double-booking en double couche
  (lock applicatif + GiST).

## Findings

### 🟠 MOYEN

**M1 — Absence de rate limiting sur `/api/*`**
`bootstrap/app.php` appelle `statefulApi()` mais jamais `throttleApi()`. En Laravel 11+,
le groupe `api` n'embarque **aucun** `throttle` par défaut. Seuls `login` et `two-factor`
sont limités (Fortify).
*Scénario* : un compte authentifié (ou cookie volé) peut marteler `POST /api/bookings`,
`POST /api/desk-occupations`, `PATCH /api/profile` sans plafond.
*Reco* : `$middleware->throttleApi()` + throttle dédié sur les écritures sensibles, et un
test asserttant la présence du rate limit.

**M2 — Policies `create` trop permissives sur bureaux/absences**
`DeskOccupationPolicy::create()` et `DeskAbsencePolicy::create()` renvoient `true`
inconditionnellement, et les Form Requests correspondants n'ajoutent aucun contrôle —
contrairement à `BookingPolicy::create()` qui exige une permission. L'impact réel est borné
par les garde-fous métier (ticket requis, bureau attitré requis), mais l'autorisation ne doit
pas reposer sur eux.
*Reco* : aligner sur `BookingPolicy` (permission explicite) + test négatif pour un rôle non habilité.

### 🟡 FAIBLE

**F1 — Flux iCal « entité » : capability URL exposant les résas d'autres membres**
`CalendarFeedController::entity()` renvoie à quiconque détient le token les réservations de
tous les membres des mêmes entités. Le token (48 hex, 24 octets `random_bytes`) est de bonne
entropie, 404 sans énumération — solide. Le risque résiduel est inhérent aux URL de capacité
(historique navigateur, logs serveur/proxy, Referer, clients agenda tiers).
*Reco* : ne pas journaliser query/path complets sur ces routes, exposer clairement la
révocation (déjà implémentée), éventuellement tracer dernière IP/date d'usage.

**F2 — Pas de throttle sur les routes publiques par token (iCal) ni le callback OAuth**
Brute-force du token irréaliste (2^192), mais un throttle léger éviterait l'abus/DoS.

**F3 — `SESSION_SECURE_COOKIE` non forcé**
`same_site=lax` + cookies isolés par sous-domaine : cohérent. Mais `SESSION_SECURE_COOKIE=true`
est absent de `.env.example`, donc non garanti en prod.
*Reco* : l'ajouter à `.env.example` (⚠️ convention projet : prévenir Guillaume pour sync
LastPass) et au todo de déploiement.

### ℹ️ INFO

**I1 — `RoomController::availability` ne vérifie pas le type de ressource** : n'importe quel
`resource_id` (y compris un bureau) répond avec ses créneaux confirmés. Données peu sensibles
(plages anonymes), mais écart de moindre-exposition. Filtrer `type = meeting_room` ou 404.
(Voir aussi [04](./04-backend-php.md) : bug fonctionnel `whereDate` au même endroit.)

**I2 — Action Fortify `UpdateUserProfileInformation` cassée** : écrit une colonne `name`
inexistante, feature activée. Traité comme **critique fonctionnel** dans [04](./04-backend-php.md) (BCK-C2).

**I3 — `.env.example`** : aucun secret réel (vérifié). RAS.

## Couverture des tests d'isolation

Bonne. `AuthorizationTest` (14 cas) couvre les accès croisés A/B sur bookings, tickets,
achats, occupations, absences, consentements, factures (avec/sans `billing_contact`,
inter-entités), super-pouvoir admin, non-suppression de facture émise. Les tests API vérifient
401/403 croisés. `C9CalendarTest` couvre l'isolation iCal et le 404 anti-énumération.
`GoogleOAuthTest` couvre les 5 refus d'escalade.

**Manques** (détail dans [06](./06-db-tests-ci.md)) : isolation HTTP de `GET /api/bookings`
(liste), `DELETE /api/desk-occupations/{id}`, `DELETE /api/absences/{id}`, tickets de B
invisibles pour A ; test de présence d'un rate limit.

## Recommandations priorisées

1. **M1** — `throttleApi()` sur le groupe API. *Effort faible, impact réel.*
2. **M2** — Resserrer les deux Policies `create`.
3. **F3** — `SESSION_SECURE_COOKIE=true` en prod (+ `.env.example`).
4. **F1/F2** — Throttle léger routes publiques ; documenter le modèle de capacité iCal.
5. Compléter les tests d'isolation HTTP manquants.
