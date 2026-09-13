import { Bell, Check } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router'
import { Button } from '@/components/ui/Button'
import { cn } from '@/lib/utils'
import type { NotificationItem } from './types'
import { useMarkAllAsRead, useMarkAsRead, useNotifications } from './useNotifications'

/**
 * Centre de notifications in-app (PRD §3.8.4) : cloche + badge « non lues »,
 * panneau déroulant accessible. Pas de pattern ARIA « menu » (APG) : le panneau
 * est une simple région parcourue au Tab natif. Le focus est déplacé dans le
 * panneau à l'ouverture et rendu au bouton à la fermeture (Escape / sélection).
 * Cliquer une notification la marque lue et navigue vers la page liée.
 */
export function NotificationBell() {
  const [open, setOpen] = useState(false)
  const containerRef = useRef<HTMLDivElement>(null)
  const panelRef = useRef<HTMLElement>(null)
  const buttonRef = useRef<HTMLButtonElement>(null)
  const navigate = useNavigate()

  const { data, hasNextPage, isFetchingNextPage, fetchNextPage } = useNotifications()
  const markAsRead = useMarkAsRead()
  const markAllAsRead = useMarkAllAsRead()

  const items = data?.pages.flatMap((page) => page.data) ?? []
  const unread = data?.pages[0]?.meta.unread_count ?? 0

  useEffect(() => {
    if (open) panelRef.current?.focus()
  }, [open])

  useEffect(() => {
    if (!open) return

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setOpen(false)
        buttonRef.current?.focus()
      }
    }
    const onClickOutside = (event: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
        setOpen(false)
      }
    }

    document.addEventListener('keydown', onKeyDown)
    document.addEventListener('mousedown', onClickOutside)
    return () => {
      document.removeEventListener('keydown', onKeyDown)
      document.removeEventListener('mousedown', onClickOutside)
    }
  }, [open])

  const handleSelect = (item: NotificationItem) => {
    if (!item.is_read) markAsRead.mutate(item.id)
    setOpen(false)
    buttonRef.current?.focus()
    if (item.data.url) navigate(item.data.url)
  }

  /** Marquer lu manuellement (PRD §3.8.4, lot G) : sans naviguer, panneau ouvert. */
  const handleMarkAsRead = (item: NotificationItem) => {
    markAsRead.mutate(item.id)
  }

  return (
    <div ref={containerRef} className="relative">
      <button
        ref={buttonRef}
        type="button"
        onClick={() => setOpen((value) => !value)}
        aria-expanded={open}
        aria-label={
          unread > 0 ? `Notifications, ${unread} non lue${unread > 1 ? 's' : ''}` : 'Notifications'
        }
        className="relative rounded-md p-2 text-neutral-600 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800"
      >
        <Bell className="size-5" aria-hidden="true" />
        {unread > 0 && (
          <span
            aria-hidden="true"
            className="absolute -top-0.5 -right-0.5 flex min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[0.625rem] font-semibold text-white"
          >
            {unread > 9 ? '9+' : unread}
          </span>
        )}
      </button>

      {open && (
        <section
          ref={panelRef}
          aria-label="Notifications"
          tabIndex={-1}
          className="absolute right-0 z-20 mt-2 w-80 overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-lg dark:border-neutral-800 dark:bg-neutral-900"
        >
          <div className="flex items-center justify-between border-b border-neutral-200 px-4 py-2 dark:border-neutral-800">
            <span className="text-sm font-medium">Notifications</span>
            {unread > 0 && (
              <button
                type="button"
                onClick={() => markAllAsRead.mutate()}
                className="text-xs text-brand-700 hover:underline dark:text-brand-50"
              >
                Tout marquer comme lu
              </button>
            )}
          </div>

          {items.length === 0 ? (
            <p className="px-4 py-6 text-center text-sm text-neutral-500 dark:text-neutral-400">
              Aucune notification pour le moment.
            </p>
          ) : (
            <ul className="max-h-96 divide-y divide-neutral-100 overflow-y-auto dark:divide-neutral-800">
              {items.map((item) => (
                <li key={item.id} className="flex items-stretch">
                  <button
                    type="button"
                    onClick={() => handleSelect(item)}
                    className={cn(
                      'flex flex-1 flex-col gap-0.5 px-4 py-3 text-left text-sm hover:bg-neutral-50 dark:hover:bg-neutral-800',
                      !item.is_read && 'bg-brand-50/50 dark:bg-neutral-800/40',
                    )}
                  >
                    <span className="flex items-center gap-2">
                      {!item.is_read && (
                        <span
                          aria-hidden="true"
                          className="size-2 shrink-0 rounded-full bg-brand-600"
                        />
                      )}
                      <span className={cn(!item.is_read && 'font-medium')}>
                        {item.data.message}
                      </span>
                    </span>
                    {item.created_at && (
                      <time
                        dateTime={item.created_at}
                        className="text-xs text-neutral-500 dark:text-neutral-400"
                      >
                        {formatDate(item.created_at)}
                      </time>
                    )}
                  </button>
                  {/* Marquer lu manuellement, sans naviguer (PRD §3.8.4) : bouton
                      dédié, à côté (pas dans) le bouton principal — jamais de
                      bouton imbriqué dans un bouton. */}
                  {!item.is_read && (
                    <button
                      type="button"
                      onClick={() => handleMarkAsRead(item)}
                      aria-label="Marquer comme lu"
                      className="flex shrink-0 items-center px-3 text-neutral-400 hover:bg-neutral-50 hover:text-brand-700 dark:hover:bg-neutral-800 dark:hover:text-brand-300"
                    >
                      <Check className="size-4" aria-hidden="true" />
                    </button>
                  )}
                </li>
              ))}
            </ul>
          )}

          {hasNextPage && (
            <div className="border-t border-neutral-100 px-4 py-2 text-center dark:border-neutral-800">
              <Button
                type="button"
                variant="ghost"
                size="sm"
                disabled={isFetchingNextPage}
                onClick={() => void fetchNextPage()}
              >
                {isFetchingNextPage ? 'Chargement…' : 'Charger plus'}
              </Button>
            </div>
          )}
        </section>
      )}
    </div>
  )
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('fr-FR', {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  })
}
