import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { act, renderHook, waitFor } from '@testing-library/react'
import { HttpResponse, http } from 'msw'
import type { ReactNode } from 'react'
import { describe, expect, it } from 'vitest'
import { AuthProvider } from '@/features/auth/AuthContext'
import { server } from '@/test/server'
import { makeAuthUser } from '@/test/utils'
import { useThemeSelection } from './useThemeSelection'

function wrapper(client: QueryClient) {
  return function Wrapper({ children }: { children: ReactNode }) {
    return (
      <QueryClientProvider client={client}>
        <AuthProvider>{children}</AuthProvider>
      </QueryClientProvider>
    )
  }
}

/**
 * Review U5 : `useThemeSelection` était naguère un `useState` par instance
 * (sidebar, menu compact mobile, top bar) — trois copies désynchronisées tant
 * que `PATCH /api/profile` n'avait pas répondu. Ce test simule deux instances
 * du hook sous le même `QueryClient` (comme le sont réellement `ProfileMenu`
 * et `ThemeToggle`, montés simultanément dans `Layout`) et vérifie qu'elles
 * se resynchronisent au même rendu, sans attendre la réponse serveur.
 */
describe('useThemeSelection — synchronisation multi-instances', () => {
  it('deux instances reflètent le même thème dès la sélection (avant même la réponse serveur)', async () => {
    server.use(
      http.get('/api/user', () => HttpResponse.json(makeAuthUser({ theme: null }))),
      http.patch('/api/profile', async ({ request }) => {
        const body = (await request.json()) as { theme?: 'light' | 'dark' | null }
        return HttpResponse.json({
          user: makeAuthUser({ theme: body.theme ?? null }),
          profile: null,
          company: null,
        })
      }),
    )

    const client = new QueryClient({
      defaultOptions: { queries: { retry: false, gcTime: 0 } },
    })

    const { result: sidebar } = renderHook(() => useThemeSelection(), {
      wrapper: wrapper(client),
    })
    const { result: topBar } = renderHook(() => useThemeSelection(), {
      wrapper: wrapper(client),
    })

    await waitFor(() => expect(sidebar.current.theme).toBeNull())
    await waitFor(() => expect(topBar.current.theme).toBeNull())

    act(() => {
      sidebar.current.selectTheme('dark')
    })

    // Synchronisation immédiate : les deux instances lisent le même cache
    // (`authQueryKey`), écrit en optimiste par `useUpdateTheme` (`onMutate`).
    await waitFor(() => expect(sidebar.current.theme).toBe('dark'))
    await waitFor(() => expect(topBar.current.theme).toBe('dark'))
    expect(document.documentElement.classList.contains('dark')).toBe(true)
  })

  it('un échec serveur fait revenir les deux instances à la valeur précédente', async () => {
    server.use(
      http.get('/api/user', () => HttpResponse.json(makeAuthUser({ theme: null }))),
      http.patch('/api/profile', () => new HttpResponse(null, { status: 500 })),
    )

    const client = new QueryClient({
      defaultOptions: { queries: { retry: false, gcTime: 0 } },
    })

    const { result: sidebar } = renderHook(() => useThemeSelection(), {
      wrapper: wrapper(client),
    })
    const { result: topBar } = renderHook(() => useThemeSelection(), {
      wrapper: wrapper(client),
    })

    await waitFor(() => expect(sidebar.current.theme).toBeNull())

    act(() => {
      sidebar.current.selectTheme('dark')
    })

    await waitFor(() => expect(sidebar.current.theme).toBeNull())
    await waitFor(() => expect(topBar.current.theme).toBeNull())
    expect(document.documentElement.classList.contains('dark')).toBe(false)
  })
})
