import { loginViaApi } from './support/auth'
import { expect, test } from './support/fixtures'
import { seed } from './support/seed'

/**
 * Finition C14 (U5, CLAUDE.md §3.5) : complète la couverture axe-core déjà
 * répartie entre `a11y.spec.ts` et les specs fonctionnelles (`bookings`,
 * `directory`, `documents`, `invoices`, `announcements`) sur trois axes que
 * ces suites ne couvraient pas encore :
 *
 * 1. Le **thème sombre** sur les écrans qui n'étaient audités qu'en clair
 *    (présence, profil — 4 onglets, notifications, tickets, plan des étages
 *    avec le panneau ouvert, connexion — étape « mot de passe oublié »).
 * 2. Les **pages publiques** (mentions légales, CGU, accessibilité),
 *    accessibles sans session, en clair et en sombre.
 * 3. La **bottom nav mobile** (390×844, Sheet « Plus » ouvert).
 *
 * Mécanique du thème sombre : le compte e2e par défaut n'a pas de préférence
 * `theme` (NULL) → la SPA suit `prefers-color-scheme`, que Playwright émule
 * via `colorScheme` (même mécanique que `a11y.spec.ts`).
 */

const AUTHENTICATED_PAGES: {
  name: string
  path: string
  heading: string | RegExp
  level?: number
}[] = [
  { name: 'presence', path: '/presence', heading: 'Ma présence', level: 1 },
  { name: 'notifications', path: '/', heading: /Bonjour/, level: 1 },
]

for (const theme of ['light', 'dark'] as const) {
  test.describe(`Audit a11y — écrans authentifiés (${theme})`, () => {
    test.use({ colorScheme: theme })

    test.beforeEach(async ({ page }) => {
      await loginViaApi(page)
    })

    for (const { name, path, heading, level } of AUTHENTICATED_PAGES) {
      test(`${name} — ${theme}`, async ({ page, checkA11y }) => {
        await page.goto(path)
        await expect(page.getByRole('heading', { level, name: heading })).toBeVisible()
        if (name === 'notifications') {
          await page.getByRole('button', { name: /^Notifications/ }).click()
          await expect(page.getByRole('region', { name: 'Notifications' })).toBeVisible()
        }
        if (name === 'presence') {
          await page.getByRole('button', { name: 'Marquer une absence' }).click()
          await expect(page.getByLabel('Date de début')).toBeVisible()
        }
        await checkA11y(`${name}-${theme}`)
      })
    }

    const PROFILE_TAB_LABELS = {
      profil: 'Profil',
      compte: 'Compte',
      preferences: 'Préférences',
      entreprise: 'Entreprise',
    } as const

    test(`profil — 4 onglets — ${theme}`, async ({ page, checkA11y }) => {
      for (const [tab, label] of Object.entries(PROFILE_TAB_LABELS)) {
        await page.goto(`/profile?tab=${tab}`)
        await expect(page.getByRole('heading', { level: 1, name: 'Mon profil' })).toBeVisible()
        await expect(page.getByRole('tab', { name: label })).toHaveAttribute(
          'aria-selected',
          'true',
        )
        await checkA11y(`profile-${tab}-${theme}`)
      }
    })

    test(`plan des étages — panneau ouvert — ${theme}`, async ({ page, checkA11y }) => {
      await page.goto('/directory/plan')
      await expect(page.getByRole('heading', { level: 1, name: 'Plan des étages' })).toBeVisible()

      const desk = page.getByRole('button', { name: /^Bureau 1 — / })
      await desk.focus()
      await page.keyboard.press('Enter')
      await expect(page.getByRole('heading', { name: 'Bureau 1', exact: true })).toBeVisible()

      await checkA11y(`floor-plan-panel-${theme}`)
    })

    test(`mot de passe oublié — ${theme}`, async ({ page, checkA11y }) => {
      await page.context().clearCookies()
      await page.goto('/login')
      await page.getByRole('button', { name: 'Mot de passe oublié ?' }).click()
      await expect(page.getByLabel('Email')).toBeVisible()
      await checkA11y(`forgot-password-${theme}`)
    })
  })

  test.describe(`Audit a11y — tickets & bureaux nomades — ${theme}`, () => {
    test.use({ colorScheme: theme })

    test(`tickets — ${theme}`, async ({ page, checkA11y }) => {
      await loginViaApi(page, seed.external.email)
      await page.goto('/tickets')
      await expect(
        page.getByRole('heading', { level: 1, name: 'Tickets & bureaux nomades' }),
      ).toBeVisible()
      await checkA11y(`tickets-${theme}`)
    })
  })

  test.describe(`Audit a11y — pages publiques — ${theme}`, () => {
    test.use({ colorScheme: theme })

    for (const { name, path, heading } of [
      { name: 'mentions-legales', path: '/mentions-legales', heading: 'Mentions légales' },
      { name: 'cgu', path: '/cgu', heading: 'Conditions générales d’utilisation' },
      {
        name: 'accessibilite',
        path: '/accessibilite',
        heading: 'Déclaration d’accessibilité',
      },
    ]) {
      test(`${name} — ${theme}`, async ({ page, checkA11y }) => {
        await page.goto(path)
        await expect(page.getByRole('heading', { level: 1, name: heading })).toBeVisible()
        await checkA11y(`${name}-${theme}`)
      })
    }
  })

  test.describe(`Audit a11y — bottom nav mobile (390×844) — ${theme}`, () => {
    test.use({ colorScheme: theme, viewport: { width: 390, height: 844 } })

    test(`Sheet « Plus » ouvert — ${theme}`, async ({ page, checkA11y }) => {
      await loginViaApi(page)
      await page.goto('/')
      await expect(page.getByRole('heading', { level: 1, name: /Bonjour/ })).toBeVisible()

      const nav = page.getByRole('navigation', { name: 'Navigation rapide' })
      await expect(nav).toBeVisible()
      await nav.getByRole('button', { name: 'Plus' }).click()
      await expect(page.getByRole('heading', { name: 'Plus' })).toBeVisible()
      await expect(page.getByRole('button', { name: 'Déconnexion' })).toBeVisible()

      await checkA11y(`bottom-nav-plus-${theme}`)
    })
  })
}

/**
 * Mobile 390 px sans scroll horizontal (CLAUDE.md §3.5 : tailles de police
 * relatives, zoom 200 % sans casse de mise en page — corollaire direct sur un
 * viewport étroit). `scrollWidth > clientWidth` détecte un débordement
 * horizontal, quelle que soit sa cause (image non contrainte, largeur fixe,
 * grille qui ne réduit pas).
 */
test.describe('Mobile 390 px — pas de scroll horizontal', () => {
  test.use({ viewport: { width: 390, height: 844 } })

  test.beforeEach(async ({ page }) => {
    await loginViaApi(page)
  })

  for (const { name, path, heading } of [
    { name: 'accueil', path: '/', heading: /Bonjour/ },
    { name: 'réservations', path: '/bookings', heading: 'Réservations de salles' },
    { name: 'factures', path: '/invoices', heading: 'Mes factures' },
    { name: 'annuaire', path: '/directory', heading: 'Annuaire des coworkers' },
  ]) {
    test(name, async ({ page }) => {
      await page.goto(path)
      await expect(page.getByRole('heading', { level: 1, name: heading })).toBeVisible()

      const overflow = await page.evaluate(() => {
        const html = document.documentElement
        return html.scrollWidth - html.clientWidth
      })
      expect(overflow).toBeLessThanOrEqual(0)
    })
  }
})

/**
 * Zoom 200 % (CLAUDE.md §3.5) : approximé par un viewport 640 px de large,
 * équivalent à un zoom 200 % d'un desktop 1280 px — méthode fiable en
 * Playwright (`page.evaluate(() => document.body.style.zoom = ...)` n'est pas
 * supporté de façon homogène par Chromium headless pour recalculer le layout
 * de mise en page responsive basée sur `matchMedia`/largeur de viewport).
 * Vérifie l'absence de scroll horizontal ET que le lien d'évitement + la
 * bottom nav restent utilisables.
 */
test.describe('Zoom 200 % (viewport 640 px)', () => {
  test.use({ viewport: { width: 640, height: 800 } })

  test('accueil : pas de scroll horizontal, skip link et bottom nav opérants', async ({ page }) => {
    await loginViaApi(page)
    await page.goto('/')
    await expect(page.getByRole('heading', { level: 1, name: /Bonjour/ })).toBeVisible()

    const overflow = await page.evaluate(
      () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
    )
    expect(overflow).toBeLessThanOrEqual(0)

    // Lien d'évitement : premier Tab depuis le haut de page.
    await page.keyboard.press('Tab')
    await expect(page.getByRole('link', { name: 'Aller au contenu principal' })).toBeFocused()

    // Bottom nav toujours opérante (640 px < breakpoint md = 768 px).
    const nav = page.getByRole('navigation', { name: 'Navigation rapide' })
    await expect(nav).toBeVisible()
    await nav.getByRole('link', { name: 'Réservations' }).click()
    await expect(
      page.getByRole('heading', { level: 1, name: 'Réservations de salles' }),
    ).toBeVisible()
  })
})

/**
 * `prefers-reduced-motion: reduce` (CLAUDE.md §3.5) : le bloc CSS global
 * (`styles.css`) ramène `animation-duration`/`transition-duration` à 0,01 ms
 * pour tout élément. On l'exerce sur une vraie animation déclenchée par
 * l'utilisateur — l'ouverture d'un `Sheet` (plan des étages, panneau bureau)
 * — plutôt que sur un élément statique, pour vérifier que la préférence
 * s'applique bien à un composant Radix animé (`data-state=open`) et pas
 * seulement en théorie.
 */
test.describe('prefers-reduced-motion: reduce', () => {
  test('le panneau de détail bureau ne joue aucune animation mesurable', async ({ page }) => {
    // `reducedMotion` n'est pas un test option de premier niveau dans cette
    // version de Playwright (seulement `contextOptions.reducedMotion` en
    // config globale) : on émule la préférence à l'exécution, comme suggéré
    // par CLAUDE.md §3.5.
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await loginViaApi(page)
    await page.goto('/directory/plan')
    await expect(page.getByRole('heading', { level: 1, name: 'Plan des étages' })).toBeVisible()

    const desk = page.getByRole('button', { name: /^Bureau 1 — / })
    await desk.click()
    const panel = page.getByRole('heading', { name: 'Bureau 1', exact: true })
    await expect(panel).toBeVisible()

    const durations = await panel.evaluate((node) => {
      let element: Element | null = node
      const values: string[] = []
      while (element) {
        const style = getComputedStyle(element)
        values.push(style.animationDuration, style.transitionDuration)
        element = element.parentElement
      }
      return values
    })

    for (const duration of durations) {
      // `0,01ms` (styles.css) ou `0s` par défaut : jamais une durée perceptible.
      const ms = duration.includes('ms')
        ? Number.parseFloat(duration)
        : Number.parseFloat(duration) * 1000
      expect(ms).toBeLessThanOrEqual(1)
    }
  })
})
