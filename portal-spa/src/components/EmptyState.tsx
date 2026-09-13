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
 */
export function EmptyState({ icon: Icon, title, description, cta, className }: EmptyStateProps) {
  return (
    <div
      role="status"
      className={`rounded-lg border border-dashed border-neutral-300 bg-white px-4 py-8 text-center dark:border-neutral-700 dark:bg-neutral-900 ${className ?? ''}`}
    >
      {Icon && (
        <Icon
          className="mx-auto size-8 text-neutral-400 dark:text-neutral-500"
          aria-hidden="true"
        />
      )}
      <p className="mt-2 font-medium text-neutral-700 dark:text-neutral-200">{title}</p>
      {description && (
        <p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">{description}</p>
      )}
      {cta && (
        <p className="mt-3">
          <Link
            to={cta.to}
            className="text-sm font-medium text-brand-700 underline underline-offset-2 dark:text-brand-300"
          >
            {cta.label} →
          </Link>
        </p>
      )}
    </div>
  )
}
