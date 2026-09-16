import { flexRender, getCoreRowModel, useReactTable } from '@tanstack/react-table'
import { Receipt } from 'lucide-react'
import { useMemo } from 'react'
import { EmptyState } from '@/components/EmptyState'
import { PageContainer } from '@/components/PageContainer'
import { PageHeader } from '@/components/PageHeader'
import { QueryError } from '@/components/QueryError'
import { Button } from '@/components/ui/button'
import { Spinner } from '@/components/ui/spinner'
import {
  Table,
  TableBody,
  TableCaption,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { EntityBlocks } from '@/features/billing/EntityBlock'
import { useBillingEntities } from '@/features/billing/useBillingEntities'
import { usePageTitle } from '@/lib/usePageTitle'
import { cn } from '@/lib/utils'
import { invoiceColumns, SORTABLE_COLUMN_IDS } from './columns'
import { InvoiceFiltersBar } from './InvoiceFiltersBar'
import type { InvoiceSort, SortDirection } from './types'
import { useInvoiceFilters } from './useInvoiceFilters'
import { useInvoices } from './useInvoices'

const SORT_LABELS: Record<InvoiceSort, string> = {
  issued_at: 'date d’émission',
  number: 'numéro',
  // Tri alphabétique sur la valeur stockée du statut : ordre métier non
  // pertinent en MVP (acté review lot D).
  status: 'statut',
}

/** Résumé annoncé aux lecteurs d'écran après un tri ou un changement de filtre. */
function resultSummary(
  total: number,
  filters: { sort: InvoiceSort; direction: SortDirection },
  filtered: boolean,
): string {
  const plural = total > 1 ? 's' : ''
  const order = filters.direction === 'asc' ? 'croissant' : 'décroissant'

  return `${total} facture${plural}${filtered ? ` filtrée${plural}` : ''}, triée${plural} par ${SORT_LABELS[filters.sort]}, ordre ${order}.`
}

/**
 * Factures (PRD §3.6.2) — DataTable `@tanstack/react-table` (colonnes en
 * `columns.tsx`) sur la primitive shadcn `Table`. Tri, filtres, recherche et
 * pagination restent **serveur** : la table ne gère que le rendu des lignes
 * déjà triées/paginées par l'API (`useInvoiceFilters`/`useInvoices`), pas de
 * `getSortedRowModel`/`getPaginationRowModel`.
 */
export function InvoicesPage() {
  usePageTitle('Factures — Portail Ecoworking')

  const { filters, apply, setPage, toggleSort, reset, hasActiveFilters } = useInvoiceFilters()
  const { data, isLoading, isError, refetch } = useInvoices(filters)
  // Bloc entité du module administratif (PRD §3.6.4) ; masqué si l'utilisateur
  // n'a aucune entité facturable.
  const entities = useBillingEntities()

  // Colonnes créées une seule fois (cf. docstring de `invoiceColumns`) : le
  // tri courant passe par `meta`, pas par une recréation des colonnes, pour
  // ne jamais remonter le bouton de tri actif (perte de focus clavier).
  const columns = useMemo(() => invoiceColumns(), [])

  const table = useReactTable({
    data: data?.data ?? [],
    columns,
    getCoreRowModel: getCoreRowModel(),
    meta: { sort: filters.sort, direction: filters.direction, onToggleSort: toggleSort },
  })

  return (
    <PageContainer width="full" className="space-y-6">
      <PageHeader title="Mes factures" />

      <InvoiceFiltersBar
        filters={filters}
        onChange={apply}
        onReset={reset}
        hasActiveFilters={hasActiveFilters}
      />

      {/* Annonce le résultat après un tri ou un filtre (lecteur d'écran). */}
      <p aria-live="polite" className="sr-only">
        {data ? resultSummary(data.meta.total, filters, hasActiveFilters) : ''}
      </p>

      {isLoading && <Spinner label="Chargement des factures…" />}
      {isError && (
        <QueryError message="Impossible de charger vos factures." onRetry={() => void refetch()} />
      )}

      {data && data.data.length === 0 && (
        <div className="space-y-3">
          <EmptyState
            icon={Receipt}
            title={
              hasActiveFilters
                ? 'Aucune facture ne correspond à ces filtres.'
                : 'Aucune facture pour le moment.'
            }
            description={
              hasActiveFilters
                ? undefined
                : 'Les factures apparaîtront ici dès qu’elles seront émises.'
            }
          />
          {hasActiveFilters && (
            <Button variant="outline" size="sm" onClick={reset}>
              Réinitialiser les filtres
            </Button>
          )}
        </div>
      )}

      {data && data.data.length > 0 && (
        <>
          <div className="overflow-x-auto rounded-lg border border-border">
            <Table>
              <TableCaption className="sr-only">
                Liste de mes factures, triable par numéro, date et statut
              </TableCaption>
              {/* Bandeau gris + texte neutral-600/300 (pas `text-muted-foreground`,
                  qui tombe sous 4.5:1 sur ce fond) — comme l'ancien `<thead>`. */}
              <TableHeader className="bg-neutral-50 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">
                {table.getHeaderGroups().map((headerGroup) => (
                  <TableRow key={headerGroup.id}>
                    {headerGroup.headers.map((header) => {
                      const isSortable = SORTABLE_COLUMN_IDS.includes(
                        header.column.id as InvoiceSort,
                      )
                      const ariaSort = isSortable
                        ? filters.sort === header.column.id
                          ? filters.direction === 'asc'
                            ? ('ascending' as const)
                            : ('descending' as const)
                          : ('none' as const)
                        : undefined

                      return (
                        <TableHead
                          key={header.id}
                          scope="col"
                          aria-sort={ariaSort}
                          className={cn(
                            header.column.columnDef.meta?.align === 'right' && 'text-right',
                          )}
                        >
                          {header.isPlaceholder
                            ? null
                            : flexRender(header.column.columnDef.header, header.getContext())}
                        </TableHead>
                      )
                    })}
                  </TableRow>
                ))}
              </TableHeader>
              <TableBody>
                {table.getRowModel().rows.map((row) => (
                  <TableRow key={row.id}>
                    {row.getVisibleCells().map((cell) =>
                      cell.column.id === 'number' ? (
                        <TableHead key={cell.id} scope="row" className="text-left font-medium">
                          {flexRender(cell.column.columnDef.cell, cell.getContext())}
                        </TableHead>
                      ) : (
                        <TableCell
                          key={cell.id}
                          className={cn(
                            cell.column.columnDef.meta?.align === 'right' &&
                              'text-right tabular-nums',
                          )}
                        >
                          {flexRender(cell.column.columnDef.cell, cell.getContext())}
                        </TableCell>
                      ),
                    )}
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>

          {data.meta.last_page > 1 && (
            <nav className="flex items-center justify-between" aria-label="Pagination des factures">
              <Button
                variant="outline"
                size="sm"
                disabled={filters.page <= 1}
                onClick={() => setPage(Math.max(1, filters.page - 1))}
              >
                Précédent
              </Button>
              <span aria-live="polite" className="text-sm text-muted-foreground">
                Page {data.meta.current_page} sur {data.meta.last_page}
              </span>
              <Button
                variant="outline"
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

      {/* Module administratif (§3.6.4) : c'est ici que le PRD prévoit le mode
          de paiement et l'IBAN-4, pas dans le profil. */}
      {entities.data && <EntityBlocks entities={entities.data} showBillingDetails />}
    </PageContainer>
  )
}
