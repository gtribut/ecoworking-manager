/** Abonnement iCal du membre (PRD §3.5.8). */
export interface CalendarSubscription {
  enabled: boolean
  urls: { mine: string; entity: string } | null
}
