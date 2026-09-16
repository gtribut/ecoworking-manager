import { Plus } from 'lucide-react'
import { Link } from 'react-router'
import { QueryError } from '@/components/QueryError'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { formatBookingRange } from './format'
import { useUpcomingBookings } from './useBookings'

const WEEKDAY_FORMAT = new Intl.DateTimeFormat('fr-FR', { weekday: 'short' })

/** Pastille jour de semaine + quantième, comme la maquette C14 (Main.dc.html). */
function DayPill({ iso }: { iso: string }) {
  const date = new Date(iso)
  return (
    <span
      aria-hidden="true"
      className="flex size-11 flex-none flex-col items-center justify-center rounded-lg bg-muted"
    >
      {/* `text-muted-foreground` (neutral-500) tombe sous 4.5:1 sur `bg-muted`
          (contrairement à `bg-card`) : neutral-600/300 reste AA ici. */}
      <span className="text-[10px] font-semibold uppercase text-neutral-600 dark:text-neutral-300">
        {WEEKDAY_FORMAT.format(date).replace('.', '')}
      </span>
      <span className="text-base leading-none font-semibold">{date.getDate()}</span>
    </span>
  )
}

function timeRange(startsAt: string, endsAt: string): string {
  const time = (iso: string) =>
    new Date(iso).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
  return `${time(startsAt)} – ${time(endsAt)}`
}

/**
 * Bloc « Mes prochaines réservations » du dashboard (PRD §3.3.2, recette
 * R-05, maquette C14) : 3 résas confirmées à venir max — pastille jour,
 * libellé, horaire · salle en badge, bouton « Nouvelle réservation ».
 */
export function DashboardUpcomingBookings() {
  const { data: bookings, isLoading, isError, refetch } = useUpcomingBookings(3)

  return (
    <section aria-labelledby="dashboard-bookings-title">
      <Card className="flex h-full flex-col">
        <CardContent className="flex flex-1 flex-col gap-3">
          <div className="flex items-center justify-between gap-2">
            <h2 id="dashboard-bookings-title" className="text-base font-semibold">
              Mes prochaines réservations
            </h2>
            <Link
              to="/bookings"
              className="text-sm font-medium text-link underline underline-offset-2"
            >
              Tout voir
            </Link>
          </div>

          {isLoading && (
            <div className="space-y-2">
              <Skeleton className="h-14 w-full" />
              <Skeleton className="h-14 w-full" />
            </div>
          )}
          {isError && (
            <QueryError
              message="Impossible de charger vos réservations."
              onRetry={() => void refetch()}
            />
          )}

          {bookings && bookings.length === 0 && (
            <p className="text-sm text-muted-foreground">Aucune réservation à venir.</p>
          )}

          {bookings && bookings.length > 0 && (
            <ul className="divide-y divide-border">
              {bookings.map((booking) => (
                <li key={booking.id} className="flex items-center gap-3 py-3 first:pt-0">
                  <DayPill iso={booking.starts_at} />
                  <span className="min-w-0 flex-1">
                    <span className="block truncate font-medium">
                      {booking.title ?? booking.resource_name}
                    </span>
                    {/* La pastille jour (`DayPill`) est `aria-hidden` : c'est
                        ici, en `sr-only`, que la date complète (pas seulement
                        l'horaire) atteint les lecteurs d'écran (RGAA 1.3.1,
                        PRD §3.3.2 « date + créneau »). */}
                    <span className="sr-only">
                      {formatBookingRange(booking.starts_at, booking.ends_at)}
                    </span>
                    <span
                      aria-hidden="true"
                      className="block truncate text-sm text-muted-foreground"
                    >
                      {timeRange(booking.starts_at, booking.ends_at)}
                    </span>
                  </span>
                  <Badge variant="secondary" className="flex-none">
                    {booking.resource_name}
                  </Badge>
                </li>
              ))}
            </ul>
          )}

          <div className="mt-auto pt-1">
            <Button asChild variant="outline" size="sm">
              <Link to="/bookings">
                <Plus aria-hidden="true" />
                Nouvelle réservation
              </Link>
            </Button>
          </div>
        </CardContent>
      </Card>
    </section>
  )
}
