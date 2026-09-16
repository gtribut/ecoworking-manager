import { NavLink } from 'react-router'
import { cn } from '@/lib/utils'

const tabClass = ({ isActive }: { isActive: boolean }) =>
  cn(
    'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
    isActive
      ? 'bg-background text-foreground shadow-sm'
      : // `text-muted-foreground` (neutral-500) sur `bg-muted` (#f5f5f5) ne fait
        // que 4,34:1 (review axe) : neutral-600/300 remonte à ≥ 4,5:1 clair et sombre.
        'text-neutral-600 hover:text-foreground dark:text-neutral-300',
  )

/**
 * Navigation interne du module annuaire : liste des coworkers / plan des
 * étages. Habillage visuel repris de `TabsList`/`TabsTrigger` (pastille
 * active sur fond `bg-muted`), mais restent des `NavLink` — deux routes
 * distinctes, pas un `Tabs` Radix (les e2e ciblent un lien nommé « Plan des
 * étages », pas un `role="tab"`).
 */
export function DirectoryTabs() {
  return (
    <nav
      aria-label="Vues de l’annuaire"
      className="inline-flex w-fit items-center gap-1 rounded-lg bg-muted p-[3px]"
    >
      <NavLink to="/directory" end className={tabClass}>
        Liste des coworkers
      </NavLink>
      <NavLink to="/directory/plan" className={tabClass}>
        Plan des étages
      </NavLink>
    </nav>
  )
}
