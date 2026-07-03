import type { Paginated } from '@/lib/api-types'
import { http } from '@/lib/http'
import type { AdministrativeDocument, InternalDocument } from './types'

export async function fetchInternalDocuments(): Promise<{ data: InternalDocument[] }> {
  const { data } = await http.get<{ data: InternalDocument[] }>('/api/documents/internal')
  return data
}

/** La version validée est snapshotée côté serveur — aucune donnée envoyée. */
export async function validateInternalDocument(documentId: number): Promise<void> {
  await http.post(`/api/documents/internal/${documentId}/validation`)
}

export async function fetchAdministrativeDocuments(
  page = 1,
): Promise<Paginated<AdministrativeDocument>> {
  const { data } = await http.get<Paginated<AdministrativeDocument>>(
    '/api/documents/administrative',
    { params: { page } },
  )
  return data
}

/**
 * URLs de téléchargement des PDF. Même origine + session Sanctum → le cookie
 * part automatiquement avec une navigation `<a>` classique, pas besoin de blob.
 */
export function internalDocumentPdfUrl(documentId: number): string {
  return `/api/documents/internal/${documentId}/pdf`
}

export function administrativeDocumentPdfUrl(documentId: number): string {
  return `/api/documents/administrative/${documentId}/pdf`
}
