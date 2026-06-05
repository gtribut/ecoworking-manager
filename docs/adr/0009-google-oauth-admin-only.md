# ADR-0009 : Google OAuth admin-only, domaine restreint, sans auto-provisioning

## Statut

Accepté — 2026-06

## Contexte

Le BRIEF §8 prévoit un login Google **additionnel** pour les admins Ecoworking (« si l'admin a un compte Google d'entreprise »), via `laravel/socialite`. Il faut définir précisément la **politique de liaison** d'un compte Google à un compte applicatif, car une mauvaise politique ouvrirait une voie de contournement de l'isolation (CLAUDE.md §3.1) ou créerait des comptes non désirés.

Trois questions :
1. Qui peut se connecter via Google ? (n'importe qui / membres / admins seulement)
2. Que fait-on d'un compte Google inconnu ? (création automatique / refus)
3. Comment se prémunir d'un compte Gmail tiers usurpant un email ?

## Décision

**Admin-only + domaine restreint + match par email, sans auto-provisioning.**

Au retour du callback Google, on connecte l'utilisateur **uniquement si** :
- l'email est **vérifié** par Google (`email_verified`), et
- il appartient au **Workspace autorisé** (`services.google.hosted_domain`, ex. `ecoworking.fr`) — revérifié côté serveur sur l'email vérifié, le paramètre `hd` envoyé à Google n'étant qu'indicatif, et
- un compte applicatif **existant** porte cet email **et** le rôle `admin`.

Toute condition non remplie → **aucune session ouverte**, redirection avec message d'erreur. Aucun compte n'est jamais créé par ce flow (cohérent avec l'absence d'inscription self-service, PRD §3.2).

Le login Google n'est exposé que sur le **sous-domaine admin** (ADR-0004) ; le portail membre n'a pas d'OAuth.

## Conséquences

### Bénéfices

- **Surface d'attaque minimale** : un compte Google hors Workspace, ou non vérifié, ou sans compte admin correspondant, ne peut rien obtenir.
- **Pas de comptes fantômes** : aucune création implicite ; l'admin reste la seule voie de création de comptes.
- **Liaison par email vérifié** : simple, sans colonne supplémentaire en base (pas de `google_id` en MVP). Acceptable car l'email Google est vérifié et le domaine contraint.
- **Défense en profondeur** : domaine vérifié côté serveur, pas seulement via le paramètre `hd`.

### Trade-offs assumés

- **Couplage email** : si un admin change d'adresse email côté Google sans la mettre à jour en base, son login Google échoue jusqu'à correction. Acceptable (rare, contournable par login email/mot de passe). Si ça devient gênant : ajouter une colonne `google_id` nullable (évolution non bloquante).
- **Dépend d'un Workspace Google** : si Ecoworking n'a pas de Workspace `ecoworking.fr`, `hosted_domain` doit être adapté (ou le contrôle assoupli) — décision laissée à la config.

### Conséquences techniques

- `config/services.php` → bloc `google` (`client_id`, `client_secret`, `redirect`, `hosted_domain`) alimenté par `.env`.
- `GoogleOAuthController` (`redirect` / `callback`) ; routes `auth/google/*` sur le domaine admin.
- 2FA : le login Google **ne dispense pas** du 2FA obligatoire admin — l'enforcement se fait au niveau du panel (C3.1), indépendamment de la méthode de login.
- Tests : Socialite mocké (aucune cred requise en CI).

## Alternatives considérées

- **Auto-provisioning** (créer le compte au premier login Google) : écarté — contredit « comptes créés par l'admin » et ouvrirait l'accès à tout le Workspace sans contrôle de rôle.
- **OAuth ouvert aux membres** : écarté en MVP — le BRIEF ne le prévoit que pour les admins ; le portail membre reste en email/mot de passe (+ 2FA optionnel).
- **Liaison par `google_id`** plutôt que par email : reporté — bénéfice marginal en MVP, coût d'une colonne + flow de liaison initial. Réservé si le couplage email pose problème.

## Références

- BRIEF §8 (auth) · ADR-0004 (sous-domaines) · PRD §3.2 (pas d'inscription self-service)
- CLAUDE.md §3.1 (isolation) · §10 (checkpoint service tiers — validé par le porteur)
