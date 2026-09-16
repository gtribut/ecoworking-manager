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

/**
 * Jour de la semaine **suivante** (0 = lundi), au format YYYY-MM-DD, calculé
 * DANS le navigateur pour partager son fuseau. La semaine suivante est
 * entièrement dans le futur : aucun créneau n'y est refusé pour cause de passé.
 */
async function dayOfNextWeek(page: Page, weekdayOffset: number): Promise<string> {
  return page.evaluate((offset) => {
    const day = new Date()
    day.setHours(0, 0, 0, 0)
    day.setDate(day.getDate() - ((day.getDay() + 6) % 7) + 7 + offset)
    const month = String(day.getMonth() + 1).padStart(2, '0')
    const date = String(day.getDate()).padStart(2, '0')
    return `${day.getFullYear()}-${month}-${date}`
  }, weekdayOffset)
}

/**
 * Passe à la période suivante ET attend que ses réservations soient arrivées :
 * sans ça, la grille se re-rend au milieu du glisser et FullCalendar perd la
 * sélection en cours.
 */
async function gotoNextPeriod(page: Page): Promise<void> {
  await Promise.all([
    page.waitForResponse(
      (response) => response.url().includes('/api/rooms/availability') && response.ok(),
    ),
    page.getByRole('button', { name: 'Période suivante' }).click(),
  ])
}

/**
 * Glisse sur la colonne d'un jour de la grille FullCalendar.
 *
 * La v7 hache ses classes : les seuls accroches stables sont les attributs
 * ARIA/données de la grille (`[role="gridcell"][data-date]` pour la colonne
 * d'un jour). Une colonne couvre exactement `slotMinTime`→`slotMaxTime`, la
 * position verticale d'une heure s'en déduit linéairement (vérifié sur le DOM :
 * un bloc de 10 h avec `slotMinTime` à 8 h est posé à `top: 2 × hauteur d'heure`).
 * Les heures sont passées en décimal et visent l'INTÉRIEUR d'un pas de 30 min
 * (`snapDuration`), pour que la sélection tombe sur des bornes prévisibles.
 */
async function dragOnDay(
  page: Page,
  options: { date: string; fromHour: number; toHour: number; dayStart: number; dayEnd: number },
): Promise<void> {
  const column = page.locator(`.fc [role="gridcell"][data-date="${options.date}"]`)
  await expect(column).toBeVisible()
  await column.scrollIntoViewIfNeeded()

  const box = await column.boundingBox()
  if (box === null) {
    throw new Error(`Colonne du ${options.date} sans géométrie`)
  }
  const span = options.dayEnd - options.dayStart
  const yAt = (hour: number) => box.y + ((hour - options.dayStart) / span) * box.height
  const x = box.x + box.width / 2

  await page.mouse.move(x, yAt(options.fromHour))
  await page.mouse.down()
  // Plusieurs déplacements : FullCalendar démarre le glisser au premier
  // mouvement significatif, un saut unique peut être avalé.
  await page.mouse.move(x, yAt((options.fromHour + options.toHour) / 2), { steps: 8 })
  await page.mouse.move(x, yAt(options.toHour), { steps: 8 })
  await page.mouse.up()
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
    await page.getByRole('button', { name: `${seed.rooms.event} : sur demande` }).click()

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

  test('glisser sur un créneau libre → modale pré-remplie', async ({ page }) => {
    // Mercredi de la semaine suivante : toujours dans le futur, donc jamais
    // refusé par `selectAllow`.
    const date = await dayOfNextWeek(page, 2)
    await gotoNextPeriod(page)

    // Bornes par défaut d'un membre résident : 08 h – 20 h. Le glisser vise
    // l'APRÈS-MIDI : un `mousedown` qui tombe sur un bloc existant démarre une
    // interaction d'événement, pas une sélection de créneau. Or le premier test
    // de ce fichier réserve `nextWeekday(7)` de 09 h à 10 h, et ce jour-là peut
    // tomber dans la semaine visée ici (jamais `nextWeekday(14)`/`(21)`, qui
    // sont au-delà). 15 h – 17 h est donc libre par construction.
    await dragOnDay(page, { date, fromHour: 15.1, toHour: 16.6, dayStart: 8, dayEnd: 20 })

    const dialog = page.getByRole('dialog', { name: 'Nouvelle réservation' })
    await expect(dialog).toBeVisible()
    await expect(dialog.getByLabel('Date')).toHaveValue(date)
    // Le pas de sélection est de 30 min : 15,1 h → borne basse 15:00,
    // 16,6 h → borne haute 17:00.
    await expect(dialog.getByLabel('Heure de début')).toHaveValue('15:00')
    await expect(dialog.getByLabel('Heure de fin')).toHaveValue('17:00')
    // Salle pré-remplie sur la première salle réservable affichée, modifiable.
    await expect(dialog.getByLabel('Salle', { exact: true })).not.toHaveValue('')
  })

  test('historique : onglet dédié aux réservations passées', async ({ page }) => {
    await page.getByRole('button', { name: 'Historique' }).click()

    await expect(
      page.getByRole('heading', { name: 'Historique de mes réservations' }),
    ).toBeVisible()
  })
})

test.describe('Réservation de salle — external', () => {
  test.beforeEach(async ({ page }) => {
    await loginViaApi(page, seed.external.email)
    await page.goto('/bookings')
    await expect(
      page.getByRole('heading', { level: 1, name: 'Réservations de salles' }),
    ).toBeVisible()
    await expect(page.getByRole('button', { name: 'Nouvelle réservation' })).toBeVisible()
  })

  test('glisser hors demi-journée n’ouvre rien, une demi-journée ouvre la modale', async ({
    page,
  }) => {
    const date = await dayOfNextWeek(page, 2)
    await gotoNextPeriod(page)

    // Grille 09 h – 18 h pour un external. La pause déjeuner 13 h – 14 h
    // n'appartient à aucune demi-journée (PRD §3.5.3) : `selectAllow` la refuse.
    // (Comme au-dessus, on reste l'après-midi : le matin de ce jour peut porter
    // la réservation 09 h – 10 h créée par le premier test du fichier.)
    await dragOnDay(page, { date, fromHour: 13.1, toHour: 13.8, dayStart: 9, dayEnd: 18 })
    // Assertion négative : on laisse à la modale le temps de s'ouvrir avant de
    // constater qu'elle ne s'ouvre pas.
    await page.waitForTimeout(300)
    await expect(page.getByRole('dialog')).toHaveCount(0)

    // L'après-midi, lui, reste sélectionnable.
    await dragOnDay(page, { date, fromHour: 14.1, toHour: 15.6, dayStart: 9, dayEnd: 18 })
    const dialog = page.getByRole('dialog', { name: 'Nouvelle réservation' })
    await expect(dialog).toBeVisible()
    await expect(dialog.getByLabel('Date')).toHaveValue(date)
    // Un external ne réserve qu'en demi-journées : l'après-midi est pré-coché.
    await expect(dialog.getByLabel('Après-midi (14 h – 18 h)')).toBeChecked()
    await expect(dialog.getByLabel('Créneau personnalisé')).toHaveCount(0)
  })
})
