# C14 — Refonte UI/UX du portail membre (plan des lots)

> Rédigé le 2026-09-16 à l'issue de la revue UI/UX de la recette (Guillaume : « UI pas moderne,
> calendrier des réservations inutilisable, tout est tassé dans une colonne centrale »).
> Maquettes validées : https://claude.ai/artifact/NtkNBV4suphHbzQ7AKhFjT (accueil desktop,
> réservations agenda clair + sombre, accueil mobile).
> Prompt de lancement de la session orchestrateur : [`02-prompt-orchestrateur.md`](./02-prompt-orchestrateur.md).

---

## 1. Décisions (validées par Guillaume le 2026-09-16)

| # | Décision | Détail |
|---|---|---|
| D1 | **shadcn/ui adopté réellement** | Le BRIEF §5 le déclarait, il n'a jamais été installé (kit maison dans `components/ui/`). `shadcn init` sur Tailwind v4, primitives Radix. ADR-0013 |
| D2 | **Layout dashboard avec sidebar** | Sidebar 256 px rétractable, top bar 60 px, contenu pleine largeur avec largeur max **par page** ; mobile = bottom nav 5 entrées (attendue par `recette.md` §3.12). Renverse l'écart PRD « nav horizontale + hamburger » acté le 13/09 (lot B) : à ré-acter dans le PRD |
| D3 | **Agenda des salles = FullCalendar v7** | `@fullcalendar/react` + `core` + `timegrid` + `daygrid` + `interaction` (MIT). Une seule grille, toutes les salles, blocs colorés par salle posés côte à côte en cas de chevauchement, chips pour masquer une salle, glisser pour créer. Vues ressources (payantes) non utilisées. Repli v6 si le connecteur React v7 s'avère instable (même API). Rejet motivé : calendarjs (repo de 12/2025, 44 ★, dépend de LemonadeJS), Schedule-X (drag/resize/draw premium), agenda custom (dette) |
| D4 | **A11y de l'agenda relâchée, alternative obligatoire** | La grille FullCalendar n'est pas auditée par axe (exclusion `.fc` documentée dans `fixtures.ts`). En contrepartie, sur la même page : liste « Mes réservations » complète + bouton « Nouvelle réservation » ouvrant `BookingDialog` (saisie manuelle date/heure/salle). Satisfait CLAUDE.md §3.5 « alternative accessible » |
| D5 | **Typographie Geist auto-hébergée** | `@fontsource-variable/geist` (pas de CDN Google Fonts, RGPD). Fallback `system-ui` |
| D6 | **Palette conservée** | Vert brand oklch existant, neutres Tailwind, couleurs de salle sky / amber / violet / teal inchangées ; thème sombre = fonds de salle foncés + texte clair (contraste AA) |
| D7 | **Recette** | §3 (écrans portail) rejoué **après** C14 ; §4→§7 (admin, isolation, mails, jobs) indépendants de l'UI |
| D8 | **Pas d'entrée « Mon entreprise » dans la sidebar** | Présente sur la maquette, retirée : aucune route nouvelle. Le bloc entité reste dans Factures (billing_contact) et dans l'onglet Entreprise du profil (U4a) |

## 2. Lots, ordre et modèles

Un lot = un worktree `.worktrees/ui-<n>`, branche `feature/ui-<n>-<slug>`, wrapper `./sail`
non versionné (base `testing_ui<n>`), un sous-agent d'implémentation, un sous-agent Opus de review,
suites vertes, merge `--no-ff` sur `main` par l'orchestrateur. Méthode éprouvée sur C13.6.

**Jamais de sous-agent sur Fable** (règle absolue) : Opus pour l'implémentation complexe et les
reviews, Sonnet pour l'UI de pages et la doc, Haiku pour les vérifications triviales.

| Lot | Contenu | Dépend de | Modèle impl. | Review |
|---|---|---|---|---|
| **U0** Docs | ADR-0013 (shadcn réel + FullCalendar + a11y agenda), PRD : ré-acter l'écart nav (sidebar + bottom nav), §3.5.2 mention FullCalendar ; BRIEF §5 (FullCalendar, fontsource, TanStack Table) ; SUIVI C14 ouvert | — | Sonnet | orchestrateur |
| **U1** Design system | `shadcn init` (Tailwind v4, `components.json`, variables CSS mappées sur le vert brand oklch, `dark` via classe existante) ; primitives : button, input, label, textarea, select, checkbox, switch, dialog, sheet, popover, dropdown-menu, tooltip, tabs, card, badge, skeleton, separator, toggle-group, table, avatar, sidebar, scroll-area ; Geist via fontsource ; migration de `components/ui/*` maison vers les primitives (call sites mis à jour, `Modal` → `Dialog`, `ConfirmButton` → `AlertDialog`) ; `Spinner` conservé, `Skeleton` ajouté ; Biome + jsx-a11y verts ; tous les tests Vitest existants verts | U0 | Opus | Opus |
| **U2** Shell | `Layout.tsx` → sidebar shadcn (groupes : principal / Administratif, filtrage par permission inchangé, état réduit persisté en localStorage), top bar (titre de page via `PageHeader`, cloche, thème, profil), bloc profil bas de sidebar (reprend `ProfileMenu`), bottom nav mobile (5 entrées + « Plus » en Sheet), largeur max par page (`PageContainer` `full` / `wide` / `narrow`), skip link + focus main conservés ; `PublicLayout` (login, légal) restylé ; tests `Layout.test.tsx`, e2e `auth.spec.ts`/`a11y.spec.ts` adaptés | U1 | Opus | Opus |
| **U3** Agenda | `RoomsCalendar` réécrit sur FullCalendar (timeGridWeek / timeGridDay / dayGridMonth, `locale: fr`, `firstDay: 1`, `slotMinTime/MaxTime` 08–20 + toggle 24 h, external : 09–18 + `selectConstraint` demi-journées jours ouvrés, `nowIndicator`, `selectable` → `BookingDialog` pré-rempli, `eventClick` → Popover (sienne : Modifier / Annuler ; autre : occupant + entité + libellé Q4 ; event room : lecture seule + mailto), `eventOverlap` côte à côte, couleurs par salle via `eventContent`/classNames, chips de filtre des salles, vue jour par défaut < 768 px) ; mapping `GET /rooms/availability` → events ; panneau droit : mini-mois (shadcn Calendar) + `MyBookingsList` + `CalendarSubscription` ; bouton « Nouvelle réservation » ; thème sombre via variables `--fc-*` ; exclusion axe `.fc` documentée ; tests Vitest réécrits (mapping events, filtres, contraintes external, popover), `bookings.spec.ts` adapté ; aucune modification back sauf besoin prouvé (à signaler) | U2 | Opus | Opus |
| **U4a** Pages 1 | Dashboard bento (4 KPI : prochaine résa, mon bureau / tickets restants selon rôle, document à valider, dernière facture ; blocs résas / actus ; bandeau factures) ; Factures en DataTable (`@tanstack/react-table` + shadcn Table : tri, filtres, recherche existants conservés, pagination) ; Profil en onglets (Profil / Compte / Préférences / Entreprise) | U2 | Sonnet | Opus |
| **U4b** Pages 2 | Tickets & bureaux nomades (cartes solde + liste), Documents (liste + états), Actualités (liste + détail), Annuaire (grille de cartes) + Plan des étages pleine largeur avec panneau latéral Sheet, Présence (bureau + absences), états vides / erreurs / skeletons homogènes | U2 | Sonnet | Opus |
| **U5** Finition | Passe axe sur toutes les pages en clair + sombre (Playwright), Lighthouse a11y ≥ 95 hors agenda, `prefers-reduced-motion`, zoom 200 %, mobile 390 px sans scroll horizontal ; `recette.md` §3 mis à jour (bottom nav, agenda FullCalendar, liste + bouton) ; SUIVI / BRIEF / `todo_guillaume.md` (actions manuelles éventuelles) ; nettoyage des composants morts | U3 + U4a + U4b | Sonnet | orchestrateur |

Parallélisme : U3, U4a et U4b tournent **en parallèle** une fois U2 mergé (fichiers disjoints :
`features/bookings` vs `features/dashboard|invoices|profile` vs le reste). U4a et U4b ne touchent
ni `Layout.tsx` ni `components/ui/`.

## 3. Garde-fous

- Aucune logique métier ni Policy ni Form Request modifiée : chantier **front**. Toute modif
  back (nouvel endpoint, champ exposé) est signalée à l'orchestrateur avant d'être codée.
- Aucune modification de `.env.example` sans le signaler (Guillaume synchronise à la main).
- Rôles et gating de navigation (lot B) **inchangés** : mêmes permissions, autre habillage.
- Isolation des données : aucune requête nouvelle côté SPA sans passer par les hooks TanStack
  Query existants (`useBookings`, `useInvoices`…).
- Commandes SPA via `./node_modules/.bin/*` (jamais `pnpm <script>`).
- Chaque lot : `sail test --parallel`, `sail pint --test`, `biome check`, `tsc -b`, `vitest run`,
  `vite build` verts ; e2e Playwright rejoués sur U2, U3 et U5.
- Un lot ne dépasse pas ~1 500 lignes de diff hors tests et hors fichiers générés shadcn ;
  sinon le découper.

## 4. Livrable attendu

- 7 merges sur `main` (U0→U5), SUIVI C14 ✅, ADR-0013, PRD et BRIEF alignés.
- Portail : sidebar + bottom nav, agenda FullCalendar, pages restylées shadcn, thème sombre.
- Suites : Pest inchangé (657), Vitest ≥ existant, e2e 23 + audits axe verts.
- Rapport final de l'orchestrateur : décisions prises, écarts avec les maquettes, points ouverts.
