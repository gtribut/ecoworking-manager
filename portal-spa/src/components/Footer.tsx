import { Link } from 'react-router'
import { CONTACT_MAILTO } from '@/lib/contact'
import { cn } from '@/lib/utils'

/**
 * Pied de page minimaliste (PRD §3.1/§3.9, lot G) : mentions légales, CGU,
 * déclaration d'accessibilité, contact. Rendu identique dans le layout
 * authentifié et le layout public — les trois pages liées sont accessibles
 * avec ou sans session (routes hors `<RequireAuth>`, cf. App.tsx).
 */
export function Footer({ className }: { className?: string }) {
  return (
    <footer className={cn('border-t border-border', className)}>
      <div className="mx-auto flex max-w-5xl flex-wrap items-center justify-center gap-x-6 gap-y-2 px-4 py-6 text-sm text-muted-foreground">
        <Link to="/mentions-legales" className="hover:underline">
          Mentions légales
        </Link>
        <Link to="/cgu" className="hover:underline">
          CGU
        </Link>
        <Link to="/accessibilite" className="hover:underline">
          Accessibilité
        </Link>
        <a href={CONTACT_MAILTO} className="hover:underline">
          Contact
        </a>
      </div>
    </footer>
  )
}
