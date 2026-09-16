import { useRef, useState } from 'react'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from '@/components/ui/alert-dialog'
import { Button, type ButtonProps } from '@/components/ui/button'

export interface ConfirmButtonProps extends Omit<ButtonProps, 'onClick' | 'asChild'> {
  /** Question posée avant d'exécuter l'action destructrice. */
  confirmMessage: string
  confirmLabel?: string
  cancelLabel?: string
  onConfirm: () => void
}

/**
 * Confirmation accessible pour les actions destructrices (CLAUDE.md §3.5 — pas
 * de `window.confirm`), construite sur `AlertDialog` (Radix) : la question est
 * le titre de la boîte, le focus y est piégé puis rendu au déclencheur, Échap
 * et le bouton d'annulation ferment sans exécuter l'action.
 *
 * L'API (`confirmMessage` / `confirmLabel` / `cancelLabel` / `onConfirm` + les
 * props de `Button`) est celle du composant maison remplacé, pour laisser les
 * appelants inchangés.
 */
export function ConfirmButton({
  confirmMessage,
  confirmLabel = 'Confirmer',
  cancelLabel = 'Annuler',
  onConfirm,
  children,
  variant,
  size,
  ...buttonProps
}: ConfirmButtonProps) {
  const [open, setOpen] = useState(false)
  const triggerRef = useRef<HTMLButtonElement>(null)
  const confirmed = useRef(false)

  return (
    <AlertDialog
      open={open}
      onOpenChange={(next) => {
        if (next) {
          // Ceinture-bretelles : une réouverture ne doit jamais hériter d'une
          // intention de confirmation laissée par un cycle précédent.
          confirmed.current = false
        }
        setOpen(next)
      }}
    >
      <AlertDialogTrigger asChild>
        <Button ref={triggerRef} variant={variant} size={size} {...buttonProps}>
          {children}
        </Button>
      </AlertDialogTrigger>
      {/* Pas de `AlertDialogDescription` : le titre porte déjà la question
          complète, un `aria-describedby` pendouillant déclencherait un
          avertissement Radix et une annonce vide au lecteur d'écran. */}
      <AlertDialogContent
        aria-describedby={undefined}
        onCloseAutoFocus={(event) => {
          if (!confirmed.current) {
            return // annulation / Échap : restauration du focus par Radix.
          }
          confirmed.current = false
          // L'action est déclenchée ICI, après la fermeture, pour que l'ordre
          // soit déterministe : Radix rend d'abord le focus au déclencheur,
          // puis `onConfirm` peut le déplacer ailleurs (plusieurs appelants
          // reportent le focus sur un titre de section quand la ligne
          // supprimée disparaît de la liste). Sans ça, la restauration
          // asynchrone de Radix écrasait ce déplacement.
          event.preventDefault()
          triggerRef.current?.focus()
          onConfirm()
        }}
      >
        <AlertDialogHeader>
          <AlertDialogTitle>{confirmMessage}</AlertDialogTitle>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>{cancelLabel}</AlertDialogCancel>
          <AlertDialogAction
            variant={variant === 'destructive' ? 'destructive' : 'default'}
            onClick={() => {
              confirmed.current = true
            }}
          >
            {confirmLabel}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  )
}
