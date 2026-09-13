import { ArrowDown, ArrowUp, ArrowUpDown, Download } from 'lucide-react'
import type { ReactNode } from 'react'
import { Alert } from '@/components/ui/Alert'
import { Button } from '@/components/ui/Button'
import { Spinner } from '@/components/ui/Spinner'
import { EntityBlocks } from '@/features/billing/EntityBlock'
import { useBillingEntities } from '@/features/billing/useBillingEntities'
import { usePageTitle } from '@/lib/usePageTitle'
import { invoicePdfUrl } from './api'
import { InvoiceFiltersBar } from './InvoiceFiltersBar'
import { euros, formatInvoiceDate, InvoiceStatusBadge } from './status'
import type { InvoiceSort, SortDirection } from './types'
import { useInvoiceFilters } from './useInvoiceFilters'
import { useInvoices } from './useInvoices'

/**
 * En-tête de colonne triable : bouton dans le `<th>` + `aria-sort` porté par la
 * cellule (RGAA/WCAG). Déclaré hors du composant page pour que le `<th>` ne
 * soit pas remonté à chaque rendu — sinon le focus clavier serait perdu au clic.
 */
function SortableHeader({
  column,
  children,
  sort,
  direction,
  onToggle,
}: {
  column: InvoiceSort
  children: ReactNode
  sort: InvoiceSort
  direction: SortDirection
  onToggle: (column: InvoiceSort) => void
}) {
  const active = sort === column
  const Icon = active ? (direction === 'asc' ? ArrowUp : ArrowDown) : ArrowUpDown

  return (
    <th
      scope="col"
      className="px-4 py-3 font-medium"
      aria-sort={active ? (direction === 'asc' ? 'ascending' : 'descending') : 'none'}
    >
      <button
        type="button"
        onClick={() => onToggle(column)}
        className="inline-flex items-center gap-1 rounded underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2"
      >
        {children}
        <Icon className="size-3.5" aria-hidden="true" />
      </button>
    </th>
  )
}

export function InvoicesPage() {
  usePageTitle('Factures — Portail Ecoworking')

  const { filters, apply, setPage, toggleSort, reset, hasActiveFilters } = useInvoiceFilters()
  const { data, isLoading, isError } = useInvoices(filters)
  // Bloc entité du module administratif (PRD §3.6.4) ; masqué si l'utilisateur
  // n'a aucune entité facturable.
  const entities = useBillingEntities()

  return (
    <div className="mx-auto max-w-4xl space-y-6">
      <h1 className="text-2xl font-semibold">Mes factures</h1>

      <InvoiceFiltersBar
        filters={filters}
        onChange={apply}
        onReset={reset}
        hasActiveFilters={hasActiveFilters}
      />

      {isLoading && <Spinner label="Chargement des factures…" />}
      {isError && <Alert variant="error">Impossible de charger vos factures.</Alert>}

      {data && data.data.length === 0 && (
        <div className="space-y-3">
          <Alert variant="info">
            {hasActiveFilters
              ? 'Aucune facture ne correspond à ces filtres.'
              : 'Aucune facture pour le moment.'}
          </Alert>
          {hasActiveFilters && (
            <Button variant="secondary" size="sm" onClick={reset}>
              Réinitialiser les filtres
            </Button>
          )}
        </div>
      )}

      {data && data.data.length > 0 && (
        <>
          <div className="overflow-x-auto rounded-lg border border-neutral-200 dark:border-neutral-800">
            <table className="w-full text-left text-sm">
              <caption className="sr-only">
                Liste de mes factures, triable par numéro, date et statut
              </caption>
              <thead className="bg-neutral-50 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">
                <tr>
                  <SortableHeader
                    column="number"
                    sort={filters.sort}
                    direction={filters.direction}
                    onToggle={toggleSort}
                  >
                    Numéro
                  </SortableHeader>
                  <SortableHeader
                    column="issued_at"
                    sort={filters.sort}
                    direction={filters.direction}
                    onToggle={toggleSort}
                  >
                    Date
                  </SortableHeader>
                  <SortableHeader
                    column="status"
                    sort={filters.sort}
                    direction={filters.direction}
                    onToggle={toggleSort}
                  >
                    Statut
                  </SortableHeader>
                  <th scope="col" className="px-4 py-3 text-right font-medium">
                    Total TTC
                  </th>
                  <th scope="col" className="px-4 py-3 text-right font-medium">
                    PDF
                  </th>
                </tr>
              </thead>
              <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                {data.data.map((invoice) => (
                  <tr key={invoice.id}>
                    <th scope="row" className="px-4 py-3 font-medium">
                      {invoice.number ?? '—'}
                      {invoice.is_credit_note && (
                        <span className="ml-2 text-xs text-neutral-500 dark:text-neutral-400">
                          (avoir)
                        </span>
                      )}
                    </th>
                    <td className="px-4 py-3">{formatInvoiceDate(invoice.issued_at)}</td>
                    <td className="px-4 py-3">
                      <InvoiceStatusBadge status={invoice.status} />
                    </td>
                    <td className="px-4 py-3 text-right tabular-nums">
                      {euros.format(Number(invoice.total_ttc))}
                    </td>
                    <td className="px-4 py-3 text-right">
                      {invoice.pdf_available ? (
                        <a
                          href={invoicePdfUrl(invoice.id)}
                          className="inline-flex items-center gap-1 text-brand-700 dark:text-brand-300 underline"
                        >
                          <Download className="size-4" aria-hidden="true" />
                          <span>
                            Télécharger<span className="sr-only"> la facture {invoice.number}</span>
                          </span>
                        </a>
                      ) : (
                        <span className="text-neutral-500 dark:text-neutral-400">Indisponible</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {data.meta.last_page > 1 && (
            <nav className="flex items-center justify-between" aria-label="Pagination des factures">
              <Button
                variant="secondary"
                size="sm"
                disabled={filters.page <= 1}
                onClick={() => setPage(Math.max(1, filters.page - 1))}
              >
                Précédent
              </Button>
              <span aria-live="polite" className="text-sm text-neutral-600 dark:text-neutral-300">
                Page {data.meta.current_page} sur {data.meta.last_page}
              </span>
              <Button
                variant="secondary"
                size="sm"
                disabled={filters.page >= data.meta.last_page}
                onClick={() => setPage(filters.page + 1)}
              >
                Suivant
              </Button>
            </nav>
          )}
        </>
      )}

      {entities.data && <EntityBlocks entities={entities.data} />}
    </div>
  )
}
