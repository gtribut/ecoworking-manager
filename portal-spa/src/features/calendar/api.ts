import { http } from '@/lib/http'
import type { CalendarSubscription } from './types'

export async function fetchCalendarSubscription(): Promise<CalendarSubscription> {
  const { data } = await http.get<CalendarSubscription>('/api/calendar')
  return data
}

export async function regenerateCalendarToken(): Promise<CalendarSubscription> {
  const { data } = await http.post<CalendarSubscription>('/api/calendar/token')
  return data
}

export async function revokeCalendarToken(): Promise<CalendarSubscription> {
  const { data } = await http.delete<CalendarSubscription>('/api/calendar/token')
  return data
}
