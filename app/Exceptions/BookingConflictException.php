<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Levée quand un créneau de salle est déjà pris (anti-double-booking, §5.4).
 * Traduite en HTTP 409 Conflict côté API.
 */
final class BookingConflictException extends RuntimeException
{
    public static function forSlot(): self
    {
        return new self('Ce créneau est déjà réservé pour cette salle.');
    }
}
