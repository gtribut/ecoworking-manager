import axios from 'axios'

/**
 * Client HTTP du portail (Sanctum mode SPA, ADR-0003).
 *
 * - `withCredentials` : envoie le cookie de session sur chaque requête.
 * - `withXSRFToken` : axios relit le cookie `XSRF-TOKEN` posé par
 *   `GET /sanctum/csrf-cookie` et le renvoie en en-tête `X-XSRF-TOKEN` sur les
 *   requêtes mutatives (POST/PATCH/DELETE) — protection CSRF.
 * - Même origine en prod (portail.ecoworking.fr) → `baseURL` relatif ; en dev
 *   le proxy Vite route vers le backend Sail.
 */
export const http = axios.create({
  baseURL: '/',
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

/** Amorce le cookie CSRF avant toute requête mutative (login, etc.). */
export async function ensureCsrfCookie(): Promise<void> {
  await http.get('/sanctum/csrf-cookie')
}
