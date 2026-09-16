import type { ReactNode } from 'react'
import { Link } from 'react-router'
import { BrandMark } from '@/components/BrandMark'
import { Footer } from '@/components/Footer'

/**
 * Layout allégé pour les pages légales (mentions, CGU, accessibilité) : ni
 * navigation ni menu profil, accessible avec ou sans session (PRD §3.1, lot
 * G) — mêmes routes que dans le layout authentifié. Même pied de page que
 * `Layout`, mêmes jetons que le shell depuis C14 (U2).
 */
export function PublicLayout({ children }: { children: ReactNode }) {
  return (
    <div className="flex min-h-svh flex-col bg-neutral-50 dark:bg-neutral-950">
      <a href="#main-content" className="skip-link">
        Aller au contenu principal
      </a>
      <header className="border-b border-border bg-card">
        <div className="mx-auto flex h-14 max-w-3xl items-center px-4">
          <Link to="/" className="flex items-center gap-2.5">
            <BrandMark />
            <span className="text-base font-bold tracking-tight">Ecoworking</span>
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
