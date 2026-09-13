import { ChevronLeft, ChevronRight } from 'lucide-react'
import { useEffect, useState } from 'react'
import { QueryError } from '@/components/QueryError'
import { Alert } from '@/components/ui/Alert'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { Label } from '@/components/ui/Label'
import { Spinner } from '@/components/ui/Spinner'
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

/**
 * Plan des étages (PRD §3.7.2) : occupation du jour bureau par bureau, avec
 * sélecteur de date, switch étage 1 / étage 2, panneau de détail au clic
 * (accessible clavier) et alternative texte obligatoire (CLAUDE.md §3.5).
 */
export function FloorPlanPage() {
  usePageTitle('Plan des étages — Portail Ecoworking')

  const [date, setDate] = useState(() => toIsoDate(new Date()))
  const [floor, setFloor] = useState(1)
  const [selectedId, setSelectedId] = useState<number | null>(null)
  const { data, isLoading, isError, error, refetch } = useFloorPlan(date)

  const selectedDesk = data?.desks.find((desk) => desk.resource_id === selectedId) ?? null

  // Un bureau sélectionné sur l'autre étage (via la liste) suit le switch.
  useEffect(() => {
    if (selectedDesk && selectedDesk.floor !== null && selectedDesk.floor !== floor) {
      setFloor(selectedDesk.floor)
    }
  }, [selectedDesk, floor])

  return (
    <div className="mx-auto max-w-4xl space-y-6">
      <h1 className="text-2xl font-semibold">Plan des étages</h1>
      <DirectoryTabs />

      <div className="flex flex-wrap items-end gap-2">
        <Button
          variant="secondary"
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
          variant="secondary"
          size="sm"
          onClick={() => setDate((value) => shiftIsoDate(value, 1))}
        >
          <span>Jour suivant</span>
          <ChevronRight className="size-4" aria-hidden="true" />
        </Button>
      </div>

      {isLoading && <Spinner label="Chargement du plan…" />}
      {isError && (
        <QueryError
          error={error}
          fallback="Impossible de charger le plan des étages."
          onRetry={() => void refetch()}
        />
      )}

      {data && !data.is_working_day && (
        <Alert variant="info">Jour non ouvré : les bureaux attitrés sont affichés absents.</Alert>
      )}

      {data && (
        <>
          <fieldset className="flex gap-1">
            <legend className="sr-only">Choix de l’étage</legend>
            {[1, 2].map((floorNumber) => (
              <button
                key={floorNumber}
                type="button"
                aria-pressed={floor === floorNumber}
                className={cn(
                  'rounded-md px-3 py-2 text-sm font-medium',
                  floor === floorNumber
                    ? 'bg-brand-600 text-white'
                    : 'bg-neutral-100 text-neutral-700 hover:bg-neutral-200 dark:bg-neutral-800 dark:text-neutral-200',
                )}
                onClick={() => setFloor(floorNumber)}
              >
                Étage {floorNumber}
              </button>
            ))}
          </fieldset>

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

          <div className="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <FloorPlanSvg
              desks={data.desks}
              floor={floor}
              selectedId={selectedId}
              onSelect={(desk) => setSelectedId(desk.resource_id)}
            />
            {selectedDesk && (
              <DeskDetailPanel desk={selectedDesk} onClose={() => setSelectedId(null)} />
            )}
          </div>

          <FloorPlanTextList desks={data.desks} />
        </>
      )}
    </div>
  )
}
