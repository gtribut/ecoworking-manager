import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { fetchNotifications, markAllNotificationsAsRead, markNotificationAsRead } from './api'

export const notificationsQueryKey = ['notifications'] as const

/**
 * Centre de notifications. Pas de WebSocket en MVP (PRD §3.8.4) : poll léger
 * toutes les 60 s + refetch au focus de la fenêtre pour rafraîchir le badge.
 */
export function useNotifications() {
  return useQuery({
    queryKey: notificationsQueryKey,
    queryFn: fetchNotifications,
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
