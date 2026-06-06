import { QueryClient } from '@tanstack/react-query'

/**
 * Configuration TanStack Query (ADR-0006 : state serveur géré exclusivement ici,
 * jamais via useState/useEffect). Pas de retry sur 401/403/422 : ces statuts
 * sont définitifs (auth/validation), inutile de réessayer.
 */
export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 30_000,
      retry: (failureCount, error) => {
        const status = (error as { response?: { status?: number } })?.response?.status
        if (status !== undefined && [401, 403, 404, 422].includes(status)) {
          return false
        }
        return failureCount < 2
      },
    },
  },
})
