# ADR-0008 : Versions runtime modernes (PHP 8.5, Node 26, Laravel 13, Filament 5, stack front mai 2026)

## Statut

Accepté — 2026-05

## Contexte

À la conception initiale du projet (été 2025), les versions runtime envisagées étaient :
- **PHP 8.3** (current à l'époque)
- **Laravel 11**
- **Filament v3**
- **Node 22 LTS**
- **Vite 6**, React 19 (générique)

Au moment de réellement démarrer le développement (mai 2026), toutes ces versions sont obsolètes ou en voie de l'être.

| Composant | Version initiale | État en mai 2026 |
|---|---|---|
| PHP 8.3 | current 2024 | Security only depuis nov 2025, EOL nov 2027 |
| Laravel 11 | current 2024 | Security ended mars 2026 |
| Filament v3 | current 2024 | Remplacé par Filament 5 en mars 2026 |
| Node 22 LTS | LTS 2024 | Toujours active LTS jusqu'à oct 2026 puis Maintenance |

Versions disponibles en mai 2026 :

| Composant | Versions disponibles | Recommandation marché |
|---|---|---|
| PHP | 8.3 (security), 8.4 (active), **8.5** (active, sortie nov 2025) | PHP 8.5 pour nouveaux projets |
| Laravel | 12 (active), **13** (active, sortie mars 2026) | Laravel 13 pour nouveaux projets |
| Filament | 4 (legacy), **5** (current, sortie 2026 alignée Laravel 13) | Filament 5 pour nouveaux projets |
| Node | 22 LTS (Maintenance bientôt), **24 LTS active**, **26 Current** (sortie 5 mai 2026) | Node 24 LTS pour conservateur, Node 26 pour moderne |

Décision à prendre : viser les versions les plus modernes (current) ou rester sur du conservateur (LTS éprouvées) ?

## Décision

Adopter les **versions les plus modernes** stable disponibles en mai 2026 :

- **PHP 8.5** (sortie nov 2025, active support jusqu'à dec 2027, security jusqu'à dec 2029)
- **Laravel 13** (sortie mars 2026, supporte PHP 8.3-8.5, support corrigé jusqu'au cycle de version suivant)
- **Filament 5** (sortie mars 2026, alignée Laravel 13)
- **Node 26** (Current depuis 5 mai 2026, devient LTS en oct 2026)
- **PostgreSQL 18** (current, sortie sept 2025) — *corrige un oubli* : la base n'avait pas été réévaluée lors de cette modernisation et restait sur le **16** hérité du BRIEF initial (été 2025). Disponible sur Clever Cloud (**18.4** depuis mai 2026), donc parité dev/prod assurée. Aucune feature du `data_model` n'exige une version précise (index uniques partiels, CHECK, exclusion GiST `btree_gist` : identiques de 16 à 18)

### Versions stack frontend (mai 2026)

| Composant | Version cible | Sortie |
|---|---|---|
| React | **19.2.6+** | 6 mai 2026 |
| TypeScript | **6.0+** | 23 mars 2026 |
| Vite | **8.0+** (Rolldown unifié) | 12 mars 2026 |
| Tailwind CSS | **v4.3+** (Lightning CSS) | mai 2026 |
| React Router | **v7.14+** (package `react-router`) | déc 2025 (v7 init), avril 2026 (v7.14) |
| TanStack Query | **5.100+** | mai 2026 |
| Biome | **v2.4+** | février 2026 |
| Playwright | **1.57+** | nov 2025 |
| Pest (PHP) | **v4.x** (browser plugin Playwright) | août 2025 |

Notes spécifiques :
- **Vite 8** : changement structurel important (Rolldown remplace Rollup + esbuild). 10-30× plus rapide. Compatibilité ascendante avec plugins Rollup
- **Tailwind v4** : nouvelle syntaxe `@import "tailwindcss"` + bloc `@theme` dans le CSS (plus de `@tailwind` directives ni de `tailwind.config.js` requis). Plugin Vite natif `@tailwindcss/vite`
- **React Router v7** : package renommé `react-router` (plus `react-router-dom`)
- **Pest 4** : intègre Playwright via `pestphp/pest-plugin-browser` pour tester l'admin Filament en e2e depuis PHP avec helpers Laravel natifs. **Stratégie recommandée** : Pest 4 + browser plugin pour les tests e2e admin (Filament), Playwright direct (TypeScript) pour les tests e2e de la SPA React

## Conséquences

### Bénéfices

- **Runway de support maximal** : avec PHP 8.5, on a 2.5 ans d'active support et 4 ans de security devant nous. Pas de migration forcée avant 2028-2029
- **Features modernes** :
  - PHP 8.5 : pipe operator `|>`, attributs natifs, fatal error backtraces par défaut, INI diff, recursive closures
  - Node 26 : **Temporal API enabled by default** (énorme pour gérer les dates de réservations, abonnements, factures sans Day.js ou date-fns), V8 14.6, Undici 8.0
  - Laravel 13 : zero breaking changes vs Laravel 12, AI SDK stable, passkey authentication, attributes `#[Fillable]` natifs (à confirmer applicabilité au projet)
- **Performance** : 5-8% throughput gain typique de PHP 8.3 → 8.5 sur applications Laravel
- **Écosystème déjà migré** : 6 mois après PHP 8.5 et 2 mois après Laravel 13, la plupart des packages tiers majeurs (Spatie, Pest, etc.) sont à jour
- **Alignement objectif "stack moderne"** du porteur (cf. ADR-0001) : prendre du current cohérent avec la motivation d'apprentissage
- **Disponibilité Clever Cloud** : PHP 8.5 disponible chez les hosts majeurs depuis Q2 2026 (à vérifier explicitement avant déploiement prod)

### Trade-offs assumés

**Côté Node 26 (Current, pas encore LTS)** :
- Node 26 est en phase **Current** pour 6 mois (mai → oct 2026) avant de passer en LTS active
- Pour un projet en dev solo (4-6 mois de cycle), le projet sera prêt à passer en prod **après** la promotion LTS d'octobre 2026 → pas de risque de prod sur du non-LTS
- Si toutefois on devait déployer en prod avant octobre 2026, on basculerait sur Node 24 LTS (rétrograde trivial : `fnm use 24` puis `npm install`)
- Ecosystem (Vite, Vitest, Playwright, etc.) : compatibles dès Node 26.0.0
- Alternative écartée : Node 24 LTS pour démarrer. Argument contre : on perd la Temporal API native, qui est précieuse pour ce projet (manipulation de dates de résa, créneaux, abonnements, facturation au prorata)

**Côté PHP 8.5 (active depuis 6 mois)** :
- Risque maturité : encore quelques packages tiers à la traîne (rare, mais possible). Risque modéré
- Pour minimiser le risque : si un package critique pose problème avec PHP 8.5, fallback PHP 8.4 (toujours en active support jusqu'à dec 2026)
- Pas de risque sur les libs principales : Laravel 13, Filament 5, Spatie, Pest 4, Sanctum, Fortify supportent toutes PHP 8.5

**Côté Laravel 13 + Filament 5** :
- Sorties récentes (mars 2026) → potentielles régressions à corriger via patches mineurs. Risque modéré
- Mitigation : épingler les versions exactes au début (`"laravel/framework": "13.0.*"`), upgrader vers patches mineurs quand stables
- Note Laravel 13.3+ : pull Symfony 8 qui impose **PHP 8.4 minimum effectif** — donc PHP 8.5 est largement compatible

### Conséquences sur les autres décisions

- `composer.json` : `"php": "^8.5"`, `"laravel/framework": "^13.0"`, `"filament/filament": "^5.0"`
- `package.json` : `"engines": { "node": ">=26.0.0" }`
- `.nvmrc` / `.tool-versions` (si utilisé) : `node 26.x`
- Setup local : installer PHP 8.5 dans WSL (apt repository `ondrej/php`)
- CI GitHub Actions : runners avec PHP 8.5 + Node 26 (vérifier disponibilité GitHub-hosted runners, généralement à jour rapidement)
- Hébergement Clever Cloud : sélectionner instance PHP 8.5 (à vérifier dispo). Si pas dispo en mai 2026, fallback PHP 8.4 temporairement

## Plan de fallback si problème

Si lors du scaffolding ou des premiers développements, un blocage majeur surgit sur ces versions :

1. **PHP 8.5 problématique** (un package critique incompatible) → bascule PHP 8.4 (1 commande : `apt install php8.4-*`, `composer require ...`). Pas de réécriture
2. **Laravel 13 problématique** (bug bloquant) → bascule Laravel 12 (rétrograde via composer, supporte aussi PHP 8.4)
3. **Filament 5 problématique** (plugin tiers manquant) → bascule Filament v3 (encore supporté jusqu'à fin 2026, supporte PHP 8.5)
4. **Node 26 problématique** (lib JS incompatible) → bascule Node 24 LTS (1 commande : `fnm use 24`)

Aucune de ces rétrogradations ne nécessite de réécriture de code applicatif — uniquement des ajustements de `composer.json` / `package.json`.

## Note méta sur cette décision

Cette décision **revient sur les versions initialement fixées** dans le BRIEF (rédigé été 2025). Le revirement est logique : un projet en cours de conception ne fige pas ses versions runtime jusqu'au démarrage effectif du dev, sinon il démarre avec des versions obsolètes.

Le pattern à retenir pour la suite : **revérifier les versions runtime à chaque démarrage de phase importante** (V1 prod, V2 facturation Factur-X, etc.) — l'écosystème PHP/Laravel évolue à rythme annuel régulier (release majeure Laravel en février, release majeure PHP en novembre).

## Alternatives considérées

### Conservateur — versions LTS uniquement

- **PHP 8.4** au lieu de 8.5 : reste en active support jusqu'à dec 2026 (8 mois de moins de runway). Pas d'avantage notable.
- **Laravel 12** au lieu de 13 : security support jusqu'à feb 2027. Pas d'avantage notable, et perte des nouveautés Laravel 13.
- **Filament 4** : end-of-life proche, à éviter.
- **Node 24 LTS** au lieu de Node 26 : argument valable pour la stabilité immédiate, mais perd la Temporal API.

**Pourquoi écarté** : la cible projet (mise en prod après octobre 2026) tombe pile au moment où Node 26 devient LTS. Les autres composants (PHP 8.5, Laravel 13, Filament 5) ont 6+ mois de maturité au démarrage, c'est largement suffisant pour un projet solo.

### Bleeding edge — PHP 8.6 dev / Node 27 nightly

**Pourquoi écarté** :
- PHP 8.6 pas avant nov 2026
- Node 27 pas avant avril 2027 (et inaugure le nouveau cycle de release Node sans current/LTS)
- Ces versions ne sont pas disponibles stables
- Hors scope d'un projet de production

## Références

- PHP support matrix : https://www.php.net/supported-versions.php
- Laravel release notes : https://laravel.com/docs/13.x/releases
- Filament docs : https://filamentphp.com/docs/5.x
- Node.js release schedule : https://github.com/nodejs/Release
- ADR-0001 (choix Laravel — décision originale)
