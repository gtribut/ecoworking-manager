import { http } from '@/lib/http'
import type { ProfilePayload, ProfileUpdate } from './types'

export async function fetchProfile(): Promise<ProfilePayload> {
  const { data } = await http.get<ProfilePayload>('/api/profile')
  return data
}

export async function updateProfile(update: ProfileUpdate): Promise<ProfilePayload> {
  const { data } = await http.patch<ProfilePayload>('/api/profile', update)
  return data
}
