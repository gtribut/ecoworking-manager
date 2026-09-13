# 08 — Écarts PRD §3 (portail membre) ↔ implémentation

> Passe globale du **2026-09-13** à la demande de Guillaume (recette C13) : comparer chaque
> exigence du PRD §3 (portail) + §2.5 (matrice des permissions côté portail) avec le code réel
> (`portal-spa/src`, `app/Http/Controllers/Api`, Fortify). **Aucune correction dans cette passe** ;
> seules les anomalies R-01 → R-06 déjà remontées ont été corrigées le même jour (commits
> `5dc146a`, `b1a2959`, `18f622f`, `e44a036`) et sont marquées ✅ *corrigé 13/09* ci-dessous.
>
> Méthode : 4 analyses parallèles (auth/accueil/transverses, profil/absences, réservations,
> factures/annuaire), chaque puce du PRD vérifiée par lecture + grep. Les items **conformes ne
> sont pas listés** ici — seuls les écarts. Numéros de section = `docs/PRD.md`.

Légende : ❌ absent · ⚠️ partiel · 🔀 fait autrement que le PRD · 🔮 le PRD le marque lui-même hors MVP / 🟡 « à valider » · ✅ *corrigé 13/09*

---

## Synthèse — les écarts qui changent l'usage

Par ordre d'impact utilisateur, après corrections du 13/09 :

1. **Calendrier des salles (§3.5.2)** : ce n'est pas un calendrier. Une salle à la fois, un seul jour, liste de créneaux d'1 h, occupants anonymes (ni nom, ni entité, ni libellé, pas de distinction de ses propres résas), pas de navigation semaine, **salle événementielle invisible**. Le PRD en fait l'outil de coordination d'équipe « temps réel ».
2. **Réservation resident/additional (§3.5.3)** : créneaux figés à 1 h entre 8 h et 20 h. Pas de journée / demi-journée / créneau personnalisé, pas de résa nocturne alors que le back accepte 24/7. **Aucune modification** de résa (ni API ni UI) : annuler + recréer.
3. **Navigation non filtrée par rôle (§2.5)** : « Factures » visible pour tous (page vide trompeuse pour un resident sans rôle billing), « Présence » proposée aux `additional` (qui n'ont pas de bureau), « Réservations/Actualités » pour un `billing_contact` pur.
4. **Absences (§3.4.6)** : récurrence hebdo **non bornable** (date de fin désactivée), pas d'édition, pas de champ note, liste sans filtre « à venir », bureau attitré et mini-plan non affichés.
5. **Factures (§3.6.2)** : aucun tri sélectionnable, aucun filtre (mois, année, statut), aucune recherche par numéro. Bloc « Mon entreprise » : mode de paiement et IBAN-4 absents, adresse tronquée, entité déduite du profil et non des entités facturables.
6. **Annuaire/plan (§3.7)** : aucun tooltip au survol (identité seulement au clic ou via aria-label), photos jamais rendues, staff opt-in sans mention « Équipe Ecoworking ».
7. **External (§3.5.9, §3.5.6)** : impossible de **voir ou annuler** ses bureaux nomades réservés (route DELETE et hook existent, aucune page) ; pas de plan SVG filtré ; « Mes tickets » sans détail par ticket ; messages « 0 ticket » techniques et sans mailto.
8. **Mot de passe (§3.4.2)** : aucun changement de mot de passe depuis le portail (back prêt : `PUT /user/password`).
9. **Notifications (§3.8.4)** : deux des cinq types prévus n'existent pas — « nouveau document à valider » (pourtant critique, doublé email) et « résa créée/modifiée/annulée par l'admin ». Pas de rétention 90 j.
10. **Chrome global (§3.1, §3.9)** : pas de footer (mentions légales, CGU, contact), pas de déclaration d'accessibilité `/accessibilite`, pas de switch thème ni de menu profil dans le header, pas de toasts, pas de Skeleton, pas de bandeau hors-ligne, pas de bouton « Réessayer ».

Points de conformité / sécurité à noter : audit log incomplet (`MemberProfile` et `DeskAbsence` non `Auditable` → opt-in newsletter, visibilité annuaire, suppressions d'absence non tracées ; aucun événement au changement de mot de passe) ; aucune vérification d'**abonnement actif** à la réservation (§3.5.3) ; `additional` peut atteindre l'API absences et reçoit une erreur métier au lieu d'un module masqué.

---

## §2.5 — Matrice des permissions (côté SPA)

| Exigence PRD | Statut | Constat |
|---|---|---|
| Calendrier des salles : billing_contact pur ❌ | ⚠️ | Entrée « Réservations » statique pour tous — `Layout.tsx:43` ; aucune lecture de `view-bookings-calendar` |
| Marquer son bureau vacant : resident + staff, **additional ❌** | 🔀 | `isResident = has('create-own-booking')` (`usePermissions.ts:25`) or `additional` a aussi cette permission → nav « Présence » + tuile visibles pour lui ; `PresencePage` laisse passer jusqu'à l'API (erreur métier « pas de bureau ») |
| Factures : billing_contact uniquement, module masqué sinon | ⚠️ | Nav « Factures » statique (`Layout.tsx:51`), route sans garde, `InvoicesPage` sans permission → « Aucune facture pour le moment » pour un resident (trompeur). `view-billing-section` inutilisée côté SPA. Le bloc dashboard est, lui, gaté (✅ 13/09) |
| S'inscrire aux events : billing_contact pur ❌ | ⚠️ | Entrée « Actualités » statique pour tous |
| Voir son entité juridique (lecture seule) | ⚠️ | Pas de module dédié : bloc dans le profil uniquement (cf. §3.6.4) |

## §1.4 / §3.1 — Identité, principes UI, RGAA

| Exigence PRD | Statut | Constat |
|---|---|---|
| Palette violet `#481944` + vert `#6AB024` en échelles | ⚠️ | `styles.css:10-17` : une seule échelle `brand` verte (5 paliers), aucun violet |
| Typographie Inter | ❌ | Aucun `@font-face` / Google Fonts |
| Switcher thème ☀️/🌙 dans le header | 🔀 | Choix via `<Select>` dans le profil ; rien dans le header |
| Persistance thème en localStorage | 🔀 | Persisté en base via `/api/profile` (plus robuste) ; flash possible avant chargement de `/api/user` |
| Nav : sidebar desktop + bottom nav mobile | 🔀 | Nav horizontale dans le header ; hamburger mobile (`Layout.tsx:77-135`) |
| Header : menu profil (logout, thème) | 🔀 | Nom + bouton « Déconnexion » direct, pas de menu |
| Footer : mentions légales, CGU, contact | ❌ | Aucun `<footer>` |
| Toasts Sonner | ❌ | Aucune lib ; feedback par `<Alert>` inline persistante |
| Skeletons shadcn | 🔀 | `Spinner` par bloc |
| PWA installable | 🔮 | Reportée V1.5 (D6) |
| Raccourcis clavier actions critiques | ❌ | Aucun (seul Échap sur la cloche) |
| Zoom 200 % / tailles relatives | ⚠️ | Un `text-[10px]` fixe (`NotificationBell.tsx:79`) |
| Erreurs de champ reliées au SR | ⚠️ | Messages sous les champs sans `aria-describedby` / `aria-errormessage` (erreur globale OK via `role=alert`) |
| Lint a11y **strict** | ⚠️ | Biome `a11y: recommended`, pas strict (`biome.json:21-23`) |
| Déclaration d'accessibilité `/accessibilite` | ❌ | Aucune route |
| h1 accueil avec emoji 👋 lu par le SR | ✅ *corrigé 13/09* | Retiré (`e44a036`) |

## §3.2 — Authentification

| Exigence PRD | Statut | Constat |
|---|---|---|
| Lien « Mot de passe oublié ? » + reset par email | ✅ *corrigé 13/09* | R-03 (`b1a2959`) : étape login + page `/reset-password/:token`, URL vers le portail, anti-énumération back |
| 2FA TOTP optionnelle membre : activation | ✅ *corrigé 13/09* | R-04 (`18f622f`) : section profil (QR, code, codes de récupération, désactivation) |
| Email d'accueil avec lien de définition initiale du mot de passe | ❌ | L'admin saisit le mot de passe dans `UserForm.php:43-51` ; aucun mail d'accueil (`app/Mail/` = magic link seul) |
| Déconnexion « dans le menu profil » | 🔀 | Bouton direct dans le header |
| Message 429 en anglais | ✅ *corrigé 13/09* | R-01 (`5dc146a`) |

## §3.3 — Accueil

| Exigence PRD | Statut | Constat |
|---|---|---|
| Bloc « Mes dernières factures » (3, billing only) | ✅ *corrigé 13/09* | R-05 (`e44a036`) |
| Bloc « Mes prochaines réservations » (3) | ✅ *corrigé 13/09* | R-05 + `GET /api/bookings?upcoming=1` |
| Bouton « Nous contacter » (mailto sujet PRD) | ✅ *corrigé 13/09* | R-05 |
| Layout desktop 2 colonnes | ✅ *corrigé 13/09* | R-05 |
| Bloc documents masqué si rien à valider | ✅ *corrigé 13/09* | R-06 |
| 🟡 Sous-titre contextuel (« X documents en attente, Y résas cette semaine ») | 🔮 ❌ | Absent |
| Statut « en attente » (au lieu de « Émise ») | ✅ *corrigé 13/09* | Libellé `sent` → « En attente » |

## §3.4 — Profil

| Exigence PRD | Statut | Constat |
|---|---|---|
| Mot de passe : formulaire ancien + nouveau + confirmation | ❌ (front) | Back prêt (`Features::updatePasswords`, `current_password` exigé) ; aucune UI, aucun appel, aucun test Pest sur `PUT /user/password` |
| Photo de profil : upload, recadrage carré, 2 Mo, JPG/PNG/WebP | ❌ | `photo_path` exposé en lecture mais jamais affiché ni uploadable côté portail ; admin seul via Filament (sans limite ni redimensionnement) |
| Présentation : éditeur **markdown** (max 500) | ⚠️ | `<Textarea>` brut, aucun rendu markdown (annuaire affiche le texte plat) |
| 🟡 Avatar initiales si pas de photo | ⚠️ | Présent dans l'annuaire, absent de la page profil |
| 🟡 Cellar + Intervention Image (80/200/400) | 🔮 ❌ | Absent |
| Adresse entreprise complète (rue, CP, ville, **pays**) | ⚠️ | `line2` et `country` exposés mais non rendus (`ProfilePage.tsx` CompanyBlock) |
| 🟡 « Mon entreprise » vs « Mes données de facturation » selon entité | 🔮 ❌ | Titre codé en dur ; `entity_type` exposé mais inutilisé |
| Feedback par **toast** | 🔀 | `<Alert>` inline |
| Texte d'aide email « procédure dédiée » | ⚠️ | Vague : dire « contactez Ecoworking » (décision D) |
| Audit : mot de passe | ❌ | Aucun événement (valeur exclue à raison, mais pas d'entrée « password changed ») |
| Audit : opt-in newsletter, visibilité annuaire | ❌ | `MemberProfile` n'est pas `Auditable` |
| Commentaire `config/fortify.php:153` « email = flux dédié hors MVP » | ⚠️ | Périmé (décision D) — cosmétique |

### §3.4.6 — Mon bureau & mes absences

| Exigence PRD | Statut | Constat |
|---|---|---|
| Accès resident/staff uniquement | ⚠️ | Visible aux `additional` (cf. §2.5) ; `DeskAbsencePolicy::create` → `true`, garde réelle = exception métier dans `PresenceService` |
| Affichage du bureau attitré (numéro, étage) | ❌ | `PresencePage` ne référence pas `desk` ; `/api/presence` ne le renvoie pas |
| Mini-aperçu de la position sur le plan | ❌ | Absent |
| Liste des absences **à venir** | ⚠️ | Toutes les absences renvoyées sans filtre de date (`PresenceController.php:128-130`) |
| Bouton « Marquer une absence » | 🔀 | Formulaire toujours affiché inline |
| Récurrence sur une plage : début + **fin** + jour | ⚠️ | `date_end` désactivée et non envoyée en mode weekly (`PresencePage.tsx:185,119-123`) → récurrence sans fin ; le back accepte pourtant une fin |
| Récurrence `daily` | ⚠️ | Enum `none|weekly` seulement (couvert par le mode « plage ») |
| Note optionnelle | ❌ | Aucun champ de saisie, `notes` absent des règles de `StoreAbsenceRequest` (colonne et service prêts) |
| Édition d'une absence | ❌ | Aucune route PATCH/PUT ; `DeskAbsencePolicy::update` inutilisée |
| Suppression possible jusqu'au début | 🔀 | Suppression permise à tout moment |
| Suppression rétroactive : warning + audit | ❌ | Confirmation générique ; `DeskAbsence` non `Auditable` |
| Admin voit les absences (§4.8) | ⚠️ | Vue dérivée « Occupation du jour » seulement ; aucune Resource Filament `desk_absences` (ni liste, ni édition) |
| Lien depuis l'accueil **si absence imminente** | ⚠️ | Tuile « Ma présence » toujours affichée, sans logique |

## §3.5 — Réservation de ressources

### §3.5.1 / §3.5.2 / §3.5.4 — Calendrier

| Exigence PRD | Statut | Constat |
|---|---|---|
| Salle événementielle visible en lecture seule | ❌ | `RoomController::index` filtre `meeting_room` → jamais renvoyée |
| Clic salle event → « contactez-nous » + mailto | 🔮 ❌ | Aucun `mailto:` dans `features/bookings` |
| 4 salles affichées simultanément | ❌ | `<Select>` mono-salle (`BookingForm.tsx:131-147`) ; dispo chargée par salle |
| Filtre multi-salles | ❌ | Sélection unique |
| Vue semaine par défaut | ❌ | Vue = un seul jour, liste de créneaux d'1 h |
| Vue jour (défaut mobile) | 🔀 | Seule vue existante, sous forme de liste, pas de grille |
| 🟡 Couleur par ressource | 🔮 ❌ | `ResourceResource` exclut la couleur |
| Mes résas mises en avant | ❌ | `busy[]` sans propriétaire → tout est « Occupé » |
| Hover/clic résa des autres = prénom + nom + entité + libellé (Q4) | ❌ | Rien d'exposé dans `busy` (`RoomController.php:63-66`) |
| Heures 24/24 resident/additional | ⚠️ | Back OK ; front `HOURS = 8..19` → 8 h–20 h seulement |
| 🟡 Toggle « voir 24 h » | 🔮 ❌ | Absent |
| Boutons semaine préc./suiv./aujourd'hui | ❌ | Aucune navigation temporelle du calendrier |
| Date picker vers une semaine | ⚠️ | `<input type=date>` vers un **jour** |

### §3.5.3 — Flow de réservation

| Exigence PRD | Statut | Constat |
|---|---|---|
| Clic sur un créneau → modal pré-remplie | 🔀 | Salle choisie avant, clic réserve immédiatement (pas de modal) |
| Toggle Journée / Matin / Après-midi / Créneau perso | ❌ | Durée figée à 1 h pour resident ; le back accepte `starts_at/ends_at` libres |
| Toast succès | ⚠️ | `<Alert>` inline |
| Conflit : message + **suggestion créneau proche** | ⚠️ | Message 409 OK, aucune suggestion |
| External sans ticket : message « contacter Ecoworking » | ⚠️ | Erreur serveur brute au clic : « …ticket « meeting_room_half_day »… » (valeur enum), pas de mailto ; solde salle non consulté avant |
| Droits : rôle **+ abonnement actif** | ⚠️ | Aucune vérification d'abonnement (`BookingPolicy`, `StoreBookingRequest`, services) |

### §3.5.5 / §3.5.7 — Modifier, supprimer, liste

| Exigence PRD | Statut | Constat |
|---|---|---|
| Modal « Modifier / Supprimer » sur sa résa | 🔀 | Bouton « Annuler » par ligne, pas de modal |
| **Modification** d'une résa | ❌ | Aucune route PATCH/PUT ; `BookingService::update` réservé back-office |
| Liste « Mes **prochaines** réservations » chronologique | 🔀 | Toutes les résas (passées, annulées) en ordre décroissant (`BookingController@index` par défaut) — le filtre `upcoming` ajouté le 13/09 ne sert que le dashboard |
| External : ticket consommé indiqué par résa | ⚠️ | Booléen « (payante) » seulement, pas d'identifiant de ticket |

### §3.5.6 — Mes tickets

| Exigence PRD | Statut | Constat |
|---|---|---|
| Statut par ticket (dispo / utilisé / restitué) | ❌ | API renvoie `tickets[]`, la page n'affiche que les soldes |
| « Plus aucun ticket » → mailto / téléphone | ⚠️ | Texte pour bureau seulement, sans mailto ; rien pour salle |

### §3.5.8 — iCal

| Exigence PRD | Statut | Constat |
|---|---|---|
| 3e flux « Toutes les réservations Ecoworking » (Google public) | ❌ | Dépend du push Google C9.1 (V1.5) ; aucune URL configurable |

### §3.5.9 — External : bureau nomade

| Exigence PRD | Statut | Constat |
|---|---|---|
| Vue **plan SVG filtrée** places libres | 🔀 | Liste textuelle nom + étage ; `FloorPlanSvg` réservé à l'annuaire (interdit aux external) |
| Jours ouvrés : fériés | ⚠️ | Garde serveur OK ; `GET /desks/availability` ne filtre pas les fériés → dispo affichée puis 422 au clic |
| 0 dispo → « Contactez-nous » + mailto | ⚠️ | Alert sans invitation ni mailto |
| Voir / annuler ses bureaux réservés (restitution ticket) | ❌ | Pas de `GET /desk-occupations` ; `DELETE` + hook `useCancelDeskOccupation` existent mais **aucune page** ne les utilise ; `DeskOccupationPolicy::delete` sans délai |

## §3.6 — Administratif & facturation

| Exigence PRD | Statut | Constat |
|---|---|---|
| Module masqué de la nav sans rôle billing | ⚠️ | Cf. §2.5 (nav « Factures » statique) |
| Colonne libellé / nom de la facture | ❌ | Aucune colonne ni donnée (pas de label sur `invoices`) |
| Tri date / numéro / statut | ⚠️ | Défaut date desc OK ; aucun tri sélectionnable (`InvoiceController@index` ne lit que `page`) |
| Filtres mois, année, statut ; recherche numéro | ❌ | Aucun |
| 🟡 URL `/api/invoices/{id}/download` | 🔀 | `/pdf` (protégé auth + policy) |
| Documents administratifs en nom propre | ⚠️ | `company_id` obligatoire → impossible pour une facturation en nom propre (contrairement aux factures) |
| Bloc « Mon entreprise » **dans le module administratif** | 🔀 | Uniquement dans le profil (§3.4.3), rien dans factures/documents |
| Mode de paiement préféré | ❌ | En DB, absent de `CompanyResource` |
| 🟡 IBAN 4 derniers chiffres | ❌ | Exclu volontairement de `CompanyResource` |
| Adresse complète | ⚠️ | `line2`, `country` non rendus |
| Entité du billing_contact pur / multi-entités | ⚠️ | `ProfileController` prend `memberProfile->company` seulement, alors que factures/documents couvrent `linkedCompanyIds()` |

## §3.7 — Annuaire & plan des étages

| Exigence PRD | Statut | Constat |
|---|---|---|
| Photo sur le plan (résident présent) / photo grisée (absent) / photo dans la fiche | ❌ | `photo_path` circule mais n'est jamais rendu (initiales) ; pas d'upload côté portail |
| Couleur « staff vacant » dédiée | 🔀 | Même style `absent` que résident (libellé distingue) |
| **Tooltip au survol** (7 états) | ❌ | Aucun `<title>` / `title` ; identité via `aria-label` et au clic seulement |
| Mention « Équipe Ecoworking » pour le staff | ⚠️ | Seulement si staff **opt-out** ; staff opt-in = nom + entreprise sans mention ; aucune distinction dans la liste |
| 🟡 Bouton « Contacter » (mailto) | 🔮 | Exclu volontairement (aucun email exposé) |
| Statut du bureau « aujourd'hui » dans le panneau | 🔀 | Statut de la **date sélectionnée** |
| `id` = id DB du bureau / 🟡 `data-desk-id` | 🔀 | `id="desk-N"` + `data-desk="N"` mappés par `resources.svg_desk_id` (équivalent fonctionnel) |
| Statut supplémentaire `partial` (demi-journée) | 🔀 | Ajout hors PRD, une couleur unique quelle que soit la typologie |

## §3.8 — États transverses & notifications

| Exigence PRD | Statut | Constat |
|---|---|---|
| Suspense React / code-splitting | ❌ | Toutes les pages importées statiquement (chunk 560 kB) |
| Bandeau hors-ligne | ❌ | Aucun `navigator.onLine` |
| 500 : toast + bouton « Réessayer » | ⚠️ | Alert inline sans réessai (retry TanStack ×2 automatique) |
| 403 : message « Accès refusé » générique | ⚠️ | Géré localement (annuaire/plan) ; ailleurs message serveur brut |
| États vides « rassurants + CTA » | ⚠️ | « Aucune réservation pour le moment. » sans « Réservez votre première salle → » ; idem factures |
| Toasts éphémères | ❌ | Aucun système |
| Marquer lu **manuellement** (sans naviguer) | ⚠️ | Auto au clic uniquement ; l'API `POST /notifications/{id}/read` existe |
| Historique 90 j configurable | ❌ | Aucune purge ; SPA n'affiche que la page 1 (20) sans pagination |
| Notif « nouveau document interne à valider » (+ email critique) | ❌ | Aucune classe ni observer `InternalDocument` |
| Notif « résa confirmée/modifiée/annulée par l'admin » | ❌ | `BookingObserver` silencieux |
| Notif « absence enregistrée **par l'admin** pour le résident » | 🔀 | Émise quand le **résident** déclare (→ admins, Q25) ; rien côté saisie admin |

## §3.9 — Layout

| Exigence PRD | Statut | Constat |
|---|---|---|
| Logo image | ⚠️ | Texte « Ecoworking » |
| Menu profil ▼, switch thème dans le header | ❌ | Absents |
| Sidebar gauche (5 entrées) | 🔀 | Nav horizontale, 9 entrées |
| Footer | ❌ | Absent |
| Bottom nav mobile 5 icônes | 🔀 | Menu déroulant sous le header ; nav sans icônes |

---

## Volume

| Statut | Nombre d'écarts (hors ✅) |
|---|---|
| ❌ absent | ≈ 45 |
| ⚠️ partiel | ≈ 35 |
| 🔀 divergent | ≈ 25 |
| 🔮 hors MVP / à valider (pour mémoire) | ≈ 10 |

Le PRD §3 compte environ 230 puces vérifiables ; **≈ 55 % sont conformes**, le reste se concentre sur le calendrier (§3.5.2-3.5.5), les absences (§3.4.6), les filtres factures (§3.6.2), le chrome global (§3.1/§3.9) et les notifications (§3.8.4).

## Suite proposée (à trancher par Guillaume, rien n'est lancé)

Regrouper en lots, chacun = une branche + tests :

- **Lot A — Calendrier salles conforme** (§3.5.2, 3.5.3, 3.5.4, 3.5.5) : API dispo multi-salles avec occupant (Q4), grille semaine/jour, salle event lecture seule, créneaux libres/journée/demi-journée, modification de résa (`PATCH /api/bookings/{id}`), liste « à venir » vs historique. Le plus gros lot, le plus visible.
- **Lot B — Rôles & navigation** (§2.5) : gating nav/routes par permission (factures, présence, réservations, actualités), `isResident` basé sur « a un bureau attitré » (exposer `has_desk` dans `/api/user`).
- **Lot C — Absences** (§3.4.6) : `date_end` en récurrence, champ note, `PATCH /api/absences/{id}`, filtre « à venir », bureau attitré affiché, `DeskAbsence` auditable, Resource Filament absences.
- **Lot D — Factures & entreprise** (§3.6) : tri/filtres/recherche, bloc entreprise complet (paiement, IBAN-4, adresse) et basé sur `linkedCompanyIds()`, colonne libellé à définir.
- **Lot E — External** (§3.5.6, 3.5.9) : liste + annulation des bureaux nomades, détail par ticket, mailto, fériés filtrés côté dispo, plan filtré (ou acter la liste).
- **Lot F — Compte** (§3.4.2/3.4.5) : changement de mot de passe, photo (upload + redimensionnement), markdown bio, audit `MemberProfile`, email d'accueil à la création par l'admin.
- **Lot G — Chrome & transverses** (§3.1, 3.8, 3.9) : footer + `/accessibilite`, switch thème/menu profil header, toasts, Skeleton, offline, « Réessayer », états vides avec CTA, notifications manquantes (document à valider, résa admin), rétention 90 j.
- **À acter plutôt qu'à coder** (écarts 🔀 défendables) : nav horizontale vs sidebar, thème en base vs localStorage, Spinner vs Skeleton, `id="desk-N"` vs id DB, route `/pdf` vs `/download`, statut `partial`. Une ligne dans le PRD suffit pour les figer.
