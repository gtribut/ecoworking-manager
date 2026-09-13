import { Link } from 'react-router'
import { usePageTitle } from '@/lib/usePageTitle'

/**
 * Écran « Accès refusé » (PRD §3.8.2) : un module interdit au rôle de
 * l'utilisateur le dit, au lieu d'une redirection silencieuse vers l'accueil.
 * Rendu dans le <main> du Layout (la navigation reste disponible).
 */
export function Forbidden() {
  usePageTitle('Accès refusé — Portail Ecoworking')

  return (
    <div className="mx-auto max-w-2xl space-y-4 text-center">
      <h1 className="text-2xl font-semibold">Accès refusé</h1>
      <p className="text-neutral-600 dark:text-neutral-300">
        Cette page n’est pas accessible avec votre profil. Si vous pensez qu’il s’agit d’une erreur,
        contactez Ecoworking.
      </p>
      <p>
        <Link to="/" className="text-brand-700 dark:text-brand-300 underline">
          Retour à l’accueil
        </Link>
      </p>
    </div>
  )
}
