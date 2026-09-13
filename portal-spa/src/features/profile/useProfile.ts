import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { authQueryKey } from '@/features/auth/AuthContext'
import type { AuthUser } from '@/features/auth/types'
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

/**
 * Mutation dédiée au thème, utilisée par le menu profil du header (review) :
 * `useUpdateProfile` écrit dans le cache `profileQueryKey`, ce que
 * `ProfilePage` écoute pour `reset()` son formulaire — changer de thème
 * depuis le header effaçait alors une saisie en cours (bio, etc.). Cette
 * mutation ne touche que `authQueryKey` (header, classe `dark`) ; le profil
 * est seulement invalidé (refetch en arrière-plan, sans jamais écraser un
 * formulaire `isDirty` — cf. le garde-fou dans `ProfilePage`).
 */
export function useUpdateTheme() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (theme: 'light' | 'dark' | null) => updateProfile({ theme }),
    onSuccess: (payload: ProfilePayload) => {
      queryClient.setQueryData(authQueryKey, (current: AuthUser | null | undefined) =>
        current ? { ...current, theme: payload.user.theme } : current,
      )
      void queryClient.invalidateQueries({ queryKey: profileQueryKey })
    },
  })
}
