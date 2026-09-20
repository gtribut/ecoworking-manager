import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  cancelDeskOccupation,
  createDeskOccupation,
  fetchDeskAvailability,
  fetchDeskOccupations,
  fetchTickets,
} from './api'
import type { CreateDeskOccupationInput, DeskPeriod } from './types'

export const ticketsQueryKey = ['tickets'] as const
export const deskAvailabilityQueryKey = (date: string, period: DeskPeriod) =>
  ['desks', 'availability', date, period] as const
export const deskOccupationsQueryKey = (scope: 'upcoming' | 'past', page: number) =>
  ['desk-occupations', scope, page] as const

/**
 * Soldes de tickets — réservé aux `external` : `GET /api/tickets` répond 403
 * aux autres rôles (recette 2026-09-20 : la page Réservations le demandait
 * pour tout le monde et générait un 403 à chaque chargement / retour d'onglet).
 * Les appelants passent donc `enabled: isExternal`.
 */
export function useTickets(options: { enabled?: boolean } = {}) {
  return useQuery({
    queryKey: ticketsQueryKey,
    queryFn: fetchTickets,
    enabled: options.enabled ?? true,
  })
}

export function useDeskAvailability(date: string, period: DeskPeriod, enabled: boolean) {
  return useQuery({
    queryKey: deskAvailabilityQueryKey(date, period),
    queryFn: () => fetchDeskAvailability(date, period),
    enabled,
  })
}

/** « Mes bureaux réservés » (PRD §3.5.9) : à venir (défaut) ou historique. */
export function useDeskOccupations(scope: 'upcoming' | 'past', page: number) {
  return useQuery({
    queryKey: deskOccupationsQueryKey(scope, page),
    queryFn: () => fetchDeskOccupations(scope, page),
    placeholderData: keepPreviousData,
  })
}

export function useCreateDeskOccupation() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (input: CreateDeskOccupationInput) => createDeskOccupation(input),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ticketsQueryKey })
      queryClient.invalidateQueries({ queryKey: ['desks', 'availability'] })
      queryClient.invalidateQueries({ queryKey: ['desk-occupations'] })
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
      queryClient.invalidateQueries({ queryKey: ['desk-occupations'] })
    },
  })
}
