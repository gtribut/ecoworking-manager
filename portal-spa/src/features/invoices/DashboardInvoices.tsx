import { Download } from 'lucide-react'
import { Link } from 'react-router'
import { QueryError } from '@/components/QueryError'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { useBillingEntities } from '@/features/billing/useBillingEntities'
import { invoicePdfUrl } from './api'
import { formatInvoiceDate, InvoiceStatusBadge } from './status'
import { DEFAULT_INVOICE_FILTERS } from './types'
import { useInvoices } from './useInvoices'

/**
 * Bandeau « Mes dernières factures » du dashboard bento (PRD §3.3.2, maquette
 * C14) : 3 factures max en cartes (numéro, date · statut, PDF). Rendu
 * uniquement pour les contacts facturation — le parent gate sur
 * `view-entity-invoices`. Le nom de l'entité complète le titre quand le
 * membre n'en a qu'une seule (cas le plus courant, PRD §3.6.4) ; avec zéro ou
 * plusieurs entités le titre générique reste plus honnête qu'un choix arbitraire.
 */
export function DashboardInvoices() {
  const { data, isLoading, isError, refetch } = useInvoices(DEFAULT_INVOICE_FILTERS)
  const entities = useBillingEntities()
  const invoices = data?.data.slice(0, 3) ?? []
  const singleEntityName = entities.data?.length === 1 ? entities.data[0]?.name : null

  return (
    <section aria-labelledby="dashboard-invoices-title">
      <Card>
        <CardContent className="space-y-4">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <h2 id="dashboard-invoices-title" className="text-base font-semibold">
              Mes dernières factures{singleEntityName ? ` · ${singleEntityName}` : ''}
            </h2>
            <Link
              to="/invoices"
              className="text-sm font-medium text-brand-700 underline underline-offset-2 dark:text-brand-300"
            >
              Toutes mes factures
            </Link>
          </div>

          {isLoading && (
            <div className="grid gap-3 sm:grid-cols-3">
              <Skeleton className="h-16 w-full" />
              <Skeleton className="h-16 w-full" />
              <Skeleton className="h-16 w-full" />
            </div>
          )}
          {isError && (
            <QueryError
              message="Impossible de charger vos factures."
              onRetry={() => void refetch()}
            />
          )}

          {data && invoices.length === 0 && (
            <p className="text-sm text-muted-foreground">Aucune facture pour le moment.</p>
          )}

          {invoices.length > 0 && (
            <ul className="grid gap-3 sm:grid-cols-3">
              {invoices.map((invoice) => (
                <li
                  key={invoice.id}
                  className="flex items-center gap-3 rounded-lg border border-border p-3 text-sm"
                >
                  <span className="min-w-0 flex-1">
                    <span className="block truncate font-medium">
                      {invoice.number ?? '—'}
                      {invoice.is_credit_note && (
                        <span className="ml-1 text-xs font-normal text-muted-foreground">
                          (avoir)
                        </span>
                      )}
                    </span>
                    <span className="block truncate text-muted-foreground">
                      {formatInvoiceDate(invoice.issued_at)}
                    </span>
                  </span>
                  <InvoiceStatusBadge status={invoice.status} />
                  {invoice.pdf_available ? (
                    <Button asChild variant="outline" size="sm">
                      <a href={invoicePdfUrl(invoice.id)}>
                        <Download aria-hidden="true" />
                        <span>
                          PDF<span className="sr-only"> de la facture {invoice.number}</span>
                        </span>
                      </a>
                    </Button>
                  ) : (
                    <span className="text-xs text-muted-foreground">Indisponible</span>
                  )}
                </li>
              ))}
            </ul>
          )}
        </CardContent>
      </Card>
    </section>
  )
}
