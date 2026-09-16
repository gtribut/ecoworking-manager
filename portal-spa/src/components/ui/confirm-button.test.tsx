import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { describe, expect, it, vi } from 'vitest'
import { ConfirmButton } from './confirm-button'

function renderConfirmButton(onConfirm = vi.fn()) {
  const user = userEvent.setup()
  render(
    <ConfirmButton
      variant="destructive"
      confirmMessage="Supprimer cette absence ?"
      confirmLabel="Oui, supprimer"
      cancelLabel="Non"
      onConfirm={onConfirm}
    >
      Supprimer
    </ConfirmButton>,
  )

  return { user, onConfirm, trigger: screen.getByRole('button', { name: 'Supprimer' }) }
}

describe('ConfirmButton', () => {
  it('ouvre une boîte de dialogue nommée par la question', async () => {
    const { user, trigger } = renderConfirmButton()

    await user.click(trigger)

    const dialog = await screen.findByRole('alertdialog', { name: 'Supprimer cette absence ?' })
    expect(dialog).toBeVisible()
  })

  it('exécute l’action et referme au clic sur la confirmation', async () => {
    const { user, onConfirm, trigger } = renderConfirmButton()

    await user.click(trigger)
    await user.click(await screen.findByRole('button', { name: 'Oui, supprimer' }))

    await waitFor(() => expect(onConfirm).toHaveBeenCalledTimes(1))
    await waitFor(() => expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument())
  })

  it('rend le focus au déclencheur AVANT d’exécuter l’action', async () => {
    // Plusieurs appelants déplacent le focus depuis `onConfirm` (report sur un
    // titre de section quand la ligne supprimée disparaît de la liste) : la
    // restauration de focus de Radix doit donc être déjà faite à cet instant,
    // sinon elle écraserait leur déplacement.
    let focusedOnConfirm: Element | null = null
    const onConfirm = vi.fn(() => {
      focusedOnConfirm = document.activeElement
    })
    const { user, trigger } = renderConfirmButton(onConfirm)

    await user.click(trigger)
    await user.click(await screen.findByRole('button', { name: 'Oui, supprimer' }))

    await waitFor(() => expect(onConfirm).toHaveBeenCalledTimes(1))
    expect(focusedOnConfirm).toBe(trigger)
  })

  it('n’exécute rien au clic sur l’annulation et rend le focus au déclencheur', async () => {
    const { user, onConfirm, trigger } = renderConfirmButton()

    await user.click(trigger)
    await user.click(await screen.findByRole('button', { name: 'Non' }))

    await waitFor(() => expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument())
    expect(onConfirm).not.toHaveBeenCalled()
    await waitFor(() => expect(trigger).toHaveFocus())
  })

  it('n’exécute rien à la touche Échap', async () => {
    const { user, onConfirm, trigger } = renderConfirmButton()

    await user.click(trigger)
    await screen.findByRole('alertdialog')
    await user.keyboard('{Escape}')

    await waitFor(() => expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument())
    expect(onConfirm).not.toHaveBeenCalled()
  })
})
