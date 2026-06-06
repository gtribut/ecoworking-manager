import { Download } from 'lucide-react'
import { useState } from 'react'
import { Alert } from '@/components/ui/Alert'
import { Button } from '@/components/ui/Button'
import { Spinner } from '@/components/ui/Spinner'
import { invoicePdfUrl } from './api'
import { useInvoices } from './useInvoices'

const STATUS_LABELS: Record<string, string> = {
  sent: 'Émise',
  paid: 'Payée',
  partially_paid: 'Partiellement payée',
  overdue: 'En retard',
  cancelled: 'Annulée',
}

const STATUS_CLASSES: Record<string, string> = {
  sent: 'bg-blue-100 text-blue-800',
  paid: 'bg-green-100 text-green-800',
  partially_paid: 'bg-amber-100 text-amber-800',
  overdue: 'bg-red-100 text-red-800',
  cancelled: 'bg-neutral-200 text-neutral-700',
}

const euros = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' })

function formatDate(value: string | null): string {
  return value ? new Date(value).toLocaleDateString('fr-FR') : '—'
}

export function InvoicesPage() {
  const [page, setPage] = useState(1)
  const { data, isLoading, isError } = useInvoices(page)

  return (
    <div className="mx-auto max-w-4xl space-y-6">
      <h1 className="text-2xl font-semibold">Mes factures</h1>

      {isLoading && <Spinner label="Chargement des factures…" />}
      {isError && <Alert variant="error">Impossible de charger vos factures.</Alert>}

      {data && data.data.length === 0 && (
        <Alert variant="info">Aucune facture pour le moment.</Alert>
      )}

      {data && data.data.length > 0 && (
        <>
          <div className="overflow-x-auto rounded-lg border border-neutral-200 dark:border-neutral-800">
            <table className="w-full text-left text-sm">
              <caption className="sr-only">Liste de mes factures</caption>
              <thead className="bg-neutral-50 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">
                <tr>
                  <th scope="col" className="px-4 py-3 font-medium">
                    Numéro
                  </th>
                  <th scope="col" className="px-4 py-3 font-medium">
                    Date
                  </th>
                  <th scope="col" className="px-4 py-3 font-medium">
                    Statut
                  </th>
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
                        <span className="ml-2 text-xs text-neutral-500">(avoir)</span>
                      )}
                    </th>
                    <td className="px-4 py-3">{formatDate(invoice.issued_at)}</td>
                    <td className="px-4 py-3">
                      <span
                        className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${
                          STATUS_CLASSES[invoice.status] ?? 'bg-neutral-200 text-neutral-700'
                        }`}
                      >
                        {STATUS_LABELS[invoice.status] ?? invoice.status}
                      </span>
                    </td>
                    <td className="px-4 py-3 text-right tabular-nums">
                      {euros.format(Number(invoice.total_ttc))}
                    </td>
                    <td className="px-4 py-3 text-right">
                      {invoice.pdf_available ? (
                        <a
                          href={invoicePdfUrl(invoice.id)}
                          className="inline-flex items-center gap-1 text-brand-700 underline"
                        >
                          <Download className="size-4" aria-hidden="true" />
                          <span>
                            Télécharger<span className="sr-only"> la facture {invoice.number}</span>
                          </span>
                        </a>
                      ) : (
                        <span className="text-neutral-400">Indisponible</span>
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
                disabled={page <= 1}
                onClick={() => setPage((p) => Math.max(1, p - 1))}
              >
                Précédent
              </Button>
              <span className="text-sm text-neutral-600 dark:text-neutral-300">
                Page {data.meta.current_page} sur {data.meta.last_page}
              </span>
              <Button
                variant="secondary"
                size="sm"
                disabled={page >= data.meta.last_page}
                onClick={() => setPage((p) => p + 1)}
              >
                Suivant
              </Button>
            </nav>
          )}
        </>
      )}
    </div>
  )
}
