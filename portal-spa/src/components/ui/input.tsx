import type * as React from 'react'
import { useId } from 'react'
import { cn } from '@/lib/utils'

/*
 * Primitive shadcn/ui `input`, avec deux écarts assumés par rapport au fichier
 * généré — cf. ADR-0013 D1 :
 *
 * 1. Le contrat du champ maison remplacé est conservé : props `error` /
 *    `describedBy` qui câblent seules `aria-invalid` et `aria-describedby`
 *    (helpers `useFieldAria` / `FieldError` ci-dessous, partagés avec
 *    `Textarea` et `NativeSelect` via `fieldClassName`).
 * 2. `outline-none` est retiré des classes de champ : il neutralisait
 *    l'indicateur de focus brand global (`:focus-visible` dans styles.css) et
 *    le remplaçait par un anneau translucide, moins net que le kit maison.
 *    L'outline brand décollé est posé explicitement, comme sur `Button`.
 */

/** Classes de champ partagées par `Input`, `Textarea` et `NativeSelect`. */
export const fieldClassName =
  'w-full min-w-0 rounded-lg border border-input bg-transparent text-base transition-colors placeholder:text-muted-foreground focus-visible:border-ring focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive md:text-sm dark:bg-input/30 dark:aria-invalid:border-destructive/50'

export interface FieldErrorProps {
  /**
   * Message d'erreur (RGAA/lot G) : quand fourni, le champ passe
   * `aria-invalid="true"` et un `<p>` d'erreur est rendu et relié au champ
   * via `aria-describedby` — sans que l'appelant ait à câbler ça à la main.
   */
  error?: string
  /** id(s) supplémentaires à ajouter à `aria-describedby` (ex. texte d'aide). */
  describedBy?: string
}

/** Câblage `id` / `aria-invalid` / `aria-describedby` commun aux champs. */
export function useFieldAria({
  id,
  error,
  describedBy,
  ariaInvalid,
  ariaDescribedBy,
}: {
  id?: string
  error?: string
  describedBy?: string
  ariaInvalid?: React.AriaAttributes['aria-invalid']
  ariaDescribedBy?: string
}) {
  const generatedId = useId()
  const fieldId = id ?? generatedId
  const errorId = error !== undefined ? `${fieldId}-error` : undefined

  return {
    fieldId,
    errorId,
    'aria-invalid': error !== undefined ? true : ariaInvalid,
    'aria-describedby':
      [describedBy, ariaDescribedBy, errorId].filter(Boolean).join(' ') || undefined,
  }
}

/** Message d'erreur relié au champ par `aria-describedby`. */
export function FieldError({ id, children }: { id: string; children: React.ReactNode }) {
  return (
    <p id={id} className="mt-1 text-sm text-red-700 dark:text-red-300">
      {children}
    </p>
  )
}

export type InputProps = React.ComponentProps<'input'> & FieldErrorProps

function Input({
  className,
  type,
  error,
  describedBy,
  id,
  'aria-invalid': ariaInvalid,
  'aria-describedby': ariaDescribedBy,
  ...props
}: InputProps) {
  const field = useFieldAria({ id, error, describedBy, ariaInvalid, ariaDescribedBy })

  return (
    <>
      <input
        type={type}
        data-slot="input"
        id={field.fieldId}
        aria-invalid={field['aria-invalid']}
        aria-describedby={field['aria-describedby']}
        className={cn(
          fieldClassName,
          'h-9 px-3 py-1 file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground',
          className,
        )}
        {...props}
      />
      {error !== undefined && field.errorId !== undefined && (
        <FieldError id={field.errorId}>{error}</FieldError>
      )}
    </>
  )
}

export { Input }
