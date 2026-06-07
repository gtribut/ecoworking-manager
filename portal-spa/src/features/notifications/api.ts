import { http } from '@/lib/http'
import type { NotificationsResponse } from './types'

export async function fetchNotifications(): Promise<NotificationsResponse> {
  const { data } = await http.get<NotificationsResponse>('/api/notifications')
  return data
}

export async function markNotificationAsRead(id: string): Promise<void> {
  await http.post(`/api/notifications/${id}/read`)
}

export async function markAllNotificationsAsRead(): Promise<void> {
  await http.post('/api/notifications/read-all')
}
