import type * as React from 'react'
import {
  FieldError,
  type FieldErrorProps,
  fieldClassName,
  useFieldAria,
} from '@/components/ui/input'
import { cn } from '@/lib/utils'

/*
 * `<select>` natif habillé comme les autres champs shadcn, volontairement
 * conservé à la place du `Select` Radix (`@/components/ui/select`) : tous les
 * appelants du portail passent par `react-hook-form` (`{...form.register('x')}`),
 * qui pilote un élément de formulaire natif via `ref` + `onChange`. Le `Select`
 * Radix, lui, n'expose pas d'élément natif et impose un `Controller`.
 * Il reste installé pour les lots C14 suivants (menus riches, filtres).
 */
export type NativeSelectProps = React.ComponentProps<'select'> & FieldErrorProps

function NativeSelect({
  className,
  children,
  error,
  describedBy,
  id,
  'aria-invalid': ariaInvalid,
  'aria-describedby': ariaDescribedBy,
  ...props
}: NativeSelectProps) {
  const field = useFieldAria({ id, error, describedBy, ariaInvalid, ariaDescribedBy })

  return (
    <>
      <select
        data-slot="native-select"
        id={field.fieldId}
        aria-invalid={field['aria-invalid']}
        aria-describedby={field['aria-describedby']}
        className={cn(fieldClassName, 'h-9 px-3', className)}
        {...props}
      >
        {children}
      </select>
      {error !== undefined && field.errorId !== undefined && (
        <FieldError id={field.errorId}>{error}</FieldError>
      )}
    </>
  )
}

export { NativeSelect }
