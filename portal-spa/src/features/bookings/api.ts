import type { Paginated } from '@/lib/api-types'
import { http } from '@/lib/http'
import type {
  Booking,
  CreateBookingInput,
  Room,
  RoomAvailability,
  RoomsAvailability,
  UpdateBookingInput,
} from './types'

export async function fetchRooms(): Promise<Room[]> {
  const { data } = await http.get<{ data: Room[] }>('/api/rooms')
  return data.data
}

export async function fetchRoomAvailability(
  roomId: number,
  date: string,
): Promise<RoomAvailability> {
  const { data } = await http.get<RoomAvailability>(`/api/rooms/${roomId}/availability`, {
    params: { date },
  })
  return data
}

/**
 * Disponibilité de toutes les salles sur une plage (calendrier PRD §3.5.2).
 * `roomIds` vide = toutes les salles du calendrier.
 */
export async function fetchRoomsAvailability(
  from: string,
  to: string,
  roomIds: number[] = [],
): Promise<RoomsAvailability> {
  const { data } = await http.get<RoomsAvailability>('/api/rooms/availability', {
    params: { from, to, ...(roomIds.length > 0 ? { rooms: roomIds } : {}) },
  })
  return data
}

/** Liste des réservations du membre : à venir (défaut) ou historique. */
export async function fetchBookings(
  scope: 'upcoming' | 'past',
  page = 1,
): Promise<Paginated<Booking>> {
  const { data } = await http.get<Paginated<Booking>>('/api/bookings', {
    params: { page, [scope]: 1 },
  })
  return data
}

/** Prochaines résas confirmées (dashboard PRD §3.3.2), chronologiques, `limit` max. */
export async function fetchUpcomingBookings(limit = 3): Promise<Booking[]> {
  const { data } = await http.get<Paginated<Booking>>('/api/bookings', {
    params: { upcoming: 1, per_page: limit },
  })
  return data.data
}

export async function createBooking(input: CreateBookingInput): Promise<Booking> {
  const { data } = await http.post<{ data: Booking }>('/api/bookings', input)
  return data.data
}

export async function updateBooking({ id, payload }: UpdateBookingInput): Promise<Booking> {
  const { data } = await http.patch<{ data: Booking }>(`/api/bookings/${id}`, payload)
  return data.data
}

export async function cancelBooking(bookingId: number): Promise<void> {
  await http.delete(`/api/bookings/${bookingId}`)
}
