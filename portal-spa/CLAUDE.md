# CLAUDE.md — portail SPA (portal-spa/)

> Conventions spécifiques au portail membre React/TypeScript.
> Les règles transverses (sécurité, RGPD, a11y, facturation, workflow) restent dans le `CLAUDE.md` racine.

## Conventions TypeScript / React

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
