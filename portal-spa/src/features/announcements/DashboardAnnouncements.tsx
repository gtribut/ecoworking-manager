import { Link } from 'react-router'
import { QueryError } from '@/components/QueryError'
import { Spinner } from '@/components/ui/Spinner'
import { AnnouncementBadge } from './AnnouncementBadge'
import { excerpt, formatAnnouncementDate } from './AnnouncementsPage'
import { useAnnouncements } from './useAnnouncements'

/**
 * Bloc « Actualités Ecoworking » du dashboard (PRD §3.3.2) : les 3 dernières
 * annonces publiées visibles du membre, badge Info/Événement, lien détail.
 */
export function DashboardAnnouncements() {
  const { data, isLoading, isError, refetch } = useAnnouncements(1)

  const latest = data?.data.slice(0, 3) ?? []

  return (
    <section aria-labelledby="dashboard-announcements-title" className="space-y-3">
      <h2 id="dashboard-announcements-title" className="text-lg font-semibold">
        Actualités Ecoworking
      </h2>

      {isLoading && <Spinner label="Chargement des actualités…" />}
      {isError && (
        <QueryError
          message="Impossible de charger les actualités."
          onRetry={() => void refetch()}
        />
      )}

      {data && latest.length === 0 && (
        <p className="text-sm text-neutral-500 dark:text-neutral-400">
          Aucune actualité pour le moment.
        </p>
      )}

      {latest.length > 0 && (
        <ul className="space-y-3">
          {latest.map((announcement) => (
            <li
              key={announcement.id}
              className="rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900"
            >
              <div className="flex flex-wrap items-center gap-2">
                <AnnouncementBadge type={announcement.type} />
                {announcement.published_at && (
                  <span className="text-xs text-neutral-500 dark:text-neutral-400">
                    {formatAnnouncementDate(announcement.published_at)}
                  </span>
                )}
              </div>
              <p className="mt-1 font-medium">{announcement.title}</p>
              <p className="mt-1 text-sm text-neutral-600 dark:text-neutral-300">
                {excerpt(announcement.body)}
              </p>
              <p className="mt-2">
                <Link
                  to={`/announcements/${announcement.id}`}
                  className="text-sm text-brand-700 dark:text-brand-300 underline"
                >
                  Voir
                  <span className="sr-only"> l’actualité {announcement.title}</span> →
                </Link>
              </p>
            </li>
          ))}
        </ul>
      )}

      <p>
        <Link to="/announcements" className="text-sm text-brand-700 dark:text-brand-300 underline">
          Voir toutes les actualités →
        </Link>
      </p>
    </section>
  )
}
