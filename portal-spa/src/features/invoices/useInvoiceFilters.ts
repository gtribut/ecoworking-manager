import { useCallback, useMemo } from 'react'
import { useSearchParams } from 'react-router'
import {
  DEFAULT_INVOICE_FILTERS,
  INVOICE_SORTS,
  type InvoiceFilters,
  type InvoiceSort,
  type SortDirection,
} from './types'

function parseFilters(params: URLSearchParams): InvoiceFilters {
  const sort = params.get('sort')
  const direction = params.get('direction')
  const page = Number.parseInt(params.get('page') ?? '', 10)
  const year = params.get('year') ?? ''

  return {
    page: Number.isFinite(page) && page > 0 ? page : 1,
    sort: INVOICE_SORTS.includes(sort as InvoiceSort) ? (sort as InvoiceSort) : 'issued_at',
    direction: direction === 'asc' ? 'asc' : 'desc',
    year,
    // Un mois sans année n'a pas de sens (et serait refusé en 422).
    month: year ? (params.get('month') ?? '') : '',
    status: params.get('status') ?? '',
    q: params.get('q') ?? '',
  }
}

/** Seuls les écarts aux valeurs par défaut sont écrits dans l'URL. */
function serializeFilters(filters: InvoiceFilters): URLSearchParams {
  const params = new URLSearchParams()
  if (filters.page > 1) params.set('page', String(filters.page))
  if (filters.sort !== DEFAULT_INVOICE_FILTERS.sort) params.set('sort', filters.sort)
  if (filters.direction !== DEFAULT_INVOICE_FILTERS.direction)
    params.set('direction', filters.direction)
  if (filters.year) params.set('year', filters.year)
  if (filters.year && filters.month) params.set('month', filters.month)
  if (filters.status) params.set('status', filters.status)
  if (filters.q) params.set('q', filters.q)

  return params
}

/**
 * Tri, filtres et recherche portés par l'URL (PRD §3.6.2) : la vue filtrée est
 * partageable et survit à un rafraîchissement ou à un retour arrière.
 */
export function useInvoiceFilters() {
  const [searchParams, setSearchParams] = useSearchParams()
  const filters = useMemo(() => parseFilters(searchParams), [searchParams])

  const apply = useCallback(
    (patch: Partial<InvoiceFilters>) => {
      // Tout changement de filtre ramène à la première page : la page courante
      // n'existe probablement plus dans le résultat filtré.
      const next: InvoiceFilters = { ...filters, page: 1, ...patch }
      setSearchParams(serializeFilters(next), { replace: true })
    },
    [filters, setSearchParams],
  )

  const setPage = useCallback(
    (page: number) => setSearchParams(serializeFilters({ ...filters, page }), { replace: true }),
    [filters, setSearchParams],
  )

  /**
   * Clic sur un en-tête : inverse la direction si la colonne est déjà triée,
   * sinon part du sens le plus utile (plus récent d'abord pour une date,
   * ordre croissant pour un numéro ou un statut).
   */
  const toggleSort = useCallback(
    (sort: InvoiceSort) => {
      const direction: SortDirection =
        filters.sort === sort
          ? filters.direction === 'asc'
            ? 'desc'
            : 'asc'
          : sort === 'issued_at'
            ? 'desc'
            : 'asc'
      apply({ sort, direction })
    },
    [apply, filters],
  )

  const reset = useCallback(() => setSearchParams(new URLSearchParams()), [setSearchParams])

  const hasActiveFilters = Boolean(filters.year || filters.month || filters.status || filters.q)

  return { filters, apply, setPage, toggleSort, reset, hasActiveFilters }
}
