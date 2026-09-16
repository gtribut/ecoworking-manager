import { Link } from 'react-router'
import { PageContainer } from '@/components/PageContainer'
import { PageHeader } from '@/components/PageHeader'
import { usePageTitle } from '@/lib/usePageTitle'

/**
 * Écran « Accès refusé » (PRD §3.8.2) : un module interdit au rôle de
 * l'utilisateur le dit, au lieu d'une redirection silencieuse vers l'accueil.
 * Rendu dans le <main> du Layout (la navigation reste disponible).
 */
export function Forbidden() {
  usePageTitle('Accès refusé — Portail Ecoworking')

  return (
    <PageContainer width="narrow" className="space-y-4 text-center">
      <PageHeader title="Accès refusé" />
      <p className="text-neutral-600 dark:text-neutral-300">
        Cette page n’est pas accessible avec votre profil. Si vous pensez qu’il s’agit d’une erreur,
        contactez Ecoworking.
      </p>
      <p>
        <Link to="/" className="text-link underline">
          Retour à l’accueil
        </Link>
      </p>
    </PageContainer>
  )
}
