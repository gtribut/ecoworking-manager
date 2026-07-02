import { LogOut, Menu, X } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { NavLink, Outlet, useLocation } from 'react-router'
import { useAuth } from '@/features/auth/useAuth'
import { usePermissions } from '@/features/auth/usePermissions'
import { NotificationBell } from '@/features/notifications/NotificationBell'
import { cn } from '@/lib/utils'
import { Button } from './ui/Button'

const navLinkClass = ({ isActive }: { isActive: boolean }) =>
  cn(
    'rounded-md px-3 py-2 text-sm font-medium',
    isActive
      ? 'bg-brand-50 text-brand-700 dark:bg-neutral-800 dark:text-brand-50'
      : 'text-neutral-600 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800',
  )

interface NavEntry {
  to: string
  label: string
  end?: boolean
}

export function Layout() {
  const { user, logout } = useAuth()
  const { isResident, isExternal } = usePermissions()
  const location = useLocation()
  const [menuOpen, setMenuOpen] = useState(false)
  const mainRef = useRef<HTMLElement>(null)
  const previousPathname = useRef<string | null>(null)

  // Gestion du focus au changement de route (RGAA 12.x) : replace le focus sur
  // le contenu principal pour que les lecteurs d'écran annoncent la nouvelle page.
  useEffect(() => {
    if (previousPathname.current !== null && previousPathname.current !== location.pathname) {
      mainRef.current?.focus()
    }
    previousPathname.current = location.pathname
  }, [location.pathname])

  const entries: NavEntry[] = [
    { to: '/', label: 'Accueil', end: true },
    { to: '/bookings', label: 'Réservations' },
    ...(isExternal ? [{ to: '/tickets', label: 'Tickets' }] : []),
    ...(isResident ? [{ to: '/presence', label: 'Présence' }] : []),
    { to: '/profile', label: 'Profil' },
    { to: '/invoices', label: 'Factures' },
  ]

  const links = (onNavigate?: () => void) =>
    entries.map((entry) => (
      <NavLink
        key={entry.to}
        to={entry.to}
        end={entry.end}
        className={navLinkClass}
        onClick={onNavigate}
      >
        {entry.label}
      </NavLink>
    ))

  return (
    <div className="min-h-screen bg-neutral-50 dark:bg-neutral-950">
      <a href="#main-content" className="skip-link">
        Aller au contenu principal
      </a>

      <header className="border-b border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
        <div className="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-3">
          <span className="text-lg font-semibold">Ecoworking</span>

          <nav aria-label="Navigation principale" className="hidden items-center gap-1 md:flex">
            {links()}
          </nav>

          <div className="flex items-center gap-3">
            <NotificationBell />
            {user && (
              <span className="hidden text-sm text-neutral-600 sm:inline dark:text-neutral-300">
                {user.first_name} {user.last_name}
              </span>
            )}
            <Button
              variant="ghost"
              size="sm"
              className="hidden md:inline-flex"
              onClick={() => void logout()}
            >
              <LogOut className="size-4" aria-hidden="true" />
              <span>Déconnexion</span>
            </Button>
            <button
              type="button"
              aria-expanded={menuOpen}
              aria-controls="mobile-nav"
              aria-label={
                menuOpen ? 'Fermer le menu de navigation' : 'Ouvrir le menu de navigation'
              }
              className="rounded-md p-2 text-neutral-600 hover:bg-neutral-100 md:hidden dark:text-neutral-300 dark:hover:bg-neutral-800"
              onClick={() => setMenuOpen((value) => !value)}
            >
              {menuOpen ? (
                <X className="size-5" aria-hidden="true" />
              ) : (
                <Menu className="size-5" aria-hidden="true" />
              )}
            </button>
          </div>
        </div>

        {menuOpen && (
          <nav
            id="mobile-nav"
            aria-label="Navigation principale"
            className="border-t border-neutral-200 px-4 py-3 md:hidden dark:border-neutral-800"
          >
            <div className="flex flex-col gap-1">
              {links(() => setMenuOpen(false))}
              <Button
                variant="ghost"
                size="sm"
                className="justify-start"
                onClick={() => void logout()}
              >
                <LogOut className="size-4" aria-hidden="true" />
                <span>Déconnexion</span>
              </Button>
            </div>
          </nav>
        )}
      </header>

      <main
        id="main-content"
        ref={mainRef}
        tabIndex={-1}
        className="mx-auto max-w-5xl px-4 py-8 focus:outline-none"
      >
        <Outlet />
      </main>
    </div>
  )
}
