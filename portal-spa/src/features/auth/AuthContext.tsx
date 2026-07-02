import { useQuery, useQueryClient } from '@tanstack/react-query'
import { createContext, type ReactNode, useCallback, useEffect } from 'react'
import { setSessionExpiredHandler } from '@/lib/http'
import { fetchCurrentUser, logout as logoutRequest } from './api'
import type { AuthUser } from './types'

export interface AuthContextValue {
  user: AuthUser | null
  isLoading: boolean
  isAuthenticated: boolean
  refetchUser: () => Promise<unknown>
  logout: () => Promise<void>
}

export const authQueryKey = ['auth', 'me'] as const

export const AuthContext = createContext<AuthContextValue | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient()

  const { data, isLoading, refetch } = useQuery({
    queryKey: authQueryKey,
    queryFn: fetchCurrentUser,
    retry: false,
    staleTime: 60_000,
  })

  const user = data ?? null

  // Session expirée détectée par l'interceptor HTTP (401/419) : on purge la
  // query auth → RequireAuth redirige vers /login au rendu suivant.
  useEffect(() => {
    setSessionExpiredHandler(() => {
      queryClient.setQueryData(authQueryKey, null)
      void queryClient.invalidateQueries({ queryKey: authQueryKey })
    })
    return () => setSessionExpiredHandler(null)
  }, [queryClient])

  // Applique le thème (classe `dark` sur <html>). `theme: null` = automatique :
  // on suit `prefers-color-scheme` (et ses changements) au lieu de forcer le clair.
  useEffect(() => {
    const root = document.documentElement
    const theme = user?.theme ?? null

    if (theme !== null) {
      root.classList.toggle('dark', theme === 'dark')
      return
    }

    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)')
    const applySystemTheme = () => root.classList.toggle('dark', mediaQuery.matches)
    applySystemTheme()
    mediaQuery.addEventListener('change', applySystemTheme)
    return () => mediaQuery.removeEventListener('change', applySystemTheme)
  }, [user?.theme])

  const logout = useCallback(async () => {
    await logoutRequest()
    queryClient.setQueryData(authQueryKey, null)
    await queryClient.invalidateQueries()
  }, [queryClient])

  const value: AuthContextValue = {
    user,
    isLoading,
    isAuthenticated: user !== null,
    refetchUser: refetch,
    logout,
  }

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
