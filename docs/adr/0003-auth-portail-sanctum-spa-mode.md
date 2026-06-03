# ADR-0003 : Sanctum mode SPA pour l'auth du portail membre

## Statut

Accepté — 2026-05

## Contexte

L'architecture retenue (cf. ADR-0002) implique une SPA React communiquant avec une API REST Laravel. La SPA est hébergée sur `portail.ecoworking.fr` et consomme `portail.ecoworking.fr/api/*` (même origine).

Trois stratégies d'authentification ont été évaluées :

1. **Sanctum mode SPA** : sessions Laravel via cookies HttpOnly + CSRF token
2. **Sanctum mode tokens** : tokens API stockés côté client (Authorization: Bearer)
3. **JWT externe** (Auth0, Clerk, ou JWT custom)

Contraintes :
- L'app et l'API tournent sur la même origine (`portail.ecoworking.fr`)
- Authentification utilisée exclusivement par la SPA web (pas de mobile native en V1)
- Sécurité prioritaire (données personnelles, facturation)
- Maintenance minimale (dev solo)

## Décision

Utiliser **Laravel Sanctum mode SPA** : authentification par sessions Laravel classiques + cookies HttpOnly sécurisés + CSRF token via le mécanisme `/sanctum/csrf-cookie`.

## Conséquences

### Bénéfices

- **Sécurité supérieure** : les cookies HttpOnly **ne sont pas accessibles à JavaScript** → résistance forte aux attaques XSS (un attaquant XSS ne peut pas voler le token de session)
- **CSRF natif Laravel** : le middleware `EnsureFrontendRequestsAreStateful` gère le double-submit pattern automatiquement
- **Pas de gestion manuelle de tokens** côté SPA : pas de localStorage, pas de refresh logic, pas de stockage explicite à gérer
- **Auth flow standard Laravel** : login via route Fortify classique, logout idem, reset password idem → on réutilise tout le scaffolding Fortify sans adaptation
- **Cookies isolés par sous-domaine** (cf. ADR-0004) : un XSS hypothétique côté portail ne donne aucun accès à la session admin
- **Compatible avec le 2FA et les magic links** Fortify sans rework
- **Maintenance triviale** : aucune lib de token à maintenir, aucun chiffrement custom, aucun JWT signing key à roter

### Trade-offs assumés

- **Pas utilisable directement par une app mobile native** : les cookies ne sont pas le standard mobile. Si une app mobile native arrive en V3+, il faudra ajouter en parallèle des tokens Sanctum (compatible, le même Sanctum supporte les deux modes)
- **Dépendance forte au navigateur** : les cookies + CSRF requièrent un client web. OK pour le portail web, à reconsidérer pour des intégrations tierces
- **Doit gérer la session côté Laravel** : table `sessions` en DB (driver `database`, persistante et révocable) → légère charge DB additionnelle
- **CORS à configurer correctement** si le portail et l'API étaient séparés cross-origin (mais ici même origine → pas un problème)

### Conséquences sur les autres décisions

- `SANCTUM_STATEFUL_DOMAINS=portail.ecoworking.fr` dans `.env`
- `SESSION_DOMAIN=null` (cookies scopés au host courant, isolation par sous-domaine)
- Driver session `database` (pas `cookie` pour la révocabilité)
- Routes API portail : middleware `auth:sanctum` obligatoire

## Alternatives considérées

### Sanctum mode tokens (Bearer Authorization)

**Pourquoi écarté** :
- **Vulnérabilité XSS** : le token doit être stocké côté client (localStorage ou sessionStorage), accessible à JavaScript → un XSS exfiltre le token et compromet le compte
- Logique de stockage et de refresh à gérer manuellement côté SPA → boilerplate, sources de bugs
- Pas de CSRF natif → autre vecteur à gérer
- Bénéfice principal (mobile, cross-origin) inutile dans ce contexte (même origine, pas de mobile)

Reste valide si une app mobile native arrive un jour, en complément de Sanctum SPA (les deux peuvent cohabiter dans Laravel).

### JWT externe (Auth0, Clerk) ou JWT custom

**Pourquoi écarté** :
- **Vendor lock-in** sur un fournisseur tiers (Auth0/Clerk) + coût récurrent (souvent ~25-100€/mois au-delà du free tier)
- **Complexité gratuite** : JWT custom nécessite gestion des clés, signature, révocation manuelle (les JWT sont par nature non-révocables sans liste noire)
- **Aucun bénéfice** dans ce contexte mono-tenant, single-app, même-origine
- Auth0/Clerk se justifient pour des cas multi-app, SSO, ou apps mobiles natives — pas le cas ici

### Auth Inertia.js classique

**Pourquoi écarté** :
- Architecture SPA + REST déjà retenue (cf. ADR-0002), Inertia ne s'applique pas ici
- Aurait nécessité de basculer sur l'architecture Inertia qui a été explicitement écartée

## Références

- Documentation officielle Sanctum SPA : https://laravel.com/docs/11.x/sanctum#spa-authentication
- OWASP : "JWT" attack vectors : https://cheatsheetseries.owasp.org/cheatsheets/JSON_Web_Token_for_Java_Cheat_Sheet.html
- ADR-0002 (architecture hybride)
- ADR-0004 (sous-domaines)
