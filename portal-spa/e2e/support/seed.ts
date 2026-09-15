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
  /**
   * External (nomade) : seul rôle à porter `create-paid-booking`, donc seul
   * compte pouvant ouvrir /tickets. Hors annuaire (opt-out) pour ne pas
   * perturber directory.spec.ts.
   */
  external: {
    email: 'nomade.e2e@ecoworking.test',
    firstName: 'Léo',
    lastName: 'Nomade',
    /** Soldes crédités par E2eSeeder (constantes EXTERNAL_*_TICKETS). */
    deskTickets: 3,
    roomTickets: 2,
  },
  password: 'e2e-password',
  /** Entité juridique du membre e2e (bloc « Mon entreprise », PRD §3.6.4). */
  entity: {
    legalName: 'Atelier Numérique E2E',
  },
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
  /** Noms figés par database/seeders/ResourceSeeder.php (appelé par E2eSeeder). */
  rooms: {
    small: 'Salle de réunion 1',
    event: 'Salle événementielle',
  },
} as const
