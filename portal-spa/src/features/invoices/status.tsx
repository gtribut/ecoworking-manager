/** Libellés/couleurs des statuts de facture, partagés liste + dashboard (PRD §3.3.2, §3.6.2). */
export const INVOICE_STATUS_LABELS: Record<string, string> = {
  sent: 'En attente',
  paid: 'Payée',
  partially_paid: 'Partiellement payée',
  overdue: 'En retard',
  cancelled: 'Annulée',
}

export const INVOICE_STATUS_CLASSES: Record<string, string> = {
  sent: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
  paid: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200',
  partially_paid: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
  overdue: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200',
  cancelled: 'bg-neutral-200 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300',
}

export const INVOICE_STATUS_FALLBACK_CLASS =
  'bg-neutral-200 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300'

export const euros = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' })

export function formatInvoiceDate(value: string | null): string {
  return value ? new Date(value).toLocaleDateString('fr-FR') : '—'
}

export function InvoiceStatusBadge({ status }: { status: string }) {
  return (
    <span
      className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${
        INVOICE_STATUS_CLASSES[status] ?? INVOICE_STATUS_FALLBACK_CLASS
      }`}
    >
      {INVOICE_STATUS_LABELS[status] ?? status}
    </span>
  )
}
