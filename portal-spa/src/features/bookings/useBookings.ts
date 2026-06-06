import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  cancelBooking,
  createBooking,
  fetchBookings,
  fetchRoomAvailability,
  fetchRooms,
} from './api'
import type { CreateBookingInput } from './types'

export const roomsQueryKey = ['rooms'] as const
export const bookingsQueryKey = (page: number) => ['bookings', page] as const
export const roomAvailabilityQueryKey = (roomId: number, date: string) =>
  ['rooms', roomId, 'availability', date] as const

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

export function useBookings(page: number) {
  return useQuery({
    queryKey: bookingsQueryKey(page),
    queryFn: () => fetchBookings(page),
    placeholderData: keepPreviousData,
  })
}

export function useCreateBooking() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (input: CreateBookingInput) => createBooking(input),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['bookings'] })
      queryClient.invalidateQueries({ queryKey: ['rooms'] })
    },
  })
}

export function useCancelBooking() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (bookingId: number) => cancelBooking(bookingId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['bookings'] })
      queryClient.invalidateQueries({ queryKey: ['rooms'] })
    },
  })
}
