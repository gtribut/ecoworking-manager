import { act, render, screen } from '@testing-library/react'
import { Toaster, toast } from 'sonner'
import { describe, expect, it } from 'vitest'
import { TOAST_CLASS_NAMES, TOAST_CONTAINER_ARIA_LABEL } from './toastTheme'

function renderToaster() {
  return render(
    <Toaster
      containerAriaLabel={TOAST_CONTAINER_ARIA_LABEL}
      toastOptions={{ classNames: TOAST_CLASS_NAMES }}
    />,
  )
}

describe('Thème des toasts (fix review Playwright — lot G)', () => {
  it('porte un aria-label de région distinct de la cloche « Notifications »', () => {
    renderToaster()

    // Sonner ajoute son raccourci clavier au libellé fourni (ex. « … alt+T ») :
    // on vérifie le préfixe, pas l'égalité stricte. Le point important est
    // qu'aucune région ne porte (même en préfixe) le libellé de la cloche
    // « Notifications » — la collision qui faisait échouer l'audit e2e.
    const region = screen.getByLabelText(new RegExp(`^${TOAST_CONTAINER_ARIA_LABEL}`))
    expect(region).toBeInTheDocument()
    expect(screen.queryByLabelText(/^Notifications(\s|$)/)).not.toBeInTheDocument()
  })

  it('applique les classes AA de succès (pas les couleurs richColors par défaut)', async () => {
    renderToaster()

    act(() => {
      toast.success('Réservation confirmée.')
    })

    const message = await screen.findByText('Réservation confirmée.')
    const toastEl = message.closest('[data-sonner-toast]')
    expect(toastEl).not.toBeNull()
    for (const className of TOAST_CLASS_NAMES.success.split(' ')) {
      expect(toastEl).toHaveClass(className)
    }
  })

  it('applique les classes AA d’erreur', async () => {
    renderToaster()

    act(() => {
      toast.error('Réservation impossible.')
    })

    const message = await screen.findByText('Réservation impossible.')
    const toastEl = message.closest('[data-sonner-toast]')
    expect(toastEl).not.toBeNull()
    for (const className of TOAST_CLASS_NAMES.error.split(' ')) {
      expect(toastEl).toHaveClass(className)
    }
  })
})
