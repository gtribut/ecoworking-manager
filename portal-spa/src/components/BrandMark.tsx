import { Leaf } from 'lucide-react'
import { cn } from '@/lib/utils'

/**
 * Pastille de marque Ecoworking (maquettes C14) : feuille blanche sur carré
 * vert brand. Purement décorative — le mot « Ecoworking » qui l'accompagne
 * porte le texte.
 */
export function BrandMark({ className }: { className?: string }) {
  return (
    <span
      aria-hidden="true"
      className={cn(
        'flex size-8 shrink-0 items-center justify-center rounded-lg bg-brand-600 text-white',
        className,
      )}
    >
      <Leaf className="size-[18px]" />
    </span>
  )
}
