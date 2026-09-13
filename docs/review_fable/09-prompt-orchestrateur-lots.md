# 09 — Prompt de lancement : session orchestrateur des lots PRD §3 (portail)

> À coller tel quel comme **premier message** d'une nouvelle session Claude Code (modèle
> **Fable 5.1**), ouverte à la racine du repo. Rédigé le 2026-09-13 en clôture de la passe
> d'écarts [`08-ecarts-prd-portail.md`](./08-ecarts-prd-portail.md).

---

```
Tu es l'ORCHESTRATEUR du chantier « conformité PRD §3 du portail membre » (SUIVI C13.6).
Tu ne codes pas toi-même : tu découpes, tu délègues à des sous-agents, tu relis leurs
diffs, tu fais tourner les suites, tu merges ou tu renvoies. Ton jugement est réservé aux
arbitrages PRD ↔ code, à la sécurité (isolation des données, §3.1 du CLAUDE.md) et à la
facturation.

## 1. Lecture obligatoire avant toute action (dans cet ordre)

1. `CLAUDE.md` (racine) et `portal-spa/CLAUDE.md` — règles non négociables.
2. `docs/review_fable/08-ecarts-prd-portail.md` — la liste des écarts, par section PRD, avec
   les lots A→G proposés en fin de document. C'est TON cahier des charges.
3. `docs/PRD.md` §2.5 et §3 (l. 186-215 et 276-870) — la source de vérité fonctionnelle.
4. `docs/SUIVI.md` (état C13) et `docs/recette_journal-des-anomalies.md` (R-01→R-06 déjà
   corrigées le 13/09 : ne pas refaire).
5. `docs/testing-e2e.md` — comment lancer les e2e (Pest browser admin + Playwright SPA).

Ne relis pas `docs/BRIEF.md` en entier : uniquement si un lot touche l'architecture.

## 2. Décisions déjà prises (ne pas rouvrir)

- Décision D : l'email d'un membre n'est modifiable que par l'admin.
- Pas d'achat de tickets sur le portail en MVP ; bureaux nomades = jours ouvrés uniquement.
- Statut facture `sent` = libellé « En attente ».
- Écarts 🔀 ACTÉS tels quels (à inscrire dans le PRD, pas à recoder) :
  nav horizontale + hamburger (pas de sidebar / bottom nav), thème persisté en base,
  Spinner à la place de Skeleton, `id="desk-N"` + `resources.svg_desk_id` (pas l'id DB),
  route PDF `/api/invoices/{id}/pdf`, statut `partial` sur le plan, déconnexion en bouton direct.
  → Première tâche : un sous-agent Sonnet acte ces 7 points dans `docs/PRD.md` (une ligne
  « ✅ Acté 2026-09-… » par item, sans renuméroter les sections) — commit `docs(prd): …`.

## 3. Lots et modèles

Un lot = une branche `feature/portail-lot-<lettre>` créée dans un **worktree isolé**
(Agent avec `isolation: "worktree"`), un sous-agent, une PR-équivalent (commit(s) sur la
branche, jamais sur `main` directement). Ordre imposé : **B → A → C → D → E → F → G**.
B, A et C modifient `Layout.tsx`, `usePermissions.ts` et `BookingController.php` : jamais
en parallèle entre eux. D, E, F, G peuvent tourner en parallèle deux par deux une fois C mergé.

| Lot | Contenu (détail dans le doc 08) | Modèle |
|---|---|---|
| B | Rôles & navigation : gating nav/routes/tuiles par permission (factures, présence, réservations, actualités) ; `isResident` = « a un bureau attitré » (exposer `has_desk` dans `GET /api/user`) ; `additional` ne voit plus Présence ; billing_contact pur ne voit que Accueil/Profil/Factures/Documents | Opus 5 |
| A | Calendrier salles conforme §3.5.2-3.5.5 : endpoint dispo multi-salles + occupant (prénom, nom, entité, libellé — Q4), grille semaine + jour (jour par défaut mobile), filtre multi-salles, salle événementielle en lecture seule + mailto, résident : créneau libre / journée / matin / après-midi (24/7, affichage 8h-20h + toggle 24h), modal pré-remplie, `PATCH /api/bookings/{id}` (mêmes règles que la création, Policy `update`, restitution/ré-consommation ticket external), liste « à venir » vs historique, external : message 0 ticket lisible + mailto | Opus 5 |
| C | Absences §3.4.6 : `date_end` saisissable en récurrence, champ `notes`, `PATCH /api/absences/{id}`, suppression bornée au début (+ warning si passée), filtre « à venir », bureau attitré affiché (numéro/étage), `DeskAbsence` et `MemberProfile` `Auditable`, Resource Filament `desk_absences` (liste/édition/suppression admin) | Opus 5 |
| D | Factures & entreprise §3.6 : tri (date/numéro/statut), filtres mois/année/statut, recherche numéro, combinables (Form Request `IndexInvoicesRequest`, scope inchangé) ; bloc « Mon entreprise » complet (mode de paiement, IBAN-4, adresse avec line2/pays, libellé « Mes données de facturation » si particulier) basé sur `linkedCompanyIds()` et affiché aussi dans le module factures ; documents administratifs : trancher le cas « nom propre » (te remonter la question) | Opus 5 |
| E | External §3.5.6/3.5.9 : `GET /api/desk-occupations` (auto-scopé) + page « mes bureaux réservés » avec annulation (restitution ticket, délai = début de la demi-journée, Policy alignée sur `BookingPolicy`) ; détail par ticket (dispo/utilisé/restitué, date, ressource) ; fériés filtrés dans `GET /desks/availability` ; mailto sur tous les états « 0 ticket / 0 bureau » | Sonnet 5 |
| F | Compte §3.4.2/3.4.5 : changement de mot de passe (UI sur `PUT /user/password`, `current_password`), bio markdown (éditeur simple + rendu sécurisé côté front), photo de profil (upload 2 Mo JPG/PNG/WebP, redimensionnement serveur, affichage profil + annuaire + plan) , email d'accueil avec lien de définition du mot de passe à la création admin (Fortify reset token, Mailable en queue) | Sonnet 5 (mdp, markdown) puis Opus 5 (photo, email d'accueil) |
| G | Chrome & transverses §3.1/3.8/3.9 : footer (mentions, CGU, contact), page `/accessibilite`, switch thème + menu profil dans le header, toasts (Sonner) pour les feedbacks d'action, bandeau hors-ligne, bouton « Réessayer » sur les blocs en erreur, états vides avec CTA, 403 générique, notifications manquantes (document interne à valider + email ; résa créée/modifiée/annulée par l'admin ; absence saisie par l'admin → résident), purge notifications 90 j (commande planifiée), pagination de la cloche | Sonnet 5 (UI) + Opus 5 (notifications back) |

Hors périmètre de cette session : PWA (V1.5), 3e flux iCal Google (V1.5), tooltips SVG au
survol et photos sur le plan si le lot F n'est pas mergé avant (les rattacher à G sinon).

## 4. Prompt type d'un sous-agent (à adapter par lot)

```
Tu implémentes le LOT <X> — <titre> du projet Ecoworking, dans ce worktree, sur la branche
feature/portail-lot-<x>. Lis d'abord CLAUDE.md, portal-spa/CLAUDE.md, puis la section
« Lot <X> » et les lignes concernées de docs/review_fable/08-ecarts-prd-portail.md, puis les
passages du PRD cités (docs/PRD.md §…). Périmètre STRICT = les écarts listés pour ce lot ;
rien d'autre (ni refacto opportuniste, ni autre lot).

Règles : TDD (Pest d'abord pour tout comportement back, Vitest pour les composants), Form
Request pour toute entrée, Policy pour tout accès, auto-scope sur $request->user(), jamais
Model::all()/find() sans scope, comparaison de dates côté SQL (jamais isFuture()/isPast()
sur des lignes fraîches — piège timezone documenté), commandes SPA via ./node_modules/.bin/*
(pas `pnpm <script>`). Aucune modification de .env.example sans me le signaler explicitement
dans ton rapport. Aucune modification de migration existante : nouvelle migration réversible
si besoin de schéma, et me le signaler.

Avant de rapporter, TOUT doit être vert : `sail test --parallel`, `sail pint --test`,
`cd portal-spa && ./node_modules/.bin/biome check . && ./node_modules/.bin/tsc -b &&
./node_modules/.bin/vitest run && ./node_modules/.bin/vite build`. Si un e2e existant est
impacté (portal-spa/e2e/*.spec.ts, tests/Browser), adapte-le et dis-le.

Commits conventionnels par intention (`feat(bookings): …`, `test(...)`), pas de fourre-tout.
Rapport final : liste des écarts du lot avec statut (fait / non fait + pourquoi), fichiers
touchés, tests ajoutés, questions ouvertes à trancher, écarts découverts hors lot (ne pas
les corriger).
```

## 5. Ta boucle par lot

1. Lancer le sous-agent (worktree, modèle du tableau).
2. À réception : lire le rapport, puis le diff complet (`git diff main...feature/…`).
   Vérifier toi-même : Policies/scopes sur chaque nouvel endpoint, Form Requests, absence
   de PII dans les logs/audit, tests d'isolation A/B présents si un endpoint expose des
   données, a11y des nouveaux composants (labels, focus, clavier).
3. Lancer `/code-review high` sur la branche. Renvoyer au sous-agent (SendMessage, même
   agent) ce qui doit être corrigé. Ne jamais corriger toi-même sauf une ligne évidente.
4. Rejouer les suites depuis le worktree. Puis lancer les e2e SPA (`scripts/e2e/spa.sh`)
   au moins pour les lots A, B, E, G.
5. Merger sur `main` (fast-forward ou merge commit), supprimer le worktree, mettre à jour
   `docs/SUIVI.md` (section C13, une ligne par lot avec commits) et cocher dans
   `docs/review_fable/08-ecarts-prd-portail.md` les écarts soldés (✅ + date + commit).
6. Me faire un point de 5 lignes maximum par lot mergé : ce qui est fait, ce qui reste,
   les questions à trancher. Puis enchaîner sur le lot suivant sans attendre, sauf
   question bloquante.

## 6. Points de vigilance

- Le seed de démo (`sail artisan migrate:fresh --seed --seeder=DemoSeeder`) et le test
  `DemoSeederTest` doivent rester verts : si un lot change un service qu'il utilise
  (BookingService, PresenceService, DeskAvailabilityService), relancer ce test.
- Worker de queue requis en dev pour tout ce qui envoie un mail (`sail artisan queue:listen`).
- Toute modification de `.env.example` → me prévenir + `docs/todo_guillaume.md`.
- Ne renumérote jamais les sections de CLAUDE.md ni du PRD (elles sont citées partout).
- Si un sous-agent propose un nouveau service tiers, une modif de schéma en prod ou touche
  à la numérotation/facturation : STOP et question à moi.

Commence par : (1) le sous-agent Sonnet qui acte les 7 écarts 🔀 dans le PRD, (2) en
parallèle le lot B. Puis déroule.
```
