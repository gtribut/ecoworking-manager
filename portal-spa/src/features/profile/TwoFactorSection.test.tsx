import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'
import type { AuthUser } from '@/features/auth/types'
import { server } from '@/test/server'
import { renderWithProviders } from '@/test/utils'
import { TwoFactorSection } from './TwoFactorSection'

function member(twoFactorEnabled: boolean): AuthUser {
  return {
    id: 1,
    first_name: 'Claire',
    last_name: 'Fontaine',
    email: 'claire@example.test',
    theme: null,
    has_desk: false,
    two_factor_enabled: twoFactorEnabled,
    roles: ['resident'],
    permissions: [],
  }
}

describe('TwoFactorSection (R-04, PRD §3.2 2FA optionnelle)', () => {
  it('active la 2FA : confirmation du mot de passe (423), QR code, code TOTP, codes de récupération', async () => {
    const user = userEvent.setup()
    let enabled = false
    let passwordConfirmed = false
    let confirmedCode: string | null = null

    server.use(
      http.get('/api/user', () => HttpResponse.json(member(enabled))),
      http.post('/user/two-factor-authentication', () =>
        passwordConfirmed
          ? new HttpResponse(null, { status: 200 })
          : new HttpResponse(null, { status: 423 }),
      ),
      http.post('/user/confirm-password', async ({ request }) => {
        const body = (await request.json()) as { password: string }
        passwordConfirmed = body.password === 'demo-password'
        return passwordConfirmed
          ? new HttpResponse(null, { status: 201 })
          : HttpResponse.json({ message: 'Mot de passe incorrect.' }, { status: 422 })
      }),
      http.get('/user/two-factor-qr-code', () =>
        HttpResponse.json({
          svg: '<svg xmlns="http://www.w3.org/2000/svg"></svg>',
          url: 'otpauth://x',
        }),
      ),
      http.get('/user/two-factor-secret-key', () =>
        HttpResponse.json({ secretKey: 'ABCDEF123456' }),
      ),
      http.post('/user/confirmed-two-factor-authentication', async ({ request }) => {
        confirmedCode = ((await request.json()) as { code: string }).code
        enabled = true
        return new HttpResponse(null, { status: 200 })
      }),
      http.get('/user/two-factor-recovery-codes', () =>
        HttpResponse.json(['aaaa-bbbb', 'cccc-dddd']),
      ),
    )

    renderWithProviders(<TwoFactorSection />, { withAuth: true })

    expect(await screen.findByText('Désactivée')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Activer la double authentification' }))

    // 423 → confirmation du mot de passe, puis l'action est rejouée.
    await user.type(await screen.findByLabelText('Mot de passe actuel'), 'demo-password')
    await user.click(screen.getByRole('button', { name: 'Confirmer' }))

    expect(
      await screen.findByRole('img', {
        name: 'QR code d’activation de la double authentification',
      }),
    ).toBeInTheDocument()
    expect(screen.getByText('ABCDEF123456')).toBeInTheDocument()

    await user.type(screen.getByLabelText(/saisissez le code affiché/i), '123456')
    await user.click(screen.getByRole('button', { name: 'Activer' }))

    expect(await screen.findByText('Double authentification activée.')).toBeInTheDocument()
    expect(screen.getByText('aaaa-bbbb')).toBeInTheDocument()
    expect(screen.getByText('cccc-dddd')).toBeInTheDocument()
    expect(confirmedCode).toBe('123456')
  })

  it('affiche l’erreur d’un code TOTP refusé (422) et reste sur l’étape de saisie', async () => {
    const user = userEvent.setup()
    server.use(
      http.get('/api/user', () => HttpResponse.json(member(false))),
      http.post('/user/two-factor-authentication', () => new HttpResponse(null, { status: 200 })),
      http.get('/user/two-factor-qr-code', () =>
        HttpResponse.json({ svg: '<svg/>', url: 'otpauth://x' }),
      ),
      http.get('/user/two-factor-secret-key', () => HttpResponse.json({ secretKey: 'KEY' })),
      http.post('/user/confirmed-two-factor-authentication', () =>
        HttpResponse.json({ message: 'Le code fourni est invalide.' }, { status: 422 }),
      ),
    )

    renderWithProviders(<TwoFactorSection />, { withAuth: true })

    await user.click(
      await screen.findByRole('button', { name: 'Activer la double authentification' }),
    )
    await user.type(await screen.findByLabelText(/saisissez le code affiché/i), '000000')
    await user.click(screen.getByRole('button', { name: 'Activer' }))

    expect(await screen.findByText('Le code fourni est invalide.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Activer' })).toBeInTheDocument()
  })

  it('désactive la 2FA après confirmation en deux temps', async () => {
    const user = userEvent.setup()
    let enabled = true
    server.use(
      http.get('/api/user', () => HttpResponse.json(member(enabled))),
      http.delete('/user/two-factor-authentication', () => {
        enabled = false
        return new HttpResponse(null, { status: 200 })
      }),
    )

    renderWithProviders(<TwoFactorSection />, { withAuth: true })

    expect(await screen.findByText('Activée')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Désactiver' }))
    await user.click(screen.getByRole('button', { name: 'Confirmer' }))

    expect(await screen.findByText('Désactivée')).toBeInTheDocument()
  })
})
