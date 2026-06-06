import { Loader2 } from 'lucide-react'
import { cn } from '@/lib/utils'

/** Indicateur de chargement accessible (role=status + texte lecteur d'écran). */
export function Spinner({
  className,
  label = 'Chargement…',
}: {
  className?: string
  label?: string
}) {
  return (
    <span
      role="status"
      className={cn('inline-flex items-center gap-2 text-neutral-500', className)}
    >
      <Loader2 className="size-4 animate-spin" aria-hidden="true" />
      <span className="sr-only">{label}</span>
    </span>
  )
}
