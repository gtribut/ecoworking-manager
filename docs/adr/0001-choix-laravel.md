# ADR-0001 : Choix de Laravel 13 comme backend

## Statut

Accepté — 2026-05

> **Note d'évolution** : à la conception initiale du projet, Laravel 11 était le current. Cet ADR a été mis à jour en mai 2026 vers Laravel 13 (sorti mars 2026) sans rupture de raisonnement — le choix du framework Laravel reste valide, seule la version courante change. Voir [ADR-0008](./0008-versions-runtime-modernes.md) pour le détail des choix de versions runtime (PHP 8.5, Node 26, Laravel 13, Filament 5).

## Contexte

Le projet vise à remplacer Cosoft par un outil sur-mesure pour Ecoworking. Caractéristiques structurantes :

- **Dev solo en soir/weekend** : la vélocité d'exécution est critique pour ne pas abandonner le projet
- **Périmètre admin-heavy** : ~80% de la valeur initiale réside dans un back-office riche (CRUD membres, entreprises, offres, abonnements, factures, paiements, ressources, réservations)
- **Volonté de montée en compétence** : le porteur du projet veut sortir de sa zone de confort (PHP/MySQL vanilla, Vue.js) vers une stack moderne
- **Maintenabilité long terme** : l'outil doit tenir 5-10 ans, l'écosystème choisi doit avoir une trajectoire claire
- **Conformité française** : facturation électronique 2027, RGPD, hébergement souverain

Quatre options sérieuses ont été évaluées pour le backend : Laravel 13, Go, Node/TypeScript (Hono ou NestJS), Next.js full-stack.

## Décision

**Laravel 13 + PHP 8.5+** comme framework backend, associé à **Filament 5** pour le panel d'administration.

## Conséquences

### Bénéfices

- **Écosystème mature et complet** pour le périmètre exact du projet : Fortify (auth + 2FA), Sanctum (auth SPA), Cashier (Stripe en V2), Socialite (OAuth), Pulse (métriques), Backup, Permission (rôles), ActivityLog (audit), Settings, Data (DTOs)
- **Filament 5** apporte un gain estimé à 2-3 mois de dev sur l'admin via la génération de Resources CRUD complètes
- **Vélocité de livraison** maximale pour un dev solo : conventions claires, scaffolding artisan, documentation excellente
- **Familiarité PHP** du porteur réduit la friction de syntaxe (la montée en compétence se concentre sur les concepts modernes Laravel : conteneur de services, Eloquent, Form Requests, queues, Inertia/Filament — pas sur la syntaxe du langage)
- **Maintenabilité** : releases LTS, communauté FR active, roadmap claire jusqu'à Laravel 14+
- **Hébergement souple** : PHP tourne nativement sur Clever Cloud, OVH, Scaleway sans setup particulier

### Trade-offs assumés

- **Pas d'alignement** avec les stacks "tech-pure" actuelles (Go, Rust) côté valeur de compétence externe → compensé par l'apprentissage Laravel moderne, Filament, et React via la SPA portail
- **PHP** a une perception marketing en lente décroissance vs Node/Go, mais reste très utilisé en France notamment dans le SaaS B2B et l'agence
- **Performance** : Laravel/PHP est moins performant que Go pour des charges très élevées, mais largement suffisant pour la volumétrie cible (~50 membres, ~75 entreprises)
- **Couplage framework** : changer de framework dans 5 ans serait coûteux, mais le risque est faible (Laravel est stable, l'ORM Eloquent reste portable conceptuellement)

### Conséquences sur les autres décisions

- L'admin se fera en Filament (cf. ADR-0002)
- Le portail nécessite une stratégie d'auth dédiée pour la SPA (cf. ADR-0003)
- Le routing par sous-domaine sera géré au niveau Laravel (cf. ADR-0004)

## Alternatives considérées

### Go + chi + sqlc + Postgres

**Pourquoi écarté** :
- Courbe d'apprentissage initiale lourde (~4 semaines avant productivité réelle) pour un dev solo en marge de son activité principale → risque d'abandon élevé
- Écosystème billing/PDF/auth/admin moins mature qu'en PHP : pas d'équivalent à Filament, Cashier, Fortify
- Génération PDF, Factur-X et autres briques métier souvent à assembler manuellement ou via sidecars
- Doubler le langage (TS front + Go back) ajoute de la charge cognitive
- Performance et concurrence : avantages réels mais inutiles à la volumétrie du projet

Verdict : Go reste un excellent langage backend, mais son ratio effort/valeur livrée est défavorable pour CE projet précis. Apprentissage Go peut être fait ailleurs sur un side-project plus petit.

### Node/TypeScript (Hono, NestJS, ou Fastify) + Drizzle + Postgres

**Pourquoi écarté** :
- Stack TS unifiée front+back attractive, mais aucun équivalent à Filament côté admin → il faudrait construire l'admin from scratch (plusieurs mois de dev en plus)
- Moins de "batteries included" que Laravel : assembler auth + permissions + audit + jobs + admin est du travail manuel
- NestJS est mature mais lourd ; Hono est moderne mais jeune (écosystème moins fourni)

### Next.js 15 (App Router + Server Actions)

**Pourquoi écarté** :
- Pas d'équivalent à Filament côté admin
- Server Components / Server Actions sont des concepts récents nécessitant une vraie acclimatation
- Pertinent pour un projet content/marketing, moins pour un outil de gestion admin-heavy
- Convient mieux à un projet déjà piloté full-React (cohérence team) qu'à un dev solo qui veut apprendre

### Supabase / Pocketbase (BaaS)

**Pourquoi écarté** :
- Vendor lock-in non négligeable
- Logique métier complexe (facturation française, prorata, avoirs, conformité Factur-X) peu adaptée aux Edge Functions
- Souveraineté limitée vs Clever Cloud français

## Références

- Documentation Laravel : https://laravel.com/docs/13.x
- Documentation Filament 5 : https://filamentphp.com/docs/5.x
