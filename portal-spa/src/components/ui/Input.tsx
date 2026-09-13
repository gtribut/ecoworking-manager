import { forwardRef, type InputHTMLAttributes, useId } from 'react'
import { cn } from '@/lib/utils'

export interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
  /**
   * Message d'erreur (RGAA/lot G) : quand fourni, le champ passe
   * `aria-invalid="true"` et un `<p>` d'erreur est rendu et relié au champ
   * via `aria-describedby` — sans que l'appelant ait à câbler ça à la main.
   */
  error?: string
  /** id(s) supplémentaires à ajouter à `aria-describedby` (ex. texte d'aide). */
  describedBy?: string
}

export const Input = forwardRef<HTMLInputElement, InputProps>(function Input(
  {
    className,
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
  const inputId = id ?? generatedId
  const errorId = error !== undefined ? `${inputId}-error` : undefined
  const describedByIds =
    [describedBy, ariaDescribedBy, errorId].filter(Boolean).join(' ') || undefined

  return (
    <>
      <input
        ref={ref}
        id={inputId}
        aria-invalid={error !== undefined ? true : ariaInvalid}
        aria-describedby={describedByIds}
        className={cn(
          'flex h-10 w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm placeholder:text-neutral-400 disabled:cursor-not-allowed disabled:opacity-50 dark:border-neutral-700 dark:bg-neutral-900',
          'aria-[invalid=true]:border-red-500',
          className,
        )}
        {...props}
      />
      {error !== undefined && (
        <p id={errorId} className="mt-1 text-sm text-red-700 dark:text-red-300">
          {error}
        </p>
      )}
    </>
  )
})
