import { Ticket as TicketIcon } from 'lucide-react'
import { EmptyState } from '@/components/EmptyState'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent } from '@/components/ui/card'
import {
  Table,
  TableBody,
  TableCaption,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import type { Ticket, TicketStatus, TicketType } from './types'

const TYPE_LABELS: Record<TicketType, string> = {
  desk_half_day: 'Bureau — demi-journée',
  meeting_room_half_day: 'Salle — demi-journée',
}

const STATUS_LABELS: Record<TicketStatus, string> = {
  available: 'Disponible',
  used: 'Utilisé',
  restituted: 'Restitué',
  cancelled: 'Annulé',
}

/* Couleurs reprises telles quelles du kit précédent (déjà auditées AA) : pas
 * de variante `Badge` shadcn équivalente pour ces quatre statuts métier. */
const STATUS_CLASSES: Record<TicketStatus, string> = {
  available: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200',
  used: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200',
  restituted: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
  // `bg-muted`/`text-muted-foreground` ne fait que 4,35:1 en clair (review
  // axe) : neutral-200/700 (repris du kit précédent, déjà audité AA) à la place.
  cancelled: 'bg-neutral-200 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-300',
}

function formatDate(iso: string | null): string {
  if (iso === null) {
    return '—'
  }
  return new Date(iso).toLocaleDateString('fr-FR', { dateStyle: 'medium' })
}

/**
 * Détail par ticket (PRD §3.5.6) : type, statut, date de crédit et
 * utilisation (ressource + date) si consommé. Tri du plus récent au plus
 * ancien (crédité le).
 */
export function MyTicketsTable({ tickets }: { tickets: Ticket[] }) {
  const sorted = [...tickets].sort((a, b) =>
    (b.credited_at ?? '').localeCompare(a.credited_at ?? ''),
  )

  return (
    <section aria-labelledby="tickets-detail-heading" className="space-y-4">
      <h2 id="tickets-detail-heading" className="text-lg font-medium">
        Détail de mes tickets
      </h2>

      {sorted.length === 0 ? (
        <EmptyState icon={TicketIcon} title="Aucun ticket pour le moment." />
      ) : (
        <Card>
          <CardContent className="px-0">
            <Table>
              <TableCaption className="sr-only">
                Détail de mes tickets, du plus récent au plus ancien
              </TableCaption>
              <TableHeader>
                <TableRow>
                  <TableHead>Type</TableHead>
                  <TableHead>Statut</TableHead>
                  <TableHead>Crédité le</TableHead>
                  <TableHead>Utilisation</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {sorted.map((ticket) => (
                  <TableRow key={ticket.id}>
                    <TableCell className="font-medium">{TYPE_LABELS[ticket.type]}</TableCell>
                    <TableCell>
                      <Badge className={STATUS_CLASSES[ticket.status]}>
                        {STATUS_LABELS[ticket.status]}
                      </Badge>
                    </TableCell>
                    <TableCell>{formatDate(ticket.credited_at)}</TableCell>
                    <TableCell className="whitespace-normal">
                      {ticket.usage
                        ? `${ticket.usage.resource_name ?? '—'} · ${formatDate(ticket.usage.date)}`
                        : '—'}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      )}
    </section>
  )
}
