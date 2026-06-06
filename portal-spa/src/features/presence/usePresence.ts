import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { createAbsence, deleteAbsence, fetchPresence } from './api'
import type { CreateAbsenceInput } from './types'

export const presenceQueryKey = (from: string, to: string) => ['presence', from, to] as const

export function usePresence(from: string, to: string) {
  return useQuery({
    queryKey: presenceQueryKey(from, to),
    queryFn: () => fetchPresence(from, to),
  })
}

export function useCreateAbsence() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (input: CreateAbsenceInput) => createAbsence(input),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['presence'] })
    },
  })
}

export function useDeleteAbsence() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (absenceId: number) => deleteAbsence(absenceId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['presence'] })
    },
  })
}
