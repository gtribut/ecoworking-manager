import type { FormEvent } from 'react'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { Label } from '@/components/ui/Label'
import { Select } from '@/components/ui/Select'
import { INVOICE_STATUS_LABELS } from './status'
import type { InvoiceFilters } from './types'

const MONTHS = [
  'Janvier',
  'Février',
  'Mars',
  'Avril',
  'Mai',
  'Juin',
  'Juillet',
  'Août',
  'Septembre',
  'Octobre',
  'Novembre',
  'Décembre',
]

/** Années proposées : l'année courante et les 5 précédentes. */
function selectableYears(): number[] {
  const current = new Date().getFullYear()
  return Array.from({ length: 6 }, (_, index) => current - index)
}

interface Props {
  filters: InvoiceFilters
  onChange: (patch: Partial<InvoiceFilters>) => void
  onReset: () => void
  hasActiveFilters: boolean
}

/**
 * Barre de filtres des factures (PRD §3.6.2) : année, mois, statut, recherche
 * par numéro. Les listes s'appliquent au changement ; la recherche texte à la
 * validation (Entrée ou bouton) pour ne pas requêter à chaque frappe.
 */
export function InvoiceFiltersBar({ filters, onChange, onReset, hasActiveFilters }: Props) {
  function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const value = new FormData(event.currentTarget).get('q')
    onChange({ q: typeof value === 'string' ? value.trim() : '' })
  }

  return (
    <search>
      <form
        aria-label="Filtrer mes factures"
        onSubmit={onSubmit}
        className="grid grid-cols-1 gap-4 rounded-lg border border-neutral-200 p-4 sm:grid-cols-2 lg:grid-cols-4 dark:border-neutral-800"
      >
        <div>
          <Label htmlFor="invoice-year">Année</Label>
          <Select
            id="invoice-year"
            value={filters.year}
            onChange={(event) => onChange({ year: event.target.value, month: '' })}
          >
            <option value="">Toutes</option>
            {selectableYears().map((year) => (
              <option key={year} value={String(year)}>
                {year}
              </option>
            ))}
          </Select>
        </div>

        <div>
          <Label htmlFor="invoice-month">Mois</Label>
          <Select
            id="invoice-month"
            value={filters.month}
            disabled={!filters.year}
            aria-describedby="invoice-month-help"
            onChange={(event) => onChange({ month: event.target.value })}
          >
            <option value="">Tous</option>
            {MONTHS.map((label, index) => (
              <option key={label} value={String(index + 1)}>
                {label}
              </option>
            ))}
          </Select>
          <p
            id="invoice-month-help"
            className="mt-1 text-xs text-neutral-500 dark:text-neutral-400"
          >
            Choisissez d’abord une année.
          </p>
        </div>

        <div>
          <Label htmlFor="invoice-status">Statut</Label>
          <Select
            id="invoice-status"
            value={filters.status}
            onChange={(event) => onChange({ status: event.target.value })}
          >
            <option value="">Tous</option>
            {Object.entries(INVOICE_STATUS_LABELS).map(([value, label]) => (
              <option key={value} value={value}>
                {label}
              </option>
            ))}
          </Select>
        </div>

        <div>
          <Label htmlFor="invoice-search">Numéro de facture</Label>
          <div className="flex gap-2">
            <Input
              id="invoice-search"
              type="search"
              name="q"
              // Réinitialise le champ non contrôlé quand l'URL change (reset).
              key={filters.q}
              defaultValue={filters.q}
              placeholder="EW-2026-…"
            />
            <Button type="submit" variant="secondary">
              Rechercher
            </Button>
          </div>
        </div>

        {hasActiveFilters && (
          <div className="sm:col-span-2 lg:col-span-4">
            <Button type="button" variant="secondary" size="sm" onClick={onReset}>
              Réinitialiser les filtres
            </Button>
          </div>
        )}
      </form>
    </search>
  )
}
