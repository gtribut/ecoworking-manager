import { useContext } from 'react'
import { AuthContext, type AuthContextValue } from './AuthContext'

/** Accès au contexte d'authentification ; échoue hors d'un AuthProvider. */
export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext)
  if (ctx === null) {
    throw new Error('useAuth doit être utilisé à l’intérieur d’un <AuthProvider>.')
  }
  return ctx
}
