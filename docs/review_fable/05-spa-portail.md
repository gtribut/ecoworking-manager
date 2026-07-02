# 05 — SPA portail (React/TS) & contrats API

> Lu : `portal-spa/` en entier (features, components, lib, config) + contrôleurs/Requests/
> Resources API Laravel pour la cohérence des contrats.

## Points forts

- **Conventions CLAUDE.md §4.2 très bien respectées** : `strict` + `noUncheckedIndexedAccess`
  + `verbatimModuleSyntax`, zéro `any` (une seule assertion contrôlée dans `queryClient.ts`),
  aucun default export, alias `@/*` cohérent, organisation par feature exemplaire.
- **État serveur 100 % TanStack Query** : aucun useEffect-fetch. Les `useEffect` restants
  sont légitimes (thème, listeners avec cleanup, `reset` RHF).
- **A11y sérieusement traitée** : skip link, `:focus-visible` global, `prefers-reduced-motion`,
  `lang="fr"`, tables avec `caption` sr-only et `scope`, `aria-live` sur les zones de
  disponibilité, **alternative liste au calendrier** (`AgendaSlotList` — exigence §3.5 honorée).
- **Gestion 409/422 propre bout en bout** (exceptions métier mappées dans `bootstrap/app.php`,
  affichage de `body.message` — le 409 est testé).
- **Tests Vitest pertinents** : 32 cas, MSW `onUnhandledRequest:'error'`, harnais propre,
  flux critiques couverts (resident vs external, 2FA, 422 « plus de ticket »).
- Attentions appréciables : `keepPreviousData` sur les paginations, `Intl.NumberFormat`,
  montants `DECIMAL` désérialisés en `string` côté TS (fidèle au back).

## Findings

### 🔴 Critique
Aucun.

### 🟠 Majeur

1. **Pas de gestion globale de session expirée (401/419)** — `lib/http.ts` (pas
   d'interceptor), `queryClient.ts:12-18`. Session expirée en cours d'usage : alertes
   d'erreur génériques sans redirection login, et mutations en **419 « CSRF token mismatch »
   affiché brut** à l'utilisateur. *Reco* : interceptor → 401 hors `/api/user` = invalider
   `authQueryKey` ; 419 = re-`ensureCsrfCookie()` + rejouer une fois.
2. **Messages de validation Laravel en anglais** — ni `lang/` ni `laravel-lang` (vérifié)
   alors que `APP_LOCALE=fr`. Tout 422 de Form Request remonte en anglais dans une UI 100 %
   française. *Reco* : publier `lang/fr` côté back.
3. **Solde de tickets non invalidé après résa/annulation de salle payante** —
   `bookings/useBookings.ts:36-58` n'invalide que `['bookings']` et `['rooms']` : le solde
   affiché sur `TicketsPage` reste périmé (le pendant desks, `useTickets.ts`, le fait
   correctement). *Reco* : `invalidateQueries({ queryKey: ['tickets'] })`.
4. **2FA : impossible d'utiliser un code de récupération** — `LoginPage.tsx:84-111` : l'API
   front supporte `recovery_code` mais l'UI n'offre que le champ TOTP (Zod `min(6)` exclut
   les recovery codes). Un membre qui a perdu son téléphone est bloqué. Pas de bouton retour
   vers le login non plus.
5. **`NotificationBell` : pattern ARIA menu incomplet** — `aria-haspopup="menu"` +
   `role="menu"/menuitem` sans navigation flèches ni gestion de focus (APG). *Reco* : retirer
   les rôles menu (panneau = simple région, Tab natif) et gérer le focus ouverture/fermeture.

### 🟡 Mineur

6. **Thème « Automatique (système) » ne suit pas le système** — `AuthContext.tsx:31-34` :
   `theme: null` force le clair au lieu d'écouter `prefers-color-scheme` ; flash clair au boot.
7. **Contrastes AA douteux** : `text-neutral-400` sur blanc ≈ 2,6:1 pour des textes porteurs
   d'information (« Occupé » `BookingForm.tsx:235,283`, « Indisponible » `InvoicesPage.tsx:104`,
   horodatage `NotificationBell.tsx:125`). Passer à `neutral-500`+. Vérifier blanc sur
   `brand-600` (≈ 4,2:1, limite en `text-sm`).
8. **Pas de `document.title` par page ni gestion de focus au changement de route** (RGAA 8.5/12.x).
9. **Pas d'ErrorBoundary** — erreur de rendu = écran blanc (`Sentry.ErrorBoundary` est gratuit).
10. **Contrat TS inexact sur la révocation iCal** — `destroy` renvoie `{enabled:false}` sans
    clé `urls`, mais `calendar/types.ts` déclare `urls: {...} | null` et le hook met ce
    payload en cache tel quel. Aligner back (`'urls' => null`) ou type.
11. **`Paginated<T>` dupliqué** (`bookings/types.ts` et `invoices/types.ts`, copier-coller
    avoué en commentaire) → extraire dans `src/lib/api-types.ts`.
12. **`BookingForm`/`DeskBookingForm` en `useState` manuels** — écart avec la convention
    « RHF + Zod » (utilisée partout ailleurs) ; aucune validation client (date passée
    saisissable → 422 serveur… en anglais, cf. #2).
13. **Agenda résident : créneaux passés du jour proposés** (`BookingForm.tsx:17,226-248`) →
    rejet serveur `after:now`. Filtrer `startIso > now` côté client.
14. `RoomController::availability` sans vérification de type de ressource (recoupe
    [02](./02-securite.md) I1 / [04](./04-backend-php.md) #10).
15. **Header non responsive** — `Layout.tsx:27-69` : 6 liens + nom + cloche sans menu mobile ;
    déborde sur ~375 px. Idem `ProfilePage.tsx:125` (`grid-cols-2` fixe).
16. **Actions destructrices sans confirmation** : annuler une résa, supprimer une absence,
    « Régénérer les liens » iCal (invalide immédiatement les abonnements). Dialog accessible.
17. **`FeedField` (copie URL iCal)** : `setTimeout` non nettoyé à l'unmount,
    `clipboard.writeText` sans catch, passage « Copier → Copié » non annoncé (pas d'`aria-live`).
18. Badges de statut sans variantes dark (`STATUS_CLASSES`).
19. `remember` déclaré mais jamais proposé (pas de case « Se souvenir de moi ») ;
    `getValidationErrors` (`lib/errors.ts:23`) utilisé nulle part hors tests.
20. Pagination sans annonce (`aria-live` sur « Page X sur Y ») ni gestion de focus.

### 💡 Suggestions

- **CLAUDE.md §3.5 exige `eslint-plugin-jsx-a11y` strict**, le projet utilise Biome
  `a11y: recommended` — choix raisonnable (§4.2 impose Biome) mais l'écart mérite d'être
  acté (ADR ou amendement CLAUDE.md) ; Biome a11y est moins complet.
- Les findings a11y (#5, #7, #8) devraient devenir des cas de test C11.3/C11.4 (Playwright + axe-core).
- `WEEKDAYS` commence dimanche (`PresencePage.tsx:22`) — l'ordre français attendu commence lundi.
- Couverture Vitest à étendre : `RequireAuth`/redirections, 401/419 (après interceptor),
  `AgendaSlotList` (chevauchements, heures passées), `NotificationBell` clavier.
- ⚠️ Constat d'exécution : un test `PresencePage` a timeouté une fois sous forte charge
  machine (repasse ensuite) — prévoir des timeouts plus généreux ou des requêtes MSW plus légères.

## Améliorations fonctionnelles possibles (portail)

1. **Afficher le calendrier de présence** : `present_days` est récupéré mais jamais affiché —
   une vue mois (avec alternative liste) donnerait au résident la vision « quand mon bureau
   est libéré » ; le range est figé à 3 mois (`PresencePage.tsx:83`).
2. **Prévenir avant l'échec ticket** : un external avec 0 ticket déroule tout le flux de résa
   de salle et n'apprend l'échec qu'au 422 final. Afficher le solde dans `BookingForm` et
   désactiver les boutons avec explication.
3. **Afficher le prix** : `external_half_day_price_ht` exposé par l'API et typé, jamais montré.
4. **« Mes bureaux nomades »** : `cancelDeskOccupation` existe côté SPA et l'endpoint DELETE
   existe, mais **aucune liste ne permet d'annuler une occupation** — écart fonctionnel réel.
5. **Polling notifications 60 s** : pertinent en MVP, bien fait (`refetchInterval` + focus,
   pause quand l'onglet est caché par défaut TanStack). Rien d'urgent.
6. **Dashboard** : assumé minimal — prochaines résas + dernières factures + documents à
   valider (PRD §3.3) seraient les tuiles les plus rentables.
7. **Résidents le week-end** : l'agenda propose tous les jours ; si les salles ne sont
   réservables qu'en jours ouvrés, griser samedi/dimanche.

## Bilan

Base saine et disciplinée — conventions réellement appliquées, isolation API rigoureuse,
a11y au-dessus de la moyenne. Les cinq points majeurs (session expirée, i18n des erreurs,
invalidation tickets, recovery code 2FA, ARIA de la cloche) sont tous corrigeables à faible
coût avant la phase C11.
