import { http } from '@/lib/http'
import type { Absence, CreateAbsenceInput, PresencePayload, UpdateAbsenceInput } from './types'

export async function fetchPresence(
  from: string,
  to: string,
  all = false,
): Promise<PresencePayload> {
  const { data } = await http.get<PresencePayload>('/api/presence', {
    params: all ? { from, to, all: 1 } : { from, to },
  })
  return data
}

export async function createAbsence(input: CreateAbsenceInput): Promise<Absence> {
  const { data } = await http.post<{ data: Absence }>('/api/absences', input)
  return data.data
}

export async function updateAbsence(id: number, input: UpdateAbsenceInput): Promise<Absence> {
  const { data } = await http.patch<{ data: Absence }>(`/api/absences/${id}`, input)
  return data.data
}

export async function deleteAbsence(absenceId: number): Promise<void> {
  await http.delete(`/api/absences/${absenceId}`)
}
