import { Download } from 'lucide-react'
import { Link } from 'react-router'
import { Alert } from '@/components/ui/Alert'
import { Spinner } from '@/components/ui/Spinner'
import { invoicePdfUrl } from './api'
import { formatInvoiceDate, InvoiceStatusBadge } from './status'
import { DEFAULT_INVOICE_FILTERS } from './types'
import { useInvoices } from './useInvoices'

/**
 * Bloc « Mes dernières factures » du dashboard (PRD §3.3.2, recette R-05) :
 * 3 factures max (numéro, date d'émission, statut, PDF). Rendu uniquement pour
 * les contacts facturation — le parent gate sur `view-entity-invoices`.
 */
export function DashboardInvoices() {
  const { data, isLoading, isError } = useInvoices(DEFAULT_INVOICE_FILTERS)
  const invoices = data?.data.slice(0, 3) ?? []

  return (
    <section aria-labelledby="dashboard-invoices-title" className="space-y-3">
      <h2 id="dashboard-invoices-title" className="text-lg font-semibold">
        Mes dernières factures
      </h2>

      {isLoading && <Spinner label="Chargement des factures…" />}
      {isError && <Alert variant="error">Impossible de charger vos factures.</Alert>}

      {data && invoices.length === 0 && (
        <p className="text-sm text-neutral-500 dark:text-neutral-400">
          Aucune facture pour le moment.
        </p>
      )}

      {invoices.length > 0 && (
        <ul className="divide-y divide-neutral-100 rounded-lg border border-neutral-200 dark:divide-neutral-800 dark:border-neutral-800">
          {invoices.map((invoice) => (
            <li
              key={invoice.id}
              className="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3 text-sm"
            >
              <span className="font-medium">
                {invoice.number ?? '—'}
                {invoice.is_credit_note && (
                  <span className="ml-1 text-xs text-neutral-500 dark:text-neutral-400">
                    (avoir)
                  </span>
                )}
              </span>
              <span className="text-neutral-500 dark:text-neutral-400">
                {formatInvoiceDate(invoice.issued_at)}
              </span>
              <InvoiceStatusBadge status={invoice.status} />
              <span className="ml-auto">
                {invoice.pdf_available ? (
                  <a
                    href={invoicePdfUrl(invoice.id)}
                    className="inline-flex items-center gap-1 text-brand-700 underline dark:text-brand-300"
                  >
                    <Download className="size-4" aria-hidden="true" />
                    <span>
                      PDF<span className="sr-only"> de la facture {invoice.number}</span>
                    </span>
                  </a>
                ) : (
                  <span className="text-neutral-500 dark:text-neutral-400">PDF indisponible</span>
                )}
              </span>
            </li>
          ))}
        </ul>
      )}

      <p>
        <Link to="/invoices" className="text-sm text-brand-700 underline dark:text-brand-300">
          Voir toutes mes factures →
        </Link>
      </p>
    </section>
  )
}
