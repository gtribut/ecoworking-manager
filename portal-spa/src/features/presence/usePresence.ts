import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { createAbsence, deleteAbsence, fetchPresence, updateAbsence } from './api'
import type { CreateAbsenceInput, UpdateAbsenceInput } from './types'

export const presenceQueryKey = (from: string, to: string, all: boolean) =>
  ['presence', from, to, all] as const

export function usePresence(from: string, to: string, all = false) {
  return useQuery({
    queryKey: presenceQueryKey(from, to, all),
    queryFn: () => fetchPresence(from, to, all),
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

export function useUpdateAbsence() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: ({ id, input }: { id: number; input: UpdateAbsenceInput }) =>
      updateAbsence(id, input),
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
