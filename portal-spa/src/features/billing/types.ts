/**
 * Entité juridique facturable, en lecture seule (PRD §3.4.3 / §3.6.4).
 *
 * `payment_method`, `payment_method_label` et `iban_last4` ne sont renvoyés par
 * l'API qu'au **contact de facturation** de l'entité : les clés sont absentes
 * pour les autres membres rattachés (CompanyResource).
 */
export interface BillingEntity {
  id: number
  entity_type: string
  name: string
  legal_name: string | null
  legal_form: string | null
  siret: string | null
  vat_number: string | null
  billing_email: string | null
  address: {
    line1: string | null
    line2: string | null
    postal_code: string | null
    city: string | null
    country: string | null
  }
  payment_method?: string | null
  payment_method_label?: string | null
  iban_last4?: string | null
}
