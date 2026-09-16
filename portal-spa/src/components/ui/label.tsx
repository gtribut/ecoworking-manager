import { Label as LabelPrimitive } from 'radix-ui'
import type * as React from 'react'
import { cn } from '@/lib/utils'

/*
 * Primitive shadcn/ui `label`. Seul écart avec le fichier généré : `mb-1`, la
 * marge basse que portait le composant maison remplacé — les formulaires du
 * portail posent `<Label>` puis le champ sans conteneur `grid gap-2`.
 */
function Label({ className, ...props }: React.ComponentProps<typeof LabelPrimitive.Root>) {
  return (
    <LabelPrimitive.Root
      data-slot="label"
      className={cn(
        'mb-1 flex items-center gap-2 text-sm leading-none font-medium select-none group-data-[disabled=true]:pointer-events-none group-data-[disabled=true]:opacity-50 peer-disabled:cursor-not-allowed peer-disabled:opacity-50',
        className,
      )}
      {...props}
    />
  )
}

export { Label }
