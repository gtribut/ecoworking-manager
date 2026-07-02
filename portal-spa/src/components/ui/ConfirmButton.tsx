import { useEffect, useId, useRef, useState } from 'react'
import { Button, type ButtonProps } from './Button'

export interface ConfirmButtonProps extends Omit<ButtonProps, 'onClick' | 'ref'> {
  /** Question posée avant d'exécuter l'action destructrice. */
  confirmMessage: string
  confirmLabel?: string
  cancelLabel?: string
  onConfirm: () => void
}

/**
 * Confirmation accessible en deux temps pour les actions destructrices
 * (CLAUDE.md §3.5 — pas de window.confirm) : le premier clic remplace le bouton
 * par la question + « Confirmer / Annuler ». Le focus est déplacé sur le bouton
 * de confirmation à l'ouverture et rendu au déclencheur à la fermeture ;
 * Échap annule.
 */
export function ConfirmButton({
  confirmMessage,
  confirmLabel = 'Confirmer',
  cancelLabel = 'Annuler',
  onConfirm,
  children,
  ...buttonProps
}: ConfirmButtonProps) {
  const [confirming, setConfirming] = useState(false)
  const messageId = useId()
  const confirmRef = useRef<HTMLButtonElement>(null)
  const triggerRef = useRef<HTMLButtonElement>(null)
  const wasConfirming = useRef(false)

  useEffect(() => {
    if (confirming) {
      confirmRef.current?.focus()
    } else if (wasConfirming.current) {
      triggerRef.current?.focus()
    }
    wasConfirming.current = confirming
  }, [confirming])

  if (!confirming) {
    return (
      <Button {...buttonProps} ref={triggerRef} onClick={() => setConfirming(true)}>
        {children}
      </Button>
    )
  }

  // Échap annule la confirmation ; l'écouteur vit sur les boutons (éléments
  // focusés), pas sur le conteneur statique (a11y/noStaticElementInteractions).
  const cancelOnEscape = (event: React.KeyboardEvent<HTMLButtonElement>) => {
    if (event.key === 'Escape') {
      event.stopPropagation()
      setConfirming(false)
    }
  }

  return (
    <span className="inline-flex flex-wrap items-center justify-end gap-2">
      <span id={messageId} className="text-sm">
        {confirmMessage}
      </span>
      <Button
        ref={confirmRef}
        aria-describedby={messageId}
        variant={buttonProps.variant}
        size={buttonProps.size}
        disabled={buttonProps.disabled}
        onKeyDown={cancelOnEscape}
        onClick={() => {
          setConfirming(false)
          onConfirm()
        }}
      >
        {confirmLabel}
      </Button>
      <Button
        aria-describedby={messageId}
        variant="secondary"
        size={buttonProps.size}
        onKeyDown={cancelOnEscape}
        onClick={() => setConfirming(false)}
      >
        {cancelLabel}
      </Button>
    </span>
  )
}
