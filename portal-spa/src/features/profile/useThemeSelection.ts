import type { LucideIcon } from 'lucide-react'
import { Monitor, Moon, Sun } from 'lucide-react'
import { useAuth } from '@/features/auth/useAuth'
import { useUpdateTheme } from '@/features/profile/useProfile'
import { applyThemeClass, type Theme } from '@/lib/theme'

export type { Theme }
export { applyThemeClass }

export const THEME_OPTIONS: { value: Theme; label: string; icon: LucideIcon }[] = [
  { value: 'light', label: 'Clair', icon: Sun },
  { value: 'dark', label: 'Sombre', icon: Moon },
  { value: null, label: 'Système', icon: Monitor },
]

/**
 * Préférence de thème du membre — **dérivée** de `user.theme` (query
 * `authQueryKey`), sans état local (review U5). Trois composants appellent ce
 * hook en parallèle (bloc profil de la sidebar, menu compact mobile,
 * `ThemeToggle` de la top bar) : un `useState` par instance les
 * désynchronisait le temps que `PATCH /api/profile` réponde (l'une affichait
 * déjà « Sombre » quand les deux autres montraient encore l'ancien choix).
 * L'optimisme vit désormais dans `useUpdateTheme` (`onMutate` sur le cache
 * partagé, cf. `useProfile.ts`) : toutes les instances lisent la même query
 * et se resynchronisent donc au même rendu, sans attendre la réponse serveur.
 *
 * `applyThemeClass` reste appelé ici en plus : il pose la classe `dark` de
 * façon synchrone au clic, sans attendre le prochain rendu déclenché par la
 * mise à jour du cache (`AuthProvider` la réapplique aussi via son propre
 * effet sur `user.theme`, en filet de sécurité). Le rollback et le toast
 * d'erreur vivent dans `useUpdateTheme` (`useProfile.ts`), pas ici.
 */
export function useThemeSelection(): { theme: Theme; selectTheme: (theme: Theme) => void } {
  const { user } = useAuth()
  const updateTheme = useUpdateTheme()
  const theme = user?.theme ?? null

  function selectTheme(next: Theme): void {
    applyThemeClass(next)
    updateTheme.mutate(next)
  }

  return { theme, selectTheme }
}
