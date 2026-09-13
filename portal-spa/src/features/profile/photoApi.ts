import type { PhotoUrls } from '@/components/Avatar'
import { http } from '@/lib/http'

/** Formats et poids acceptés — doublent la validation serveur (CLAUDE.md §3.2). */
export const PHOTO_ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp'] as const
export const PHOTO_MAX_BYTES = 2 * 1024 * 1024

export interface PhotoResponse {
  photo: PhotoUrls | null
}

/** Message d'erreur client, ou null si le fichier est acceptable. */
export function validatePhotoFile(file: File): string | null {
  if (!(PHOTO_ACCEPTED_TYPES as readonly string[]).includes(file.type)) {
    return 'Formats acceptés : JPG, PNG ou WebP.'
  }
  if (file.size > PHOTO_MAX_BYTES) {
    return 'La photo ne doit pas dépasser 2 Mo.'
  }
  return null
}

export async function uploadProfilePhoto(file: File): Promise<PhotoResponse> {
  const body = new FormData()
  body.append('photo', file)

  const { data } = await http.post<PhotoResponse>('/api/profile/photo', body)
  return data
}

export async function deleteProfilePhoto(): Promise<PhotoResponse> {
  const { data } = await http.delete<PhotoResponse>('/api/profile/photo')
  return data
}
