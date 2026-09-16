import type { ReactNode } from 'react'
import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { cn } from '@/lib/utils'

export interface KpiTileProps {
  label: string
  value?: ReactNode
  sub?: ReactNode
  loading?: boolean
  /** `amber` : mise en avant (document à valider), cf. maquette C14. */
  tone?: 'default' | 'amber'
  /**
   * Repasse la tuile en tête de la grille en mobile uniquement (maquette :
   * le document à valider précède les autres KPI sous `md`), sans effet en
   * desktop où l'ordre du DOM (accessible) fait foi.
   */
  orderFirst?: boolean
  className?: string
}

/**
 * Tuile KPI du dashboard bento (PRD §3.3, maquette C14) : `Card` shadcn +
 * paire libellé/valeur/sous-texte en `<dl>` (sémantique de statistique,
 * RGAA — un `<dt>` par libellé, une `<dd>` par valeur). Partagée entre
 * `DashboardKpiGrid` (résa, bureau/tickets, facture) et
 * `DashboardDocumentsToValidate` (import cross-feature volontaire, comme
 * `EntityBlock`/`AnnouncementBadge` ailleurs dans le portail).
 */
export function KpiTile({
  label,
  value,
  sub,
  loading = false,
  tone = 'default',
  orderFirst = false,
  className,
}: KpiTileProps) {
  return (
    <Card
      className={cn(
        'gap-1',
        orderFirst && 'order-first md:order-none',
        tone === 'amber' &&
          'border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/40',
        className,
      )}
    >
      <CardContent>
        <dl className="space-y-1">
          <dt
            className={cn(
              'text-sm font-medium text-muted-foreground',
              tone === 'amber' && 'text-amber-900 dark:text-amber-200',
            )}
          >
            {label}
          </dt>
          {loading ? (
            <dd className="space-y-2">
              <Skeleton className="h-6 w-28" />
              <Skeleton className="h-4 w-20" />
            </dd>
          ) : (
            <dd className="space-y-1">
              <p className="truncate text-xl font-semibold tracking-tight">{value}</p>
              {sub !== undefined && (
                <p
                  className={cn(
                    'truncate text-sm',
                    tone === 'amber'
                      ? 'text-amber-900 dark:text-amber-200'
                      : 'text-muted-foreground',
                  )}
                >
                  {sub}
                </p>
              )}
            </dd>
          )}
        </dl>
      </CardContent>
    </Card>
  )
}
