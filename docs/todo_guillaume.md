# TODO Guillaume — actions manuelles côté humain

> **À quoi sert ce fichier**
> Liste des tâches que **Guillaume** doit réaliser **hors du code** : créations de comptes
> chez des tiers, identifiants/clés à générer et à placer dans `.env` (jamais transmis à
> Claude), réglages DNS/infra, décisions à trancher, etc.
>
> **Fonctionnement** : Claude y inscrit tout ce qui requiert une action humaine au moment où
> ça se présente. Guillaume signale au fil de l'eau ce qui est fait → Claude coche la case et
> date. Vue d'ensemble partagée de ce qui reste à faire des deux côtés.
>
> **Rappel sécurité** : Claude ne lit/ne demande **jamais** les valeurs de secrets. Les creds
> vont dans le `.env` local de Guillaume + les variables d'env Clever Cloud (prod). Claude ne
> renseigne que `.env.example` (placeholders vides).

**Légende** : `- [ ]` à faire · `- [x]` fait · 🔴 bloquant pour une tâche en cours · 🟡 quand tu pourras

---

## Synchronisation `.env` (à appliquer à chaque évolution de `.env.example`)

> **Convention** : Claude **prévient systématiquement** de toute modification de
> `.env.example` (ajout/renommage/suppression de clé, valeur changée) et détaille
> ici les variables à répercuter. Toi : tu appliques dans ton **`.env` local** +
> dans **LastPass** (valeurs prod, en attendant Clever Cloud). Claude ne touche
> qu'au `.env.example` (placeholders), jamais à tes valeurs réelles.

### Modifs du 02/07/26 après-midi (seed admin + nettoyage Redis)

| Variable | Dev local (`.env`) | Prod (LastPass → Clever Cloud) |
|---|---|---|
| `SEED_ADMIN_PASSWORD` | optionnel (vide = mdp aléatoire affiché au seed) | à définir SI on seed en prod (sinon inutile) |
| `REDIS_CLIENT/HOST/PASSWORD/PORT`, `MEMCACHED_HOST` | **supprimées** de `.env.example` (variables mortes, ADR-0007) | à retirer des `.env` si présentes |

- [ ] 🟡 **`.env` local** : retirer les variables Redis/Memcached si présentes ; `SEED_ADMIN_PASSWORD` optionnel.
- [ ] 🟡 **LastPass (prod)** : idem.

### Modifs du 02/07/26 (timezone Europe/Paris — ADR-0010)

| Variable | Dev local (`.env`) | Prod (LastPass → Clever Cloud) |
|---|---|---|
| `APP_TIMEZONE` | `Europe/Paris` | `Europe/Paris` |

> Nouvelle variable (défaut du code = `Europe/Paris`, donc non bloquant si absente,
> mais la poser explicitement partout évite toute divergence). Contexte : les
> demi-journées « 9h-13h / 14h-18h » étaient construites en UTC (décalage 1-2 h).

- [ ] 🟡 **`.env` local** : ajouter `APP_TIMEZONE=Europe/Paris`.
- [ ] 🟡 **LastPass (prod)** : ajouter `APP_TIMEZONE=Europe/Paris`.

### Modifs du 07/06/26 (routing par sous-domaine + APP_KEY)

| Variable | Dev local (`.env`) | Prod (LastPass → Clever Cloud) |
|---|---|---|
| `APP_URL` | `http://admin.ecoworking.test` | `https://admin.ecoworking.fr` |
| `ADMIN_DOMAIN` | `admin.ecoworking.test` | `admin.ecoworking.fr` |
| `PORTAL_DOMAIN` | `portail.ecoworking.test` | `portail.ecoworking.fr` |
| `APP_KEY` | généré localement (`key:generate`) | défini en prod (Clever Cloud / LastPass) |

- [x] ✅ **`.env` local** déjà à jour (vérifié 07/06/26) + `APP_KEY` régénéré.
- [x] ✅ **LastPass** : valeurs **prod** consignées (07/06/26 ; à pousser dans Clever Cloud au déploiement).
- [x] ✅ **`/etc/hosts` Windows** : `127.0.0.1 admin.ecoworking.test` + `127.0.0.1 portail.ecoworking.test` configuré (07/06/26).

> Note tests : en environnement **test** (Pest), `ADMIN_DOMAIN`/`PORTAL_DOMAIN` sont
> forcés vides par `phpunit.xml` — ne pas s'en étonner, c'est voulu (routes sans
> contrainte de domaine sur `localhost`).

---

## C2.2 — Google OAuth (login admin via Socialite)

> Contexte : login Google **additionnel** réservé aux admins (BRIEF §8). Politique retenue :
> **admin-only + domaine restreint + match par email**, sans création de compte automatique.
> Le code (controller, routes, tests mockés) est livré sans tes creds ; tu n'en as besoin que
> pour un **test live** contre Google.

- [x] ✅ Créer (ou réutiliser) un **projet Google Cloud** pour Ecoworking → *APIs & Services*
- [x] ✅ Configurer l'**OAuth consent screen**
  - Type **Internal** si vous avez un Google Workspace `ecoworking.fr` (limite d'office aux comptes maison) ; sinon **External**
  - Scopes strictement : `openid`, `email`, `profile`
- [x] ✅ Créer des **identifiants OAuth** → *Create Credentials → OAuth client ID → Web application*
  - **Authorized redirect URIs** : `https://admin.ecoworking.fr/auth/google/callback` (**prod uniquement** — test manuel Google en prod seulement)
  - **Origines JavaScript autorisées** : laissées **vides** (flow Socialite server-side, pas de login JS)
  - Creds stockées dans **LastPass** (pas de fichier `.env.prod` local)
- [x] ✅ **Domaine autorisé** (paramètre `hd`) = `ecoworking.fr` (confirmé 07/06/26) — déjà en config (`GOOGLE_HOSTED_DOMAIN`, re-vérifié par suffixe email au callback).
- [ ] 🟡 **Au déploiement** : placer les valeurs dans les **env vars Clever Cloud** (pas dans le `.env` local, qui reste avec les clés vides) :
  - `GOOGLE_CLIENT_ID=…`
  - `GOOGLE_CLIENT_SECRET=…`
  - `GOOGLE_REDIRECT_URI=https://admin.ecoworking.fr/auth/google/callback`
  - `GOOGLE_HOSTED_DOMAIN=ecoworking.fr` (ou ce que tu décides)
  - *(les clés vides correspondantes sont déjà dans `.env.example`)*
- [ ] 🟡 Vérifier qu'au moins **un compte admin** en base a une **adresse email = celle de ton compte Google** (le match se fait par email)
- [ ] 🔴 **À TESTER MANUELLEMENT EN PROD** : flow `admin.ecoworking.fr` → « Se connecter avec Google » pour confirmer le bon fonctionnement, et me remonter tout souci

---

## C8 — Emails transactionnels (prod Brevo)

> Contexte : en dev les emails partent dans **Mailpit** (rien à faire). En prod,
> les notifications critiques (facture émise / en retard) doivent partir par email
> via **Brevo** (BRIEF §13). Le code (notifications en queue) est prêt et agnostique
> du transport ; il suffit de configurer le mailer.

- [x] ✅ Compte **Brevo** créé + **domaine expéditeur** `ecoworking.fr` validé (SPF/DKIM) (07/06/26)
- [x] ✅ **Clé API Brevo** générée → dans **LastPass** (`BREVO_API_KEY`), à pousser en prod (07/06/26)
- [x] ✅ Transport choisi : **driver API Brevo** (07/06/26). Câblage finalisé côté code : `symfony/brevo-mailer` + `symfony/http-client`, mailer `brevo` (config/mail.php), `services.brevo.key`, transport enregistré dans `AppServiceProvider` (`Mail::extend`). Test `BrevoTransportTest`.
- [x] ✅ **`MAIL_MAILER=brevo`** enregistré dans l'env prod (LastPass, 07/06/26) — à pousser dans Clever Cloud au déploiement.
- [ ] 🟡 S'assurer qu'un **worker de queue** tourne en prod (`queue:work`, queues sur Postgres ADR-0007) — sinon les emails (en queue) ne partent pas. Lié au provisioning Clever Cloud (V1.5).

---

## C10 — Observabilité (Sentry, Pulse, Better Stack, Healthchecks)

> Contexte : packages **installés et câblés** côté code (Sentry back+front, Pulse,
> ping Healthchecks sur les crons, endpoint `/up` pour l'uptime). Tout est **no-op
> tant que les variables d'env sont vides** → il reste à créer les comptes et à
> renseigner les clés. Placeholders déjà dans `.env.example`.

### Sentry (erreurs + perf)
- [x] ✅ Compte **Sentry** + **2 projets** (`ecoworking-laravel`, `ecoworking-portal-react`) créés (07/06/26)
- [x] ✅ DSN prod enregistrés dans **LastPass** : `SENTRY_LARAVEL_DSN` (back) + `VITE_SENTRY_DSN` (front, injecté au build Vite) + `SENTRY_TRACES_SAMPLE_RATE` — à pousser dans Clever Cloud au déploiement (07/06/26)
- [ ] 🟡 (CI, jour du déploiement) Créer un **`SENTRY_AUTH_TOKEN`** pour l'upload des source maps + tag release, à brancher dans le pipeline

### Laravel Pulse (perf interne)
- [ ] 🟡 Aucune action de compte. Vérifier en prod que `/pulse` n'est accessible **qu'aux admins** (gate `viewPulse` posé sur `User::isAdmin`) et que `PULSE_ENABLED=true`
- [ ] 🟡 Prévoir le **trim** des données Pulse (commande `pulse:check`/scheduler par défaut) si volume

### Better Stack (uptime externe)
- [x] ✅ Compte **Better Stack** créé → monitor sur `https://portail.ecoworking.fr/up` (endpoint santé Laravel déjà exposé) (08/06/26)
- [x] ✅ **Alertes** configurées (email / Slack) (08/06/26)

### Healthchecks.io (surveillance des crons)
- [x] ✅ Compte **Healthchecks.io** créé + **1 check par cron** (08/06/26) :
  - facturation mensuelle → `HEALTHCHECK_MONTHLY_BILLING_URL` (cron `0 6 1 * *` UTC, grâce 2 h)
  - bascule factures en retard → `HEALTHCHECK_OVERDUE_INVOICES_URL` (période 1 j, grâce 1 h)
  - (le scheduler ping l'URL en succès et `…/fail` en échec — déjà câblé)
- [x] ✅ **Période/grâce** réglées sur la fréquence réelle (mensuel / quotidien) (08/06/26)
- [x] ✅ URLs de ping enregistrées dans **LastPass** (08/06/26)
- [ ] 🟡 (jour du déploiement) Pousser les 2 URLs dans **Clever Cloud** + tester un ping réel

---

## C9.1 — Push Google Calendar (différé V1.5)

> Décision 2026-06-07 : le **push sortant** des résas salles vers un calendrier
> Google dédié est **reporté en V1.5** (avec le provisioning Clever Cloud). Les flux
> **iCal d'abonnement** côté membre (C9.2) sont, eux, **livrés** et ne dépendent
> d'aucun service Google.

- [ ] 🟡 (V1.5) Créer un **service account Google** + calendrier dédié « Ecoworking — Salles », partager en lecture publique
- [ ] 🟡 (V1.5) Me le signaler pour que j'ajoute `google/apiclient` + le `SyncBookingToGoogleCalendarJob` (la colonne `bookings.google_calendar_event_id` est déjà prête)

---

## C12.1 — Serving SPA en production (2026-07-02)

> Les assets buildés (`public/portal/`) sont **gitignorés** : le build SPA est une
> étape de déploiement. Sans build, le portail répond 503 explicite.

- [ ] 🟡 (jour du déploiement) Configurer le **hook de build Clever Cloud** pour builder la SPA : `cd portal-spa && pnpm install --frozen-lockfile && pnpm build` (sortie automatique vers `public/portal/`)

---

## C11.3 — Tests e2e (2026-07-03)

> Rien à installer côté hôte (pas de sudo requis) : Chromium et ses libs vivent dans le
> conteneur Sail. Runbook complet : `docs/testing-e2e.md`.

- [ ] 🟡 (récurrent) Après chaque `sail build` (rebuild de l'image), relancer `scripts/e2e/install.sh` — les libs système Chromium du conteneur ne survivent pas au rebuild

---

## Passe P2 review (2026-07-03) — ⚠️ `.env.example` modifié

> Deux changements dans `.env.example` (à répercuter dans ton `.env` local si tu utilises
> le disque S3 en dev — sinon aucun impact, ces variables sont inertes tant que
> `FILESYSTEM_DISK=local`) :

- [ ] 🟡 `AWS_DEFAULT_REGION` : `us-east-1` → **`eu-west-1`** (aligné BRIEF §13 / Cellar)
- [ ] 🟡 `AWS_ENDPOINT=` **ajouté** (vide) — à remplir en prod avec l'endpoint Cellar (`$CELLAR_ADDON_HOST`) ; penser à l'entrée LastPass prod

---

## C13 — Recette manuelle (2026-09-12)

> Livrés : `docs/recette.md` (checklist) + `DemoSeeder` (jeu de démo), validés le 13/09
> (`DemoSeederTest` vert, suite complète 446 Pest verte, Pint propre).

- [x] ✅ Docker Desktop relancé + intégration WSL2 active (13/09/26)
- [x] ✅ Suite Pest verte après 2 mois : 446 tests (13/09/26) — Biome/Vitest côté SPA non relancés (aucune modif SPA)
- [x] ✅ Jeu de démo chargé sur la base de dev (13/09/26) : 11 factures (4 payées, 1 partielle, 2 en retard, 1 annulée + avoir, 2 brouillons), 0 job en attente, 57 notifications, 10 mails dans Mailpit. Relancer `sail artisan migrate:fresh --seed --seeder=DemoSeeder` pour repartir d'une base propre
- [ ] 🟡 Poser `SEED_ADMIN_PASSWORD` dans le `.env` local avant le seed (sinon mot de passe admin affiché une seule fois)
- [ ] 🟡 Dérouler `docs/recette.md`, cocher, remplir le journal §9, puis me dire « corrige R-nn »

## Fix liens emails → domaine portail (2026-09-14) — ⚠️ `.env.example` modifié

> Bug de recette : les liens des emails de notification (« Voir mes documents », « Voir mes factures », « Régulariser ») pointaient sur `admin.ecoworking.fr`, inaccessible aux membres. Cause : `PortalNotification::portalUrl()` construisait ses URLs sur `config('app.url')`. Corrigé via `App\Support\PortalUrl` (source unique = `PORTAL_DOMAIN`). Règle codifiée en CLAUDE.md §3.7 + ADR-0004.

- [ ] 🟠 **`.env.example` modifié** : synchroniser ton `.env` local + LastPass prod / Clever Cloud
  - `GOOGLE_REDIRECT_URI` ne dérive plus de `APP_URL` mais de `ADMIN_DOMAIN` → `"http://${ADMIN_DOMAIN}/auth/google/callback"` (dev). **La valeur produite est inchangée** (`https://admin.ecoworking.fr/auth/google/callback` en prod) : **rien à modifier dans la Console Google Cloud**
  - `APP_URL` : valeur inchangée, seuls des commentaires d'avertissement ont été ajoutés au-dessus
- [ ] 🟠 **Vérifier `APP_URL` en prod Clever Cloud** = `https://admin.ecoworking.fr`. Si ce n'est pas le cas, la `GOOGLE_REDIRECT_URI` prod actuelle (qui en dérivait) est déjà de travers et le login Google admin est cassé
- [ ] 🟡 Vérifier en recette que les emails de notif arrivent bien avec un lien `portail.` (Mailpit : republier un document interne, ouvrir le mail « Nouveau document à valider »)

## Plus tard / hors MVP (pour mémoire)

- [ ] 🟡 (V1.5) Provisioning **Clever Cloud** : app, Postgres 18, Cellar, FS Bucket, DNS, env vars prod (cf. BRIEF §11, SUIVI D1-D2)

---

*Maintenu par Claude au fil des sessions. Dis-moi ce que tu as fait → je coche et je date.*
