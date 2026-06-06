export interface Invoice {
  id: number
  number: string | null
  status: string
  is_credit_note: boolean
  issued_at: string | null
  due_at: string | null
  total_ht: string
  total_vat: string
  total_ttc: string
  amount_paid: string
  pdf_available: boolean
}

/** Réponse paginée Laravel (Resource collection). */
export interface Paginated<T> {
  data: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}
