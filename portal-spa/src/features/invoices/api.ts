import { http } from '@/lib/http'
import type { Invoice, Paginated } from './types'

export async function fetchInvoices(page = 1): Promise<Paginated<Invoice>> {
  const { data } = await http.get<Paginated<Invoice>>('/api/invoices', { params: { page } })
  return data
}

/**
 * URL de téléchargement du PDF. Même origine + session Sanctum → le cookie part
 * automatiquement avec une navigation `<a>` classique, pas besoin de blob.
 */
export function invoicePdfUrl(invoiceId: number): string {
  return `/api/invoices/${invoiceId}/pdf`
}
