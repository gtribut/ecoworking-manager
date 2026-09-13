/** « 12 septembre 2026, 10:00 – 12:00 » — format partagé liste + dashboard. */
export function formatBookingRange(startIso: string, endIso: string): string {
  const start = new Date(startIso)
  const end = new Date(endIso)
  const day = start.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })
  const t = (d: Date) => d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
  return `${day}, ${t(start)} – ${t(end)}`
}
