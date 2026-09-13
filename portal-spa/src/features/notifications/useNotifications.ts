import { useInfiniteQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { fetchNotifications, markAllNotificationsAsRead, markNotificationAsRead } from './api'

export const notificationsQueryKey = ['notifications'] as const

/**
 * Centre de notifications. Pas de WebSocket en MVP (PRD §3.8.4) : poll léger
 * toutes les 60 s + refetch au focus de la fenêtre pour rafraîchir le badge.
 * Pagination « Charger plus » (lot G) : `useInfiniteQuery` accumule les pages
 * déjà chargées plutôt que de les remplacer.
 *
 * `maxPages: 3` (review) : sans borne, chaque tick de `refetchInterval`
 * (et chaque refocus de fenêtre) redemande TOUTES les pages déjà chargées —
 * un membre qui a cliqué « Charger plus » plusieurs fois finirait par
 * repayer N requêtes chaque minute juste pour le badge. On borne à 3 pages
 * (60 notifications) : au-delà, la plus ancienne page chargée est évincée du
 * cache à la prochaine page suivante demandée (TanStack Query s'en charge),
 * ce qui rouvre `getNextPageParam` — sans effet perçu pour l'usage réel
 * (« Charger plus » ponctuel), tout en bornant le coût du poll périodique.
 * Alternative écartée : isoler le badge dans une query dédiée légère
 * (`GET /api/notifications?per_page=1` ou un endpoint `unread_count` séparé)
 * — plus propre à terme mais plus invasif (nouvelle route back) pour ce
 * correctif ; à reconsidérer si le badge doit un jour ignorer la pagination.
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
    maxPages: 3,
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
