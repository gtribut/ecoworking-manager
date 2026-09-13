import type { ReactNode } from 'react'
import { Link } from 'react-router'
import { Footer } from '@/components/Footer'

/**
 * Layout allégé pour les pages légales (mentions, CGU, accessibilité) : ni
 * navigation ni menu profil, accessible avec ou sans session (PRD §3.1, lot
 * G) — mêmes routes que dans le layout authentifié. Même pied de page que
 * `Layout`.
 */
export function PublicLayout({ children }: { children: ReactNode }) {
  return (
    <div className="flex min-h-screen flex-col bg-neutral-50 dark:bg-neutral-950">
      <a href="#main-content" className="skip-link">
        Aller au contenu principal
      </a>
      <header className="border-b border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
        <div className="mx-auto max-w-3xl px-4 py-3">
          <Link to="/" className="text-lg font-semibold">
            Ecoworking
          </Link>
        </div>
      </header>
      <main
        id="main-content"
        tabIndex={-1}
        className="mx-auto w-full max-w-3xl flex-1 px-4 py-8 focus:outline-none"
      >
        {children}
      </main>
      <Footer />
    </div>
  )
}
