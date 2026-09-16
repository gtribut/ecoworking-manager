import { Link } from 'react-router'
import { KpiTile } from '@/features/dashboard/KpiTile'
import { cn } from '@/lib/utils'
import { useInternalDocuments } from './useDocuments'

/**
 * KPI « Documents à valider » du dashboard bento (PRD §3.3.2, maquette C14) :
 * nombre + premier titre des documents internes non validés, carte ambre.
 * Libellé au pluriel (« Documents à valider ») dès qu'il y en a au moins un —
 * cohérent avec le titre de la même section sur `/documents` (`DocumentsPage`)
 * et repris tel quel par `e2e/auth.spec.ts`, quel que soit le nombre réel.
 * Import cross-feature de `KpiTile` (dashboard) assumé, comme `EntityBlock`
 * (billing) l'est déjà par le profil et les factures.
 *
 * Le téléchargement et la validation restent sur `/documents`
 * (`InternalDocumentItem`, page « Documents ») : cette tuile n'est qu'un
 * point d'entrée compact, pas une régression fonctionnelle — l'ancien bloc
 * pleine liste dupliquait déjà cette page (cf. rapport U4a).
 */
export function DashboardDocumentsToValidate() {
  const { data, isLoading, isError } = useInternalDocuments()

  if (isError) {
    return (
      <KpiTile
        label="Documents"
        orderFirst
        value="Indisponible"
        sub={
          <Link
            to="/documents"
            className="font-medium text-brand-700 underline underline-offset-2 dark:text-brand-300"
          >
            Voir mes documents
          </Link>
        }
      />
    )
  }

  const toValidate = data?.data.filter((document) => !document.is_validated) ?? []
  const hasPending = toValidate.length > 0
  const firstTitle = toValidate[0]?.title

  return (
    <KpiTile
      label={hasPending ? 'Documents à valider' : 'Documents'}
      loading={isLoading}
      tone={hasPending ? 'amber' : 'default'}
      orderFirst
      value={hasPending ? firstTitle : 'Documents à jour'}
      sub={
        <Link
          to="/documents"
          className={cn(
            'font-medium underline underline-offset-2',
            hasPending
              ? 'text-amber-900 dark:text-amber-200'
              : 'text-brand-700 dark:text-brand-300',
          )}
        >
          {hasPending
            ? toValidate.length > 1
              ? `+${toValidate.length - 1} autre(s) · Lire et valider`
              : 'Lire et valider'
            : 'Voir mes documents'}
        </Link>
      }
    />
  )
}
