import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ticketsQueryKey } from '@/features/tickets/useTickets'
import {
  cancelBooking,
  createBooking,
  fetchBookings,
  fetchRoomAvailability,
  fetchRooms,
  fetchRoomsAvailability,
  fetchUpcomingBookings,
  updateBooking,
} from './api'
import type { CreateBookingInput, UpdateBookingInput } from './types'

export const roomsQueryKey = ['rooms'] as const
export const bookingsQueryKey = (scope: 'upcoming' | 'past', page: number) =>
  ['bookings', scope, page] as const
/** Préfixe `bookings` conservé : invalidé par création/annulation comme la liste. */
export const upcomingBookingsQueryKey = (limit: number) => ['bookings', 'upcoming', limit] as const
export const roomAvailabilityQueryKey = (roomId: number, date: string) =>
  ['rooms', roomId, 'availability', date] as const
export const roomsAvailabilityQueryKey = (from: string, to: string, roomIds: number[]) =>
  ['rooms', 'availability', from, to, [...roomIds].sort((a, b) => a - b).join(',')] as const

export function useRooms() {
  return useQuery({ queryKey: roomsQueryKey, queryFn: fetchRooms })
}

export function useRoomAvailability(roomId: number | null, date: string) {
  return useQuery({
    queryKey: roomAvailabilityQueryKey(roomId ?? 0, date),
    queryFn: () => fetchRoomAvailability(roomId as number, date),
    enabled: roomId !== null && date !== '',
  })
}

/** Disponibilité multi-salles de la plage affichée par le calendrier. */
export function useRoomsAvailability(from: string, to: string, roomIds: number[] = []) {
  return useQuery({
    queryKey: roomsAvailabilityQueryKey(from, to, roomIds),
    queryFn: () => fetchRoomsAvailability(from, to, roomIds),
    enabled: from !== '' && to !== '',
    placeholderData: keepPreviousData,
  })
}

export function useBookings(scope: 'upcoming' | 'past', page: number) {
  return useQuery({
    queryKey: bookingsQueryKey(scope, page),
    queryFn: () => fetchBookings(scope, page),
    placeholderData: keepPreviousData,
  })
}

export function useUpcomingBookings(limit = 3) {
  return useQuery({
    queryKey: upcomingBookingsQueryKey(limit),
    queryFn: () => fetchUpcomingBookings(limit),
  })
}

/** Invalide tout ce qu'une écriture de réservation périme (listes, calendrier, tickets). */
function useInvalidateBookings() {
  const queryClient = useQueryClient()

  return () => {
    queryClient.invalidateQueries({ queryKey: ['bookings'] })
    queryClient.invalidateQueries({ queryKey: ['rooms'] })
    // Une résa de salle payante consomme un ticket : le solde affiché
    // (TicketsPage) doit être rafraîchi, comme pour les bureaux nomades.
    queryClient.invalidateQueries({ queryKey: ticketsQueryKey })
  }
}

export function useCreateBooking() {
  const invalidate = useInvalidateBookings()

  return useMutation({
    mutationFn: (input: CreateBookingInput) => createBooking(input),
    onSuccess: invalidate,
  })
}

export function useUpdateBooking() {
  const invalidate = useInvalidateBookings()

  return useMutation({
    mutationFn: (input: UpdateBookingInput) => updateBooking(input),
    onSuccess: invalidate,
  })
}

export function useCancelBooking() {
  const invalidate = useInvalidateBookings()

  return useMutation({
    mutationFn: (bookingId: number) => cancelBooking(bookingId),
    onSuccess: invalidate,
  })
}
