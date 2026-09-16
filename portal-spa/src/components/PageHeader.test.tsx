import { render, screen } from '@testing-library/react'
import { useState } from 'react'
import { describe, expect, it } from 'vitest'
import { PageHeader, PageHeaderSlotContext } from './PageHeader'

/** Reproduit le shell : un emplacement dans la « top bar » + le contenu. */
function Shell({ children }: { children: React.ReactNode }) {
  const [slot, setSlot] = useState<HTMLElement | null>(null)

  return (
    <PageHeaderSlotContext.Provider value={{ element: slot }}>
      <header data-testid="topbar" ref={setSlot} />
      <main>{children}</main>
    </PageHeaderSlotContext.Provider>
  )
}

describe('PageHeader', () => {
  it('rend le titre en <h1> sur place hors du shell (page isolée, pages légales)', () => {
    render(<PageHeader title="Mes factures" />)

    const headings = screen.getAllByRole('heading', { level: 1 })
    expect(headings).toHaveLength(1)
    expect(headings[0]).toHaveTextContent('Mes factures')
  })

  it('remonte le titre dans la top bar du shell, sans le dupliquer', () => {
    render(
      <Shell>
        <PageHeader title="Réservations de salles" description="Semaine en cours" />
      </Shell>,
    )

    const headings = screen.getAllByRole('heading', { level: 1 })
    expect(headings).toHaveLength(1)
    expect(screen.getByTestId('topbar')).toContainElement(headings[0] ?? null)
    expect(screen.getByText('Semaine en cours')).toBeInTheDocument()
  })

  it('affiche les actions de la page à côté du titre', () => {
    render(
      <Shell>
        <PageHeader title="Documents" actions={<button type="button">Ajouter</button>} />
      </Shell>,
    )

    expect(screen.getByRole('button', { name: 'Ajouter' })).toBeInTheDocument()
  })
})
