import { http } from '@/lib/http'
import type { NotificationsResponse } from './types'

/** Liste paginée (PRD §3.8.4, lot G) : `page` alimente le bouton « Charger plus ». */
export async function fetchNotifications(page = 1): Promise<NotificationsResponse> {
  const { data } = await http.get<NotificationsResponse>('/api/notifications', {
    params: { page },
  })
  return data
}

export async function markNotificationAsRead(id: string): Promise<void> {
  await http.post(`/api/notifications/${id}/read`)
}

export async function markAllNotificationsAsRead(): Promise<void> {
  await http.post('/api/notifications/read-all')
}
