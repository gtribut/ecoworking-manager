/** Abonnement iCal du membre (PRD §3.5.8), forme normalisée côté SPA. */
export interface CalendarSubscription {
  enabled: boolean
  urls: { mine: string; entity: string } | null
}

/**
 * Payload brut de l'API : `urls` peut être `null` OU absent (révocation).
 * Normalisé en `CalendarSubscription` dans `api.ts` pour que le cache
 * TanStack Query reste cohérent quel que soit le payload.
 */
export interface CalendarSubscriptionPayload {
  enabled: boolean
  urls?: CalendarSubscription['urls']
}
