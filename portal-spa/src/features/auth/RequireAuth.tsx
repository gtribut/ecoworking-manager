import type { ReactNode } from 'react'
import { Navigate, useLocation } from 'react-router'
import { Spinner } from '@/components/ui/spinner'
import { useAuth } from './useAuth'

/**
 * Garde de routes : redirige vers /login si non authentifié, en mémorisant la
 * destination initiale pour y revenir après connexion. Tant que l'état d'auth
 * n'est pas résolu, affiche un indicateur (évite un flash de la page login).
 */
export function RequireAuth({ children }: { children: ReactNode }) {
  const { isAuthenticated, isLoading } = useAuth()
  const location = useLocation()

  if (isLoading) {
    return (
      <div className="flex min-h-screen items-center justify-center">
        <Spinner label="Vérification de la session…" />
      </div>
    )
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" replace state={{ from: location }} />
  }

  return <>{children}</>
}
