import { useEffect } from 'react'

/**
 * Titre de document par page (RGAA 8.5) : chaque écran annonce son contexte
 * dans l'onglet et aux lecteurs d'écran au changement de route.
 */
export function usePageTitle(title: string): void {
  useEffect(() => {
    document.title = title
  }, [title])
}
