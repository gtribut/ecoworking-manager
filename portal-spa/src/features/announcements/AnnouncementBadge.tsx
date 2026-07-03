import type { AnnouncementType } from './types'

const TYPE_LABELS: Record<AnnouncementType, string> = {
  info: 'Info',
  event: 'Événement',
  alert: 'Alerte',
}

const TYPE_CLASSES: Record<AnnouncementType, string> = {
  info: 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200',
  event: 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200',
  alert: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200',
}

/** Badge de type d'annonce (PRD §3.3.2 : distinction visuelle Info / Événement). */
export function AnnouncementBadge({ type }: { type: AnnouncementType }) {
  return (
    <span
      className={`inline-block rounded-full px-2 py-0.5 text-xs font-medium ${TYPE_CLASSES[type]}`}
    >
      {TYPE_LABELS[type]}
    </span>
  )
}
