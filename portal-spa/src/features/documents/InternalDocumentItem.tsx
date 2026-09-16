import { Download } from 'lucide-react'
import { useState } from 'react'
import { Alert } from '@/components/ui/alert'
import { ConfirmButton } from '@/components/ui/confirm-button'
import { usePermissions } from '@/features/auth/usePermissions'
import { getApiErrorMessage } from '@/lib/errors'
import { internalDocumentPdfUrl } from './api'
import type { InternalDocument } from './types'
import { useValidateInternalDocument } from './useDocuments'

export function formatDocumentDate(value: string): string {
  return new Date(value).toLocaleDateString('fr-FR')
}

/**
 * Ligne « document interne » partagée entre la page Documents et le bloc
 * dashboard (PRD §3.3.2) : titre + version, téléchargement PDF, bouton
 * « Valider » avec confirmation en deux temps, ou date de validation.
 * Le serveur reste l'autorité (audience, version, double validation).
 */
export function InternalDocumentItem({ document }: { document: InternalDocument }) {
  const { has } = usePermissions()
  const validate = useValidateInternalDocument()
  const [error, setError] = useState<string | null>(null)

  const handleValidate = () => {
    setError(null)
    validate.mutate(document.id, {
      onError: (mutationError) =>
        setError(getApiErrorMessage(mutationError, 'La validation a échoué.')),
    })
  }

  return (
    <li className="rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="font-medium">
          {document.title}{' '}
          <span className="text-xs font-normal text-neutral-500 dark:text-neutral-400">
            (version {document.version})
          </span>
        </p>

        <div className="flex flex-wrap items-center gap-3">
          {document.pdf_available && (
            <a
              href={internalDocumentPdfUrl(document.id)}
              className="inline-flex items-center gap-1 text-sm text-brand-700 dark:text-brand-300 underline"
            >
              <Download className="size-4" aria-hidden="true" />
              <span>
                Télécharger<span className="sr-only"> le document {document.title} (PDF)</span>
              </span>
            </a>
          )}

          {document.is_validated && document.validated_at ? (
            <p className="text-sm font-medium text-green-700 dark:text-green-300">
              Validé le {formatDocumentDate(document.validated_at)}
            </p>
          ) : (
            has('validate-internal-document') && (
              <ConfirmButton
                size="sm"
                disabled={validate.isPending}
                confirmMessage={`Valider « ${document.title} » ? Cette action atteste que vous en avez pris connaissance.`}
                onConfirm={handleValidate}
              >
                Valider
              </ConfirmButton>
            )
          )}
        </div>
      </div>

      {error && <Alert variant="error">{error}</Alert>}
    </li>
  )
}
