# 01 — Architecture & documentation

> Sources lues intégralement : BRIEF.md, PRD.md, data_model.md, SUIVI.md, todo_guillaume.md,
> les 9 ADRs, CLAUDE.md, routes, bootstrap/app.php, config/domains.php, providers — plus
> sondages code pour vérifier les « ✅ » de SUIVI.

## 1. Synthèse architecture

### Verdict : architecture saine, proportionnée, remarquablement documentée

L'hybride **Filament (admin) + SPA React (portail)** sur **une seule app Laravel, deux
sous-domaines** (ADR-0002/0004) est très pertinent pour ce contexte (dev solo, mono-tenant,
~50 membres, usage admin-heavy) :

- **ADR-0002** justifie honnêtement l'hybride (gain Filament 2-3 mois vs objectif
  d'apprentissage React) et écarte les alternatives avec de vrais arguments (Inertia,
  tout-Filament, deux apps).
- **ADR-0004** (sous-domaines) apporte un vrai bénéfice sécurité (isolation cookies,
  `SESSION_DOMAIN=null`), vérifié dans le code : `config/domains.php` + pattern « domaine
  nullable en test » (`phpunit.xml` force les domaines vides) est une solution élégante au
  problème classique des tests HTTP avec `Route::domain()`.
- **ADR-0003** (Sanctum SPA) : correct et correctement implémenté (`statefulApi()`,
  `auth:sanctum` sur 100 % de `routes/api.php`).
- **ADR-0007** (pas de Redis) est exemplaire : volumétrie chiffrée, signaux de bascule
  listés, plan de migration trivial. La « note méta » sur le sur-outillage est une bonne
  pratique rare.
- **ADR-0005** (stratégie B facturation électronique) : trade-offs lucides, fallback
  documenté. **ADR-0008** (versions modernes) : argumenté, avec plan de rétrogradation.

### Risques / incohérences architecturales

1. **Le serving de la SPA en production n'existe pas et n'est tracé nulle part.**
   BRIEF §6 et ADR-0004 spécifient `Route::get('/{any?}', fn () => view('portal-spa'))` sur
   le domaine portail + build dans `public/portal/`. Or `routes/web.php` n'a **aucune route
   catch-all SPA**, pas de `portal-spa.blade.php`, pas de `public/portal/`. La SPA ne tourne
   que via le dev server Vite. Ni SUIVI C5 (« ✅ complet ») ni les tâches V1.5 (D1-D6) ne
   portent cette intégration. **Chaînon manquant qui surgira au déploiement.**
2. **`composer.json` : `"php": "^8.3"`** alors qu'ADR-0008 prescrit `^8.5`. Un install
   passerait sur un runtime incapable d'exécuter le code (CI et Sail sont en 8.5).
3. **Route `/` non contrainte par domaine** (`routes/web.php:9`, `view('welcome')`) : elle
   répond sur les deux sous-domaines, y compris `portail.*`. À nettoyer avec le point 1.
4. **BRIEF §8 obsolète sur le 2FA admin** : il décrit « Fortify + device memory 30 jours »
   alors que la réalité est le MFA natif Filament (challenge à chaque connexion, colonnes
   `app_authentication_*`). Bien documenté ailleurs (SUIVI C3.1), mais BRIEF à mettre à jour.

## 2. Qualité documentaire

### Points forts

- **Hiérarchie des sources énoncée** (PRD prime en fonctionnel, BRIEF en infra, SUIVI = statut).
- **Traçabilité exceptionnelle** : 26 questions PRD §7 tranchées avec renvois croisés,
  décisions datées, marquage `🟡 ajout Claude` vs specs d'origine.
- **data_model.md de très haute qualité** : contraintes classées par couche (U/A/D),
  backstops DB explicites, section RGPD, champs Factur-X anticipés.
- `todo_guillaume.md` : convention secrets (Claude ne lit jamais les valeurs) — excellente hygiène.

### Contradictions et zones floues

| # | Incohérence | Où |
|---|---|---|
| 1 | **`APP_URL` prod contradictoire** : BRIEF §11.5/§13 = `https://portail.ecoworking.fr` (liens emails membres) ; todo_guillaume + `.env.example` = `https://admin.ecoworking.fr`. Impact réel sur les URLs des emails. **À trancher.** | BRIEF §11.5/§13 vs `.env.example` |
| 2 | **PostgreSQL 16 vs 18** dans le BRIEF lui-même : diagramme §6 et runbook §11.3 (`--version 16`, plan xs_sml ~7 €) vs le reste (18, plan S ~15 €) | BRIEF §6, §11, §11.3 |
| 3 | URL uptime `https://app.ecoworking.fr/up` — sous-domaine `app.` inexistant ; le monitor réel est sur `portail.` | BRIEF §16 |
| 4 | `FILAMENT_DOMAIN` cité au BRIEF mais inexistant (le code utilise `ADMIN_DOMAIN` via `config/domains.php`) | BRIEF §13 |
| 5 | `SESSION_LIFETIME` : BRIEF = 240 ; `.env.example` = 120. BRIEF §17 promet « timeout membre 7 j remember_me » — non différencié aujourd'hui | BRIEF §13/§17 |
| 6 | `AWS_DEFAULT_REGION` : BRIEF = `eu-west-1`, `.env.example` = `us-east-1` ; `AWS_ENDPOINT` (Cellar) absent de `.env.example` | BRIEF §13 |
| 7 | **Packages « essentiels » BRIEF §5.1 non installés** : `spatie/laravel-backup`, `laravel-settings`, `laravel-data`, `intervention/image`, `telescope`. data_model §4.6 liste la table `settings` — aucune migration ne la crée | BRIEF §5.1, data_model §4.6 |
| 8 | **BRIEF §2 vs §18 vs SUIVI** : §2 inclut en MVP *Annonces & événements*, *Plan interactif*, *Annuaire*, *Documents* ; §18 les omet et SUIVI n'a aucun code de tâche pour eux (cf. §4) | BRIEF §2/§18, SUIVI |
| 9 | ADRs « immuables » modifiés en place (0001 — fichier encore nommé `choix-laravel-11` — et 0008 réécrits) ; les notes d'évolution rendent ça acceptable mais la règle affichée n'est pas suivie | adr/README.md |
| 10 | PRD §3.2 « Magic link — Q6 : V1 » — aucune trace dans SUIVI ni le code. Idem flux changement email/mdp (§3.4.5) renvoyés « hors périmètre » sans tâche porteuse | PRD §3.2/§3.4.5 |

## 3. Alignement docs ↔ code (sondages)

**Très bon taux de conformité.** Vérifiés réels : les 14 services annoncés, les 15 Resources
Filament, tous les endpoints API décrits (tous sous `auth:sanctum`), les 32 migrations dont
`invoice_line_subscriptions` (C6.5), la morph map identique à data_model §5, les 20 Policies,
le rendu JSON 409/422, Sentry/Pulse/Healthchecks, les crons, le MFA Filament `isRequired`.
Comptage de tests honnête (235 aujourd'hui vs « 232 » au 07/06).

**Écarts** : serving SPA prod (cf. §1.1) ; `composer.json` `^8.3` ; pas de Resource Filament
`Ticket` ni `DeskAbsence`, pas de Pages/Widgets custom (dashboard admin = défaut Filament,
loin du PRD §4.1) ; `spatie/laravel-query-builder` recommandé au BRIEF mais non utilisé (choix
plus simple assumé, non répercuté).

## 4. Périmètre fonctionnel — le constat majeur

Le découpage MVP/V1.5/V2/V3 est sain sur le fond (exclusions argumentées, V2 adossée à
l'échéance légale sept. 2027, Q26 bien gérée, report C9.1 documenté). **Mais un pan entier du
périmètre MVP « papier » n'a jamais reçu de codes de tâches** — SUIVI affiche « prochaines
étapes : C11 puis V1.5 », ce qui crée une illusion de complétude :

| Module PRD (MVP) | Réf. | État code |
|---|---|---|
| Annuaire des coworkers + plan des étages SVG | PRD §3.7, §4.12 | rien |
| Annonces & événements côté portail (+ inscriptions) | PRD §3.3.2, §4.11 | Resource admin seule, aucun endpoint portail |
| Documents internes à valider (workflow, bloc accueil) | PRD §3.3.2, §5.3 | Resource admin seule, `member_document_validations` jamais alimentée |
| Documents administratifs côté portail | PRD §3.6.3 | pas d'endpoint |
| Dashboard admin (KPIs, alertes) | PRD §4.1 | dashboard Filament par défaut |
| Vue « Occupation du jour » | PRD §4.8.4 | absente |
| Gestion tickets côté admin (Resource dédiée, consommation manuelle) | PRD §4.8.1 | pas de `TicketResource` |
| Audit log UI, rôles UI, Settings | PRD §4.13-4.15 | absents |
| Magic link (Q6 « V1 »), flux changement email/mdp | PRD §3.2, §3.4.5 | absents |
| Anonymisation RGPD (action admin) | PRD §5.6 | colonne `anonymized_at` prête, pas d'action/service |

**Recommandation** : soit créer une phase `C12 — Modules portail/admin restants`, soit amender
explicitement BRIEF §2/§18 pour requalifier ces modules en V1.5/V2 — **dé-scoper consciemment
plutôt que par omission**.

### Point calendrier

- **Obligation de réception e-factures : 1er septembre 2026** (dans 2 mois). ADR-0005 la
  classe « V1.5 — à confirmer post-expert-comptable », point en suspens toujours ouvert,
  aucune tâche D-xx ne la porte. Même si la réponse probable est « déléguée au comptable »,
  la décision doit être **actée et datée maintenant** (→ todo_guillaume.md).
- Côté positif : le rythme réel écrase le plan (MVP prévu ~6 mois, quasi atteint en ~1 mois
  de sessions). Le risque n'est pas le retard mais l'illusion de complétude ci-dessus.

## 5. Recommandations par priorité

### P1 — avant de déclarer le MVP terminé
1. Réconcilier le périmètre MVP (décision explicite module par module, MàJ des 3 docs).
2. Câbler le serving SPA production (catch-all, Blade, build `public/portal/`, exclusion `/api`) et le tracer. Retirer/contraindre la route `/` welcome.
3. Trancher `APP_URL` prod (impact direct liens emails membres).
4. Acter la stratégie de réception e-factures avant le 01/09/2026.

### P2 — dette documentaire — ✅ soldée le 2026-07-03
5. ✅ Passe de correction BRIEF faite : runbook §11.3 Postgres 18 + plan aligné §21 (prix), `app.` → `portail.` (§16), `FILAMENT_DOMAIN` retiré (§13), `SESSION_LIFETIME` 240→120 + §17 sessions réalignés (remember Fortify, pas de « 7 j »), `.env.example` AWS aligné (`eu-west-1` + `AWS_ENDPOINT`), 2FA admin §8 = MFA natif Filament, colonne **Statut** sur les packages §5.1 (+ mentions Telescope corrigées, magic link §8 acté livré).
6. ✅ `composer.json` `"php": "^8.5"` (fait en 2e passe le 02/07).
7. ✅ Table `settings` retirée de data_model §4.6 (décision C12.8b du 03/07).
8. ✅ `0001-choix-laravel-11.md` → `0001-choix-laravel.md` + politique d'évolution des ADRs clarifiée dans `adr/README.md` §Maintenance (amendement daté vs supersession).

### P3 — opportunités
9. C11.3/C11.4 (e2e + axe-core) : seuls ⬜ du MVP alors que « RGAA AA » est revendiqué (C5.8 ✅) — outiller avant toute annonce de conformité.
10. Ajouter en V1.5 : `spatie/laravel-backup` (D3 l'implique), headers sécurité §17 (CSP/HSTS — aucun middleware constaté).
11. SUIVI : fusionner le bloc « Position actuelle » et la ligne « Dernière mise à jour » (double résumé = dérive).
12. ADR-0010 court pour la décision « facturation par entité + `invoice_line_subscriptions` » (structurante, aujourd'hui seulement dans PRD §5.1 + data_model).

## Conclusion

Projet dans le **top décile** en qualité documentaire et discipline architecture/code. Les deux
vrais sujets : le **périmètre fantôme** et le **serving SPA prod non tracé**. Le reste relève
de la dérive documentaire normale d'un projet qui avance vite, résorbable en une passe de
synchronisation.
