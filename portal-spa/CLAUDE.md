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

## Design system (shadcn/ui — ADR-0013 D1)

- `src/components/ui/*` contient les **primitives shadcn/ui** (base Radix, style
  `radix-nova`, `components.json` à la racine de `portal-spa/`). Exception assumée à la
  règle « nom de fichier = nom du composant » : ces fichiers suivent la convention
  shadcn en **kebab-case** (`alert-dialog.tsx`), pour rester réinstallables via
  `pnpm dlx shadcn@latest add -o <composant>`. Les exports restent nommés.
- Les fichiers générés sont **notre code** : toute adaptation (contraste, i18n
  française, a11y) est faite sur place et **commentée en tête de fichier**.
- Jetons de thème dans `src/styles.css` : variables shadcn (`--primary`, `--ring`,
  `--sidebar-*`…) mappées sur le vert brand `oklch` et les neutres Tailwind.
  Mode sombre via la classe `dark` sur `<html>` (`@custom-variant dark`).
- Champs de formulaire : `Input`, `Textarea` et `NativeSelect` acceptent
  `error` / `describedBy` et câblent seuls `aria-invalid` + `aria-describedby`.
  `NativeSelect` (`<select>` natif) est le composant à utiliser avec
  `react-hook-form` (`{...register()}`) ; le `Select` Radix (`ui/select.tsx`)
  est réservé aux menus riches pilotés par un `Controller`.
- Chargement : `Spinner` (maison) pour une action ponctuelle, `Skeleton`
  (shadcn) pour un bloc de contenu.
- Confirmation d'action destructrice : `ConfirmButton` (wrapper sur
  `AlertDialog`). Attention en test : la boîte est rendue dans un **portail**,
  donc hors du DOM du composant appelant.
