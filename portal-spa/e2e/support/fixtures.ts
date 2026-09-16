import AxeBuilder from '@axe-core/playwright'
import { test as base } from '@playwright/test'

/**
 * Fixtures partagées de la suite e2e SPA.
 *
 * `checkA11y` (C11.4) audite la page courante avec axe-core, restreint aux
 * règles de la cible RGAA 4.1 niveau AA du projet (CLAUDE.md §3.5), soit les
 * tags WCAG 2.0/2.1 A + AA — pas `best-practice`, hors périmètre.
 * ZÉRO violation tolérée, quel que soit l'impact : le rapport d'échec liste
 * chaque règle violée (impact, description, URL de la doc axe) et les
 * sélecteurs des nœuds concernés.
 */
interface A11yOptions {
  /**
   * Sélecteurs CSS exclus de l'audit. À n'utiliser QUE pour un choix
   * délibéré impossible à corriger proprement, avec une justification écrite
   * en commentaire au point d'appel (CLAUDE.md §3.5 : ne jamais désactiver
   * une règle a11y sans justification documentée).
   */
  exclude?: readonly string[]
}

interface A11yFixtures {
  checkA11y: (context?: string, options?: A11yOptions) => Promise<void>
}

/** Cible du projet : WCAG 2.1 AA (RGAA 4.1 AA). */
const WCAG_AA_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']

/**
 * Seule exclusion d'audit du portail : la grille de l'agenda des salles
 * (ADR-0013 **D4**, dérogation actée le 2026-09-16).
 *
 * FullCalendar ne garantit ni une navigation clavier complète ni une
 * restitution fidèle par lecteur d'écran sur sa grille temporelle interactive
 * (glisser, chevauchements) ; y remédier en profondeur dépasse le coût
 * acceptable à l'échelle d'Ecoworking (~50 membres). En contrepartie, **la même
 * page** expose une alternative accessible intégralement auditée : le bouton
 * « Nouvelle réservation » (saisie manuelle salle / date / heures) et la liste
 * « Mes réservations » (consulter, modifier, annuler). Tout le reste de l'écran
 * — barre d'outils, chips de filtre, popover, panneau droit, modale — reste
 * audité sans exclusion.
 *
 * `.fc` n'est plus une classe de FullCalendar en v7 (classes hachées) : c'est
 * le conteneur de `RoomsCalendar` qui la porte explicitement, justement pour
 * rester le point d'accroche documenté ici.
 */
export const FULLCALENDAR_AXE_EXCLUDE = ['.fc'] as const

type AxeResults = Awaited<ReturnType<AxeBuilder['analyze']>>

/** Rapport d'échec lisible : une entrée par règle, nœuds fautifs listés. */
function formatViolations(context: string, violations: AxeResults['violations']): string {
  const entries = violations.map((violation) => {
    const nodes = violation.nodes
      .map((node) => {
        const summary = node.failureSummary?.replace(/\s+/g, ' ').trim() ?? ''
        return `    - ${node.target.join(' ')}${summary ? `\n      ${summary}` : ''}`
      })
      .join('\n')
    return [
      `  ● ${violation.id} [impact: ${violation.impact ?? 'n/a'}] — ${violation.help}`,
      `    ${violation.helpUrl}`,
      nodes,
    ].join('\n')
  })
  return [
    `Audit a11y « ${context} » : ${violations.length} règle(s) WCAG 2.1 AA violée(s)`,
    ...entries,
  ].join('\n\n')
}

export const test = base.extend<A11yFixtures>({
  checkA11y: async ({ page }, use) => {
    await use(async (context = 'page', options = {}) => {
      let builder = new AxeBuilder({ page }).withTags([...WCAG_AA_TAGS])
      for (const selector of options.exclude ?? []) {
        builder = builder.exclude(selector)
      }
      const results = await builder.analyze()

      if (results.violations.length > 0) {
        throw new Error(formatViolations(context, results.violations))
      }
    })
  },
})

export { expect } from '@playwright/test'
