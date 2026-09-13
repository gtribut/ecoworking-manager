<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\Period;
use App\Models\Booking;
use App\Models\Resource;
use App\Support\FrenchHolidays;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Calcul de disponibilité des salles (C7.2, PRD §6.1).
 *
 *  - resident / additional : créneau libre 24/24 7/7 (gratuit, via abonnement) ;
 *  - external : demi-journées fixes (matin 9h–13h, après-midi 14h–18h), jours
 *    ouvrés uniquement (L–V hors fériés), payant via ticket.
 */
final class RoomAvailabilityService
{
    /** Demi-journées external : [période => [heure début, heure fin]]. */
    private const HALF_DAYS = [
        'morning' => [9, 13],
        'afternoon' => [14, 18],
    ];

    /** Un créneau est-il libre (aucune résa confirmée chevauchante) ? */
    public function isSlotFree(Resource $room, CarbonInterface $startsAt, CarbonInterface $endsAt, ?int $excludeBookingId = null): bool
    {
        return ! Booking::query()
            ->where('resource_id', $room->id)
            ->where('status', BookingStatus::Confirmed->value)
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->when($excludeBookingId !== null, fn ($q) => $q->whereKeyNot($excludeBookingId))
            ->exists();
    }

    /**
     * Demi-journées réservables par un external pour une salle à une date :
     * vide si jour non ouvré ; sinon les créneaux libres uniquement.
     *
     * @return list<array{period: Period, starts_at: CarbonImmutable, ends_at: CarbonImmutable}>
     */
    public function externalSlotsFor(Resource $room, CarbonInterface $date): array
    {
        if (! FrenchHolidays::isWorkingDay($date)) {
            return [];
        }

        $day = CarbonImmutable::parse($date->format('Y-m-d'));
        $slots = [];

        foreach (self::HALF_DAYS as $period => [$startHour, $endHour]) {
            $startsAt = $day->setTime($startHour, 0);
            $endsAt = $day->setTime($endHour, 0);

            if ($this->isSlotFree($room, $startsAt, $endsAt)) {
                $slots[] = [
                    'period' => Period::from($period),
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                ];
            }
        }

        return $slots;
    }

    /**
     * Bornes horaires d'une demi-journée external (matin/après-midi) pour une date.
     *
     * @return array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}
     */
    public function halfDayBounds(CarbonInterface $date, Period $period): array
    {
        [$startHour, $endHour] = self::HALF_DAYS[$period->value]
            ?? throw new \InvalidArgumentException('Demi-journée invalide : '.$period->value);

        $day = CarbonImmutable::parse($date->format('Y-m-d'));

        return [
            'starts_at' => $day->setTime($startHour, 0),
            'ends_at' => $day->setTime($endHour, 0),
        ];
    }

    /**
     * Un créneau external (date + demi-journée) est-il réservable ?
     * `$excludeBookingId` : réservation à ignorer (modification d'une résa
     * existante, qui ne doit pas entrer en conflit avec elle-même).
     */
    public function isExternalSlotBookable(Resource $room, CarbonInterface $date, Period $period, ?int $excludeBookingId = null): bool
    {
        if ($period === Period::FullDay || ! FrenchHolidays::isWorkingDay($date)) {
            return false;
        }

        [$startHour, $endHour] = self::HALF_DAYS[$period->value];
        $day = CarbonImmutable::parse($date->format('Y-m-d'));

        return $this->isSlotFree($room, $day->setTime($startHour, 0), $day->setTime($endHour, 0), $excludeBookingId);
    }
}
