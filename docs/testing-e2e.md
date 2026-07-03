# Tests e2e — runbook (C11.3)

> Deux suites navigateur complémentaires, conformes à l'ADR-0008 :
>
> | Suite | Cible | Outil | Dossier | Base de données |
> |---|---|---|---|---|
> | **Admin** | Back-office Filament | Pest 4 + `pestphp/pest-plugin-browser` | `tests/Browser/` | `testing` (RefreshDatabase par test) |
> | **SPA membre** | Portail React servi comme en prod | Playwright (TypeScript) | `portal-spa/e2e/` | `e2e` (dédiée, reseedée à chaque run) |
>
> **La base de dev n'est jamais touchée.** Les deux suites tournent dans le
> conteneur Sail en local, directement sur le runner en CI (job `e2e`,
> non bloquant pour l'instant — cf. commentaire dans `ci.yml`).

---

## 1. Prérequis (une fois, et après tout `sail build`)

Conteneurs Sail up (`sail up -d`), puis :

```bash
scripts/e2e/install.sh
```

Le script :
1. installe les deps npm **racine** (CLI `playwright`, requise par pest-plugin-browser) ;
2. installe les deps pnpm de `portal-spa/` (`@playwright/test`) ;
3. télécharge Chromium dans **`.playwright-browsers/`** (gitignoré, partagé
   hôte/conteneur via le montage du repo — variable `PLAYWRIGHT_BROWSERS_PATH`) ;
4. installe les **librairies système** Chromium dans le conteneur (en root du
   conteneur — aucun sudo hôte requis). Les paquets apt du conteneur ne
   survivent pas à un rebuild de l'image : relancer le script après `sail build`.

---

## 2. Lancer les suites

```bash
# Suite admin (Filament) — Pest browser, ~30 s
scripts/e2e/admin.sh                     # toute la suite
scripts/e2e/admin.sh --filter="anonymise"  # un test

# Suite SPA membre — Playwright, ~45 s
scripts/e2e/spa.sh                       # toute la suite
scripts/e2e/spa.sh --grep "magic link"   # un test
scripts/e2e/spa.sh --trace on            # forcer la trace complète
```

Les suites classiques restent inchangées : `sail test` (Pest Feature/Unit)
ne découvre **jamais** `tests/Browser/` (testsuite absente de `phpunit.xml`),
et Vitest ignore `portal-spa/e2e/` (`test.include` restreint à `src/`).

### Diagnostics

- **Admin** : capture d'écran automatique à l'échec dans
  `tests/Browser/Screenshots/` (gitignoré).
- **SPA** : traces Playwright (`trace: retain-on-failure`) dans
  `portal-spa/test-results/` ; ouvrir avec
  `cd portal-spa && npx playwright show-trace test-results/<test>/trace.zip`.

---

## 3. Architecture retenue

### Domaines : tout sur un seul host

Comme `phpunit.xml`, les deux suites forcent `ADMIN_DOMAIN`/`PORTAL_DOMAIN`
vides → panel admin sur `/admin`, portail + API sans contrainte de domaine.
Aucune entrée `/etc/hosts`, aucun tour de passe-passe de header Host.

### Suite admin — serveur in-process

`pest-plugin-browser` démarre un serveur HTTP **dans le process de test**
(Amp) qui route chaque requête du navigateur à travers le kernel Laravel de
test : `RefreshDatabase`, factories et `$this->seed()` fonctionnent tel quel,
sur la base `testing`. Config dédiée **`phpunit.e2e.xml`** :
même `<php>` que `phpunit.xml` sauf `SESSION_DRIVER=file` et
`CACHE_STORE=file` (le driver `array` ne survit pas d'une requête navigateur
à l'autre).

Login 2FA : `createAdminWithTotp()` + `loginToAdminPanel()`
(`tests/Browser/Support/helpers.php`, autoload-dev) — secret TOTP contrôlé,
code courant calculé via le provider `AppAuthentication` de Filament.

### Suite SPA — la prod, pas le dev Vite

La cible est la SPA **buildée** (`vite build` → `public/portal/`) servie par
Laravel via le catch-all (C12.1) : c'est le montage de production, plus
représentatif et plus stable que `vite dev` (pas de HMR, assets figés).

`playwright.config.ts` pilote tout : son `webServer`
(**`portal-spa/e2e/serve.sh`**) crée l'état — `migrate:fresh --seed
--seeder=E2eSeeder` sur la base **`e2e`** (le script force `DB_DATABASE=e2e`
en dur : il ne peut pas toucher une autre base) — puis sert l'app sur
`127.0.0.1:8091` (port interne au conteneur, pas de conflit avec :80/:5173).

Emails : `QUEUE_CONNECTION=sync` + SMTP → **Mailpit** (test magic link via
l'API `http://mailpit:8025`, `E2E_MAILPIT_URL` en CI).

### Pièges connus (déjà payés, ne pas re-payer)

- **`php artisan serve` filtre l'environnement** : seul une liste blanche de
  variables atteint le vrai process serveur — les overrides (`DB_DATABASE`,
  domaines…) seraient perdus. D'où `php -S` + `server.php` de Laravel dans
  `serve.sh`.
- **Sanctum ne voit une session que « depuis le frontend »** : les appels
  `page.request` vers `/api/*` doivent porter un `Referer` stateful →
  toujours passer par `apiHeaders()` (`e2e/support/auth.ts`).
- **Throttle login Fortify (5/min/email)** : sessions mises en cache entre
  specs (`loginViaApi`) + `CACHE_STORE=array` côté serveur e2e (les rate
  limiters vivent dans le cache ; le throttling n'est pas un comportement
  couvert par cette suite).
- **Anti-rejeu TOTP Filament** : le dernier code accepté est mémorisé en
  cache par id utilisateur ; avec `RefreshDatabase` les ids se répètent →
  `Cache::flush()` avant chaque login admin (fait par le helper).
- **Supprimer les cookies ne tue pas une session** : Laravel re-émet le
  cookie sur chaque réponse en vol. Pour simuler une expiration :
  `expireSessionServerSide()` (POST /logout côté API).
- **Libellés avec parenthèses côté Pest browser** : `click('Anonymiser
  (RGPD)')` serait parsé comme un sélecteur CSS → utiliser la forme
  Playwright explicite `click('text="Anonymiser (RGPD)"')`.
- **Valeurs de champs** : `assertSee`/`waitForText` ne voient pas les values
  d'inputs (ex. numéro de facture sur la page Filament) → `assertValue()`.

---

## 4. Ajouter un test

### Admin (Pest browser)

Créer `tests/Browser/AdminXxxTest.php` (aucune déclaration de suite à
ajouter : `phpunit.e2e.xml` découvre le dossier ; `tests/Pest.php` y applique
déjà `TestCase` + `RefreshDatabase`). Squelette :

```php
it('fait quelque chose de critique', function () {
    $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    $admin = createAdminWithTotp();

    loginToAdminPanel($admin)
        ->navigate('/admin/xxx')
        ->waitForText('…')       // attentes natives, jamais ->wait(n)
        ->assertSee('…');
});
```

Règles : sélecteurs textuels/rôles d'abord (cohérence RGAA), CSS explicite en
dernier recours ; chaque nouveau test doit être vu **échouer** au moins une
fois (casser l'assertion temporairement).

### SPA (Playwright)

Créer `portal-spa/e2e/xxx.spec.ts` en important depuis `./support/fixtures`
(et non `@playwright/test`) pour recevoir la fixture `checkA11y` :

```ts
import { loginViaApi } from './support/auth'
import { expect, test } from './support/fixtures'
import { seed } from './support/seed'

test('…', async ({ page, checkA11y }) => {
  await loginViaApi(page)          // session par API, pas de login UI
  await page.goto('/xxx')
  await expect(page.getByRole('heading', { name: '…' })).toBeVisible()
  await checkA11y('xxx')           // no-op aujourd'hui, axe-core en C11.4
})
```

Données : tout ce qui est asserté vient de `E2eSeeder`
(`database/seeders/E2eSeeder.php`) et de son miroir
`portal-spa/e2e/support/seed.ts` — **maintenir les deux en phase**.

### Préparation C11.4 (a11y)

La fixture `checkA11y` (`e2e/support/fixtures.ts`) est un point d'ancrage
vide, déjà appelée sur les écrans critiques. Pour activer l'audit :
`pnpm add -D @axe-core/playwright` puis implémenter le corps de la fixture
(fail sur violations serious/critical). Côté admin, le plugin Pest expose
`assertNoAccessibilityIssues()`. Aucun spec à modifier.

---

## 5. CI

Job `e2e` de `.github/workflows/ci.yml` : services Postgres 18 + Mailpit,
PHP 8.5, build SPA, cache navigateurs (`~/.cache/ms-playwright`), les deux
suites, artefacts (traces + screenshots) uploadés à l'échec.

**Non bloquant** (`continue-on-error: true`) le temps de valider la stabilité
sur quelques semaines de runs — retirer la ligne ensuite (commentaire en
place dans le workflow).
