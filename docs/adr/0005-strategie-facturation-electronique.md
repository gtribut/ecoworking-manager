# ADR-0005 : Stratégie B pour la facturation électronique (réforme 2026-2027)

## Statut

Accepté — 2026-05 (en attente confirmation finale post-discussion expert-comptable)

## Contexte

La réforme française de la facturation électronique impose à terme aux entreprises assujetties à la TVA d'émettre et de recevoir leurs factures B2B sous format électronique structuré (Factur-X PDF/A-3 + XML CII, UBL, ou autres formats équivalents) via une Plateforme Agréée (ex-PDP, désormais "PA" immatriculée par l'État).

Calendrier applicable à Ecoworking (TPE/PME) :

- **1er septembre 2026** : obligation de **réception** de factures électroniques structurées
- **1er septembre 2027** : obligation d'**émission** au format structuré via une PA

Trois stratégies ont été évaluées :

- **Stratégie A** : déléguer entièrement la facturation à une plateforme tierce (Pennylane, Sellsy, Qonto Factures…). L'outil custom envoie les "demandes de facturation" via API, la plateforme tierce gère émission, archivage, conformité et transmission via PA
- **Stratégie B** : facturer dans l'outil custom (génération PDF + bientôt Factur-X), utiliser une PA tierce **uniquement comme tuyau de transmission** vers le destinataire et le système national
- **Stratégie C** : tout faire en interne, y compris le rôle de plateforme de réception (interfaçage direct avec le PPF) → écartée d'emblée comme disproportionnée

## Décision

Adopter la **Stratégie B** : génération des factures dans l'outil custom (incluant Factur-X en V2) + transmission via une PA tierce à figer en 2026-2027.

## Conséquences

### Bénéfices

- **Maîtrise complète des données** : la facturation reste dans la base Postgres du projet, pas chez un prestataire
- **Cohérence du flux** : abonnements, achats ponctuels, réservations sont déjà modélisés dans l'outil → la facture en est la conséquence naturelle, sans aller-retour avec un système externe
- **Coût récurrent contenu** : une PA "tuyau" coûte ~10-30€/mois selon le prestataire vs ~30-50€/mois pour une plateforme de facturation complète
- **Apprentissage technique** : implémentation Factur-X (PDF/A-3 + XML CII) est une montée en compétence concrète sur les standards e-invoicing européens
- **Indépendance** : changer de PA en 2028 ne nécessite pas de migrer toute la facturation, juste de rebrancher l'API de transmission
- **Reporting interne** : tableaux de bord, KPIs, suivi de paiements custom faciles à construire sur des données possédées

### Trade-offs assumés

- **Charge dev plus importante** que la Stratégie A : il faut implémenter Factur-X (XML CII conforme), gérer la numérotation, les mentions obligatoires (incluant les nouvelles 2026), la transmission API à la PA, les webhooks de statut, l'archivage légal 10 ans
- **Responsabilité de conformité fiscale** plus forte côté projet (vs Stratégie A où on délègue à un prestataire qui assume sa part)
- **Maintenance perpétuelle** : si les formats évoluent (nouvelles mentions, nouveaux schémas XML), il faudra mettre à jour l'outil
- **Dépendance à la maturité des libs PHP Factur-X** : l'écosystème PHP est moins mature que Java/Python sur ce sujet → la lib `atgp/factur-x` est à valider, fallback possible via sidecar Python ou API tierce de génération
- **Pas de compta intégrée** : l'expert-comptable continue d'utiliser son outil (Pennylane, Tiime ou autre) → un export/synchro à prévoir (Pennylane API en lecture pour les paiements bancaires reçus, V3)

### Conséquences sur les autres décisions

- Tables `invoices` et `invoice_lines` modélisées avec toutes les colonnes nécessaires aux mentions obligatoires
- Champs `factur_x_xml_path`, `pa_transmission_id`, `pa_transmission_status` ajoutés dès le MVP (NULL en MVP, utilisés en V2)
- Numérotation chronologique sans trou implémentée dès le MVP (compteur DB avec `lockForUpdate()`)
- Statuts de paiement gérés manuellement par l'admin en MVP (cohérent avec le process actuel Ecoworking)
- Implémentation Factur-X en V2 (avant septembre 2027, planifié 2026 ou début 2027)

## Plan d'implémentation par phase

### MVP (avant septembre 2026)

- Génération PDF classique des factures (sans XML structuré)
- Numérotation chronologique sans trou (`EW-YYYY-NNNNN`)
- Toutes les mentions obligatoires CGI art. 289 + nouvelles 2026 modélisées en DB (catégorie d'opération, option TVA débits, adresse de livraison si différente, SIREN client, etc.)
- Statuts de paiement manuels (admin clique "payé" après réception SEPA/virement/CB)
- Archivage des PDF sur Cellar (S3) avec rétention 10 ans

### V1.5 — Réception (avant septembre 2026)

- Capacité de **recevoir** une facture électronique d'un fournisseur (EDF, etc.)
- Probablement déléguée à l'expert-comptable / Pennylane en réception → décision à confirmer post-discussion

### V2 — Émission conforme (avant septembre 2027)

- Implémentation Factur-X complète : génération PDF/A-3 avec XML CII embedded
- Choix de la PA à figer (1er trimestre 2027) après publication de la liste définitive sur impots.gouv.fr et discussion expert-comptable
- Branchement API de la PA pour transmission
- Webhooks de statut transmission (envoyé, reçu, accepté, rejeté, etc.)
- Tests de conformité avec quelques factures réelles avant bascule complète

### V3 — Confort (optionnel)

- Stripe Cashier pour paiements en ligne (CB + SEPA récurrent)
- Sync Pennylane API pour récupération automatique des paiements bancaires reçus
- Relances impayés automatisées

## Alternatives considérées

### Stratégie A — délégation à Pennylane (ou équivalent)

**Pourquoi écarté en première intention** :
- Perte de contrôle sur le format et le moment d'émission
- Coût récurrent supérieur (~30-50€/mois)
- Dépendance forte au prestataire (migration coûteuse plus tard)
- Synchro complexe entre les souscriptions/résa dans l'outil et la facturation déclenchée chez Pennylane
- Moins d'apprentissage technique pour le porteur

**Reste un plan B viable** si :
- L'implémentation Factur-X PHP s'avère trop fragile à maintenir
- L'expert-comptable recommande fortement cette voie pour des raisons de simplicité fiscale
- Le coût/temps de la Stratégie B dépasse ce qui est raisonnable pour un dev solo

### Stratégie C — implémentation directe avec le PPF

**Pourquoi écarté** :
- Le PPF (Portail Public de Facturation) a vu son rôle réduit dans la réforme : il n'est plus une plateforme d'échange complète, mais un annuaire + concentrateur e-reporting
- Toutes les factures B2B doivent transiter par une **PA agréée** (immatriculée), pas par le PPF directement
- Tenter une intégration directe = travail bas niveau pour zéro avantage business

## Points en suspens

À confirmer post-discussion avec l'expert-comptable :

1. Quelle PA cible (liste officielle sur impots.gouv.fr à consulter en 2027)
2. Pennylane sera-t-elle immatriculée PA au moment de la bascule ? Si oui, basculer vers Stratégie A peut redevenir intéressant car déjà utilisée pour la compta
3. Statégie de réception 2026 : déléguée à l'expert-comptable / Pennylane, ou implémentée dans l'outil ?
4. Workflow exact : qui valide une facture avant émission ? Validation manuelle obligatoire ou émission automatique ?

## Références

- Réforme facturation électronique (impots.gouv.fr) : https://www.impots.gouv.fr/professionnel/je-passe-la-facturation-electronique
- Documentation Factur-X (FNFE-MPE) : https://fnfe-mpe.org/factur-x/
- Lib PHP `atgp/factur-x` : à valider au moment de l'implémentation V2
