import { Menu, X } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { NavLink, Outlet, useLocation } from 'react-router'
import { Toaster } from 'sonner'
import { usePermissions } from '@/features/auth/usePermissions'
import { NotificationBell } from '@/features/notifications/NotificationBell'
import { cn } from '@/lib/utils'
import { Footer } from './Footer'
import { OfflineBanner } from './OfflineBanner'
import { ProfileMenu } from './ProfileMenu'

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
  const { isResident, isExternal, canViewDirectory, canViewBilling, canViewBookings } =
    usePermissions()
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

  // Navigation filtrée par rôle (PRD §2.5) : un module inaccessible n'est jamais
  // proposé — les routes correspondantes sont gardées par <RequireAccess>.
  const entries: NavEntry[] = [
    { to: '/', label: 'Accueil', end: true },
    // Calendrier des salles : jamais pour un contact facturation pur.
    ...(canViewBookings ? [{ to: '/bookings', label: 'Réservations' }] : []),
    // Actualités : lisibles par tous les rôles, y compris un contact facturation
    // pur (il fait partie des audiences) — seule l'INSCRIPTION à un événement
    // demande `register-event`, côté RsvpButton.
    { to: '/announcements', label: 'Actualités' },
    ...(isExternal ? [{ to: '/tickets', label: 'Tickets' }] : []),
    // Présence/absences : seulement avec un bureau attitré (pas les `additional`).
    ...(isResident ? [{ to: '/presence', label: 'Présence' }] : []),
    // C12.5 — Annuaire (masqué aux external : pas de view-annuaire)
    ...(canViewDirectory ? [{ to: '/directory', label: 'Annuaire' }] : []),
    { to: '/documents', label: 'Documents' },
    { to: '/profile', label: 'Profil' },
    // Module administratif (PRD §3.6.1) : rôle billing_contact uniquement.
    ...(canViewBilling ? [{ to: '/invoices', label: 'Factures' }] : []),
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
    <div className="flex min-h-screen flex-col bg-neutral-50 dark:bg-neutral-950">
      <a href="#main-content" className="skip-link">
        Aller au contenu principal
      </a>

      {/* Toasts (PRD §3.1/§3.8.4) : feedback d'action éphémère, thème suivi,
          animations coupées si prefers-reduced-motion (géré par Sonner). */}
      <Toaster richColors closeButton position="top-right" />
      <OfflineBanner />

      <header className="border-b border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
        <div className="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-3">
          <span className="text-lg font-semibold">Ecoworking</span>

          <nav aria-label="Navigation principale" className="hidden items-center gap-1 md:flex">
            {links()}
          </nav>

          <div className="flex items-center gap-3">
            <NotificationBell />
            <ProfileMenu />
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
            <div className="flex flex-col gap-1">{links(() => setMenuOpen(false))}</div>
          </nav>
        )}
      </header>

      <main
        id="main-content"
        ref={mainRef}
        tabIndex={-1}
        className="mx-auto w-full max-w-5xl flex-1 px-4 py-8 focus:outline-none"
      >
        <Outlet />
      </main>

      <Footer />
    </div>
  )
}
