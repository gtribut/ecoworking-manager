import { ArrowLeft, CalendarDays, MapPin, Users } from 'lucide-react'
import { Link, useParams } from 'react-router'
import { MarkdownContent } from '@/components/MarkdownContent'
import { PageContainer } from '@/components/PageContainer'
import { QueryError } from '@/components/QueryError'
import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { usePageTitle } from '@/lib/usePageTitle'
import { AnnouncementBadge } from './AnnouncementBadge'
import { formatAnnouncementDate, formatEventSlot } from './AnnouncementsPage'
import { RsvpButton } from './RsvpButton'
import { useAnnouncement } from './useAnnouncements'

export function AnnouncementDetailPage() {
  const params = useParams<{ id: string }>()
  const id = Number(params.id)
  const { data: announcement, isLoading, isError } = useAnnouncement(id)

  usePageTitle(
    announcement ? `${announcement.title} — Portail Ecoworking` : 'Actualité — Portail Ecoworking',
  )

  return (
    <PageContainer width="narrow" className="space-y-6">
      <p>
        <Link
          to="/announcements"
          className="inline-flex items-center gap-1 text-sm text-brand-700 dark:text-brand-300 underline"
        >
          <ArrowLeft className="size-4" aria-hidden="true" />
          Toutes les actualités
        </Link>
      </p>

      {isLoading && (
        <div role="status" className="space-y-3">
          <span className="sr-only">Chargement de l’actualité…</span>
          <Skeleton className="h-8 w-2/3" />
          <Skeleton className="h-40 w-full" />
        </div>
      )}
      {isError && (
        <QueryError message="Cette actualité est introuvable ou n’est plus disponible." />
      )}

      {announcement && (
        <Card>
          <CardContent>
            <article aria-labelledby="announcement-title" className="space-y-4">
              <header className="space-y-2">
                <div className="flex flex-wrap items-center gap-2">
                  <AnnouncementBadge type={announcement.type} />
                  {announcement.published_at && (
                    <span className="text-xs text-muted-foreground">
                      Publié le {formatAnnouncementDate(announcement.published_at)}
                    </span>
                  )}
                </div>
                {/* Exception documentée (portal-spa/CLAUDE.md « Shell du portail ») :
                    le <h1> de cette page vient des données et reste dans
                    l'<article>, pas de <PageHeader> ici. */}
                <h1 id="announcement-title" className="text-2xl font-semibold">
                  {announcement.title}
                </h1>
              </header>

              {announcement.type === 'event' && (
                <dl className="space-y-1 rounded-lg bg-muted/50 p-4 text-sm">
                  {announcement.event_starts_at && (
                    <div className="flex items-center gap-2">
                      <dt className="inline-flex items-center gap-1 font-medium">
                        <CalendarDays className="size-4" aria-hidden="true" />
                        Date
                      </dt>
                      <dd>{formatEventSlot(announcement)}</dd>
                    </div>
                  )}
                  {announcement.location && (
                    <div className="flex items-center gap-2">
                      <dt className="inline-flex items-center gap-1 font-medium">
                        <MapPin className="size-4" aria-hidden="true" />
                        Lieu
                      </dt>
                      <dd>{announcement.location}</dd>
                    </div>
                  )}
                  {announcement.requires_registration && announcement.registered_count !== null && (
                    <div className="flex items-center gap-2">
                      <dt className="inline-flex items-center gap-1 font-medium">
                        <Users className="size-4" aria-hidden="true" />
                        Inscrits
                      </dt>
                      <dd>
                        {announcement.registered_count}
                        {announcement.max_participants !== null &&
                          ` / ${announcement.max_participants}`}
                      </dd>
                    </div>
                  )}
                </dl>
              )}

              {/* Corps saisi par l'admin (texte brut, pas du markdown à l'origine) :
                  MarkdownContent reste le rendu commun de texte libre du portail
                  (bios annuaire, cf. `DirectoryPage`) — `breaks: true` préserve les
                  retours à la ligne simples, sanitizé (allow-list) comme ailleurs. */}
              <MarkdownContent
                markdown={announcement.body}
                className="space-y-2 text-sm leading-relaxed text-foreground"
              />

              <RsvpButton announcement={announcement} />
            </article>
          </CardContent>
        </Card>
      )}
    </PageContainer>
  )
}
