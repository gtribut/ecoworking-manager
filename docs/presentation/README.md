# Ecoworking — présentation de l'outil

> Outil de gestion sur-mesure de l'espace de coworking **Ecoworking** (Lyon, SARL, ~50 résidents, ~75 entreprises).
> Il remplace Cosoft et couvre tout le quotidien : membres et entreprises, abonnements, facturation, réservations de salles, bureaux nomades, présence, documents et communication.
>
> Ce document donne une vision **fonctionnelle et visuelle** complète en une lecture rapide. Les captures sont prises sur un jeu de données de démonstration (personnes et entreprises fictives).
> Version PDF : [`ecoworking-presentation.pdf`](./ecoworking-presentation.pdf). État : MVP complet et testé, mise en production à venir (V1.5).

---

## 1. Deux applications, une seule base

| | Portail membre | Back-office |
|---|---|---|
| **Pour qui** | Les coworkers et leurs contacts facturation | L'équipe Ecoworking |
| **URL** | `portail.ecoworking.fr` | `admin.ecoworking.fr` |
| **Usage** | Réserver, consulter ses factures et documents, déclarer ses absences, s'informer | Gérer membres, entités, catalogue, facturation, espaces, communication |
| **Connexion** | Email + mot de passe, lien magique par email, 2FA optionnelle | Email + mot de passe ou Google (domaine ecoworking.fr), 2FA obligatoire |
| **Écran** | Responsive, desktop et mobile | Desktop |

Les deux applications partagent la même base de données : une réservation faite par un membre apparaît immédiatement dans le back-office, une facture émise par l'équipe apparaît immédiatement dans le portail.

### Rôles

| Rôle | Qui | Ce que ça ouvre |
|---|---|---|
| `resident` | Abonné avec bureau attitré | Salles gratuites 24/7, présence et absences, annuaire |
| `additional` | Personne supplémentaire d'une entreprise abonnée | Comme résident, sans bureau attitré ni présence |
| `external` | Nomade sans abonnement | Tickets demi-journée pour bureau nomade et salles, pas d'annuaire |
| `staff` | Équipe Ecoworking | Droits résident, pas de facturation |
| `billing_contact` | Contact facturation d'une entité (cumulable) | Module Administratif : factures, documents de l'entité, fiche entreprise |
| `admin` | Équipe Ecoworking | Back-office complet, peut agir au nom d'un membre |

### Offres (catalogue, HT)

Bureau résident 328,50 €/mois · personne supplémentaire 59 €/mois · domiciliation 35 €/mois · ticket bureau nomade demi-journée 17,50 € (packs 2 et 10) · ticket salle de réunion demi-journée 71 € (pack 10). Pas de paiement en ligne au MVP : les tickets sont crédités par l'équipe, l'encaissement se fait hors outil.

---

## 2. Portail membre

### Connexion

![Connexion](screenshots/portail/01-login.png)

- Email + mot de passe, ou **lien de connexion par email** (usage unique, 15 min).
- Mot de passe oublié, 2FA TOTP optionnelle avec codes de récupération.
- Email d'accueil à la création du compte pour définir son mot de passe.

### Accueil

![Accueil résidente](screenshots/portail/02-dashboard.png)

- Tuiles selon le rôle : prochaine réservation, bureau attitré, tickets restants, documents à valider, dernière facture.
- Prochaines réservations et actualités Ecoworking côte à côte.
- Dernières factures pour le contact facturation uniquement.

Vue d'un nomade (`external`) : les tuiles changent, les tickets remplacent le bureau attitré.

![Accueil nomade](screenshots/portail/12-dashboard-nomade.png)

### Réservations de salles

![Réservations](screenshots/portail/03-reservations.png)

- Agenda multi-salles jour / semaine, filtre par salle, bascule 8h-20h / 24h.
- Réservation par cliquer-glisser ou par formulaire (journée, demi-journée, créneau libre), modification et annulation jusqu'à l'heure de début.
- Une réservation d'autrui affiche le nom du réserveur et son entité. La salle événementielle est sur demande.
- Liste « Mes réservations » (alternative accessible) et **liens iCal** à ajouter dans Google Agenda ou Apple Calendar.
- Nomades : demi-journées en jours ouvrés, 1 ticket consommé, restitué en cas d'annulation.

### Tickets & bureaux nomades (nomades)

![Tickets](screenshots/portail/11-tickets.png)

- Soldes bureau / salle et détail de chaque ticket (disponible, utilisé, restitué).
- Réservation d'un bureau nomade : date, période, choix parmi les bureaux libres sur le plan.
- Annulation = ticket restitué automatiquement. Sans ticket, message pour contacter l'équipe.

### Présence (titulaires d'un bureau)

![Présence](screenshots/portail/08-presence.png)

- Présent par défaut tous les jours ; l'absence se déclare : jour, plage de dates ou récurrence hebdomadaire, matin / après-midi / journée.
- Une absence libère le bureau pour les nomades ce jour-là et notifie l'équipe.

### Annuaire et plan des étages

![Annuaire](screenshots/portail/06-annuaire.png)

![Plan des étages](screenshots/portail/07-plan-etages.png)

- Annuaire **opt-in** : photo, entité, fonction, présentation, centres d'intérêt, liens. Jamais d'email ni de téléphone.
- Plan interactif des 2 étages (49 bureaux) à la date choisie : occupé, absent, nomade, équipe, libre, hors service, présence partielle.
- Équivalent texte synchronisé sous le plan (accessibilité).

### Actualités

![Actualités](screenshots/portail/09-annonces.png)

- Informations, alertes et événements publiés par l'équipe, filtrés par audience.
- Inscription / désinscription aux événements avec jauge de places.

### Documents

![Documents](screenshots/portail/05-documents.png)

- Documents communs à **valider** (charte, CGU, droit à l'image) : lecture PDF puis validation datée. Une nouvelle version redemande la validation.
- Documents de l'entité (contrats, avenants, domiciliation) visibles par le contact facturation.

### Mes factures (contact facturation)

![Factures](screenshots/portail/04-factures.png)

- Factures émises uniquement : numéro, statut (payée, en attente, partielle, en retard, annulée), PDF, filtres et recherche.
- Fiche « Mon entreprise » : raison sociale, SIRET, TVA, adresse, mode de paiement, 4 derniers chiffres de l'IBAN, demande de modification par email.

### Mon profil

![Profil](screenshots/portail/10-profil.png)

- Profil public (photo, fonction, présentation), compte (mot de passe, 2FA), préférences (thème clair / sombre, notifications, opt-in annuaire et newsletter), récap de l'entité.

### Transverse

- Cloche de notifications : facture émise ou en retard, document publié, annonce, réservation ou absence modifiée par l'équipe.
- Pages publiques : mentions légales, CGU, déclaration d'accessibilité.
- Mode sombre, états vides et hors-ligne, 404 et accès refusé dédiés.

### Mobile

Sidebar remplacée par une barre d'onglets ; mêmes fonctions.

| Accueil | Réservations | Factures |
|---|---|---|
| ![Accueil mobile](screenshots/portail/m1-dashboard-mobile.png) | ![Réservations mobile](screenshots/portail/m2-reservations-mobile.png) | ![Factures mobile](screenshots/portail/m3-factures-mobile.png) |

---

## 3. Back-office

### Tableau de bord

![Tableau de bord](screenshots/admin/01-dashboard.png)

- KPIs : membres actifs, abonnements actifs, factures en retard et impayé, CA émis du mois vs mois précédent, occupation des salles.
- Widgets : factures en retard, abonnements se terminant sous 30 jours, taux de validation des documents, réservations et bureaux nomades du jour, nouveaux membres, absences déclarées par les membres, activité récente.
- Recherche globale en haut de page.

### Espaces & réservations

![Occupation du jour](screenshots/admin/02-occupation-du-jour.png)

- **Occupation du jour** : par étage, bureaux attitrés (présent / absent), bureaux libres et leur occupant nomade, capacité restante, salles réservées.
- **Espaces** : bureaux (étage, attribution résident / équipe / libre), salles de réunion, salle événementielle, horaires et tarifs.
- **Réservations** : toutes les réservations de salles, création au nom de n'importe quel membre.

![Réservations](screenshots/admin/03-reservations.png)

- **Occupations bureaux** et **Absences bureaux** : suivi jour par jour, saisie possible par l'équipe.

### Membres & entités

![Comptes](screenshots/admin/04-comptes.png)

- **Comptes** : rôles, mot de passe, renvoi de l'email d'accueil, **anonymisation RGPD** (les factures restent, les données personnelles disparaissent).
- **Entités** : entreprise ou particulier, coordonnées de facturation, mandat SEPA (4 derniers chiffres), remise négociée, notes internes, onglets Contacts et Membres.

![Entités](screenshots/admin/05-entites.png)

![Fiche entité](screenshots/admin/06-entite-detail.png)

- **Profils membres** : entité de rattachement, bureau attitré, date d'arrivée, statut (actif, en pause…).
- **Contacts** : contacts facturation / direction / technique d'une entité.

### Catalogue & ventes

![Catalogue](screenshots/admin/07-catalogue.png)

- **Catalogue** : offres récurrentes, ponctuelles et packs de tickets, prix HT, TVA, actif / public. Un changement de prix s'applique à la facturation suivante de tous les abonnés.
- **Abonnements** : souscripteur (membre ou entité pour la domiciliation), statut actif / en pause / terminé / résilié, jour de facturation.

![Abonnements](screenshots/admin/08-abonnements.png)

- **Achats ponctuels** : prix figé à l'achat.
- **Tickets** : crédit manuel (quantité, motif) et consommation manuelle pour un membre.

### Facturation

![Factures](screenshots/admin/09-factures.png)

- Brouillons générés automatiquement le 1er du mois (une facture par entité, lignes regroupées, prorata séparé), modifiables.
- **Émettre** : numéro chronologique sans trou `EW-AAAA-NNNNN`, montants figés, PDF, email au contact facturation.
- **Annuler + avoir** : une facture émise ne se supprime jamais, elle s'annule avec un avoir miroir.

![Détail facture](screenshots/admin/10-facture-detail.png)

- **Paiements** : saisie par facture (SEPA, virement, CB, chèque, espèces), statut recalculé automatiquement (payée, partielle). Passage en retard automatique à l'échéance.

![Paiements](screenshots/admin/11-paiements.png)

### Communication & documents

![Annonces](screenshots/admin/12-annonces.png)

- **Annonces** : info / événement / alerte, audience, jauge et inscriptions, brouillon puis publication avec notification aux membres.
- **Documents communs** : versionnés (charte, CGU, droit à l'image), une nouvelle version invalide les validations.
- **Documents entités** : PDF rattachés à une entité (contrat, avenant, domiciliation).

![Documents entités](screenshots/admin/13-documents-entites.png)

### Système

![Audit log](screenshots/admin/14-audit-log.png)

- **Audit log** : qui a modifié quoi et quand sur les entités sensibles (comptes, entités, factures, abonnements, réservations, paiements), avec le détail des champs.
- **Rôles & permissions** : matrice en lecture seule des droits réels.

![Rôles & permissions](screenshots/admin/15-roles-permissions.png)

---

## 4. Workflows clés

**Arrivée d'un membre**
1. L'équipe crée l'entité, le compte (rôle), le profil (bureau attitré) et l'abonnement.
2. Le membre reçoit un email d'accueil et définit son mot de passe.
3. À la première connexion : documents communs à valider, profil et préférences à compléter.

**Cycle de facturation mensuel**
1. Le 1er du mois à 6h : une facture brouillon par entité, calculée sur le catalogue courant et la remise négociée.
2. L'équipe vérifie puis **émet** : numéro, PDF, email, notification portail.
3. Paiement saisi à réception. Chaque jour à 7h, les factures échues passent « en retard » et le contact est notifié.
4. Erreur sur une facture émise : annulation + avoir, puis nouvelle facture.

**Réservation d'une salle**
1. Le membre choisit un créneau libre ; les conflits sont refusés côté serveur.
2. Résident, additionnel, équipe : gratuit, sans quota. Nomade : demi-journée en jour ouvré, 1 ticket.
3. Annulation possible jusqu'au début ; le ticket est restitué.

**Départ d'un membre** : abonnement terminé (prorata sur la dernière facture), puis anonymisation du compte. Factures et paiements conservés 10 ans.

**Tâches planifiées** : génération des brouillons (1er du mois 6h), passage en retard (quotidien 7h), purge des notifications à 90 jours (quotidien 4h). Chacune signale son exécution à Healthchecks.io.

---

## 5. Sous le capot

**Architecture** : une seule application Laravel déployée une fois, qui répond sur deux sous-domaines. Le back-office est un panel Filament. Le portail est une SPA React servie par Laravel, qui parle à une API REST sur la même origine (pas de CORS). Cookies isolés par sous-domaine : la session du portail ne donne jamais accès à l'admin.

| Couche | Choix |
|---|---|
| Backend | PHP 8.5, Laravel 13, PostgreSQL 18 (données, cache, sessions et files d'attente : pas de Redis) |
| Back-office | Filament 5, 2FA TOTP native obligatoire, connexion Google restreinte au domaine |
| Portail | React 19, TypeScript strict, Vite 8, React Router 7, TanStack Query 5, Tailwind 4, shadcn/ui, FullCalendar 7 |
| Auth | Fortify (sessions, 2FA, reset) et Sanctum en mode SPA (cookies + CSRF) |
| PDF / email | dompdf en tâche de fond, Brevo |
| Observabilité | Sentry (back et front), Better Stack (`/up`), Healthchecks.io (crons), Laravel Pulse |
| Hébergement | Clever Cloud Paris : PHP + PostgreSQL + Cellar S3, environ 30 €/mois |
| Tests | Pest, Vitest, Playwright, axe-core, Pint, Biome, CI GitHub Actions |

**Sécurité et données**
- Une Policy par modèle : chaque accès portail est scopé à l'utilisateur ou à son entité, avec tests d'isolation membre A / membre B.
- Validation serveur systématique (Form Requests) doublée côté client (Zod).
- Audit log sur comptes, entités, factures, abonnements, réservations, paiements, absences.
- RGPD : IBAN jamais stocké en clair, anonymisation avec conservation comptable, purge des notifications, pas de données personnelles dans les logs, polices auto-hébergées.
- Facturation : montants en décimal, calculs serveur, numérotation sans trou sous verrou, montants figés à l'émission, suppression d'une facture émise impossible.

**Accessibilité** : cible RGAA 4.1 niveau AA. Audits axe-core en clair et en sombre sans violation, Lighthouse accessibilité 100/100 sur les écrans clés, alternatives accessibles pour l'agenda et le plan SVG.

**Qualité (au 17/09/2026)** : 668 tests Pest, 288 tests Vitest, 52 tests navigateur Playwright dont 26 audits d'accessibilité. Lint et tests bloquants en CI.

**Modèle de données (~25 tables)**
- Identité : `users`, `member_profiles`, `companies`, `contacts`, `consents`
- Contrats : `offers`, `subscriptions`, `purchases`, `tickets`
- Espaces : `resources`, `bookings`, `desk_occupations`, `desk_absences`
- Facturation : `invoices`, `invoice_lines`, `invoice_line_subscriptions`, `payments`, `invoice_counters`
- Communication : `announcements`, `announcement_registrations`, `internal_documents`, `member_document_validations`, `administrative_documents`

Détails : [`BRIEF.md`](../BRIEF.md) (technique), [`PRD.md`](../PRD.md) (fonctionnel), [`data_model.md`](../data_model.md), [`adr/`](../adr/).

---

## 6. Hors périmètre actuel

- Mise en production Clever Cloud et import des données Cosoft (V1.5).
- Synchronisation sortante Google Calendar (V1.5) : les liens iCal existent déjà.
- Sauvegardes externalisées, en-têtes CSP / HSTS, PWA (V1.5).
- Paiement en ligne et achat de tickets en libre-service (V2).
- Facturation électronique Factur-X (V2, échéance 2027).
- Choix assumés : pas de vérification d'abonnement actif à la réservation d'une salle, pas d'écran de paramètres admin, matrice des rôles en lecture seule.

---

## 7. Régénérer ce document

Captures et PDF sont produits par script sur une base Postgres dédiée `presentation`, reseedée avec le jeu de démonstration (dates relatives au jour de la capture). La base de développement n'est pas touchée.

```bash
scripts/presentation/build.sh            # captures + PDF
scripts/presentation/build.sh --pdf-only # PDF seul, après édition du Markdown
```

Prérequis : conteneurs Sail démarrés et navigateurs Playwright installés (`scripts/e2e/install.sh`). Sources : `portal-spa/presentation/` (parcours de capture, connexion admin 2FA avec un secret TOTP de démonstration, génération du PDF).
