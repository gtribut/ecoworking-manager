import { Alert } from '@/components/ui/Alert'
import { Button } from '@/components/ui/Button'
import { getApiErrorMessage, getApiStatus } from '@/lib/errors'

interface QueryErrorProps {
  /** Erreur brute (objet Axios) : dérive le message via `getApiErrorMessage`. */
  error?: unknown
  /** Message imposé, prioritaire sur `error` (ex. libellé métier déjà stable). */
  message?: string
  fallback?: string
  /** Absent = pas de bouton (ex. jamais un vrai échec réseau à réessayer, un 403). */
  onRetry?: () => void
  className?: string
}

/**
 * Bloc d'erreur de chargement avec réessai (PRD §3.8.2, lot G) : remplace les
 * `<Alert variant="error">` statiques utilisées pour un échec de `useQuery`.
 * Un 403 API affiche déjà le message générique « Accès refusé » (lib/errors) ;
 * un 401 n'atteint jamais ce composant (redirection globale, cf. lib/http.ts).
 */
export function QueryError({
  error,
  message,
  fallback = 'Impossible de charger les données.',
  onRetry,
  className,
}: QueryErrorProps) {
  const text = message ?? getApiErrorMessage(error, fallback)
  // Contrat du composant (docstring ci-dessus) appliqué ici plutôt que laissé
  // à chaque appelant (review) : un 403 n'est jamais transitoire, « Réessayer »
  // échouerait à l'identique — le bouton est retiré même si l'appelant a
  // fourni `onRetry`, dès que l'erreur brute est disponible et vaut 403.
  const canRetry = onRetry !== undefined && getApiStatus(error) !== 403

  return (
    <Alert variant="error" className={className}>
      <div className="flex flex-wrap items-center justify-between gap-3">
        <span>{text}</span>
        {canRetry && (
          <Button type="button" variant="secondary" size="sm" onClick={onRetry}>
            Réessayer
          </Button>
        )}
      </div>
    </Alert>
  )
}
