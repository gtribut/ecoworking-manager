import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { authQueryKey } from '@/features/auth/AuthContext'
import { fetchProfile, updateProfile } from './api'
import type { ProfilePayload, ProfileUpdate } from './types'

export const profileQueryKey = ['profile'] as const

export function useProfile() {
  return useQuery({ queryKey: profileQueryKey, queryFn: fetchProfile })
}

export function useUpdateProfile() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (update: ProfileUpdate) => updateProfile(update),
    onSuccess: (payload: ProfilePayload) => {
      queryClient.setQueryData(profileQueryKey, payload)
      // Le thème vit aussi sur /api/user (header, classe dark) → resynchroniser.
      queryClient.invalidateQueries({ queryKey: authQueryKey })
    },
  })
}
