import type { ReactNode } from 'react'
import { cn } from '@/lib/utils'

/**
 * Message d'alerte accessible. `role="alert"` pour les erreurs (annoncé
 * immédiatement par les lecteurs d'écran), `role="status"` pour le reste.
 */
export function Alert({
  variant = 'error',
  children,
  className,
}: {
  variant?: 'error' | 'success' | 'info'
  children: ReactNode
  className?: string
}) {
  const styles = {
    error:
      'border-red-300 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200',
    success:
      'border-green-300 bg-green-50 text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200',
    info: 'border-blue-300 bg-blue-50 text-blue-800 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-200',
  } as const

  return (
    <div
      role={variant === 'error' ? 'alert' : 'status'}
      className={cn('rounded-md border px-4 py-3 text-sm', styles[variant], className)}
    >
      {children}
    </div>
  )
}
