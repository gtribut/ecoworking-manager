# Architecture Decision Records (ADR)

Ce dossier contient les ADRs documentant les décisions structurantes du projet Ecoworking.

## Qu'est-ce qu'un ADR ?

Un **Architecture Decision Record** est un document court (1-2 pages) qui capture une décision technique structurante au moment où elle est prise, avec son **contexte**, ses **alternatives considérées**, et ses **conséquences assumées**. L'objectif est de répondre dans 6 mois ou 2 ans à la question *"pourquoi on a fait ça déjà ?"*.

Format inspiré de la convention popularisée par Michael Nygard.

## Index

| N° | Titre | Statut | Date |
|---|---|---|---|
| [0001](./0001-choix-laravel-11.md) | Choix de Laravel (initialement 11, mis à jour vers 13) comme backend | Accepté | 2026-05 |
| [0002](./0002-architecture-hybride-filament-spa.md) | Architecture hybride Filament admin + SPA React portail | Accepté | 2026-05 |
| [0003](./0003-auth-portail-sanctum-spa-mode.md) | Sanctum mode SPA pour l'auth du portail membre | Accepté | 2026-05 |
| [0004](./0004-sous-domaines-admin-portail.md) | Deux sous-domaines `admin` et `portail` sur un seul déploiement | Accepté | 2026-05 |
| [0005](./0005-strategie-facturation-electronique.md) | Stratégie B pour la facturation électronique | Accepté | 2026-05 |
| [0006](./0006-routing-et-data-fetching-portail.md) | Stack data fetching et routing du portail (React Router + TanStack Query) | Accepté | 2026-05 |
| [0007](./0007-pas-de-redis-en-mvp.md) | Pas de Redis en MVP — cache, sessions et queues sur Postgres | Accepté | 2026-05 |
| [0008](./0008-versions-runtime-modernes.md) | Versions runtime modernes (PHP 8.5, Node 26, Laravel 13, Filament 5) | Accepté | 2026-05 |
| [0009](./0009-google-oauth-admin-only.md) | Google OAuth réservé aux admins (match par email, pas d'auto-provisioning) | Accepté | 2026-06 |
| [0010](./0010-timezone-europe-paris.md) | Timezone applicative Europe/Paris | Accepté | 2026-07 |
| [0011](./0011-magic-link-jetons-en-table.md) | Magic link — jetons à usage unique hashés en table dédiée | Accepté | 2026-07 |

## Conventions

### Statuts possibles

- **Proposé** : en discussion, pas encore décidé
- **Accepté** : décision actée, mise en œuvre en cours ou faite
- **Déprécié** : décision plus en vigueur mais conservée pour traçabilité
- **Remplacé par ADR-XXXX** : décision remplacée par un ADR plus récent

### Création d'un nouvel ADR

1. Choisir le prochain numéro disponible (4 chiffres : `NNNN`)
2. Créer le fichier `NNNN-titre-kebab-case.md` à la racine de ce dossier
3. Suivre la structure standard :
   - **Statut** : Proposé en première version
   - **Contexte** : problème à résoudre, contraintes
   - **Décision** : choix retenu (concis)
   - **Conséquences** : bénéfices et trade-offs assumés
   - **Alternatives considérées** : ce qu'on a regardé et pourquoi écarté
   - **Références** : liens utiles, ADRs liés
4. Soumettre en PR pour discussion
5. À l'acceptation : statut "Accepté" et ajout au tableau ci-dessus
6. Si la décision remplace un ADR existant : marquer l'ancien comme "Remplacé par ADR-NNNN" (sans le supprimer — la traçabilité est l'intérêt principal des ADRs)

### Quand créer un ADR

Pour les **décisions difficiles à inverser** ou **non évidentes**. Exemples :

- Choix d'un framework, d'une lib structurante
- Choix d'une architecture (monolithe vs micro, SPA vs SSR)
- Choix d'un pattern de sécurité ou d'auth
- Choix d'une stratégie de migration de données
- Choix d'un format de données
- Changement de stratégie de déploiement

**Pas pour** : ajout d'un endpoint, choix d'un naming local, refactoring tactique.

### Maintenance

Un ADR accepté est **immuable**. Si la décision doit évoluer, créer un nouvel ADR qui supersède l'ancien. C'est volontaire : l'historique des décisions est aussi précieux que la décision actuelle.
