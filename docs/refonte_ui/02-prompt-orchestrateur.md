# 02 — Prompt de lancement : session orchestrateur C14 (refonte UI portail)

> À coller tel quel comme **premier message** d'une nouvelle session Claude Code (modèle
> **Fable 5.1**), ouverte à la racine du repo, Sail démarré (`sail up -d`). Rédigé le 2026-09-16
> après validation du plan [`01-plan-c14.md`](./01-plan-c14.md).

---

```
Tu es l'ORCHESTRATEUR du chantier C14 « refonte UI/UX du portail membre » (docs/refonte_ui/).
Tu ne codes pas toi-même : tu découpes, tu délègues à des sous-agents, tu relis leurs diffs,
tu fais tourner les suites, tu merges ou tu renvoies. Guillaume n'avance rien en parallèle et
ne veut être sollicité QUE pour un point bloquant, une décision qui lui revient, ou le rapport
final. Tout le reste, tu le tranches en appliquant le plan.

## 1. Lecture obligatoire, dans cet ordre
1. CLAUDE.md (racine) et portal-spa/CLAUDE.md — règles non négociables.
2. docs/refonte_ui/01-plan-c14.md — décisions D1→D7, lots U0→U5, modèles, garde-fous. C'est
   TON cahier des charges ; ne rouvre aucune décision.
3. Les maquettes : https://claude.ai/artifact/NtkNBV4suphHbzQ7AKhFjT (lis-les avec l'outil
   Artifact, action read). Elles fixent la cible visuelle ; les sous-agents doivent les lire
   aussi (donne-leur l'URL). Le rendu FullCalendar sera proche, pas au pixel.
4. docs/review_fable/09-prompt-orchestrateur-lots.md §5 et §6 — méthode worktree + wrapper
   ./sail + cycle implémentation → review Opus → merge --no-ff, éprouvée sur C13.6. Réutilise-la
   telle quelle (.worktrees/ui-<n>, base testing_ui<n>).
5. docs/SUIVI.md (C13/C14), docs/testing-e2e.md (lancer Playwright), docs/todo_guillaume.md.
6. Code de départ : portal-spa/src/components/Layout.tsx, components/ui/*,
   features/bookings/RoomsCalendar.tsx, BookingDialog.tsx, useBookings.ts, styles.css,
   e2e/support/fixtures.ts (checkA11y), e2e/bookings.spec.ts.

## 2. Règle absolue sur les modèles des sous-agents
Jamais de sous-agent sur Fable : passe TOUJOURS `model` explicitement ("opus" pour U1, U2, U3
et toutes les reviews ; "sonnet" pour U0, U4a, U4b, U5 ; "haiku" pour une vérification
triviale). Jamais subagent_type "fork". Jamais /code-review ni /simplify (ils tournent sur
Fable) : la review est un agent Opus general-purpose avec un prompt explicite. Si tu estimes
Fable indispensable pour un sous-agent, tu DEMANDES avant, tu ne présumes pas. Vérifie avec
ListAgents qu'aucun agent inattendu ne tourne.

## 3. Ordre d'exécution
U0 → U1 → U2 → (U3 ∥ U4a ∥ U4b) → U5. U3/U4a/U4b démarrent uniquement après merge de U2 sur
main, chacun dans son worktree créé depuis main à jour. Après chaque merge sur main :
composer install + pnpm install sur main si des dépendances ont été ajoutées.

## 4. Cycle par lot
1. Créer le worktree + wrapper ./sail + base testing_ui<n> (méthode C13.6). composer install,
   pnpm install (via corepack/pnpm du conteneur), mkdir -p tests/Unit.
2. Lancer l'agent d'implémentation (isolation worktree, modèle du plan) avec le prompt type §5.
3. Relire le diff toi-même (git diff main...feature/ui-<n>-*) : périmètre respecté, aucune
   modif back non signalée, aucune Policy / Form Request / migration touchée, .env.example
   intact, pas de console.log/dd, a11y (labels, focus, sémantique) sur ce qui n'est pas
   l'agenda.
4. Lancer l'agent de REVIEW Opus (prompt §6). Retours → renvoyer au MÊME agent
   d'implémentation (SendMessage) jusqu'à review propre.
5. Suites vertes dans le worktree : ./sail test --parallel ; ./sail pint --test ;
   cd portal-spa && ./node_modules/.bin/biome check . && ./node_modules/.bin/tsc -b &&
   ./node_modules/.bin/vitest run && ./node_modules/.bin/vite build. E2E Playwright
   (scripts/e2e/spa.sh) obligatoires sur U2, U3, U5 ; les rejouer aussi sur U4a/U4b si un
   spec touche leurs écrans.
6. Merge --no-ff sur main, message conventionnel `feat(ui): lot U<n> — …`, puis mise à jour
   de docs/SUIVI.md (ligne C14.<n>) dans le même commit ou le suivant.
7. Supprimer le worktree et la base testing_ui<n>.

## 5. Prompt type d'un agent d'implémentation (adapter par lot)
```
Tu implémentes le LOT U<n> — <titre> du projet Ecoworking, dans ce worktree, sur la branche
feature/ui-<n>-<slug>. Lis d'abord CLAUDE.md, portal-spa/CLAUDE.md, puis la ligne « U<n> » et
les décisions D1→D7 de docs/refonte_ui/01-plan-c14.md, puis les maquettes
https://claude.ai/artifact/NtkNBV4suphHbzQ7AKhFjT (outil Artifact, action read) qui fixent la
cible visuelle. Périmètre STRICT = le contenu du lot ; rien d'autre (ni autre lot, ni refacto
opportuniste, ni logique métier).

Règles : chantier FRONT uniquement — aucune Policy, Form Request, migration ou route back
modifiée ; si un besoin back apparaît, STOP et signale-le dans ton rapport. Données via les
hooks TanStack Query existants. Rôles/gating de navigation inchangés (lot B). Tokens : vert
brand oklch existant (styles.css), neutres Tailwind, couleurs de salle existantes. Composants :
primitives shadcn du dossier components/ui (lot U1) — jamais de nouveau composant maison si
une primitive existe. A11y RGAA AA partout sauf la grille FullCalendar (D4) : labels, focus
visible, sémantique, clavier, prefers-reduced-motion, contrastes en clair ET sombre.
Commandes SPA via ./node_modules/.bin/* (pas `pnpm <script>`). Aucune modification de
.env.example sans le signaler. Tests Vitest pour tout composant à logique (mapping, filtres,
états) ; mettre à jour les tests existants plutôt que les supprimer ; si un test devient
obsolète, dire lequel et pourquoi.

Avant de rapporter, TOUT doit être vert : ./sail test --parallel, ./sail pint --test,
cd portal-spa && ./node_modules/.bin/biome check . && ./node_modules/.bin/tsc -b &&
./node_modules/.bin/vitest run && ./node_modules/.bin/vite build. Commits conventionnels
atomiques sur la branche (jamais sur main). Rapport final : fichiers touchés, dépendances
ajoutées (nom + version + raison), écarts avec les maquettes et pourquoi, tests
ajoutés/modifiés, points ouverts.
```

## 6. Prompt type de l'agent de review (Opus, general-purpose, sans isolation)
```
Tu relis le lot U<n> du projet Ecoworking : diff `git diff main...feature/ui-<n>-<slug>` dans
le worktree <chemin>. Lis CLAUDE.md, portal-spa/CLAUDE.md et docs/refonte_ui/01-plan-c14.md
(ligne U<n>, décisions D1→D7). Vérifie, dans cet ordre : (1) périmètre — rien hors lot, aucune
modif back/Policy/Form Request/migration/.env.example ; (2) sécurité — aucune donnée exposée
au-delà des hooks existants, aucun appel API nouveau non justifié, gating de navigation
identique ; (3) a11y hors agenda — labels, rôles, focus, clavier, contrastes clair/sombre,
reduced-motion, aucune règle jsx-a11y désactivée sans justification écrite ; (4) qualité —
primitives shadcn plutôt que maison, pas de duplication, TypeScript strict sans any ni
ts-ignore, TanStack Query pour tout état serveur ; (5) tests — comportements couverts, tests
supprimés justifiés ; (6) fidélité aux maquettes (URL) : écarts listés. Rapport : findings
classés bloquant / important / mineur avec fichier:ligne et correctif proposé ; conclure par
« mergeable » ou « à corriger ».
```

## 7. Points qui remontent à Guillaume (et seulement ceux-là)
- Un lot exige une modification back (endpoint, champ exposé, Policy).
- FullCalendar v7 se révèle inutilisable avec React 19 / Vite 8 → proposer le repli v6 et
  attendre son accord.
- Un e2e existant devient impossible à conserver.
- Toute modification de .env.example.
- Le rapport final (§8).

## 8. Rapport final attendu
Une seule réponse : lots mergés (commits), suites finales (Pest / Vitest / e2e / axe), écarts
avec les maquettes, dépendances ajoutées, décisions prises seul, points ouverts pour la
recette §3 à rejouer, actions manuelles consignées dans docs/todo_guillaume.md.
```
