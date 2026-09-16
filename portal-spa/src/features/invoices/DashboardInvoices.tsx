import { Download } from 'lucide-react'
import { Link } from 'react-router'
import { QueryError } from '@/components/QueryError'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { invoicePdfUrl } from './api'
import { euros, formatInvoiceDate, InvoiceStatusBadge } from './status'
import { DEFAULT_INVOICE_FILTERS } from './types'
import { useInvoices } from './useInvoices'

/**
 * Bandeau « Mes dernières factures » du dashboard bento (PRD §3.3.2, maquette
 * C14) : 3 factures max en cartes (numéro, date · montant · statut, PDF).
 * Rendu uniquement pour les contacts facturation — le parent gate sur
 * `view-entity-invoices`. Titre générique, sans nom d'entité : cette donnée
 * n'est chargée nulle part ailleurs sur l'accueil (`useBillingEntities()`
 * n'est utilisé que par `InvoicesPage`), une requête dédiée pour un simple
 * ornement de titre n'en valait pas la peine (review U4a, point mineur).
 */
export function DashboardInvoices() {
  const { data, isLoading, isError, refetch } = useInvoices(DEFAULT_INVOICE_FILTERS)
  const invoices = data?.data.slice(0, 3) ?? []

  return (
    <section aria-labelledby="dashboard-invoices-title">
      <Card>
        <CardContent className="space-y-4">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <h2 id="dashboard-invoices-title" className="text-base font-semibold">
              Mes dernières factures
            </h2>
            <Link
              to="/invoices"
              className="text-sm font-medium text-link underline underline-offset-2"
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
                      {formatInvoiceDate(invoice.issued_at)} ·{' '}
                      {euros.format(Number(invoice.total_ttc))}
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
