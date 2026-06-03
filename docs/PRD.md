# PRD — Plateforme de gestion Ecoworking

> **Product Requirements Document** — spec fonctionnelle détaillée.
> Document complémentaire à [`BRIEF.md`](./BRIEF.md) (décisions techniques) et [`adr/`](./adr/) (décisions architecturales).
> Statut : v1 — à itérer pendant le dev.

**Conventions du document** :
- Ce qui vient des specs initiales fournies par Guillaume est intégré tel quel
- Ce que j'ajoute (Claude) est marqué `🟡 **Ajout à valider**` pour distinction claire
- Les questions ouvertes sont marquées `❓ **À trancher**`

---

## Sommaire

1. [Vue d'ensemble](#1-vue-densemble)
2. [Rôles et permissions](#2-rôles-et-permissions)
3. [Portail client (membre)](#3-portail-client-membre)
   - 3.1 Principes UI/UX globaux
   - 3.2 Authentification
   - 3.3 Accueil (dashboard)
   - 3.4 Profil
   - 3.5 Réservation ressources
   - 3.6 Administratif & facturation
   - 3.7 Annuaire des coworkers
   - 3.8 États transverses
   - 3.9 Layout & navigation
4. [Back-office admin (Filament)](#4-back-office-admin-filament)
   - 4.1 Dashboard admin
   - 4.2 Membres & profils
   - 4.3 Entités juridiques
   - 4.4 Contacts
   - 4.5 Offres & abonnements
   - 4.6 Ressources (salles, bureaux)
   - 4.7 Réservations
   - 4.8 Tickets nomades & présences
   - 4.9 Factures & paiements
   - 4.10 Documents (internes + administratifs)
   - 4.11 Annonces & événements
   - 4.12 Plan des étages
   - 4.13 Rôles & permissions
   - 4.14 Audit log
   - 4.15 Settings
5. [Workflows transverses](#5-workflows-transverses)
6. [Règles métier](#6-règles-métier)
7. [Questions ouvertes](#7-questions-ouvertes)

---

## 1. Vue d'ensemble

### 1.1 Vision produit

Plateforme de gestion sur-mesure pour Ecoworking, espace de coworking lyonnais. La plateforme remplace Cosoft (outil actuel) et couvre deux surfaces utilisateur :

- **Le portail client** (`portail.ecoworking.fr`) : interface des membres pour gérer leur profil, leurs factures, réserver des ressources, accéder à l'annuaire des coworkers, valider les documents internes
- **Le back-office admin** (`admin.ecoworking.fr`) : interface des admins Ecoworking pour gérer membres, entreprises, offres, abonnements, factures, ressources, événements, plan des étages, etc.

Les deux surfaces partagent la même base de données et la même logique métier.

### 1.2 Utilisateurs cibles

**Portail client** :
- ~40-50 membres résidents (abonnement mensuel, bureau attitré) — **cœur du business : ~90% du CA**
- **Quelques personnes additionnelles** (~5-10, membres rattachés à un abonnement d'entreprise, sans bureau attitré propre) — population marginale
- **~50 externals en BDD** mais **usage réel faible** : 10-30 tickets consommés / mois au total → **5-10% du CA max**. Compte portail créé mais beaucoup de comptes dormants ou occasionnels
- Optionnellement : contacts facturation purs (gestionnaires de facturation d'une entreprise non membre) avec compte portail mais aucun rôle membre

> **Implication produit** : prioriser l'UX du **flow résident** (90% du CA, usage quotidien intensif sur résa salle + occupation bureau). Le flow external reste nécessaire (achat tickets, résa salle via ticket, indication dispo bureau) mais peut accepter une UX un peu moins léchée sans impact business significatif. Pas de sur-investissement à prévoir sur ce module.

**Personnel Ecoworking** :
- 1 manageuse de l'espace (bureau attitré) — rôle `admin` + `staff` (cumul typique)
- Parfois 1 stagiaire / alternant (bureau attitré) — rôle `staff` seul

**Back-office admin** :
- 1-3 admins Ecoworking (typiquement la manageuse + back-up éventuel)

### 1.3 Objectifs métier

- Remplacer Cosoft avec une UX significativement supérieure
- Permettre l'autonomie des membres sur leur profil, leurs factures, leurs réservations
- Fluidifier le travail quotidien des admins (gain de temps réel sur la facturation et la gestion résa)
- Préparer la conformité à la réforme facturation électronique 2026-2027
- Servir de vitrine technique de l'écosystème Ecoworking

### 1.4 Principes UI/UX globaux

Issus des specs initiales et décisions actées :

- **Intégration HTML/CSS full responsive** desktop + mobile (usage principal desktop, mobile important)
- **UX++** : moderne, épuré, centré utilisabilité avant esthétique pure
- **Identité graphique** : réutilisation de l'existant ecoworking.fr — **même logo et palette couleurs** (issues du logo). Codes hexa exacts à fournir par Guillaume (à intégrer dans `tailwind.config.js` comme couleurs custom du projet)
- **Palette couleurs** : niveaux de gris + 2 primaires (vert + violet Ecoworking, codes hexa à figer)
- **Typographie** : **Inter** (sans-serif moderne, lisible, gratuite, multilingue, support emojis natif) → chargée via Google Fonts ou en self-hosted (préférable pour perf et conformité RGPD)
- **Thème light/dark** avec :
  - Détection automatique du thème système (`prefers-color-scheme`) par défaut au premier accès
  - Switcher utilisateur (icône ☀️/🌙 dans le header)
  - Persistance de la préférence en localStorage côté SPA portail + en base côté admin (selon contexte)

> 🟡 **Reste à fournir** : codes hexa exacts des primaires (Guillaume les fournira ultérieurement, à intégrer ensuite dans `tailwind.config.js` côté SPA + thème Filament côté admin)

---

## 2. Rôles et permissions

### 2.1 Architecture des rôles

Les rôles obéissent à **deux règles** :

**Règle 1 — Exclusivité du rôle d'usage** : un compte utilisateur peut avoir **au plus un** des quatre rôles suivants (mutuellement exclusifs) :
- `resident` — membre avec abonnement résident et bureau attitré
- `additional` — personne supplémentaire rattachée à un abonnement d'entreprise (sans bureau attitré propre, utilise librement les bureaux des résidents de son entité juridique — **sans suivi explicite dans l'app**)
- `external` — compte portail avec entité juridique rattachée, mais **sans abonnement résident/additional** : il paie pour chaque ressource qu'il utilise (résa salle, ticket bureau)
- `staff` — personnel Ecoworking (manageuse, stagiaire/alternant…) avec bureau attitré. Accès gratuit aux salles de réunion comme un résident. **Pas d'abonnement payant ni facturation associée**

Un compte peut aussi n'avoir **aucun** de ces 4 rôles (cas : `billing_contact` pur — gestionnaire de facturation d'une entreprise non membre Ecoworking).

**Règle 2 — Rôles additionnels cumulables** :
- `billing_contact` : peut s'additionner aux 4 rôles précédents ou exister seul
- `admin` : accès au back-office Ecoworking. Cumulable avec n'importe lequel des autres rôles. Exemple typique : la manageuse cumule `staff` + `admin`. Un stagiaire/alternant aura généralement `staff` seul.

### 2.2 Cas concrets possibles

| Combinaison | Description | Bureau attitré ? |
|---|---|---|
| `resident` | Résident lambda | Oui (`assigned_resident`) |
| `resident` + `billing_contact` | Résident qui gère la factu de son entreprise | Oui |
| `additional` | Personne sup. d'un abo entreprise | Non (utilise librement les bureaux résidents de l'entité) |
| `additional` + `billing_contact` | Personne sup. + factu de son entreprise | Non |
| `external` | Externe avec compte, paie ses résa | Non (achète des tickets) |
| `external` + `billing_contact` | Idem + gère factu de son entité | Non |
| `staff` | Stagiaire/alternant Ecoworking | Oui (`assigned_staff`) |
| `staff` + `admin` | Manageuse de l'espace | Oui |
| `billing_contact` seul | Gestionnaire factu pur, **non membre** Ecoworking | Non |
| `admin` seul | Admin Ecoworking sans présence physique | Non |
| `admin` + `resident` | Admin qui est aussi résident | Oui |
| (aucun rôle) | Compte créé mais sans rôle → pas d'accès portail | N/A |

### 2.3 Inventaire des rôles

| Rôle | Type | Description |
|---|---|---|
| `admin` | Staff Ecoworking — back-office | Accès complet au back-office + super-pouvoirs métier (réserver/déclarer présence pour quiconque, consommer tickets manuellement) |
| `resident` | Usage (XOR) | Abonnement résident avec bureau attitré. Accès gratuit aux salles de réunion 24/24 7/7 |
| `additional` | Usage (XOR) | Personne supplémentaire d'un abonnement d'entreprise (pas de bureau attitré propre). Mêmes droits que résident sur les salles de réunion |
| `external` | Usage (XOR) | Compte portail avec entité juridique. Pas d'abonnement résident/additional. **Paie chaque ressource** utilisée (résa salle via ticket demi-journée, ticket bureau demi-journée) |
| `staff` | Usage (XOR) | Personnel Ecoworking (manageuse, stagiaire/alternant). Bureau attitré (`assigned_staff`). Mêmes droits que résident sur les salles de réunion. **Pas d'abonnement payant** |
| `billing_contact` | Additionnel | Débloque le module Administratif/Facturation du portail. Cumulable avec resident/additional/external/staff, ou seul (gestionnaire factu pur d'une entreprise non membre) |

### 2.4 Implémentation des rôles cumulables

Avec `spatie/laravel-permission` :

```php
// Résident lambda
$user->assignRole('resident');

// Résident qui gère la factu de son entreprise
$user->assignRole(['resident', 'billing_contact']);

// Externe
$user->assignRole('external');

// Externe + factu
$user->assignRole(['external', 'billing_contact']);

// Contact factu pur (entreprise non membre)
$user->assignRole('billing_contact');

// Admin Ecoworking qui a aussi un bureau
$user->assignRole(['admin', 'resident']);
```

**Validation XOR resident/additional/external** : `spatie/laravel-permission` ne contraint pas nativement l'exclusivité entre certains rôles. Cette contrainte métier doit être implémentée :

1. **Côté UI** (Filament Resource) : sélecteur unique entre `resident`/`additional`/`external` (ou "aucun")
2. **Côté serveur** : une `Rule` Laravel custom dans le Form Request qui vérifie qu'un user n'a pas simultanément 2 des 3 rôles XOR
3. **Côté base** : une contrainte trigger Postgres en backstop (optionnel mais ceinture+bretelles)

> 🟡 **Conséquence sur le modèle de données** :
> - `member_profile` (table) reste 1-1 optionnel avec `users`. Existe pour `resident`/`additional`/`external` (les 3 ont une entité juridique rattachée et des données profil). N'existe pas pour `billing_contact` pur ni pour `admin` sans cumul membre.
> - Le lien `billing_contact ↔ entité juridique` se fait via la table `contacts` (un user contact factu a une ligne dans `contacts` avec `user_id` set et `role='billing'`)
> - L'entité juridique d'un `external` particulier (personne physique) : soit on crée une `company` virtuelle avec `legal_form='particulier'`, soit on permet `member_profile.company_id` NULL — à trancher

> ❓ **À trancher** : comment modéliser l'entité juridique d'un `external` particulier (sans SIRET) ?

### 2.5 Matrice des permissions portail

| Action | resident | additional | external | staff | + billing_contact | billing_contact pur |
|---|---|---|---|---|---|---|
| Accéder à l'accueil | ✅ | ✅ | ✅ | ✅ | hérité | ✅ |
| Éditer son profil | ✅ | ✅ | ✅ | ✅ | hérité | ✅ (limité aux champs perso) |
| Voir son entité juridique (lecture seule) | ✅ | ✅ | ✅ | N/A (pas d'entité payante) | hérité | ✅ |
| Voir l'annuaire des coworkers (incl. plan des étages) | ✅ | ✅ | ❌ | ✅ | hérité | ❌ |
| Apparaître dans l'annuaire (opt-in) | ✅ | ✅ | ❌ | ✅ | hérité | ❌ |
| Voir le calendrier des salles (réunion + event) | ✅ | ✅ | ✅ | ✅ | hérité | ❌ |
| Occuper son bureau attitré | ✅ (`assigned_resident`) | ❌ (utilise libre, pas de suivi) | ❌ | ✅ (`assigned_staff`) | hérité | ❌ |
| Marquer son bureau vacant (jour / plage / récurrence) | ✅ | ❌ | ❌ | ✅ | hérité | ❌ |
| Acheter un ticket "bureau libre demi-journée" | ❌ | ❌ | ✅ | ❌ | hérité | ❌ |
| Réserver une salle de réunion gratuitement (24/24 7/7) | ✅ | ✅ | ❌ | ✅ | hérité | ❌ |
| Réserver une salle de réunion via ticket demi-journée (jours ouvrés) | ❌ | ❌ | ✅ | ❌ | hérité | ❌ |
| Réserver la salle event | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Modifier/supprimer ses propres réservations | ✅ | ✅ | ✅ | ✅ | hérité | ❌ |
| Voir les factures de son entité | ❌ | ❌ | ❌ | ❌ | ➕ ajoute cette permission | ✅ |
| Voir les documents administratifs de son entité | ❌ | ❌ | ❌ | ❌ | ➕ ajoute cette permission | ✅ |
| Demander une modif d'entreprise | ❌ | ❌ | ❌ | ❌ | ➕ ajoute cette permission | ✅ |
| Valider les documents internes | ✅ | ✅ | ✅ (Q18 résolue) | ✅ | hérité | ⚠️ selon politique |
| S'inscrire aux events | ✅ | ✅ | ✅ | ✅ | hérité | ❌ |

**Lecture** :
- "hérité" = permission héritée du rôle de base
- `billing_contact` est un **rôle additionnel** qui n'ajoute que la visibilité factu/documents
- La salle event est **réservable uniquement par les admins** (cf. §2.6 et §3.5.4)
- Les `staff` ont des droits équivalents à un résident côté portail (bureau, salles, annuaire), sans la partie facturation
- Les `external` doivent valider les documents internes au même titre que les autres rôles d'usage (Q18 résolue)

### 2.6 Matrice des permissions admin

| Action | admin |
|---|---|
| CRUD membres et profils | ✅ |
| CRUD entités juridiques | ✅ |
| CRUD offres | ✅ |
| CRUD abonnements | ✅ |
| CRUD ressources | ✅ |
| **Créer/modifier/supprimer une résa au nom de n'importe qui** | ✅ |
| Voir toutes les résa | ✅ |
| CRUD factures | ✅ |
| Enregistrer paiements | ✅ |
| **Consommer manuellement un ticket nomade pour un membre** | ✅ |
| **Déclarer une présence nomade pour un membre** | ✅ |
| CRUD documents internes | ✅ |
| CRUD documents administratifs | ✅ |
| CRUD annonces/events | ✅ |
| Gérer plan des étages (attribuer bureau ↔ membre) | ✅ |
| Gérer rôles/permissions | ✅ |
| Voir audit log | ✅ |
| Settings | ✅ |

**Super-pouvoirs métier de l'admin** (en gras ci-dessus) :
- Réservation pour le compte de n'importe qui (cas : externe au téléphone, membre qui appelle, blocage interne)
- Consommation manuelle de tickets nomades (cas : nomade arrive sur place, admin lui décompte à la volée)
- Déclaration de présence nomade pour autrui

### 2.7 Implémentation technique

- Gestion via `spatie/laravel-permission` (cf. BRIEF section 5.1)
- Rôles cumulables nativement (sauf contrainte XOR resident/additional/external à enforcer côté application)
- Permissions vérifiées via Policies Eloquent (`Gate::authorize()`, `$user->can(...)`, `@can` dans Blade)
- Filament respecte les Policies pour chaque Resource
- Portail SPA : middleware sur les routes API + le front cache les sections inaccessibles via `user.permissions` exposé au login

### 2.8 Permissions granulaires (pour info)

Liste exhaustive des permissions à créer via `spatie/laravel-permission` (à figer en V1) :

```
// Portail
view-annuaire, view-own-bookings, view-bookings-calendar, create-own-booking,
create-paid-booking, manage-own-booking, register-event, validate-internal-document,
view-billing-section, view-entity-invoices, view-entity-admin-documents,
request-entity-modification, purchase-nomad-tickets

// Admin
manage-members, manage-companies, manage-contacts, manage-offers,
manage-subscriptions, manage-resources, manage-bookings, manage-bookings-for-others,
manage-invoices, manage-payments, manage-internal-documents, manage-admin-documents,
manage-announcements, manage-desk-assignments, manage-roles, view-audit-log,
manage-settings, consume-nomad-tickets-for-others, declare-presence-for-others
```

Les rôles sont définis comme des compositions de ces permissions (à figer en seeder).

---

## 3. Portail client (membre)

### 3.1 Principes UI/UX globaux

Cf. §1.4. Points spécifiques au portail :

- Navigation principale toujours accessible (sidebar desktop, bottom nav mobile)
- Header avec nom utilisateur + bouton menu profil (logout, switch thème)
- Footer minimaliste : mentions légales, CGU, contact
- **Toast notifications** via [Sonner](https://sonner.emilkowal.ski/) (shadcn-compatible) — usage : feedback action immédiat, succès, erreurs, infos non bloquantes
- **États de chargement** via shadcn/ui Skeleton component (skeletons sur les blocs, pas de spinner full-screen)
- Mobile-first sur certaines vues (résa en mode jour notamment)
- PWA : installable (icône bureau + écran d'accueil mobile), cache assets statiques

#### Accessibilité — exigence RGAA (importante)

Le portail vise une **conformité RGAA (Référentiel Général d'Amélioration de l'Accessibilité) au maximum** dès le MVP — pas seulement WCAG. C'est un référentiel français basé sur WCAG 2.1 niveau AA, avec un cadre méthodologique et juridique spécifique à la France. Approprié pour un site français destiné à des clients français.

**Cible** : RGAA 4.1 (version actuelle) — niveau AA.

**Exigences techniques** (intégrées dès le dev, pas en patch après) :
- Navigation 100% clavier (tab order logique, focus visible, raccourcis pour actions critiques)
- ARIA labels et roles corrects sur tous les composants interactifs custom
- Contrastes texte/fond conformes WCAG AA (4.5:1 pour le texte normal, 3:1 pour le texte large)
- Tailles de police relatives (rem, em), zoom 200% sans casse de mise en page
- Alternatives textuelles pour toutes les images significatives (`alt`) + images décoratives marquées (`alt=""`)
- Structure sémantique HTML5 (header, nav, main, aside, footer, articles avec hiérarchie h1→h6)
- Formulaires avec labels associés, messages d'erreur explicites et signalés au screen reader
- Composants complexes (calendrier de résa, plan des étages SVG) avec alternatives accessibles : pour le SVG, équivalent texte de l'occupation des bureaux ; pour le calendrier, vue liste alternative
- Mode dark : contrastes vérifiés indépendamment du mode light
- Animations respectant `prefers-reduced-motion`

**Outils et workflow** :
- `eslint-plugin-jsx-a11y` activé en strict dans la config ESLint/Biome (lint à chaque save)
- Audit automatisé `axe-core` intégré aux tests Playwright (un test e2e a11y par écran critique)
- Audit manuel périodique avec Pa11y / Lighthouse / WAVE
- Tests réels avec lecteur d'écran (NVDA gratuit pour Windows) sur les flows principaux avant chaque release majeure

**Déclaration d'accessibilité** : à produire et publier sur le portail (`/accessibilite`) — c'est une obligation du RGAA. Contient : niveau de conformité atteint, dérogations éventuelles, contact en cas de problème d'accessibilité. Template disponible sur [accessibilite.numerique.gouv.fr](https://accessibilite.numerique.gouv.fr/).

> 🟡 **Note sur le périmètre légal** : le RGAA s'impose juridiquement aux services publics et aux entreprises > 250M€ de CA. **Pour Ecoworking, ce n'est pas une obligation légale**, mais une démarche qualité importante (signal de sérieux, inclusion réelle, pertinence pour les membres ou visiteurs en situation de handicap).

> 🟡 **Pour le back-office Filament** : a11y est gérée nativement par Filament dans une bonne mesure (composants Livewire respectueux). Pas d'effort spécifique de mise en conformité du back-office, mais éviter les régressions sur les pages custom.

### 3.2 Authentification

> 🟡 **Section entièrement ajoutée par Claude** (non détaillée dans les specs initiales)

**Login**
- Page dédiée hors authentification (`/login`)
- Email + mot de passe
- Lien "Mot de passe oublié ?" → reset par email (token expiration 1h)
- Lien "Magic link" — envoi d'un lien de connexion direct par email, valable 15 min (Q6 résolue : V1)
- 2FA TOTP optionnel (recommandé mais non bloquant pour les membres)

**Inscription**
- Pas de self-service en MVP : c'est l'admin qui crée les comptes membres
- Email d'accueil envoyé au membre avec lien de définition initial du mot de passe

**Déconnexion**
- Bouton dans le menu profil
- Détruit la session côté serveur
- Redirige vers `/login`

**Sécurité**
- Rate limiting Fortify (5 tentatives login/60s/IP)
- Pas de message qui révèle si l'email existe ou non en cas d'échec login
- Magic link : token signé, à usage unique, expiration 15 min, invalidation à la première utilisation

### 3.3 Accueil (dashboard)

#### 3.3.1 Vue d'ensemble

Page d'accueil après connexion. Vue récapitulative qui agrège les infos pertinentes sans surcharger.

#### 3.3.2 Composition

**Entête**
- Texte : "Bonjour [prénom]"
- 🟡 Possibilité d'ajouter un sous-titre contextuel (ex. "X documents en attente", "Y résa cette semaine") si pertinent

**Bloc 3 dernières factures**
- Titre du bloc : "Mes dernières factures"
- 3 lignes max, format : numéro, date d'émission, statut de paiement (badge coloré), bouton télécharger PDF
- Statuts visibles : payée (vert), en attente (jaune), en retard (rouge), annulée (gris)
- Si moins de 3 factures : afficher ce qu'il y a, masquer les lignes vides
- Si zéro facture : message "Aucune facture pour le moment"
- Lien en pied de bloc : "Voir toutes mes factures →" vers le module Administratif/Facturation
- 🟡 Affichage **conditionné** au rôle `billing_contact` ou si la facturation concerne le user en nom propre — sinon le bloc est masqué (un additional sans billing n'a pas accès aux factures)

**Bloc 3 dernières infos/events**
- Titre du bloc : "Actualités Ecoworking"
- 3 cards format : titre, date, mini-description (max 100 caractères), lien "Voir →"
- Distinction visuelle entre info pure (badge "Info") et event avec inscription (badge "Événement")
- Si moins de 3 : afficher ce qu'il y a
- Si zéro : message "Aucune actualité pour le moment"
- Lien en pied de bloc : "Voir toutes les actualités →"

**Bloc documents à valider** 🟡 (section enrichie)
- Titre du bloc : "Documents à valider"
- Liste des documents internes que l'utilisateur n'a pas encore validés ou dont une nouvelle version est sortie
- Documents prévus : charte interne, conditions générales & Internet, droit à l'image
- Chaque ligne : titre du document, bouton "Télécharger" (PDF), bouton "Valider"
- Date de validation affichée si déjà validé : "Validé le DD/MM/YYYY"
- 🟡 Si version du document mise à jour par l'admin, la validation précédente devient invalide → le doc redevient "à valider" (à confirmer)
- Si zéro doc à valider : bloc masqué ou message "Tous vos documents sont à jour"

**Bloc 3 prochaines réservations**
- Titre du bloc : "Mes prochaines réservations"
- 3 lignes max, format : libellé (si renseigné par le user), nom ressource, date + créneau horaire
- Si zéro résa à venir : message "Aucune réservation à venir"
- Lien en pied de bloc : "Module réservations →"

**Bouton "Nous contacter"**
- Simple `mailto:contact@ecoworking.fr?subject=[backend ecowo] Demande d'informations`
- 🟡 À placer en footer ou comme floating action button (FAB) sur mobile

#### 3.3.3 Layout

- Desktop : grid responsive 2 colonnes (gauche : factures + résa, droite : actualités + documents) ou 1 colonne large
- Mobile : 1 colonne, ordre vertical (entête → documents à valider en priorité si présents → factures → résa → actualités)

> 🟡 **Ajout à valider** : ordre exact des blocs sur mobile et desktop, à affiner avec wireframes

### 3.4 Profil

#### 3.4.1 Vue d'ensemble

Page de gestion des données personnelles + visualisation des données entreprise (non éditables côté membre).

#### 3.4.2 Section "Mes informations"

**Champs lecture seule (édition admin uniquement)**
- Nom
- Prénom

**Champs éditables**
- Email / login
- Mot de passe (formulaire dédié : ancien mot de passe + nouveau + confirmation)
- Photo de profil (upload image, recadrage carré, max 2 Mo, formats JPG/PNG/WebP)
- Date de naissance (optionnelle)
- Fonction / poste
- Présentation (éditeur markdown, max ~500 caractères source — Q11 résolue)
- Centres d'intérêt (textarea ou tags, max ~200 caractères)
- URL LinkedIn (validation URL)
- URL site web (validation URL)
- Afficher mon profil dans l'annuaire Ecoworking (bool, opt-in)
- M'inscrire à la newsletter (bool, opt-in)

> 🟡 **Ajout à valider** :
> - Photo de profil : redimensionnement automatique (~400×400 max stocké), génération d'avatar par défaut (initiales + couleur de fond générée du nom) si absente
> - Stockage : ImageKit recommandé pour transformations à la volée (résolution adaptée à l'usage : 80×80 pour annuaire, 200×200 pour modal détail)
> - Présentation : éditeur markdown avec preview (lib type @uiw/react-md-editor ou similaire). Rendu via une lib markdown sécurisée (XSS-safe, par ex. `marked` + `dompurify` côté front, ou `commonmark` côté back si on préfère rendre en SSR)

#### 3.4.3 Section "Mon entreprise"

Bloc lecture seule affichant les infos de l'entité juridique rattachée :
- Nom entreprise (raison sociale)
- Statut juridique (SARL, SAS, EI, association, etc.)
- SIRET
- Numéro TVA intracom
- Email de facturation
- Adresse complète (rue, code postal, ville, pays)

Bouton "Demander une modification" → `mailto:contact@ecoworking.fr?subject=[backend ecowo] Demande de modification`

> 🟡 **Ajout à valider** :
> - Si membre rattaché à une entreprise vs en nom propre : afficher "Mon entreprise" vs "Mes données de facturation" selon le cas
> - Si pas rattaché à une entité juridique (cas atypique) : masquer ce bloc ou afficher message explicatif

#### 3.4.4 Actions

- Bouton "Enregistrer mes modifications" en bas
- Feedback : toast succès / erreur
- Validation côté front (Zod) + back (Form Request)
- Mise à jour partielle possible (PATCH /api/profile)

#### 3.4.5 Sécurité

- Changement de mot de passe : ré-authentification requise (mot de passe actuel obligatoire)
- Changement d'email : envoi d'un email de confirmation à la nouvelle adresse + invalidation jusqu'à confirmation 🟡
- Audit log : trace tout changement de champs sensibles (email, mot de passe, opt-in newsletter, visibilité annuaire)

#### 3.4.6 Mon bureau & mes absences (résidents et staff uniquement)

> Sous-section accessible aux utilisateurs avec rôle `resident` ou `staff` (ceux qui ont un bureau attitré). Cachée pour `additional`, `external`, `billing_contact` pur, `admin` sans bureau.

**Vue d'ensemble**
- Affichage du bureau attitré (numéro, étage, position sur le plan en mini-aperçu)
- Liste de mes absences planifiées à venir
- Bouton "Marquer une absence"

**Marquage d'absence**

Le résident/staff peut déclarer son bureau vacant sur certaines dates → cela permet aux admins de mieux placer les externals quand tous les bureaux sont attitrés et qu'il manque des bureaux libres.

Trois modes d'absence :
1. **Jour unique** : sélecteur de date + période (journée / matin / après-midi)
2. **Plage de dates** : date début + date fin (vacances par exemple)
3. **Récurrence sur une plage** : date début + date fin + jour de récurrence (ex. "tous les vendredis du 1er juin au 30 septembre")

**Champs**
- Date début, date fin (si applicable)
- Période (matin / après-midi / journée complète)
- Récurrence (nullable) : `daily` | `weekly` (jour de semaine) | `none`
- Note optionnelle (visible admin only, ex. "déplacement client")

**Édition / suppression**
- Possible jusqu'au début de l'absence
- Suppression rétroactive : warning si la période est déjà passée (audit log uniquement)

**Visibilité**
- L'absence apparaît sur le plan des étages avec un état "bureau vacant" pour les dates concernées
- L'admin voit également ces absences dans son back-office (cf. §4.8)
- Les autres membres voient juste le bureau comme "vacant temporaire" sur le plan

**UX++ d'entrée alternative**
- Depuis le plan des étages (§3.7), clic sur **son propre bureau** → bouton "Gérer mes absences" en plus des actions standards
- Lien direct depuis l'accueil si une absence est imminente (UX bonus)

> 🟡 **Implémentation modèle de données** suggérée : table `desk_absences` avec colonnes `desk_id`, `user_id`, `date_start`, `date_end`, `period`, `recurrence_type`, `recurrence_day_of_week` (nullable), `notes`. La logique d'expansion des récurrences se fait à la lecture (ne pas pré-générer N lignes individuelles).

> ❓ **À trancher** : faut-il une notification automatique à l'admin lors de l'enregistrement d'une absence longue (ex. > 5 jours) ?

### 3.5 Réservation de ressources

#### 3.5.1 Architecture des ressources

Trois types de ressources, chacun avec des règles d'utilisation et d'affichage différentes.

| Type | Quantité | Visualisation | Mode de "réservation" |
|---|---|---|---|
| **Bureau** | **48 au total** : 1-2 attitrés au personnel Ecoworking + ~40-50 attitrés aux résidents + reste libre pour external | Module **Annuaire > Plan des étages** (§3.7) | Occupation, pas de "résa" classique |
| **Salle de réunion** | 3 | Module **Réservation ressources > Calendrier** (cette section) | Résa créneau libre (resident/additional gratuit, external via ticket) |
| **Salle event** | 1 | Module **Réservation ressources > Calendrier** (lecture seule pour membres) | Réservable **uniquement par les admins** |

**Sous-types de bureaux** (champ `assignment` sur `resources`) :
- `assigned_resident` : bureau attitré à un membre `resident`. Si résident absent, utilisable par un `additional` de la même entité juridique.
- `assigned_staff` : bureau attitré au personnel Ecoworking (manageuse, stagiaire/alternant). **Non utilisable** par d'autres en cas d'absence du staff (cf. ❓ §7.1).
- `unassigned` (libre) : utilisable par les `external` via ticket bureau demi-journée.

**Conséquence UX importante** : ce module concerne **uniquement les salles** (réunion + event). L'occupation des bureaux est gérée séparément, visible via le plan des étages (§3.7). Le calendrier ne montre pas l'occupation des bureaux.

#### 3.5.2 Calendrier des salles

**Affichage par défaut**
- Toutes les salles (3 salles de réunion + 1 salle event) affichées simultanément
- Filtre par ressource (sélecteur multi-choix : choisir 1, 2, 3 ou toutes les salles)
- Semaine en cours par défaut, mode hebdomadaire
- Mode jour disponible (et par défaut sur mobile)
- 🟡 Codes couleur par ressource pour distinguer visuellement
- Mes propres résa mises en avant (contour ou couleur primaire renforcée)
- Résa des autres : visibles mais non cliquables pour modification. Hover/clic affiche le **nom du réserveur** (prénom + nom + entité juridique) + libellé si renseigné (Q4 tranchée : transparence par défaut)

**Salle event** : visible dans le calendrier en lecture seule (pour info), mais le membre **ne peut pas réserver** ce créneau — seul l'admin peut.

**Heures affichées**
- 24h/24 pour resident/additional (réservation libre 24/24 7/7)
- 9h-18h pour external (puisque seuls les créneaux demi-journée 9h-13h et 14h-18h jours ouvrés lui sont autorisés)
- 🟡 Affichage adaptatif : par défaut 8h-20h pour ne pas surcharger, mais possibilité de "voir 24h" pour resident/additional via toggle

**Navigation**
- Boutons "Semaine précédente" / "Semaine suivante" / "Aujourd'hui"
- Date picker pour aller à une semaine arbitraire
- 🟡 Limite : pas de résa au-delà de X semaines dans le futur (configurable, par défaut 12 semaines ?)

❓ **À trancher** : nom du réserveur visible aux autres ou anonyme (privacy) ?
→ ✅ **Tranchée (Q4)** : nom du réserveur **visible** aux autres membres (tooltip ou clic sur la résa affiche "prénom + nom + entité juridique" et le libellé si renseigné).

#### 3.5.3 Réserver une salle de réunion — flow par rôle

**Pour `resident` et `additional` : réservation libre gratuite**

UX++ rapide :
1. Clic sur un créneau libre dans le calendrier
2. Modal :
   - Ressource pré-remplie (déduite du clic)
   - Créneau pré-rempli (date + heure début/fin)
   - Toggle "Toute la journée / Demi-journée matin / Demi-journée après-midi / Créneau personnalisé (heure début + fin)"
   - Champ "Libellé / info" (optionnel)
3. Bouton "Réserver" → vérification serveur (dispo, conflits) → confirmation
4. Calendrier rafraîchi, toast succès
5. En cas de conflit (résa concurrente créée juste avant) : message clair + suggestion créneau proche

**Pour `external` : réservation via ticket demi-journée**

Workflow différent (puisque résa payante via ticket) :
1. **Pré-requis** : avoir des tickets "Salle de réunion demi-journée" disponibles, OU les acheter à la volée (module Achats)
2. Sélection du créneau : uniquement 9h-13h (matin) ou 14h-18h (après-midi), uniquement jours ouvrés (L-V hors fériés)
3. Si l'external n'a pas de ticket disponible : modal "Acheter un ticket et réserver" qui combine achat + résa en un flow
4. Validation serveur : créneau libre + ticket dispo + jour ouvré + heures conformes
5. Consommation du ticket à l'émission de la résa
6. Confirmation, toast, calendrier rafraîchi

**Validation côté serveur (commune)** :
- Conflit de résa : transaction DB avec `lockForUpdate()`
- Droits du user (rôle + abonnement actif pour resident/additional)
- Conformité aux règles par rôle (cf. §6.1)
- Pour external : présence d'un ticket valide à consommer

#### 3.5.4 Salle event — admin only

Visible dans le calendrier (mode lecture seule) mais :
- Aucun bouton "Réserver" disponible pour resident/additional/external
- 🟡 Au clic sur un créneau libre de la salle event : message "Pour réserver cette salle, contactez-nous" + bouton `mailto:`
- L'admin peut réserver pour soi ou pour n'importe quel autre user/entité depuis le back-office (cf. §4.7)

#### 3.5.5 Modifier / supprimer une réservation

- Clic sur **sa propre résa** → modal avec actions "Modifier" / "Supprimer"
- Modification : mêmes contrôles que création (notamment respect des règles par rôle)
- Suppression : confirmation requise
- **Délai d'annulation** : possible jusqu'à l'**heure de début** du créneau (Q22 résolue)
  - Au-delà : annulation impossible côté portail (le membre doit contacter l'admin si cas particulier)
- **Pour external avec ticket consommé** : l'annulation dans le délai **restitue automatiquement le ticket** (statut → `available`, date d'utilisation effacée)
- Audit log obligatoire pour toute annulation (resident/additional/external)

#### 3.5.6 Achats de tickets

Seuls les `external` peuvent acheter des tickets (Q21 résolue) :
- **Tickets bureau libre demi-journée** : achat à l'unité ou en pack (2, 10) avec dégressivité
- **Tickets salle de réunion demi-journée** (matin ou après-midi) : achat à l'unité ou en pack
- Tickets achetés → visibles dans "Mes tickets" du module, avec date d'expiration et statut (disponible / utilisé / expiré / restitué)

**Conséquence** : un `resident`, `additional` ou `staff` qui veut inviter ponctuellement un collègue ou tiers doit passer par l'admin (pas de self-service pour acheter un ticket au nom de quelqu'un d'autre).

🟡 Achat : workflow simple en MVP (pas de paiement en ligne — admin marque comme payé après réception SEPA/virement/CB physique, cf. BRIEF §5.5)

#### 3.5.7 Liste "Mes prochaines réservations"

- Vue alternative au calendrier, mode liste chronologique
- Mes résa à venir avec actions modifier/supprimer
- Pour external : indication du ticket consommé pour chaque résa (traçabilité)

#### 3.5.8 Sync Google Calendar

- Lien "Ajouter à mon agenda Google" dans le module
- Format : URL iCal publique (lecture seule) du calendrier Google partagé Ecoworking
- Le calendrier Google contient **les résa salles uniquement** (pas les occupations bureaux, qui sont gérées via le plan des étages)
- Sync sortante uniquement

> 🟡 **À envisager** : 2 liens iCal proposés
> - "Mes réservations seules" (personnalisé)
> - "Toutes les réservations Ecoworking" (vue globale)

#### 3.5.9 Cas particulier external — vérification dispo bureau

Pour les **tickets bureau libre demi-journée**, l'achat se fait sans choisir un bureau précis (les bureaux libres sont attribués au coup par coup). Avant l'achat, le système doit indiquer la disponibilité :

- Sur la page d'achat : sélecteur de date + période (matin / après-midi / journée)
- Affichage du nombre de bureaux libres restants pour la période sélectionnée
- Si zéro disponible : alerte avec message "Aucun bureau disponible sur ce créneau. Contactez-nous pour étudier les possibilités" + bouton `mailto:`
- 🟡 Calcul : `total_bureaux_non_attitres - tickets_bureau_consommes_pour_cette_periode`

❓ **À trancher** : on doit également considérer les bureaux attitrés des résidents absents pour calculer la dispo réelle ? Probablement non (politique simple : on ne sait pas qui sera absent → on ne propose que les bureaux explicitement libres).

### 3.6 Administratif & facturation

#### 3.6.1 Conditions d'accès

- Accessible **uniquement** aux utilisateurs ayant le rôle `billing_contact` (cf. §2)
- Si le user n'a pas le rôle : le module est masqué de la navigation
- 🟡 Le rôle peut être attribué par l'admin à un membre désigné comme référent factu de son entreprise

#### 3.6.2 Section "Mes factures"

- Listing de **toutes** les factures rattachées à l'entité juridique du user (entreprise, ou en nom propre, ou perso)
- Colonnes : libellé / nom de la facture, numéro, date d'émission, statut de paiement, lien de téléchargement PDF
- Statuts visibles : payée, en attente, en retard, partielle, annulée, brouillon (admin only)
- Pagination ou scroll infini
- Tri possible par date / numéro / statut (par défaut : date décroissante)

**Recherche / filtres**
- Filtre par mois
- Filtre par année
- Recherche par numéro de facture (texte)
- Filtre par statut de paiement
- 🟡 Combinaison de filtres possible

**Téléchargement**
- Clic sur "Télécharger" ouvre/télécharge le PDF
- 🟡 Lien direct vers `/api/invoices/{id}/download` protégé par auth + policy (seul le billing_contact de l'entité concernée peut télécharger)

#### 3.6.3 Section "Mes documents administratifs"

- Listing des documents administratifs rattachés à l'entité juridique : contrats, avenants, contrat de domiciliation
- Colonnes : titre du document, type (contrat / avenant / domiciliation / autre), date, bouton télécharger
- Tri par date décroissante par défaut
- 🟡 Documents uploadés par l'admin dans le back-office, rattachés à l'entité juridique

#### 3.6.4 Bloc "Mon entreprise"

- Récap **non éditable** de toutes les données de l'entité juridique :
  - Raison sociale, statut juridique, SIRET, numéro TVA
  - Email facturation, adresse complète
  - Mode de paiement préféré (SEPA, virement, CB)
  - IBAN (4 derniers chiffres uniquement pour rappel) 🟡
- Bouton "Demander une modification" → `mailto:contact@ecoworking.fr?subject=[backend ecowo] Demande de modification`

### 3.7 Annuaire des coworkers (avec plan des étages)

#### 3.7.1 Vue d'ensemble

Annuaire visuel des coworkers basé sur un **plan des étages** où chaque bureau est un bloc interactif. Le module sert deux usages :
- Découvrir les coworkers (qui est où, qui fait quoi)
- Visualiser l'**occupation des bureaux** en temps réel ou pour une date donnée (compléments du calendrier salles qui, lui, montre l'occupation des salles)

**Accessibilité** : module non accessible pour les `external` (cf. §2.5).

#### 3.7.2 Plan des étages

- Visualisation des **2 étages du local** (étage 1 et étage 2, ou rez-de-chaussée + étage selon configuration locale)
- Switcher étage 1 / étage 2
- Chaque bureau est un bloc interactif sur une map statique (SVG recommandé 🟡)
- Chaque bloc a un `id` correspondant à l'id du bureau en DB (`resources` de type `desk`)

**Typologie des bureaux** (cf. §3.5.1) :
- **Bureau attitré résident** (`assigned_resident`) : occupé par le résident. **L'occupation par un `additional` de la même entité juridique n'est pas suivie dans l'app** (libre placement informel).
- **Bureau attitré staff** (`assigned_staff`) : occupé par le personnel Ecoworking (manageuse, stagiaire/alternant). 1-2 bureaux concernés. Strictement réservé.
- **Bureau non attitré** (`unassigned`) : utilisable par les `external` via ticket demi-journée.
- Optionnel : bureaux en "hors service" (maintenance, vacant durable)

**Présence par défaut implicite** (cf. §6.1 et Q14 résolue) :
- Un `resident` est considéré présent par défaut tous les jours ouvrés sur son bureau attitré, sauf s'il a déclaré une absence (cf. §3.4.6)
- Un `staff` est considéré présent par défaut tous les jours ouvrés sur son bureau attitré, sauf s'il a déclaré une absence
- Aucune déclaration de présence quotidienne n'est demandée

**Sélecteur de date**
- 🟡 Par défaut : "Aujourd'hui"
- Possibilité de naviguer aux jours précédents/suivants pour voir l'occupation passée ou prévisionnelle
- L'occupation affichée dépend de la date sélectionnée

#### 3.7.3 États visuels des bureaux

| État du bureau | Représentation visuelle | Tooltip / clic |
|---|---|---|
| Bureau attitré occupé par son résident (par défaut, jour ouvré, pas d'absence déclarée) | Couleur "résident présent" + photo si profil opt-in | Tooltip : prénom, nom, entreprise. Clic : modal détaillée (cf. §3.7.4) |
| Bureau attitré résident **avec absence déclarée** | Couleur "vacant temporaire" + photo grisée du résident | Tooltip : "Bureau de X (absent)" |
| **Bureau staff** occupé par le personnel Ecoworking | Couleur "staff" distincte | Tooltip : prénom, nom, "Équipe Ecoworking". Clic : modal détaillée si opt-in |
| **Bureau staff** avec absence déclarée | Couleur "staff vacant" | Tooltip : "Bureau équipe (absent)" |
| Bureau non attitré, **occupé par un external** (ticket consommé) | Couleur "external présent" | Tooltip : prénom, nom, entreprise. Clic : modal détaillée si opt-in |
| Bureau non attitré, **libre** | Couleur "disponible" | Tooltip : "Bureau libre" |
| Bureau hors service | Couleur "désactivée" | Tooltip : "Hors service" |

#### 3.7.4 Interactions

**Hover sur un bloc bureau occupé**
- Tooltip : prénom, nom, entreprise rattachée (si opt-in annuaire) ou anonyme

**Clic sur un bloc bureau occupé (par un coworker opt-in annuaire)**
- Ouverture d'une **modal** (ou panneau latéral) avec les infos détaillées :
  - Photo de profil
  - Prénom + nom
  - Entreprise rattachée
  - Fonction / poste
  - Présentation
  - Centres d'intérêt
  - Liens : LinkedIn, site web
  - 🟡 Bouton "Contacter" (mailto vers l'email du coworker)

**Clic sur un bloc bureau libre (non attitré)**
- Modal courte : "Bureau libre — Pour réserver ce type de bureau à la demi-journée, contactez-nous"
- 🟡 Pour les externals (qui n'accèdent pas à ce module) : le mécanisme passe directement par l'achat de ticket via le module Réservation ressources (cf. §3.5.9)

**Clic sur un bloc bureau attitré dont le résident a opt-out de l'annuaire**
- Tooltip / modal : "Coworker (souhaite rester discret)" — pas de détails personnels affichés

**Clic sur SON PROPRE bureau (résident ou staff)**
- Modal/panneau enrichi avec :
  - Ses infos visibles dans l'annuaire (s'il a opt-in) — pour preview
  - **Bouton "Gérer mes absences"** → ouvre la sous-section §3.4.6 (raccourci UX++)
  - **Statut du bureau aujourd'hui** (présent / absent selon ses absences déclarées)

#### 3.7.5 Règles de visibilité

- Un coworker n'apparaît avec ses détails dans l'annuaire **que s'il a opté-in** via son profil (`afficher mon profil dans l'annuaire = O`)
- Si opt-out : son bureau est affiché comme "Coworker (souhaite rester discret)" sans détails personnels
- Les `staff` apparaissent avec mention "Équipe Ecoworking" et opt-in standard pour les détails
- 🟡 Bureaux des admins Ecoworking : si l'admin cumule avec `resident`/`staff` (donc bureau attitré), il apparaît normalement selon son opt-in. Si pas de bureau : pas dans l'annuaire

#### 3.7.6 Implémentation technique

- Carte SVG statique stockée dans le repo (versionnée)
- Liaison bureau ↔ résident : champ `member_profiles.desk_id` (FK vers `resources` de type `desk`)
- Liaison occupation jour → utilisateur : table dédiée (cf. modèle de données — sera détaillé dans `data_model.md`)
- Mise à jour du plan : tâche admin (upload nouvelle SVG si réagencement)
- 🟡 Format recommandé : SVG avec attributs `data-desk-id="123"` sur chaque path/rect cliquable

> 🟡 **Ajout à valider** :
> - Plan SVG **statique défini une fois pour toutes** (Q13 résolue) — pas d'édition admin via UI. Si le plan évolue, l'admin remplace le fichier SVG dans le repo et redéploie
> - Performance : viser <500 Ko optimisé pour le SVG

### 3.8 États transverses

> 🟡 **Section entièrement ajoutée par Claude** (UX critique souvent oubliée)

#### 3.8.1 États de chargement

- **Skeletons** sur les blocs en cours de chargement (pas de spinner full-screen)
- **Lazy loading** des images (photos profil, plan)
- **Suspense React** pour les composants asynchrones

#### 3.8.2 États d'erreur

- **Erreur réseau** (offline) : bandeau persistant en haut de page "Vous êtes hors-ligne, certaines données peuvent ne pas être à jour"
- **Erreur API 500** : toast d'erreur + bouton "Réessayer" sur le bloc concerné
- **Erreur 403/401** : redirection vers login si expiration session, message "Accès refusé" sinon
- **Erreur 404** : page dédiée avec lien retour accueil

#### 3.8.3 États vides

- Toujours afficher un message rassurant + CTA si pertinent
- Exemples :
  - Pas de facture : "Aucune facture pour le moment. Les factures apparaîtront ici dès qu'elles seront émises."
  - Pas de résa : "Aucune réservation. Réservez votre première salle →"
  - Pas d'actualité : "Aucune actualité pour le moment."

#### 3.8.4 Notifications

- **Toasts** pour feedback action immédiat (succès, erreur, info — éphémères, durée ~3-5s)
- **Centre de notifications persistant** (Q5 résolue) :
  - Icône cloche dans le header avec badge "X non lues"
  - Panneau déroulant ou page dédiée listant les notifications avec statut lu/non lu
  - Marquer comme lu manuellement OU automatiquement quand l'utilisateur clique sur la notification
  - Marquer toutes comme lues : action bulk
  - Historique conservé (ex. 90 jours configurable)
  - Types d'événements générant une notification :
    - Nouveau document interne à valider
    - Résa confirmée (création/modification/suppression admin)
    - Facture émise / en retard
    - Absence enregistrée par l'admin pour le résident
    - Annonce / event publié par l'admin (si visibilité applicable)
- Stockage : table `notifications` Laravel native (driver `database`) — pas de WebSocket en MVP, refresh à la connexion / poll léger
- 🟡 Notifications email : doubler les notifs in-app par un email pour les événements critiques (facture émise, document à valider) — toggle dans le profil utilisateur

### 3.9 Layout & navigation

#### 3.9.1 Desktop

```
┌────────────────────────────────────────────────────────────┐
│ Logo Ecoworking          [Menu profil ▼] [Switch thème ☼]  │
├────────────────────────────────────────────────────────────┤
│ ┌──────────┐  ┌────────────────────────────────────────┐   │
│ │          │  │                                        │   │
│ │ Sidebar  │  │           Contenu principal            │   │
│ │          │  │                                        │   │
│ │ ▸ Accueil│  │                                        │   │
│ │ ▸ Profil │  │                                        │   │
│ │ ▸ Résa.  │  │                                        │   │
│ │ ▸ Annu.  │  │                                        │   │
│ │ ▸ Factu. │  │                                        │   │
│ │          │  │                                        │   │
│ └──────────┘  └────────────────────────────────────────┘   │
│                                                            │
│           Footer (mentions, CGU, contact)                  │
└────────────────────────────────────────────────────────────┘
```

#### 3.9.2 Mobile

```
┌──────────────────────────┐
│ Logo  [☼]  [☰ Menu]      │
├──────────────────────────┤
│                          │
│      Contenu principal   │
│                          │
│                          │
│                          │
│                          │
├──────────────────────────┤
│  [🏠] [👤] [📅] [📍] [📄]  │  ← Bottom nav
└──────────────────────────┘
```

> 🟡 **Ajout à valider** : icônes exactes, ordre, comportement actif

---

## 4. Back-office admin (Filament)

> 🟡 **Section entièrement générée par Claude en miroir des besoins front + besoins propres admin.**
> Tous les modules ci-dessous sont à valider. Filament fournit l'UI standard (Resources, Forms, Tables, Widgets), donc l'effort est de définir le périmètre et les règles, pas l'UI.

### 4.1 Dashboard admin

#### 4.1.1 Vue d'ensemble

Page d'accueil après login admin. Vue de pilotage rapide.

#### 4.1.2 Widgets prévus

**KPIs en haut**
- Membres actifs (count)
- Abonnements actifs (count)
- Factures en retard (count + montant total impayé)
- CA du mois en cours (vs mois précédent)
- Taux d'occupation moyen salles (semaine en cours)

**Blocs d'alerte**
- Factures en retard (top 5)
- Abonnements qui se terminent dans les 30 prochains jours
- Documents internes non validés par X% des membres

**Activité récente**
- 10 dernières actions (création membre, émission facture, etc.) issues de l'audit log
- Lien "Voir tout l'audit log →"

**Vue rapide "Aujourd'hui"**
- Liste des résa du jour (salles + bureaux nomades)
- Liste des nouveaux membres arrivés cette semaine

### 4.2 Membres & profils

#### 4.2.1 Listing

- Table Filament avec colonnes : avatar, nom complet, email, entreprise rattachée, statut (actif/pausé/parti), rôle, bureau attribué, date d'arrivée
- Filtres : statut, rôle, entreprise, présence d'abonnement actif, date d'arrivée
- Recherche full-text : nom, prénom, email, entreprise
- Tri : nom, date d'arrivée, statut
- Export CSV
- Actions bulk : envoyer email groupé, changer statut

#### 4.2.2 Création

- Wizard en 2-3 étapes :
  1. Données user (nom, prénom, email, rôle)
  2. Profil membre (entreprise, statut, date d'arrivée, bureau optionnel)
  3. Abonnement optionnel (lier directement à une offre)
- Envoi automatique d'un email d'accueil avec lien de définition initial du mot de passe

#### 4.2.3 Édition

- Formulaire complet avec **tous** les champs (y compris ceux lecture-seule côté portail)
- Sections séparées : Identité, Coordonnées, Profil public, Abonnement & rôle, Bureau attribué, Notes admin
- Upload photo de profil
- Notes internes (champ admin, non visible portail)

#### 4.2.4 Suppression

- Soft delete avec confirmation
- 🟡 **Workflow d'anonymisation** RGPD (cf. §5.6) : pour vrai "droit à l'oubli", action dédiée "Anonymiser ce membre" qui efface les données perso tout en conservant l'intégrité comptable

#### 4.2.5 Actions disponibles

- Réinitialiser le mot de passe (envoie un email)
- Désactiver / réactiver le 2FA
- Envoyer un email manuel
- Voir les factures de ce membre
- Voir les résa de ce membre
- Voir l'historique audit (filtré sur ce membre)

### 4.3 Entités juridiques (entreprises + particuliers)

> Cette section gère les **entités billables** : entreprises ET particuliers (typiquement pour les `external` qui sont des personnes physiques sans SIRET).

#### 4.3.1 Type d'entité

À la création/édition, l'admin choisit le **type** :
- `company` : entreprise classique (SIRET obligatoire, raison sociale, forme juridique, TVA…)
- `individual` : particulier (pas de SIRET, juste nom/prénom + adresse)

Le formulaire d'édition affiche dynamiquement les champs pertinents selon le type.

#### 4.3.2 Listing

- Table Filament : type (badge), raison sociale OU nom du particulier, SIRET (ou — pour particulier), ville, nombre de membres rattachés, abonnements actifs, factures en retard
- Filtres : type, statut (active/inactive), ville, présence SEPA mandate
- Recherche : nom (raison sociale ou nom + prénom), SIRET
- Export CSV

#### 4.3.3 Édition

**Champs communs** (tous types)
- Type d'entité (`company` / `individual`) — non modifiable après création (sauf cas exceptionnel admin)
- Adresse complète (rue, code postal, ville, pays)
- Email facturation
- Mode de paiement préféré (SEPA / virement / CB / chèque)
- Section SEPA : 4 derniers chiffres IBAN, référence mandat, date signature
- Upload mandat SEPA PDF (stocké sur Cellar)
- Notes admin

**Champs spécifiques `company`**
- Raison sociale (obligatoire)
- Forme juridique (SARL, SAS, EI, association…)
- SIRET (obligatoire, validation 14 chiffres)
- Numéro TVA intracom
- Code APE (optionnel)

**Champs spécifiques `individual`**
- Nom (obligatoire)
- Prénom (obligatoire)
- Date de naissance (optionnel, pour cas particuliers)
- Pas de SIRET, ni de TVA, ni de raison sociale

#### 4.3.4 Vue détail

- Onglet "Membres rattachés" : liste des `member_profiles` liés
- Onglet "Abonnements" : actifs et historique
- Onglet "Factures" : toutes les factures de l'entité
- Onglet "Documents" : contrats, avenants, etc.
- Onglet "Contacts" : liste des contacts associés

#### 4.3.5 Conséquences sur les factures

- Pour `company` : facture émise au nom de la raison sociale avec SIRET, TVA, etc.
- Pour `individual` : facture émise au nom du particulier (prénom + nom + adresse), sans mention SIRET ni TVA (assujetti TVA à voir au cas par cas — la plupart du temps non, la facture est TTC simple)

> 🟡 **À valider avec expert-comptable** : règles de facturation différentes entre BtoB (company) et BtoC (individual) — notamment côté Factur-X 2027 (le BtoC est-il dans le périmètre de l'obligation ? *a priori non*).

### 4.4 Contacts

#### 4.4.1 Listing

- Table : nom complet, entreprise, rôle (billing/management/technical), email, téléphone, contact principal Y/N
- Filtres : entreprise, rôle
- Recherche : nom, email

#### 4.4.2 Édition

- Formulaire : nom, prénom, email, téléphone, rôle, entreprise (sélecteur), is_primary, notes
- 🟡 Possibilité de lier un contact à un user du portail (cas où le contact a aussi un compte)

### 4.5 Offres & abonnements

#### 4.5.1 Catalogue d'offres

**Listing**
- Table : code, nom, type (subscription/one_shot/pack), période, prix HT, TVA, ticket type, actif, public
- Filtres : type, actif
- Tri : ordre d'affichage public

**Édition**
- Formulaire complet : code, nom, description, type, période, prix HT, TVA, quantity_per_purchase, ticket_type, max_per_user, features (JSON ou éditeur structuré), actif, public, display_order

**Désactivation**
- Une offre désactivée n'est plus proposée à la souscription mais les abonnements existants continuent

#### 4.5.2 Abonnements actifs

**Listing**
- Table : membre, entité billable, offre, statut, date début, date fin prévue, prix HT
- Filtres : statut, offre, billable
- Tri : date début

**Création**
- Wizard : choisir membre → choisir offre → définir billable (user ou company) → définir date début + billing day → snapshot prix
- Validation : un membre ne peut avoir qu'un seul abonnement `subscription` actif à la fois (à confirmer)

**Édition**
- Modifier statut (pause / reprise / résiliation)
- Modifier date de fin
- Ajouter remise ponctuelle
- Notes

**Actions**
- Suspendre / réactiver
- Résilier (avec date de fin + raison)
- Voir factures liées

### 4.6 Ressources

#### 4.6.1 Types de ressources

Trois types principaux gérés par l'admin (cf. §3.5.1) :

| Type code | Description | Quantité prévue | Réservable par |
|---|---|---|---|
| `desk` | Bureau (attitré résident, attitré staff, ou libre) | **48 au total** (1-2 staff + ~40-50 résidents + reste libre) | Mécaniques différentes selon attribution (cf. §4.7 et §4.8) |
| `meeting_room` | Salle de réunion | 3 | resident / additional (gratuit) + external (via ticket) |
| `event_room` | Salle événement | 1 | **Admin uniquement** |

Optionnels (V3+) : `phone_booth`, `parking`, ou autres.

#### 4.6.2 Listing

- Table : nom, type, attribué à (pour les desks), capacité, actif, tarif externe HT (si applicable)
- Filtres : type, actif, attribué/libre (pour desks)
- Recherche : nom

#### 4.6.3 Édition

Formulaire avec champs adaptés au type :

**Communs**
- Nom, description, capacité
- Features (JSON : TV, whiteboard, écran, paper-board, etc.)
- Tarif externe HT (si applicable, ex. tarif demi-journée salle réunion pour ticket external)
- Plages horaires d'ouverture (par défaut 24/24 pour resident, 9h-18h jours ouvrés pour external)
- Couleur calendrier Google
- Actif Y/N, ordre d'affichage

**Spécifique `desk`** (48 unités au total)
- ID unique pour mapping au plan des étages SVG
- Champ `assignment` :
  - `assigned_resident` : attitré à un membre `resident` (~40-50 bureaux)
  - `assigned_staff` : attitré au personnel Ecoworking (1-2 bureaux : manageuse + stagiaire/alternant éventuel)
  - `unassigned` : libre, utilisable par les `external` via ticket
- Si `assigned_*` : sélecteur de membre (FK `member_profiles.desk_id` ou équivalent)
- Étage : 1 / 2 (pour filtre rapide)

**Spécifique `meeting_room`**
- Créneaux min/max (durée réservation)
- Type de tickets autorisés pour external (demi-journée matin / demi-journée après-midi)
- Tarif unitaire ticket demi-journée pour external

**Spécifique `event_room`**
- Restriction admin only (`requires_admin = true`)
- Validation au niveau Policy : aucun user non-admin ne peut créer une réservation

### 4.7 Réservations (salles)

> Ce module gère les **réservations de salles** (meeting_room + event_room). L'**occupation des bureaux** (desks) est gérée dans le module §4.8 (Tickets & occupations bureau).

#### 4.7.1 Listing

- Table : ressource, "au nom de" (membre/user), entité billable, date début, date fin, statut, prix HT, titre, ticket consommé (si external)
- Filtres : ressource, statut, date, membre, billable, type de ressource (meeting/event)
- Recherche : nom membre, libellé résa
- Vue calendrier hebdomadaire admin (toutes salles, multi-couleurs par ressource)
- 🟡 Vue calendrier mensuelle aussi disponible (vue d'ensemble macro)

#### 4.7.2 Création — au nom de n'importe qui

L'admin a un **super-pouvoir** : créer une réservation au nom de n'importe quel utilisateur (membre, externe, ou même un user fictif "Walk-in" pour cas non rattachés).

**Cas d'usage typiques** :
- Externe qui appelle pour réserver une salle (admin saisit pour lui)
- Membre qui appelle parce qu'il n'a pas accès au portail à l'instant
- Bloquer une salle pour usage interne Ecoworking (ménage, maintenance)
- Réserver la **salle event** (seul moyen — non disponible côté portail)
- Réservation récurrente (admin crée une série sur N semaines)

**Formulaire** :
- Champ "Au nom de" : sélecteur de user (recherche par nom/email/entreprise)
- Champ "Billable" : sélecteur user/company (auto-rempli depuis la sélection précédente, modifiable)
- Ressource (filtre par type)
- Créneau (avec validation selon règles par rôle du user "au nom de")
- Libellé optionnel
- Prix HT (auto-calculé selon ressource + rôle user, surchargeable par admin)
- Option "Réservation interne Ecoworking" : pas de billable, prix 0€, pas de notification email
- 🟡 Option "Récurrence" : créer une série de résa identiques sur N semaines

**Pour une résa au nom d'un `external` sur salle réunion** :
- L'admin peut soit consommer un ticket déjà acheté par l'external, soit l'enregistrer en facturation directe (la facture sera émise séparément)
- Aucune contrainte de créneau ouvré strict côté admin (super-pouvoir d'override)

#### 4.7.3 Édition / suppression

- L'admin peut modifier ou supprimer **n'importe quelle résa**
- Si modification ou suppression d'une résa créée par un membre : notification email automatique avec la raison
- Audit log obligatoire (qui, quoi, raison)

#### 4.7.4 Actions

- Annuler (avec raison, notification membre)
- Marquer no-show (a posteriori, stat d'usage)
- Rétablir un ticket consommé (si annulation avec restitution)
- Voir la résa dans Google Calendar
- Forcer un re-sync Google Calendar (si désynchronisation détectée)
- 🟡 Dupliquer (créer une nouvelle résa à partir des paramètres d'une existante)

### 4.8 Tickets, occupations bureau & présences

> Ce module gère deux choses :
> - Les **tickets** achetés par les externals (et exceptionnellement par d'autres) — 2 types : tickets bureau libre demi-journée, tickets salle réunion demi-journée
> - Les **occupations de bureau** au jour le jour (présence des résidents, additionals utilisant un bureau de leur entité absente, externals consommant un ticket bureau)

#### 4.8.1 Tickets

**Types de tickets en MVP**
- `desk_half_day` : ticket bureau libre demi-journée (pour external typiquement)
- `meeting_room_half_day_morning` : ticket salle de réunion matin 9h-13h (pour external)
- `meeting_room_half_day_afternoon` : ticket salle de réunion après-midi 14h-18h (pour external)

🟡 Architecture suggérée : tous les tickets dans une table commune `tickets` avec colonne `type`, pour simplicité de gestion (filtres, compteurs, consommation).

**Listing**
- Table : membre/external, type ticket, purchase parent, date d'expiration, statut (disponible / utilisé / expiré / annulé), date d'utilisation, ressource utilisée (si applicable)
- Filtres : membre, type, statut
- Recherche : nom membre, code purchase
- 🟡 Vue regroupée par membre avec compteurs par type ("X tickets bureau dispo / Y tickets salle dispo / Z tickets utilisés")

**Création (automatique via purchases)**
- Création automatique lors d'un `purchase` d'une offre `pack` ou `one_shot` ticket
- Tickets générés à partir du purchase selon l'offre (1 purchase pack de 10 tickets bureau → 10 tickets `desk_half_day`)
- L'admin peut créer un `purchase` manuel (cas : offre cadeau, compensation, ajustement)

**Actions admin (super-pouvoirs métier)**

*Consommer manuellement un ticket pour un user* — cas typique = un external se présente sur place, l'admin lui décompte un ticket à la volée :
1. Sélecteur de user → liste de ses tickets disponibles par type
2. Choix d'un ticket + date + période (matin / après-midi)
3. Si ticket bureau : pas de ressource précise (les bureaux libres sont attribués au coup par coup)
4. Si ticket salle de réunion : sélecteur de salle + créneau spécifique → création d'une `booking`
5. Crée l'occupation correspondante (occupation bureau ou booking salle)
6. Marque le ticket comme utilisé avec timestamp

*Autres actions* :
- Annuler une consommation (cas exceptionnel : erreur, no-show finalement) → ticket redevient disponible
- Étendre date d'expiration (geste commercial)
- Transférer un ticket à un autre user (V2, si politique le permet)

#### 4.8.2 Occupations de bureau

> Ce sous-module gère le suivi de **qui occupe quel bureau et quand**. Distinct des bookings (qui concernent les salles).

**Modèle de données suggéré** (à figer dans `data_model.md`) :

Table `desk_occupations` (ou équivalent) :
- `id`, `desk_id` (FK resource), `user_id` (FK user)
- `date`, `period` (morning / afternoon / full_day)
- `source` : `resident_default` (résident/staff sur son bureau attitré, présence par défaut), `external_ticket` (external avec ticket bureau consommé)
- `ticket_id` (FK nullable, si source = external_ticket)
- `status` : `present`, `absent`, `cancelled`

> Note : la source `additional_borrowing` est **abandonnée** — les `additional` se placent librement sur les bureaux des résidents de leur entité sans suivi explicite dans l'app.

Table `desk_absences` (nouvelle — pour les déclarations d'absence par résident/staff) :
- `id`, `desk_id` (FK), `user_id` (FK)
- `date_start`, `date_end`
- `period` (morning / afternoon / full_day)
- `recurrence_type` : `none` | `weekly` (jour de semaine récurrent)
- `recurrence_day_of_week` (nullable, 0=dim ... 6=sam si weekly)
- `notes`
- `created_at`, `updated_at`

L'expansion des récurrences se fait à la lecture (pas de pré-génération de N lignes).

**Listing occupations**
- Table : date, bureau, qui occupe, source, statut
- Filtres : date, bureau, source, statut, étage
- Recherche : nom user
- 🟡 Vue calendrier mensuel par bureau (qui a occupé quel bureau quel jour)

**Listing absences déclarées** (nouvel onglet)
- Table : résident/staff concerné, bureau, période, date début, date fin, récurrence
- Filtres : user, période active, type récurrence
- Tri : date début

**Création par l'admin (super-pouvoir métier)**

L'admin peut :
- Déclarer une **occupation** pour un `external` avec ticket bureau (consommation du ticket + ligne `desk_occupations` source=`external_ticket`)
- Déclarer une **absence** pour un `resident`/`staff` (cas : la manageuse pose les vacances de quelqu'un à sa demande)
- Modifier ou supprimer toute occupation ou absence existante (audit log obligatoire)

❓ **À trancher** : un `staff` peut-il occuper ponctuellement un autre bureau si son bureau est en maintenance ? (cas marginal — Q24 : par défaut bureau staff strictement réservé)

#### 4.8.3 Présences hors bureau

Pour info, les "réservations salles" sont gérées dans §4.7 (table `bookings`). Pas confondre avec les occupations bureau.

#### 4.8.4 Vue "Occupation du jour" (page Filament custom)

Page de référence quotidienne pour l'admin, accessible en un clic depuis le dashboard.

- Date affichée : aujourd'hui par défaut, sélecteur de date
- Plan des étages en synthèse (mini-aperçu cliquable)
- Sections :
  - **Bureaux attitrés** : par étage, indiquer pour chaque desk attitré le résident + statut (présent / absent / pas d'info), et s'il est utilisé par un additional ou un external
  - **Bureaux libres** : par étage, indiquer pour chaque desk libre l'external qui l'utilise (si applicable) ou "Disponible"
  - **Capacité bureaux libres restante** : nombre de bureaux libres non encore utilisés par un external aujourd'hui
  - **Réservations salles du jour** : liste chronologique avec ressource, créneau, qui, libellé, ticket consommé (si external)
  - **Walk-ins du jour** 🟡 : externals enregistrés ponctuellement (résa salle ou ticket bureau)
- Actions rapides depuis cette vue :
  - Marquer une absence d'un résident
  - Consommer un ticket pour un user (bureau ou salle)
  - Bloquer une salle pour usage interne
  - Créer une résa au nom de quelqu'un

> **Q7 tranchée** : les membres **ne déclarent pas** leur propre présence depuis le portail. La seule action de déclaration disponible côté membre est le module "Mes absences" (§3.4.6) pour libérer son bureau attitré. Pas de feature "présence prévisionnelle" pour usage interne (badge "café", prise de présence collective, etc.) — hors scope.

### 4.9 Factures & paiements

#### 4.9.1 Factures

**Listing**
- Table : numéro, billable (user ou company), date émission, date échéance, statut, total TTC, montant payé, montant dû
- Filtres : statut, période, billable, en retard
- Recherche : numéro, raison sociale billable
- Export CSV de tout le listing filtré
- 🟡 Bouton bulk "Marquer comme payées" avec sélecteur de date + méthode

**Création**
- Wizard :
  1. Choisir le billable (user ou company)
  2. Ajouter des lignes (manuel ou depuis abonnement/purchase/booking non encore facturé)
  3. Vérifier les totaux calculés
  4. Choisir date d'émission + date d'échéance
  5. Émettre (numérotation chronologique + verrouillage) ou enregistrer en brouillon

**Édition**
- Brouillon : édition libre
- Émise : édition **restreinte** (interdiction de modifier les montants, le numéro, le billable — uniquement notes admin et statut paiement)
- Annulation : génère un avoir (V2) ou modification de statut "cancelled" avec audit log

**Visualisation**
- Aperçu PDF dans Filament
- Téléchargement PDF
- Bouton "Renvoyer par email"
- 🟡 Bouton "Marquer comme envoyée" (statut intermédiaire)

#### 4.9.2 Paiements

**Listing**
- Table : facture associée, montant, date paiement, méthode (SEPA / virement / CB physique / chèque / espèces), référence, créé par
- Filtres : méthode, période

**Enregistrement**
- Action depuis une facture : "Enregistrer un paiement"
- Modal : montant (pré-rempli avec le solde dû), date, méthode, référence (optionnel), notes
- Mise à jour automatique du statut de la facture (payée si solde nul, partielle sinon)

**Édition / suppression**
- Possible avec confirmation et audit log

### 4.10 Documents (internes + administratifs)

#### 4.10.1 Documents internes

> Concept nouveau introduit par les specs : documents communs à tous les membres (charte, CGU, droit image)

**Listing**
- Table : titre, type (charte / CGU / droit image / autre), version, date de publication, nombre de validations / nombre de membres concernés
- Filtres : type, actif

**Édition**
- Formulaire : titre, type, version (string libre, ex. "v1.0"), corps du document (markdown ou WYSIWYG) OU upload PDF, date de publication, public concerné (tous / résidents / additional / billing_contact)
- 🟡 **Version** : changer la version invalide les validations précédentes → tous les membres concernés doivent re-valider

**Suivi des validations**
- Vue détail : tableau des membres avec colonne "Validé le DD/MM/YYYY" ou "Non validé"
- Possibilité de relancer par email les non-validés (bulk action)

**Suppression**
- Soft delete (conservation pour preuve historique des versions précédentes)

#### 4.10.2 Documents administratifs

> Documents propres à chaque entité juridique : contrats, avenants, contrat de domiciliation

**Listing**
- Table : titre, type (contrat / avenant / domiciliation / autre), entité rattachée, date, PDF
- Filtres : type, entité

**Création**
- Upload PDF + métadonnées (titre, type, date, entité rattachée)
- 🟡 Notification optionnelle au billing_contact de l'entité ("Un nouveau document est disponible")

**Édition**
- Modification métadonnées
- Remplacement du PDF (avec audit log)

**Suppression**
- Soft delete (conservation 10 ans pour conformité légale)

### 4.11 Annonces & événements

#### 4.11.1 Listing

- Table : titre, type (info / event / alert), date de publication, date événement (si applicable), nombre d'inscrits, statut (publié / brouillon / archivé)
- Filtres : type, statut, période
- Recherche : titre

#### 4.11.2 Édition

- Formulaire : titre, type, corps (markdown ou WYSIWYG), date début / date fin (pour events), lieu, max participants, requires_registration, date de publication, visibilité (tous / résidents / additional / billing_contact), image de couverture (upload via ImageKit)
- Brouillon → Publication (workflow)

#### 4.11.3 Suivi inscriptions

- Vue détail event : liste des inscrits + statut (inscrit / annulé / présent / no-show)
- Bulk actions : envoyer email aux inscrits, marquer présents

### 4.12 Plan des étages

> Module pour gérer l'**attribution bureau ↔ utilisateur**. Le SVG du plan est **statique, défini une fois pour toutes** (Q13 résolue) — il est versionné dans le repo et remplacé par un nouveau déploiement si le local est réagencé (cas rare).

#### 4.12.1 Visualisation

- Affichage du SVG des 2 étages (même map que côté portail)
- Chaque bureau a un id unique correspondant à une ressource `desk` en DB
- Couleur des blocs selon état d'attribution : `assigned_resident` (vert) / `assigned_staff` (bleu) / `unassigned` (gris) / hors service (rayé)

#### 4.12.2 Attribution

- Clic sur un bureau → modal "Attribuer ce bureau" :
  - Sélecteur du type : `assigned_resident` / `assigned_staff` / `unassigned`
  - Si `assigned_resident` ou `assigned_staff` : sélecteur de user (filtré par rôle approprié : `resident` ou `staff`)
- Désattribution : clic → "Libérer ce bureau" (passe en `unassigned`)
- Hors service : marquer un bureau temporairement non attribuable

#### 4.12.3 Cohérence SVG ↔ DB

- À chaque ouverture du module : vérification que tous les `data-desk-id` du SVG ont une ressource correspondante en DB, et inversement
- Avertissement si désynchronisation (ex. nouveau bureau ajouté en DB sans le SVG, ou bureau retiré du SVG)
- Pas d'outil d'édition du SVG dans l'app — modification via fichier SVG dans le repo + redéploiement

### 4.13 Rôles & permissions

- UI standard `spatie/laravel-permission` ou Filament Shield
- Création / édition de rôles
- Attribution de rôles aux users
- Permissions granulaires possibles (V2 si besoin)

### 4.14 Audit log

- Vue listing de toutes les actions tracées par `spatie/activitylog`
- Colonnes : date, user, action, modèle affecté, ID modèle, IP, changements (old / new)
- Filtres : user, modèle, action, période
- Recherche full-text dans les changements
- Export CSV
- 🟡 Lecture seule (aucune édition possible — c'est le principe d'un audit log)

### 4.15 Settings

- Configuration globale : nom du site, email contact, paramètres horaires salles, paramètres factures (préfixe numérotation, mentions légales, etc.)
- Gestion des credentials externes (Brevo API key, Google Calendar credentials, Sentry DSN)
- 🟡 Via `spatie/laravel-settings` ou pages Filament dédiées

---

## 5. Workflows transverses

### 5.1 Cycle de facturation mensuelle

> Workflow critique métier — à automatiser au max

**Trigger** : cron job mensuel (1er du mois, ou configurable par admin)

**Étapes** :
1. Pour chaque abonnement `status=active` avec `billing_day` ≤ jour courant :
   - Vérifier qu'aucune facture n'a déjà été émise pour ce mois sur cet abonnement (idempotence)
   - Créer une facture brouillon
   - Ajouter une ligne d'abonnement (snapshot prix de l'abonnement)
2. Notification admin : "X factures brouillon générées, à valider"
3. Admin valide en bulk depuis Filament (action "Émettre toutes")
4. Émission → numérotation chronologique + PDF généré + statut "sent" + email au billing contact

**Edge cases** :
- Abonnement commencé en milieu de mois : **prorata calculé** (Q9 résolue — règle ci-dessous)
- Abonnement résilié en milieu de mois : **prorata calculé** identique
- Pause d'abonnement : pas de facturation pendant la pause

#### Règle de prorata

**Formule par défaut** (à valider) :
```
montant_proratisé = montant_mensuel_HT × (jours_consommés / jours_total_du_mois)
```

Où :
- `jours_consommés` = nombre de jours calendaires effectivement couverts par l'abonnement dans le mois
- `jours_total_du_mois` = nombre de jours calendaires du mois concerné (28, 29, 30 ou 31)
- Arrondi : 2 décimales selon les règles standards (`ROUND_HALF_UP`)

**Exemples** :
- Abonnement à 300€/mois démarré le 15 d'un mois de 30 jours → facturé 300 × 16/30 = 160€ (16 jours = du 15 au 30 inclus)
- Abonnement à 300€/mois résilié le 10 d'un mois de 31 jours → facturé 300 × 10/31 = 96,77€ (10 jours = du 1 au 10 inclus)

**Convention de comptage** :
- Le jour de début est inclus, le jour de fin est inclus
- 🟡 Convention à valider : faut-il exclure le jour de fin (date de résiliation effective vs date de dernière utilisation) ?

> ❓ **À valider précisément avec expert-comptable** :
> - Convention exacte de comptage des jours (inclusion/exclusion bornes)
> - Arrondi (au centime ? à l'euro ?)
> - TVA appliquée sur le montant proratisé (oui par défaut)

### 5.2 Création d'un nouveau membre

**Trigger** : admin crée un nouveau membre dans Filament

**Étapes** :
1. Création User + MemberProfile + (optionnel) Subscription
2. Génération d'un token de définition de mot de passe (expire 24h)
3. Email d'accueil envoyé : "Bienvenue chez Ecoworking — définir votre mot de passe"
4. Au premier login, le membre est invité à :
   - Compléter son profil (photo, bio, etc.)
   - Valider les documents internes en cours
   - Accepter les opt-ins (annuaire, newsletter)
5. Audit log

### 5.3 Validation des documents internes

> Workflow nouveau introduit par les specs

**Trigger** : admin publie un nouveau document interne (ou nouvelle version)

**Étapes** :
1. Document créé en DB avec version, audience cible
2. Tous les membres concernés voient le doc apparaître dans le bloc "Documents à valider" sur leur accueil portail
3. 🟡 Notification email au membre lors de la publication
4. Membre télécharge le doc, le lit, clique "Valider"
5. Création d'un enregistrement `member_document_validations` (user_id, document_id, version, validated_at, ip)
6. Le doc disparaît de "Documents à valider" pour ce membre (sauf si nouvelle version)

### 5.4 Gestion conflit de réservation

> Cas typique de race condition

**Scénario** : deux membres tentent de réserver la même salle au même créneau, en même temps.

**Stratégie** :
1. À la création d'une résa, transaction DB avec `lockForUpdate()` sur les résa de la ressource concernée chevauchant le créneau demandé
2. Si chevauchement détecté : abort transaction + erreur 409 Conflict
3. Côté front : message clair + suggestion d'un créneau alternatif proche (calculé automatiquement)
4. Le membre relance la résa avec le nouveau créneau

### 5.5 Synchronisation Google Calendar

**Trigger** : création / modification / suppression d'une résa salle

**Étapes** :
1. Job `SyncBookingToGoogleCalendarJob` dispatché en queue
2. Le job appelle Google Calendar API :
   - Si création : crée un event, stocke `google_calendar_event_id` dans la booking
   - Si modification : update l'event via son ID
   - Si suppression : delete l'event via son ID
3. Retry 3 fois avec backoff exponentiel en cas d'erreur API
4. Si échec définitif : log Sentry + notification admin

**Politique** :
- Sync sortante uniquement (Google → DB ignoré)
- Calendrier public iCal partagé en lecture seule
- Aucune modif manuelle attendue côté Google Calendar

### 5.6 Anonymisation RGPD d'un membre

> Workflow pour le droit à l'oubli — préservation des données comptables

**Trigger** : demande explicite du membre OU décision admin

**Étapes** :
1. Confirmation par l'admin (action critique)
2. Pour l'utilisateur visé :
   - `users.email` → hash déterministe (ex. `deleted-<hash>@ecoworking.invalid`)
   - `users.first_name` / `last_name` → "Utilisateur"
   - `member_profile.bio`, `linkedin_url`, etc. → NULL
   - `member_profile.birth_date` → NULL
   - Photo de profil supprimée du storage
   - 2FA secrets supprimés
   - Audit log entry : "Membre anonymisé le DD/MM/YYYY"
3. Données conservées (obligation légale) :
   - Factures (avec billable_type/id pointant sur l'utilisateur anonymisé)
   - Lignes de paiement
   - Logs d'activité
4. Soft delete du user → impossible de se reconnecter

### 5.7 Génération PDF d'une facture

**Trigger** : émission d'une facture (passage brouillon → émise)

**Étapes** :
1. Numérotation atomique via `InvoiceNumberingService` (lock DB)
2. Génération PDF via `react-pdf/renderer` ou `dompdf` (à figer cf. BRIEF)
3. Stockage sur Cellar (S3) : `invoices/YYYY/EW-YYYY-NNNNN.pdf`
4. Snapshot adresse facturation à l'émission (au cas où l'adresse de l'entité change ensuite)
5. Job `SendInvoiceEmailJob` dispatché → email avec PDF en attachment au billing_contact
6. Statut → "sent"

---

## 6. Règles métier

### 6.1 Réservations & occupations

#### Salles de réunion (3 unités)

**Créneaux pour `resident` et `additional`** :
- Réservation libre : créneau de durée libre, plage horaire libre
- Disponibilité : **24/24 7/7**
- **Aucune limite ni fair use** (à confirmer en condition réelle, restera ajustable si abus)
- Gratuit (couvert par l'abonnement)

**Créneaux pour `external`** :
- Demi-journée **matin 9h-13h** OU **après-midi 14h-18h** (créneaux figés, non personnalisables)
- Jours ouvrés uniquement (L-V hors fériés français)
- Payant via ticket pré-acheté (cf. §6.3)

**Restrictions communes** :
- Conflit serveur : pas de double-booking sur la même salle
- 🟡 Annulation possible jusqu'à 1h avant le créneau (à figer)
- Audit log obligatoire en cas de suppression tardive

#### Salle event (1 unité)

- Réservable **uniquement par les admins**
- Plage horaire libre 24/24 7/7
- Tarification : variable selon le contexte (interne Ecoworking gratuit, sinon facturé selon devis admin)

#### Bureaux (48 unités au total)

Répartition :
- 1-2 bureaux attitrés au personnel Ecoworking (manageuse + stagiaire/alternant éventuel) — type `assigned_staff`
- ~40-50 bureaux attitrés aux résidents — type `assigned_resident`
- Les bureaux restants (= 48 - staff - résidents avec abonnement) sont libres — type `unassigned`

Pas de "réservation" classique — gestion par **occupation** et **absence** :

- **`resident`** : occupation **implicite** de son bureau attitré tous les jours ouvrés (pas de déclaration quotidienne). Peut **marquer son bureau vacant** sur des dates précises ou en récurrence (cf. §3.4.6) pour libérer la place
- **`staff`** : occupation **implicite** de son bureau attitré tous les jours ouvrés. Peut aussi marquer son bureau vacant. Bureau **non utilisable** par d'autres en cas d'absence (sauf cas marginal admin)
- **`additional`** : utilise librement les bureaux des résidents de son entité juridique. **Aucun suivi explicite dans l'app** (placement informel)
- **`external`** : achète un ticket bureau libre demi-journée, occupe un bureau `unassigned` disponible le jour J. Pas de choix précis du bureau (attribution au coup par coup)

**Calcul de la disponibilité bureau pour external** :
```
nb_bureaux_libres_jour_J = COUNT(resources WHERE type=desk AND assignment=unassigned AND active=true)
nb_externals_jour_J = COUNT(desk_occupations WHERE date=J AND source=external_ticket AND status=present)
dispo_external = nb_bureaux_libres_jour_J - nb_externals_jour_J
```

> 🟡 **Optionnel** : l'admin peut, dans des cas tendus (tous les bureaux libres pris), réutiliser des bureaux `assigned_resident` dont le résident a déclaré une absence ce jour-là pour placer un external supplémentaire. À discrétion admin uniquement (l'external ne réserve pas ces bureaux par lui-même).

### 6.2 Facturation

**Numérotation**
- Format : `EW-YYYY-NNNNN` (ex. `EW-2026-00042`)
- Compteur réinitialisé chaque année
- Chronologique strict, sans trou (CGI art. 289)
- Une facture brouillon ne consomme pas le compteur — seulement à l'émission définitive

**Date d'échéance par défaut** :
- 🟡 30 jours après émission (configurable par admin)

**Statuts**
- `draft` : brouillon, modifiable, non comptabilisée
- `sent` : émise, envoyée au client, en attente de paiement
- `paid` : intégralement payée
- `partially_paid` : partiellement payée
- `overdue` : en retard (passe l'échéance, automatique via cron)
- `cancelled` : annulée (avoir nécessaire pour conformité — V2)

### 6.3 Tickets

#### Types de tickets

| Type code | Usage | Format vendable | Prix unitaire HT |
|---|---|---|---|
| `desk_half_day` | 1 demi-journée bureau libre | à l'unité, pack 2 (réduit), pack 10 (réduit++) | 🟡 à figer |
| `meeting_room_half_day_morning` | 1 créneau matin 9h-13h sur 1 salle réunion | à l'unité, pack 🟡 | 🟡 à figer |
| `meeting_room_half_day_afternoon` | 1 créneau après-midi 14h-18h sur 1 salle réunion | à l'unité, pack 🟡 | 🟡 à figer |

> ❓ **À figer** : prix unitaires + structure des packs (nombre de tickets, dégressivité).

#### Éligibilité à l'achat

- **Seuls les `external` peuvent acheter des tickets** côté portail (Q21 tranchée).
- L'admin peut créer un purchase manuel au nom de n'importe quel user (cas exceptionnel : offre cadeau, compensation, achat staff/resident pour usage interne).
- Un `resident`/`additional`/`staff` qui souhaite inviter ponctuellement un tiers doit passer par l'admin.

#### Validité

- 🟡 Par défaut : **12 mois après l'achat** (configurable par offre)

#### Consommation

- Un ticket consommé crée :
  - Pour `desk_half_day` : une ligne `desk_occupations` avec source = `external_ticket`
  - Pour `meeting_room_half_day_*` : une ligne `bookings` sur une salle de réunion + créneau correspondant

#### Annulation et restitution (Q22 tranchée)

- L'external peut annuler une résa de salle de réunion (consommée via ticket) **jusqu'à l'heure de début** du créneau
- L'annulation dans le délai **restitue automatiquement le ticket** au statut `available`, avec `consumed_at` effacé
- Au-delà du début du créneau : annulation impossible côté portail → contact admin uniquement
- L'admin peut, en back-office, annuler une résa et choisir de restituer ou non le ticket (à discrétion)

#### Cessibilité

- **Tickets non cessibles** (Q8 tranchée). Un ticket appartient au user qui l'a acheté, et ne peut être transféré à un autre user.
- Cas exceptionnel : l'admin peut annuler un ticket et rembourser/remplacer manuellement si politique commerciale le justifie (cas marginal, traité au cas par cas).

### 6.4 Abonnements

**Unicité**
- Un user ne peut avoir qu'un seul `subscription` `status=active` à la fois
- Exception : un user peut être lié comme `additional` à un abonnement d'entreprise dont il n'est pas le souscripteur direct

**Cycle de vie**
- Création → active → (paused | ended | cancelled)
- Pause : facturation suspendue temporairement
- Ended : terminé naturellement (date de fin atteinte)
- Cancelled : résilié anticipé

---

## 7. Questions ouvertes

### 7.1 Périmètre fonctionnel

| # | Question | Impact | Statut |
|---|---|---|---|
| 1 | ~~Règles précises de réservation par rôle~~ | Moyen | ✅ Résolue (cf. §6.1) |
| 2 | ~~Statut admin_billing~~ | Faible | ✅ Résolue (supprimé) |
| 3 | ~~Membres externes : compte portail ou pas~~ | Moyen | ✅ Résolue (oui, portail) |
| 4 | ~~Annuaire : nom du réserveur visible aux autres membres~~ | Faible | ✅ Résolue (visible : transparence par défaut) |
| 5 | ~~Centre de notifications persistant ou toasts seuls~~ | Faible | ✅ Résolue (persistant + toasts, cf. §3.8.4) |
| 6 | ~~Magic link en V1 ou V2~~ | Faible | ✅ Résolue (V1) |
| 7 | ~~Membres peuvent-ils déclarer leur propre présence/absence~~ | Moyen | ✅ Résolue (non, seul module "Mes absences" §3.4.6 pour libérer bureau) |
| 8 | ~~Cessibilité des tickets entre users~~ | Faible | ✅ Résolue (non, jamais cessible) |
| 9 | ~~Prorata abonnement en milieu de mois~~ | Moyen | ✅ Résolue (jours_consommés / jours_total_mois — cf. §5.1) |
| 10 | ~~Plage horaire d'ouverture salles~~ | Moyen | ✅ Résolue (24/24 7/7 resident/additional, 9h-18h jours ouvrés external) |
| 11 | ~~Markdown vs texte brut pour la présentation profil~~ | Faible | ✅ Résolue (markdown) |
| 12 | ~~Bureaux admin Ecoworking sur le plan annuaire~~ | Faible | ✅ Résolue (visibles si admin cumule staff/resident, sinon hors plan) |
| 13 | ~~Format SVG plan : statique vs éditable par admin~~ | Moyen | ✅ Résolue (statique, défini une fois pour toutes) |
| 14 | ~~Résidents : présence implicite ou déclaration explicite ?~~ | Moyen | ✅ Résolue (implicite + module "Mes absences" §3.4.6) |
| 15 | ~~Visibilité du calendrier pour `external`~~ | Faible | ✅ Résolue (oui pour salles réunion + event) |
| 16 | ~~Annuaire visible pour `external`~~ | Faible | ✅ Résolue (non) |
| 17 | ~~`additional` bureau préférentiel ?~~ | Faible | ✅ Résolue (pas de suivi, placement libre informel) |
| 18 | ~~Documents internes applicables à `external`~~ | Faible | ✅ Résolue (oui, même obligation de validation que les autres rôles) |
| 19 | ~~Modélisation entité juridique d'un `external` particulier~~ | Moyen — DB | ✅ Résolue (type `individual` vs `company` sur entité) |
| 20 | Tarifs unitaires et structure des packs de tickets | Moyen | Ouverte (à figer avec Guillaume) |
| 21 | ~~Achat de tickets bureau par resident/additional/staff~~ | Faible | ✅ Résolue (non, external uniquement — admin manuel pour les cas exceptionnels) |
| 22 | ~~Annulation de résa external avec ticket~~ | Faible | ✅ Résolue (annulation possible jusqu'à l'heure de début, restitution auto du ticket) |
| 23 | ~~Statut technique stagiaire/alternant~~ | Moyen | ✅ Résolue (rôle `staff` XOR avec resident/additional/external, pas de gestion facturation) |
| 24 | Bureau `assigned_staff` utilisable par d'autres en cas d'absence du staff | Faible | ✅ Résolue (strictement réservé sauf cas marginal admin) |
| 25 | Notification automatique admin lors d'enregistrement d'une absence longue (> N jours) ? | Faible | Ouverte |
| 26 | Facturation BtoC (`individual`) : règles spécifiques vs BtoB (notamment Factur-X 2027) | Moyen — facturation | Ouverte (à valider avec expert-comptable) |

### 7.2 UI/UX

| # | Question | Impact | Statut |
|---|---|---|---|
| 1 | ~~Codes hexa exacts vert + violet Ecoworking~~ | Faible | ⏳ À fournir par Guillaume (issus du logo existant) |
| 2 | ~~Identité graphique ad hoc vs réutilisation ecoworking.fr~~ | Faible | ✅ Résolue (réutilisation existant) |
| 3 | ~~Police de caractère~~ | Faible | ✅ Résolue (Inter) |
| 4 | Ordre exact des blocs accueil sur mobile vs desktop | Faible | Ouverte |
| 5 | Wireframes à produire par qui (Guillaume + outil ? Figma ? itérations Claude ?) | Moyen | Ouverte |

### 7.3 Workflows

| # | Question | Impact |
|---|---|---|
| 1 | Notification email à chaque publication de document interne (oui par défaut ?) | Faible |
| 2 | Cron facturation : lancement auto fin de mois, ou trigger manuel admin | Moyen |
| 3 | Workflow avoir (V2) : génération automatique sur annulation ou manuel | Moyen |

### 7.4 Technique

| # | Question | Impact |
|---|---|---|
| 1 | Photo profil : ImageKit ou Cellar direct | Faible |
| 2 | Stockage SVG plan : versionné dans le repo ou en DB | Faible |
| 3 | Génération PDF facture : react-pdf vs dompdf vs browsershot (V2) | Moyen |

---

## Notes finales

Ce PRD est une **première version** à itérer. Les sections marquées 🟡 et ❓ doivent être résolues progressivement, idéalement avant d'attaquer le module concerné dans le dev.

**Prochaines étapes suggérées** :
1. Guillaume relit et valide / corrige les ajouts 🟡
2. Guillaume tranche les questions ❓ critiques (notamment §6 règles métier réservations)
3. Définition du modèle de données détaillé (`docs/data_model.md`) — peut être fait en parallèle
4. Wireframes du portail (a minima les écrans principaux : accueil, calendrier résa, annuaire) — peut être fait par Guillaume avec un outil comme Figma ou itéré en HTML directement dans Claude Code
5. Plan de migration des données Cosoft

*Document généré avec Claude — itéré avec Guillaume.*
