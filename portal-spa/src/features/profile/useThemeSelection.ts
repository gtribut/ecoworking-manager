import type { LucideIcon } from 'lucide-react'
import { Monitor, Moon, Sun } from 'lucide-react'
import { useEffect, useState } from 'react'
import { toast } from 'sonner'
import { useAuth } from '@/features/auth/useAuth'
import { useUpdateTheme } from '@/features/profile/useProfile'
import { getApiErrorMessage } from '@/lib/errors'

/** `null` = « Système » : la classe `dark` suit `prefers-color-scheme`. */
export type Theme = 'light' | 'dark' | null

export const THEME_OPTIONS: { value: Theme; label: string; icon: LucideIcon }[] = [
  { value: 'light', label: 'Clair', icon: Sun },
  { value: 'dark', label: 'Sombre', icon: Moon },
  { value: null, label: 'Système', icon: Monitor },
]

/** Applique la classe `dark` sur `<html>` sans attendre la réponse serveur (PRD §3.1). */
export function applyThemeClass(theme: Theme): void {
  const root = document.documentElement
  if (theme === null) {
    root.classList.toggle('dark', window.matchMedia('(prefers-color-scheme: dark)').matches)
    return
  }
  root.classList.toggle('dark', theme === 'dark')
}

/**
 * Préférence de thème du membre, en optimiste : la classe `dark` est posée
 * immédiatement, la persistance suit (`PATCH /api/profile`). En cas d'échec
 * serveur, l'affichage ET l'état du menu reviennent à la valeur précédente,
 * avec un toast — sans quoi l'UI resterait en sombre alors que le serveur est
 * resté en clair (revue du lot G).
 *
 * Extrait de `ProfileMenu` en U2 : la même logique sert au menu profil et au
 * bouton de thème de la top bar.
 */
export function useThemeSelection(): { theme: Theme; selectTheme: (theme: Theme) => void } {
  const { user } = useAuth()
  const updateTheme = useUpdateTheme()
  const [optimisticTheme, setOptimisticTheme] = useState<Theme>(user?.theme ?? null)

  useEffect(() => {
    setOptimisticTheme(user?.theme ?? null)
  }, [user?.theme])

  function selectTheme(theme: Theme): void {
    const previous = optimisticTheme
    applyThemeClass(theme)
    setOptimisticTheme(theme)
    updateTheme.mutate(theme, {
      onError: (error) => {
        applyThemeClass(previous)
        setOptimisticTheme(previous)
        toast.error(getApiErrorMessage(error, 'Impossible d’enregistrer le thème.'))
      },
    })
  }

  return { theme: optimisticTheme, selectTheme }
}
