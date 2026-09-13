import type { Page } from '@playwright/test'
import { apiHeaders, loginViaApi, xsrfToken } from './support/auth'
import { expect, test } from './support/fixtures'
import { seed } from './support/seed'

/** Prochain jour ouvré à `offsetDays` d'aujourd'hui au moins (format YYYY-MM-DD). */
function nextWeekday(offsetDays: number): string {
  const date = new Date()
  date.setDate(date.getDate() + offsetDays)
  while (date.getDay() === 0 || date.getDay() === 6) {
    date.setDate(date.getDate() + 1)
  }
  const iso = date.toISOString()
  const day = iso.slice(0, 10)
  if (!day) {
    throw new Error('Date invalide')
  }
  return day
}

/**
 * Libellé de jour tel que la SPA le compose (`formatDayLabel`), calculé DANS le
 * navigateur pour partager exactement la même locale et le même fuseau.
 */
async function dayLabel(page: Page, date: string): Promise<string> {
  return page.evaluate(
    (day) =>
      new Date(`${day}T00:00:00`).toLocaleDateString('fr-FR', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
      }),
    date,
  )
}

/** Va à la semaine contenant `date` puis renvoie le libellé du jour. */
async function goToWeekOf(page: Page, date: string): Promise<string> {
  await page.getByLabel('Aller à la semaine du').fill(date)
  return dayLabel(page, date)
}

test.describe('Réservation de salle', () => {
  test.beforeEach(async ({ page }) => {
    await loginViaApi(page)
    await page.goto('/bookings')
    await expect(page.getByRole('heading', { level: 1, name: 'Réservations' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Calendrier des salles' })).toBeVisible()
  })

  test('réserver un créneau libre → confirmation + liste', async ({ page, checkA11y }) => {
    const date = nextWeekday(7)
    const label = await goToWeekOf(page, date)

    await page.getByRole('button', { name: `Réserver ${seed.rooms.small}, ${label} 09:00` }).click()

    const dialog = page.getByRole('dialog', { name: `Réserver ${seed.rooms.small}` })
    await expect(dialog).toBeVisible()
    await expect(dialog.getByLabel('Date')).toHaveValue(date)
    await dialog.getByLabel('Libellé (optionnel)').fill('Point équipe e2e')
    await dialog.getByRole('button', { name: 'Réserver', exact: true }).click()

    await expect(page.getByText('Réservation confirmée.')).toBeVisible()

    // La réservation apparaît dans « Mes prochaines réservations », confirmée.
    await expect(page.getByRole('heading', { name: 'Mes prochaines réservations' })).toBeVisible()
    const myBookings = page.getByRole('table', { name: 'Mes réservations de salle à venir' })
    await expect(myBookings.getByText('Confirmée').first()).toBeVisible()
    await expect(myBookings.getByText('Point équipe e2e')).toBeVisible()

    await checkA11y('bookings')
  })

  test('créneau pris entre-temps → erreur de conflit propre', async ({
    page,
    browser,
    baseURL,
  }) => {
    const date = nextWeekday(14)
    const label = await goToWeekOf(page, date)
    await expect(
      page.getByRole('button', { name: `Réserver ${seed.rooms.small}, ${label} 10:00` }),
    ).toBeVisible()

    // Le créneau 10:00–11:00 est réservé PAR UN AUTRE membre pendant que la
    // page affiche encore les disponibilités (état périmé) — même calcul de
    // dates que la SPA, exécuté dans le navigateur (fuseau Europe/Paris).
    const [startsAt, endsAt] = await page.evaluate(
      (day) =>
        [
          new Date(`${day}T10:00:00`).toISOString(),
          new Date(`${day}T11:00:00`).toISOString(),
        ] as const,
      date,
    )

    const otherContext = await browser.newContext({ baseURL })
    const otherPage = await otherContext.newPage()
    await loginViaApi(otherPage, seed.other.email)
    const roomsResponse = await otherPage.request.get('/api/rooms', {
      headers: apiHeaders(),
    })
    expect(roomsResponse.ok(), `GET /api/rooms → ${roomsResponse.status()}`).toBeTruthy()
    const rooms = ((await roomsResponse.json()) as { data: { id: number; name: string }[] }).data
    const room = rooms.find((candidate) => candidate.name === seed.rooms.small)
    if (!room) {
      throw new Error(`Salle « ${seed.rooms.small} » absente du seed`)
    }
    const conflicting = await otherPage.request.post('/api/bookings', {
      headers: apiHeaders({ 'X-XSRF-TOKEN': await xsrfToken(otherPage) }),
      data: { resource_id: room.id, starts_at: startsAt, ends_at: endsAt },
    })
    expect(conflicting.ok(), `résa concurrente → ${conflicting.status()}`).toBeTruthy()
    await otherContext.close()

    // Le clic sur le créneau périmé doit produire une erreur propre (409).
    await page.getByRole('button', { name: `Réserver ${seed.rooms.small}, ${label} 10:00` }).click()
    const dialog = page.getByRole('dialog')
    await dialog.getByRole('button', { name: 'Réserver', exact: true }).click()
    await expect(dialog.getByText('Ce créneau est déjà réservé pour cette salle.')).toBeVisible()
  })

  test('modifier puis supprimer sa réservation depuis le calendrier', async ({ page }) => {
    const date = nextWeekday(21)
    const label = await goToWeekOf(page, date)

    await page.getByRole('button', { name: `Réserver ${seed.rooms.small}, ${label} 14:00` }).click()
    const createDialog = page.getByRole('dialog', { name: `Réserver ${seed.rooms.small}` })
    await createDialog.getByLabel('Libellé (optionnel)').fill('Atelier e2e')
    await createDialog.getByRole('button', { name: 'Réserver', exact: true }).click()
    await expect(page.getByText('Réservation confirmée.')).toBeVisible()

    // Sa propre résa est mise en avant et ouvre la modale « Modifier / Supprimer ».
    await page
      .getByRole('button', { name: /Ma réservation.*modifier ou supprimer/ })
      .first()
      .click()
    const editDialog = page.getByRole('dialog', { name: `Ma réservation — ${seed.rooms.small}` })
    await expect(editDialog.getByLabel('Libellé (optionnel)')).toHaveValue('Atelier e2e')
    await editDialog.getByLabel('Libellé (optionnel)').fill('Atelier e2e modifié')
    await editDialog
      .getByRole('button', { name: 'Enregistrer les modifications', exact: true })
      .click()
    await expect(page.getByText('Réservation modifiée.')).toBeVisible()
    await expect(
      page
        .getByRole('table', { name: 'Mes réservations de salle à venir' })
        .getByText('Atelier e2e modifié'),
    ).toBeVisible()

    await page
      .getByRole('button', { name: /Ma réservation.*modifier ou supprimer/ })
      .first()
      .click()
    const deleteDialog = page.getByRole('dialog', { name: `Ma réservation — ${seed.rooms.small}` })
    await deleteDialog.getByRole('button', { name: 'Supprimer', exact: true }).click()
    await deleteDialog.getByRole('button', { name: 'Oui, supprimer', exact: true }).click()
    await expect(page.getByText('Réservation annulée.')).toBeVisible()
  })

  test('salle événementielle : lecture seule + invitation à nous contacter', async ({ page }) => {
    const date = nextWeekday(7)
    const label = await goToWeekOf(page, date)

    await page
      .getByRole('button', {
        name: `${seed.rooms.event}, ${label} 09:00 : réservation sur demande`,
      })
      .click()

    await expect(page.getByText('Pour réserver cette salle, contactez-nous.')).toBeVisible()
    await expect(page.getByRole('link', { name: 'Nous contacter' })).toHaveAttribute(
      'href',
      /mailto:contact@ecoworking\.fr/,
    )
    await expect(page.getByRole('dialog')).toHaveCount(0)
  })

  test('historique : onglet dédié aux réservations passées', async ({ page }) => {
    await page.getByRole('button', { name: 'Historique' }).click()

    await expect(
      page.getByRole('heading', { name: 'Historique de mes réservations' }),
    ).toBeVisible()
  })
})
