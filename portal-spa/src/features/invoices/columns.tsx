import type { HeaderContext } from '@tanstack/react-table'
import { createColumnHelper } from '@tanstack/react-table'
import { Download } from 'lucide-react'
import { invoicePdfUrl } from './api'
import { SortButton } from './SortButton'
import { euros, formatInvoiceDate, InvoiceStatusBadge } from './status'
import type { Invoice, InvoiceSort, SortDirection } from './types'

declare module '@tanstack/react-table' {
  /** Colonnes alignées à droite (montants) — le reste suit l'alignement par défaut. */
  interface ColumnMeta<TData, TValue> {
    align?: 'right'
  }

  /**
   * Tri courant + callback, injectés via `useReactTable({ meta })` (cf.
   * `InvoicesPage`). Lus depuis `context.table.options.meta` **dans** les
   * fonctions `header` ci-dessous plutôt que capturés par closure : les
   * colonnes sont construites une seule fois (`invoiceColumns()` mémoïsé sans
   * dépendances) pour que `flexRender` réutilise le même composant à chaque
   * tri — recréer les colonnes à chaque changement de filtre remplacerait la
   * fonction `header` (nouvelle identité) et ferait perdre le focus clavier
   * sur le bouton de tri actif.
   *
   * Namespacé sous `invoiceSort` et **optionnel** (review U4a I-1) :
   * `TableMeta` est une interface globale (`declare module`) — la rendre
   * obligatoire aurait forcé tout `useReactTable` futur ailleurs dans le
   * portail à fournir `sort`/`direction`/`onToggleSort`, même sans rapport
   * avec les factures.
   */
  interface TableMeta<TData> {
    invoiceSort?: {
      sort: InvoiceSort
      direction: SortDirection
      onToggle: (column: InvoiceSort) => void
    }
  }
}

const columnHelper = createColumnHelper<Invoice>()

/**
 * En-tête triable : lit `sort`/`direction`/`onToggleSort` depuis
 * `context.table.options.meta` (toujours fourni par `InvoicesPage` — un
 * `meta` absent est un bug d'intégration, pas un état valide, d'où l'erreur
 * explicite plutôt qu'un repli silencieux) plutôt que par closure, pour que
 * `invoiceColumns()` reste appelable une seule fois (cf. sa docstring).
 */
function sortableHeader(column: InvoiceSort, label: string) {
  return (context: HeaderContext<Invoice, unknown>) => {
    const invoiceSort = context.table.options.meta?.invoiceSort
    if (!invoiceSort) {
      throw new Error(
        'invoiceColumns: `meta.invoiceSort` manquant — useReactTable({ meta: { invoiceSort } }) est requis.',
      )
    }
    return (
      <SortButton
        column={column}
        label={label}
        sort={invoiceSort.sort}
        direction={invoiceSort.direction}
        onToggle={invoiceSort.onToggle}
      />
    )
  }
}

/**
 * Colonnes de la DataTable des factures (`@tanstack/react-table` 8.21,
 * PRD §3.6.2). Le tri et la pagination restent **serveur**
 * (`useInvoiceFilters` / `useInvoices`) : ces colonnes ne décrivent que le
 * rendu, le tri déclenché par `SortButton` reste le seul déclencheur d'un
 * nouveau tri — pas de `getSortedRowModel`/`getPaginationRowModel` (données
 * déjà triées/paginées par l'API, cf. `InvoicesPage`).
 *
 * **Appelée une seule fois** (`useMemo(() => invoiceColumns(), [])` côté
 * `InvoicesPage`) : le tri courant est lu dans `meta` (cf. `sortableHeader`),
 * pas capturé par closure — sinon chaque changement de tri recréerait les
 * fonctions `header`, une nouvelle identité que `flexRender` traiterait comme
 * un composant différent, remontant le bouton actif et perdant le focus
 * clavier (CLAUDE.md §3.5).
 *
 * Pas d'annotation de retour explicite : chaque colonne a un `TValue` propre
 * (`string`, `string | null`…) et `ColumnDef<Invoice>` figerait `TValue` à
 * `unknown`, incompatible avec l'union inférée (limitation connue de
 * `@tanstack/react-table` — la lib type elle-même `columns` en
 * `ColumnDef<TData, any>[]`). Laisser inférer évite tout `any` de notre côté.
 */
export function invoiceColumns() {
  return [
    columnHelper.accessor('number', {
      id: 'number',
      header: sortableHeader('number', 'Numéro'),
      cell: (info) => {
        const invoice = info.row.original
        return (
          <>
            {invoice.number ?? '—'}
            {invoice.is_credit_note && (
              <span className="ml-2 text-xs font-normal text-muted-foreground">(avoir)</span>
            )}
          </>
        )
      },
    }),
    columnHelper.accessor('issued_at', {
      id: 'issued_at',
      header: sortableHeader('issued_at', 'Date'),
      cell: (info) => formatInvoiceDate(info.getValue()),
    }),
    columnHelper.accessor('status', {
      id: 'status',
      header: sortableHeader('status', 'Statut'),
      cell: (info) => <InvoiceStatusBadge status={info.getValue()} />,
    }),
    columnHelper.accessor('total_ttc', {
      id: 'total_ttc',
      header: 'Total TTC',
      meta: { align: 'right' },
      cell: (info) => euros.format(Number(info.getValue())),
    }),
    columnHelper.display({
      id: 'pdf',
      header: 'PDF',
      meta: { align: 'right' },
      cell: (info) => {
        const invoice = info.row.original
        return invoice.pdf_available ? (
          <a
            href={invoicePdfUrl(invoice.id)}
            className="inline-flex items-center gap-1 text-brand-700 underline dark:text-brand-300"
          >
            <Download className="size-4" aria-hidden="true" />
            <span>
              Télécharger<span className="sr-only"> la facture {invoice.number}</span>
            </span>
          </a>
        ) : (
          <span className="text-muted-foreground">Indisponible</span>
        )
      },
    }),
  ]
}

/** Colonnes triables — utilisé pour calculer `aria-sort` sur le `<th>` englobant. */
export const SORTABLE_COLUMN_IDS: InvoiceSort[] = ['number', 'issued_at', 'status']
