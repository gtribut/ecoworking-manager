import { useQuery } from '@tanstack/react-query'
import { fetchBillingEntities } from './api'

export const billingEntitiesQueryKey = ['billing', 'entities'] as const

export function useBillingEntities() {
  return useQuery({
    queryKey: billingEntitiesQueryKey,
    queryFn: fetchBillingEntities,
    retry: false, // 403 attendu hors rôle facturation : ne pas insister
  })
}
