# Maquettes C14

Artboards extraits du canvas Claude Design validé par Guillaume le 2026-09-16
(https://claude.ai/artifact/NtkNBV4suphHbzQ7AKhFjT) : `Main` (accueil desktop 1440 px), `Mobile`
(accueil mobile 390 px), `Reservations` / `ReservationsSombre` (agenda des salles, clair et
sombre). Ce sont des `.dc.html` statiques, référence visuelle pour les lots U1→U5 (ADR-0013,
[`../01-plan-c14.md`](../01-plan-c14.md)) — ni servis ni buildés dans le portail.

Le lien Google Fonts qu'ils embarquent (`fonts.googleapis.com`, police Geist) est propre à
l'outil de conception : le portail utilise `@fontsource-variable/geist` en auto-hébergement pour
raison RGPD (D5), pas de CDN Google Fonts en production.
