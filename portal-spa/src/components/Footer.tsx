import { Link } from 'react-router'

const CONTACT_MAILTO = 'mailto:contact@ecoworking.fr?subject=[backend ecowo] Demande d’informations'

/**
 * Pied de page minimaliste (PRD §3.1/§3.9, lot G) : mentions légales, CGU,
 * déclaration d'accessibilité, contact. Rendu identique dans le layout
 * authentifié et le layout public — les trois pages liées sont accessibles
 * avec ou sans session (routes hors `<RequireAuth>`, cf. App.tsx).
 */
export function Footer() {
  return (
    <footer className="border-t border-neutral-200 dark:border-neutral-800">
      <div className="mx-auto flex max-w-5xl flex-wrap items-center justify-center gap-x-6 gap-y-2 px-4 py-6 text-sm text-neutral-500 dark:text-neutral-400">
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
