import { deskLabel } from './plan-utils'
import type { PlanDesk } from './types'

/**
 * Alternative accessible OBLIGATOIRE du plan SVG (CLAUDE.md §3.5) : équivalent
 * texte de l'occupation, bureau par bureau et étage par étage. Le contenu de
 * chaque ligne est identique aux `aria-label` des blocs du SVG (même source
 * {@link deskLabel}), les deux vues sont donc toujours synchrones.
 */
export function FloorPlanTextList({ desks }: { desks: PlanDesk[] }) {
  const floors = [...new Set(desks.map((desk) => desk.floor ?? 0))].sort((a, b) => a - b)

  return (
    <section id="plan-alternative" aria-labelledby="plan-alternative-title" className="space-y-4">
      <h2 id="plan-alternative-title" className="text-lg font-medium">
        Occupation en liste
      </h2>
      <p className="text-sm text-neutral-600 dark:text-neutral-300">
        Équivalent texte du plan interactif ci-dessus, étage par étage.
      </p>

      {floors.map((floor) => (
        <div key={floor}>
          <h3 className="text-base font-medium">Étage {floor}</h3>
          <ul className="mt-2 grid gap-1 text-sm text-neutral-700 sm:grid-cols-2 dark:text-neutral-300">
            {desks
              .filter((desk) => (desk.floor ?? 0) === floor)
              .map((desk) => (
                <li key={desk.resource_id}>{deskLabel(desk)}</li>
              ))}
          </ul>
        </div>
      ))}
    </section>
  )
}
