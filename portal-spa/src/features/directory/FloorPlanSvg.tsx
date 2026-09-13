import { useEffect, useRef } from 'react'
import planSvgRaw from './assets/etages.svg?raw'
import { deskLabel } from './plan-utils'
import type { PlanDesk } from './types'

interface FloorPlanSvgProps {
  desks: PlanDesk[]
  floor: number
  selectedId: number | null
  onSelect: (desk: PlanDesk) => void
}

const SVG_NS = 'http://www.w3.org/2000/svg'

/**
 * Photo du résident présent, posée dans le bloc bureau (PRD §3.7.3) et grisée
 * s'il a déclaré une absence. Les coordonnées sont lues sur le `<rect>` du
 * bloc : aucune position en dur, le plan reste redessinable.
 *
 * `aria-hidden` : l'information (qui occupe le bureau, présent ou absent) est
 * déjà portée par l'`aria-label` du bloc et par l'alternative texte.
 */
function decoratePhoto(element: SVGGElement, desk: PlanDesk): void {
  element.querySelector('[data-desk-photo]')?.remove()

  const photo = desk.occupant?.visible ? desk.occupant.photo : null
  const rect = element.querySelector('rect')
  if (!photo || !rect) return

  const x = Number(rect.getAttribute('x'))
  const y = Number(rect.getAttribute('y'))
  const width = Number(rect.getAttribute('width'))
  const height = Number(rect.getAttribute('height'))
  if (![x, y, width, height].every(Number.isFinite)) return

  const diameter = Math.min(width, height) * 0.6
  const image = document.createElementNS(SVG_NS, 'image')
  image.setAttribute('href', photo.sm)
  image.setAttribute('x', String(x + width - diameter - 3))
  image.setAttribute('y', String(y + 3))
  image.setAttribute('width', String(diameter))
  image.setAttribute('height', String(diameter))
  image.setAttribute('preserveAspectRatio', 'xMidYMid slice')
  image.setAttribute('clip-path', 'circle(50%)')
  image.setAttribute('aria-hidden', 'true')
  image.setAttribute('data-desk-photo', 'true')
  if (desk.status === 'absent') {
    image.setAttribute('opacity', '0.45')
    image.style.filter = 'grayscale(1)'
  }

  element.appendChild(image)
}

/**
 * Plan SVG interactif des étages (PRD §3.7.2).
 *
 * Le SVG est un asset statique versionné (copie de `docs/plan/etages.svg`,
 * source de vérité — resynchroniser ce fichier si Guillaume le redessine).
 * Le code cible EXCLUSIVEMENT les ids `#desk-N` / `data-desk` et les zones
 * `#etage-N` : jamais de coordonnées en dur, le plan peut être redessiné tant
 * que les ids sont conservés. La correspondance avec la DB passe par
 * `resources.svg_desk_id`.
 *
 * A11y : chaque bureau devient un bloc focusable (`role="button"`, Entrée /
 * Espace) avec un `aria-label` identique au texte de l'alternative accessible
 * ({@link deskLabel}). L'état est aussi porté par `data-status` (couleurs CSS,
 * jamais l'information par la couleur seule).
 */
export function FloorPlanSvg({ desks, floor, selectedId, onSelect }: FloorPlanSvgProps) {
  const containerRef = useRef<HTMLDivElement>(null)
  const desksRef = useRef(desks)
  desksRef.current = desks
  const onSelectRef = useRef(onSelect)
  onSelectRef.current = onSelect

  // Décoration du SVG : étage visible, statuts, focus et labels par bureau.
  useEffect(() => {
    const svg = containerRef.current?.querySelector('svg')
    if (!svg) return

    // Le SVG placeholder est `role="img"` : devenu interactif, il doit être un
    // groupe (les descendants d'un rôle img sont ignorés des lecteurs d'écran).
    svg.setAttribute('role', 'group')
    svg.setAttribute('aria-label', `Plan de l'étage ${floor}`)
    svg.removeAttribute('aria-labelledby')

    for (const floorNumber of [1, 2]) {
      const group = svg.querySelector<SVGGElement>(`#etage-${floorNumber}`)
      if (group) {
        group.style.display = floorNumber === floor ? '' : 'none'
      }
    }

    // Cadrage sur l'étage visible, mesuré à l'exécution (pas de coordonnées
    // en dur : le plan sera redessiné en conservant uniquement les ids).
    const visibleFloor = svg.querySelector<SVGGElement>(`#etage-${floor}`)
    if (visibleFloor) {
      try {
        const box = visibleFloor.getBBox()
        svg.setAttribute(
          'viewBox',
          `${box.x - 8} ${box.y - 8} ${box.width + 16} ${box.height + 16}`,
        )
      } catch {
        // getBBox indisponible (jsdom) : on garde le viewBox d'origine.
      }
    }

    for (const desk of desks) {
      if (!desk.svg_desk_id) continue
      const element = svg.querySelector<SVGGElement>(`#${CSS.escape(desk.svg_desk_id)}`)
      if (!element) continue

      decoratePhoto(element, desk)

      element.dataset.status = desk.status
      element.dataset.assignment = desk.assignment ?? 'none'
      element.dataset.own = desk.is_own ? 'true' : 'false'
      element.dataset.selected = desk.resource_id === selectedId ? 'true' : 'false'
      element.setAttribute('role', 'button')
      element.setAttribute('tabindex', desk.floor === floor ? '0' : '-1')
      element.setAttribute('aria-label', deskLabel(desk))
      element.setAttribute('aria-pressed', desk.resource_id === selectedId ? 'true' : 'false')
    }
  }, [desks, floor, selectedId])

  // Délégation clic + clavier (Entrée / Espace) sur les blocs `data-desk`.
  useEffect(() => {
    const container = containerRef.current
    if (!container) return

    const select = (target: EventTarget | null): boolean => {
      const group = target instanceof Element ? target.closest('[data-desk]') : null
      const number = group?.getAttribute('data-desk')
      if (!number) return false

      const desk = desksRef.current.find((entry) => entry.svg_desk_id === `desk-${number}`)
      if (desk) onSelectRef.current(desk)
      return true
    }

    const onClick = (event: MouseEvent): void => {
      select(event.target)
    }
    const onKeyDown = (event: KeyboardEvent): void => {
      if (event.key !== 'Enter' && event.key !== ' ') return
      if (select(event.target)) event.preventDefault()
    }

    container.addEventListener('click', onClick)
    container.addEventListener('keydown', onKeyDown)
    return () => {
      container.removeEventListener('click', onClick)
      container.removeEventListener('keydown', onKeyDown)
    }
  }, [])

  return (
    <div
      ref={containerRef}
      className="plan-svg rounded-lg border border-neutral-200 bg-white p-2 dark:border-neutral-800"
      // biome-ignore lint/security/noDangerouslySetInnerHtml: SVG statique versionné dans le repo (aucun contenu utilisateur) — injection brute requise pour cibler #desk-N/data-desk.
      dangerouslySetInnerHTML={{ __html: planSvgRaw }}
    />
  )
}
