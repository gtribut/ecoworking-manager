import { Download } from 'lucide-react'
import { useState } from 'react'
import { Alert } from '@/components/ui/Alert'
import { Button } from '@/components/ui/Button'
import { Spinner } from '@/components/ui/Spinner'
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
    <div className="mx-auto max-w-4xl space-y-10">
      <h1 className="text-2xl font-semibold">Documents</h1>

      <section aria-labelledby="internal-documents-title" className="space-y-4">
        <h2 id="internal-documents-title" className="text-lg font-semibold">
          Documents à valider
        </h2>
        <p className="text-sm text-neutral-600 dark:text-neutral-300">
          Chaque nouvelle version de ces documents (charte, conditions générales, droit à l’image…)
          doit être validée : téléchargez, lisez, puis validez.
        </p>

        {internal.isLoading && <Spinner label="Chargement des documents…" />}
        {internal.isError && <Alert variant="error">Impossible de charger vos documents.</Alert>}

        {internal.data && toValidate.length === 0 && (
          <p className="text-sm text-neutral-500 dark:text-neutral-400" role="status">
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
            <h3 className="text-sm font-semibold text-neutral-600 dark:text-neutral-300">
              Documents déjà validés
            </h3>
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

          {administrative.isLoading && <Spinner label="Chargement des documents administratifs…" />}
          {administrative.isError && (
            <Alert variant="error">Impossible de charger vos documents administratifs.</Alert>
          )}

          {administrative.data && administrative.data.data.length === 0 && (
            <Alert variant="info">Aucun document administratif pour le moment.</Alert>
          )}

          {administrative.data && administrative.data.data.length > 0 && (
            <>
              <div className="overflow-x-auto rounded-lg border border-neutral-200 dark:border-neutral-800">
                <table className="w-full text-left text-sm">
                  <caption className="sr-only">Liste de mes documents administratifs</caption>
                  <thead className="bg-neutral-50 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">
                    <tr>
                      <th scope="col" className="px-4 py-3 font-medium">
                        Titre
                      </th>
                      <th scope="col" className="px-4 py-3 font-medium">
                        Type
                      </th>
                      <th scope="col" className="px-4 py-3 font-medium">
                        Entité
                      </th>
                      <th scope="col" className="px-4 py-3 font-medium">
                        Date
                      </th>
                      <th scope="col" className="px-4 py-3 text-right font-medium">
                        PDF
                      </th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                    {administrative.data.data.map((document) => (
                      <tr key={document.id}>
                        <th scope="row" className="px-4 py-3 font-medium">
                          {document.title}
                        </th>
                        <td className="px-4 py-3">{ADMINISTRATIVE_TYPE_LABELS[document.type]}</td>
                        <td className="px-4 py-3">{document.company_name ?? '—'}</td>
                        <td className="px-4 py-3">
                          {document.document_date
                            ? formatDocumentDate(document.document_date)
                            : '—'}
                        </td>
                        <td className="px-4 py-3 text-right">
                          {document.pdf_available ? (
                            <a
                              href={administrativeDocumentPdfUrl(document.id)}
                              className="inline-flex items-center gap-1 text-brand-700 dark:text-brand-300 underline"
                            >
                              <Download className="size-4" aria-hidden="true" />
                              <span>
                                Télécharger
                                <span className="sr-only"> le document {document.title}</span>
                              </span>
                            </a>
                          ) : (
                            <span className="text-neutral-500 dark:text-neutral-400">
                              Indisponible
                            </span>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              {administrative.data.meta.last_page > 1 && (
                <nav
                  className="flex items-center justify-between"
                  aria-label="Pagination des documents administratifs"
                >
                  <Button
                    variant="secondary"
                    size="sm"
                    disabled={page <= 1}
                    onClick={() => setPage((p) => Math.max(1, p - 1))}
                  >
                    Précédent
                  </Button>
                  <span
                    aria-live="polite"
                    className="text-sm text-neutral-600 dark:text-neutral-300"
                  >
                    Page {administrative.data.meta.current_page} sur{' '}
                    {administrative.data.meta.last_page}
                  </span>
                  <Button
                    variant="secondary"
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
    </div>
  )
}
