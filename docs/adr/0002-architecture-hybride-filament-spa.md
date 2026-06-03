# ADR-0002 : Architecture hybride Filament admin + SPA React portail

## Statut

Accepté — 2026-05

## Contexte

Le projet doit servir deux types d'utilisateurs avec des besoins très différents :

- **Admins Ecoworking** (1-3 personnes) : gestion quotidienne, CRUD intensif, vue d'ensemble, génération de factures, suivi de paiements, gestion des résa. Besoin d'**efficacité opérationnelle** maximale.
- **Membres** (40-50 personnes) : consultation de leur profil et factures, réservation de salles, achat de tickets nomades, inscription aux events. Besoin d'**UX soignée**, mobile-friendly (PWA), aligné à l'identité Ecoworking.

Ces deux surfaces ont des contraintes UX et fonctionnelles incompatibles avec une approche unique.

Par ailleurs, le porteur du projet souhaite **monter en compétence sur React moderne** comme objectif d'apprentissage stratégique.

## Décision

Adopter une **architecture hybride dans un repo unique** :

- **Admin** : panel Filament 5, rendu côté serveur (Livewire sous le capot), servi sur `admin.ecoworking.fr`
- **Portail membre** : SPA React 19 + TypeScript + Vite + TanStack, consommant une API REST Laravel via Sanctum, servi sur `portail.ecoworking.fr`
- **Une seule application Laravel** sous-jacente, avec routing par sous-domaine

## Conséquences

### Bénéfices

- **Optimisation par usage** : chaque surface utilise la stack la mieux adaptée à son besoin
- **Vélocité admin** : Filament génère du CRUD complet par déclaration (Resources, Forms, Tables, Widgets). Gain estimé : 2-3 mois de dev économisés vs construction manuelle
- **UX portail soignée** : SPA React permet une UX moderne, fluide, customisée à l'identité Ecoworking, là où Filament aurait imposé son design system
- **Apprentissage React** : le portail constitue un projet React de taille moyenne (10-15 écrans, 15-25 endpoints API), parfait pour monter en compétence sur React 19 + TanStack
- **Mutualisation des modèles métier** : Eloquent models, Policies, Services, Jobs, Mail sont partagés entre Filament et l'API REST → pas de duplication de logique
- **Déploiement unique** : un seul `git push`, un seul container, une seule DB → ops minimale pour un dev solo
- **Auth séparée par contexte** : sessions Laravel classiques côté admin, Sanctum SPA côté portail, isolation cookies par sous-domaine (cf. ADR-0004)

### Trade-offs assumés

- **Deux philosophies UI à maîtriser** : Filament (déclaratif, server-rendered) ET React/SPA (impératif, client-rendered) → coût d'apprentissage doublé mais utile aux deux niveaux
- **Build & assets séparés** : compilation Vite admin (assets Filament) + Vite portail (SPA) → légère complexité de build, gérable
- **Pas une "vraie SPA pure"** : le portail consomme une API mais l'admin ne le fait pas → l'API REST n'est pas universelle (volontaire, pas une API publique)
- **Risque de divergence** des conventions front entre admin Filament et portail React → mitigé par le fait que Filament est très conventionnel et que le portail est isolé

### Conséquences sur les autres décisions

- Auth portail dédiée nécessaire (cf. ADR-0003)
- Routing par sous-domaine pour isolation propre (cf. ADR-0004)
- API REST conventions à définir côté Laravel (cf. BRIEF.md section 6)

## Alternatives considérées

### Tout en Filament (admin + portail)

**Pourquoi écarté** :
- UX portail générique, "look Filament" pour tous → mauvaise expérience membre, pas de différenciation Ecoworking
- Filament excelle en backoffice riche, pas en portail public/membre fluide
- Pas d'apprentissage React, objectif de montée en compétence raté
- PWA et offline harder à mettre en place sur Filament

### Tout en SPA React (admin + portail)

**Pourquoi écarté** :
- Construction d'un admin riche from scratch = plusieurs mois de dev en plus
- Perte du gain Filament (2-3 mois) → décale fortement la livraison MVP
- Pas de gain UX significatif sur l'admin (un dev solo n'a pas besoin d'admin léché)

### Inertia.js partout (Laravel + React via Inertia)

**Pourquoi écarté** :
- Inertia est un excellent compromis (server-side routing + composants React) mais ne donne pas la "vraie" expérience SPA + REST que le porteur veut apprendre
- L'auth Inertia est moins explicite que Sanctum SPA
- Moins de découplage architectural : changer le portail back en mobile native plus tard serait plus difficile
- Filament reste meilleur que des composants Inertia custom pour le CRUD admin → l'hybride reste pertinent même avec Inertia

### Deux apps Laravel séparées (admin et portail)

**Pourquoi écarté** :
- Sur-ingénierie pour un mono-tenant à 50 membres
- Duplication des models, migrations, Services
- Double déploiement, double monitoring, double maintenance → coût ops disproportionné pour un dev solo
- Aucun bénéfice pratique vs un repo unique avec sous-domaines

## Références

- Filament 5 docs : https://filamentphp.com/docs/5.x
- Sanctum SPA authentication : https://laravel.com/docs/13.x/sanctum#spa-authentication
- ADR-0001 (choix Laravel)
- ADR-0003 (auth portail Sanctum SPA)
- ADR-0004 (sous-domaines)
