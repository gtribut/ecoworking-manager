import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { fetchInvoices } from './api'

export const invoicesQueryKey = (page: number) => ['invoices', page] as const

export function useInvoices(page: number) {
  return useQuery({
    queryKey: invoicesQueryKey(page),
    queryFn: () => fetchInvoices(page),
    placeholderData: keepPreviousData,
  })
}
