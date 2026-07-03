# ADR-0011 : Magic link — jetons à usage unique persistés en table dédiée

## Statut

Accepté — 2026-07-03

## Contexte

Le PRD §3.2 (Q6, arbitrée « V1 » le 2026-07-02) prévoit une connexion membre par
« magic link » : lien de connexion envoyé par email, valable 15 minutes, à usage
unique, invalidé au changement de mot de passe, jamais disponible pour un compte
admin (qui conserve mot de passe + 2FA obligatoire).

Une URL signée seule (`URL::temporarySignedRoute`) fournit l'intégrité et
l'expiration, mais **pas** l'usage unique ni l'invalidation ciblée : il faut un
état côté serveur. Deux options : le cache Laravel (Postgres en MVP, ADR-0007)
ou une table dédiée.

## Décision

**Table dédiée `magic_link_tokens`**, cycle de vie encapsulé dans
`App\Services\Auth\MagicLinkService` :

- colonne `token_hash` = SHA-256 du jeton (64 hex) — le jeton en clair n'est
  **jamais** persisté (une compromission en lecture de la DB ne permet pas de
  forger un lien), unique + indexée ;
- `expires_at` (15 min, indexée) et `used_at` (usage unique, marquage atomique
  par `UPDATE … WHERE used_at IS NULL`) ;
- `user_id` FK cascade + index → invalidation ciblée par utilisateur
  (`UserObserver` purge les jetons à tout changement de mot de passe, quel que
  soit le chemin : membre, reset email, admin Filament) ;
- défense en profondeur : le lien reste par ailleurs une URL signée temporaire —
  signature ET jeton doivent être valides.

Écartée : l'entrée cache (`Cache::put` avec TTL). Elle aurait suffi pour
l'expiration, mais l'invalidation « tous les jetons d'un user » exige des clés
indexables (donc une table de fait), et une table explicite est requêtable
(support, debug) et cohérente avec `password_reset_tokens` de Laravel — même
famille de mécanisme, même durée de vie courte.

## Conséquences

- Migration réversible `create_magic_link_tokens_table` ; volumétrie négligeable
  (~50 membres), les jetons consommés/expirés restent purgeables plus tard par
  une commande de nettoyage si besoin (non nécessaire en MVP).
- L'éligibilité (jamais admin, jamais anonymisé) est vérifiée **à l'envoi et à
  la consommation** : un jeton forgé ou devenu obsolète (promotion admin,
  anonymisation) ne connecte pas.
- Anti-énumération : `POST /magic-link` répond strictement la même chose que
  l'email existe ou non ; l'échec de consommation redirige vers
  `/login?magic_link=invalid` sans détail.
