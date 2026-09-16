import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { authQueryKey } from '@/features/auth/AuthContext'
import type { AuthUser } from '@/features/auth/types'
import { getApiErrorMessage } from '@/lib/errors'
import { applyThemeClass } from '@/lib/theme'
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
 *
 * L'optimisme vit dans `onMutate`, sur le cache partagé — pas dans un
 * `useState` local de `useThemeSelection` (review U5) : trois composants
 * l'appellent en parallèle (bloc profil sidebar, menu compact mobile, bouton
 * thème de la top bar) et un état local par instance les désynchronisait
 * tant que la mutation n'avait pas résolu. En écrivant directement dans
 * `authQueryKey`, toutes les instances de `useAuth()` — donc toutes les
 * instances de `useThemeSelection` — se resynchronisent au même rendu.
 *
 * Rollback ET toast d'erreur vivent tous les deux ici (et non dans un
 * `onError` passé par l'appelant à `mutate()`) : TanStack Query ne garantit
 * pas d'ordre entre les callbacks de `useMutation()` et ceux fournis à
 * `mutate(vars, options)` selon les versions — les co-localiser élimine toute
 * dépendance à cet ordre.
 *
 * Le rollback appelle aussi `applyThemeClass` directement (en plus d'écrire
 * dans le cache) : si `onMutate` puis `onError` s'exécutent dans le même
 * batch React (résolution MSW/réseau très rapide en test), la query ne voit
 * jamais l'état intermédiaire optimiste et son état final égale l'état
 * initial — l'effet d'`AuthProvider` sur `[user?.theme]` ne se redéclenche
 * alors pas puisque la valeur observée n'a « pas changé », alors que la
 * classe `dark` posée en impératif par `selectTheme`, elle, l'a bien été. Un
 * appel impératif ici garantit la classe correcte quel que soit ce batching
 * (bug reproduit et corrigé en U5, cf. `ProfileMenu.test.tsx`).
 */
export function useUpdateTheme() {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (theme: 'light' | 'dark' | null) => updateProfile({ theme }),
    onMutate: async (theme) => {
      await queryClient.cancelQueries({ queryKey: authQueryKey })
      const previousUser = queryClient.getQueryData<AuthUser | null>(authQueryKey)
      queryClient.setQueryData(authQueryKey, (current: AuthUser | null | undefined) =>
        current ? { ...current, theme } : current,
      )
      return { previousUser }
    },
    onError: (error, _theme, context) => {
      if (context !== undefined) {
        queryClient.setQueryData(authQueryKey, context.previousUser)
        applyThemeClass(context.previousUser?.theme ?? null)
      }
      toast.error(getApiErrorMessage(error, 'Impossible d’enregistrer le thème.'))
    },
    onSuccess: (payload: ProfilePayload) => {
      queryClient.setQueryData(authQueryKey, (current: AuthUser | null | undefined) =>
        current ? { ...current, theme: payload.user.theme } : current,
      )
      void queryClient.invalidateQueries({ queryKey: profileQueryKey })
    },
  })
}
