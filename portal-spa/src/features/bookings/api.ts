import type { Paginated } from '@/lib/api-types'
import { http } from '@/lib/http'
import type { Booking, CreateBookingInput, Room, RoomAvailability } from './types'

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

export async function fetchBookings(page = 1): Promise<Paginated<Booking>> {
  const { data } = await http.get<Paginated<Booking>>('/api/bookings', { params: { page } })
  return data
}

export async function createBooking(input: CreateBookingInput): Promise<Booking> {
  const { data } = await http.post<{ data: Booking }>('/api/bookings', input)
  return data.data
}

export async function cancelBooking(bookingId: number): Promise<void> {
  await http.delete(`/api/bookings/${bookingId}`)
}
