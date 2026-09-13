import { PublicLayout } from '@/components/PublicLayout'
import { usePageTitle } from '@/lib/usePageTitle'

/**
 * Mentions légales (PRD §3.1/§3.9, lot G) : contenu **provisoire**, à
 * compléter par Ecoworking (raison sociale exacte, SIRET/RCS, capital social,
 * directeur de publication, hébergeur) — voir les `[à compléter]` ci-dessous.
 * Aucune donnée non vérifiée (SIRET, RCS…) n'est inventée.
 */
export function MentionsLegalesPage() {
  usePageTitle('Mentions légales — Portail Ecoworking')

  return (
    <PublicLayout>
      <div className="space-y-6">
        <h1 className="text-2xl font-semibold">Mentions légales</h1>
        <p className="rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
          Contenu provisoire — à compléter par Ecoworking.
        </p>

        <section className="space-y-2">
          <h2 className="text-lg font-medium">Éditeur du site</h2>
          <p className="text-sm text-neutral-700 dark:text-neutral-300">
            Ecoworking, société à responsabilité limitée (SARL) au capital de [à compléter].
            <br />
            Siège social : [à compléter].
            <br />
            RCS : [à compléter] — SIRET : [à compléter].
            <br />
            Directeur de la publication : [à compléter].
            <br />
            Contact :{' '}
            <a href="mailto:contact@ecoworking.fr" className="underline">
              contact@ecoworking.fr
            </a>
          </p>
        </section>

        <section className="space-y-2">
          <h2 className="text-lg font-medium">Hébergement</h2>
          <p className="text-sm text-neutral-700 dark:text-neutral-300">
            Le portail et ses données sont hébergés par Clever Cloud SAS, 3 rue de l’Allier, 44000
            Nantes, France.
          </p>
        </section>

        <section className="space-y-2">
          <h2 className="text-lg font-medium">Propriété intellectuelle</h2>
          <p className="text-sm text-neutral-700 dark:text-neutral-300">
            L’ensemble des contenus de ce portail (textes, mises en page, éléments graphiques) est
            la propriété d’Ecoworking, sauf mention contraire. [à compléter]
          </p>
        </section>
      </div>
    </PublicLayout>
  )
}
