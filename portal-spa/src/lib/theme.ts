/**
 * Extrait de `features/profile/useThemeSelection.ts` (review U5) : ce module
 * neutre évite un import circulaire entre `useThemeSelection.ts` (qui importe
 * `useUpdateTheme` de `useProfile.ts`) et `useProfile.ts` (qui a besoin
 * d'`applyThemeClass` pour son rollback imperative, cf. plus bas).
 */

/** `null` = « Système » : la classe `dark` suit `prefers-color-scheme`. */
export type Theme = 'light' | 'dark' | null

/** Applique la classe `dark` sur `<html>` sans attendre la réponse serveur (PRD §3.1). */
export function applyThemeClass(theme: Theme): void {
  const root = document.documentElement
  if (theme === null) {
    root.classList.toggle('dark', window.matchMedia('(prefers-color-scheme: dark)').matches)
    return
  }
  root.classList.toggle('dark', theme === 'dark')
}
