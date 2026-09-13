import type { Paginated } from '@/lib/api-types'
import { http } from '@/lib/http'
import type {
  CreateDeskOccupationInput,
  DeskAvailability,
  DeskOccupation,
  DeskPeriod,
  TicketsPayload,
} from './types'

export async function fetchTickets(): Promise<TicketsPayload> {
  const { data } = await http.get<TicketsPayload>('/api/tickets')
  return data
}

/** Bureaux nomades réservés du membre : à venir (défaut) ou historique (PRD §3.5.9). */
export async function fetchDeskOccupations(
  scope: 'upcoming' | 'past',
  page = 1,
): Promise<Paginated<DeskOccupation>> {
  const { data } = await http.get<Paginated<DeskOccupation>>('/api/desk-occupations', {
    params: { page, [scope]: 1 },
  })
  return data
}

export async function fetchDeskAvailability(
  date: string,
  period: DeskPeriod,
): Promise<DeskAvailability> {
  const { data } = await http.get<DeskAvailability>('/api/desks/availability', {
    params: { date, period },
  })
  return data
}

export async function createDeskOccupation(
  input: CreateDeskOccupationInput,
): Promise<DeskOccupation> {
  const { data } = await http.post<{ data: DeskOccupation }>('/api/desk-occupations', input)
  return data.data
}

export async function cancelDeskOccupation(occupationId: number): Promise<void> {
  await http.delete(`/api/desk-occupations/${occupationId}`)
}
