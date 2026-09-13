import { useSyncExternalStore } from 'react'

function subscribe(callback: () => void): () => void {
  const observer = new MutationObserver(callback)
  observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] })
  return () => observer.disconnect()
}

function getSnapshot(): boolean {
  return document.documentElement.classList.contains('dark')
}

/**
 * État clair/sombre effectif de la page (classe `dark` sur `<html>`, gérée par
 * `AuthContext` — thème choisi ou `prefers-color-scheme` en automatique).
 * Sert à resynchroniser des bibliothèques tierces qui ont leur propre notion
 * de thème (Sonner : sans ça, ses couleurs par défaut restent figées sur
 * « light » et le texte de description perd tout contraste sur un fond de
 * toast assombri par nos classes Tailwind — cf. Layout.tsx).
 */
export function useIsDarkMode(): boolean {
  return useSyncExternalStore(subscribe, getSnapshot, () => false)
}
