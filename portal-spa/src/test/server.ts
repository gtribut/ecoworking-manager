import { setupServer } from 'msw/node'

/** Serveur MSW partagé ; les handlers sont définis par test via server.use(). */
export const server = setupServer()
