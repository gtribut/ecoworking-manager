import { useQuery, useQueryClient } from '@tanstack/react-query'
import { createContext, type ReactNode, useCallback, useEffect } from 'react'
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

  // Applique le thème de l'utilisateur (classe `dark` sur <html>).
  useEffect(() => {
    const root = document.documentElement
    root.classList.toggle('dark', user?.theme === 'dark')
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
