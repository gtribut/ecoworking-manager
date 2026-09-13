import { PublicLayout } from '@/components/PublicLayout'
import { usePageTitle } from '@/lib/usePageTitle'

/**
 * Déclaration d'accessibilité (PRD §3.1, §1.4 — RGAA 4.1) : gabarit officiel
 * accessibilite.numerique.gouv.fr. Ecoworking (SARL, ~50 membres) n'est pas
 * légalement soumis au RGAA (CLAUDE.md §3.5) : la démarche reste volontaire,
 * d'où l'absence d'audit externe formalisé à ce stade — signalé ci-dessous
 * plutôt que de prétendre à une conformité vérifiée.
 */
export function AccessibilitePage() {
  usePageTitle('Déclaration d’accessibilité — Portail Ecoworking')

  return (
    <PublicLayout>
      <div className="space-y-6">
        <h1 className="text-2xl font-semibold">Déclaration d’accessibilité</h1>

        <p className="text-sm text-neutral-700 dark:text-neutral-300">
          Ecoworking s’engage à rendre son portail membre accessible conformément au référentiel
          général d’amélioration de l’accessibilité (RGAA), version 4.1, à titre de démarche qualité
          volontaire (le RGAA n’est pas une obligation légale pour Ecoworking, SARL de moins de 250
          M€ de chiffre d’affaires).
        </p>

        <section className="space-y-2">
          <h2 className="text-lg font-medium">État de conformité</h2>
          <p className="text-sm text-neutral-700 dark:text-neutral-300">
            Le portail membre d’Ecoworking est en conformité <strong>partielle</strong> avec le RGAA
            version 4.1, en raison des non-conformités et dérogations listées ci-dessous.
          </p>
        </section>

        <section className="space-y-2">
          <h2 className="text-lg font-medium">Résultats des tests</h2>
          <p className="text-sm text-neutral-700 dark:text-neutral-300">
            L’audit de conformité RGAA (échantillon de pages, taux global de conformité) n’a pas
            encore été réalisé par un tiers. [à compléter par Ecoworking une fois l’audit mené —
            date, périmètre de l’échantillon, taux de conformité]. En attendant, le développement
            suit les exigences RGAA dès la conception (revue continue, audits automatisés
            `axe-core`).
          </p>
        </section>

        <section className="space-y-2">
          <h2 className="text-lg font-medium">Contenus non accessibles</h2>
          <p className="text-sm text-neutral-700 dark:text-neutral-300">
            Non-conformités connues :
          </p>
          <ul className="list-disc space-y-1 pl-6 text-sm text-neutral-700 dark:text-neutral-300">
            <li>
              Le plan des étages (SVG) et le calendrier de réservation reposent sur des alternatives
              textuelles (liste par étage, vue liste des créneaux) qui n’ont pas encore fait l’objet
              d’un test utilisateur avec lecteur d’écran.
            </li>
            <li>
              Certains messages d’erreur de formulaire ne sont pas encore tous liés au champ
              concerné.
            </li>
            <li>
              [à compléter après le premier audit Pa11y / Lighthouse / NVDA, cf. CLAUDE.md §3.5]
            </li>
          </ul>
        </section>

        <section className="space-y-2">
          <h2 className="text-lg font-medium">
            Établissement de cette déclaration d’accessibilité
          </h2>
          <p className="text-sm text-neutral-700 dark:text-neutral-300">
            Cette déclaration a été établie le 13 septembre 2026. Elle sera mise à jour à chaque
            audit ou évolution significative du portail.
          </p>
          <p className="text-sm text-neutral-700 dark:text-neutral-300">
            <strong>Technologies utilisées :</strong> HTML5, CSS, JavaScript (React).
            <br />
            <strong>Environnement de test :</strong> [à compléter — navigateurs et lecteurs d’écran
            testés lors du prochain audit manuel].
          </p>
        </section>

        <section className="space-y-2">
          <h2 className="text-lg font-medium">Retour d’information et contact</h2>
          <p className="text-sm text-neutral-700 dark:text-neutral-300">
            Si vous n’arrivez pas à accéder à un contenu ou à un service du portail, vous pouvez
            contacter Ecoworking pour être orienté vers une alternative accessible ou obtenir le
            contenu sous une autre forme :{' '}
            <a href="mailto:contact@ecoworking.fr" className="underline">
              contact@ecoworking.fr
            </a>
            .
          </p>
        </section>

        <section className="space-y-2">
          <h2 className="text-lg font-medium">Voies de recours</h2>
          <p className="text-sm text-neutral-700 dark:text-neutral-300">
            Cette procédure est à utiliser dans le cas suivant : vous avez signalé au responsable du
            portail un défaut d’accessibilité qui vous empêche d’accéder à un contenu ou à un des
            services et vous n’avez pas obtenu de réponse satisfaisante.
          </p>
          <p className="text-sm text-neutral-700 dark:text-neutral-300">
            Le RGAA ne s’imposant pas légalement à Ecoworking (SARL), les voies de recours prévues
            par le Défenseur des droits pour les services publics ne s’appliquent pas formellement
            ici ; le contact direct ci-dessus reste le moyen prévu pour signaler un problème
            d’accessibilité. [à compléter par Ecoworking si une voie de recours complémentaire est
            souhaitée]
          </p>
        </section>
      </div>
    </PublicLayout>
  )
}
