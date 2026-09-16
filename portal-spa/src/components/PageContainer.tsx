import type { ReactNode } from 'react'
import { cn } from '@/lib/utils'

/** Largeur maximale du contenu, choisie **par page** (PRD §3.9, ré-actage C14). */
export type PageWidth = 'full' | 'wide' | 'narrow'

const WIDTHS: Record<PageWidth, string> = {
  /** Écrans denses : agenda, annuaire, plan des étages, factures. */
  full: 'max-w-none',
  /** Défaut : listes et tableaux de bord. */
  wide: 'max-w-6xl',
  /** Lecture et formulaires : profil, détail d'actualité. */
  narrow: 'max-w-3xl',
}

export interface PageContainerProps {
  width?: PageWidth
  className?: string
  children: ReactNode
}

/**
 * Enveloppe de contenu d'une page du portail : gouttières 16 px en mobile,
 * 32 px à partir de `md`, et une réserve basse suffisante pour ne pas passer
 * sous la bottom nav mobile (56 px + safe-area).
 */
export function PageContainer({ width = 'wide', className, children }: PageContainerProps) {
  return (
    <div
      data-width={width}
      className={cn(
        'mx-auto w-full px-4 pt-5 pb-28 md:px-8 md:pt-7 md:pb-8',
        WIDTHS[width],
        className,
      )}
    >
      {children}
    </div>
  )
}
