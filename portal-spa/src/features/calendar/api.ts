import { http } from '@/lib/http'
import type { CalendarSubscription, CalendarSubscriptionPayload } from './types'

/** Normalise le payload API (`urls` absent OU null → `urls: null`). */
function normalize(payload: CalendarSubscriptionPayload): CalendarSubscription {
  return { enabled: payload.enabled, urls: payload.urls ?? null }
}

export async function fetchCalendarSubscription(): Promise<CalendarSubscription> {
  const { data } = await http.get<CalendarSubscriptionPayload>('/api/calendar')
  return normalize(data)
}

export async function regenerateCalendarToken(): Promise<CalendarSubscription> {
  const { data } = await http.post<CalendarSubscriptionPayload>('/api/calendar/token')
  return normalize(data)
}

export async function revokeCalendarToken(): Promise<CalendarSubscription> {
  const { data } = await http.delete<CalendarSubscriptionPayload>('/api/calendar/token')
  return normalize(data)
}
