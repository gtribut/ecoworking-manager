# Recette manuelle — portail membre + back-office admin

> **Objectif** : dérouler à la main tous les parcours du MVP (PRD §3 portail, §4 back-office,
> §5 workflows) sur la base de démo, cocher ce qui passe, consigner ce qui casse.
> Complète les suites automatisées (444 Pest, 72 Vitest, 26 e2e) : ici on juge l'**usage réel**
> (UX, libellés, cohérence des données, emails reçus), pas la couverture de code.
>
> **Comment l'utiliser** : cocher au fil de l'eau — `[x]` testé ET validé, `[-]` testé ET problème/non conforme, `[ ]` pas testé.
> Noter les anomalies dans **[`recette_journal-des-anomalies.md`](./recette_journal-des-anomalies.md)** (fichier séparé,
> éditable à part) avec un identifiant `R-nn`, puis me dire « corrige R-03 ».
>
> Chaque item décrit le **résultat attendu** ; si l'écran fait autre chose, c'est une anomalie (ou un écart PRD à trancher — le signaler comme tel).
>
> Démarrée le : 13/09/2026 · Terminée le : ______ · Testeur : Guillaume

---

## 0. Préparation de l'environnement

- [x] Docker Desktop lancé, intégration WSL2 active (`docker ps` répond dans WSL)
- [x] `sail up -d` → conteneurs `laravel.test`, `pgsql`, `mailpit` up
- [x] Suites vertes avant de commencer (référence) :
  ```bash
  sail test
  sail pint --test
  cd portal-spa && ./node_modules/.bin/biome check . && ./node_modules/.bin/vitest run
  ```
- [x] `.env` local synchronisé (cf. `todo_guillaume.md` §Synchronisation) : `APP_TIMEZONE=Europe/Paris`, variables Redis retirées
- [x] `SEED_ADMIN_PASSWORD` renseigné dans `.env` (sinon le mot de passe admin est affiché une seule fois au seed)
- [x] **Base de démo chargée** (efface la base de dev) :
  ```bash
  sail artisan migrate:fresh --seed --seeder=DemoSeeder
  ```
  Attendu : tableau des comptes en fin de run, aucune erreur. PDF factures et notifications générés immédiatement (queue forcée en `sync` par le seeder). Les emails d'émission partent dans Mailpit.
- [x] `/etc/hosts` Windows : `127.0.0.1 admin.ecoworking.test` et `127.0.0.1 portail.ecoworking.test` (BRIEF §14)
- [x] Portail servi, au choix (⚠️ toujours via `./node_modules/.bin/*` : `pnpm <script>` échoue sur le dep-check `msw`) :
  - **mode prod-like** (recommandé pour la recette) :
    ```bash
    cd portal-spa && ./node_modules/.bin/tsc -b && ./node_modules/.bin/vite build
    ```
    puis http://portail.ecoworking.test (à relancer après chaque modification SPA)
  - **mode dev** (HMR) : `cd portal-spa && ./node_modules/.bin/vite` puis http://portail.ecoworking.test:5173
- [x] Back-office : http://admin.ecoworking.test
- [x] Mailpit : http://localhost:8025 (vider la boîte avant de commencer)
- [ ] **Worker de queue OBLIGATOIRE pendant la recette** (R-02) : `QUEUE_CONNECTION=database` en dev → sans worker, aucun mail ne part (magic link, reset mot de passe, facture émise…) et aucun PDF n'est généré hors seed. Dans un second terminal, laisser tourner :
  ```bash
  sail artisan queue:listen --tries=1
  ```
  (le seed, lui, force la queue en `sync` : il n'en a pas besoin)

> ⚠️ WSL : si `localhost` ne répond pas depuis Windows, utiliser l'IP de la VM WSL (`hostname -I`) — piège connu.

---

## 1. Comptes de démo

Mot de passe commun : **`demo-password`** (sauf admin).

| Email                                  | Rôle(s)                    | Ce que le compte permet de tester                                                                                                                                             |
| -------------------------------------- | -------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `admin@ecoworking.fr`                  | admin                      | Back-office complet. Mot de passe = `SEED_ADMIN_PASSWORD`. 2FA TOTP obligatoire au 1er login                                                                                  |
| `claire.fontaine@atelier-lumiere.demo` | resident + billing_contact | Bureau 1 · factures Atelier Lumière (payées, remise 10 %, avoir) · docs admin (contrat + domiciliation) · charte v2 **à revalider** · inscrite à l'apéro                      |
| `marc.delorme@atelier-lumiere.demo`    | resident                   | Bureau 2 · **opt-out annuaire** · docs à jour · pas d'accès factures                                                                                                          |
| `ines.rahmani@atelier-lumiere.demo`    | resident                   | Bureau 3 · absences : **tous les vendredis** (3 mois) + **congés semaine prochaine**                                                                                          |
| `julien.petit@atelier-lumiere.demo`    | additional                 | Sans bureau · sans factures · résa salle gratuite possible                                                                                                                    |
| `sophie.verger@studio-verger.demo`     | resident + billing_contact | Bureau 30 (étage 2) · factures Studio Verger : M-1 **en retard**, M-2 **en retard avec acompte 50 %** · absence demi-journée saisie par l'admin                               |
| `karim.haddad@studio-verger.demo`      | resident (profil en pause) | Bureau 31 · abonnement en **pause**                                                                                                                                           |
| `thomas.bernard@demo.fr`               | external + billing_contact | Particulier · **6 tickets bureau + 1 ticket salle** dispo (4 + 1 utilisés) · résa salle et bureau nomade à venir · facture tickets **partiellement payée** (acompte CB 100 €) |
| `lea.moreau@nova-conseil.demo`         | external                   | **Aucun ticket** → tous les états vides / messages « contactez-nous »                                                                                                         |
| `camille.roux@ecoworking.fr`           | staff                      | Bureau 29 attitré staff · « Équipe Ecoworking » dans l'annuaire                                                                                                               |
| `paul.ancien@studio-verger.demo`       | (anonymisé)                | Connexion **impossible** · factures passées Studio Verger conservées · entrée audit `anonymized`                                                                              |

Données de cadre : catalogue 8 offres (prix PRD), 49 bureaux (29 + 20), 3 salles de réunion (71 € HT la demi-journée external), 1 salle événementielle (admin only). Factures récurrentes émises sur M-3, M-2, M-1 ; brouillons du mois courant non émis.

---

## 2. Portail — authentification (PRD §3.2, C2.3, C12.8a)

Compte : n'importe quel membre (commencer par Claire).

- [x] `/login` : email + mot de passe → redirection dashboard, « Bonjour Claire »
- [x] Mauvais mot de passe → message générique (ne révèle pas si l'email existe), champ en erreur annoncé (aria)
- [x] 6 tentatives ratées en 1 min → message de limitation (429)
- [x] **Lien de connexion (magic link)** : saisir l'email → message générique identique que l'email existe ou non → mail reçu dans Mailpit → clic → connecté. Recliquer le même lien → refusé (usage unique). Lien > 15 min → refusé
- [x] Magic link avec l'email de `paul.ancien@…` (anonymisé) → aucun mail envoyé, même message générique
- [x] **Mot de passe oublié** → mail Mailpit → nouveau mot de passe → connexion OK → l'ancien magic link éventuel est invalidé
- [-] **2FA membre (optionnelle)** : activer dans le profil → QR code → code TOTP → à la reconnexion, challenge 2FA ; codes de récupération utilisables
- [x] Déconnexion (menu profil) → retour `/login` ; bouton Précédent du navigateur ne ré-affiche pas de données
- [x] Session expirée (supprimer le cookie de session puis naviguer) → redirection `/login` propre, pas d'écran blanc
- [x] URL protégée en anonyme (`/invoices`) → `/login` puis retour sur la page demandée après connexion
- [x] Un membre ne peut **pas** se connecter sur admin.ecoworking.test (403 / « accès refusé »)

---

## 3. Portail — écrans membre

### 3.1 Accueil / dashboard (PRD §3.3)

Tester avec **Claire**, puis **Julien** (additional), puis **Léa** (external sans ticket).

> ⚠️ **UI refondue en C14** (sidebar + bottom nav, dashboard « bento » en tuiles KPI) : rejouer
> l'intégralité de §3 sur la nouvelle UI, y compris les lignes déjà `[x]` avant C14 dont le
> comportement fonctionnel n'a pas changé — seul l'habillage visuel a bougé, mais une régression
> de layout doit pouvoir être détectée.

- [ ] Entête « Bonjour {prénom} », puis **rangée de tuiles KPI** (bento, jusqu'à 4 selon le rôle :
      prochaine résa, bureau attitré/tickets restants, documents à valider, dernière facture) —
      grille en 2 colonnes sur mobile, `auto-fit` au-delà de `lg` (pas de colonnes vides si < 4 tuiles)
- [x] Bloc **Mes dernières factures** (Claire) : 3 max, numéro `EW-AAAA-NNNNN`, date, badge statut coloré, bouton PDF ; lien « Voir toutes mes factures »
- [x] Bloc factures **masqué** pour Julien (pas billing_contact) et Marc
- [x] Bloc **Actualités** : 3 dernières, badge Info / Alerte / Événement, lien « Voir toutes »
- [ ] **Tuile KPI « Documents à valider »** (Claire, teinte ambre) : titre du premier document en
      attente + lien « Lire et valider » vers `/documents` — **plus de bouton Télécharger/Valider
      directement sur l'accueil**, uniquement sur la page Documents (§3.8)
- [ ] Tuile documents **entièrement masquée** chez Marc si rien à valider (recette R-06) — la
      grille passe à 3 tuiles sans laisser de vide
- [x] Bloc **Mes prochaines réservations** : 3 max, ressource + date + créneau ; résa annulée **absente**
- [ ] États vides chez Léa : messages rassurants (aucune facture / résa / actualité restreinte aux résidents non visible)
- [ ] **Bouton « Nous contacter » retiré de l'accueil** (desktop et mobile) : le lien `mailto:`
      identique se trouve dans le **pied de page** (toutes tailles d'écran) et dans la **top bar**
      en desktop uniquement
- [x] Skeletons pendant le chargement (throttling réseau « Slow 3G » dans DevTools), jamais de spinner plein écran

### 3.2 Profil (PRD §3.4)

- [ ] Page organisée en **4 onglets** (Profil / Compte / Préférences / Entreprise), état dans
      l'URL (`?tab=`) — recharger la page sur `/profile?tab=compte` rouvre directement cet onglet
- [ ] Soumettre le formulaire d'un onglet avec un champ en erreur pendant qu'un autre onglet est
      affiché → **bascule automatiquement** sur l'onglet contenant l'erreur
- [ ] Onglet **Entreprise** toujours présent (jamais masqué) ; pour un compte sans entité
      rattachée, affiche un message explicite au lieu du bloc entité
- [ ] Nom / prénom **en lecture seule** ; **email non modifiable** (décision D : admin only) — aucun champ email éditable
- [ ] Éditer poste, présentation, centres d'intérêt, LinkedIn, site web → enregistrer → toast succès → rechargement : valeurs persistées
- [ ] URL LinkedIn invalide (`pas-une-url`) → erreur de validation lisible sous le champ (422), pas de toast succès
- [ ] Toggle **Afficher mon profil dans l'annuaire** : décocher chez Claire → vérifier dans l'annuaire (Marc connecté) qu'elle devient « Coworker (souhaite rester discret) » → recocher
- [ ] Toggle newsletter persiste
- [ ] **Préférences de notification** : email / in-app indépendants, activés par défaut ; décocher email chez Sophie puis émettre une facture Studio Verger côté admin (§4.11) → notif in-app oui, mail Mailpit non
- [ ] Bloc **Mon entreprise** (Claire) : raison sociale, forme, SIRET, TVA, email factu, adresse — lecture seule ; bouton « Demander une modification » = mailto
- [ ] Bloc entreprise chez Thomas (particulier) : libellé adapté (« Mes données de facturation »), pas de SIRET/TVA
- [ ] **Changement de mot de passe** : ancien mot de passe faux → erreur ; OK → reconnexion avec le nouveau
- [ ] **Thème** clair / sombre / système : le choix persiste après rechargement et après déconnexion/reconnexion
- [ ] Photo de profil : upload JPG < 2 Mo OK, avatar initiales si absente, fichier > 2 Mo ou PDF refusé proprement *(si l'upload photo est livré — sinon noter « non implémenté MVP »)*

### 3.3 Mon bureau & mes absences (PRD §3.4.6 — resident / staff)

Compte : **Inès**, puis **Camille** (staff), puis **Julien** (ne doit pas voir la section).

- [ ] Section visible pour Inès et Camille, **absente** pour Julien, Thomas, Léa
- [ ] Bureau attitré affiché (Bureau 3, étage 1)
- [ ] Liste des absences à venir : récurrence « tous les vendredis jusqu'au … » + plage « congés du … au … »
- [ ] Déclarer **jour unique** demain matin → apparaît dans la liste → visible sur le plan des étages (§3.7) comme vacant le matin
- [ ] Déclarer une **plage** → OK ; date de fin < date de début → erreur de validation
- [ ] Déclarer une **récurrence hebdo** sur une plage → OK ; jour de semaine obligatoire
- [ ] Supprimer une absence à venir → disparaît ; supprimer une absence passée → avertissement (ou refus) selon PRD
- [ ] Côté admin : chaque déclaration a généré une **notification in-app admin** (« absence déclarée », Q25) — vérifier sur le dashboard / la cloche admin

### 3.4 Réservation de salles (PRD §3.5, C5.5)

> ⚠️ **Agenda entièrement réécrit en C14 (U3, FullCalendar v7, ADR-0013)** : la grille maison
> semaine/jour a été remplacée. La grille FullCalendar elle-même est **hors périmètre de l'audit
> a11y** (dérogation D4, ADR-0013) — vérifier ci-dessous surtout son alternative accessible (liste
> « Mes réservations » + bouton « Nouvelle réservation »), qui elle reste couverte à 100 %.

Compte : **Claire** (resident).

- [ ] Grille FullCalendar : les 3 salles + la salle événementielle affichées simultanément, une
      **chip de filtre par salle** (masquer/afficher), sélecteur **Jour / Semaine** uniquement
      (vue Mois retirée en recette 16/09 — illisible sur cette grille), vue semaine par défaut,
      jour < 768 px, navigation « Période précédente/suivante/Aujourd'hui », **mini-mois**
      (panneau droit) pour sauter à une date arbitraire
- [ ] Lignes horaires nettement plus hautes qu'avant (recette 16/09, +50 %) : le contenu d'un
      créneau d'1 h (titre, horaire · salle, occupant) tient confortablement sans être tassé
- [ ] Toggle « Voir 24 h » (resident/additional) bascule la plage horaire 8 h–20 h ↔ 0 h–24 h
- [ ] **Glisser-déposer** sur un créneau libre de la grille ouvre la modale de résa, salle
      pré-remplie avec la **première salle réservable affichée** (pas nécessairement celle du
      créneau glissé — modifiable dans la modale avant de valider)
- [ ] Bouton **« Nouvelle réservation »** (toujours visible, hors grille) ouvre la même modale en
      saisie 100 % manuelle (date, heure début/fin, salle) — c'est l'**alternative accessible**
      complète à la grille (D4), à tester spécifiquement au clavier/lecteur d'écran
- [ ] Liste **« Mes prochaines réservations »** (sous la grille, dans la colonne de l'agenda —
      recette 16/09 : plus dans le panneau droit ni tout en bas de page) : entièrement
      accessible, synchronisée avec la grille, actions Modifier/Annuler
- [ ] Résas des autres visibles dans la grille ; clic → **popover** → prénom + nom + entité +
      libellé (transparence Q4) ; aucune action Modifier/Annuler (résa d'un tiers)
- [ ] Clic sur **sa propre résa** dans la grille → popover avec boutons **Modifier** / **Annuler**
      (confirmation en deux temps pour Annuler)
- [ ] Ses propres résas mises en avant (contour renforcé + couleur brand pleine dans le bloc)
- [ ] **Afterwork Ecoworking** (salle événementielle, résa interne admin) visible en lecture seule,
      distinguée visuellement par un motif hachuré (pas la couleur seule)
- [ ] Bouton dédié « [Salle événementielle] : sur demande » **et** clic sur un bloc **occupé** de
      la salle event → même message « contactez-nous » + mailto, jamais de formulaire de résa
- [ ] Créer une résa (modale, glisser ou bouton) : demain 15 h–17 h, salle 1, libellé → toast
      succès, grille rafraîchie, résa dans « Mes prochaines réservations »
- [ ] Toggles Journée / Matin / Après-midi / Créneau perso (dans la modale) pré-remplissent correctement
- [ ] Résa **hors heures ouvrées** (23 h–1 h) acceptée pour un resident (24/7, toggle 24 h activé)
- [ ] **Conflit** : tenter la salle 3 le jour de la journée complète de Sophie → 409, message clair, aucune résa créée
- [ ] Course : ouvrir 2 onglets, créer la même résa dans chacun → un seul succès, l'autre 409 (backstop GiST)
- [ ] Fin ≤ début → erreur de validation 422 lisible
- [ ] **Annuler** sa résa à venir (popover grille **ou** liste) → confirmation → disparaît ;
      tenter d'annuler une résa **passée** (Kick-off refonte site) → impossible (bouton absent ou 422)
- [ ] Résa de Marc : aucun bouton modifier/annuler pour Claire, ni dans le popover ni dans la liste
- [ ] Vérifier côté admin (Bookings) : la résa créée porte `user` = Claire, statut confirmée, prix vide (gratuit resident) ; l'annulation est **dans l'audit log**
- [ ] Mobile 390 px : la page réservations tient sans scroll horizontal (vue jour par défaut)

Compte : **Julien** (additional) — même flow gratuit, une résa créée OK.

Compte : **Thomas** (external, 1 ticket salle dispo).

- [ ] Calendrier restreint 9 h–18 h ; seuls créneaux **matin 9–13** / **après-midi 14–18**, **jours ouvrés** proposés
- [ ] Sa résa existante « Entretiens de recrutement » indique le **ticket consommé**
- [ ] Réserver un après-midi → succès → solde tickets salle passe à **0**
- [ ] Nouvelle tentative sans ticket → message « contactez Ecoworking » (pas d'achat en ligne), aucune résa
- [ ] Tenter un samedi ou un créneau 10 h–12 h (via l'UI ou en forgeant la requête) → 422 métier
- [ ] **Annuler** la résa avec ticket (avant le début) → ticket **restitué** (solde salle repasse à 1)

Compte : **Léa** (0 ticket) : message d'invitation à contacter Ecoworking dès l'ouverture du module, aucune résa possible.

### 3.5 Abonnement agenda iCal (PRD §3.5.8, C9.2)

- [ ] Sur la page Réservations : 2 URLs (« Mes réservations », « Réservations de mon entité »), bouton copier
- [ ] Ouvrir l'URL perso dans le navigateur → fichier `.ics` valide (importer dans Google Agenda / Thunderbird) contenant uniquement ses résas salles
- [ ] URL entité (Claire) → contient aussi les résas de Marc et Inès (Atelier Lumière), pas celles de Sophie
- [ ] **Régénérer** le token → ancienne URL → 404, nouvelle OK
- [ ] **Désactiver** → URL → 404 ; réactivation possible
- [ ] Julien (additional, même entité) obtient le flux entité Atelier Lumière

### 3.6 Mes tickets & bureau nomade (PRD §3.5.6, §3.5.9 — external)

Compte : **Thomas**.

- [ ] Page « Mes tickets » : compteurs **bureau 6 dispo / 4 utilisés**, **salle 1 dispo / 1 utilisé** ; liste avec date d'utilisation et ressource
- [ ] Occupation à venir (bureau nomade, prochain jour ouvré matin) listée avec le ticket lié
- [ ] Réserver un bureau : sélecteur date + période ; **week-end refusé** côté client ET serveur ; jour férié refusé
- [ ] Vue plan filtrée : **uniquement** les bureaux libres non attitrés cliquables ; aucun nom de résident visible ; compteur « X bureaux libres »
- [ ] Alternative texte de la vue plan (liste des bureaux libres) présente et navigable au clavier
- [ ] Réserver un bureau demain après-midi → succès → solde bureau 5 → occupation visible côté admin (DeskOccupations + Occupation du jour)
- [ ] Réserver le même bureau, même période, avec un autre external (créditer 1 ticket à Léa côté admin, §4.7) → refusé, autre bureau proposé
- [ ] Annuler l'occupation à venir → ticket restitué (solde +1) ; annuler une occupation **passée** → impossible
- [ ] Léa (0 ticket) : message « contactez-nous », aucune sélection possible
- [ ] Claire (resident) ne voit **pas** ce module (ni « Mes tickets »)

### 3.7 Administratif & facturation (PRD §3.6 — billing_contact)

Compte : **Claire**, puis **Sophie**, puis **Thomas**. Puis **Marc** (ne doit rien voir).

- [ ] Module **absent de la navigation** pour Marc, Inès, Julien, Léa ; URL directe `/invoices` → liste vide ou 403, jamais les factures d'autrui
- [ ] Claire : liste des factures **Atelier Lumière** uniquement (M-3, M-2, M-1 payées + la facture annulée + son avoir) — aucune facture Studio Verger, aucun brouillon du mois courant
- [ ] Liste rendue en **DataTable** (tri par colonne au clic sur l'en-tête, `aria-sort` mis à jour,
      focus restauré sur le bouton de tri après le rechargement des données)
- [ ] Colonnes : numéro, date d'émission, statut (badge), total TTC, PDF ; tri date décroissante par défaut ; pagination
- [ ] Filtres : statut, mois/année ; recherche par numéro
- [ ] **Facture annulée** : statut « annulée », avoir associé visible (numéro suivant, montants négatifs)
- [ ] Télécharger un PDF → ouvre le PDF : en-tête Ecoworking, mentions CGI art. 289, adresse facturation snapshotée, lignes regroupées par prestation (3 × Bureau résident, 1 × Personne supplémentaire, Domiciliation), **remise 10 %** visible, TVA 20 %, totaux corrects au centime
- [ ] Vérifier un calcul : Bureau résident 328,50 × 3 − 10 % = 886,95 HT → TVA 177,39 → 1 064,34 TTC (ligne seule ; recouper avec le total de la facture)
- [ ] Sophie : facture **M-1 en retard** (badge rouge, rien payé), **M-2 en retard avec acompte** (montant payé / restant dû affichés), M-3 payée ; les factures incluent la ligne de Paul sur M-3/M-2 (avant son départ)
- [ ] Thomas : facture tickets (pack 10 bureau 140 € + 2 salle 71 € = 282 € HT, 338,40 € TTC) **partiellement payée** (acompte 100 €, échéance à venir), au nom du particulier sans SIRET/TVA
- [ ] **Documents administratifs** (Claire) : « Contrat de mise à disposition — 3 postes » + « Contrat de domiciliation », téléchargement PDF OK ; Sophie : 1 contrat ; Thomas : aucun (état vide)
- [ ] Forger l'URL `/api/invoices/{id}/pdf` d'une facture Studio Verger connecté en Claire → **403**
- [ ] Bloc **Mon entreprise** : IBAN affiché **uniquement** par ses 4 derniers chiffres (`•••• 4821`), mode de paiement SEPA

### 3.8 Documents internes à valider (PRD §5.3, C12.4)

Compte : **Claire**.

- [ ] Page Documents : Charte v2.0 « à valider », CGU « validée le … (v1.0) », Droit à l'image « à valider »
- [ ] Télécharger la charte → PDF
- [ ] **Valider** la charte → « Validé le {aujourd'hui} », disparaît du bloc dashboard ; re-valider → 422 « déjà validée »
- [ ] Côté admin (InternalDocuments) : compteur de validations mis à jour, historique conservé (v1.0 **et** v2.0 pour Claire)
- [ ] Publier une **nouvelle version** de la charte (v2.1) côté admin → Claire et Marc repassent « à valider », historique intact
- [ ] Léa (external) : voit les docs d'audience `all` (charte, CGU) mais pas « droit à l'image » (résidents)
- [ ] L'accès au portail n'est **pas bloqué** par un document non validé (règle §5.3 tranchée)

### 3.9 Actualités & événements (PRD §4.11 côté portail, C12.3)

Compte : **Claire**, puis **Léa**.

- [ ] Liste : 5 annonces publiées pour Claire ; « Rappel : tri des déchets » (résidents) **absent** pour Léa ; brouillon « Fête de fin d'année » **jamais** visible
- [ ] Détail : corps complet, date, lieu et horaires pour l'événement, jauge « 3 / 12 inscrits »
- [ ] **Apéro coworking** : Claire déjà inscrite → bouton « Se désinscrire » → désinscription → jauge 2/12 → réinscription OK
- [ ] Léa : s'inscrire → jauge 3/12 ; se désinscrire
- [ ] Jauge pleine : côté admin passer `max_participants` à 3 puis tenter une inscription avec Julien → 422 « complet »
- [ ] Événement **passé** (« Facturer sans stress ») : inscription impossible, statut « terminé »
- [ ] Accéder par URL à l'annonce résidents connecté en Léa → **404**
- [ ] Publier une annonce côté admin (§4.12) → **notification in-app** chez les membres de l'audience (cloche), pas chez l'auteur admin

### 3.10 Annuaire & plan des étages (PRD §3.7, C12.5)

Compte : **Claire** (resident), puis **Camille** (staff), puis **Thomas** (external → 403).

- [ ] Module absent pour Thomas et Léa ; URL directe → 403 / page refusée
- [ ] Annuaire liste : Claire, Inès, Julien, Sophie, Karim, Camille (« Équipe Ecoworking ») — **Marc absent** (opt-out) ; jamais d'email ni de téléphone affichés
- [ ] Fiche coworker : photo/avatar, poste, présentation, intérêts, LinkedIn/site (liens externes `rel=noopener`)
- [ ] Plan : switch étage 1 / étage 2 ; 29 + 20 bureaux ; sélecteur de date (aujourd'hui par défaut)
- [ ] États visuels aujourd'hui : Bureau 1 Claire présent, Bureau 2 « Coworker (souhaite rester discret) », Bureau 3 Inès présent (sauf vendredi → « absent »), Bureau 29 « staff », Bureau 30 Sophie, Bureau 31 Karim, bureaux libres « disponible », bureau occupé par Thomas (prochain jour ouvré) en « external présent »
- [ ] Naviguer à **vendredi prochain** → Inès « Bureau de Inès (absent) » ; semaine prochaine → absente tous les jours (congés)
- [ ] Naviguer à un **week-end** → **aucun bandeau « jour non ouvré »** ; les bureaux attitrés restent **présents** (présence 7 j/7, ré-acté 2026-09-17 — seule une absence déclarée rend absent). Vérifier notamment Bureau 1 (Claire) présent le samedi, et Inès absente si le samedi tombe dans sa semaine de congés
- [ ] Clic sur **son propre bureau** (Claire → Bureau 1) → panneau enrichi + bouton « Gérer mes absences » → ouvre §3.3
- [ ] Clic bureau libre → « Bureau libre — pour réserver, contactez-nous »
- [ ] Le panneau de détail bureau s'ouvre en **Sheet** (panneau latéral, C14) : le focus part sur
      le titre du panneau à l'ouverture et **revient sur le bureau cliqué** à la fermeture (Échap
      ou bouton fermer) — jamais perdu sur `<body>`
- [ ] **Alternative texte** : tableau/liste de l'occupation synchronisée avec le plan (mêmes libellés, même date)
- [ ] **Clavier** : Tab parcourt les bureaux dans un ordre logique, Entrée ouvre le panneau, Échap ferme, focus visible sur le SVG
- [ ] Zoom navigateur 200 % : plan et liste restent utilisables

### 3.11 Notifications (PRD §3.8.4, C8.2)

- [ ] Cloche avec badge « non lues » (Claire a reçu : factures émises, annonces publiées, éventuellement retard)
- [ ] Panneau : liste, lu / non lu, clic → marque lu (+ navigation vers l'objet) ; « Tout marquer comme lu »
- [ ] Échap et clic extérieur ferment le panneau ; focus revient sur la cloche
- [ ] Nouvelle notif (publier une annonce côté admin) → badge mis à jour sans recharger (poll ≤ 60 s)
- [ ] Mailpit : mails « facture émise » reçus par Claire (billing) et Sophie ; **Marc n'a reçu aucun mail facture**

### 3.12 Transverses portail (PRD §3.8, §3.9, RGAA)

- [ ] URL inconnue `/nimportequoi` → page 404 avec lien accueil
- [ ] Couper le réseau (DevTools offline) → bandeau hors-ligne ; retour réseau → données rafraîchies
- [ ] Erreur 500 simulée (arrêter `pgsql` puis naviguer) → toast erreur + bouton réessayer, pas d'écran blanc ; relancer `pgsql`
- [ ] **Sidebar desktop** (≥ 768 px) : rétractable en mode icône (bouton de repli ou Ctrl/Cmd+B),
      état persisté après rechargement ; groupe « Administratif » visible seulement si au moins un
      module gardé est autorisé pour le rôle
- [ ] **Bottom nav mobile** (< 768 px, tester à 390 px) : 5 entrées fixes selon le rôle (Accueil,
      Réservations/Tickets, Présence, Actualités, Plus), bouton **« Plus »** ouvre un Sheet listant
      les modules restants + Mon profil + Déconnexion ; aucun scroll horizontal sur l'accueil, les
      réservations, les factures et l'annuaire à 390 px
- [ ] Zoom navigateur 200 % (ou fenêtre réduite à ~640 px de large) : pas de scroll horizontal,
      lien d'évitement et bottom nav toujours utilisables
- [ ] Navigation **100 % clavier** sur un parcours complet (login → résa → annulation) : focus toujours visible, ordre logique, modales piègent le focus et se ferment à Échap
- [ ] Lecteur d'écran (NVDA) 10 min : titres de page annoncés au changement de route, erreurs de formulaire annoncées, cloche et badge lisibles
- [ ] **Thème sombre partout** : contrastes AA vérifiés sur toutes les pages authentifiées (pas
      seulement dashboard/résa/plan) — profil (4 onglets), factures, annuaire, documents, tickets,
      présence, notifications, pages publiques (mentions légales, CGU, accessibilité) ; liens en
      `text-link` (brand-700 clair / brand-300 sombre), jamais `text-primary` ni `text-destructive`
      brut sur une carte sombre
- [ ] `prefers-reduced-motion` activé (OS) → pas d'animations (Sheet, Dialog, Popover s'ouvrent
      sans transition perceptible)
- [ ] Lighthouse a11y ≥ 95 sur dashboard, résa (hors grille agenda, exclusion D4 assumée),
      factures, profil, annuaire — en clair **et** en sombre

> ✅ **Mesuré 2026-09-16 (U5)** : Lighthouse a11y (dans le conteneur Sail, contre le serveur e2e,
> compte `membre.e2e`, procédure ci-dessous) — **100/100** sur `/login`, `/` (accueil), `/invoices`,
> `/profile`, `/directory`, en clair **et** en sombre (thème forcé via `PATCH /api/profile`). Seul
> signal récurrent (toutes pages, poids **0** dans le score Lighthouse, sans effet dessus) :
> `label-content-name-mismatch` sur les onglets abrégés de la bottom nav (« Résas » visible,
> « Réservations » dans l'`aria-label` — WCAG 2.5.3, règle axe **expérimentale** donc hors du
> périmètre `wcag2a/wcag2aa/wcag21a/wcag21aa` strict audité par la suite e2e, cf.
> `e2e/support/fixtures.ts`). Existant depuis U2, non introduit en U5, non corrigé ici (changer le
> texte visible ou l'aria-label des tuiles nav est un choix de contenu, pas une finition
> mécanique) — noté dans `docs/todo_guillaume.md`.
>
> **Procédure rejouable** (pages authentifiées, dans le conteneur Sail) :
> ```bash
> # 1. Serveur e2e (build + migrate:fresh --seed sur la base `e2e`)
> docker compose exec -u sail -w /var/www/html/portal-spa laravel.test bash e2e/serve.sh &
> # 2. Session par API (Sanctum) — cf. e2e/support/auth.ts pour le détail CSRF/cookie
> #    curl .../sanctum/csrf-cookie puis POST /login avec X-XSRF-TOKEN, cookies dans un jar
> # 3. Thème sombre : PATCH /api/profile {"theme":"dark"} avec la même session (remettre "light" ensuite)
> # 4. Lighthouse, cookies de session en Cookie header (fichier JSON via --extra-headers)
> docker compose exec -u sail -w /var/www/html/portal-spa \
>   -e CHROME_PATH=/var/www/html/.playwright-browsers/chromium-*/chrome-linux64/chrome \
>   laravel.test npx --yes lighthouse "http://127.0.0.1:8091/invoices" \
>   --chrome-flags="--headless --no-sandbox" --only-categories=accessibility \
>   --extra-headers=/tmp/extra-headers.json --disable-storage-reset --output=json \
>   --output-path=/tmp/lh-invoices.json --quiet
> ```
> Pages publiques (`/login`, `/mentions-legales`, `/cgu`, `/accessibilite`) : aucune session
> nécessaire, lancer directement `lighthouse http://127.0.0.1:8091/<page>`.
- [ ] Aucune donnée d'un autre membre dans les réponses réseau (onglet Network : `/api/user`, `/api/profile`, `/api/invoices` ne contiennent que le périmètre du compte, jamais d'IBAN, de token, de hash)

---

## 4. Back-office admin

Compte : `admin@ecoworking.fr`.

### 4.1 Authentification admin (PRD §3.2 admin, C2.1, C2.2, C3.1)

- [ ] Login mot de passe → **configuration 2FA obligatoire** au premier accès (QR + codes de récupération) → dashboard
- [ ] Reconnexion → challenge TOTP ; code faux refusé ; code de récupération accepté une fois
- [ ] Bouton « Se connecter avec Google » présent ; en dev sans creds → erreur propre (le test live Google reste **prod-only**, cf. `todo_guillaume.md`)
- [ ] Connexion avec un compte **membre** (Claire) → refusée (`canAccessPanel`)
- [ ] Déconnexion → `/login` ; `last_login_at` mis à jour sur la fiche User (si exposé)
- [ ] `/pulse` accessible admin, 403 en membre / anonyme

### 4.2 Dashboard (PRD §4.1, C12.6)

- [ ] KPIs : membres actifs, abonnements actifs, factures en retard (nb + montant = facture M-1 Studio Verger), CA du mois vs N-1, taux d'occupation salles
- [ ] Widget **factures en retard** : Studio Verger M-1 listée, lien vers la facture
- [ ] Widget **abonnements se terminant sous 30 j** : vide (ou tester en posant une `ends_at` proche)
- [ ] Widget **documents non validés** : charte v2.0 avec % de validation
- [ ] Widget **activité récente** : émissions de factures, anonymisation de Paul… lien « Voir tout l'audit log »
- [ ] Widgets **Aujourd'hui** : résas salles du jour, occupations bureau du jour, nouveaux membres de la semaine (Léa)
- [ ] Aucune requête N+1 visible (Pulse → requêtes lentes vide après chargement)

### 4.3 Membres — Users & MemberProfiles (PRD §4.2, C3.2, C12.7, C12.9)

- [ ] Liste Users : recherche nom/email, filtres rôle et statut, colonnes rôles
- [ ] **Créer un membre** : nom, prénom, email, mot de passe, rôle `resident` → OK ; email déjà utilisé → erreur d'unicité
- [ ] **XOR des rôles d'usage** : cocher `resident` + `external` → refusé ; `resident` + `billing_contact` → accepté
- [ ] Modifier l'**email** de Claire → OK (seul flux autorisé, décision D) → Claire se reconnecte avec le nouvel email → remettre l'ancien
- [ ] Tenter d'éditer l'email depuis le portail (forger `PATCH /api/profile` avec `email`) → ignoré silencieusement
- [ ] Profil membre (RelationManager / MemberProfileResource) : entreprise, statut actif/pause/parti, date d'arrivée, **bureau attitré** (sélecteur), notes admin non visibles portail
- [ ] Attribuer le Bureau 10 à Julien → plan portail : Bureau 10 devient attitré à Julien ; retirer → redevient libre
- [ ] **Anonymiser** Léa (créer d'abord un membre jetable pour ne pas casser la démo) : confirmation forte → nom/email remplacés, connexion impossible, factures intactes, entrée audit `anonymized` **sans PII** ; action grisée si déjà anonymisé ; impossible sur soi-même
- [ ] Paul (anonymisé) : visible via filtre « supprimés », données `deleted-…@ecoworking.invalid`
- [ ] Réinitialiser le mot de passe d'un membre → mail Mailpit

### 4.4 Entités — Companies & Contacts (PRD §4.3, §4.4)

- [ ] Liste : badge type entreprise / particulier, nom calculé (raison sociale ou prénom nom), ville, filtres
- [ ] Créer une **entreprise** : SIRET 14 chiffres obligatoire, forme juridique, TVA ; SIRET à 13 chiffres → erreur
- [ ] Créer un **particulier** : champs SIRET/TVA masqués, nom + prénom obligatoires
- [ ] Section SEPA : **4 derniers chiffres seulement**, référence + date mandat, upload PDF mandat ; aucun champ IBAN complet nulle part
- [ ] **Remise négociée** Atelier Lumière = 10 % ; passer à 15 % → générer une facture (§4.10) → la remise appliquée est 15 % ; remettre 10 %
- [ ] Onglets / RelationManagers : membres rattachés, contacts, abonnements, factures, documents
- [ ] Contacts : créer un contact facturation lié à un user portail → ce user reçoit les mails facture de l'entité ; contact principal unique

### 4.5 Catalogue — Offers, Subscriptions, Purchases (PRD §4.5, C3.3)

- [ ] Offres : 8 SKU aux prix PRD ; formulaire conditionnel selon type (abonnement / one-shot / pack) ; désactiver une offre → n'apparaît plus à la souscription, abonnements existants intacts ; réactiver
- [ ] **Modifier le prix** `resident_desk` (328,50 → 340,00) → générer la facturation du mois (§4.10) → nouveau prix appliqué à **tous** (pas de prix figé) → remettre 328,50 et supprimer les brouillons générés
- [ ] Abonnements : liste avec souscripteur (user **ou** entité pour la domiciliation), billable, statut ; **aucun champ prix** (§3.6)
- [ ] Créer un abonnement `resident_desk` pour Julien → OK ; second abonnement actif pour le même membre → refusé (1 actif max) ; domiciliation pour Studio Verger → OK ; seconde domiciliation même entité → refusée
- [ ] Mettre en pause / reprendre / résilier (date de fin + raison) : statuts cohérents, Karim déjà en pause
- [ ] **Achats** : créer un achat « Pack 10 tickets bureau » pour Léa → prix pré-rempli depuis l'offre mais **éditable** (snapshot) → **10 tickets générés** visibles dans Tickets ; `created_by` = admin
- [ ] Achat depuis une offre sans `ticket_type` (abonnement) → refusé avec message métier

### 4.6 Espaces — Resources (PRD §4.6, C3.4)

- [ ] Liste : 49 bureaux, 3 salles, 1 salle événementielle ; filtres type / attribution / étage ; recherche
- [ ] Bureau : `assignment` attitré résident / staff / libre, étage, `svg_desk_id` ; passer Bureau 12 **hors service** → plan portail « hors service » → rétablir
- [ ] Salle : capacité, tarif external HT (71,00), horaires d'ouverture (KeyValue), features
- [ ] Salle événementielle : `requires_admin` coché ; tenter une résa portail dessus (forger `POST /api/bookings`) → 403/422
- [ ] Désactiver une salle → disparaît du calendrier portail → réactiver

### 4.7 Réservations & occupations — Bookings, DeskOccupations, Tickets (PRD §4.7, §4.8, C12.2)

- [ ] Bookings : liste filtrable (salle, statut, membre, période) ; **salles uniquement** (jamais de bureau)
- [ ] Créer une résa **au nom de** Marc, salle 2, demain 9 h–10 h → OK, `created_by` = admin, notification in-app à Marc
- [ ] Créer un **conflit** (même créneau que la résa de Claire) → **erreur de formulaire** claire, rien créé
- [ ] Résa **interne** (ménage) : pas de billable, prix 0, pas de notif
- [ ] Résa **salle événementielle** → OK (seul canal possible)
- [ ] Résa au nom de Thomas avec **consommation d'un ticket salle** → ticket passe « utilisé » ; **Annuler** la résa → ticket restitué ; **supprimer** la résa → ticket restitué (observer)
- [ ] Modifier le créneau d'une résa vers un créneau pris → refusé
- [ ] Bulk delete **absent** sur Bookings
- [ ] DeskOccupations : liste (date, bureau, qui, source, statut), filtres étage / source ; l'occupation de Thomas y est
- [ ] **Tickets** : liste **lecture seule** (pas d'édition), filtres membre / type / statut ; soldes de Thomas conformes à §3.6
- [ ] **Crédit manuel** : créditer 2 tickets bureau à Léa avec raison « geste commercial » → `credited_by` + `credit_reason` renseignés, visibles côté portail Léa
- [ ] **Consommation manuelle bureau** : Léa, demain matin, choisir un bureau libre → occupation créée, ticket utilisé ; même bureau/période déjà pris → 422
- [ ] **Consommation manuelle salle** : Thomas, salle 3, après-demain après-midi → booking créé lié au ticket
- [ ] Consommation manuelle un **samedi** → refusée (jours ouvrés, finding #17)
- [ ] Absences (via Occupation du jour ou DeskAbsences) : l'admin déclare une absence pour Sophie → visible côté portail Sophie et sur le plan

### 4.8 Occupation du jour (PRD §4.8.4, C12.6)

- [ ] Page accessible depuis le dashboard ; date = aujourd'hui, sélecteur
- [ ] Étage 1 / étage 2 : bureaux attitrés avec résident + statut présent / absent (Inès absente le vendredi), bureaux libres avec external ou « Disponible »
- [ ] **Capacité libre restante** cohérente avec le plan portail
- [ ] Résas salles du jour chronologiques avec ticket consommé pour Thomas
- [ ] Naviguer au prochain jour ouvré → occupation de Thomas listée ; Sophie absente l'après-midi
- [ ] Naviguer à un **week-end** → encart « bureaux nomades non réservables » (la mention « résidents pas attendus » a disparu) ; les résidents restent **présents** sauf absence déclarée
- [ ] Page réservée admin (403 en membre)

### 4.9 Documents — InternalDocuments, AdministrativeDocuments (PRD §4.10, C3.6, C12.4)

- [ ] Documents internes : liste avec version, audience, **nb validations / nb membres concernés**
- [ ] Créer « Règlement parking v1.0 » audience `all` avec PDF → visible « à valider » chez tous les membres → notification in-app (si câblée)
- [ ] Changer la **version** de la charte (2.0 → 2.1) → validations précédentes conservées mais tous repassent « à valider »
- [ ] Document **inactif** → disparaît du portail sans perte d'historique
- [ ] Documents administratifs : upload PDF rattaché à Nova Conseil → visible… par personne (Léa n'est pas billing_contact) ; donner `billing_contact` à Léa → visible
- [ ] Téléchargement PDF depuis l'admin OK ; fichier non-PDF refusé à l'upload

### 4.10 Facturation — Invoices & Payments (PRD §4.9, §5.1, C3.5, C6)

> ⚠️ Zone sensible (§3.6). Ne rien « corriger » à la main en base ; noter et me remonter.

- [ ] Liste : numéro, billable, dates, statut, TTC, payé, dû ; filtres statut / période / en retard ; recherche numéro
- [ ] **Brouillons du mois courant** présents (Atelier Lumière, Studio Verger), **sans numéro**
- [ ] Ouvrir un brouillon : lignes éditables (Repeater), totaux HT/TVA/TTC **recalculés côté serveur** ; saisir 3 × 100 HT à 20 % → 300 / 60 / 360
- [ ] **Supprimer** un brouillon → autorisé (aucun numéro consommé) ; l'entité reste refacturable → relancer `invoices:generate-monthly` → brouillon recréé
- [ ] **Émettre** le brouillon Studio Verger : numéro = **suivant sans trou** de la séquence `EW-AAAA-NNNNN`, statut « envoyée », échéance +14 j, adresse snapshotée, totaux figés, **PDF généré** (aperçu + téléchargement), mail Mailpit à Sophie (contact facturation), notif in-app
- [ ] Facture émise : montants, lignes, numéro, billable **non modifiables** ; seules notes éditables
- [ ] Facture émise : **aucune action Supprimer** ; forcer l'URL de suppression → 403
- [ ] Aucun **bulk delete** sur Invoices
- [ ] **Annuler + avoir** sur la facture émise : statut « annulée », avoir créé (numéro suivant, montants négatifs, lien réciproque), **PDF de l'avoir** généré ; ré-annuler → impossible ; l'avoir n'envoie pas de mail « facture émise »
- [ ] Émettre un brouillon **sans ligne** → refusé
- [ ] Course : émettre le **même** brouillon depuis 2 onglets → un seul numéro consommé, second onglet en erreur
- [ ] **Paiement** : « Enregistrer un paiement » sur M-1 Studio Verger, montant pré-rempli = solde dû, méthode virement → statut **payée**, `amount_paid` = TTC ; paiement partiel → « partielle » ; montant > solde → refusé ; supprimer un paiement → statut recalculé
- [ ] Paiement daté **avant** l'émission ou dans le futur → refusé (bornes PaymentForm)
- [ ] Payments : liste filtrable méthode / période, `created_by` renseigné
- [ ] Ventilation TVA par taux correcte sur le PDF (créer une ligne à 10 % pour vérifier deux taux)
- [ ] Facture d'un **particulier** (Thomas) : sans SIRET/TVA intracom, nom prénom + adresse
- [ ] **Audit log** : émission, annulation, paiement tracés avec auteur ; jamais d'IBAN ni de PII inutile dans le diff

### 4.11 Communication — Announcements (PRD §4.11, C3.6, C12.3)

- [ ] Liste : type, statut, date de publication, date événement, **nb inscrits** (apéro = 3)
- [ ] Créer une annonce `info` en brouillon → invisible portail ; **publier** → visible + notification in-app à l'audience (pas à l'auteur)
- [ ] Événement : bloc conditionnel (dates, lieu, jauge, inscription requise) ; date fin < début → erreur
- [ ] Visibilité `residents` → Léa ne la voit pas ; `all` → tous
- [ ] Suivi des inscriptions : liste des inscrits à l'apéro, statuts inscrit / annulé / présent
- [ ] Archiver → disparaît du portail, reste en base

### 4.12 Audit log & rôles (PRD §4.13, §4.14, C12.8b)

- [ ] ActivityResource **lecture seule** : filtres modèle / action / auteur / période, recherche dans le diff
- [ ] Vérifier la présence : `anonymized` (Paul, sans PII), émissions de factures, annulations de résa, changements d'email
- [ ] **Aucune PII inutile** dans les diffs : pas de mot de passe, pas de tokens, pas d'IBAN
- [ ] Matrice rôles → permissions : reflète la DB (Permission enum) ; lecture seule

### 4.13 Transverses admin

- [ ] Toutes les pages Filament : titres FR, enums traduits (`HasLabel`), badges colorés cohérents
- [ ] Tableaux : pagination, tri, recherche fonctionnent sur chaque Resource
- [ ] Aucune Resource ne propose de bulk delete sur Invoices, Bookings, Users
- [ ] Erreur métier (conflit, ticket manquant) affichée en **erreur de formulaire**, jamais en page 500
- [ ] Navigation clavier basique (Filament) : pas de régression sur les pages custom (Occupation du jour, Matrice)

---

## 5. Isolation des données (CLAUDE.md §3.1 — bloquant)

À faire connecté en **Claire** avec DevTools ouverts, en forgeant les requêtes (ou via l'onglet Réseau + « Rejouer »).

- [ ] `GET /api/invoices` : jamais une facture Studio Verger ou Thomas
- [ ] `GET /api/invoices/{id-studio}/pdf` → 403
- [ ] `DELETE /api/bookings/{id-de-marc}` → 403, résa intacte
- [ ] `DELETE /api/desk-occupations/{id-de-thomas}` → 403
- [ ] `DELETE /api/absences/{id-de-ines}` → 403
- [ ] `GET /api/documents/administrative/{id-studio}/pdf` → 403
- [ ] `POST /api/announcements/{id-residents}/registration` en Léa → 404
- [ ] `GET /api/directory` en Thomas → 403
- [ ] `PATCH /api/profile` avec `email`, `first_name`, `roles` → champs ignorés, 200
- [ ] `GET /api/user` : pas de `password`, `two_factor_secret`, `calendar_token`, `remember_token`
- [ ] Admin : `GET /api/*` sur portail.ecoworking.test avec la session admin → 401 (sessions isolées par sous-domaine)
- [ ] Portail : `admin.ecoworking.test/...` avec la session membre → refusé
- [ ] Rate limit API : > 60 requêtes/min → 429

---

## 6. Emails (Mailpit) — récapitulatif attendu

| Déclencheur                                   | Destinataire(s)                                             | Attendu                                                                                                               |
| --------------------------------------------- | ----------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------- |
| Émission facture (§4.10)                      | contacts facturation de l'entité (Claire / Sophie / Thomas) | sujet + numéro + PDF joint ou lien, pas de mail à Marc                                                                |
| Seed de démo                                  | Claire, Sophie, Thomas                                      | mails « facture émise » (M-3, M-2, M-1, tickets) + « en retard » (Sophie ×2) déjà présents dans Mailpit après le seed |
| Facture en retard (`invoices:update-overdue`) | idem                                                        | 1 seul mail par facture (`overdue_notified_at`) même si la commande est relancée                                      |
| Magic link                                    | le membre                                                   | lien signé, expiration 15 min mentionnée                                                                              |
| Mot de passe oublié                           | le membre                                                   | lien reset 1 h                                                                                                        |
| Reset par l'admin                             | le membre                                                   | lien reset                                                                                                            |
| Absence déclarée                              | **aucun mail** (in-app admin uniquement, Q25)               | —                                                                                                                     |
| Annonce publiée                               | **aucun mail** (in-app)                                     | —                                                                                                                     |

- [ ] Tous les mails : FR, expéditeur `MAIL_FROM_ADDRESS`, aucune donnée d'un autre membre, liens pointant vers `portail.ecoworking.test`
- [ ] Préférence `notify_email` désactivée → aucun mail pour cet événement, in-app conservé

---

## 7. Jobs, commandes & scheduler (C6.5, C6.6, C10)

```bash
sail artisan invoices:generate-monthly                  # mois courant : idempotent (rien de nouveau)
sail artisan invoices:generate-monthly --month=YYYY-MM  # mois suivant : brouillons pour chaque entité active
sail artisan invoices:update-overdue                    # bascule échéances dépassées, notif une seule fois
sail artisan schedule:list                              # 2 crons : mensuel 1er 06:00, quotidien 07:00
sail artisan queue:work --stop-when-empty               # vider la queue database si QUEUE_CONNECTION=database
```

- [ ] `generate-monthly` relancé 2 fois → aucun doublon (idempotence entité × période)
- [ ] Abonnement démarré le 15 → ligne de **prorata** séparée (jours inclus, arrondi ROUND_HALF_UP)
- [ ] Abonnement en **pause** (Karim) → non facturé ; abonnement **terminé** (Paul) → non facturé après sa fin
- [ ] Domiciliation facturée à l'entité, regroupée sur la facture d'entité
- [ ] `update-overdue` relancé → pas de second mail
- [ ] Ping Healthchecks : sans URL configurée, aucun appel réseau ni erreur
- [ ] `/up` → 200

---

## 8. Ce que la recette NE couvre PAS (hors périmètre ou prod-only)

- Login **Google** admin : test live en prod uniquement (`todo_guillaume.md` C2.2)
- Envoi **Brevo** : prod uniquement (dev = Mailpit)
- Push **Google Calendar** (C9.1) : V1.5
- Sentry / Better Stack / Healthchecks : comptes créés, no-op tant que les DSN/URLs sont vides en dev
- PWA (D6), import Cosoft (D4), Factur-X (E1) : V1.5 / V2
- Paiement en ligne : V2

---

## 9. Journal des anomalies

➡️ Déplacé dans **[`recette_journal-des-anomalies.md`](./recette_journal-des-anomalies.md)** (fichier dédié à la saisie,
éditable indépendamment de cette checklist). Ce fichier-ci ne contient plus que les cases à cocher.

---

*Créée le 2026-09-12. Jeu de données : `database/seeders/DemoSeeder.php` (test de non-régression `tests/Feature/Database/DemoSeederTest.php`).*
