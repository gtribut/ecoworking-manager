import type { ReactNode } from 'react'
import { Forbidden } from '@/components/Forbidden'
import { usePermissions } from './usePermissions'

interface RequireAccessProps {
  /** Permission requise (nom exact de `App\Enums\Permission`), si le module en dépend. */
  permission?: string
  /** Module réservé au membre doté d'un bureau attitré (PRD §2.5, `has_desk`). */
  requiresDesk?: boolean
  children: ReactNode
}

/**
 * Garde de route par rôle (PRD §2.5) : un module masqué de la navigation ne
 * doit pas non plus être atteignable par URL directe. Affiche « Accès refusé »
 * (PRD §3.8.2) plutôt qu'une redirection silencieuse — le serveur reste
 * l'autorité (403 côté API).
 *
 * Toujours rendue SOUS `RequireAuth` : l'utilisateur est déjà chargé, aucun
 * refus ne peut s'afficher pendant la résolution de la session.
 */
export function RequireAccess({ permission, requiresDesk, children }: RequireAccessProps) {
  const { has, isResident } = usePermissions()

  const allowed =
    (permission === undefined || has(permission)) && (requiresDesk !== true || isResident)

  if (!allowed) {
    return <Forbidden />
  }

  return <>{children}</>
}
