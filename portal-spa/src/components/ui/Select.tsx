import { forwardRef, type SelectHTMLAttributes, useId } from 'react'
import { cn } from '@/lib/utils'

export interface SelectProps extends SelectHTMLAttributes<HTMLSelectElement> {
  /**
   * Message d'erreur (RGAA/lot G) : quand fourni, le champ passe
   * `aria-invalid="true"` et un `<p>` d'erreur est rendu et relié au champ
   * via `aria-describedby` — sans que l'appelant ait à câbler ça à la main.
   */
  error?: string
  /** id(s) supplémentaires à ajouter à `aria-describedby` (ex. texte d'aide). */
  describedBy?: string
}

export const Select = forwardRef<HTMLSelectElement, SelectProps>(function Select(
  {
    className,
    children,
    error,
    describedBy,
    id,
    'aria-invalid': ariaInvalid,
    'aria-describedby': ariaDescribedBy,
    ...props
  },
  ref,
) {
  const generatedId = useId()
  const selectId = id ?? generatedId
  const errorId = error !== undefined ? `${selectId}-error` : undefined
  const describedByIds =
    [describedBy, ariaDescribedBy, errorId].filter(Boolean).join(' ') || undefined

  return (
    <>
      <select
        ref={ref}
        id={selectId}
        aria-invalid={error !== undefined ? true : ariaInvalid}
        aria-describedby={describedByIds}
        className={cn(
          'flex h-10 w-full rounded-md border border-neutral-300 bg-white px-3 text-sm disabled:opacity-50 dark:border-neutral-700 dark:bg-neutral-900',
          className,
        )}
        {...props}
      >
        {children}
      </select>
      {error !== undefined && (
        <p id={errorId} className="mt-1 text-sm text-red-700 dark:text-red-300">
          {error}
        </p>
      )}
    </>
  )
})
