# ADR-0007 : Pas de Redis en MVP — cache, sessions et queues sur Postgres

## Statut

Accepté — 2026-05

## Contexte

La stack initialement envisagée incluait **Redis** managé sur Clever Cloud pour quatre usages :

1. **Cache applicatif** (Laravel `Cache` facade)
2. **Sessions utilisateurs** (Laravel `Session`)
3. **Queues** de jobs asynchrones (envoi d'emails, génération PDF, sync Google Calendar)
4. **Rate limiting** (compteurs login attempts, API throttling)

L'ajout de Redis nécessite :
- Un service managé supplémentaire chez Clever Cloud (~7€/mois)
- Un container additionnel en dev local (gérable via Sail mais ajoute de la friction)
- Une dépendance de plus à monitorer et à comprendre
- L'extension PHP `php-redis` à installer

Question posée frontalement : **Redis est-il indispensable au regard de la volumétrie cible et du périmètre MVP ?**

Volumétrie réelle prévue :
- 40-50 membres actifs, ~75 entreprises
- Quelques dizaines de connexions/jour côté portail
- 1-5 réservations/jour
- ~50-80 factures/mois
- Quelques jobs asynchrones/jour (emails transactionnels, génération PDF, sync calendar)

On est plusieurs ordres de grandeur en dessous des seuils où Redis apporte une valeur tangible.

## Décision

**Ne pas utiliser Redis en MVP.** Configuration Laravel :

```env
CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database
```

Conséquence : tout le state transient (cache, sessions, queues, rate limiting) est stocké dans PostgreSQL via les drivers `database` natifs de Laravel 13.

**Pas d'utilisation de Laravel Horizon** (qui exige Redis). Le monitoring des queues passe par **Laravel Pulse** (intégré, gratuit) et **Telescope** (en dev). Workers démarrés via `php artisan queue:work` ou un process Clever Cloud dédié en prod.

## Conséquences

### Bénéfices

- **~7€/mois économisés** sur la facture Clever Cloud (passage de ~37€ à ~30€)
- **Une dépendance majeure de moins** à monitorer, sauvegarder, mettre à jour, comprendre
- **Setup dev local simplifié** : un container Postgres + Mailpit suffit (vs +1 container Redis)
- **Backup unifié** : un seul dump à externaliser quotidiennement (la DB Postgres contient tout)
- **Cohérence opérationnelle** : un seul backend de données, une seule logique de réplication/restauration
- **Pas de risque de désynchronisation** Redis/Postgres (qui peut arriver lors de crashes ou redémarrages mal séquencés)

### Trade-offs assumés

- **Performance brute inférieure** : Redis (RAM) répond en ~0.1-1ms, Postgres en ~1-5ms sur des lectures simples. **Différence invisible** à la volumétrie cible
- **Charge accrue sur Postgres** : la DB doit absorber les écritures cache + sessions + jobs en plus du métier. Reste très loin de la saturation au volume cible (Postgres S Clever Cloud absorbe facilement des centaines de req/s)
- **Pas de Laravel Horizon** : pas de dashboard web élégant pour les queues. **Pulse** (intégré) et **Telescope** (dev) suffisent largement pour 5-10 jobs/jour
- **Table `jobs` Postgres à surveiller** : si elle commence à grossir anormalement (jobs bloqués, retries en boucle), c'est un signal d'alerte → à monitorer via Pulse
- **Pas adapté à un cas pub/sub temps réel** : si on voulait du WebSocket ou des notifications push live, il faudrait ajouter Redis. **Hors scope MVP**

### Conséquences sur les autres décisions

- Suppression de `laravel/horizon` du `composer.json`
- Suppression du container Redis dans `docker-compose.yml` (et de Redis du Sail `with`)
- Suppression de l'extension `php8.5-redis` dans le setup dev
- Suppression du service Redis dans la commande Clever Cloud
- Pas de `REDIS_*` variables dans `.env.example`
- Variables `CACHE_STORE`, `SESSION_DRIVER`, `QUEUE_CONNECTION` fixées à `database`

## Plan de migration vers Redis (si jamais nécessaire plus tard)

La migration est **triviale** si elle devient nécessaire :

1. Provisionner un Redis S sur Clever Cloud (~7€/mois)
2. Installer `predis/predis` via Composer
3. Configurer les variables `REDIS_*` dans l'env
4. Changer 3 variables :
   ```env
   CACHE_STORE=redis
   SESSION_DRIVER=redis
   QUEUE_CONNECTION=redis
   ```
5. Optionnel : ajouter `laravel/horizon` pour le dashboard queues
6. Redémarrer l'app

**Aucune réécriture de code**. Les facades `Cache::`, `Session::`, `Queue::` sont agnostiques du driver.

## Signaux qui justifieraient le passage à Redis

À surveiller pendant la vie du projet :
- Croissance soutenue de la table `jobs` Postgres (régulièrement > 1000 lignes en attente)
- Latence p95 sur les endpoints API qui se dégrade et qui corrèle avec des locks Postgres sur les tables `cache` ou `sessions`
- Volume de jobs > 100/heure de manière soutenue
- Apparition d'un besoin pub/sub temps réel (notifications push, WebSocket)
- Besoin de partager du cache entre plusieurs instances Laravel (scaling horizontal au-delà de 2 instances)

Tant qu'aucun de ces signaux n'apparaît, Postgres seul est suffisant.

## Alternatives considérées

### Garder Redis dès le MVP (proposition initiale)

**Pourquoi écarté** :
- Sur-ingénierie pour la volumétrie cible
- Coût récurrent injustifié au démarrage
- Friction setup et maintenance disproportionnée pour un dev solo
- "Au cas où" est exactement le type de raisonnement qui alourdit inutilement les MVP

### Driver `file` ou `cookie` pour les sessions

**Pourquoi écarté** :
- `file` : peu fiable en environnement scalé (les fichiers ne sont pas partagés entre instances), pas pertinent même en single instance
- `cookie` : limité en taille, non révocable (un user déconnecté peut conserver son cookie session jusqu'à expiration), incompatible avec le besoin de révocation immédiate (cas de compromission, 2FA, etc.)

Le driver `database` est le bon compromis : performant, persistant, révocable, scale-friendly.

### Driver `array` pour le cache (in-memory process)

**Pourquoi écarté** :
- Cache uniquement vivant dans le process PHP courant
- Pas partagé entre requêtes
- Aucun bénéfice pour des données persistantes

## Note méta sur cette décision

Cette décision **revient sur une proposition initiale** (Redis dans la stack). Le revirement vient d'un challenge explicite : *"Redis est-il indispensable concrètement ?"*. La réponse honnête est non, à la volumétrie cible.

C'est exactement le type d'arbitrage qu'il faut faire en début de projet :
- Au moment de la proposition initiale, j'avais embarqué Redis "par habitude" (c'est un standard dans les apps Laravel sérieuses), sans vraiment l'évaluer face au volume réel du projet
- La question frontale a forcé une analyse honnête qui montre que Redis n'apporte aucune valeur tangible avant un certain seuil
- Le coût marginal (Redis) ne se justifie que par un bénéfice marginal réel

Application générale : à chaque dépendance qu'on envisage d'ajouter, se demander **"qu'est-ce que je perds concrètement sans elle ?"**. Si la réponse est *"un peu de performance invisible à mon volume"*, on s'en passe.

## Références

- Documentation Laravel queues (driver database) : https://laravel.com/docs/13.x/queues#driver-prerequisites
- Documentation Laravel cache (driver database) : https://laravel.com/docs/13.x/cache#prerequisites
- Documentation Laravel sessions (driver database) : https://laravel.com/docs/13.x/session#driver-prerequisites
- Laravel Pulse (monitoring) : https://laravel.com/docs/13.x/pulse
- ADR-0001 (choix Laravel)
- ADR-0003 (Sanctum SPA — sessions sur Postgres)
