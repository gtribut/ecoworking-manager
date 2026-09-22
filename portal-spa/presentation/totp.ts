import { createHmac } from 'node:crypto'

/**
 * TOTP (RFC 6238, SHA-1, 6 chiffres, pas de 30 s) — juste ce qu'il faut pour
 * passer le challenge 2FA Filament de l'admin de démo, enrôlé par serve.sh
 * avec un secret FIXE. Aucune dépendance : évite d'ajouter otplib pour un
 * script de documentation.
 */
export const PRESENTATION_TOTP_SECRET = 'PRESENTATIONTOTP2345ABCDEFGHIJKL'

const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'

function base32Decode(input: string): Buffer {
  let bits = ''
  for (const char of input.replace(/=+$/, '').toUpperCase()) {
    const value = BASE32.indexOf(char)
    if (value === -1) {
      throw new Error(`Caractère base32 invalide : ${char}`)
    }
    bits += value.toString(2).padStart(5, '0')
  }
  const bytes: number[] = []
  for (let i = 0; i + 8 <= bits.length; i += 8) {
    bytes.push(Number.parseInt(bits.slice(i, i + 8), 2))
  }
  return Buffer.from(bytes)
}

export function totp(secret: string = PRESENTATION_TOTP_SECRET, now: number = Date.now()): string {
  const counter = Math.floor(now / 1000 / 30)
  const message = Buffer.alloc(8)
  message.writeBigUInt64BE(BigInt(counter))
  const digest = createHmac('sha1', base32Decode(secret)).update(message).digest()
  const offset = digest[digest.length - 1] & 0x0f
  const binary =
    ((digest[offset] & 0x7f) << 24) |
    ((digest[offset + 1] & 0xff) << 16) |
    ((digest[offset + 2] & 0xff) << 8) |
    (digest[offset + 3] & 0xff)
  return String(binary % 1_000_000).padStart(6, '0')
}
