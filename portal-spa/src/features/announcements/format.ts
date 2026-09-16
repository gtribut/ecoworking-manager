import type { Announcement } from './types'

/**
 * Extrait de `AnnouncementsPage.tsx` (review U5, bundle) : ces trois
 * fonctions pures étaient importées à la fois par `DashboardAnnouncements`
 * (bloc du dashboard, chargé au premier écran) et `AnnouncementDetailPage`
 * (page lazy) depuis `AnnouncementsPage.tsx` elle-même — un import statique
 * dans un module par ailleurs chargé en dynamique empêche Rollup/Rolldown de
 * le sortir du chunk principal (avertissement `INEFFECTIVE_DYNAMIC_IMPORT` à
 * `vite build`). Les isoler ici laisse `AnnouncementsPage.tsx` partir dans
 * son propre chunk.
 */
export function formatAnnouncementDate(value: string | null): string {
  return value ? new Date(value).toLocaleDateString('fr-FR') : ''
}

export function formatEventSlot(announcement: Announcement): string {
  if (!announcement.event_starts_at) return ''
  const start = new Date(announcement.event_starts_at)
  const day = start.toLocaleDateString('fr-FR', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
  const time = start.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
  return `${day} à ${time}`
}

/** Extrait court pour les cards (PRD §3.3.2 : mini-description ~100 caractères). */
export function excerpt(body: string, max = 100): string {
  return body.length > max ? `${body.slice(0, max).trimEnd()}…` : body
}
