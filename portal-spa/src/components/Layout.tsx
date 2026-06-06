import { LogOut } from 'lucide-react'
import { NavLink, Outlet } from 'react-router'
import { useAuth } from '@/features/auth/useAuth'
import { cn } from '@/lib/utils'
import { Button } from './ui/Button'

const navLinkClass = ({ isActive }: { isActive: boolean }) =>
  cn(
    'rounded-md px-3 py-2 text-sm font-medium',
    isActive
      ? 'bg-brand-50 text-brand-700 dark:bg-neutral-800 dark:text-brand-50'
      : 'text-neutral-600 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800',
  )

export function Layout() {
  const { user, logout } = useAuth()

  return (
    <div className="min-h-screen bg-neutral-50 dark:bg-neutral-950">
      <a href="#main-content" className="skip-link">
        Aller au contenu principal
      </a>

      <header className="border-b border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
        <div className="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-3">
          <span className="text-lg font-semibold">Ecoworking</span>

          <nav aria-label="Navigation principale" className="flex items-center gap-1">
            <NavLink to="/" end className={navLinkClass}>
              Accueil
            </NavLink>
            <NavLink to="/profile" className={navLinkClass}>
              Profil
            </NavLink>
            <NavLink to="/invoices" className={navLinkClass}>
              Factures
            </NavLink>
          </nav>

          <div className="flex items-center gap-3">
            {user && (
              <span className="hidden text-sm text-neutral-600 sm:inline dark:text-neutral-300">
                {user.first_name} {user.last_name}
              </span>
            )}
            <Button variant="ghost" size="sm" onClick={() => void logout()}>
              <LogOut className="size-4" aria-hidden="true" />
              <span>Déconnexion</span>
            </Button>
          </div>
        </div>
      </header>

      <main id="main-content" className="mx-auto max-w-5xl px-4 py-8">
        <Outlet />
      </main>
    </div>
  )
}
