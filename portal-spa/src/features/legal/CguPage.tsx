import { PublicLayout } from '@/components/PublicLayout'
import { usePageTitle } from '@/lib/usePageTitle'

/**
 * Conditions générales d'utilisation (PRD §3.1/§3.9, lot G) : contenu
 * **provisoire**, à compléter/valider par Ecoworking (objet précis du
 * service, durée d'engagement, résiliation, droit applicable) — voir les
 * `[à compléter]` ci-dessous.
 */
export function CguPage() {
  usePageTitle('CGU — Portail Ecoworking')

  return (
    <PublicLayout>
      <div className="space-y-6">
        <h1 className="text-2xl font-semibold">Conditions générales d’utilisation</h1>
        <p className="rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200">
          Contenu provisoire — à compléter par Ecoworking.
        </p>

        <section className="space-y-2">
          <h2 className="text-lg font-medium">1. Objet</h2>
          <p className="text-sm text-neutral-700 dark:text-neutral-300">
            Les présentes conditions régissent l’utilisation du portail membre d’Ecoworking (SARL),
            réservé aux membres et entreprises domiciliées de l’espace de coworking. [à compléter]
          </p>
        </section>

        <section className="space-y-2">
          <h2 className="text-lg font-medium">2. Accès au portail</h2>
          <p className="text-sm text-neutral-700 dark:text-neutral-300">
            L’accès est créé par Ecoworking pour chaque membre ; il est personnel et non cessible.
            [à compléter]
          </p>
        </section>

        <section className="space-y-2">
          <h2 className="text-lg font-medium">3. Données personnelles</h2>
          <p className="text-sm text-neutral-700 dark:text-neutral-300">
            Le traitement des données personnelles est décrit dans la politique de confidentialité
            d’Ecoworking. [à compléter — lien vers la politique de confidentialité]
          </p>
        </section>

        <section className="space-y-2">
          <h2 className="text-lg font-medium">4. Contact</h2>
          <p className="text-sm text-neutral-700 dark:text-neutral-300">
            Pour toute question relative aux présentes CGU :{' '}
            <a href="mailto:contact@ecoworking.fr" className="underline">
              contact@ecoworking.fr
            </a>
          </p>
        </section>
      </div>
    </PublicLayout>
  )
}
