import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react'
import type { InvoiceSort, SortDirection } from './types'

/**
 * Contenu d'un en-tête de colonne triable (RGAA/WCAG) : bouton, l'état
 * (`aria-sort`) est porté par le `<th>` englobant — cf. `InvoicesPage`
 * (colonnes de `@tanstack/react-table`, tri toujours géré côté serveur).
 */
export function SortButton({
  column,
  label,
  sort,
  direction,
  onToggle,
}: {
  column: InvoiceSort
  label: string
  sort: InvoiceSort
  direction: SortDirection
  onToggle: (column: InvoiceSort) => void
}) {
  const active = sort === column
  const Icon = active ? (direction === 'asc' ? ArrowUp : ArrowDown) : ArrowUpDown

  return (
    <button
      type="button"
      aria-label={`Trier par ${label.toLowerCase()}`}
      onClick={() => onToggle(column)}
      className="inline-flex items-center gap-1 rounded underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2"
    >
      {label}
      <Icon className="size-3.5" aria-hidden="true" />
    </button>
  )
}
