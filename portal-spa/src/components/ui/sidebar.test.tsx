import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it } from 'vitest'
import { SidebarProvider, SidebarTrigger, useSidebar } from './sidebar'

const STORAGE_KEY = 'ecoworking.sidebar.open'

function SidebarState() {
  const { state } = useSidebar()

  return <output>{state}</output>
}

function renderSidebar() {
  const user = userEvent.setup()
  render(
    <SidebarProvider>
      <SidebarTrigger />
      <SidebarState />
    </SidebarProvider>,
  )

  return { user, state: () => screen.getByRole('status').textContent }
}

afterEach(() => {
  window.localStorage.clear()
})

describe('SidebarProvider — persistance de l’état réduit (D2)', () => {
  it('démarre déployé quand aucune préférence n’est stockée', () => {
    const { state } = renderSidebar()

    expect(state()).toBe('expanded')
  })

  it('relit la préférence stockée au montage', () => {
    window.localStorage.setItem(STORAGE_KEY, 'false')

    const { state } = renderSidebar()

    expect(state()).toBe('collapsed')
  })

  it('persiste le nouvel état au basculement', async () => {
    const { user, state } = renderSidebar()

    await user.click(screen.getByRole('button', { name: 'Afficher ou masquer le menu' }))

    expect(state()).toBe('collapsed')
    expect(window.localStorage.getItem(STORAGE_KEY)).toBe('false')
  })
})
