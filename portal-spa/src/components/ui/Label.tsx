import type { LabelHTMLAttributes } from 'react'
import { cn } from '@/lib/utils'

export function Label({ className, ...props }: LabelHTMLAttributes<HTMLLabelElement>) {
  return (
    // biome-ignore lint/a11y/noLabelWithoutControl: wrapper générique du design system ; l'association au champ passe par `htmlFor` fourni par l'appelant (couplage label↔input garanti côté formulaire).
    <label
      className={cn(
        'mb-1 block text-sm font-medium text-neutral-800 dark:text-neutral-200',
        className,
      )}
      {...props}
    />
  )
}
