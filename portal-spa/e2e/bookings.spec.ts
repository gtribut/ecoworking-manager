import type { Locator, Page } from '@playwright/test'
import { apiHeaders, loginViaApi, xsrfToken } from './support/auth'
import { expect, FULLCALENDAR_AXE_EXCLUDE, test } from './support/fixtures'
import { seed } from './support/seed'

/**
 * Depuis C14 (ADR-0013 D3) la grille est rendue par FullCalendar : elle n'est
 * ni navigable au clavier ni auditée par axe. Les parcours e2e passent donc par
 * l'**alternative accessible** de la même page — bouton « Nouvelle
 * réservation » (saisie manuelle salle / date / heures) et liste
 * « Mes prochaines réservations » — qui est aussi le chemin que doit pouvoir
 * emprunter un membre au lecteur d'écran.
 */

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
 * Choisit une salle dans le `<select>` de la modale. L'option porte le nom de
 * la salle suivi de sa capacité : on lit sa `value` (l'id de la ressource)
 * plutôt que de coder le libellé complet dans le test.
 */
async function selectRoom(dialog: Locator, roomName: string): Promise<void> {
  const option = dialog.getByRole('option', { name: new RegExp(`^${roomName}`) })
  const value = await option.getAttribute('value')
  if (value === null) {
    throw new Error(`Salle « ${roomName} » absente du sélecteur de la modale`)
  }
  await dialog.getByLabel('Salle', { exact: true }).selectOption(value)
}

/** Ligne de « Mes prochaines réservations » portant ce libellé. */
function bookingRow(table: Locator, label: string): Locator {
  return table.getByRole('row').filter({ hasText: label })
}

/** Remplit la modale de création : salle, date, créneau personnalisé, libellé. */
async function fillBooking(
  page: Page,
  options: { room: string; date: string; start: string; end: string; title?: string },
): Promise<Locator> {
  await page.getByRole('button', { name: 'Nouvelle réservation' }).click()
  const dialog = page.getByRole('dialog', { name: 'Nouvelle réservation' })
  await expect(dialog).toBeVisible()

  await selectRoom(dialog, options.room)
  await dialog.getByLabel('Date').fill(options.date)
  await dialog.getByLabel('Créneau personnalisé').check()
  await dialog.getByLabel('Heure de début').fill(options.start)
  await dialog.getByLabel('Heure de fin').fill(options.end)
  if (options.title !== undefined) {
    await dialog.getByLabel('Libellé (optionnel)').fill(options.title)
  }
  return dialog
}

test.describe('Réservation de salle', () => {
  test.beforeEach(async ({ page }) => {
    await loginViaApi(page)
    await page.goto('/bookings')
    await expect(
      page.getByRole('heading', { level: 1, name: 'Réservations de salles' }),
    ).toBeVisible()
    // Barre d'outils de l'agenda (FullCalendar) : période affichée + action.
    await expect(page.getByRole('button', { name: 'Nouvelle réservation' })).toBeVisible()
    await expect(page.getByRole('radio', { name: 'Semaine' })).toBeVisible()
  })

  test('réserver un créneau → confirmation + liste', async ({ page, checkA11y }) => {
    const date = nextWeekday(7)

    const dialog = await fillBooking(page, {
      room: seed.rooms.small,
      date,
      start: '09:00',
      end: '10:00',
      title: 'Point équipe e2e',
    })
    await dialog.getByRole('button', { name: 'Réserver', exact: true }).click()

    await expect(page.getByText('Réservation confirmée.').first()).toBeVisible()

    // La réservation apparaît dans « Mes prochaines réservations », confirmée.
    await expect(page.getByRole('heading', { name: 'Mes prochaines réservations' })).toBeVisible()
    const myBookings = page.getByRole('table', { name: 'Mes réservations de salle à venir' })
    await expect(myBookings.getByText('Confirmée').first()).toBeVisible()
    await expect(myBookings.getByText('Point équipe e2e')).toBeVisible()

    // La grille FullCalendar est la seule zone exclue de l'audit (ADR-0013 D4).
    await checkA11y('bookings', { exclude: FULLCALENDAR_AXE_EXCLUDE })
  })

  test('créneau pris entre-temps → erreur de conflit propre', async ({
    page,
    browser,
    baseURL,
  }) => {
    const date = nextWeekday(14)

    // Le créneau 10:00–11:00 est réservé PAR UN AUTRE membre — même calcul de
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

    // La demande du même créneau doit produire une erreur propre (409).
    const dialog = await fillBooking(page, {
      room: seed.rooms.small,
      date,
      start: '10:00',
      end: '11:00',
    })
    await dialog.getByRole('button', { name: 'Réserver', exact: true }).click()

    await expect(dialog.getByText('Ce créneau est déjà réservé pour cette salle.')).toBeVisible()
  })

  test('modifier puis supprimer sa réservation depuis la liste', async ({ page }) => {
    const date = nextWeekday(21)

    const createDialog = await fillBooking(page, {
      room: seed.rooms.small,
      date,
      start: '14:00',
      end: '15:00',
      title: 'Atelier e2e',
    })
    await createDialog.getByRole('button', { name: 'Réserver', exact: true }).click()
    await expect(page.getByText('Réservation confirmée.').first()).toBeVisible()

    const myBookings = page.getByRole('table', { name: 'Mes réservations de salle à venir' })
    await expect(myBookings.getByText('Atelier e2e')).toBeVisible()

    // « Modifier » depuis la liste : c'est l'alternative accessible au popover
    // de la grille (ADR-0013 D4). La liste est triée par date et contient les
    // résas des tests précédents : on cible la LIGNE, jamais le premier bouton.
    await bookingRow(myBookings, 'Atelier e2e')
      .getByRole('button', { name: /Modifier/ })
      .click()
    const editDialog = page.getByRole('dialog', { name: `Ma réservation — ${seed.rooms.small}` })
    await expect(editDialog.getByLabel('Libellé (optionnel)')).toHaveValue('Atelier e2e')
    await editDialog.getByLabel('Libellé (optionnel)').fill('Atelier e2e modifié')
    await editDialog
      .getByRole('button', { name: 'Enregistrer les modifications', exact: true })
      .click()
    await expect(page.getByText('Réservation modifiée.').first()).toBeVisible()
    await expect(myBookings.getByText('Atelier e2e modifié')).toBeVisible()

    await bookingRow(myBookings, 'Atelier e2e modifié')
      .getByRole('button', { name: /Modifier/ })
      .click()
    const deleteDialog = page.getByRole('dialog', { name: `Ma réservation — ${seed.rooms.small}` })
    await deleteDialog.getByRole('button', { name: 'Supprimer', exact: true }).click()
    // La confirmation est un AlertDialog Radix rendu dans un portail à la
    // racine du document, donc hors du DOM de la modale de réservation.
    await page
      .getByRole('alertdialog')
      .getByRole('button', { name: 'Oui, supprimer', exact: true })
      .click()
    await expect(page.getByText('Réservation annulée.').first()).toBeVisible()
  })

  test('salle événementielle : lecture seule + invitation à nous contacter', async ({ page }) => {
    // Plus de créneau cliquable par salle depuis C14 (grille unique) : le
    // renvoi vers Ecoworking est un bouton explicite à côté des chips.
    await page.getByRole('button', { name: `Réserver ${seed.rooms.event}` }).click()

    // Le lien est cherché DANS le bandeau : la top bar du shell (C14) porte
    // désormais son propre « Nous contacter », visible en desktop.
    const notice = page.getByRole('status').filter({ hasText: 'Pour réserver cette salle' })
    await expect(notice).toBeVisible()
    await expect(notice.getByRole('link', { name: 'Nous contacter' })).toHaveAttribute(
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
