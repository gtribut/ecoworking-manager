import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  cancelDeskOccupation,
  createDeskOccupation,
  fetchDeskAvailability,
  fetchTickets,
} from './api'
import type { CreateDeskOccupationInput, DeskPeriod } from './types'

export const ticketsQueryKey = ['tickets'] as const
export const deskAvailabilityQueryKey = (date: string, period: DeskPeriod) =>
  ['desks', 'availability', date, period] as const

export function useTickets() {
  return useQuery({ queryKey: ticketsQueryKey, queryFn: fetchTickets })
}

export function useDeskAvailability(date: string, period: DeskPeriod, enabled: boolean) {
  return useQuery({
    queryKey: deskAvailabilityQueryKey(date, period),
    queryFn: () => fetchDeskAvailability(date, period),
    enabled,
  })
}

export function useCreateDeskOccupation() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (input: CreateDeskOccupationInput) => createDeskOccupation(input),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ticketsQueryKey })
      queryClient.invalidateQueries({ queryKey: ['desks', 'availability'] })
    },
  })
}

export function useCancelDeskOccupation() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (occupationId: number) => cancelDeskOccupation(occupationId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ticketsQueryKey })
      queryClient.invalidateQueries({ queryKey: ['desks', 'availability'] })
    },
  })
}
