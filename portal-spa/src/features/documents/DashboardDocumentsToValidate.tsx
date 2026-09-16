import { Link } from 'react-router'
import { KpiTile } from '@/features/dashboard/KpiTile'
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
 * **Masquée entièrement dès que rien n'est à valider** (recette R-06,
 * anomalie corrigée le 13/09 : la tuile « prenait de la place pour rien ») —
 * la grille passe alors à 3 tuiles, sans bandeau en mobile. Ne pas confondre
 * avec le chargement (`data` encore `undefined`) : la tuile reste affichée
 * (avec son skeleton) tant qu'on ne sait pas encore s'il y a des documents en
 * attente, pour éviter un flash "rien" → "quelque chose".
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
        className="col-span-2 lg:col-span-1"
        value="Indisponible"
        sub={
          <Link to="/documents" className="font-medium text-link underline underline-offset-2">
            Voir mes documents
          </Link>
        }
      />
    )
  }

  const toValidate = data?.data.filter((document) => !document.is_validated) ?? []
  const hasPending = toValidate.length > 0

  if (data && !hasPending) {
    return null
  }

  const firstTitle = toValidate[0]?.title

  return (
    <KpiTile
      label="Documents à valider"
      loading={isLoading}
      tone="amber"
      orderFirst
      className="col-span-2 lg:col-span-1"
      value={firstTitle}
      sub={
        <Link
          to="/documents"
          className="font-medium text-amber-900 underline underline-offset-2 dark:text-amber-200"
        >
          {toValidate.length > 1
            ? `+${toValidate.length - 1} autre(s) · Lire et valider`
            : 'Lire et valider'}
        </Link>
      }
    />
  )
}
