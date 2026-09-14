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

| Variable                                            | Dev local (`.env`)                                            | Prod (LastPass → Clever Cloud)               |
| --------------------------------------------------- | ------------------------------------------------------------- | -------------------------------------------- |
| `SEED_ADMIN_PASSWORD`                               | optionnel (vide = mdp aléatoire affiché au seed)              | à définir SI on seed en prod (sinon inutile) |
| `REDIS_CLIENT/HOST/PASSWORD/PORT`, `MEMCACHED_HOST` | **supprimées** de `.env.example` (variables mortes, ADR-0007) | à retirer des `.env` si présentes            |

- [x] 🟡 **`.env` local** : retirer les variables Redis/Memcached si présentes ; `SEED_ADMIN_PASSWORD` optionnel.
- [x] 🟡 **LastPass (prod)** : idem.

### Modifs du 02/07/26 (timezone Europe/Paris — ADR-0010)

| Variable       | Dev local (`.env`) | Prod (LastPass → Clever Cloud) |
| -------------- | ------------------ | ------------------------------ |
| `APP_TIMEZONE` | `Europe/Paris`     | `Europe/Paris`                 |

> Nouvelle variable (défaut du code = `Europe/Paris`, donc non bloquant si absente,
> mais la poser explicitement partout évite toute divergence). Contexte : les
> demi-journées « 9h-13h / 14h-18h » étaient construites en UTC (décalage 1-2 h).

- [x] 🟡 **`.env` local** : ajouter `APP_TIMEZONE=Europe/Paris`.
- [x] 🟡 **LastPass (prod)** : ajouter `APP_TIMEZONE=Europe/Paris`.

### Modifs du 07/06/26 (routing par sous-domaine + APP_KEY)

| Variable        | Dev local (`.env`)                 | Prod (LastPass → Clever Cloud)           |
| --------------- | ---------------------------------- | ---------------------------------------- |
| `APP_URL`       | `http://admin.ecoworking.test`     | `https://admin.ecoworking.fr`            |
| `ADMIN_DOMAIN`  | `admin.ecoworking.test`            | `admin.ecoworking.fr`                    |
| `PORTAL_DOMAIN` | `portail.ecoworking.test`          | `portail.ecoworking.fr`                  |
| `APP_KEY`       | généré localement (`key:generate`) | défini en prod (Clever Cloud / LastPass) |

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
  - purge des notifications → `HEALTHCHECK_NOTIFICATIONS_PURGE_URL` (cron `notifications:purge`, quotidien 4 h) — **ajouté au lot G, créé le 14/09**
  - (le scheduler ping l'URL en succès et `…/fail` en échec — déjà câblé)
- [x] ✅ **Période/grâce** réglées sur la fréquence réelle (mensuel / quotidien) (08/06/26)
- [x] ✅ URLs de ping enregistrées dans **LastPass** (les 2 premières le 08/06/26, `notifications_purge` le 14/09/26)
- [ ] 🟡 (jour du déploiement) Pousser les **3 URLs** dans **Clever Cloud** + tester un ping réel (`monthly_billing`, `overdue_invoices`, `notifications_purge` — cf. `config/services.php`)

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

- [x] 🟡 `AWS_DEFAULT_REGION` : `us-east-1` → **`eu-west-1`** (aligné BRIEF §13 / Cellar)
- [ ] 🟡 `AWS_ENDPOINT=` **ajouté** (vide) — à remplir en prod avec l'endpoint Cellar (`$CELLAR_ADDON_HOST`) ; penser à l'entrée LastPass prod

---

## C13 — Recette manuelle (2026-09-12)

> Livrés : `docs/recette.md` (checklist) + `DemoSeeder` (jeu de démo), validés le 13/09
> (`DemoSeederTest` vert, suite complète 446 Pest verte, Pint propre).

- [x] ✅ Docker Desktop relancé + intégration WSL2 active (13/09/26)
- [x] ✅ Suite Pest verte après 2 mois : 446 tests (13/09/26) — Biome/Vitest côté SPA non relancés (aucune modif SPA)
- [x] ✅ Jeu de démo chargé sur la base de dev (13/09/26) : 11 factures (4 payées, 1 partielle, 2 en retard, 1 annulée + avoir, 2 brouillons), 0 job en attente, 57 notifications, 10 mails dans Mailpit. Relancer `sail artisan migrate:fresh --seed --seeder=DemoSeeder` pour repartir d'une base propre
- [x] 🟡 Poser `SEED_ADMIN_PASSWORD` dans le `.env` local avant le seed (sinon mot de passe admin affiché une seule fois)
- [ ] 🟡 Dérouler `docs/recette.md`, cocher, remplir le journal §9, puis me dire « corrige R-nn »

## C13.6 — Lots de conformité PRD §3 (2026-09-13, session orchestrateur)

> Les 7 lots (B, A, C, D, E, F, G) sont mergés sur `main` (dernier : G, `1f92dfb`, 14/09). Détail par lot en fin de `docs/review_fable/08-ecarts-prd-portail.md`. Il ne reste que les actions manuelles ci-dessous.

- [x] 🟠 **`.env.example` modifié (lot G)** : synchroniser ton `.env` local + LastPass prod — `NOTIFICATIONS_RETENTION_DAYS` (défaut 90, ligne commentée) et `HEALTHCHECK_NOTIFICATIONS_PURGE_URL` (vide ; créer le check Healthchecks.io du cron `notifications:purge`, quotidien 4 h)
- [x] ✅ `HEALTHCHECK_NOTIFICATIONS_PURGE_URL` : check Healthchecks.io **créé** + URL consignée dans **LastPass** (14/09) — reste à pousser dans Clever Cloud au déploiement (cf. C10 ci-dessus)
- [x] ✅ `composer install` + `pnpm install` (portal-spa) faits par Claude le 14/09 : deps `intervention/image@4.3.2`, `marked@18.0.11`, `dompurify@3.4.15`, `sonner@2.0.8` en place ; suites vertes (657 Pest, 212 Vitest)
  - ⚠️ **`pnpm install` se lance depuis l'hôte WSL**, pas dans le conteneur Sail : un install conteneur crée un `.pnpm-store/` de 314 Mo à la racine du dépôt et force ensuite un re-install côté hôte
  - Corrigé au passage (`5a7f83f`) : le fuseau des tests Vitest est figé sur `Europe/Paris` ; 4 tests d'horaires échouaient sur toute machine en UTC, **y compris le CI GitHub Actions**
  - Reste : `PhotoSection.test.tsx` échoue dans le conteneur Sail (Node 24, `FormData` refuse les `File` de jsdom). Sans impact sur l'hôte ni le CI, qui sont en Node 22 — ne pas lancer Vitest dans le conteneur
- [x] 🟠 `sail artisan migrate` : nouvelle table `welcome_invitation_tokens` (lot F) ; puis `migrate:fresh --seed --seeder=DemoSeeder` pour une base de recette propre (les documents seedés génèrent désormais notifs + mails en queue → `queue:listen`)
- [x] ✅ **Décisions tranchées le 14/09** (cf. doc 08, « Avancement des lots ») — détail ci-dessous
- [ ] 🟡 Compléter les pages légales du portail (`/mentions-legales`, `/cgu`, `/accessibilite`) : raison sociale, capital, RCS/SIRET, directeur de publication, audit RGAA réel
- [ ] 🟡 (V1.5) Cellar : bucket `ecoworking-storage` en ACL **private** + `FILESYSTEM_DISK=s3` (photos de profil servies uniquement via l'API)
- [ ] 🟡 Ajouter un compte `external` à `E2eSeeder` si un parcours e2e bureau nomade est voulu (la route `/tickets` est désormais réservée aux external)

## C13.6 — Décisions tranchées (2026-09-14)

> Les 8 arbitrages en attente depuis les lots A→G sont pris. **4 n'impliquent aucun code**, 3 sont du chantier à venir, 1 est une mise à jour de doc. Reporter aussi ces décisions dans `docs/review_fable/08-ecarts-prd-portail.md` (les ⏸️) et dans le PRD.

**Sans effet sur le code** — rien à faire :

- ✅ **`invoices.billable_type`** : on **garde le polymorphisme `user` + `company`**. Permet de facturer un particulier sans lui inventer une entité fictive (external, membres sans société). L'acté PRD « tout passe par une entité » est donc **abandonné** → à corriger dans le PRD.
- ✅ **Libellé de facture** : **pas de colonne `invoices.label`**. Les `invoice_lines.description` + la période suffisent à composer l'intitulé du PDF.
- ✅ **Admin sans mot de passe ni photo (lot F)** : **validé tel quel**. L'admin ne saisit jamais de mot de passe et ne téléverse pas de photo ; un membre bloqué passe par un nouveau lien d'invitation / de réinitialisation.
- ✅ **Notes des absences `DemoSeeder` sans `created_by`** : **voulu**, comportement correct (notes internes admin, invisibles côté membre).

**Chantiers à implémenter** :

- [ ] 🔴 **Fuseau Postgres — à traiter MAINTENANT, avant la prod.** La base de dev est jetable (`migrate:fresh --seed`), donc aucune donnée à réinterpréter ; après le provisioning Clever Cloud ce serait une migration de données à risque. ⚠️ **Le correctif candidat de doc 08 (`'timezone' => 'Europe/Paris'` sur la connexion `pgsql`) est insuffisant à lui seul : il inverserait le bug** — les écritures SPA (ISO UTC) sont aujourd'hui correctes et deviendraient fausses. Le correctif porte sur la **normalisation du fuseau à l'écriture**, avec un test de non-régression sur les deux chemins (SPA en UTC / PHP en Paris : `now()`, `halfDayBounds()`, seeders, Filament).
- [ ] 🟠 **Opt-out annuaire dans le calendrier → transparence totale** : le nom du membre **réapparaît** sur ses créneaux même si `show_in_directory = false`. L'opt-out ne vaut que pour l'annuaire, pas pour l'occupation des salles (nécessité opérationnelle). Inversion de `RoomController::occupant()`. ⚠️ **À annoncer aux membres** : c'est un élargissement de la visibilité par rapport à ce qu'ils ont pu comprendre en cochant l'opt-out — à refléter dans les pages légales / la politique de confidentialité du portail.
- [ ] 🟡 **Borne photo de profil : 6000×6000 → 4000×4000.** 16 Mpx reste très au-dessus du besoin (affichage 400px max) et divise par ~2 le pic mémoire du redimensionnement.

**Écart au PRD assumé** :

- [ ] 🟡 **Abonnement actif non vérifié à la réservation (PRD §3.5.3) : statu quo, on ne vérifie rien.** À ~50 membres la régulation est sociale. ⚠️ **L'écart doit être tracé explicitement dans le PRD §3.5.3**, sinon il repassera en anomalie à chaque recette.

## Fix liens emails → domaine portail (2026-09-14) — ⚠️ `.env.example` modifié

> Bug de recette : les liens des emails de notification (« Voir mes documents », « Voir mes factures », « Régulariser ») pointaient sur `admin.ecoworking.fr`, inaccessible aux membres. Cause : `PortalNotification::portalUrl()` construisait ses URLs sur `config('app.url')`. Corrigé via `App\Support\PortalUrl` (source unique = `PORTAL_DOMAIN`). Règle codifiée en CLAUDE.md §3.7 + ADR-0004.

- [x] 🟠 **`.env.example` modifié** : synchroniser ton `.env` local + LastPass prod / Clever Cloud
  - `GOOGLE_REDIRECT_URI` ne dérive plus de `APP_URL` mais de `ADMIN_DOMAIN` → `"http://${ADMIN_DOMAIN}/auth/google/callback"` (dev). **La valeur produite est inchangée** (`https://admin.ecoworking.fr/auth/google/callback` en prod) : **rien à modifier dans la Console Google Cloud**
  - `APP_URL` : valeur inchangée, seuls des commentaires d'avertissement ont été ajoutés au-dessus
- [x] 🟠 **`APP_URL` prod vérifié** = `https://admin.ecoworking.fr` (valeur LastPass — Clever Cloud pas encore provisionné, cf. V1.5). La `GOOGLE_REDIRECT_URI` prod qui en dérivait était donc correcte, le login Google admin n'est pas impacté
- [x] 🟡 Vérifier en recette que les emails de notif arrivent bien avec un lien `portail.` (Mailpit : republier un document interne, ouvrir le mail « Nouveau document à valider »)

## Plus tard / hors MVP (pour mémoire)

- [ ] 🟡 (V1.5) Provisioning **Clever Cloud** : app, Postgres 18, Cellar, FS Bucket, DNS, env vars prod (cf. BRIEF §11, SUIVI D1-D2)

---

*Maintenu par Claude au fil des sessions. Dis-moi ce que tu as fait → je coche et je date.*
