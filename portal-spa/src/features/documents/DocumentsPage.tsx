import { Download, FileText } from 'lucide-react'
import { useState } from 'react'
import { EmptyState } from '@/components/EmptyState'
import { PageContainer } from '@/components/PageContainer'
import { PageHeader } from '@/components/PageHeader'
import { QueryError } from '@/components/QueryError'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Table,
  TableBody,
  TableCaption,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { usePermissions } from '@/features/auth/usePermissions'
import { usePageTitle } from '@/lib/usePageTitle'
import { administrativeDocumentPdfUrl } from './api'
import { formatDocumentDate, InternalDocumentItem } from './InternalDocumentItem'
import type { AdministrativeDocumentType } from './types'
import { useAdministrativeDocuments, useInternalDocuments } from './useDocuments'

const ADMINISTRATIVE_TYPE_LABELS: Record<AdministrativeDocumentType, string> = {
  contract: 'Contrat',
  amendment: 'Avenant',
  domiciliation: 'Domiciliation',
  other: 'Autre',
}

/**
 * Page « Documents » (PRD §3.3.2, §3.6.3 — C12.4) : documents internes à
 * valider (charte, CGU, droit à l'image) puis documents administratifs des
 * entités du membre (visibles du seul billing_contact — section masquée sinon,
 * PRD §3.6.1).
 */
export function DocumentsPage() {
  usePageTitle('Documents — Portail Ecoworking')

  const { has } = usePermissions()
  const canViewAdministrative = has('view-entity-admin-documents')

  const internal = useInternalDocuments()
  const [page, setPage] = useState(1)
  const administrative = useAdministrativeDocuments(page, canViewAdministrative)

  const toValidate = internal.data?.data.filter((document) => !document.is_validated) ?? []
  const validated = internal.data?.data.filter((document) => document.is_validated) ?? []

  return (
    <PageContainer width="wide" className="space-y-10">
      <PageHeader title="Documents" />

      <section aria-labelledby="internal-documents-title" className="space-y-4">
        <h2 id="internal-documents-title" className="text-lg font-semibold">
          Documents à valider
        </h2>
        <p className="text-sm text-muted-foreground">
          Chaque nouvelle version de ces documents (charte, conditions générales, droit à l’image…)
          doit être validée : téléchargez, lisez, puis validez.
        </p>

        {internal.isLoading && (
          <div role="status" className="space-y-3">
            <span className="sr-only">Chargement des documents…</span>
            <Skeleton className="h-16 w-full" />
            <Skeleton className="h-16 w-full" />
          </div>
        )}
        {internal.isError && (
          <QueryError
            message="Impossible de charger vos documents."
            onRetry={() => void internal.refetch()}
          />
        )}

        {internal.data && toValidate.length === 0 && (
          <p className="text-sm text-muted-foreground" role="status">
            Tous vos documents sont à jour.
          </p>
        )}

        {toValidate.length > 0 && (
          <ul className="space-y-3">
            {toValidate.map((document) => (
              <InternalDocumentItem key={document.id} document={document} />
            ))}
          </ul>
        )}

        {validated.length > 0 && (
          <>
            <h3 className="text-sm font-semibold text-muted-foreground">Documents déjà validés</h3>
            <ul className="space-y-3">
              {validated.map((document) => (
                <InternalDocumentItem key={document.id} document={document} />
              ))}
            </ul>
          </>
        )}
      </section>

      {canViewAdministrative && (
        <section aria-labelledby="administrative-documents-title" className="space-y-4">
          <h2 id="administrative-documents-title" className="text-lg font-semibold">
            Mes documents administratifs
          </h2>

          {administrative.isLoading && (
            <div role="status" className="space-y-2">
              <span className="sr-only">Chargement des documents administratifs…</span>
              <Skeleton className="h-10 w-full" />
              <Skeleton className="h-10 w-full" />
            </div>
          )}
          {administrative.isError && (
            <QueryError
              message="Impossible de charger vos documents administratifs."
              onRetry={() => void administrative.refetch()}
            />
          )}

          {administrative.data && administrative.data.data.length === 0 && (
            <EmptyState icon={FileText} title="Aucun document administratif pour le moment." />
          )}

          {administrative.data && administrative.data.data.length > 0 && (
            <>
              <Card>
                <CardContent className="px-0">
                  {/* `[&_th]:px-4 [&_td]:px-4` (review F-1) : les cellules `p-2`
                      par défaut collaient à 8 px du bord de la Card, moins que
                      le padding de carte habituel (16 px). */}
                  <Table className="[&_td]:px-4 [&_th]:px-4">
                    <TableCaption className="sr-only">
                      Liste de mes documents administratifs
                    </TableCaption>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Titre</TableHead>
                        <TableHead>Type</TableHead>
                        <TableHead>Entité</TableHead>
                        <TableHead>Date</TableHead>
                        <TableHead className="text-right">PDF</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {administrative.data.data.map((document) => (
                        <TableRow key={document.id}>
                          {/* `<th scope="row">` (review M-1) plutôt qu'un `TableCell`. */}
                          <th
                            scope="row"
                            className="p-2 align-middle font-medium whitespace-nowrap"
                          >
                            {document.title}
                          </th>
                          <TableCell>{ADMINISTRATIVE_TYPE_LABELS[document.type]}</TableCell>
                          <TableCell>{document.company_name ?? '—'}</TableCell>
                          <TableCell>
                            {document.document_date
                              ? formatDocumentDate(document.document_date)
                              : '—'}
                          </TableCell>
                          <TableCell className="text-right">
                            {document.pdf_available ? (
                              <a
                                href={administrativeDocumentPdfUrl(document.id)}
                                className="inline-flex items-center gap-1 text-link underline"
                              >
                                <Download className="size-4" aria-hidden="true" />
                                <span>
                                  Télécharger
                                  <span className="sr-only"> le document {document.title}</span>
                                </span>
                              </a>
                            ) : (
                              <span className="text-muted-foreground">Indisponible</span>
                            )}
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </CardContent>
              </Card>

              {administrative.data.meta.last_page > 1 && (
                <nav
                  className="flex items-center justify-between"
                  aria-label="Pagination des documents administratifs"
                >
                  <Button
                    variant="outline"
                    size="sm"
                    disabled={page <= 1}
                    onClick={() => setPage((p) => Math.max(1, p - 1))}
                  >
                    Précédent
                  </Button>
                  <span aria-live="polite" className="text-sm text-muted-foreground">
                    Page {administrative.data.meta.current_page} sur{' '}
                    {administrative.data.meta.last_page}
                  </span>
                  <Button
                    variant="outline"
                    size="sm"
                    disabled={page >= administrative.data.meta.last_page}
                    onClick={() => setPage((p) => p + 1)}
                  >
                    Suivant
                  </Button>
                </nav>
              )}
            </>
          )}
        </section>
      )}
    </PageContainer>
  )
}
