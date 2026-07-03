/**
 * Miroir TypeScript des constantes de database/seeders/E2eSeeder.php.
 * Toute modification du seeder doit être répercutée ici (et inversement).
 */
export const seed = {
  member: {
    email: 'membre.e2e@ecoworking.test',
    firstName: 'Emma',
    lastName: 'Membre',
  },
  other: {
    email: 'autre.e2e@ecoworking.test',
    firstName: 'Hugo',
    lastName: 'Discret',
  },
  password: 'e2e-password',
  invoice: {
    // Compteur pré-positionné à 90000 par E2eSeeder (isolation du storage
    // partagé avec le dev) → premier numéro émis : 90001.
    number: `EW-${new Date().getFullYear()}-90001`,
  },
  announcement: {
    news: 'Nouvelle machine à café au rez-de-chaussée',
    event: 'Apéro coworking du mois',
  },
  internalDocument: {
    title: 'Charte du coworking',
    version: '2.0',
  },
  rooms: {
    small: 'Salle de réunion 1',
  },
} as const
