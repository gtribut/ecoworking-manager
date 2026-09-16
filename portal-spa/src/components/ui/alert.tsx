import { cva, type VariantProps } from 'class-variance-authority'
import type * as React from 'react'
import { cn } from '@/lib/utils'

/*
 * Primitive shadcn/ui `alert`, étendue des variantes sémantiques du kit maison
 * remplacé (`error` / `success` / `info` / `warning`) : les couleurs et les
 * contrastes sont repris tels quels du composant précédent, déjà audités AA.
 * `default` et `destructive` (variantes shadcn d'origine) restent disponibles
 * pour les lots suivants.
 */
const alertVariants = cva(
  "group/alert relative grid w-full gap-0.5 rounded-lg border px-4 py-3 text-left text-sm has-data-[slot=alert-action]:pr-18 has-[>svg]:grid-cols-[auto_1fr] has-[>svg]:gap-x-2 *:[svg]:row-span-2 *:[svg]:translate-y-0.5 *:[svg]:text-current *:[svg:not([class*='size-'])]:size-4",
  {
    variants: {
      variant: {
        default: 'border-border bg-card text-card-foreground',
        destructive:
          'border-red-300 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200',
        error:
          'border-red-300 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200',
        success:
          'border-green-300 bg-green-50 text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200',
        info: 'border-blue-300 bg-blue-50 text-blue-800 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-200',
        warning:
          'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200',
      },
    },
    defaultVariants: {
      variant: 'default',
    },
  },
)

export type AlertProps = React.ComponentProps<'div'> & VariantProps<typeof alertVariants>

/**
 * Message d'alerte accessible. `role="alert"` pour les erreurs (annoncé
 * immédiatement par les lecteurs d'écran), `role="status"` pour le reste —
 * comportement du kit maison remplacé, sur lequel s'appuient les tests.
 */
function Alert({ className, variant = 'default', role, ...props }: AlertProps) {
  const isError = variant === 'error' || variant === 'destructive'

  return (
    <div
      data-slot="alert"
      role={role ?? (isError ? 'alert' : 'status')}
      className={cn(alertVariants({ variant }), className)}
      {...props}
    />
  )
}

function AlertTitle({ className, ...props }: React.ComponentProps<'div'>) {
  return (
    <div
      data-slot="alert-title"
      className={cn(
        'font-medium group-has-[>svg]/alert:col-start-2 [&_a]:underline [&_a]:underline-offset-3',
        className,
      )}
      {...props}
    />
  )
}

function AlertDescription({ className, ...props }: React.ComponentProps<'div'>) {
  return (
    <div
      data-slot="alert-description"
      className={cn(
        'text-sm text-balance md:text-pretty [&_a]:underline [&_a]:underline-offset-3 [&_p:not(:last-child)]:mb-4',
        className,
      )}
      {...props}
    />
  )
}

function AlertAction({ className, ...props }: React.ComponentProps<'div'>) {
  return (
    <div data-slot="alert-action" className={cn('absolute top-2 right-2', className)} {...props} />
  )
}

export { Alert, AlertAction, AlertDescription, AlertTitle, alertVariants }
