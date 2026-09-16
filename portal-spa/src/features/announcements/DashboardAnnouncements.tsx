import { Link } from 'react-router'
import { QueryError } from '@/components/QueryError'
import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { AnnouncementBadge } from './AnnouncementBadge'
import { excerpt, formatAnnouncementDate } from './AnnouncementsPage'
import { useAnnouncements } from './useAnnouncements'

/**
 * Bloc « Actualités Ecoworking » du dashboard (PRD §3.3.2, maquette C14) :
 * les 3 dernières annonces publiées visibles du membre, badge Info/Événement,
 * lien détail. Colonne droite du dashboard bento, toujours affichée (aucun
 * gating de rôle).
 */
export function DashboardAnnouncements() {
  const { data, isLoading, isError, refetch } = useAnnouncements(1)

  const latest = data?.data.slice(0, 3) ?? []

  return (
    <section aria-labelledby="dashboard-announcements-title">
      <Card className="flex h-full flex-col">
        <CardContent className="flex flex-1 flex-col gap-3">
          <div className="flex items-center justify-between gap-2">
            <h2 id="dashboard-announcements-title" className="text-base font-semibold">
              Actualités Ecoworking
            </h2>
            <Link
              to="/announcements"
              className="text-sm font-medium text-brand-700 underline underline-offset-2 dark:text-brand-300"
            >
              Toutes les actualités
            </Link>
          </div>

          {isLoading && (
            <div className="space-y-2">
              <Skeleton className="h-16 w-full" />
              <Skeleton className="h-16 w-full" />
            </div>
          )}
          {isError && (
            <QueryError
              message="Impossible de charger les actualités."
              onRetry={() => void refetch()}
            />
          )}

          {data && latest.length === 0 && (
            <p className="text-sm text-muted-foreground">Aucune actualité pour le moment.</p>
          )}

          {latest.length > 0 && (
            <ul className="divide-y divide-border">
              {latest.map((announcement) => (
                <li key={announcement.id} className="py-3 first:pt-0">
                  <div className="flex flex-wrap items-center gap-2">
                    <AnnouncementBadge type={announcement.type} />
                    {announcement.published_at && (
                      <span className="text-xs text-muted-foreground">
                        {formatAnnouncementDate(announcement.published_at)}
                      </span>
                    )}
                  </div>
                  <p className="mt-1 font-medium">{announcement.title}</p>
                  <p className="mt-1 text-sm text-muted-foreground">{excerpt(announcement.body)}</p>
                  <p className="mt-1">
                    <Link
                      to={`/announcements/${announcement.id}`}
                      className="text-sm font-medium text-brand-700 underline underline-offset-2 dark:text-brand-300"
                    >
                      Voir<span className="sr-only"> l’actualité {announcement.title}</span> →
                    </Link>
                  </p>
                </li>
              ))}
            </ul>
          )}
        </CardContent>
      </Card>
    </section>
  )
}
