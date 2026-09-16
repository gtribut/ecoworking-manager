import { ArrowLeft, CalendarDays, MapPin, Users } from 'lucide-react'
import { Link, useParams } from 'react-router'
import { PageContainer } from '@/components/PageContainer'
import { QueryError } from '@/components/QueryError'
import { Spinner } from '@/components/ui/spinner'
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

      {isLoading && <Spinner label="Chargement de l’actualité…" />}
      {isError && (
        <QueryError message="Cette actualité est introuvable ou n’est plus disponible." />
      )}

      {announcement && (
        <article aria-labelledby="announcement-title" className="space-y-4">
          <header className="space-y-2">
            <div className="flex flex-wrap items-center gap-2">
              <AnnouncementBadge type={announcement.type} />
              {announcement.published_at && (
                <span className="text-xs text-neutral-500 dark:text-neutral-400">
                  Publié le {formatAnnouncementDate(announcement.published_at)}
                </span>
              )}
            </div>
            <h1 id="announcement-title" className="text-2xl font-semibold">
              {announcement.title}
            </h1>
          </header>

          {announcement.type === 'event' && (
            <dl className="space-y-1 rounded-lg border border-neutral-200 bg-white p-4 text-sm dark:border-neutral-800 dark:bg-neutral-900">
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

          {/* Corps saisi par l'admin (texte brut) : retours à la ligne préservés. */}
          <p className="whitespace-pre-wrap text-sm leading-relaxed text-neutral-700 dark:text-neutral-200">
            {announcement.body}
          </p>

          <RsvpButton announcement={announcement} />
        </article>
      )}
    </PageContainer>
  )
}
