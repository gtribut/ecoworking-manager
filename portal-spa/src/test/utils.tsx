import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { type RenderResult, render } from '@testing-library/react'
import type { ReactElement, ReactNode } from 'react'
import { MemoryRouter } from 'react-router'
import { AuthProvider } from '@/features/auth/AuthContext'

/** QueryClient isolé par test, sans retry (pas de flakiness ni d'attente). */
export function makeTestQueryClient(): QueryClient {
  return new QueryClient({
    defaultOptions: {
      queries: { retry: false, gcTime: 0 },
      mutations: { retry: false },
    },
  })
}

export function renderWithProviders(
  ui: ReactElement,
  { route = '/', withAuth = false }: { route?: string; withAuth?: boolean } = {},
): RenderResult {
  const client = makeTestQueryClient()

  function Wrapper({ children }: { children: ReactNode }) {
    const tree = withAuth ? <AuthProvider>{children}</AuthProvider> : children
    return (
      <QueryClientProvider client={client}>
        <MemoryRouter initialEntries={[route]}>{tree}</MemoryRouter>
      </QueryClientProvider>
    )
  }

  return render(ui, { wrapper: Wrapper })
}
