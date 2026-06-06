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
