import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { fetchAnnouncement, fetchAnnouncements, registerToEvent, unregisterFromEvent } from './api'

export const announcementsQueryKey = (page: number) => ['announcements', page] as const
export const announcementQueryKey = (id: number) => ['announcements', 'detail', id] as const

export function useAnnouncements(page: number) {
  return useQuery({
    queryKey: announcementsQueryKey(page),
    queryFn: () => fetchAnnouncements(page),
    placeholderData: keepPreviousData,
  })
}

export function useAnnouncement(id: number) {
  return useQuery({
    queryKey: announcementQueryKey(id),
    queryFn: () => fetchAnnouncement(id),
  })
}

/** Invalidation commune : liste, détail et bloc « à la une » du dashboard. */
function useInvalidateAnnouncements() {
  const queryClient = useQueryClient()
  return () => queryClient.invalidateQueries({ queryKey: ['announcements'] })
}

export function useRegisterToEvent() {
  const invalidate = useInvalidateAnnouncements()

  return useMutation({
    mutationFn: (announcementId: number) => registerToEvent(announcementId),
    onSuccess: invalidate,
  })
}

export function useUnregisterFromEvent() {
  const invalidate = useInvalidateAnnouncements()

  return useMutation({
    mutationFn: (announcementId: number) => unregisterFromEvent(announcementId),
    onSuccess: invalidate,
  })
}
