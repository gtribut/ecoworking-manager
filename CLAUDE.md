# CLAUDE.md — Projet Ecoworking

> Fichier de contexte permanent pour Claude Code.
> À lire intégralement avant **toute** intervention sur ce projet.
> Mise à jour : à versionner à chaque évolution structurante.

---

## 0. Lecture obligatoire avant toute action

Avant de proposer du code, **toujours** :

1. Lire `docs/BRIEF.md` — source de vérité technique et fonctionnelle
2. Lire `docs/PRD.md` — spec fonctionnelle détaillée (portail client + back-office admin)
3. Consulter `docs/SUIVI.md` — **état d'avancement** (quelle phase/tâche est faite, en cours, à faire). Mettre à jour le statut après chaque tâche terminée.
4. Lire les `docs/adr/*.md` pertinents — décisions architecturales
5. Si demande ambiguë : poser des questions de clarification **avant** de coder
6. Si demande qui contredit le BRIEF : signaler la contradiction explicitement

> **Règle d'or** : aucun code écrit sans contexte lu. Si BRIEF.md indique une convention, l'appliquer sans la remettre en cause sauf si la demande de l'utilisateur le fait explicitement.

---

## 1. Projet en une ligne

Outil de gestion sur-mesure pour Ecoworking (coworking lyonnais, SARL, ~50 membres, ~75 entreprises). Remplace Cosoft. Mono-tenant. Laravel 13 + Filament 5 (admin) + Vite/React/TS (portail SPA) + PostgreSQL 18, hébergé sur Clever Cloud.

---

## 2. Stack & arborescence rapide

- **Backend** : PHP 8.5+, Laravel 13, PostgreSQL 18 (cache, sessions et queues sur Postgres — pas de Redis en MVP, cf. ADR-0007)
- **Admin** : Filament 5, servi sur `admin.ecoworking.fr` exclusivement
- **Portail membre** : SPA **React 19.2+** + **TypeScript 6.0+** strict + **Vite 8** (Rolldown) + **React Router v7.14+** + **TanStack Query 5.100+** + **Tailwind v4.3+** + shadcn/ui, servi sur `portail.ecoworking.fr` exclusivement
- **Auth admin** : Fortify (sessions + 2FA TOTP) + Socialite Google
- **Auth portail** : Sanctum mode SPA (cookies + CSRF)
- **Tests** : Pest (PHP) + Playwright (e2e)
- **Lint** : Pint (PHP) + Biome (TS/React)
- **Dev local** : Laravel Sail (Docker Compose) sous WSL2 Ubuntu

Arborescence repo :
```
ecoworking-manager/
├── app/            # Code Laravel (Models, Controllers, Filament, Services, Jobs)
├── config/         # Configuration Laravel
├── database/       # Migrations, seeders, factories
├── portal-spa/     # Projet SPA React Vite (autonome)
├── public/         # Assets publics (build SPA exposé via public/portal/)
├── resources/      # Vues Blade (minimales), CSS/JS admin Filament
├── routes/         # api.php, web.php (routes par domaine)
├── tests/          # Pest (Feature, Unit), Playwright (e2e)
├── docs/           # BRIEF.md, PRD.md, adr/, data_model.md
└── CLAUDE.md       # ce fichier
```

---

## 3. Règles critiques — non négociables

### 3.1 Sécurité & isolation données

> Le porteur du projet a vécu une vulnérabilité d'isolation données critique dans un projet précédent (`SharedUserData::all()` exposant des données entre tenants). **Cette catégorie d'erreur est inacceptable ici.**

- **Jamais** de `Model::all()` ou `Model::find($id)` sans scope d'autorisation
- **Toujours** passer par une **Policy** Eloquent pour les accès non-admin
- **Toujours** utiliser des **Global Scopes** ou des **Local Scopes** explicites sur les ressources liées à un utilisateur
- En cas de doute sur l'isolation : écrire un test `tests/Feature/AuthorizationTest.php` qui vérifie qu'un user A ne peut PAS accéder aux données d'un user B
- **Jamais** d'`auth()->id()` dans une query brute sans vérifier le contexte (l'utilisateur peut être un admin agissant pour un membre)
- **Toutes** les routes API portail passent par `auth:sanctum` middleware obligatoire

### 3.2 Validation & input

- **Jamais** de `$request->input()` direct vers Eloquent (`Model::create($request->all())` est interdit)
- **Toujours** un **Form Request** validé (`StoreXxxRequest`, `UpdateXxxRequest`)
- Validation côté front (Zod) en plus de la validation back, jamais en remplacement
- Pour la SPA : valider aussi via Form Request côté Laravel, retourner 422 avec format Laravel standard

### 3.3 Secrets & credentials

- **Jamais** de hardcode de clés, tokens, mots de passe, URLs sensibles
- Tout passe par `.env` + `config/*.php` avec `env()` (et `config()` côté code, pas `env()`)
- `.env` n'est jamais commité, `.env.example` toujours à jour
- Si une nouvelle config tierce est introduite : ajouter au `.env.example` ET dans la section 13 de `BRIEF.md`

### 3.4 RGPD

- Aucune donnée personnelle stockée sans justification métier explicite
- IBAN complet **jamais** stocké en clair → uniquement les 4 derniers chiffres + mandat SEPA en PDF chiffré
- Soft delete users avec **anonymisation** (factures conservées pour conformité fiscale 10 ans, mais données perso anonymisées)
- Logs : pas de données personnelles dans les logs applicatifs (PII redaction)
- Audit log automatique sur entités sensibles via `spatie/activitylog` (User, Company, Invoice, Subscription, Booking, Payment)

### 3.5 Accessibilité (RGAA cible)

- Le portail vise une **conformité RGAA 4.1 niveau AA** (basé WCAG 2.1 AA). Pas une obligation légale pour Ecoworking, mais une démarche qualité du projet.
- Toute génération de composant React/JSX doit respecter par défaut :
  - Sémantique HTML5 correcte (`<header>`, `<nav>`, `<main>`, `<button>` pour actions, `<a>` pour navigation, hiérarchie `h1` → `h6`)
  - Labels associés à tous les inputs (`<label htmlFor>` ou `aria-label`)
  - `alt` sur toutes les images (vide `alt=""` pour décoratives, descriptif sinon)
  - ARIA labels/roles sur composants custom interactifs (modals, dropdowns, calendrier, plan SVG)
  - Focus visible (pas de `outline: none` sans alternative)
  - Tab order logique, navigation 100% clavier
  - Contrastes WCAG AA minimum (4.5:1 texte normal, 3:1 texte large)
  - Animations respectant `prefers-reduced-motion`
- Lint : `eslint-plugin-jsx-a11y` en strict, **ne pas désactiver les règles** sans justification documentée
- Tests : `axe-core` intégré aux tests Playwright e2e a11y sur les écrans critiques
- Composants complexes (calendrier résa, plan des étages SVG) doivent avoir une **alternative accessible** (vue liste pour le calendrier, équivalent texte de l'occupation des bureaux pour le SVG)
- Avant chaque release majeure : audit manuel avec Pa11y / Lighthouse + test rapide lecteur d'écran (NVDA gratuit Windows)

### 3.6 Argent & numérotation

- Numérotation factures **chronologique sans trou** (CGI art. 289) → compteur DB avec transaction `SELECT ... FOR UPDATE`
- **Aucun** brouillon de facture ne consomme le compteur (numérotation uniquement à l'émission définitive)
- Montants : `DECIMAL(10,2)`, **jamais** `FLOAT` ou `DOUBLE`
- Calculs HT/TVA/TTC : toujours côté back, jamais faire confiance au front
- Les montants d'une **facture émise** sont figés sur ses lignes (`invoice_lines`) à l'émission — jamais recalculés ensuite (conformité)
- Une **facture émise ne se supprime JAMAIS** (interdit légalement) : seule voie de correction = **annulation + avoir** (passage `cancelled` + avoir auto). `InvoicePolicy::delete()` → `false` si statut ≠ `draft` (seuls les brouillons, sans numéro, sont supprimables)
- `purchases` (tickets, ponctuels) : prix snapshoté au moment de l'achat
- `subscriptions` (récurrents) : **pas** de prix figé à la souscription — le montant est recalculé à chaque facturation depuis le **catalogue courant** (mis à jour ~1×/an, applicable à tous dès validation) modulé par la **remise négociée de l'entité** si présente (cf. PRD §6.4)

### 3.7 Routing par sous-domaine

- Filament panel : `$panel->domain('admin.ecoworking.fr')` configuré dans le Panel Provider, chemin racine `/`
- SPA portail : `Route::domain('portail.ecoworking.fr')` dans `routes/web.php`
- API `/api/*` : sur `portail.ecoworking.fr` uniquement (même origine pour la SPA, pas de CORS)
- **Jamais** de route admin sur le domaine portail et vice-versa
- Cookies isolés par sous-domaine (`SESSION_DOMAIN=null`)

---

## 4. Conventions de code

### 4.1 PHP / Laravel

**Structure** :
- Models : `App\Models\` (singulier, ex. `User`, `Booking`)
- Tables DB : pluriel, snake_case (`users`, `bookings`)
- Controllers Web (admin redirect) : `App\Http\Controllers\`
- Controllers API portail : `App\Http\Controllers\Api\` (sous-namespaces par module si besoin : `Api\Bookings\`, `Api\Invoices\`)
- Form Requests : `App\Http\Requests\` (`StoreBookingRequest`, `UpdateBookingRequest`)
- Policies : `App\Policies\`, auto-discovery Laravel 13
- Services métier : `App\Services\` (`BookingService`, `InvoiceNumberingService`, etc.) — logique réutilisable, **hors HTTP**
- Jobs : `App\Jobs\`, suffixe `Job` (`SendInvoiceJob`, `SyncBookingToGoogleCalendarJob`)
- Mail : `App\Mail\`, suffixe `Mail` (`InvoiceIssuedMail`)
- Filament Resources : `App\Filament\Resources\`
- DTOs (si besoin) : `App\Data\` via `spatie/laravel-data`

**Style** :
- PSR-12 enforced via Pint (`./vendor/bin/sail pint`)
- Type hints partout (paramètres ET retours)
- `declare(strict_types=1);` en tête de chaque fichier PHP
- Final classes par défaut sauf si héritage prévu explicitement
- Readonly properties pour DTOs/value objects
- PHP 8.5 features encouragées : constructor property promotion, enums, readonly, match expressions, named arguments quand ça améliore la lisibilité

**Eloquent** :
- Relations : nom explicite (`bookings()`, `memberProfile()`, `billableEntity()`)
- Eager loading systématique pour éviter N+1 (`->with([...])`)
- Casts pour types complexes (`'starts_at' => 'datetime'`, `'features' => 'array'`)
- `$fillable` ou `$guarded` toujours défini
- Scopes locaux explicites pour les requêtes métier courantes (`->scopeActive()`)
- **Jamais** de `Model::all()` sans pagination/scope si la table peut grandir

**Migrations** :
- **Toujours réversibles** (méthode `down()` propre)
- Indexes déclarés explicitement (foreign keys, colonnes filtrées fréquemment, colonnes `status`/`type`)
- Foreign keys avec `onDelete` explicite (`cascade`, `restrict`, `set null`)
- Soft deletes (`->softDeletes()`) sur entités sensibles uniquement (users, companies, invoices)

### 4.2 TypeScript / React

**Strict mode** :
- `"strict": true` dans `tsconfig.json`
- `noUncheckedIndexedAccess: true`
- Pas de `any` (utiliser `unknown` + narrowing si vraiment besoin)
- Pas de `// @ts-ignore` (`// @ts-expect-error` toléré ponctuellement avec commentaire)

**Composants** :
- Functional components uniquement, hooks
- Naming : `PascalCase` pour composants, `useCamelCase` pour hooks, `camelCase` pour utilitaires
- 1 composant principal par fichier, nom de fichier = nom du composant
- Pas de default export pour les composants (named exports facilitent le refactoring)

**Imports** :
- Alias absolus via `@/*` (configuré dans `vite.config.ts` et `tsconfig.json`)
- Ordre : externals → alias internes → relatifs
- Auto-tri via Biome

**État** :
- État serveur (données API) : **TanStack Query exclusivement** (jamais `useState` + `useEffect` pour ça)
- État UI local : `useState` ou `useReducer`
- État global UI cross-composants : Context API en MVP, Zustand seulement si vrai besoin (peu probable)

**Forms** :
- React Hook Form + Zod schema partagé via resolver
- Schema Zod côté front, **toujours doublé** d'un Form Request côté back

**Organisation** :
- Par feature : `src/features/bookings/`, `src/features/invoices/`
- Dans chaque feature : `components/`, `hooks/`, `api/` (fonctions de fetch), `types.ts`
- Pas de dossier `src/components/` global (sauf pour composants vraiment cross-feature : `Button`, `Layout`)

---

## 5. Workflow attendu de Claude Code

### 5.1 Cycle standard pour toute tâche

1. **Lire le contexte** : BRIEF.md, PRD.md, fichiers concernés
2. **Comprendre la demande** : reformuler en une phrase, identifier les ambiguïtés
3. **Si ambiguïté** : poser questions, **ne pas inventer**
4. **Planifier** : exposer le plan en quelques bullet points avant de coder
5. **Si le plan touche à l'architecture** : proposer un ADR avant l'implémentation
6. **Coder** : par petites étapes, tests d'abord si possible (TDD)
7. **Tester** : `./vendor/bin/sail artisan test` (Pest) doit passer
8. **Linter** : `./vendor/bin/sail pint` et `pnpm biome check` (côté SPA) doivent passer
9. **Commit** : message conventionnel (`feat(booking): ...`)

### 5.2 TDD encouragé

Pour toute nouvelle fonctionnalité métier (pas pour du purement visuel) :
- Écrire d'abord un test Pest qui décrit le comportement attendu
- Le voir échouer
- Implémenter le minimum pour faire passer
- Refactoriser

Si TDD pas adapté (Filament Resource rapide par exemple) : tests post-implémentation, mais **avant le commit**.

### 5.3 Itérations courtes

- Un commit = une intention claire (pas de fourre-tout)
- Des PR petites (≤500 LOC modifiées idéalement)
- Pas de "WIP" en cumulé sur main

---

## 6. Patterns recommandés

### 6.1 Form Request type

```php
final class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Booking::class);
    }

    public function rules(): array
    {
        return [
            'resource_id' => ['required', 'integer', 'exists:resources,id'],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'title' => ['nullable', 'string', 'max:255'],
            'attendees_count' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
```

### 6.2 Service métier type

```php
final class InvoiceNumberingService
{
    public function __construct(private DatabaseManager $db) {}

    public function nextNumber(): string
    {
        return $this->db->transaction(function () {
            $year = now()->year;
            $counter = InvoiceCounter::lockForUpdate()->firstOrCreate(['year' => $year]);
            $counter->increment('value');
            return sprintf('EW-%d-%05d', $year, $counter->value);
        });
    }
}
```

### 6.3 Policy type

```php
final class BookingPolicy
{
    public function view(User $user, Booking $booking): bool
    {
        return $user->id === $booking->user_id || $user->hasRole('admin');
    }

    public function update(User $user, Booking $booking): bool
    {
        return $user->id === $booking->user_id 
            && $booking->starts_at->isFuture()
            && $booking->status !== 'cancelled';
    }
}
```

### 6.4 Test Pest type (Feature)

```php
it('refuse à un membre de voir la résa d\'un autre membre', function () {
    $member1 = User::factory()->member()->create();
    $member2 = User::factory()->member()->create();
    $booking = Booking::factory()->for($member1)->create();

    $this->actingAs($member2)
        ->getJson("/api/bookings/{$booking->id}")
        ->assertForbidden();
});
```

---

## 7. Anti-patterns interdits

| Interdit | Pourquoi | Alternative |
|---|---|---|
| `Model::all()` sans pagination | Risque de scan complet, pas de scope | `->paginate()` + scopes |
| `$request->all()` vers Eloquent | Mass assignment, validation absente | Form Request + `$request->validated()` |
| Query brute via DB::raw | SQL injection possible | Eloquent ou query builder typé |
| Email ou notification dans le controller | Bloque la requête | Job en queue (`dispatch(new SendXxxJob)`) |
| Variables d'env via `env()` dans le code applicatif | Plus accessible après cache config | `config('xxx')` uniquement |
| Logique métier dans un Filament Resource | Pas testable, pas réutilisable | Extraire dans un Service |
| `useEffect` pour fetch API | Anti-pattern moderne, gestion d'erreur lourde | TanStack Query (`useQuery`) |
| Hardcoder un user/role dans un test | Fragile, peu lisible | Factories + traits (`->admin()`, `->member()`) |
| Migration non-réversible | Bloque le rollback | Écrire `down()` propre |
| `delete($id)` sans Policy check | Suppression non autorisée | `$this->authorize('delete', $model)` |

---

## 8. Commandes courantes

Sail alias : `alias sail='[ -f sail ] && sh sail || sh vendor/bin/sail'`

```bash
# Démarrage env
sail up -d

# Tests
sail test                                # tous les tests Pest
sail test --filter=BookingTest           # un test spécifique
sail test --parallel                     # en parallèle

# Lint & format
sail pint                                # PHP format/lint
sail pint --test                         # check sans modifier
pnpm --filter portal-spa biome check     # TS/React
pnpm --filter portal-spa biome format    # TS/React format

# Artisan
sail artisan migrate
sail artisan migrate:fresh --seed        # reset DB complète + seed
sail artisan make:model Booking -mfrs    # model + migration + factory + request + seeder
sail artisan make:filament-resource Booking
sail artisan tinker

# SPA
cd portal-spa && pnpm dev                # dev server :5173
cd portal-spa && pnpm build              # build production
cd portal-spa && pnpm test               # tests Vitest (si configurés)

# Queues
sail artisan queue:work                  # démarrer un worker en dev

# Cache
sail artisan optimize:clear              # vider tous les caches
sail artisan config:cache                # cacher la config (prod)
sail artisan route:cache                 # cacher les routes (prod)
```

---

## 9. Git workflow

### Branches
- `main` : production, protégée
- `feature/<nom>` : nouvelles features
- `fix/<nom>` : corrections de bugs
- `refactor/<nom>` : refactos sans changement fonctionnel
- `chore/<nom>` : dépendances, config, tooling

### Commits conventionnels
Format : `<type>(<scope>): <description>`

Types : `feat`, `fix`, `refactor`, `docs`, `test`, `chore`, `style`, `perf`, `ci`

Exemples :
- `feat(booking): ajout sync Google Calendar sur création de résa`
- `fix(invoice): correction race condition sur numérotation`
- `refactor(auth): extraction logique 2FA dans un Service`
- `test(booking): tests d'isolation user A vs user B`

### Avant chaque commit
1. `sail test` passe
2. `sail pint` propre
3. `pnpm biome check` propre côté SPA
4. Pas de `dd()`, `dump()`, `console.log` oubliés
5. Pas de `.env` ou secrets commités

---

## 10. Quand il y a doute — la règle d'arrêt

Si l'une de ces situations se présente, **arrêter et demander** :

- La demande contredit le BRIEF.md ou un ADR existant
- La demande implique une modification de schéma DB en production
- La demande touche à la facturation, paiements, ou numérotation
- La demande implique une suppression de données utilisateur réelles
- La demande touche aux Policies ou à l'isolation des données
- La demande implique un nouveau service tiers (DPA RGPD à vérifier)
- Une ambiguïté technique pourrait avoir plusieurs réponses raisonnables

**Mieux vaut une question de plus qu'une régression silencieuse en prod.**

---

## 11. Posture attendue

- **Honnêteté technique** : si tu doutes, le dire. Si une solution a des trade-offs, les exposer.
- **Pas de sycophancy** : ne pas approuver une mauvaise idée pour faire plaisir. Challenger respectueusement quand pertinent.
- **Pas de sur-ingénierie** : MVP avant V2. Pas de pattern complexe pour un problème simple.
- **Pas de raccourcis sur la sécurité ou les tests** : "on testera plus tard" est inacceptable sur les fonctionnalités touchant aux données utilisateur ou à la facturation.
- **Documentation au fil de l'eau** : si une décision non triviale est prise, créer un ADR dans `docs/adr/`.
- **Refactoriser quand le code parle** : si un pattern revient 3 fois, extraire dans un Service ou un Helper.

---

## 12. Références

- `docs/BRIEF.md` — brief projet complet (référence principale)
- `docs/PRD.md` — spec fonctionnelle détaillée (portail client + back-office admin)
- `docs/SUIVI.md` — **suivi des phases & tâches** (état d'avancement, codes de tâches `C1.4`…)
- `docs/data_model.md` — modèle de données détaillé (schéma Postgres)
- `docs/adr/` — Architecture Decision Records
- Doc Laravel : https://laravel.com/docs/13.x
- Doc Filament 5 : https://filamentphp.com/docs/5.x
- Doc Sanctum SPA : https://laravel.com/docs/13.x/sanctum#spa-authentication
- Doc Pest : https://pestphp.com/
- Doc TanStack Query : https://tanstack.com/query/latest

---

*Maintenu par Guillaume. Toute modification structurante de ce fichier doit être discutée et reflétée dans BRIEF.md.*
