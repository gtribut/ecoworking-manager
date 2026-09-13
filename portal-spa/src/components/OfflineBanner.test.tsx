import { act, render, screen } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { OfflineBanner } from './OfflineBanner'

function setOnline(value: boolean) {
  Object.defineProperty(window.navigator, 'onLine', { configurable: true, value })
}

describe('OfflineBanner', () => {
  const original = window.navigator.onLine

  beforeEach(() => setOnline(true))
  afterEach(() => setOnline(original))

  it('ne s’affiche pas quand la connexion est disponible', () => {
    render(<OfflineBanner />)
    expect(screen.queryByRole('status')).not.toBeInTheDocument()
  })

  it('apparaît sur l’événement offline et disparaît sur online', () => {
    render(<OfflineBanner />)

    act(() => {
      setOnline(false)
      window.dispatchEvent(new Event('offline'))
    })
    expect(
      screen.getByText('Vous êtes hors-ligne, certaines données peuvent ne pas être à jour.'),
    ).toBeInTheDocument()

    act(() => {
      setOnline(true)
      window.dispatchEvent(new Event('online'))
    })
    expect(screen.queryByRole('status')).not.toBeInTheDocument()
  })
})
