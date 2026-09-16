import type * as React from 'react'
import {
  FieldError,
  type FieldErrorProps,
  fieldClassName,
  useFieldAria,
} from '@/components/ui/input'
import { cn } from '@/lib/utils'

export type TextareaProps = React.ComponentProps<'textarea'> & FieldErrorProps

function Textarea({
  className,
  error,
  describedBy,
  id,
  'aria-invalid': ariaInvalid,
  'aria-describedby': ariaDescribedBy,
  ...props
}: TextareaProps) {
  const field = useFieldAria({ id, error, describedBy, ariaInvalid, ariaDescribedBy })

  return (
    <>
      <textarea
        data-slot="textarea"
        id={field.fieldId}
        aria-invalid={field['aria-invalid']}
        aria-describedby={field['aria-describedby']}
        className={cn(fieldClassName, 'flex field-sizing-content min-h-20 px-3 py-2', className)}
        {...props}
      />
      {error !== undefined && field.errorId !== undefined && (
        <FieldError id={field.errorId}>{error}</FieldError>
      )}
    </>
  )
}

export { Textarea }
