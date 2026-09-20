import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { useRef, useState } from 'react'
import { MemoryRouter } from 'react-router'
import { describe, expect, it } from 'vitest'
import { DeskDetailPanel } from './DeskDetailPanel'
import type { PlanDesk } from './types'

const desk: PlanDesk = {
  resource_id: 30,
  svg_desk_id: 'desk-30',
  name: 'Bureau 30',
  floor: 2,
  assignment: 'unassigned',
  is_own: false,
  status: 'free',
  present_period: null,
  occupant: null,
}

/**
 * Harnais minimal reproduisant le câblage réel de `FloorPlanPage` (ouverture
 * programmatique, pas de `SheetTrigger`) mais avec un déclencheur `<button>`
 * ordinaire plutôt qu'un bloc SVG : jsdom ne supporte pas `focus()` sur les
 * éléments SVG (limitation de l'environnement de test, pas de l'app — en
 * navigateur réel, les blocs `[data-desk]` sont focusables, cf. les règles
 * `:focus-visible` de `styles.css`), donc le retour de focus se vérifie ici
 * sur un élément HTML natif.
 */
function Harness() {
  const [open, setOpen] = useState(false)
  const openerRef = useRef<HTMLElement | null>(null)

  return (
    <MemoryRouter>
      <button
        type="button"
        onClick={(event) => {
          openerRef.current = event.currentTarget
          setOpen(true)
        }}
      >
        Ouvrir le bureau 30
      </button>
      <DeskDetailPanel
        desk={open ? desk : null}
        onClose={() => setOpen(false)}
        returnFocusTo={openerRef.current}
      />
    </MemoryRouter>
  )
}

describe('DeskDetailPanel', () => {
  it('ouvre le Sheet avec le nom du bureau et son statut', async () => {
    const user = userEvent.setup()
    render(<Harness />)

    await user.click(screen.getByRole('button', { name: 'Ouvrir le bureau 30' }))

    expect(await screen.findByRole('heading', { name: 'Bureau 30' })).toBeInTheDocument()
    expect(screen.getByText('Statut : libre')).toBeInTheDocument()
    expect(screen.getByText(/Bureau libre — pour réserver ce type de bureau/)).toBeInTheDocument()
  })

  it('rend le focus au bouton ouvreur à la fermeture (bouton « Fermer »)', async () => {
    const user = userEvent.setup()
    render(<Harness />)

    const opener = screen.getByRole('button', { name: 'Ouvrir le bureau 30' })
    await user.click(opener)
    await screen.findByRole('heading', { name: 'Bureau 30' })

    await user.click(screen.getByRole('button', { name: 'Fermer cette fenêtre' }))

    expect(screen.queryByRole('heading', { name: 'Bureau 30' })).not.toBeInTheDocument()
    await waitFor(() => expect(opener).toHaveFocus())
  })

  it('rend le focus au bouton ouvreur à la fermeture (Échap)', async () => {
    const user = userEvent.setup()
    render(<Harness />)

    const opener = screen.getByRole('button', { name: 'Ouvrir le bureau 30' })
    await user.click(opener)
    await screen.findByRole('heading', { name: 'Bureau 30' })

    await user.keyboard('{Escape}')

    expect(screen.queryByRole('heading', { name: 'Bureau 30' })).not.toBeInTheDocument()
    await waitFor(() => expect(opener).toHaveFocus())
  })
})
