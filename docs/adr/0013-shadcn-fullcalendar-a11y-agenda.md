# ADR-0013 : shadcn/ui réellement installé, FullCalendar pour l'agenda des salles, a11y de l'agenda relâchée avec alternative

## Statut

Accepté — 2026-09-16

## Contexte

La recette C13 (§3.1, 16/09) a jugé l'UI du portail datée et le calendrier des réservations de
salles inutilisable (« tout est tassé dans une colonne centrale »). Trois problèmes distincts,
mais liés par le même chantier C14, sont adressés ici. Décisions validées par Guillaume le
2026-09-16, détaillées dans [`refonte_ui/01-plan-c14.md`](../refonte_ui/01-plan-c14.md) (D1, D3,
D4, et incidemment D2/D5/D6/D8).

### 1. shadcn/ui jamais installé

Le `BRIEF.md` §5.3 annonce shadcn/ui depuis mai 2026. En pratique, le portail a vécu tout le MVP
sur un kit maison dans `portal-spa/src/components/ui/` (`Button`, `Input`, `Label`, `Textarea`,
`Select`, `Modal`, `ConfirmButton`, `Alert`, `Spinner`) — jamais remplacé par les primitives
Radix promises. Résultat : composants moins accessibles par défaut, pas de design tokens
partagés, et un écart doc/code qui s'est creusé sans être remonté en ADR.

### 2. Calendrier des salles illisible

Le calendrier actuel (lot A, C13.6) est une grille semaine/jour maison qui affiche les résa des
3 salles + la salle event, contrainte dans la colonne centrale étroite du layout (max ~1024 px) :
créneaux tassés, chevauchements illisibles, pas de vue mois ni de glisser pour créer. Il faut une
vraie librairie de calendrier pour une grille temps multi-salles lisible en pleine largeur.

### 3. Tension accessibilité vs richesse d'interaction

CLAUDE.md §3.5 et PRD §3.1 visent une conformité RGAA 4.1 AA sur tout le portail, y compris les
composants complexes, mais prévoient explicitement une porte de sortie : *« Composants complexes
(calendrier résa, plan des étages SVG) doivent avoir une alternative accessible »*. Aucune
librairie de calendrier riche (drag & drop, redimensionnement, grille dense) n'est pleinement
pilotable au clavier ni lisible par un lecteur d'écran sans un investissement disproportionné au
regard de l'échelle du projet (~50 membres, ~75 entreprises).

### Maquettes de référence

Maquettes validées par Guillaume le 2026-09-16, extraites en artboards HTML statiques dans
[`refonte_ui/maquettes/`](../refonte_ui/maquettes/) (source : canvas Claude Design,
https://claude.ai/artifact/NtkNBV4suphHbzQ7AKhFjT) :
- `Main.dc.html` — accueil desktop 1440 px : sidebar 256 px + top bar 60 px + bento KPI.
- `Mobile.dc.html` — accueil mobile 390 px : bottom nav 5 entrées (Accueil, Résas, Présence,
  Actus, Plus).
- `Reservations.dc.html` / `ReservationsSombre.dc.html` — agenda des salles clair et sombre :
  grille semaine, chips de filtre par salle, popover sur résa, panneau droit (mini-mois + liste
  « Mes prochaines réservations » + abonnement iCal).

## Décision

### D1 — shadcn/ui adopté réellement

`shadcn init` sur Tailwind v4 (`components.json`), primitives Radix. Variables CSS shadcn
mappées sur le vert brand `oklch` déjà en place (pas de nouvelle palette, cf. D6) ; le mode sombre
continue de s'activer via la classe `dark` déjà posée sur `<html>` (pas de nouveau mécanisme).
Primitives retenues (cf. ligne U1 du plan) : `button`, `input`, `label`, `textarea`, `select`,
`checkbox`, `switch`, `dialog`, `sheet`, `popover`, `dropdown-menu`, `tooltip`, `tabs`, `card`,
`badge`, `skeleton`, `separator`, `toggle-group`, `table`, `avatar`, `sidebar`, `scroll-area`.
Le kit maison (`Modal`, `ConfirmButton`, etc.) est migré vers ces primitives (`Modal` → `Dialog`,
`ConfirmButton` → `AlertDialog`) ; `Spinner` est conservé pour les actions ponctuelles (upload,
soumission de formulaire), `Skeleton` shadcn revient pour les états de chargement de bloc.

### D3 — Agenda des salles sur FullCalendar v7

`@fullcalendar/react` + `@fullcalendar/core` + `@fullcalendar/timegrid` + `@fullcalendar/daygrid`
+ `@fullcalendar/interaction` (licence MIT). Une seule grille affichant toutes les salles
simultanément (conforme PRD §3.5.2), chevauchements posés côte à côte (`eventOverlap`), chips
pour masquer une salle, glisser-déposer sur un créneau libre pour ouvrir `BookingDialog`
pré-rempli. Les vues « resource » (Resource Timeline / Resource Timegrid), qui nécessitent une
licence premium FullCalendar, ne sont **pas** utilisées : une seule salle par colonne temporelle
suffit à l'échelle de 3 salles + 1 event.

Repli documenté : si le connecteur `@fullcalendar/react` v7 s'avère instable avec React 19.2 /
Vite 8 (Rolldown), rétrograder vers `@fullcalendar/react` v6 — l'API des plugins core/timegrid/
daygrid/interaction est stable d'une version majeure à l'autre, le coût de repli est donc faible.

> ✅ **Corrigé 2026-09-16 (U5)** — packaging réel constaté à l'usage (U3 puis vérifié en finition) :
> contrairement à la description ci-dessus (héritée de la doc FullCalendar v6, plugins séparés),
> **v7 est un seul paquet** `@fullcalendar/react@7.1`, plugins en **sous-chemins** du même paquet
> (`@fullcalendar/react/timegrid`, `/daygrid`, `/interaction`, `/locales/fr`,
> `/themes/classic`) — pas de `@fullcalendar/core` ni `@fullcalendar/timegrid` séparés en
> dépendances npm. Deux peers obligatoires non anticipés : `temporal-polyfill` (v7 utilise
> `Temporal` en interne) et `@full-ui/headless-calendar`. Le thème **classic** (import CSS
> obligatoire, `themes/classic/palette.css`) fournit les variables `--fc-classic-*`, préfixées par
> le nom du thème (pas `--fc-*` génériques comme en v6) — remappées sur les jetons shadcn dans
> `portal-spa/src/styles.css` (`.fc.ew-agenda` / `.dark .fc.ew-agenda`). Les classes générées par
> FullCalendar v7 sont hachées (pas de `.fc` stable) : `RoomsCalendar` pose donc lui-même la classe
> `.ew-agenda` (et rajoute `.fc` manuellement) sur son conteneur, qui devient le point d'exclusion
> axe documenté (`FULLCALENDAR_AXE_EXCLUDE`, `e2e/support/fixtures.ts`) — sans ce filet, l'exclusion
> `.fc` de la description initiale ne matcherait plus rien. Le repli v6 reste **requalifié
> structurant** (packaging et système de thème différents, migration non triviale) mais s'est avéré
> **non nécessaire** : stabilité constatée avec React 19.2 / Vite 8 sur tout C14 (U3 → U5).

> ✅ **Recette 2026-09-16** — deux retours corrigés sur l'agenda :
> - **Vue Mois retirée** (`dayGridMonth`) : sur cette grille (une seule colonne temporelle par
>   jour, blocs de salles côte à côte), un mois de lignes compactées était illisible et
>   n'apportait rien de plus que la vue Semaine pour se projeter. `AgendaView` ne porte plus que
>   `timeGridDay | timeGridWeek`, le plugin `@fullcalendar/react/daygrid` n'est plus importé.
> - **Hauteur des lignes horaires +50 %** : FullCalendar v7 / thème classic n'expose aucune
>   variable CSS ni règle `.fc-timegrid-slot` pour ça (classes hachées, vérifié dans les sources
>   du paquet et la doc officielle) — une surcharge CSS directe désynchronise en plus la position
>   des événements de la grille (testé). Le seul levier qui repositionne aussi les événements est
>   la combinaison documentée `height` (px) + `expandRows` : `RoomsCalendar.tsx` calcule
>   désormais une hauteur totale (`AGENDA_HEADER_HEIGHT_PX` + une ligne de `AGENDA_ROW_HEIGHT_PX`
>   par heure affichée, 24,98 px mesurés → 37,5 px) au lieu de `height="auto"`.

### D4 — A11y de l'agenda relâchée, alternative accessible obligatoire

La grille FullCalendar (sélecteur `.fc`) est **exclue de l'audit `axe-core`** dans les tests
Playwright e2e ; l'exclusion est documentée en commentaire dans
`portal-spa/e2e/support/fixtures.ts`, à côté du helper d'audit a11y. FullCalendar ne garantit pas
une navigation clavier complète ni une lecture fidèle par lecteur d'écran sur sa grille
temporelle interactive (drag, resize, chevauchements) ; corriger cela en profondeur dépasserait
le coût acceptable pour ~50 membres.

En contrepartie, sur la **même page** « Réservations de salles » :
- une liste complète « Mes réservations », intégralement accessible (HTML sémantique, navigation
  clavier, lue par un lecteur d'écran) — sous la grille dans la colonne de l'agenda (retour de
  recette 2026-09-16 : la maquette la plaçait dans le panneau droit, en réalité relégué tout en
  bas de page une fois la liste sortie de ce panneau) ;
- un bouton « Nouvelle réservation » ouvrant `BookingDialog`, formulaire de saisie manuelle
  (date, heure de début/fin, salle) entièrement accessible, sans dépendre de la grille.

Cela satisfait CLAUDE.md §3.5 : *« Composants complexes (calendrier résa, plan des étages SVG)
doivent avoir une alternative accessible (vue liste pour le calendrier, équivalent texte de
l'occupation des bureaux pour le SVG) »*. Le reste du portail (sidebar, dialogs, tables,
formulaires) reste couvert par l'audit `axe-core` sans exclusion.

### Décisions liées (D2, D5, D6, D8 — actées dans le même lot, détaillées dans le plan)

- **D2 — Layout dashboard** : sidebar desktop 256 px rétractable (état persisté en
  `localStorage`) + top bar 60 px, contenu en largeur max **par page** ; mobile = bottom nav 5
  entrées (Accueil, Réservations, Présence, Actualités, Plus), le reste dans un `Sheet` « Plus ».
  Annule l'écart « nav horizontale + hamburger » acté le 2026-09-13 (cf. PRD §3.1 et §3.9,
  ré-actés ci-dessous).
- **D5 — Typographie Geist auto-hébergée** : `@fontsource-variable/geist`, pas de CDN Google
  Fonts (RGPD — les artboards de maquette utilisent un lien `fonts.googleapis.com` propre au
  canvas de conception, non repris dans le portail, cf.
  [`refonte_ui/maquettes/README.md`](../refonte_ui/maquettes/README.md)). Fallback `system-ui`.
- **D6 — Palette conservée** : vert brand `oklch` existant, neutres Tailwind, couleurs de salle
  inchangées (sky / amber / violet / teal). En thème sombre, les blocs de salle passent en fond
  foncé + texte clair (contraste vérifié AA), cf. `ReservationsSombre.dc.html`.
- **D8 — Pas d'entrée « Mon entreprise » dans la sidebar** : présente sur la maquette
  (`Main.dc.html`, groupe « Administratif »), volontairement retirée — aucune route nouvelle en
  C14. Le bloc entité reste accessible via Factures (`billing_contact`) et l'onglet Entreprise du
  profil (lot U4a).

## Conséquences

### Bénéfices

- Composants réellement accessibles par défaut (Radix), design tokens partagés, doc/code
  réalignés sur le BRIEF.
- Agenda enfin lisible sur une vraie grille horaire multi-salles, sans repartir d'un composant
  maison ni payer une licence premium.
- La dérogation a11y sur `.fc` est explicite, bornée et documentée, avec une alternative
  complète et testée plutôt qu'un vague renvoi de principe.
- Repli v6 documenté à l'avance : pas de blocage si le connecteur React v7 est trouvé instable en
  cours d'implémentation (lot U3).

### Trade-offs assumés

- Migration du kit maison vers shadcn = un lot dédié (U1) avant tout autre changement UI, avec
  mise à jour de tous les call sites (`Modal` → `Dialog`, etc.) et des tests Vitest associés.
- L'agenda FullCalendar n'est pas navigable ni restituable intégralement au clavier / lecteur
  d'écran — accepté explicitement, compensé par la liste + le bouton, jamais par un simple lien
  « skip ».
- FullCalendar impose ses propres variables CSS (`--fc-*`) pour le thème sombre, en plus des
  tokens shadcn — deux systèmes de variables à maintenir sur la même page.
- `Spinner` (kit maison) est conservé en parallèle de `Skeleton` (shadcn) : deux mécanismes de
  chargement coexistent selon le cas d'usage (action ponctuelle vs bloc de contenu), à ne pas
  confondre en review.

## Alternatives considérées

### Agenda : calendarjs

**Pourquoi écarté** : dépôt très récent (12/2025), 44 ★, dépendance à LemonadeJS — maturité et
pérennité insuffisantes pour un composant central du portail.

### Agenda : Schedule-X

**Pourquoi écarté** : les fonctionnalités utiles ici (drag, resize, dessin de créneau) sont
derrière une offre premium payante ; FullCalendar couvre le même besoin en MIT avec les plugins
core/timegrid/daygrid/interaction.

### Agenda : composant custom (statu quo amélioré)

**Pourquoi écarté** : c'est l'option actuelle (colonne centrale) qui a motivé la refonte ; la
maintenir en l'améliorant à la main reproduirait la dette déjà identifiée (pas de vraie gestion
de grille temporelle, de chevauchements, de vues jour/semaine/mois).

### A11y : rendre l'agenda FullCalendar totalement conforme RGAA AA

**Pourquoi écarté** : nécessiterait de réimplémenter une grande partie de l'interaction clavier
et de la sémantique ARIA de FullCalendar, hors de portée raisonnable pour un projet à cette
échelle. L'alternative liste + formulaire manuel couvre le même besoin fonctionnel
(consulter/annuler ses résa, en créer une) avec une accessibilité complète.

### Layout : conserver la nav horizontale + hamburger (écart du 13/09)

**Pourquoi écarté** : jugée en recette moins lisible qu'une sidebar à ce nombre d'entrées (6 +
2 administratif), et `recette.md` §3.12 attend déjà une bottom nav mobile. Revirement assumé,
documenté dans le PRD plutôt que silencieusement remplacé (cf. ci-dessous).

## Mise en œuvre (U1 → U5)

Résumé de ce que chaque lot a livré par rapport aux décisions ci-dessus (détail dans
`docs/SUIVI.md` C14.0-C14.6) :

- **U1-U2** : jetons shadcn mappés sur le vert brand oklch (`portal-spa/src/styles.css`), shell
  sidebar + bottom nav.
- **U3** : agenda FullCalendar réel (packaging v7 corrigé ci-dessus), alternative D4 livrée (liste
  « Mes réservations » + bouton « Nouvelle réservation »).
- **U4a/U4b** : pages restylées, aucun changement sur D3/D4.
- **U5 (finition)** : utilitaire de lien unifié `text-link` (styles.css, `@utility`) remplaçant la
  paire répétée `text-brand-700 dark:text-brand-300` sur ~20 call sites et `Button
  variant="link"` ; contraste `text-destructive` corrigé (`dark:text-red-300`) sur `Badge` et
  `DropdownMenuItem` destructifs ; exclusion axe `.fc` (D4) revérifiée en clair **et** sombre sur
  la page réservations, plus une passe axe complète du reste du portail (voir rapport de lot U5) ;
  aucune régression sur le packaging v7 ni sur le thème `.fc.ew-agenda` documenté ci-dessus.

## Références

- [`refonte_ui/01-plan-c14.md`](../refonte_ui/01-plan-c14.md) — plan des lots C14, décisions
  D1→D8
- [`refonte_ui/maquettes/`](../refonte_ui/maquettes/) — artboards HTML (`Main`, `Mobile`,
  `Reservations`, `ReservationsSombre`) ; source : https://claude.ai/artifact/NtkNBV4suphHbzQ7AKhFjT
- ADR-0006 (stack routing/data-fetching portail) — shadcn/ui déjà annoncé mais non installé
- `docs/PRD.md` §3.1 (principes UI/UX), §3.5.2 (calendrier des salles), §3.9 (layout & navigation)
- `docs/BRIEF.md` §5.3 (frontend portail — SPA React)
- `docs/CLAUDE.md` §3.5 (accessibilité — composants complexes, alternative accessible)
- `portal-spa/e2e/support/fixtures.ts` — exclusion `.fc` de l'audit axe (à documenter au lot U3)
