# ADR-0004 : Deux sous-domaines `admin` et `portail` sur un seul déploiement

## Statut

Accepté — 2026-05

## Contexte

L'architecture retenue (cf. ADR-0002) comporte deux surfaces utilisateur très différentes (admin Filament + portail SPA React) au sein d'une même application Laravel.

La question du routing entre ces deux surfaces se pose :

1. **Path-based** : `app.ecoworking.fr/admin` et `app.ecoworking.fr/portal`
2. **Sous-domaines** : `admin.ecoworking.fr` et `portail.ecoworking.fr`
3. **Deux applications distinctes** (deux déploiements, deux DBs ou DB partagée)

Critères d'évaluation :
- Isolation de sécurité (notamment cookies, en lien avec l'historique RGPD/audit du porteur)
- Lisibilité pour l'utilisateur final
- Complexité ops
- Cohérence DNS

L'apex `ecoworking.fr` héberge déjà le site marketing existant (hors scope de ce projet).

## Décision

Utiliser **deux sous-domaines pointant vers une seule application Laravel** :

- `admin.ecoworking.fr` → Filament panel (admins Ecoworking)
- `portail.ecoworking.fr` → SPA React membre + endpoints `/api/*`

Le routing est géré au niveau Laravel via `Route::domain()` et la configuration `$panel->domain()` côté Filament.

## Conséquences

### Bénéfices

- **Isolation des cookies par sous-domaine** : un cookie posé sur `admin.ecoworking.fr` n'est jamais transmis aux requêtes vers `portail.ecoworking.fr` et vice-versa (avec `SESSION_DOMAIN=null`)
  - Bénéfice sécurité majeur : un XSS hypothétique côté portail **ne donne aucun accès** à la session admin
  - Aligné avec la posture sécurité forte du projet (cf. CLAUDE.md §3.1)
- **Lisibilité utilisateur** : URL claire qui indique le contexte (admin vs portail) sans avoir à parser le path
- **DNS clean** : deux records CNAME explicites pointés sur la même app Clever Cloud, facile à documenter et à modifier
- **Filament panel à la racine** : `$panel->path('')` → URL admin directe (`admin.ecoworking.fr/`) au lieu de `/admin`
- **API même-origine** : la SPA portail consomme `portail.ecoworking.fr/api/*`, **pas de CORS à configurer**
- **Routing Laravel propre** : `Route::domain('portail.ecoworking.fr')->group(...)` rend le code immédiatement compréhensible
- **Évolutivité** : si on veut un jour séparer en deux apps (peu probable), la migration est facile car les domaines sont déjà séparés
- **Coût ops nul** : un seul déploiement, une seule DB, un seul monitoring → maintenance équivalente à un déploiement standard

### Trade-offs assumés

- **Sessions non partagées** entre les deux sous-domaines : un admin qui est aussi membre devrait se connecter deux fois (une fois sur chaque sous-domaine). **Considéré comme un bénéfice sécurité**, pas une nuisance — c'est exactement le comportement souhaité (séparation des contextes)
- **Configuration DNS additionnelle** : deux records CNAME au lieu d'un → trivial
- **Configuration `.env` plus précise** : `SANCTUM_STATEFUL_DOMAINS`, `FILAMENT_DOMAIN`, etc. à bien renseigner → géré une fois pour toutes
- **Dev local** : nécessite l'ajout d'entrées dans `/etc/hosts` Windows pour `admin.ecoworking.test` et `portail.ecoworking.test` → setup one-shot

### Conséquences sur les autres décisions

- Cookies session scopés au host courant : `SESSION_DOMAIN=null`
- Sanctum stateful uniquement sur `portail.ecoworking.fr` : `SANCTUM_STATEFUL_DOMAINS=portail.ecoworking.fr`
- Filament panel restreint : `$panel->domain('admin.ecoworking.fr')`
- Routes web/api groupées par domaine dans `routes/web.php` et `routes/api.php`

## Alternatives considérées

### Path-based (`app.ecoworking.fr/admin` + `app.ecoworking.fr/portal`)

**Pourquoi écarté** :
- **Cookies partagés** sur le même domaine → un XSS côté portail peut accéder aux cookies admin (sauf à scoper manuellement chaque cookie au path, ce qui est fragile et facile à oublier)
- URL moins claire pour l'utilisateur ("pourquoi le path commence par /admin ?")
- Filament path-prefix moins idiomatique
- Aucun bénéfice opérationnel vs sous-domaines

### Deux applications Laravel distinctes

**Pourquoi écarté** :
- Sur-ingénierie majeure pour un mono-tenant à 50 membres
- Duplication des models, migrations, Services, Policies
- Double déploiement, double monitoring, double maintenance
- Pas de bénéfice sécurité supérieur (l'isolation cookies par sous-domaine atteint déjà 95% de l'objectif)
- Synchronisation des modèles entre les deux apps = source de bugs

### Apex `ecoworking.fr` redirigé vers le portail

**Pourquoi écarté** :
- L'apex est déjà occupé par le site marketing Ecoworking existant (hors scope du projet)
- Aucune décision DNS sur l'apex à prendre dans le cadre de ce projet

## Implémentation

### DNS production

| Host | Type | Cible |
|---|---|---|
| `admin.ecoworking.fr` | CNAME | `<app-id>.cleverapps.io` |
| `portail.ecoworking.fr` | CNAME | `<app-id>.cleverapps.io` |

Certificats SSL Let's Encrypt automatiques via Clever Cloud pour chacun.

### Dev local Windows

Dans `C:\Windows\System32\drivers\etc\hosts` :

```
127.0.0.1   admin.ecoworking.test
127.0.0.1   portail.ecoworking.test
```

### Configuration Laravel

```php
// Filament Panel Provider
$panel
    ->id('admin')
    ->path('')
    ->domain(config('app.admin_domain'))
    ->...
```

```php
// routes/web.php
Route::domain(config('app.portal_domain'))->group(function () {
    Route::get('/{any?}', fn () => view('portal-spa'))
        ->where('any', '^(?!api/).*$');
});

// routes/api.php
Route::domain(config('app.portal_domain'))->middleware('auth:sanctum')->group(function () {
    // endpoints API
});
```

## Références

- Laravel routing by domain : https://laravel.com/docs/13.x/routing#route-group-subdomain-routing
- Filament multi-tenancy / domain config : https://filamentphp.com/docs/5.x/panels/configuration
- ADR-0002 (architecture hybride)
- ADR-0003 (Sanctum SPA)
