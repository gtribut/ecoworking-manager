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

/** Colonnes triables exposées par l'API (miroir d'IndexInvoicesRequest::SORTS). */
export const INVOICE_SORTS = ['issued_at', 'number', 'status'] as const
export type InvoiceSort = (typeof INVOICE_SORTS)[number]
export type SortDirection = 'asc' | 'desc'

/**
 * Tri + filtres de la liste (PRD §3.6.2). `year`/`month`/`status`/`q` valent
 * `''` quand le filtre est inactif — l'API ne les reçoit alors pas du tout.
 */
export interface InvoiceFilters {
  page: number
  sort: InvoiceSort
  direction: SortDirection
  year: string
  month: string
  status: string
  q: string
}

export const DEFAULT_INVOICE_FILTERS: InvoiceFilters = {
  page: 1,
  sort: 'issued_at',
  direction: 'desc',
  year: '',
  month: '',
  status: '',
  q: '',
}
