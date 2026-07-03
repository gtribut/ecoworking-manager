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

test.describe('Réservation de salle', () => {
  test.beforeEach(async ({ page }) => {
    await loginViaApi(page)
    await page.goto('/bookings')
    await expect(page.getByRole('heading', { level: 1, name: 'Réservations' })).toBeVisible()
  })

  test('réserver un créneau libre → confirmation + liste', async ({ page, checkA11y }) => {
    const date = nextWeekday(7)

    await page.getByLabel('Salle', { exact: true }).selectOption({ index: 1 })
    await page.getByLabel('Date', { exact: true }).fill(date)

    await expect(page.getByRole('heading', { name: /Créneaux du/ })).toBeVisible()
    await page.getByRole('button', { name: 'Réserver le créneau 09:00 – 10:00' }).click()

    await expect(page.getByText('Réservation confirmée.')).toBeVisible()

    // La réservation apparaît dans « Mes réservations », confirmée.
    await expect(page.getByRole('heading', { name: 'Mes réservations' })).toBeVisible()
    await expect(page.getByRole('table').getByText('Confirmée').first()).toBeVisible()

    await checkA11y('bookings')
  })

  test('créneau pris entre-temps → erreur de conflit propre', async ({
    page,
    browser,
    baseURL,
  }) => {
    const date = nextWeekday(14)

    await page.getByLabel('Salle', { exact: true }).selectOption({ index: 1 })
    await page.getByLabel('Date', { exact: true }).fill(date)
    await expect(page.getByRole('heading', { name: /Créneaux du/ })).toBeVisible()

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
    await page.getByRole('button', { name: 'Réserver le créneau 10:00 – 11:00' }).click()
    await expect(page.getByText('Ce créneau est déjà réservé pour cette salle.')).toBeVisible()
  })
})
