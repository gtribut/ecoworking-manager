import { WifiOff } from 'lucide-react'
import { useSyncExternalStore } from 'react'

function subscribe(callback: () => void): () => void {
  window.addEventListener('online', callback)
  window.addEventListener('offline', callback)
  return () => {
    window.removeEventListener('online', callback)
    window.removeEventListener('offline', callback)
  }
}

function getSnapshot(): boolean {
  return navigator.onLine
}

/**
 * Bandeau hors-ligne persistant (PRD §3.8.2) : `navigator.onLine` + les
 * événements `online`/`offline`. `useSyncExternalStore` évite tout état React
 * dérivé qui désynchroniserait du vrai statut réseau du navigateur.
 */
export function OfflineBanner() {
  const online = useSyncExternalStore(subscribe, getSnapshot, () => true)

  if (online) return null

  return (
    <div
      role="status"
      className="flex items-center justify-center gap-2 bg-amber-100 px-4 py-2 text-center text-sm font-medium text-amber-900 dark:bg-amber-950 dark:text-amber-200"
    >
      <WifiOff className="size-4 shrink-0" aria-hidden="true" />
      <span>Vous êtes hors-ligne, certaines données peuvent ne pas être à jour.</span>
    </div>
  )
}
