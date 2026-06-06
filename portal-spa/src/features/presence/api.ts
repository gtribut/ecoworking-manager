import { http } from '@/lib/http'
import type { Absence, CreateAbsenceInput, PresencePayload } from './types'

export async function fetchPresence(from: string, to: string): Promise<PresencePayload> {
  const { data } = await http.get<PresencePayload>('/api/presence', { params: { from, to } })
  return data
}

export async function createAbsence(input: CreateAbsenceInput): Promise<Absence> {
  const { data } = await http.post<{ data: Absence }>('/api/absences', input)
  return data.data
}

export async function deleteAbsence(absenceId: number): Promise<void> {
  await http.delete(`/api/absences/${absenceId}`)
}
