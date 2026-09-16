import { createContext, type ReactNode, useContext } from 'react'
import { createPortal } from 'react-dom'
import { useIsMobile } from '@/hooks/use-mobile'
import { cn } from '@/lib/utils'

/**
 * Emplacement du titre de page dans la top bar du shell. `element` vaut `null`
 * le temps que le `<header>` soit monté ; le contexte entier vaut `null` hors
 * du shell (pages légales, tests unitaires d'une page isolée).
 */
export interface PageHeaderSlot {
  element: HTMLElement | null
}

export const PageHeaderSlotContext = createContext<PageHeaderSlot | null>(null)

export interface PageHeaderProps {
  /** Titre de la page — rendu en `<h1>`, un seul par écran (RGAA 9.1). */
  title: string
  /** Contexte secondaire : date du jour, sous-titre… */
  description?: ReactNode
  /** Actions propres à la page (bouton primaire, filtres globaux). */
  actions?: ReactNode
}

/**
 * Titre de la page courante (PRD §3.9, ré-actage C14) : déclaré par chaque
 * écran, rendu **dans la top bar de 60 px** en desktop (via un portail vers
 * l'emplacement posé par `Layout`) et **au-dessus du contenu** en mobile, où
 * la barre de 56 px est déjà occupée par le logo et les actions globales.
 *
 * Le `<h1>` reste unique et dans le `<main>` dans les deux cas : la top bar
 * est rendue à l'intérieur du `<main>` du shell. Hors shell, le composant se
 * rend sur place — les pages restent testables isolément.
 */
export function PageHeader({ title, description, actions }: PageHeaderProps) {
  const slot = useContext(PageHeaderSlotContext)
  const isMobile = useIsMobile()
  const inTopBar = slot !== null && !isMobile

  const content = (
    <div
      className={cn(
        'flex w-full min-w-0 flex-wrap items-center justify-between gap-x-4 gap-y-2',
        !inTopBar && 'mb-6',
      )}
    >
      <div className="min-w-0">
        <h1
          className={cn('font-semibold tracking-tight', inTopBar ? 'truncate text-lg' : 'text-2xl')}
        >
          {title}
        </h1>
        {description !== undefined && (
          <p className={cn('text-muted-foreground', inTopBar ? 'truncate text-xs' : 'text-sm')}>
            {description}
          </p>
        )}
      </div>
      {actions !== undefined && <div className="flex shrink-0 items-center gap-2">{actions}</div>}
    </div>
  )

  if (!inTopBar) return content

  // `slot.element` est null le temps du premier rendu du shell : on n'affiche
  // rien plutôt que le titre « en place », qui sauterait dans la top bar à la
  // frame suivante.
  return slot?.element === null || slot === null ? null : createPortal(content, slot.element)
}
