import { Link } from 'react-router'
import { Alert } from '@/components/ui/Alert'
import { Spinner } from '@/components/ui/Spinner'
import { InternalDocumentItem } from './InternalDocumentItem'
import { useInternalDocuments } from './useDocuments'

/**
 * Bloc « Documents à valider » du dashboard (PRD §3.3.2, §5.3) : documents
 * internes non validés dans leur version courante, avec téléchargement et
 * validation directe. Non bloquant : si tout est à jour, un simple message le
 * confirme — l'accès au portail n'est jamais conditionné à la validation.
 */
export function DashboardDocumentsToValidate() {
  const { data, isLoading, isError } = useInternalDocuments()

  const toValidate = data?.data.filter((document) => !document.is_validated) ?? []

  return (
    <section aria-labelledby="dashboard-documents-title" className="space-y-3">
      <h2 id="dashboard-documents-title" className="text-lg font-semibold">
        Documents à valider
      </h2>

      {isLoading && <Spinner label="Chargement des documents…" />}
      {isError && <Alert variant="error">Impossible de charger vos documents.</Alert>}

      {data && toValidate.length === 0 && (
        <p className="text-sm text-neutral-500 dark:text-neutral-400" role="status">
          Tous vos documents sont à jour.
        </p>
      )}

      {toValidate.length > 0 && (
        <>
          <ul className="space-y-3">
            {toValidate.map((document) => (
              <InternalDocumentItem key={document.id} document={document} />
            ))}
          </ul>
          <p>
            <Link to="/documents" className="text-sm text-brand-700 dark:text-brand-300 underline">
              Voir tous mes documents →
            </Link>
          </p>
        </>
      )}
    </section>
  )
}
