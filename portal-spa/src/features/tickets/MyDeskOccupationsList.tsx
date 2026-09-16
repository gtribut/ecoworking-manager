import { Armchair } from 'lucide-react'
import { useRef, useState } from 'react'
import { toast } from 'sonner'
import { EmptyState } from '@/components/EmptyState'
import { QueryError } from '@/components/QueryError'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { ConfirmButton } from '@/components/ui/confirm-button'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Table,
  TableBody,
  TableCaption,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { getApiErrorMessage } from '@/lib/errors'
import type { DeskOccupation, DeskOccupationStatus, DeskPeriod } from './types'
import { useCancelDeskOccupation, useDeskOccupations } from './useTickets'

const PERIOD_LABELS: Record<DeskPeriod, string> = {
  morning: 'Matin',
  afternoon: 'Après-midi',
  full_day: 'Journée complète',
}

const STATUS_LABELS: Record<DeskOccupationStatus, string> = {
  present: 'Réservé',
  absent: 'Absent',
  cancelled: 'Annulé',
}

// `bg-muted`/`text-muted-foreground` ne fait que 4,35:1 en clair (review
// axe) : neutral-200/700 à la place, cohérent avec `MyTicketsTable`.
const STATUS_CLASSES: Record<DeskOccupationStatus, string> = {
  present: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200',
  absent: 'bg-neutral-200 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300',
  cancelled: 'bg-neutral-200 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300',
}

type Scope = 'upcoming' | 'past'

function formatDate(isoDate: string): string {
  return new Date(`${isoDate}T00:00:00`).toLocaleDateString('fr-FR', { dateStyle: 'long' })
}

/**
 * « Mes bureaux réservés » (PRD §3.5.9) : occupations de bureau nomade à venir
 * (défaut) et historique, annulation avec restitution du ticket (délai Q22
 * transposé, cf. DeskOccupationPolicy::delete).
 */
export function MyDeskOccupationsList() {
  const [scope, setScope] = useState<Scope>('upcoming')
  const [page, setPage] = useState(1)
  const { data, isLoading, isError, refetch } = useDeskOccupations(scope, page)
  const cancelOccupation = useCancelDeskOccupation()
  // La ligne annulée disparaît de la liste (refetch) : son bouton « Annuler »
  // est démonté, ce qui perdrait le focus. On le reporte sur le titre de
  // section plutôt que de le laisser retomber sur <body> (RGAA 12.x) ; le
  // résultat de l'action, lui, est annoncé par le toast (PRD §3.1).
  const headingRef = useRef<HTMLHeadingElement>(null)

  function switchScope(next: Scope) {
    setScope(next)
    setPage(1)
  }

  async function onCancel(occupation: DeskOccupation) {
    try {
      await cancelOccupation.mutateAsync(occupation.id)
      toast.success(`Réservation du bureau « ${occupation.desk_name} » annulée.`)
      headingRef.current?.focus()
    } catch (error) {
      toast.error(getApiErrorMessage(error, 'Annulation impossible.'))
    }
  }

  return (
    <section aria-labelledby="my-desks-heading" className="space-y-4">
      <h2
        id="my-desks-heading"
        ref={headingRef}
        tabIndex={-1}
        className="text-lg font-medium focus:outline-none"
      >
        {scope === 'upcoming' ? 'Mes bureaux réservés' : 'Historique de mes bureaux réservés'}
      </h2>

      <div className="flex flex-wrap gap-2">
        <Button
          variant={scope === 'upcoming' ? 'default' : 'outline'}
          size="sm"
          aria-pressed={scope === 'upcoming'}
          onClick={() => switchScope('upcoming')}
        >
          À venir
        </Button>
        <Button
          variant={scope === 'past' ? 'default' : 'outline'}
          size="sm"
          aria-pressed={scope === 'past'}
          onClick={() => switchScope('past')}
        >
          Historique
        </Button>
      </div>

      {isLoading && (
        <div role="status" className="space-y-2">
          <span className="sr-only">Chargement de vos bureaux réservés…</span>
          <Skeleton className="h-10 w-full" />
          <Skeleton className="h-10 w-full" />
          <Skeleton className="h-10 w-full" />
        </div>
      )}
      {isError && (
        <QueryError
          message="Impossible de charger vos bureaux réservés."
          onRetry={() => void refetch()}
        />
      )}

      {data && data.data.length === 0 && (
        <EmptyState
          icon={Armchair}
          title={
            scope === 'upcoming'
              ? 'Aucun bureau réservé à venir.'
              : 'Aucun bureau réservé passé pour le moment.'
          }
        />
      )}

      {data && data.data.length > 0 && (
        <>
          <Card>
            <CardContent className="px-0">
              {/* `[&_th]:px-4 [&_td]:px-4` (review F-1) : les cellules `p-2` par
                  défaut collaient à 8 px du bord de la Card, moins que le
                  padding de carte habituel (16 px). */}
              <Table className="[&_td]:px-4 [&_th]:px-4">
                <TableCaption className="sr-only">
                  {scope === 'upcoming'
                    ? 'Mes bureaux nomades réservés à venir'
                    : 'Historique de mes bureaux nomades réservés'}
                </TableCaption>
                <TableHeader>
                  <TableRow>
                    <TableHead>Bureau</TableHead>
                    <TableHead>Date</TableHead>
                    <TableHead>Période</TableHead>
                    <TableHead>Ticket</TableHead>
                    <TableHead>Statut</TableHead>
                    <TableHead className="text-right">Action</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {data.data.map((occupation) => (
                    <TableRow key={occupation.id}>
                      {/* `<th scope="row">` (review M-1) plutôt qu'un `TableCell`. */}
                      <th scope="row" className="p-2 align-middle font-medium whitespace-nowrap">
                        {occupation.desk_name}
                        {occupation.desk_floor !== null && (
                          <span className="block text-xs font-normal text-muted-foreground">
                            Étage {occupation.desk_floor}
                          </span>
                        )}
                      </th>
                      <TableCell>{formatDate(occupation.date)}</TableCell>
                      <TableCell>{PERIOD_LABELS[occupation.period]}</TableCell>
                      <TableCell>
                        {occupation.ticket ? `nº ${occupation.ticket.id}` : '—'}
                      </TableCell>
                      <TableCell>
                        <Badge className={STATUS_CLASSES[occupation.status]}>
                          {STATUS_LABELS[occupation.status]}
                        </Badge>
                      </TableCell>
                      <TableCell className="text-right whitespace-normal">
                        {occupation.cancellable ? (
                          <ConfirmButton
                            variant="destructive"
                            size="sm"
                            disabled={cancelOccupation.isPending}
                            confirmMessage="Annuler cette réservation ?"
                            confirmLabel="Oui, annuler"
                            cancelLabel="Non"
                            onConfirm={() => void onCancel(occupation)}
                          >
                            Annuler
                            <span className="sr-only">
                              {' '}
                              la réservation du bureau {occupation.desk_name} du{' '}
                              {formatDate(occupation.date)}
                            </span>
                          </ConfirmButton>
                        ) : (
                          <span className="text-muted-foreground">
                            {scope === 'upcoming' ? 'Non annulable (délai dépassé)' : '—'}
                          </span>
                        )}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>

          {data.meta.last_page > 1 && (
            <nav
              className="flex items-center justify-between"
              aria-label="Pagination des bureaux réservés"
            >
              <Button
                variant="outline"
                size="sm"
                disabled={page <= 1}
                onClick={() => setPage((current) => Math.max(1, current - 1))}
              >
                Précédent
              </Button>
              <span aria-live="polite" className="text-sm text-neutral-600 dark:text-neutral-300">
                Page {data.meta.current_page} sur {data.meta.last_page}
              </span>
              <Button
                variant="outline"
                size="sm"
                disabled={page >= data.meta.last_page}
                onClick={() => setPage((current) => current + 1)}
              >
                Suivant
              </Button>
            </nav>
          )}
        </>
      )}
    </section>
  )
}
