import type { LucideIcon } from 'lucide-react'
import type { ReactNode } from 'react'
import { Link } from 'react-router'

interface EmptyStateCta {
  label: string
  to: string
}

interface EmptyStateProps {
  icon?: LucideIcon
  title: string
  description?: ReactNode
  cta?: EmptyStateCta
  className?: string
}

/**
 * État vide « rassurant + CTA » (PRD §3.8.3) : remplace les
 * `<Alert variant="info">« Aucun·e … »` sèches par un bloc avec icône
 * décorative, message et — quand il y a une action évidente — un lien vers
 * elle (« Réservez votre première salle → »).
 *
 * Contour pointillé (au lieu du `Card` plein utilisé pour le contenu réel) :
 * signale visuellement un emplacement vide, cohérent sur les deux thèmes via
 * les jetons `border` / `card` / `muted-foreground` plutôt que des teintes
 * neutres codées en dur (lot U4b).
 */
export function EmptyState({ icon: Icon, title, description, cta, className }: EmptyStateProps) {
  return (
    <div
      role="status"
      className={`rounded-xl border border-dashed border-border bg-card px-4 py-8 text-center ${className ?? ''}`}
    >
      {Icon && <Icon className="mx-auto size-8 text-muted-foreground" aria-hidden="true" />}
      <p className="mt-2 font-medium text-foreground">{title}</p>
      {description && <p className="mt-1 text-sm text-muted-foreground">{description}</p>}
      {cta && (
        <p className="mt-3">
          <Link to={cta.to} className="text-sm font-medium text-link underline underline-offset-2">
            {cta.label} →
          </Link>
        </p>
      )}
    </div>
  )
}
