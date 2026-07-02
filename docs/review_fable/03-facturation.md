# 03 — Facturation & argent

> Audit des services de facturation, observer paiements, commandes/scheduler, modèles, Policy,
> Resources Filament, migrations, job/template PDF et des 3 suites de tests concernées.
> Référentiel : CLAUDE.md §3.6 (règles non négociables), PRD §5/§6, data_model.

## 1. Conformité aux règles §3.6

| Règle | Statut | Commentaire |
|---|---|---|
| Numérotation sans trou (`SELECT … FOR UPDATE`) | ✅ avec réserve | Lock correct, pas de trou au rollback (transaction imbriquée = savepoint). MAIS trou possible par double émission concurrente (F1) |
| Brouillon ne consomme jamais le compteur | ✅ | Numéro posé uniquement à l'émission/annulation ; testé |
| `DECIMAL(10,2)`, jamais FLOAT | ✅ (DB) | 100 % decimal en base. Calculs PHP en `float` + `round()` — correct en pratique, bcmath serait plus rigoureux (F14) |
| Montants figés à l'émission | ✅ | Recalcul défensif à l'émission, Policy `update` ⇒ draft only, Filament verrouillé, API read-only |
| Facture émise jamais supprimée | ✅ avec réserve | `InvoicePolicy::delete` ⇒ draft only, testé. Réserve `DeleteBulkAction` (F9) |
| Avoir : négatif, lié, numéroté | ✅ avec réserves | Miroir correct, double self-FK. MAIS pas de PDF (F3), double annulation possible (F2) |
| Abonnements : prix catalogue courant + remise entité | ✅ | `planFor()` relit `offer.unit_price_ht` ; remise avant TVA ; testé |
| Prorata ROUND_HALF_UP, bornes incluses | ✅ | `round()` PHP = HALF_UP ; `diffInDays + 1` ; vrais jours du mois. Février/31 j non testés |
| Idempotence mensuelle (applicatif + UNIQUE DB) | ✅ avec réserves sérieuses | UNIQUE en place ; mais grain entité bloquant (F5) et suppression de brouillon = blocage silencieux (F4) |
| Purchases : prix snapshoté | ✅ | `PurchaseService::createFromOffer` fige prix/TVA — ⚠️ mais le service n'est branché nulle part, cf. [04](./04-backend-php.md) BCK-C1 |
| Audit log entités sensibles | ⚠️ | `saveQuietly()` contourne activitylog sur les transitions de paiement (F10) |

## 2. Findings

### 🔴 ÉLEVÉ

**F1 — Double émission concurrente ⇒ trou de numérotation (violation CGI art. 289)**
`app/Services/IssueInvoiceService.php:35` — la garde `status !== Draft` est évaluée **hors
transaction**, et la facture n'est jamais relue avec `lockForUpdate()` dans la transaction.
*Scénario* : deux admins (ou un double-clic Livewire) émettent le même brouillon → les deux
passent la garde, le compteur délivre `EW-2026-00007` puis `00008`, la seconde sauvegarde
écrase la première → `00007` n'existe sur aucune facture. Trou définitif.
*Fix* : dans la transaction, `Invoice::query()->lockForUpdate()->findOrFail($id)` +
re-vérification du statut (ou `UPDATE … WHERE status='draft'` avec test des lignes affectées).
*(Corroboré indépendamment par deux revues.)*

**F2 — Double annulation concurrente ⇒ deux avoirs pour une même facture**
`app/Services/CancelInvoiceService.php:32-42` — mêmes gardes hors transaction. Deux
annulations simultanées produisent deux avoirs numérotés (sur-crédit comptable),
`cancellation_credit_note_id` ne pointant que vers le dernier. Même fix que F1.

**F3 — L'avoir n'a jamais de PDF**
`CancelInvoiceService.php:44-98` — l'avoir est créé sans passer par `IssueInvoiceService` et
`GenerateInvoicePdfJob` n'est dispatché que dans `IssueInvoiceService.php:76`. `pdf_path`
reste `null`, le portail répond 404. Un avoir est une pièce comptable au même titre qu'une
facture — il doit être matérialisé.

**F4 — Suppression d'un brouillon récurrent ⇒ entité définitivement infacturable sur le mois**
`EditInvoice.php` (DeleteAction autorisée sur draft) + `MonthlyBillingService.php:291-298`.
*Scénario* : le cron du 1er génère le brouillon de la société X ; l'admin le supprime
(montant faux, test…) en pensant régénérer. La suppression est un **soft delete** : les
`invoice_line_subscriptions` **survivent** → `alreadyBilled()` reste vrai, l'index UNIQUE
aussi → `generateForEntity()` retourne `null` pour toujours, **sans erreur ni log**. La
société X n'est jamais facturée ce mois-ci.
*Fix* : hook `deleting`/`deleted` sur le brouillon qui purge les liaisons (ou exclure les
lignes de brouillons supprimés du check et de l'unicité).

### 🟠 MOYEN

**F5 — Idempotence au grain entité : pas de rattrapage possible**
`MonthlyBillingService.php:91` — `alreadyBilled()` skippe l'entité entière si **un seul**
abonnement est déjà facturé. *Scénario* : abo A facturé le 1er ; abo B créé le 10 ; toute
relance ignore l'entité → B jamais facturé sur le mois, sans signal.
*Fix* : filtrer les abonnements déjà facturés et facturer le reliquat.

**F6 — Changement de facture sur un paiement : l'ancienne facture jamais recalculée**
`PaymentObserver.php:24-27` — `updated()` ne resynchronise que le **nouveau** `invoice_id`.
*Scénario* : paiement 100 € saisi sur A (passée `paid`), corrigé vers B → B passe `paid`,
mais A **reste `paid` avec `amount_paid=100`** à jamais.
*Fix* : si `wasChanged('invoice_id')`, recalculer aussi `getOriginal('invoice_id')`.

**F7 — Oscillation `overdue` ↔ `partially_paid` ⇒ notifications de retard dupliquées**
`InvoicePaymentService.php:43-45` (tout `paid>0` ⇒ `PartiallyPaid`, sans regarder l'échéance)
+ `UpdateOverdueInvoicesCommand.php:30` (re-sélectionne les `partially_paid` échues et
**re-notifie**). Chaque paiement partiel sur une facture échue déclenche une nouvelle
notification de retard — contredit le commentaire « une seule notif par facture ».
*Fix* : ne jamais quitter `Overdue` tant que non soldée, ou colonne `overdue_notified_at`.

**F8 — PDF : TVA non ventilée par taux**
`resources/views/invoices/pdf.blade.php` — une seule ligne « TVA » agrégée. Dès qu'une
facture porte plusieurs taux (le schéma le permet), la ventilation base HT / taxe **par taux**
(art. 242 nonies A ann. II CGI) manque. À corriger avant d'avoir des offres à taux différents.

**F9 — `DeleteBulkAction` sans garde per-record (bombe à retardement)**
`InvoicesTable.php` et `PaymentsTable.php`. Les bulk actions Filament 5 ne vérifient que
`deleteAny()` et ne font **aucun check per-record par défaut**. Aujourd'hui les Policies
n'ont pas de `deleteAny` → action refusée (sûr **par accident**, fonctionnalité morte). Le
jour où quelqu'un ajoute `deleteAny() => isAdmin()`, la suppression en masse de **factures
émises** devient possible sans passer par `InvoicePolicy::delete`.
*Fix* : retirer l'action, ou `deleteAny()` + `->authorizeIndividualRecords('delete')`.

**F10 — Transitions de paiement non auditées**
`InvoicePaymentService.php:26` — `saveQuietly()` supprime les events ⇒ activitylog ne
journalise aucune transition `sent → partially_paid → paid` ni les recalculs. §3.4 exige
l'audit sur Invoice/Payment (le paiement lui-même est audité, la trace côté facture est perdue).

**F11 — Paiements : montant non borné, factures annulées/avoirs encaissables**
`PaymentForm.php` — `amount` sans `minValue` (0 et négatif acceptés, pas de CHECK DB) et le
select facture n'exclut ni les `cancelled` ni les avoirs. Pas de Form Request : la validation
repose entièrement sur ce schéma Filament.

### 🟡 FAIBLE

- **F12** — Course sur la création du compteur d'une nouvelle année
  (`InvoiceNumberingService.php:27-29` : `lockForUpdate()->firstOrCreate()` ne verrouille
  rien si la ligne n'existe pas → violation UNIQUE transitoire possible, une fois par an au
  pire, sans trou). Retry ou upsert préalable.
- **F13** — Écart d'un centime possible entre somme des quote-parts
  (`MonthlyBillingService.php:188`, arrondi par abonnement) et total de ligne
  (`InvoiceLineCalculator`, arrondi global). Sans impact comptable (traçabilité), mais un
  contrôle de cohérence échouerait.
- **F14** — Arithmétique en float (accumulations + `number_format`). Correct aux magnitudes
  du projet, fragile par principe — bcmath/centimes entiers plus sûrs.
- **F15** — Brouillons dégénérés émettables : brouillon **sans ligne** émis à 0,00 €
  (numéro consommé) ; pas de `minValue` sur `unit_price_ht` → lignes négatives possibles,
  « facture négative » contournant le flux avoir. Gardes à ajouter.
- **F16** — `generateMonth` non résilient : une exception sur une entité interrompt le
  `each` → entités suivantes non facturées sur ce run (le healthcheck pingera `/fail`,
  heureusement). try/catch par entité.
- **F17** — Divers : avoir figé au statut `sent` (cosmétique) ; annulation d'une facture
  `paid` laisse `amount_paid > 0` sans mécanique de remboursement (à documenter) ; PRD
  « choisir date d'émission + échéance » vs hardcode `now()`/`+14 j` (cohérent avec la règle
  figée PRD, mais l'écran décrit ne l'est pas).

## 3. Couverture de tests — état réel

**Bien couvert** : séquence de numérotation multi-années, brouillon sans compteur, figement
totaux + snapshot adresse, refus de ré-émission, annulation → avoir miroir lié, interdiction
de suppression (Policy), recalcul `amount_paid`/statuts, passage overdue, regroupement par
entité, prorata (bornes, ligne séparée), remise entité, idempotence « second passage », PDF
généré, actions Filament, verrouillage post-émission.

**Trous** (correspondant aux findings) : aucune simulation de concurrence (F1/F2) ;
suppression d'un brouillon récurrent puis régénération (F4) ; rattrapage d'un abonnement
ajouté en cours de mois (F5) ; changement d'`invoice_id` d'un paiement (F6) ; re-notification
overdue après paiement partiel (F7) ; PDF d'un avoir (F3) ; multi-taux de TVA ; prorata
février/31 jours ; violation directe du UNIQUE backstop ; cohérence quote-parts vs ligne (F13).

## Priorités

1. **F1/F2** — lock + re-check en transaction (quelques lignes chacun).
2. **F4** — purge des liaisons à la suppression d'un brouillon.
3. **F3** — dispatch du PDF pour l'avoir.
4. **F5, F6, F7** — grain d'idempotence, observer paiements, anti-oscillation.
5. **F8** — ventilation TVA par taux (avant toute offre multi-taux).
