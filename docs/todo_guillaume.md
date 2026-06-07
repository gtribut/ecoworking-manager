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

- [ ] 🟡 Créer un compte **Brevo** (ex-Sendinblue) + valider le **domaine expéditeur** `ecoworking.fr` (SPF/DKIM)
- [ ] 🟡 Générer une **clé API Brevo** → la placer dans `.env` prod : `BREVO_API_KEY=…`
- [ ] 🟡 Décider du transport : SMTP Brevo (`MAIL_MAILER=smtp` + creds Brevo) **ou** driver API — me dire lequel pour que je finalise `config/mail.php` / `services.php`
- [ ] 🟡 S'assurer qu'un **worker de queue** tourne en prod (`queue:work`, queues sur Postgres ADR-0007) — sinon les emails ne partent pas

---

## C10 — Observabilité (Sentry, Pulse, Better Stack, Healthchecks)

> Contexte : packages **installés et câblés** côté code (Sentry back+front, Pulse,
> ping Healthchecks sur les crons, endpoint `/up` pour l'uptime). Tout est **no-op
> tant que les variables d'env sont vides** → il reste à créer les comptes et à
> renseigner les clés. Placeholders déjà dans `.env.example`.

### Sentry (erreurs + perf)
- [ ] 🟡 Créer un compte **Sentry** (free tier 5k events/mois) + **2 projets** : `ecoworking-laravel` et `ecoworking-portal-react`
- [ ] 🟡 Renseigner en prod : `SENTRY_LARAVEL_DSN=…` (back) et `VITE_SENTRY_DSN=…` (front, injecté au build Vite). Ajuster `SENTRY_TRACES_SAMPLE_RATE` (0.2 par défaut)
- [ ] 🟡 (CI) Créer un **`SENTRY_AUTH_TOKEN`** pour l'upload des source maps + tag release (à brancher dans le pipeline le jour du déploiement)

### Laravel Pulse (perf interne)
- [ ] 🟡 Aucune action de compte. Vérifier en prod que `/pulse` n'est accessible **qu'aux admins** (gate `viewPulse` posé sur `User::isAdmin`) et que `PULSE_ENABLED=true`
- [ ] 🟡 Prévoir le **trim** des données Pulse (commande `pulse:check`/scheduler par défaut) si volume

### Better Stack (uptime externe)
- [ ] 🟡 Créer un compte **Better Stack** (free : 3 monitors) → monitorer `https://portail.ecoworking.fr/up` (endpoint santé Laravel déjà exposé)
- [ ] 🟡 Configurer les **alertes** (email / Slack)

### Healthchecks.io (surveillance des crons)
- [ ] 🟡 Créer un compte **Healthchecks.io** (free : 20 checks) + **1 check par cron** :
  - facturation mensuelle → `HEALTHCHECK_MONTHLY_BILLING_URL=…`
  - bascule factures en retard → `HEALTHCHECK_OVERDUE_INVOICES_URL=…`
  - (le scheduler ping l'URL en succès et `…/fail` en échec — déjà câblé)
- [ ] 🟡 Régler la **période/grâce** de chaque check sur la fréquence réelle (mensuel / quotidien)

---

## C9.1 — Push Google Calendar (différé V1.5)

> Décision 2026-06-07 : le **push sortant** des résas salles vers un calendrier
> Google dédié est **reporté en V1.5** (avec le provisioning Clever Cloud). Les flux
> **iCal d'abonnement** côté membre (C9.2) sont, eux, **livrés** et ne dépendent
> d'aucun service Google.

- [ ] 🟡 (V1.5) Créer un **service account Google** + calendrier dédié « Ecoworking — Salles », partager en lecture publique
- [ ] 🟡 (V1.5) Me le signaler pour que j'ajoute `google/apiclient` + le `SyncBookingToGoogleCalendarJob` (la colonne `bookings.google_calendar_event_id` est déjà prête)

---

## Plus tard / hors MVP (pour mémoire)

- [ ] 🟡 Dev local sous-domaines : ajouter `admin.ecoworking.test` / `portail.ecoworking.test` dans le `hosts` Windows **le jour où** on testera le routing par sous-domaine en local (en dev courant, les domaines restent nuls → tout sur `localhost`)
- [ ] 🟡 (V1.5) Provisioning **Clever Cloud** : app, Postgres 18, Cellar, FS Bucket, DNS, env vars prod (cf. BRIEF §11, SUIVI D1-D2)

---

*Maintenu par Claude au fil des sessions. Dis-moi ce que tu as fait → je coche et je date.*
