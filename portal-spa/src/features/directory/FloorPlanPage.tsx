import { ChevronLeft, ChevronRight } from 'lucide-react'
import { useRef, useState } from 'react'
import { PageContainer } from '@/components/PageContainer'
import { PageHeader } from '@/components/PageHeader'
import { QueryError } from '@/components/QueryError'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Skeleton } from '@/components/ui/skeleton'
import { usePageTitle } from '@/lib/usePageTitle'
import { cn } from '@/lib/utils'
import { DeskDetailPanel } from './DeskDetailPanel'
import { DirectoryTabs } from './DirectoryTabs'
import { FloorPlanSvg } from './FloorPlanSvg'
import { FloorPlanTextList } from './FloorPlanTextList'
import { useFloorPlan } from './useDirectory'

/** Date locale au format YYYY-MM-DD (pas d'UTC : le plan est « aujourd'hui » local). */
export function toIsoDate(date: Date): string {
  const month = `${date.getMonth() + 1}`.padStart(2, '0')
  const day = `${date.getDate()}`.padStart(2, '0')
  return `${date.getFullYear()}-${month}-${day}`
}

export function shiftIsoDate(isoDate: string, days: number): string {
  const [year = 0, month = 1, day = 1] = isoDate.split('-').map(Number)
  const date = new Date(year, month - 1, day)
  date.setDate(date.getDate() + days)
  return toIsoDate(date)
}

const LEGEND: { label: string; swatchClass: string }[] = [
  { label: 'Résident présent', swatchClass: 'plan-swatch-resident' },
  { label: 'Équipe Ecoworking', swatchClass: 'plan-swatch-staff' },
  { label: 'Nomade (ticket)', swatchClass: 'plan-swatch-external' },
  { label: 'Présence partielle', swatchClass: 'plan-swatch-partial' },
  { label: 'Absent / vacant', swatchClass: 'plan-swatch-absent' },
  { label: 'Bureau libre', swatchClass: 'plan-swatch-free' },
  { label: 'Hors service', swatchClass: 'plan-swatch-out' },
]

/** Étages affichés côte à côte (empilés en mobile) — cf. `FloorPlanSvg`. */
const FLOORS = [1, 2]

/**
 * Plan des étages (PRD §3.7.2) : occupation du jour bureau par bureau, avec
 * sélecteur de date, les deux étages côte à côte, panneau de détail au clic
 * (accessible clavier) et alternative texte obligatoire (CLAUDE.md §3.5).
 */
export function FloorPlanPage() {
  usePageTitle('Plan des étages — Portail Ecoworking')

  const [date, setDate] = useState(() => toIsoDate(new Date()))
  const [selectedId, setSelectedId] = useState<number | null>(null)
  // Bloc bureau ayant ouvert le Sheet (clic ou clavier) : à qui rendre le
  // focus à la fermeture (cf. `DeskDetailPanel`, pas de `SheetTrigger` ici).
  const openerRef = useRef<HTMLElement | null>(null)
  const { data, isLoading, isError, error, refetch } = useFloorPlan(date)

  const selectedDesk = data?.desks.find((desk) => desk.resource_id === selectedId) ?? null

  return (
    <PageContainer width="full" className="space-y-6">
      <PageHeader title="Plan des étages" />
      <DirectoryTabs />

      <div className="flex flex-wrap items-end gap-2">
        <Button
          variant="outline"
          size="sm"
          onClick={() => setDate((value) => shiftIsoDate(value, -1))}
        >
          <ChevronLeft className="size-4" aria-hidden="true" />
          <span>Jour précédent</span>
        </Button>
        <div>
          <Label htmlFor="plan-date">Date</Label>
          <Input
            id="plan-date"
            type="date"
            className="w-44"
            value={date}
            onChange={(event) => {
              if (event.target.value !== '') setDate(event.target.value)
            }}
          />
        </div>
        <Button
          variant="outline"
          size="sm"
          onClick={() => setDate((value) => shiftIsoDate(value, 1))}
        >
          <span>Jour suivant</span>
          <ChevronRight className="size-4" aria-hidden="true" />
        </Button>
      </div>

      {isLoading && (
        <div role="status">
          <span className="sr-only">Chargement du plan…</span>
          <Skeleton className="h-[420px] w-full" />
        </div>
      )}
      {isError && (
        <QueryError
          error={error}
          fallback="Impossible de charger le plan des étages."
          onRetry={() => void refetch()}
        />
      )}

      {data && (
        <>
          <ul aria-label="Légende du plan" className="flex flex-wrap gap-x-4 gap-y-1 text-sm">
            {LEGEND.map((item) => (
              <li key={item.label} className="inline-flex items-center gap-1.5">
                <span aria-hidden="true" className={cn('plan-swatch', item.swatchClass)} />
                {item.label}
              </li>
            ))}
          </ul>

          <a href="#plan-alternative" className="skip-link">
            Aller à l’équivalent texte du plan
          </a>

          {/* Les deux étages côte à côte (empilés sous `lg`) : plus de switch,
              tout le plan est lisible d'un coup d'œil. Pas de titre au-dessus
              des cartes : le numéro d'étage est déjà dessiné dans le SVG et
              porté par l'`aria-label` de chaque groupe. Le détail d'un bureau
              s'ouvre dans un panneau latéral (`Sheet`, lot U4b). */}
          <div className="grid gap-4 lg:grid-cols-2">
            {FLOORS.map((floorNumber) => (
              <FloorPlanSvg
                key={floorNumber}
                desks={data.desks}
                floor={floorNumber}
                selectedId={selectedId}
                onSelect={(desk) => {
                  openerRef.current = document.activeElement as HTMLElement | null
                  setSelectedId(desk.resource_id)
                }}
              />
            ))}
          </div>
          <DeskDetailPanel
            desk={selectedDesk}
            onClose={() => setSelectedId(null)}
            returnFocusTo={openerRef.current}
          />

          <FloorPlanTextList desks={data.desks} />
        </>
      )}
    </PageContainer>
  )
}
