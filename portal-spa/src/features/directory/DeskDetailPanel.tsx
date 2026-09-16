import { Link } from 'react-router'
import { Avatar } from '@/components/Avatar'
import { MarkdownContent } from '@/components/MarkdownContent'
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import { useIsMobile } from '@/hooks/use-mobile'
import { occupantDisplayName, statusLabel } from './plan-utils'
import type { PlanDesk } from './types'

interface DeskDetailPanelProps {
  /** `null` = fermé : le `Sheet` reste monté pour son animation de sortie. */
  desk: PlanDesk | null
  onClose: () => void
  /**
   * Bloc `[data-desk]` du SVG qui a ouvert le panneau : le `Sheet` n'a pas de
   * `SheetTrigger` (ouverture programmatique depuis la délégation clic/clavier
   * du plan), Radix n'a donc aucun déclencheur à qui rendre le focus tout
   * seul — on le lui fournit explicitement via `onCloseAutoFocus`.
   */
  returnFocusTo: HTMLElement | null
}

/**
 * Panneau de détail d'un bureau (PRD §3.7.4) : fiche coworker si opt-in,
 * mention anonyme sinon, message « bureau libre », et raccourci « Gérer mes
 * absences » sur SON propre bureau.
 *
 * `Sheet` shadcn (side droit desktop / bas mobile, cf. `useIsMobile`) plutôt
 * qu'un panneau inline (lot U4b) : piège de focus et fermeture Échap sont
 * gérés par Radix, le retour de focus est explicite (cf. `returnFocusTo`).
 */
export function DeskDetailPanel({ desk, onClose, returnFocusTo }: DeskDetailPanelProps) {
  const isMobile = useIsMobile()
  const occupant = desk?.occupant ?? null
  const name = desk ? occupantDisplayName(desk) : null

  return (
    <Sheet open={desk !== null} onOpenChange={(open) => !open && onClose()}>
      <SheetContent
        side={isMobile ? 'bottom' : 'right'}
        aria-describedby={undefined}
        className="overflow-y-auto"
        onCloseAutoFocus={(event) => {
          if (!returnFocusTo) return
          event.preventDefault()
          returnFocusTo.focus()
        }}
      >
        {desk && (
          <>
            <SheetHeader>
              <SheetTitle>
                {desk.name}
                {desk.is_own && ' (votre bureau)'}
              </SheetTitle>
            </SheetHeader>

            <div className="space-y-4 px-4 pb-4 text-sm">
              <p className="text-muted-foreground">Statut : {statusLabel(desk)}</p>

              {occupant?.visible && (
                <div className="flex gap-4">
                  <Avatar
                    firstName={occupant.first_name}
                    lastName={occupant.last_name}
                    photo={occupant.photo}
                    size="md"
                    // Résident absent ce jour-là : photo grisée (PRD §3.7.3).
                    muted={desk.status === 'absent'}
                  />
                  <div className="min-w-0 space-y-2">
                    <p className="text-base font-medium">
                      {occupant.first_name} {occupant.last_name}
                    </p>
                    {occupant.company && <p>{occupant.company}</p>}
                    {occupant.job_title && (
                      <p className="text-muted-foreground">{occupant.job_title}</p>
                    )}
                    {occupant.bio && (
                      <MarkdownContent markdown={occupant.bio} className="text-muted-foreground" />
                    )}
                    {occupant.interests && (
                      <p className="text-muted-foreground">
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
                            className="text-primary underline"
                          >
                            LinkedIn
                            <span className="sr-only">
                              {' '}
                              de {occupant.first_name} (nouvelle fenêtre)
                            </span>
                          </a>
                        </li>
                      )}
                      {occupant.website_url && (
                        <li>
                          <a
                            href={occupant.website_url}
                            target="_blank"
                            rel="noreferrer"
                            className="text-primary underline"
                          >
                            Site web
                            <span className="sr-only">
                              {' '}
                              de {occupant.first_name} (nouvelle fenêtre)
                            </span>
                          </a>
                        </li>
                      )}
                    </ul>
                  </div>
                </div>
              )}

              {occupant && !occupant.visible && <p className="text-muted-foreground">{name}</p>}

              {!occupant && desk.status === 'free' && (
                <p className="text-muted-foreground">
                  Bureau libre — pour réserver ce type de bureau à la demi-journée, contactez-nous.
                </p>
              )}

              {desk.status === 'out_of_service' && (
                <p className="text-muted-foreground">Bureau temporairement hors service.</p>
              )}

              {desk.is_own && (
                <p>
                  <Link to="/presence" className="text-primary underline">
                    Gérer mes absences
                  </Link>
                </p>
              )}
            </div>
          </>
        )}
      </SheetContent>
    </Sheet>
  )
}
