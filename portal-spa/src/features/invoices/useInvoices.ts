import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { fetchInvoices } from './api'
import type { InvoiceFilters } from './types'

/** La clé porte tous les filtres : chaque combinaison a son entrée de cache. */
export const invoicesQueryKey = (filters: InvoiceFilters) => ['invoices', filters] as const

export function useInvoices(filters: InvoiceFilters) {
  return useQuery({
    queryKey: invoicesQueryKey(filters),
    queryFn: () => fetchInvoices(filters),
    placeholderData: keepPreviousData,
  })
}
