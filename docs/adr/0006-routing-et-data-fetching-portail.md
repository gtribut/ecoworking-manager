# ADR-0006 : Stack data fetching et routing du portail (React Router v7 + TanStack Query)

## Statut

Accepté — 2026-05

## Contexte

La SPA portail (cf. ADR-0002) consomme une API REST Laravel et nécessite deux briques distinctes :

1. **Un router** : mapping URL ↔ composant, navigation, params, search params
2. **Une stack de data fetching** : gestion du cache, déduplication, refetch, mutations, états loading/error

Ces deux briques sont **indépendantes** mais structurantes : changer l'une ou l'autre en cours de projet est coûteux.

Pour le routing, deux options sérieuses ont été évaluées :
- **React Router v7** : le routeur historique de l'écosystème, fusionné avec Remix, désormais en v7
- **TanStack Router v1** : routeur récent (stable fin 2024), focus type-safety totale

Pour le data fetching, **TanStack Query v5** est le standard de facto. Une alternative envisagée a été **SWR** (Vercel).

## Décision

- **Routing** : **React Router v7.14+** (package `react-router`), mode library/déclaratif (sans framework SSR, pure client-side)
- **Data fetching** : **TanStack Query 5.100+**
- **Validation runtime** : Zod v4+ (schemas partagés avec les Form Requests Laravel idéalement)
- **Client HTTP** : Axios (intercepteurs pratiques pour CSRF Sanctum + auth)

> 📌 **Note de migration importante** : depuis React Router v7, le package s'appelle simplement `react-router` (plus `react-router-dom`). Tous les imports sont à faire depuis `react-router`. Si on rencontre des tutos avec `react-router-dom`, c'est de la v6 — adapter aux nouveaux imports.

## Conséquences

### Bénéfices

**React Router v7** :
- **Maturité massive** : ~10 ans d'existence, base utilisateurs énorme, des milliers de tutos et de Q&A Stack Overflow disponibles → réduit la friction d'apprentissage pour un dev solo
- **Valeur de marché** : compétence attendue dans 95% des offres React, alignée avec l'objectif de montée en compétence
- **API stable** : v7 a stabilisé les concepts, plus de cassures majeures attendues à court terme
- **SSR possible** plus tard si besoin (héritage Remix)
- **Suffisant pour les 10-15 écrans** du portail Ecoworking : la complexité de routing est faible

**TanStack Query** :
- **Standard de facto** pour le state serveur en React depuis ~2021 (~60k téléchargements/jour npm)
- **Couvre tout le besoin** : cache, refetch, optimistic updates, mutations avec rollback, devtools, infinite queries
- **Router-agnostic** : fonctionne avec React Router sans complication
- **Excellente DX** : devtools intégrés, hooks intuitifs (`useQuery`, `useMutation`, `useQueryClient`)
- **Valeur de marché** très élevée

### Trade-offs assumés

**React Router v7 vs TanStack Router** :
- **Type-safety params/search params moins poussée** : React Router renvoie des `string | undefined` par défaut, là où TanStack Router type tout natively
  - Mitigation : sur 10-15 écrans avec peu de params, la friction est faible. Wrapping manuel via Zod si critique
- **Search params manuels** : pas d'API typée first-class pour les query strings
  - Mitigation : helper custom + Zod pour les écrans à filtres (factures, résa) → 20-30 lignes utilitaires
- **Pas de loader/cache prefetch natif** : TanStack Router intègre TanStack Query au niveau route, React Router non
  - Mitigation : `queryClient.ensureQueryData()` dans les loaders v7 OU `useQuery()` dans le composant avec gestion loading classique. Acceptable au volume du projet

**TanStack Query vs SWR** :
- TanStack Query est légèrement plus lourd (bundle) que SWR, mais offre plus de features (optimistic updates avec rollback, infinite queries, mutations avancées) qui seront utiles pour le portail

### Conséquences sur les autres décisions

- Stack data du portail figée :
  ```
  React Router v7    →  navigation, URLs
  TanStack Query     →  fetch, cache, mutations
  Zod                →  validation
  Axios              →  client HTTP + intercepteurs CSRF Sanctum
  ```
- Les schémas Zod peuvent être (à terme) générés depuis le backend Laravel via un outil tiers, ou maintenus manuellement en miroir des Form Requests
- Pas de dépendance entre router et data fetching → liberté de changer l'un sans casser l'autre

## Alternatives considérées

### TanStack Router v1 (initialement proposé)

**Avantages techniques réels** :
- Type-safety bout-en-bout supérieure (params, search params, loaders typés)
- Intégration native avec TanStack Query via les loaders (prefetch + cache automatique)
- API moderne, philosophie cohérente

**Pourquoi écarté** :
- **Maturité** : v1 stable seulement depuis fin 2024, écosystème de tutos/blogs/Q&A nettement plus restreint que React Router → friction réelle pour un dev solo apprenant en parallèle
- **Valeur de marché** : compétence valorisée mais reste niche, là où React Router est attendu partout
- **Volume du projet** : les avantages de TanStack Router (search params typés, loaders avancés) brillent surtout sur des apps à 50+ écrans avec filtres complexes. Sur 10-15 écrans, le bénéfice marginal ne justifie pas le coût d'apprentissage et la friction de doc/communauté

**Reste pertinent pour** :
- Apps à beaucoup de filtres/tris/pagination URL-driven (admin de table par exemple)
- Équipes qui veulent imposer la type-safety sans dérive
- Projets qui utilisent déjà TanStack partout et cherchent la cohérence philosophique

### SWR (Vercel) pour le data fetching

**Avantages** :
- Plus léger, API plus simple, courbe d'apprentissage plus douce
- Bonne intégration dans l'écosystème Next.js/Vercel

**Pourquoi écarté** :
- Features moins riches que TanStack Query : pas d'optimistic updates avec rollback, infinite queries plus basiques, mutations moins complètes
- Adoption marché moindre que TanStack Query
- Pour le coût marginal (qq Ko de bundle), TanStack Query apporte plus de valeur sur les cas métiers (réservations, paiements manuels, etc.)

### Apollo Client, RTK Query

**Pourquoi écartés** :
- Apollo : pertinent uniquement pour GraphQL (le projet est REST)
- RTK Query : nécessite Redux Toolkit (overkill, pas dans la stack)

### fetch natif + useState/useEffect

**Pourquoi écarté** :
- Réinventer le cache, le dedupe, les retries, les loading states → coût massif, sources de bugs
- Anti-pattern moderne, pas pédagogique

## Note méta sur cette décision

Cette décision **revient sur une recommandation initiale** (TanStack Router v1) faite plus tôt dans la conception. Le revirement est volontaire après ré-évaluation honnête des critères :
- Au moment de la première reco, j'avais pondéré la modernité technique au-dessus de la facilité d'apprentissage et de la disponibilité de ressources
- La question explicite "quelle différence entre TanStack et React Router ?" a forcé une analyse plus fine, qui a conduit au pivot

C'est documenté ici pour traçabilité : si dans 1 an l'argumentaire change (par exemple TanStack Router devient mainstream avec 10× plus de tutos), un nouvel ADR pourra reconsidérer la décision.

## Références

- React Router v7 : https://reactrouter.com/
- TanStack Query v5 : https://tanstack.com/query/latest
- TanStack Router : https://tanstack.com/router/latest
- Zod : https://zod.dev/
- ADR-0002 (architecture hybride Filament + SPA)
