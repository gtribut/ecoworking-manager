import type { Paginated } from '@/lib/api-types'
import { http } from '@/lib/http'
import type { Announcement } from './types'

export async function fetchAnnouncements(page = 1): Promise<Paginated<Announcement>> {
  const { data } = await http.get<Paginated<Announcement>>('/api/announcements', {
    params: { page },
  })
  return data
}

export async function fetchAnnouncement(id: number): Promise<Announcement> {
  const { data } = await http.get<{ data: Announcement }>(`/api/announcements/${id}`)
  return data.data
}

export async function registerToEvent(id: number): Promise<void> {
  await http.post(`/api/announcements/${id}/registration`)
}

export async function unregisterFromEvent(id: number): Promise<void> {
  await http.delete(`/api/announcements/${id}/registration`)
}
