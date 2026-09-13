import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { renderHook, waitFor } from '@testing-library/react'
import { HttpResponse, http } from 'msw'
import type { ReactNode } from 'react'
import { describe, expect, it } from 'vitest'
import { server } from '@/test/server'
import type { NotificationsResponse } from './types'
import { useNotifications } from './useNotifications'

function page(current: number, lastPage: number): NotificationsResponse {
  return {
    data: [
      {
        id: `n${current}`,
        data: { type: 'invoice.issued', message: `Page ${current}` },
        read_at: null,
        is_read: true,
        created_at: null,
      },
    ],
    meta: {
      current_page: current,
      last_page: lastPage,
      per_page: 1,
      total: lastPage,
      unread_count: 0,
    },
  }
}

function wrapper(client: QueryClient) {
  return function Wrapper({ children }: { children: ReactNode }) {
    return <QueryClientProvider client={client}>{children}</QueryClientProvider>
  }
}

describe('useNotifications — maxPages (review)', () => {
  it('ne conserve/ne refetch jamais plus de 3 pages, même après en avoir chargé davantage', async () => {
    const requestedPages: number[] = []
    server.use(
      http.get('/api/notifications', ({ request }) => {
        const current = Number(new URL(request.url).searchParams.get('page') ?? '1')
        requestedPages.push(current)
        return HttpResponse.json(page(current, 5))
      }),
    )

    const client = new QueryClient({
      defaultOptions: { queries: { retry: false, gcTime: 0 } },
    })

    const { result } = renderHook(() => useNotifications(), { wrapper: wrapper(client) })

    await waitFor(() => expect(result.current.data).toBeDefined())

    // Charge les pages 2, 3 puis 4 : 4 pages au total, au-delà de maxPages=3.
    for (let i = 0; i < 3; i += 1) {
      await result.current.fetchNextPage()
      await waitFor(() => expect(result.current.isFetchingNextPage).toBe(false))
    }

    expect(result.current.data?.pages.length).toBeLessThanOrEqual(3)

    // Un refetch complet (poll périodique ou refocus fenêtre) ne doit jamais
    // redemander plus de 3 pages, même si 4 ont déjà été chargées via
    // « Charger plus » (review : sans borne, le coût du poll croît avec
    // l'historique parcouru).
    requestedPages.length = 0
    await result.current.refetch()
    await waitFor(() => expect(result.current.isRefetching).toBe(false))

    expect(requestedPages.length).toBeLessThanOrEqual(3)
  })
})
