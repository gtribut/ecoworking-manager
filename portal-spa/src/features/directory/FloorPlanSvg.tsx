import { memo, useEffect, useMemo, useRef, useState } from 'react'
import { Card } from '@/components/ui/card'
import { cn } from '@/lib/utils'
import planSvgRaw from './assets/etages.svg?raw'
import { deskLabel, deskTooltip } from './plan-utils'
import type { PlanDesk } from './types'

interface FloorPlanSvgProps {
  desks: PlanDesk[]
  floor: number
  selectedId: number | null
  onSelect: (desk: PlanDesk) => void
}

interface HoveredDesk {
  resourceId: number
  x: number
  y: number
  /** Tooltip au-dessus du bloc, sauf pour la 1re rangée (carte `overflow-hidden`). */
  above: boolean
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
 * Hôte du SVG brut, mémoïsé SANS props : React réinjecte l'`innerHTML` à chaque
 * rendu du parent (l'objet `dangerouslySetInnerHTML` est neuf à chaque fois),
 * ce qui détacherait les blocs décorés — un clic parti juste après un survol
 * atterrirait alors sur un noeud hors du DOM (bug réel). Ici le noeud est monté
 * une fois pour toutes, et les effets ci-dessous restent seuls maîtres du DOM.
 */
const PlanSvgHost = memo(function PlanSvgHost() {
  return (
    <div
      className="plan-svg"
      // biome-ignore lint/security/noDangerouslySetInnerHtml: SVG statique versionné dans le repo (aucun contenu utilisateur) — injection brute requise pour cibler #desk-N/data-desk.
      dangerouslySetInnerHTML={{ __html: planSvgRaw }}
    />
  )
})

/**
 * Plan SVG interactif d'UN étage (PRD §3.7.2). Les deux étages sont affichés
 * côte à côte (une carte chacun, empilées en mobile), donc chaque instance ne
 * garde que son propre groupe `#etage-N` : le groupe de l'autre étage — et les
 * `<title>`/`<desc>` du document — sont retirés du DOM pour ne pas dupliquer
 * d'id entre les deux cartes.
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
 * jamais l'information par la couleur seule). Le tooltip au survol est
 * décoratif (`aria-hidden`) : il ne dit rien de plus que l'`aria-label`.
 */
export function FloorPlanSvg({ desks, floor, selectedId, onSelect }: FloorPlanSvgProps) {
  const containerRef = useRef<HTMLDivElement>(null)
  const desksRef = useRef(desks)
  desksRef.current = desks
  const onSelectRef = useRef(onSelect)
  onSelectRef.current = onSelect
  const [hovered, setHovered] = useState<HoveredDesk | null>(null)

  const floorDesks = useMemo(() => desks.filter((desk) => desk.floor === floor), [desks, floor])
  const hoveredDesk = floorDesks.find((desk) => desk.resource_id === hovered?.resourceId) ?? null

  // Décoration du SVG : étage conservé, statuts, focus et labels par bureau.
  useEffect(() => {
    const svg = containerRef.current?.querySelector('svg')
    if (!svg) return

    // Le SVG placeholder est `role="img"` : devenu interactif, il doit être un
    // groupe (les descendants d'un rôle img sont ignorés des lecteurs d'écran).
    svg.setAttribute('role', 'group')
    svg.setAttribute('aria-label', `Plan de l'étage ${floor}`)
    svg.removeAttribute('aria-labelledby')
    // `<title>`/`<desc>` porteurs d'ids : inutiles (le nom vient de l'aria-label)
    // et dupliqués entre les deux cartes s'ils restaient.
    svg.querySelector(':scope > title')?.remove()
    svg.querySelector(':scope > desc')?.remove()

    for (const other of [1, 2].filter((floorNumber) => floorNumber !== floor)) {
      svg.querySelector(`#etage-${other}`)?.remove()
    }

    // Cadrage sur l'étage affiché, mesuré à l'exécution (pas de coordonnées
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

    for (const desk of floorDesks) {
      if (!desk.svg_desk_id) continue
      const element = svg.querySelector<SVGGElement>(`#${CSS.escape(desk.svg_desk_id)}`)
      if (!element) continue

      decoratePhoto(element, desk)

      element.dataset.status = desk.status
      element.dataset.assignment = desk.assignment ?? 'none'
      element.dataset.own = desk.is_own ? 'true' : 'false'
      element.dataset.selected = desk.resource_id === selectedId ? 'true' : 'false'
      element.setAttribute('role', 'button')
      element.setAttribute('tabindex', '0')
      element.setAttribute('aria-label', deskLabel(desk))
      element.setAttribute('aria-pressed', desk.resource_id === selectedId ? 'true' : 'false')
    }
  }, [floorDesks, floor, selectedId])

  // Délégation clic + clavier (Entrée / Espace) et survol sur les blocs
  // `data-desk`, montés par `dangerouslySetInnerHTML` (hors arbre React).
  useEffect(() => {
    const container = containerRef.current
    if (!container) return

    const deskFrom = (target: EventTarget | null): PlanDesk | null => {
      const group = target instanceof Element ? target.closest('[data-desk]') : null
      const number = group?.getAttribute('data-desk')
      if (!number) return null

      return desksRef.current.find((entry) => entry.svg_desk_id === `desk-${number}`) ?? null
    }

    const select = (target: EventTarget | null): boolean => {
      const group = target instanceof Element ? target.closest('[data-desk]') : null
      if (!group) return false

      const desk = deskFrom(target)
      if (desk) onSelectRef.current(desk)
      return true
    }

    // Position du tooltip : au-dessus du bloc, mesurée sur le DOM (le plan est
    // redimensionné en pourcentage, aucune coordonnée SVG exploitable ici).
    const hover = (target: EventTarget | null): void => {
      const group = target instanceof Element ? target.closest('[data-desk]') : null
      const desk = deskFrom(target)
      if (!group || !desk) {
        setHovered(null)
        return
      }

      const box = group.getBoundingClientRect()
      const root = container.getBoundingClientRect()
      // La carte est `overflow-hidden` : au-dessus de la 1re rangée le tooltip
      // serait rogné, on le bascule sous le bloc. Idem en x, borné aux marges.
      const above = box.top - root.top > 44
      const x = box.left - root.left + box.width / 2

      setHovered({
        resourceId: desk.resource_id,
        x: Math.min(Math.max(x, 72), Math.max(root.width - 72, 72)),
        y: above ? box.top - root.top - 6 : box.bottom - root.top + 6,
        above,
      })
    }

    const onClick = (event: MouseEvent): void => {
      select(event.target)
    }
    const onKeyDown = (event: KeyboardEvent): void => {
      if (event.key === 'Escape') {
        setHovered(null)
        return
      }
      if (event.key !== 'Enter' && event.key !== ' ') return
      if (select(event.target)) event.preventDefault()
    }
    const onMouseOver = (event: MouseEvent): void => hover(event.target)
    const onFocusIn = (event: FocusEvent): void => hover(event.target)
    const onLeave = (): void => setHovered(null)

    container.addEventListener('click', onClick)
    container.addEventListener('keydown', onKeyDown)
    container.addEventListener('mouseover', onMouseOver)
    container.addEventListener('mouseleave', onLeave)
    container.addEventListener('focusin', onFocusIn)
    container.addEventListener('focusout', onLeave)
    return () => {
      container.removeEventListener('click', onClick)
      container.removeEventListener('keydown', onKeyDown)
      container.removeEventListener('mouseover', onMouseOver)
      container.removeEventListener('mouseleave', onLeave)
      container.removeEventListener('focusin', onFocusIn)
      container.removeEventListener('focusout', onLeave)
    }
  }, [])

  const tooltip = hoveredDesk ? deskTooltip(hoveredDesk) : null

  return (
    <Card className="p-2">
      <div ref={containerRef} className="relative">
        <PlanSvgHost />
        {tooltip && hovered && (
          <div
            aria-hidden="true"
            data-testid="desk-tooltip"
            className={cn(
              'pointer-events-none absolute z-10 max-w-48 -translate-x-1/2 rounded-md bg-neutral-900 px-2 py-1 text-xs leading-snug text-white shadow-md dark:bg-neutral-100 dark:text-neutral-900',
              hovered.above && '-translate-y-full',
            )}
            style={{ left: hovered.x, top: hovered.y }}
          >
            <span className="block font-medium">{tooltip.title}</span>
            {tooltip.subtitle && (
              <span className="block text-neutral-300 dark:text-neutral-600">
                {tooltip.subtitle}
              </span>
            )}
          </div>
        )}
      </div>
    </Card>
  )
}
