<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Génère des flux iCalendar (RFC 5545) d'abonnement pour le portail membre
 * (PRD §3.5.8). Pull-based : le client agenda s'abonne via une URL à token et
 * rafraîchit à son rythme — aucune charge de push côté serveur, juste cette
 * sérialisation à la demande. Contenu : réservations de SALLES uniquement.
 *
 * Distinct de la sync Google sortante côté admin (BRIEF §10, push, → V1.5).
 */
final class IcsCalendarService
{
    /**
     * Borne temporelle des flux : les résas plus anciennes sont exclues
     * (l'historique complet à vie gonflerait le flux à chaque pull client).
     */
    private const int PAST_MONTHS = 3;

    /** Réservations de salles du membre (flux « Mes réservations »). */
    public function forUser(User $user): string
    {
        $bookings = Booking::query()
            ->confirmed()
            ->where('user_id', $user->id)
            ->where('starts_at', '>=', $this->horizon())
            ->with('resource')
            ->orderBy('starts_at')
            ->get();

        return $this->render('Mes réservations Ecoworking', $bookings);
    }

    /**
     * Réservations de salles de tous les membres rattachés aux mêmes entités
     * juridiques que le porteur du token (flux « Réservations de mon entité »,
     * coordination d'équipe). Scopé aux `companies` liées (périmètre du membre).
     */
    public function forEntity(User $user): string
    {
        $companyIds = $user->linkedCompanyIds();

        $bookings = Booking::query()
            ->confirmed()
            ->whereHas('user.memberProfile', function ($q) use ($companyIds): void {
                $q->whereIn('company_id', $companyIds->all());
            })
            ->where('starts_at', '>=', $this->horizon())
            ->with(['resource', 'user'])
            ->orderBy('starts_at')
            ->get();

        return $this->render('Réservations de mon entité — Ecoworking', $bookings);
    }

    /**
     * @param  Collection<int, Booking>  $bookings
     */
    private function render(string $calendarName, Collection $bookings): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Ecoworking//Portail//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.$this->escape($calendarName),
            'X-WR-TIMEZONE:Europe/Paris',
        ];

        $stamp = $this->formatUtc(now());

        foreach ($bookings as $booking) {
            $summary = $this->summaryFor($booking);

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:booking-'.$booking->id.'@ecoworking.fr';
            $lines[] = 'DTSTAMP:'.$stamp;
            $lines[] = 'DTSTART:'.$this->formatUtc($booking->starts_at);
            $lines[] = 'DTEND:'.$this->formatUtc($booking->ends_at);
            $lines[] = 'SUMMARY:'.$this->escape($summary);
            if ($booking->resource !== null) {
                $lines[] = 'LOCATION:'.$this->escape((string) $booking->resource->name);
            }
            $lines[] = 'STATUS:CONFIRMED';
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        // RFC 5545 : séparateur CRLF + repli des lignes > 75 octets.
        return implode("\r\n", array_map($this->fold(...), $lines))."\r\n";
    }

    private function summaryFor(Booking $booking): string
    {
        $room = $booking->resource?->name ?? 'Salle';
        $title = $booking->title;

        return $title !== null && $title !== ''
            ? "{$room} — {$title}"
            : (string) $room;
    }

    /** Format date-time UTC iCal (RFC 5545 : `YYYYMMDDTHHMMSSZ`). */
    private function formatUtc(\DateTimeInterface $date): string
    {
        return CarbonImmutable::instance($date)->utc()->format('Ymd\THis\Z');
    }

    /** Échappe les caractères spéciaux d'une valeur texte iCal (RFC 5545 §3.3.11). */
    private function escape(string $value): string
    {
        return str_replace(
            ['\\', "\n", ',', ';'],
            ['\\\\', '\\n', '\\,', '\\;'],
            $value,
        );
    }

    /** Plus vieille date de début incluse dans un flux. */
    private function horizon(): CarbonImmutable
    {
        return CarbonImmutable::now()->subMonths(self::PAST_MONTHS);
    }

    /**
     * Repli d'une ligne à 75 octets max (continuation préfixée d'un espace).
     * Découpe via `mb_strcut` : la coupe se fait en OCTETS mais jamais au
     * milieu d'un caractère UTF-8 (un `str_split` binaire scindait les accents,
     * produisant des séquences invalides chez certains clients).
     */
    private function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $folded = '';
        $first = true;

        while ($line !== '') {
            // 73 octets utiles : marge pour l'espace de continuation.
            $chunk = mb_strcut($line, 0, 73, 'UTF-8');
            if ($chunk === '') {
                $chunk = substr($line, 0, 73); // filet anti-boucle (contenu non-UTF-8)
            }
            $folded .= ($first ? '' : "\r\n ").$chunk;
            $line = substr($line, strlen($chunk));
            $first = false;
        }

        return $folded;
    }
}
