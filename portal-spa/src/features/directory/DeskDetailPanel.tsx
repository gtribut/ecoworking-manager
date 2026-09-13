import { X } from 'lucide-react'
import { useEffect, useRef } from 'react'
import { Link } from 'react-router'
import { MarkdownContent } from '@/components/MarkdownContent'
import { occupantDisplayName, statusLabel } from './plan-utils'
import type { PlanDesk } from './types'

interface DeskDetailPanelProps {
  desk: PlanDesk
  onClose: () => void
}

/**
 * Panneau de détail d'un bureau (PRD §3.7.4) : fiche coworker si opt-in,
 * mention anonyme sinon, message « bureau libre », et raccourci « Gérer mes
 * absences » sur SON propre bureau. Focus déplacé sur le titre à l'ouverture,
 * fermeture au clavier (Échap ou bouton).
 */
export function DeskDetailPanel({ desk, onClose }: DeskDetailPanelProps) {
  const headingRef = useRef<HTMLHeadingElement>(null)

  useEffect(() => {
    headingRef.current?.focus()
  }, [])

  const occupant = desk.occupant
  const name = occupantDisplayName(desk)

  return (
    <section
      aria-labelledby="desk-detail-title"
      className="rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900"
      onKeyDown={(event) => {
        if (event.key === 'Escape') onClose()
      }}
    >
      <div className="flex items-start justify-between gap-2">
        <h2
          id="desk-detail-title"
          ref={headingRef}
          tabIndex={-1}
          className="text-lg font-medium focus:outline-none"
        >
          {desk.name}
          {desk.is_own && ' (votre bureau)'}
        </h2>
        <button
          type="button"
          aria-label="Fermer le détail du bureau"
          className="rounded-md p-1 text-neutral-600 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800"
          onClick={onClose}
        >
          <X className="size-5" aria-hidden="true" />
        </button>
      </div>

      <p className="mt-1 text-sm text-neutral-600 dark:text-neutral-300">
        Statut : {statusLabel(desk)}
      </p>

      {occupant?.visible && (
        <div className="mt-4 space-y-2 text-sm">
          <p className="text-base font-medium">
            {occupant.first_name} {occupant.last_name}
          </p>
          {occupant.company && <p>{occupant.company}</p>}
          {occupant.job_title && (
            <p className="text-neutral-600 dark:text-neutral-300">{occupant.job_title}</p>
          )}
          {occupant.bio && (
            <MarkdownContent
              markdown={occupant.bio}
              className="text-neutral-600 dark:text-neutral-300"
            />
          )}
          {occupant.interests && (
            <p className="text-neutral-600 dark:text-neutral-300">
              Centres d’intérêt : {occupant.interests}
            </p>
          )}
          <ul className="flex flex-wrap gap-3">
            {occupant.linkedin_url && (
              <li>
                <a
                  href={occupant.linkedin_url}
                  target="_blank"
                  rel="noreferrer"
                  className="text-brand-700 dark:text-brand-300 underline"
                >
                  LinkedIn
                  <span className="sr-only"> de {occupant.first_name} (nouvelle fenêtre)</span>
                </a>
              </li>
            )}
            {occupant.website_url && (
              <li>
                <a
                  href={occupant.website_url}
                  target="_blank"
                  rel="noreferrer"
                  className="text-brand-700 dark:text-brand-300 underline"
                >
                  Site web
                  <span className="sr-only"> de {occupant.first_name} (nouvelle fenêtre)</span>
                </a>
              </li>
            )}
          </ul>
        </div>
      )}

      {occupant && !occupant.visible && (
        <p className="mt-4 text-sm text-neutral-600 dark:text-neutral-300">{name}</p>
      )}

      {!occupant && desk.status === 'free' && (
        <p className="mt-4 text-sm text-neutral-600 dark:text-neutral-300">
          Bureau libre — pour réserver ce type de bureau à la demi-journée, contactez-nous.
        </p>
      )}

      {desk.status === 'out_of_service' && (
        <p className="mt-4 text-sm text-neutral-600 dark:text-neutral-300">
          Bureau temporairement hors service.
        </p>
      )}

      {desk.is_own && (
        <p className="mt-4">
          <Link to="/presence" className="text-sm text-brand-700 dark:text-brand-300 underline">
            Gérer mes absences
          </Link>
        </p>
      )}
    </section>
  )
}
