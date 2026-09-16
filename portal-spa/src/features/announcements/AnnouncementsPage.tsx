import { CalendarDays, MapPin, Megaphone, Users } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router'
import { EmptyState } from '@/components/EmptyState'
import { PageContainer } from '@/components/PageContainer'
import { PageHeader } from '@/components/PageHeader'
import { QueryError } from '@/components/QueryError'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { usePageTitle } from '@/lib/usePageTitle'
import { AnnouncementBadge } from './AnnouncementBadge'
import { excerpt, formatAnnouncementDate, formatEventSlot } from './format'
import { useAnnouncements } from './useAnnouncements'

export function AnnouncementsPage() {
  usePageTitle('Actualités — Portail Ecoworking')

  const [page, setPage] = useState(1)
  const { data, isLoading, isError, refetch } = useAnnouncements(page)

  return (
    <PageContainer width="wide" className="space-y-6">
      <PageHeader title="Actualités Ecoworking" />

      {isLoading && (
        <div role="status" className="space-y-4">
          <span className="sr-only">Chargement des actualités…</span>
          <Skeleton className="h-28 w-full" />
          <Skeleton className="h-28 w-full" />
        </div>
      )}
      {isError && (
        <QueryError
          message="Impossible de charger les actualités."
          onRetry={() => void refetch()}
        />
      )}

      {data && data.data.length === 0 && (
        <EmptyState icon={Megaphone} title="Aucune actualité pour le moment." />
      )}

      {data && data.data.length > 0 && (
        <>
          <Card>
            <CardContent>
              <ul>
                {data.data.map((announcement) => (
                  <li
                    key={announcement.id}
                    className="border-t py-4 first:border-t-0 first:pt-0 last:pb-0"
                  >
                    <article aria-labelledby={`announcement-${announcement.id}-title`}>
                      <div className="flex flex-wrap items-center gap-2">
                        <AnnouncementBadge type={announcement.type} />
                        {announcement.published_at && (
                          <span className="text-xs text-muted-foreground">
                            Publié le {formatAnnouncementDate(announcement.published_at)}
                          </span>
                        )}
                      </div>

                      <h2
                        id={`announcement-${announcement.id}-title`}
                        className="mt-2 text-base font-medium"
                      >
                        <Link
                          to={`/announcements/${announcement.id}`}
                          className="hover:text-brand-700 hover:underline dark:hover:text-brand-300"
                        >
                          {announcement.title}
                        </Link>
                      </h2>

                      {announcement.event_starts_at && (
                        <p className="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground">
                          <span className="inline-flex items-center gap-1">
                            <CalendarDays className="size-4" aria-hidden="true" />
                            {formatEventSlot(announcement)}
                          </span>
                          {announcement.location && (
                            <span className="inline-flex items-center gap-1">
                              <MapPin className="size-4" aria-hidden="true" />
                              {announcement.location}
                            </span>
                          )}
                          {announcement.is_registered && (
                            <span className="inline-flex items-center gap-1 font-medium text-green-700 dark:text-green-300">
                              <Users className="size-4" aria-hidden="true" />
                              Inscrit(e)
                            </span>
                          )}
                        </p>
                      )}

                      <p className="mt-2 text-sm text-muted-foreground">
                        {excerpt(announcement.body)}
                      </p>

                      <p className="mt-3">
                        <Link
                          to={`/announcements/${announcement.id}`}
                          className="text-sm text-link underline"
                        >
                          Voir
                          <span className="sr-only"> l’actualité {announcement.title}</span> →
                        </Link>
                      </p>
                    </article>
                  </li>
                ))}
              </ul>
            </CardContent>
          </Card>

          {data.meta.last_page > 1 && (
            <nav
              className="flex items-center justify-between"
              aria-label="Pagination des actualités"
            >
              <Button
                variant="outline"
                size="sm"
                disabled={page <= 1}
                onClick={() => setPage((p) => Math.max(1, p - 1))}
              >
                Précédent
              </Button>
              <span aria-live="polite" className="text-sm text-muted-foreground">
                Page {data.meta.current_page} sur {data.meta.last_page}
              </span>
              <Button
                variant="outline"
                size="sm"
                disabled={page >= data.meta.last_page}
                onClick={() => setPage((p) => p + 1)}
              >
                Suivant
              </Button>
            </nav>
          )}
        </>
      )}
    </PageContainer>
  )
}
