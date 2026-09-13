import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { EntityBlock, EntityBlocks } from './EntityBlock'
import type { BillingEntity } from './types'

function makeEntity(overrides: Partial<BillingEntity> = {}): BillingEntity {
  return {
    id: 1,
    entity_type: 'company',
    name: 'Acme SCOP',
    legal_name: 'Acme SCOP',
    legal_form: 'SCOP',
    siret: '12345678901234',
    vat_number: 'FR12345678901',
    billing_email: 'fact@acme.fr',
    address: {
      line1: '10 rue du Lac',
      line2: 'Bâtiment B',
      postal_code: '69003',
      city: 'Lyon',
      country: 'FR',
    },
    ...overrides,
  }
}

describe('EntityBlock', () => {
  it('affiche l’entité complète, adresse (line2 + pays) et données de facturation', () => {
    render(
      <EntityBlock
        entity={makeEntity({
          payment_method: 'sepa',
          payment_method_label: 'Prélèvement SEPA',
          iban_last4: '1234',
        })}
      />,
    )

    expect(screen.getByRole('heading', { level: 2, name: 'Mon entreprise' })).toBeInTheDocument()
    expect(screen.getByText('Acme SCOP')).toBeInTheDocument()
    expect(screen.getByText('SCOP')).toBeInTheDocument()
    expect(screen.getByText('12345678901234')).toBeInTheDocument()
    expect(screen.getByText('FR12345678901')).toBeInTheDocument()
    expect(screen.getByText('fact@acme.fr')).toBeInTheDocument()
    expect(screen.getByText('10 rue du Lac')).toBeInTheDocument()
    expect(screen.getByText('Bâtiment B')).toBeInTheDocument()
    expect(screen.getByText('69003 Lyon')).toBeInTheDocument()
    expect(screen.getByText('France')).toBeInTheDocument()
    expect(screen.getByText('Prélèvement SEPA')).toBeInTheDocument()
    expect(screen.getByText('•••• 1234')).toBeInTheDocument()
  })

  it('titre « Mes données de facturation » pour un particulier', () => {
    render(
      <EntityBlock
        entity={makeEntity({
          entity_type: 'individual',
          name: 'Camille Durand',
          legal_name: null,
          legal_form: null,
          siret: null,
          vat_number: null,
        })}
      />,
    )

    expect(
      screen.getByRole('heading', { level: 2, name: 'Mes données de facturation' }),
    ).toBeInTheDocument()
    expect(screen.getByText('Nom')).toBeInTheDocument()
    expect(screen.getByText('Camille Durand')).toBeInTheDocument()
    expect(screen.queryByText('SIRET')).not.toBeInTheDocument()
  })

  it('n’affiche ni IBAN ni mode de paiement quand l’API ne les renvoie pas', () => {
    render(<EntityBlock entity={makeEntity()} />)

    expect(screen.queryByText('IBAN')).not.toBeInTheDocument()
    expect(screen.queryByText('Mode de paiement')).not.toBeInTheDocument()
  })

  it('propose un lien de demande de modification', () => {
    render(<EntityBlock entity={makeEntity()} />)

    expect(screen.getByRole('link', { name: 'Demander une modification' })).toHaveAttribute(
      'href',
      'mailto:contact@ecoworking.fr?subject=[backend ecowo] Demande de modification',
    )
  })

  it('rend un bloc par entité en multi-entités, et rien sans entité', () => {
    const { rerender } = render(
      <EntityBlocks
        entities={[
          makeEntity({ id: 1, legal_name: 'Alpha SAS' }),
          makeEntity({ id: 2, legal_name: 'Beta SARL' }),
        ]}
      />,
    )

    expect(screen.getAllByRole('heading', { level: 2, name: 'Mon entreprise' })).toHaveLength(2)
    expect(screen.getByText('Alpha SAS')).toBeInTheDocument()
    expect(screen.getByText('Beta SARL')).toBeInTheDocument()

    rerender(<EntityBlocks entities={[]} />)
    expect(screen.queryByRole('heading', { level: 2 })).not.toBeInTheDocument()
  })
})
