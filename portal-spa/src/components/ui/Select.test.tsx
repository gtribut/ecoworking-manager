import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { Select } from './Select'

describe('Select', () => {
  it('avec une erreur : aria-invalid, message relié via aria-describedby', () => {
    render(
      <Select id="period" aria-label="Période" error="Choisissez une période.">
        <option value="am">Matin</option>
      </Select>,
    )
    const select = screen.getByLabelText('Période')

    expect(select).toHaveAttribute('aria-invalid', 'true')
    const message = screen.getByText('Choisissez une période.')
    expect(select.getAttribute('aria-describedby')).toContain(message.id)
  })
})
