import { type APIRequestContext, expect } from '@playwright/test'

/**
 * Client minimal de l'API Mailpit (conteneur Sail `mailpit`).
 *
 * Depuis le conteneur Sail, l'API est joignable sur http://mailpit:8025 ;
 * en CI (service GitHub Actions), sur http://127.0.0.1:8025 — piloté par
 * E2E_MAILPIT_URL.
 */
const MAILPIT_URL = process.env.E2E_MAILPIT_URL ?? 'http://mailpit:8025'

interface MailpitMessageSummary {
  ID: string
  To: { Address: string }[]
  Subject: string
  Created: string
}

interface MailpitSearchResponse {
  messages: MailpitMessageSummary[]
}

/** Dernier message reçu pour un destinataire (polling — l'envoi est asynchrone). */
export async function fetchLatestMessageFor(
  request: APIRequestContext,
  recipient: string,
  options: { subject?: string; newerThan?: Date } = {},
): Promise<{ id: string; html: string }> {
  const deadline = Date.now() + 15_000

  while (Date.now() < deadline) {
    const search = await request.get(`${MAILPIT_URL}/api/v1/search`, {
      params: { query: `to:${recipient}`, limit: 10 },
    })
    expect(search.ok(), `Mailpit search → ${search.status()}`).toBeTruthy()
    const payload = (await search.json()) as MailpitSearchResponse

    const match = payload.messages.find(
      (message) =>
        (!options.subject || message.Subject.includes(options.subject)) &&
        (!options.newerThan || new Date(message.Created) >= options.newerThan),
    )

    if (match) {
      const detail = await request.get(`${MAILPIT_URL}/api/v1/message/${match.ID}`)
      expect(detail.ok(), `Mailpit message → ${detail.status()}`).toBeTruthy()
      const body = (await detail.json()) as { HTML: string; Text: string }
      return { id: match.ID, html: body.HTML || body.Text }
    }

    await new Promise((resolve) => setTimeout(resolve, 500))
  }

  throw new Error(`Aucun email reçu pour ${recipient} (15 s)`)
}

/** Extrait la première URL http(s) correspondant à un chemin donné dans un HTML d'email. */
export function extractLink(html: string, pathFragment: string): string {
  const matches = html.match(/https?:\/\/[^"'\s<>]+/g) ?? []
  const link = matches.find((url) => url.includes(pathFragment))
  if (!link) {
    throw new Error(`Aucun lien contenant « ${pathFragment} » dans l'email.`)
  }
  // Les URLs dans le HTML d'email peuvent être encodées (&amp;).
  return link.replaceAll('&amp;', '&')
}
