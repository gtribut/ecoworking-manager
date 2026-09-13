import { http } from '@/lib/http'
import type { BillingEntity } from './types'

/**
 * Entités facturables du contact de facturation (PRD §3.6.4). 403 si le user
 * n'a pas le rôle `billing_contact` — le module est de toute façon masqué.
 */
export async function fetchBillingEntities(): Promise<BillingEntity[]> {
  const { data } = await http.get<{ data: BillingEntity[] }>('/api/billing/entity')
  return data.data
}
