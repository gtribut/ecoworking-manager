import { useInfiniteQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { fetchNotifications, markAllNotificationsAsRead, markNotificationAsRead } from './api'

export const notificationsQueryKey = ['notifications'] as const

/**
 * Centre de notifications. Pas de WebSocket en MVP (PRD §3.8.4) : poll léger
 * toutes les 60 s + refetch au focus de la fenêtre pour rafraîchir le badge.
 * Pagination « Charger plus » (lot G) : `useInfiniteQuery` accumule les pages
 * déjà chargées plutôt que de les remplacer.
 */
export function useNotifications() {
  return useInfiniteQuery({
    queryKey: notificationsQueryKey,
    queryFn: ({ pageParam }) => fetchNotifications(pageParam),
    initialPageParam: 1,
    getNextPageParam: (lastPage) =>
      lastPage.meta.current_page < lastPage.meta.last_page
        ? lastPage.meta.current_page + 1
        : undefined,
    refetchInterval: 60_000,
    refetchOnWindowFocus: true,
  })
}

export function useMarkAsRead() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (id: string) => markNotificationAsRead(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: notificationsQueryKey })
    },
  })
}

export function useMarkAllAsRead() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: () => markAllNotificationsAsRead(),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: notificationsQueryKey })
    },
  })
}
