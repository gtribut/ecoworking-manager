import '@testing-library/jest-dom/vitest'
import { afterAll, afterEach, beforeAll } from 'vitest'
import { server } from './server'

// Le fuseau est figé sur Europe/Paris par `vite.config.ts` (`test.env.TZ`).
// Sans lui, les specs qui manipulent des horaires (calendrier des salles,
// réservations) échouent de 1 à 2 h sur une machine en UTC. On le vérifie ici
// pour échouer avec un message explicite plutôt qu'en cascade d'assertions.
// 2026-09-16 est en heure d'été : 12:00 UTC = 14:00 à Paris.
if (new Date('2026-09-16T12:00:00Z').getHours() !== 14) {
  throw new Error(
    `Fuseau de test inattendu (${Intl.DateTimeFormat().resolvedOptions().timeZone}) : ` +
      'les tests exigent TZ=Europe/Paris, normalement posé par vite.config.ts.',
  )
}

// jsdom n'implémente pas window.matchMedia, utilisé par le thème
// « Automatique (système) » (AuthContext) : stub minimal non-matching.
if (typeof window.matchMedia !== 'function') {
  Object.defineProperty(window, 'matchMedia', {
    writable: true,
    value: (query: string): MediaQueryList =>
      ({
        matches: false,
        media: query,
        onchange: null,
        addListener: () => undefined,
        removeListener: () => undefined,
        addEventListener: () => undefined,
        removeEventListener: () => undefined,
        dispatchEvent: () => false,
      }) as MediaQueryList,
  })
}

// jsdom n'implémente pas Element.scrollIntoView : no-op, le comportement réel
// (amener un message hors écran sous les yeux) ne se teste qu'en navigateur.
if (typeof Element.prototype.scrollIntoView !== 'function') {
  Element.prototype.scrollIntoView = () => undefined
}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
afterEach(() => server.resetHandlers())
afterAll(() => server.close())
