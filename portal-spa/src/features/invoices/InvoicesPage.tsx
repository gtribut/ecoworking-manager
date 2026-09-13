import { Download } from 'lucide-react'
import { useState } from 'react'
import { Alert } from '@/components/ui/Alert'
import { Button } from '@/components/ui/Button'
import { Spinner } from '@/components/ui/Spinner'
import { usePageTitle } from '@/lib/usePageTitle'
import { invoicePdfUrl } from './api'
import { euros, formatInvoiceDate, InvoiceStatusBadge } from './status'
import { useInvoices } from './useInvoices'

export function InvoicesPage() {
  usePageTitle('Factures — Portail Ecoworking')

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
                disabled={page <= 1}
                onClick={() => setPage((p) => Math.max(1, p - 1))}
              >
                Précédent
              </Button>
              <span aria-live="polite" className="text-sm text-neutral-600 dark:text-neutral-300">
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
