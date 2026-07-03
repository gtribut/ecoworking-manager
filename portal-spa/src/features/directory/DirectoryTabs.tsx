import { NavLink } from 'react-router'
import { cn } from '@/lib/utils'

const tabClass = ({ isActive }: { isActive: boolean }) =>
  cn(
    'rounded-md px-3 py-2 text-sm font-medium',
    isActive
      ? 'bg-brand-50 text-brand-700 dark:bg-neutral-800 dark:text-brand-50'
      : 'text-neutral-600 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800',
  )

/** Navigation interne du module annuaire : liste des coworkers / plan des étages. */
export function DirectoryTabs() {
  return (
    <nav aria-label="Vues de l’annuaire" className="flex gap-1">
      <NavLink to="/directory" end className={tabClass}>
        Liste des coworkers
      </NavLink>
      <NavLink to="/directory/plan" className={tabClass}>
        Plan des étages
      </NavLink>
    </nav>
  )
}
