import type { Paginated } from '@/lib/api-types'
import { http } from '@/lib/http'
import type { Invoice, InvoiceFilters } from './types'

/**
 * Liste paginée des factures du périmètre de facturation. Seuls les filtres
 * actifs sont envoyés : l'API valide chaque paramètre (IndexInvoicesRequest)
 * et un filtre n'élargit jamais le périmètre.
 */
export async function fetchInvoices(filters: InvoiceFilters): Promise<Paginated<Invoice>> {
  const params: Record<string, string | number> = {
    page: filters.page,
    sort: filters.sort,
    direction: filters.direction,
  }
  if (filters.year) params.year = filters.year
  if (filters.year && filters.month) params.month = filters.month
  if (filters.status) params.status = filters.status
  if (filters.q) params.q = filters.q

  const { data } = await http.get<Paginated<Invoice>>('/api/invoices', { params })
  return data
}

/**
 * URL de téléchargement du PDF. Même origine + session Sanctum → le cookie part
 * automatiquement avec une navigation `<a>` classique, pas besoin de blob.
 */
export function invoicePdfUrl(invoiceId: number): string {
  return `/api/invoices/${invoiceId}/pdf`
}
