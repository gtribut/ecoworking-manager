import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { NativeSelect } from './native-select'

describe('NativeSelect', () => {
  it('avec une erreur : aria-invalid, message relié via aria-describedby', () => {
    render(
      <NativeSelect id="period" aria-label="Période" error="Choisissez une période.">
        <option value="am">Matin</option>
      </NativeSelect>,
    )
    const select = screen.getByLabelText('Période')

    expect(select).toHaveAttribute('aria-invalid', 'true')
    const message = screen.getByText('Choisissez une période.')
    expect(select.getAttribute('aria-describedby')).toContain(message.id)
  })
})
