import { forwardRef, type SelectHTMLAttributes } from 'react'
import { cn } from '@/lib/utils'

export const Select = forwardRef<HTMLSelectElement, SelectHTMLAttributes<HTMLSelectElement>>(
  function Select({ className, children, ...props }, ref) {
    return (
      <select
        ref={ref}
        className={cn(
          'flex h-10 w-full rounded-md border border-neutral-300 bg-white px-3 text-sm disabled:opacity-50 dark:border-neutral-700 dark:bg-neutral-900',
          className,
        )}
        {...props}
      >
        {children}
      </select>
    )
  },
)
