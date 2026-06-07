import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { fetchCalendarSubscription, regenerateCalendarToken, revokeCalendarToken } from './api'
import type { CalendarSubscription } from './types'

export const calendarQueryKey = ['calendar-subscription'] as const

export function useCalendarSubscription() {
  return useQuery({ queryKey: calendarQueryKey, queryFn: fetchCalendarSubscription })
}

export function useRegenerateCalendarToken() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: () => regenerateCalendarToken(),
    onSuccess: (data: CalendarSubscription) => {
      queryClient.setQueryData(calendarQueryKey, data)
    },
  })
}

export function useRevokeCalendarToken() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: () => revokeCalendarToken(),
    onSuccess: (data: CalendarSubscription) => {
      queryClient.setQueryData(calendarQueryKey, data)
    },
  })
}
