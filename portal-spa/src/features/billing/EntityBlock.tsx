import { useId } from 'react'
import type { BillingEntity } from './types'

/** Objet de la demande de modification (PRD §3.4.3 / §3.6.4). */
const MODIFICATION_MAILTO =
  'mailto:contact@ecoworking.fr?subject=[backend ecowo] Demande de modification'

const regionNames =
  typeof Intl !== 'undefined' && 'DisplayNames' in Intl
    ? new Intl.DisplayNames(['fr'], { type: 'region' })
    : null

function countryLabel(code: string | null): string | null {
  if (!code) return null
  try {
    return regionNames?.of(code) ?? code
  } catch {
    return code
  }
}

function formatAddress(address: BillingEntity['address']): string[] {
  const cityLine = [address.postal_code, address.city].filter(Boolean).join(' ')

  return [address.line1, address.line2, cityLine, countryLabel(address.country)].filter(
    (line): line is string => Boolean(line && line.length > 0),
  )
}

/**
 * Bloc « Mon entreprise » (entreprise) / « Mes données de facturation »
 * (particulier facturé en nom propre) — récap non éditable de l'entité
 * juridique. Partagé entre le profil (PRD §3.4.3) et le module administratif
 * (PRD §3.6.4). Aucune donnée sensible : l'IBAN n'apparaît que par ses 4
 * derniers chiffres, jamais le mandat SEPA.
 */
export function EntityBlock({
  entity,
  showBillingDetails = false,
}: {
  entity: BillingEntity
  /**
   * Mode de paiement + IBAN-4 : prévus par le PRD dans le **module
   * administratif** (§3.6.4) seulement, pas dans le profil (§3.4.3).
   */
  showBillingDetails?: boolean
}) {
  const headingId = useId()
  const isIndividual = entity.entity_type === 'individual'
  const addressLines = formatAddress(entity.address)

  const rows: Array<[string, string | null]> = [
    [isIndividual ? 'Nom' : 'Raison sociale', entity.legal_name ?? entity.name],
    ['Forme juridique', entity.legal_form],
    ['SIRET', entity.siret],
    ['N° TVA intracommunautaire', entity.vat_number],
    ['Email de facturation', entity.billing_email],
    ['Mode de paiement', showBillingDetails ? (entity.payment_method_label ?? null) : null],
    ['IBAN', showBillingDetails && entity.iban_last4 ? `•••• ${entity.iban_last4}` : null],
  ]
  const filledRows = rows.filter(([, value]) => Boolean(value))

  return (
    <section
      aria-labelledby={headingId}
      className="space-y-3 rounded-lg border border-neutral-200 p-4 dark:border-neutral-800"
    >
      <h2 id={headingId} className="text-lg font-medium">
        {isIndividual ? 'Mes données de facturation' : 'Mon entreprise'}
      </h2>

      {filledRows.length === 0 && addressLines.length === 0 ? (
        <p className="text-sm text-neutral-600 dark:text-neutral-300">
          Aucune information enregistrée pour cette entité.
        </p>
      ) : (
        <dl className="grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
          {filledRows.map(([label, value]) => (
            <div key={label}>
              <dt className="text-neutral-500 dark:text-neutral-400">{label}</dt>
              <dd>{value}</dd>
            </div>
          ))}
          {addressLines.length > 0 && (
            <div>
              <dt className="text-neutral-500 dark:text-neutral-400">Adresse</dt>
              <dd>
                {addressLines.map((line) => (
                  <span key={line} className="block">
                    {line}
                  </span>
                ))}
              </dd>
            </div>
          )}
        </dl>
      )}

      <p className="text-xs text-neutral-500 dark:text-neutral-400">
        Ces informations sont gérées par Ecoworking.{' '}
        <a className="underline" href={MODIFICATION_MAILTO}>
          Demander une modification
        </a>
      </p>
    </section>
  )
}

/**
 * Liste des entités facturables (multi-entités : un bloc par entité, PRD
 * §3.6.4). Rien n'est rendu si l'utilisateur n'a aucune entité.
 */
export function EntityBlocks({
  entities,
  showBillingDetails = false,
}: {
  entities: BillingEntity[]
  showBillingDetails?: boolean
}) {
  if (entities.length === 0) return null

  return (
    <div className="space-y-4">
      {entities.map((entity) => (
        <EntityBlock key={entity.id} entity={entity} showBillingDetails={showBillingDetails} />
      ))}
    </div>
  )
}
