import { type ReactNode, useCallback, useEffect, useId, useRef } from 'react'
import { Button } from './Button'

const FOCUSABLE =
  'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'

export interface ModalProps {
  open: boolean
  title: string
  onClose: () => void
  children: ReactNode
}

/**
 * Boîte de dialogue modale accessible (CLAUDE.md §3.5) : `role="dialog"` +
 * `aria-modal`, titre relié par `aria-labelledby`, focus déplacé à l'ouverture
 * puis piégé (Tab / Shift+Tab cyclent), Échap ferme, et le focus revient à
 * l'élément déclencheur à la fermeture.
 */
export function Modal({ open, title, onClose, children }: ModalProps) {
  const titleId = useId()
  const dialogRef = useRef<HTMLDivElement>(null)
  const triggerRef = useRef<Element | null>(null)

  useEffect(() => {
    if (!open) {
      return
    }
    triggerRef.current = document.activeElement
    const first = dialogRef.current?.querySelector<HTMLElement>(FOCUSABLE)
    ;(first ?? dialogRef.current)?.focus()

    return () => {
      if (triggerRef.current instanceof HTMLElement) {
        triggerRef.current.focus()
      }
    }
  }, [open])

  const onKeyDown = useCallback(
    (event: React.KeyboardEvent<HTMLDivElement>) => {
      if (event.key === 'Escape') {
        event.stopPropagation()
        onClose()
        return
      }
      if (event.key !== 'Tab') {
        return
      }
      const focusable = Array.from(
        dialogRef.current?.querySelectorAll<HTMLElement>(FOCUSABLE) ?? [],
      )
      if (focusable.length === 0) {
        return
      }
      const first = focusable[0] as HTMLElement
      const last = focusable[focusable.length - 1] as HTMLElement
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault()
        last.focus()
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault()
        first.focus()
      }
    },
    [onClose],
  )

  if (!open) {
    return null
  }

  return (
    <div className="fixed inset-0 z-50 flex items-end justify-center bg-neutral-900/50 p-0 sm:items-center sm:p-4">
      {/* biome-ignore lint/a11y/noNoninteractiveElementInteractions: le conteneur
          `role="dialog"` DOIT porter le gestionnaire clavier — c'est le patron
          APG dialog standard (piège du focus Tab/Shift+Tab, Échap ferme) : il
          n'y a pas d'élément natif interactif équivalent pour une boîte de
          dialogue entière. */}
      <div
        ref={dialogRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby={titleId}
        tabIndex={-1}
        onKeyDown={onKeyDown}
        className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-t-xl border border-neutral-200 bg-white p-5 shadow-lg sm:rounded-xl dark:border-neutral-800 dark:bg-neutral-900"
      >
        <div className="mb-4 flex items-start justify-between gap-4">
          <h2 id={titleId} className="text-lg font-medium">
            {title}
          </h2>
          <Button variant="ghost" size="sm" onClick={onClose}>
            {/* Le nom accessible ne reprend PAS le titre de la modale : la boîte
                est déjà nommée par `aria-labelledby`, et concaténer le titre
                créait des collisions de nom (« Fermer la fenêtre Réserver… »
                vs le bouton « Réserver »). */}
            Fermer<span className="sr-only"> cette fenêtre</span>
          </Button>
        </div>
        {children}
      </div>
    </div>
  )
}
